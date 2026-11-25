---
tipo: spec
numero: 20
modulo: Expedientes
dominio: CASES
estado: HIST
tags: [spec, expedientes, historico]
relacionados:
  - "[[55_generador_codigo_expediente|Spec 55]]"
---
# Plan de Implementación: Nueva Nemotecnia — Código de Identificación de Expedientes

## Metadata
- **Fecha:** 2026-03-15
- **Versión:** 1.0
- **Arquitecto:** Claude (Architect Agent)
- **Epic:** 2.x — Gestión de Expedientes Core
- **Alcance:** Reemplazar la lógica de generación del `case_number` con una nueva nemotecnia que incorpora año (2 dígitos), código de tipo de caso, primeras 4 letras del apellido del cliente, y un consecutivo de 4 dígitos. Incluye helper de normalización, migración de registros históricos, y suite de pruebas unitarias.
- **Archivos afectados:**
  - `app/Services/Case/CaseService.php` (método `generateCaseNumber`)
  - `app/Repositories/Eloquent/CaseRepository.php` (método `getNextSequence`)
  - `app/Helpers/CaseCodeHelper.php` (nuevo)
  - `database/migrations/XXXX_regenerate_case_numbers.php` (nuevo — comando artisan)
  - `tests/Unit/Helpers/CaseCodeHelperTest.php` (nuevo)
  - `tests/Unit/Services/CaseNumberGenerationTest.php` (nuevo)
- **Tiempo Estimado:** 4–6 horas
- **Estado:** ✅ COMPLETADO (2026-03-15)

---

## Contexto y Problema

### Formato actual
```
{AÑO4}-{TYPE_CODE}-{SEQUENCE5}
Ejemplo: 2026-ASYLUM-00042
```

El año se expresa en 4 dígitos, el tipo usa el `code` completo del `CaseType` (que puede ser largo: `EXPRESS_ENTRY`, `CITIZENSHIP_CERTIFICATE`), y el consecutivo es de 5 dígitos con scope por (año + tipo).

### Nuevo formato requerido
```
{AÑO2}-{TYPE_CODE}-{APELLIDO4}-{SEQUENCE4}
Ejemplo: 26-RT-RODR-0060
```

| Segmento | Descripción | Ejemplo |
|---|---|---|
| `AÑO2` | Últimos 2 dígitos del año actual | `26` |
| `TYPE_CODE` | Campo `code` del `CaseType` relacionado | `RT` |
| `APELLIDO4` | Primeros 4 caracteres del apellido del cliente, normalizados y en MAYÚSCULAS | `RODR` |
| `SEQUENCE4` | Contador incremental de 4 dígitos con padding de ceros, scope por (año + tipo + apellido) | `0060` |

### Impacto en registros históricos
Los expedientes existentes tendrán sus `case_number` regenerados. No se requiere mantener el código original. La regeneración se ejecuta mediante un Artisan command con transacción atómica, procesando los registros en orden cronológico para preservar la semántica del consecutivo.

---

## Análisis de Decisiones Arquitectónicas

| Dimensión | Decisión | Justificación |
|---|---|---|
| **Ubicación de la lógica** | `CaseService::generateCaseNumber()` + nuevo `CaseCodeHelper` | El Service Layer ya contiene esta responsabilidad. El helper es testeable de forma unitaria independiente del ORM. Un Observer sería implícito y difícil de testear; un SP acoplaría al motor de BD. |
| **Scope del consecutivo** | Por (año + tipo + apellido-normalizado) | Garantiza unicidad dentro del subgrupo natural. Dos clientes `Rodriguez` y `Rodrigo` comparten el slug `RODR` y su consecutivo es compartido por diseño — eso es correcto. |
| **Concurrencia** | Optimistic locking + retry loop (ya implementado) + `UNIQUE` constraint en BD | El retry loop existente en `CaseService` es suficiente. El `UNIQUE` constraint en BD actúa como red de seguridad final. Máximo 5 reintentos antes de lanzar excepción. |
| **Normalización del apellido** | `CaseCodeHelper::normalizeLastName()` con pipeline determinista | El orden de operaciones es fijo y predecible. Ver sección siguiente. |
| **Apellidos cortos (< 4 chars)** | Pad con `X` hasta 4 caracteres | `Ng` → `NGXX`, `Li` → `LIXX`. Simple, sin ambigüedad, reversible visualmente. |
| **Preview en el Wizard** | Placeholder visual `{AÑO2}-{TYPE}-{APELL}-????` | El consecutivo real requiere una query a BD. Mostrar un código provisional que luego cambia genera confusión. El código definitivo llega en la respuesta del `POST /api/cases`. |
| **Migración histórica** | Artisan command con `DB::transaction` + `ORDER BY created_at` | Garantiza que el consecutivo se asigne en orden cronológico. El command es idempotente: puede re-ejecutarse de forma segura. |
| **Índice de BD** | `UNIQUE` sobre `case_number` (ya existe) | Sin cambios necesarios. El índice único en `VARCHAR` tiene impacto mínimo en una tabla de gestión de casos. |

