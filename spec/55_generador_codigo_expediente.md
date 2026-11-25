---
tipo: spec
numero: 55
modulo: Expedientes
dominio: CASES
estado: PEND
tags: [spec, expedientes, pendiente]
relacionados:
  - "[[20_nueva_nemotecnica_codigo_expediente|Spec 20]]"
---
# [[55_generador_codigo_expediente|Spec 55]]: Generador de Código de Expediente (`YY-TT-AAAA-NNNN`)

**Versión:** 1.2
**Fecha:** 2026-05-11
**Estado:** Listo para implementación
**Relacionado:** [[20_nueva_nemotecnica_codigo_expediente|Spec 20]] (Nemotécnica original)

---

## Resumen Ejecutivo

El sistema ya genera `immigration_cases.case_number` con el formato `YY-TT-AAAA-NNNN` (implementado en Spec 20), pero el consecutivo `NNNN` está **scoped por la combinación `(YY + TT + AAAA)`**, lo que significa que cada combinación año-tipo-apellido reinicia su propio contador desde `0001`.

**El único cambio que requiere este spec:** ampliar el scope del consecutivo a **global por tenant** — un único contador que avanza independientemente del tipo de caso y del apellido del cliente.

### Antes vs después

| | Antes | Después |
|---|---|---|
| Consecutivo de `26-RT-RODR-0001` y `26-WK-SMIT-0001` | Independientes (cada combinación empieza en 1) | Secuenciales (si RODR es 0041, SMIT es 0042) |
| Scope del MAX() | `WHERE case_number LIKE '26-RT-RODR-%'` | `WHERE tenant_id = ?` |
| Tabla nueva requerida | — | **Ninguna** |
| Migraciones nuevas | — | **Ninguna** |
| Race condition | Retry loop + UNIQUE constraint | Mismo mecanismo (suficiente para el caso de uso) |

La tabla `case_code_sequences` propuesta en v1.0/v1.1 **no es necesaria**. El `tenant_id` ya existe en `immigration_cases` y el consecutivo se puede obtener con un `MAX()` sobre esa columna. El retry loop + la UNIQUE constraint existente son protección suficiente para el volumen de una firma de abogados.

### Decisiones clave

| # | Decisión | Justificación |
|---|---|---|
| D1 | **Cero tablas nuevas, cero columnas nuevas** | El `tenant_id` en `immigration_cases` es suficiente para derivar el MAX global por tenant. |
| D2 | **`NNNN` es global por tenant** | Un único contador que avanza sin importar tipo ni apellido. |
| D3 | **`TT` usa `case_types.code`** (ya implementado) | No se crea columna `abbreviation`. |
| D4 | **`case_number` sigue siendo el único identificador** | No se añade `case_code` ni ningún otro campo a `immigration_cases`. |
| D5 | **Retry loop + UNIQUE constraint se mantienen** | Red de seguridad ante la improbable colisión concurrente en este dominio. |
| D6 | **`CaseCodeService` queda deprecado** | Su única responsabilidad no trivial era el `lockForUpdate()` sobre una tabla que ya no existe. La lógica de formato y normalización vive en `CaseService::generateCaseNumber()` y `CaseCodeHelper`. |
| D7 | **`FolderService` y `SyncCaseFolderStructure` no cambian** | Siguen usando `$case->case_number`. El nuevo valor se propaga automáticamente. |

---

## 1. Cambio Único Requerido

### `CaseRepository::getNextSequence()` — nuevo scope

**Antes** (scope por combinación año+tipo+apellido):

```php
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

**Después** (scope global por tenant):

```php
public function getNextSequence(int $tenantId): int
{
    $max = ImmigrationCase::withTrashed()
        ->where('tenant_id', $tenantId)
        ->selectRaw('MAX(CAST(SUBSTRING_INDEX(case_number, \'-\', -1) AS UNSIGNED)) as max_seq')
        ->value('max_seq');

    return (int) ($max ?? 0) + 1;
}
```

El `withTrashed()` garantiza que los casos soft-deleted no liberen su número para ser reutilizado.

### `CaseRepositoryInterface` — actualizar firma

```php
// Antes:
public function getNextSequence(CaseType $caseType, string $year2, string $lastNameSlug): int;

// Después:
public function getNextSequence(int $tenantId): int;
```

### `CaseService::generateCaseNumber()` — actualizar llamada

```php
// Antes:
$sequence = $this->caseRepository->getNextSequence($caseType, $year2, $lastNameSlug);