---

## Regla de Normalización del Apellido

La función `CaseCodeHelper::normalizeLastName(string $lastName): string` aplica el siguiente pipeline **en orden estricto**:

```
1. iconv('UTF-8', 'ASCII//TRANSLIT', $lastName)  → elimina acentos y diacríticos
2. strtoupper()                                    → mayúsculas
3. preg_replace('/[^A-Z]/', '', $slug)             → elimina todo lo que no sea A-Z
   (espacios, guiones, apóstrofes, números)
4. str_pad($slug, 4, 'X')                          → pad con 'X' si menos de 4 letras
5. substr($slug, 0, 4)                             → truncar a exactamente 4 caracteres
```

### Tabla de casos cubiertos

| Apellido de entrada | Tras pipeline | Resultado |
|---|---|---|
| `Rodriguez` | `RODRIGUEZ` → `RODR` | `RODR` |
| `Gómez` | `GOMEZ` → `GOME` | `GOME` |
| `De la Cruz` | `DELACRUZ` → `DELA` | `DELA` |
| `García-Pérez` | `GARCÍAPEREZ` → `GARC` | `GARC` |
| `O'Brien` | `OBRIEN` → `OBRI` | `OBRI` |
| `Ng` | `NG` → pad → `NGXX` | `NGXX` |
| `Li` | `LI` → pad → `LIXX` | `LIXX` |
| `Smith` | `SMITH` → `SMIT` | `SMIT` |
| `A` | `A` → pad → `AXXX` | `AXXX` |
| `Müller` | `MULLER` → `MULL` | `MULL` |

---

## Diseño Técnico

### 1. `CaseCodeHelper` (nuevo archivo)

```php
// app/Helpers/CaseCodeHelper.php

namespace App\Helpers;

class CaseCodeHelper
{
    /**
     * Normalize a last name to a 4-character uppercase slug.
     * Pipeline: transliterate → uppercase → letters only → pad/truncate to 4.
     */
    public static function normalizeLastName(string $lastName): string
    {
        // 1. Transliterate accents/diacritics to ASCII equivalents
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lastName);

        // 2. Uppercase
        $slug = strtoupper($slug);

        // 3. Keep only A-Z letters (remove spaces, hyphens, apostrophes, numbers)
        $slug = preg_replace('/[^A-Z]/', '', $slug);

        // 4. Pad with 'X' if shorter than 4 characters
        $slug = str_pad($slug, 4, 'X');

        // 5. Truncate to exactly 4 characters
        return substr($slug, 0, 4);
    }
}
```

### 2. `CaseRepository::getNextSequence()` (modificado)

La firma cambia para recibir también el slug del apellido:

```php
/**
 * Get the next sequence number for the new case number format.
 * Scope: (year2, type_code, last_name_slug)
 *
 * Pattern: '{year2}-{code}-{slug}-%'
 * Example: '26-RT-RODR-%'
 */
public function getNextSequence(CaseType $caseType, string $year2, string $lastNameSlug): int
{
    $pattern = "{$year2}-{$caseType->code}-{$lastNameSlug}-%";

    $caseNumbers = ImmigrationCase::withTrashed()
        ->where('case_number', 'like', $pattern)
        ->pluck('case_number');

    if ($caseNumbers->isEmpty()) {
        return 1;
    }

    $maxSequence = $caseNumbers->map(function ($caseNumber) {
        $parts = explode('-', $caseNumber);
        return (int) end($parts);
    })->max();

    return $maxSequence + 1;
}
```

### 3. `CaseService::generateCaseNumber()` (modificado)

La firma requiere el modelo `Client` para extraer el apellido:

```php
use App\Helpers\CaseCodeHelper;
use App\Models\Client;
use App\Models\CaseType;

/**
 * Generate a unique case number.
 * Format: {YY}-{TYPE_CODE}-{LAST4}-{SEQUENCE4}
 * Example: 26-RT-RODR-0060
 */
private function generateCaseNumber(CaseType $caseType, Client $client): string
{
    $year2       = date('y');                                          // e.g. '26'
    $lastNameSlug = CaseCodeHelper::normalizeLastName($client->last_name);
    $sequence    = $this->caseRepository->getNextSequence($caseType, $year2, $lastNameSlug);

    $caseNumber = sprintf('%s-%s-%s-%04d', $year2, $caseType->code, $lastNameSlug, $sequence);

    // Safety net: retry on collision (race condition protection)
    $attempts = 0;
    while ($this->caseRepository->existsByCaseNumber($caseNumber) && $attempts < 5) {
        $sequence++;
        $attempts++;
        $caseNumber = sprintf('%s-%s-%s-%04d', $year2, $caseType->code, $lastNameSlug, $sequence);
    }

    if ($this->caseRepository->existsByCaseNumber($caseNumber)) {
        throw new \RuntimeException("No se pudo generar un código único para el expediente después de {$attempts} intentos.");
    }

    return $caseNumber;
}
```

### 4. Ajuste en `CaseService::createCase()`

El método `generateCaseNumber` recibe ahora el `Client`. El cliente ya es accesible dentro de `createCase` a través de `$data['client_id']`:

```php
// Dentro de createCase(), reemplazar la línea:
$data['case_number'] = $this->generateCaseNumber($caseType);

// Por:
$client = $this->clientRepository->findById($data['client_id']);
$data['case_number'] = $this->generateCaseNumber($caseType, $client);
```

---

## Migración de Registros Históricos

### Artisan Command

```php
// app/Console/Commands/RegenerateCaseNumbers.php

namespace App\Console\Commands;

use App\Helpers\CaseCodeHelper;
use App\Models\ImmigrationCase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RegenerateCaseNumbers extends Command
{
    protected $signature   = 'cases:regenerate-numbers {--dry-run : Show changes without applying them}';
    protected $description = 'Regenerate all case numbers using the new nemonic format YY-TYPE-LAST4-SEQUENCE';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $cases  = ImmigrationCase::with(['caseType', 'client'])
            ->orderBy('created_at')
            ->get();

        $this->info("Procesando {$cases->count()} expedientes...");

        // Build new numbers preserving chronological order for sequence
        $counters = [];  // key: "YY-TYPE-SLUG" => next_sequence
        $changes  = [];

        foreach ($cases as $case) {
            $year2 = $case->created_at->format('y');
            $slug  = CaseCodeHelper::normalizeLastName($case->client->last_name);
            $key   = "{$year2}-{$case->caseType->code}-{$slug}";

            if (! isset($counters[$key])) {
                $counters[$key] = 1;
            }

            $newNumber = sprintf('%s-%04d', $key, $counters[$key]++);
            $changes[] = ['id' => $case->id, 'old' => $case->case_number, 'new' => $newNumber];
        }

        if ($dryRun) {
            $this->table(['ID', 'Código anterior', 'Código nuevo'], $changes);
            $this->warn('--dry-run: no se aplicaron cambios.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes) {
            foreach ($changes as $change) {
                ImmigrationCase::withTrashed()
                    ->where('id', $change['id'])
                    ->update(['case_number' => $change['new']]);
            }
        });

        $this->info("✅ {$cases->count()} códigos regenerados exitosamente.");
        return self::SUCCESS;
    }
}
```

**Uso:**
```bash
# Vista previa sin aplicar cambios
php artisan cases:regenerate-numbers --dry-run

# Aplicar la regeneración
php artisan cases:regenerate-numbers
```

---

## Preview en el Wizard (Frontend)

No se genera el código completo en el frontend. En cambio, el **paso de resumen** (último paso del wizard) muestra un placeholder que se construye con los datos ya disponibles:

```typescript
// En el componente de resumen del wizard
const caseNumberPreview = computed(() => {
    const year2 = new Date().getFullYear().toString().slice(-2);
    const typeCode = selectedCaseType.value?.code ?? '???';
    const lastName = client.value?.last_name ?? '';
    const slug = lastName
        ? lastName.toUpperCase().replace(/[^A-Z]/g, '').padEnd(4, 'X').slice(0, 4)
        : '????';
    return `${year2}-${typeCode}-${slug}-????`;
});
```