// Después:
$tenantId = Auth::user()->tenant_id;
$sequence = $this->caseRepository->getNextSequence($tenantId);
```

El resto del método no cambia: el `sprintf`, el retry loop y el `existsByCaseNumber()` permanecen intactos.

---

## 2. Archivos Afectados

| Archivo | Acción | Detalle |
|---|---|---|
| `app/Repositories/Contracts/CaseRepositoryInterface.php` | **Modificar** | Actualizar firma de `getNextSequence()` |
| `app/Repositories/Eloquent/CaseRepository.php` | **Modificar** | Nuevo body de `getNextSequence()` con `WHERE tenant_id` |
| `app/Services/Case/CaseService.php` | **Modificar** | Una línea: cambiar la llamada a `getNextSequence()` |
| `app/Services/Case/CaseCodeService.php` | **Deprecar / Eliminar** | Ya no tiene responsabilidad útil; `CaseCodeHelper` + `CaseService` cubren todo |
| `app/Helpers/CaseCodeHelper.php` | **Sin cambios** | Sigue proveyendo `normalizeLastName()` |
| `app/Services/Document/FolderService.php` | **Verificar** | Confirmar que lee `$case->case_number` — no tocar |
| `app/Jobs/SyncCaseFolderStructure.php` | **Verificar** | Confirmar que lee `$case->case_number` — no tocar |

**No se crean:** migraciones, modelos, tablas, columnas, ni comandos Artisan.

---

## 3. Riesgos y Casos de Borde

| Caso | Comportamiento | Notas |
|---|---|---|
| Dos consultores crean un expediente al mismo tiempo | El segundo intento lee el mismo MAX y obtiene el mismo número → la UNIQUE constraint rechaza el INSERT → el retry loop incrementa y reintenta | Probabilidad muy baja en el dominio; el mecanismo existente es suficiente |
| `case_number` con formato anterior (Spec 20 legacy) | `SUBSTRING_INDEX(..., '-', -1)` extrae siempre el último segmento numérico, sea el formato viejo o el nuevo | Sin regresión |
| Casos sin `case_number` (`NULL`) | `CAST(NULL AS UNSIGNED)` → `0` → `MAX()` los ignora | Sin regresión |
| `NNNN` supera 9999 | `str_pad($seq, 4, '0', STR_PAD_LEFT)` crece a 5+ dígitos naturalmente | Sin cap; `case_number` es `VARCHAR` |
| Import masivo simultáneo | Retry loop serializa por UNIQUE; más lento pero correcto | Postpuesto a v2 si se requiere optimizar |

---

## 4. Checklist de Implementación

### Ya existe — no tocar

- [x] `app/Helpers/CaseCodeHelper.php` — `normalizeLastName()`
- [x] `CaseRepository::existsByCaseNumber()` — se mantiene
- [x] Retry loop en `CaseService::generateCaseNumber()`
- [x] UNIQUE constraint en `immigration_cases.case_number`
- [x] `case_types.code` usado como segmento `TT`

### Modificado ✅

- [x] `app/Repositories/Contracts/CaseRepositoryInterface.php`
  - [x] Firma actualizada: `getNextSequence(int $tenantId): int` — import `CaseType` eliminado
- [x] `app/Repositories/Eloquent/CaseRepository.php`
  - [x] Body reemplazado con PHP-side MAX extraction por `tenant_id` — import `CaseType` eliminado
- [x] `app/Services/Case/CaseService.php`
  - [x] Llamada actualizada: `getNextSequence(Auth::user()->tenant_id)`
  - [x] Docblock de `generateCaseNumber()` actualizado para reflejar scope global por tenant

### Eliminado ✅

- [x] `app/Services/Case/CaseCodeService.php` — eliminado
- [x] `app/Models/CaseCodeSequence.php` — eliminado
- [x] `database/migrations/2026_05_11_000001_add_abbreviation_to_case_types.php` — eliminado (no-op stub)
- [x] `database/migrations/2026_05_11_000002_create_case_code_sequences_table.php` — eliminado (never run)
- [x] `database/migrations/2026_05_11_000003_add_case_code_to_cases.php` — eliminado (never run)
- [x] `database/migrations/2026_05_11_000004_add_case_code_permissions.php` — eliminado (never run)

### Verificado ✅

- [x] `FolderService::createDefaultStructure()` usa `$case->case_number` — sin cambios requeridos
- [x] `SyncCaseFolderStructure` usa `$case->case_number` — sin cambios requeridos
- [x] Ningún caller de `getNextSequence()` con la firma anterior queda en el codebase
- [x] Ninguna referencia a `CaseCodeService` o `CaseCodeSequence` queda en el codebase
- [x] Tests pre-existentes (`CaseNumberGenerationTest`, 14 tests): todos pasan ✅
- [x] Fallos en `BaseFolderResolutionTest` (4 tests): pre-existentes, no introducidos por este spec

---

## 5. Tests

### Modificar tests existentes

- Actualizar cualquier test que llame a `getNextSequence()` con la firma vieja `(CaseType, string, string)`.
- Si existe `CaseCodeServiceTest`, eliminar o vaciar (la clase desaparece).

### Tests nuevos

**`tests/Feature/Cases/CaseCodeGlobalSequenceTest.php`**

- Crear dos casos del mismo tenant con distinto tipo y distinto cliente → consecutivos son `0001` y `0002` (no dos veces `0001`).
- Crear un caso en tenant A y otro en tenant B → ambos pueden tener `0001` sin colisión.
- Crear caso con apellido `Li` → segmento AAAA es `LIXX`.
- Crear caso con apellido `Núñez` → segmento AAAA es `NUNE`.

---

## 6. Advertencias Específicas del Proyecto (Gotchas)

- **G7 (Observers):** no se añaden observers. La generación sigue ocurriendo en `CaseService::generateCaseNumber()`, que es un método privado del servicio, no un observer.
- **G6 (i18n FLAT):** si `CaseCodeService` tenía la clave `case_type.code_missing`, verificar que ese string siga siendo lanzado desde `CaseService` o eliminarlo de los locales si ya no se usa.
- **`withTrashed()` es obligatorio:** el MAX debe incluir soft-deleted para que los números eliminados no se reutilicen.
- **`SUBSTRING_INDEX` es MySQL:** si los tests corren en SQLite, esta query fallará. Usar la alternativa PHP-side con `->pluck('case_number')->map(fn($n) => (int) last(explode('-', $n)))->max()` condicionalmente, o configurar los tests de feature con MySQL.