Se muestra como: **`26-RT-RODR-????`** con una nota visual indicando que el número definitivo se asignará al guardar.

Una vez creado el expediente, el `case_number` real llega en la respuesta de `POST /api/cases` y se muestra en la confirmación final.

---

## Suite de Pruebas

### `CaseCodeHelperTest` — Pruebas unitarias puras

```php
// tests/Unit/Helpers/CaseCodeHelperTest.php

namespace Tests\Unit\Helpers;

use App\Helpers\CaseCodeHelper;
use PHPUnit\Framework\TestCase;

class CaseCodeHelperTest extends TestCase
{
    /** @dataProvider lastNameProvider */
    public function test_normalize_last_name(string $input, string $expected): void
    {
        $this->assertSame($expected, CaseCodeHelper::normalizeLastName($input));
    }

    public static function lastNameProvider(): array
    {
        return [
            'normal 10 chars'     => ['Rodriguez',    'RODR'],
            'acentuado'           => ['Gómez',         'GOME'],
            'compuesto con espacio' => ['De la Cruz',  'DELA'],
            'con guión'           => ['García-Pérez',  'GARC'],
            'con apóstrofe'       => ["O'Brien",       'OBRI'],
            'corto 2 chars'       => ['Ng',            'NGXX'],
            'corto 1 char'        => ['A',             'AXXX'],
            'umlaut alemán'       => ['Müller',        'MULL'],
            'exactamente 4'       => ['Cruz',          'CRUZ'],
            'exactamente 5'       => ['Smith',         'SMIT'],
            'todo minúsculas'     => ['rodriguez',     'RODR'],
        ];
    }

    public function test_result_is_always_exactly_4_characters(): void
    {
        $inputs = ['X', 'Li', 'Ng', 'Cruz', 'Smith', 'Rodriguez', 'Vandenberghe'];
        foreach ($inputs as $input) {
            $this->assertSame(4, strlen(CaseCodeHelper::normalizeLastName($input)), "Failed for: {$input}");
        }
    }

    public function test_result_contains_only_uppercase_letters(): void
    {
        $inputs = ['García-Pérez', "O'Brien", 'De la Cruz'];
        foreach ($inputs as $input) {
            $slug = CaseCodeHelper::normalizeLastName($input);
            $this->assertMatchesRegularExpression('/^[A-Z]{4}$/', $slug, "Failed for: {$input}");
        }
    }
}
```

### `CaseNumberGenerationTest` — Prueba de integración del formato completo

```php
// tests/Unit/Services/CaseNumberGenerationTest.php

namespace Tests\Unit\Services;

use App\Helpers\CaseCodeHelper;
use PHPUnit\Framework\TestCase;

class CaseNumberGenerationTest extends TestCase
{
    /**
     * Verifica el caso de negocio principal del spec:
     * 2026 + Residencia Temporal (RT) + Rodriguez + 60 = "26-RT-RODR-0060"
     */
    public function test_canonical_example_from_spec(): void
    {
        $year2        = '26';
        $typeCode     = 'RT';
        $lastNameSlug = CaseCodeHelper::normalizeLastName('Rodriguez');
        $sequence     = 60;

        $caseNumber = sprintf('%s-%s-%s-%04d', $year2, $typeCode, $lastNameSlug, $sequence);

        $this->assertSame('26-RT-RODR-0060', $caseNumber);
    }

    /** @dataProvider caseNumberFormatProvider */
    public function test_format_matches_pattern(
        string $year2,
        string $typeCode,
        string $lastName,
        int    $sequence,
        string $expected
    ): void {
        $slug       = CaseCodeHelper::normalizeLastName($lastName);
        $caseNumber = sprintf('%s-%s-%s-%04d', $year2, $typeCode, $slug, $sequence);
        $this->assertSame($expected, $caseNumber);
    }

    public static function caseNumberFormatProvider(): array
    {
        return [
            'caso canónico del spec'      => ['26', 'RT',              'Rodriguez', 60,   '26-RT-RODR-0060'],
            'primer expediente del año'   => ['26', 'EXPRESS_ENTRY',   'Smith',     1,    '26-EXPRESS_ENTRY-SMIT-0001'],
            'apellido con acento'         => ['26', 'STUDENT',         'Gómez',     5,    '26-STUDENT-GOME-0005'],
            'apellido corto'              => ['26', 'ASYLUM',          'Ng',        3,    '26-ASYLUM-NGXX-0003'],
            'secuencia de 4 dígitos max'  => ['26', 'WORK',            'Brown',     9999, '26-WORK-BROW-9999'],
            'apellido compuesto'          => ['26', 'SPONSORSHIP',     'De la Cruz', 12,  '26-SPONSORSHIP-DELA-0012'],
        ];
    }

    public function test_sequence_padding_is_4_digits(): void
    {
        $caseNumber = sprintf('%s-%s-%s-%04d', '26', 'RT', 'RODR', 1);
        $this->assertStringEndsWith('0001', $caseNumber);

        $caseNumber = sprintf('%s-%s-%s-%04d', '26', 'RT', 'RODR', 99);
        $this->assertStringEndsWith('0099', $caseNumber);
    }

    public function test_year_is_2_digits(): void
    {
        $year2 = date('y');
        $this->assertSame(2, strlen($year2));
    }
}
```

---

## Checklist de Implementación

### Backend

- [x] **1. Crear `app/Helpers/CaseCodeHelper.php`** con `normalizeLastName()`
- [x] **2. Registrar el helper** en `composer.json` → `autoload.files` (si no se usa autoload de clase estática)
- [x] **3. Modificar `CaseRepository::getNextSequence()`** — nueva firma con `$year2` y `$lastNameSlug`
- [x] **4. Modificar `CaseService::generateCaseNumber()`** — nueva firma con `Client`, usar `CaseCodeHelper`, formato `sprintf('%s-%s-%s-%04d', ...)`
- [x] **5. Ajustar `CaseService::createCase()`** — pasar el cliente a `generateCaseNumber()`
- [x] **6. Crear Artisan command `RegenerateCaseNumbers`** y registrarlo en `app/Console/Kernel.php`
- [ ] **7. Ejecutar `php artisan cases:regenerate-numbers --dry-run`** en staging para validar
- [ ] **8. Ejecutar `php artisan cases:regenerate-numbers`** en producción dentro de una ventana de mantenimiento

### Tests

- [x] **9. Crear `tests/Unit/Helpers/CaseCodeHelperTest.php`** — 11+ casos cubriendo acentos, apellidos cortos, caracteres especiales
- [x] **10. Crear `tests/Unit/Services/CaseNumberGenerationTest.php`** — validar el caso canónico `26-RT-RODR-0060` y variantes
- [x] **11. Ejecutar `./vendor/bin/phpunit tests/Unit`** — todos deben pasar en verde
- [x] **12. Verificar que los tests de integración existentes** (`CaseServiceTest`, `StoreCaseRequestTest`) no rompen con el nuevo formato

### Frontend

- [x] **13. Añadir `caseNumberPreview` computed** en el paso de resumen del wizard (`StepSummary` o equivalente)
- [x] **14. Mostrar el placeholder visual** `26-RT-RODR-????` en el paso final antes de guardar
- [x] **15. Verificar que el `case_number` de la respuesta** se muestra correctamente en la pantalla de confirmación post-creación

### Validación Final

- [ ] **16. Crear un expediente de prueba** con cliente "Rodriguez" + tipo "RT" → verificar `26-RT-RODR-0001`
- [ ] **17. Crear un segundo expediente** con el mismo cliente y tipo → verificar `26-RT-RODR-0002`
- [ ] **18. Crear un expediente** con cliente "Ng" + tipo "ASYLUM" → verificar `26-ASYLUM-NGXX-0001`
- [ ] **19. Verificar en la lista de expedientes** que el nuevo formato se muestra correctamente

---

## Riesgos y Mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|---|---|---|---|
| Race condition en consecutivo | Baja | Alto | Retry loop + `UNIQUE` constraint en BD |
| `iconv` no disponible en el servidor | Muy baja | Medio | Fallback con `str_replace` de vocales acentuadas comunes |
| Apellido vacío o null en BD | Muy baja | Alto | Validación en `StoreCaseRequest` (`client_id` required); el client siempre tiene `last_name` |
| Colisión al regenerar históricos | Media | Medio | El command construye en memoria los nuevos números antes de escribir, garantizando unicidad antes del `UPDATE` |
| Expedientes con `case_number` referenciado en otros sistemas | N/A | Alto | El spec indica que no es necesario mantener el código original |
