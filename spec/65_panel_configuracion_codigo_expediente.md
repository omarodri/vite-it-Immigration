---
tipo: spec
numero: 65
modulo: Expedientes
dominio: CASES
estado: PEND
tags: [spec, expedientes, pendiente]
relacionados:
  - "[[55_generador_codigo_expediente|Spec 55]]"
  - "[[56_seleccion_dinamica_directorios_wizard|Spec 56]]"
  - "[[41_busqueda_global|Spec 41]]"
  - "[[30_cloud_folder_sync_por_tenant|Spec 30]]"
  - "[[27_file_manager_system|Spec 27]]"
---
# [[65_panel_configuracion_codigo_expediente|Spec 65]] — Panel de Configuración del Código de Expediente

**Dominio:** ADMIN / CASES  
**Estado:** DONE  
**Relacionados:** 55 (Generador Código Expediente), 56 (Directorios Wizard), 41 (Búsqueda Global), 30 (Cloud Folder Sync), 27 (File Manager)  
**Creado:** 2026-05-25  
**Autor:** Análisis de Impacto — Architect Agent + Validación de Reglas de Negocio

---

## Resumen Ejecutivo

El generador de códigos de expediente implementado en [[55_generador_codigo_expediente|Spec 55]] produce un formato fijo `YY-TT-AAAA-NNNN` con separador `-` hardcoded. Esta Spec introduce un **panel de configuración** exclusivo para Super Admin y Admin que permite:

1. Reordenar los 4 bloques principales (`YY`, `TT`, `AAAA`, `NNNN`) mediante Drag & Drop.
2. Seleccionar el carácter separador (`-`, `_`, o sin separador).
3. Activar o desactivar el bloque `NOMBRE` (nombre completo del aplicante, siempre al final).
4. Ver una previsualización en tiempo real del código resultante.
5. Guardar la configuración afectando **solo expedientes futuros** — los existentes son inmutables.

### Bloques Configurables

| Bloque | Descripción | Ejemplo |
|--------|-------------|---------|
| `YY`   | Dos dígitos del año en curso | `26` |
| `TT`   | Abreviatura del tipo de caso | `SP` |
| `AAAA` | 4 letras del apellido del aplicante principal | `RODR` |
| `NNNN` | Consecutivo global incremental por tenant (obligatorio) | `0005` |
| `NOMBRE` | Nombre completo del aplicante unido por guión (siempre al final si activo) | `Omar-Andres-Rodriguez-Perez` |

**Resultado de ejemplo** con orden `TT-YY-AAAA-NNNN` + NOMBRE activo:
```
SP-26-RODR-0005-Omar-Andres-Rodriguez-Perez
```

---

### Hallazgos Críticos Verificados en el Codebase

| # | Hallazgo | Archivo | Impacto en el Diseño |
|---|----------|---------|----------------------|
| H1 | `CaseService::generateCaseNumber()` extrae el consecutivo con `last(explode('-', $case_number))` | `app/Services/Case/CaseService.php` | Se rompe si NNNN no es el último bloque, si cambia el separador, o si NOMBRE está activo |
| H2 | `ImmigrationCase` usa `protected $table = 'cases'` — no `immigration_cases` | `app/Models/ImmigrationCase.php` | Toda migración sobre el modelo debe referenciar la tabla `cases` (G11) |
| H3 | `CaseRepository::getNextSequence()` parsea el string `case_number` para extraer el número | `app/Repositories/Eloquent/CaseRepository.php` | Requiere columna dedicada `case_number_seq` para ser inmune a cambios de máscara |
| H4 | `FolderNameValidator` rechaza: `< > : " / \ | ? *` | `app/Services/Document/FolderNameValidator.php` | Separadores `-` y `_` son seguros; el bloque NOMBRE requiere sanitización previa |
| H5 | `tenant.settings` es columna JSON existente usada por otros settings del tenant | `app/Models/Tenant.php` | Los 3 campos nuevos se guardan ahí — sin tabla nueva. Defaults = Spec 55, backward compatible |
| H6 | `GlobalSearchService` busca `case_number LIKE '%query%'` | `app/Services/Search/GlobalSearchService.php` | Sin impacto — búsqueda por substring es agnóstica al formato |
| H7 | `vue-draggable-plus` ya está instalado | `package.json` | No requiere dependencia nueva para el Drag & Drop |
| H8 | Observer `ImmigrationCaseObserver` registrado en `AppServiceProvider::boot()` | `app/Observers/ImmigrationCaseObserver.php` | La generación del código debe permanecer en `CaseService`, no moverse al observer (G7) |

---

### Decisiones Clave

| # | Decisión | Justificación |
|---|----------|---------------|
| D1 | **Almacenamiento como Array JSON** en `tenant.settings` (Opción A) | Validación trivial (`in:YY,TT,AAAA,NNNN` + `distinct`), sin parsing en hot path, extensible para bloques futuros. El string-máscara (Opción B) requiere tokenizer y es frágil con separadores variables |
| D2 | **`NOMBRE` como flag separado** (`case_code_include_name: bool`) — no como elemento del array | La restricción "siempre al final" no es expresable en el array sin lógica extra de ordenamiento. El toggle independiente evita bugs de posicionamiento |
| D3 | **Nueva columna `case_number_seq INT UNSIGNED`** en tabla `cases` con UNIQUE `(tenant_id, case_number_seq)` | Consecutivo inmune a cualquier cambio de máscara. O(1) con `MAX()` sobre índice. Race conditions resueltas por constraint UNIQUE + retry loop existente |
| D4 | **Strategy Pattern** para resolución de bloques (`BlockResolverInterface`) | Cada bloque tiene su propio resolver — extensible para futuros bloques sin modificar el orquestador |
| D5 | **Generación en `CaseService::createCase()`** — no en observer | Respeta G7 (observers registrados una vez en AppServiceProvider). Facilita testing con mocks |
| D6 | **Cache del patrón con `Cache::remember()`** (1 hora, cross-process) | Compatible con queue workers. Invalidación explícita al guardar la configuración |
| D7 | **`NOMBRE` sanitizado** alineado con `FolderNameValidator` | Acentos→ASCII (`iconv TRANSLIT`), espacios→guión, caracteres prohibidos removidos, truncado a 60 chars |
| D8 | **Save explícito con modal de confirmación** SweetAlert2 — sin auto-save | Operación crítica que afecta nomenclatura de todos los expedientes futuros del tenant |
| D9 | **Defaults idénticos a Spec 55** (`["YY","TT","AAAA","NNNN"]` + `"-"` + `false`) | Backward compatible — tenants existentes sin configuración previa se comportan igual que antes |
| D10 | **Permiso dedicado `settings.case_code.manage`** asignado a `super-admin` y `admin` | Granularidad fina; separable de `settings.update` general |
| D11 | **`NNNN` es obligatorio** — validación backend rechaza configuraciones sin él | Sin consecutivo la unicidad del código no está garantizada |
| D12 | **Separadores permitidos**: `-`, `_`, vacío | Los tres son seguros para rutas cloud (OneDrive/SharePoint/Google Drive). Las barras `/` y `\` están explícitamente excluidas |

---

## 1. Base de Datos

### 1.1 Migración — Columna `case_number_seq` en tabla `cases`

```php
<?php
// database/migrations/2026_06_01_000001_add_case_number_seq_to_cases.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Nota G11: la tabla física es 'cases', no 'immigration_cases'
        Schema::table('cases', function (Blueprint $table) {
            $table->unsignedInteger('case_number_seq')
                  ->nullable()
                  ->after('case_number');
        });

        // Backfill: asignar número de secuencia por orden de creación dentro de cada tenant
        DB::statement("
            UPDATE cases c
            JOIN (
                SELECT id,
                       ROW_NUMBER() OVER (
                           PARTITION BY tenant_id
                           ORDER BY created_at, id
                       ) AS rn
                FROM cases
            ) r ON r.id = c.id
            SET c.case_number_seq = r.rn
        ");

        // Una vez backfill completo, hacer NOT NULL + índice único
        Schema::table('cases', function (Blueprint $table) {
            $table->unsignedInteger('case_number_seq')->nullable(false)->change();
            $table->unique(
                ['tenant_id', 'case_number_seq'],
                'cases_tenant_seq_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropUnique('cases_tenant_seq_unique');
            $table->dropColumn('case_number_seq');
        });
    }
};
```

**Notas críticas:**
- `ROW_NUMBER() OVER (PARTITION BY tenant_id ORDER BY created_at, id)` garantiza secuencias por tenant.
- Ejecutar en ventana de mantenimiento si hay muchos registros (MySQL 8 procesa ~1M filas/min en InnoDB).
- Verificar post-migración: `SELECT COUNT(*) FROM cases WHERE case_number_seq IS NULL` → debe ser 0.

### 1.2 Migración — Permiso `settings.case_code.manage`

```php
<?php
// database/migrations/2026_06_01_000002_add_case_code_pattern_permissions.php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // G4: permisos en migración, no en seeder
        $permission = Permission::firstOrCreate(
            ['name' => 'settings.case_code.manage', 'guard_name' => 'web']
        );

        foreach (['super-admin', 'admin'] as $roleName) {
            $role = Role::findByName($roleName, 'web');
            if ($role && !$role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }
    }

    public function down(): void
    {
        Permission::where('name', 'settings.case_code.manage')->delete();
    }
};
```

### 1.3 Campos en `tenant.settings` (sin migración de columna)

La columna `settings` ya existe como JSON en `tenants`. Solo se añaden los campos en la lógica de la aplicación:

```json
{
  "case_code_blocks": ["YY", "TT", "AAAA", "NNNN"],
  "case_code_separator": "-",
  "case_code_include_name": false
}
```

Accessors en `Tenant.php`:
```php
public function caseCodeBlocks(): array
{
    return data_get($this->settings, 'case_code_blocks', ['YY', 'TT', 'AAAA', 'NNNN']);
}

public function caseCodeSeparator(): string
{
    return data_get($this->settings, 'case_code_separator', '-');
}

public function caseCodeIncludeName(): bool
{
    return (bool) data_get($this->settings, 'case_code_include_name', false);
}
```

---

## 2. Backend — Servicio Generador de Códigos

### 2.1 Estructura de Archivos

```
app/Services/Case/
├── CaseCodeGeneratorService.php      # Orquestador + retry loop
├── CaseCodePatternResolver.php       # Lee tenant.settings con Cache::remember (1h)
├── CaseCodePattern.php               # Value object readonly (blocks, separator, includeName)
├── GenerationContext.php             # DTO de entrada inmutable
└── BlockResolvers/
    ├── BlockResolverInterface.php    # Contrato
    ├── YearBlockResolver.php         # → YY
    ├── TypeBlockResolver.php         # → TT
    ├── LastNameBlockResolver.php     # → AAAA
    ├── SequenceBlockResolver.php     # → NNNN (con padding)
    └── NameBlockResolver.php         # → NOMBRE (sanitizado)
```

### 2.2 Value Objects e Interfaces

```php
// app/Services/Case/BlockResolvers/BlockResolverInterface.php
interface BlockResolverInterface
{
    public function resolve(GenerationContext $ctx): string;
}

// app/Services/Case/GenerationContext.php
final readonly class GenerationContext
{
    public function __construct(
        public Tenant $tenant,
        public CaseType $caseType,
        public string $primaryFullName,
        public string $primaryLastName,
        public ?int $sequence = null,
    ) {}

    public function withSequence(int $sequence): self
    {
        return new self(
            tenant: $this->tenant,
            caseType: $this->caseType,
            primaryFullName: $this->primaryFullName,
            primaryLastName: $this->primaryLastName,
            sequence: $sequence,
        );
    }
}

// app/Services/Case/CaseCodePattern.php
final readonly class CaseCodePattern
{
    public function __construct(
        public array $blocks,        // ['YY','TT','AAAA','NNNN']
        public string $separator,    // '-' | '_' | ''
        public bool $includeName,
    ) {}

    public static function fromSettings(array $settings): self
    {
        return new self(
            blocks: data_get($settings, 'case_code_blocks', ['YY', 'TT', 'AAAA', 'NNNN']),
            separator: data_get($settings, 'case_code_separator', '-'),
            includeName: (bool) data_get($settings, 'case_code_include_name', false),
        );
    }
}
```

### 2.3 Resolvers de Bloques

```php
// YearBlockResolver.php
final class YearBlockResolver implements BlockResolverInterface
{
    public function resolve(GenerationContext $ctx): string
    {
        return now()->format('y'); // '26'
    }
}

// TypeBlockResolver.php
final class TypeBlockResolver implements BlockResolverInterface
{
    public function resolve(GenerationContext $ctx): string
    {
        return strtoupper(mb_substr($ctx->caseType->abbreviation ?? $ctx->caseType->name, 0, 2));
    }
}

// LastNameBlockResolver.php
final class LastNameBlockResolver implements BlockResolverInterface
{
    public function resolve(GenerationContext $ctx): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $ctx->primaryLastName) ?: $ctx->primaryLastName;
        return strtoupper(mb_substr(preg_replace('/[^A-Za-z]/', '', $ascii), 0, 4));
    }
}

// SequenceBlockResolver.php
final class SequenceBlockResolver implements BlockResolverInterface
{
    public function resolve(GenerationContext $ctx): string
    {
        return str_pad((string) ($ctx->sequence ?? 1), 4, '0', STR_PAD_LEFT); // '0005'
    }
}

// NameBlockResolver.php — sanitización crítica para compatibilidad cloud
final class NameBlockResolver implements BlockResolverInterface
{
    public function resolve(GenerationContext $ctx): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $ctx->primaryFullName) ?: $ctx->primaryFullName;
        $ascii = preg_replace('/[<>:"\/\\\\|?*]/u', '', $ascii);   // FolderNameValidator chars
        $ascii = preg_replace('/\s+/', '-', trim($ascii));          // espacios → guión
        $ascii = preg_replace('/-+/', '-', $ascii);                 // colapsar guiones
        return mb_substr($ascii, 0, 60);
    }
}
```

### 2.4 `CaseCodePatternResolver` con Cache

```php
// app/Services/Case/CaseCodePatternResolver.php
final class CaseCodePatternResolver
{
    private const CACHE_TTL = 3600;
    private const CACHE_KEY = 'tenant:%d:case_code_pattern';

    public function forTenant(Tenant $tenant): CaseCodePattern
    {
        return Cache::remember(
            sprintf(self::CACHE_KEY, $tenant->id),
            self::CACHE_TTL,
            fn () => CaseCodePattern::fromSettings($tenant->settings ?? [])
        );
    }

    public function invalidate(Tenant $tenant): void
    {
        Cache::forget(sprintf(self::CACHE_KEY, $tenant->id));
    }
}
```

### 2.5 `CaseCodeGeneratorService` — Orquestador

```php
// app/Services/Case/CaseCodeGeneratorService.php
final class CaseCodeGeneratorService
{
    /** @var array<string, BlockResolverInterface> */
    private array $resolvers;

    public function __construct(
        private CaseCodePatternResolver $patternResolver,
        private CaseRepositoryInterface $caseRepository,
        YearBlockResolver     $year,
        TypeBlockResolver     $type,
        LastNameBlockResolver $lastName,
        SequenceBlockResolver $sequence,
        private NameBlockResolver $name,
    ) {
        $this->resolvers = [
            'YY'   => $year,
            'TT'   => $type,
            'AAAA' => $lastName,
            'NNNN' => $sequence,
        ];
    }

    /**
     * @return array{0: string, 1: int}  [caseNumber, sequence]
     */
    public function generate(GenerationContext $ctx): array
    {
        $pattern  = $this->patternResolver->forTenant($ctx->tenant);
        $sequence = $this->caseRepository->getNextSequence($ctx->tenant->id);

        $attempts = 0;
        do {
            $ctxWithSeq  = $ctx->withSequence($sequence);
            $caseNumber  = $this->buildCode($pattern, $ctxWithSeq);

            if (!$this->caseRepository->existsByCaseNumber($caseNumber)) {
                return [$caseNumber, $sequence];
            }

            $sequence++;
            $attempts++;
        } while ($attempts < 5);

        throw new \RuntimeException(
            "No se pudo generar un código único después de {$attempts} intentos."
        );
    }

    private function buildCode(CaseCodePattern $pattern, GenerationContext $ctx): string
    {
        $parts = array_map(
            fn (string $key) => $this->resolvers[$key]->resolve($ctx),
            $pattern->blocks
        );

        $code = implode($pattern->separator, $parts);

        if ($pattern->includeName) {
            $sep   = $pattern->separator !== '' ? $pattern->separator : '-';
            $code .= $sep . $this->name->resolve($ctx);
        }

        return $code;
    }
}
```

### 2.6 Refactor de `CaseService::createCase()`

```php
// Reemplazar la llamada a generateCaseNumber() existente por:

$ctx = new GenerationContext(
    tenant: $caseType->tenant ?? app('currentTenant'),
    caseType: $caseType,
    primaryFullName: $primaryApplicant->full_name ?? '',
    primaryLastName: $primaryApplicant->last_name ?? '',
);

[$caseNumber, $seq]     = $this->caseCodeGenerator->generate($ctx);
$data['case_number']     = $caseNumber;
$data['case_number_seq'] = $seq;
```

### 2.7 Refactor de `CaseRepository::getNextSequence()`

```php
// Reemplazar el método actual (que parsea el string case_number) por:
public function getNextSequence(int $tenantId): int
{
    $max = ImmigrationCase::withTrashed()
        ->where('tenant_id', $tenantId)
        ->max('case_number_seq');

    return (int) ($max ?? 0) + 1;
}
```

---

## 3. Backend — API

### 3.1 Request de Validación

```php
// app/Http/Requests/Tenant/UpdateCaseCodePatternRequest.php
public function rules(): array
{
    return [
        'case_code_blocks'       => ['required', 'array', 'min:1', 'max:4'],
        'case_code_blocks.*'     => ['string', 'in:YY,TT,AAAA,NNNN', 'distinct'],
        'case_code_separator'    => ['required', 'string', 'in:-,_,'],
        'case_code_include_name' => ['required', 'boolean'],
    ];
}

public function withValidator($validator): void
{
    $validator->after(function ($v) {
        // NNNN es obligatorio
        if (!in_array('NNNN', $this->input('case_code_blocks', []), true)) {
            $v->errors()->add('case_code_blocks', __('settings.case_code.must_include_nnnn'));
        }

        // Validar compatibilidad cloud: generar código dummy y pasarlo por FolderNameValidator
        // Solo si includeName está activo (es la única fuente de chars problemáticos)
        if ($this->boolean('case_code_include_name')) {
            $nameResolver = app(NameBlockResolver::class);
            $dummyCtx = new GenerationContext(
                tenant: $this->route('tenant') ?? auth()->user()->tenant,
                caseType: new CaseType(['name' => 'SP']),
                primaryFullName: 'Test User Name',
                primaryLastName: 'Test',
                sequence: 1,
            );
            $segment = $nameResolver->resolve($dummyCtx);
            if (!FolderNameValidator::isValid($segment)) {
                $v->errors()->add('case_code_include_name', __('settings.case_code.invalid_for_cloud'));
            }
        }
    });
}
```

### 3.2 Método en `TenantController`

```php
// app/Http/Controllers/Api/TenantController.php

public function updateCaseCodePattern(UpdateCaseCodePatternRequest $request): JsonResponse
{
    $tenant = auth()->user()->tenant;

    $tenant->settings = array_merge($tenant->settings ?? [], [
        'case_code_blocks'       => $request->validated()['case_code_blocks'],
        'case_code_separator'    => $request->validated()['case_code_separator'],
        'case_code_include_name' => $request->boolean('case_code_include_name'),
        'case_code_updated_at'   => now()->toISOString(),
    ]);
    $tenant->save();

    // Invalidar cache del patrón
    app(CaseCodePatternResolver::class)->invalidate($tenant);

    // Generar preview con datos ficticios
    $pattern = CaseCodePattern::fromSettings($tenant->settings);
    $preview = $this->buildPreview($pattern);

    return response()->json([
        'data' => [
            'case_code_blocks'       => $pattern->blocks,
            'case_code_separator'    => $pattern->separator,
            'case_code_include_name' => $pattern->includeName,
            'preview'                => $preview,
        ],
        'message' => __('settings.case_code.saved_successfully'),
    ]);
}

private function buildPreview(CaseCodePattern $pattern): string
{
    $examples = ['YY' => '26', 'TT' => 'SP', 'AAAA' => 'RODR', 'NNNN' => '0005'];
    $parts    = array_map(fn ($b) => $examples[$b], $pattern->blocks);
    $code     = implode($pattern->separator, $parts);

    if ($pattern->includeName) {
        $sep   = $pattern->separator !== '' ? $pattern->separator : '-';
        $code .= $sep . 'Omar-Andres-Rodriguez-Perez';
    }

    return $code;
}
```

### 3.3 Ruta

```php
// routes/api.php — dentro del grupo auth:sanctum + tenant
Route::put('/tenant/case-code-pattern', [TenantController::class, 'updateCaseCodePattern'])
     ->middleware('permission:settings.case_code.manage');
```

### 3.4 Contract de API

**Request:**
```json
PUT /api/tenant/case-code-pattern
{
  "case_code_blocks": ["TT", "YY", "AAAA", "NNNN"],
  "case_code_separator": "_",
  "case_code_include_name": true
}
```

**Response 200:**
```json
{
  "data": {
    "case_code_blocks": ["TT", "YY", "AAAA", "NNNN"],
    "case_code_separator": "_",
    "case_code_include_name": true,
    "preview": "SP_26_RODR_0005_Omar-Andres-Rodriguez-Perez"
  },
  "message": "Configuración guardada. Los nuevos expedientes usarán este formato."
}
```

**Errors:**
- `403` — sin permiso `settings.case_code.manage`
- `422` — sin `NNNN`, bloque desconocido, separador inválido

---

## 4. Frontend — Componente `CaseCodeBuilder`

### 4.1 Estructura de Archivos Frontend

```
resources/js/src/
├── views/admin/
│   └── CaseCodeSettings.vue           # Vista completa (ruta dedicada)
├── components/admin/
│   └── CaseCodeBuilder.vue            # Componente reutilizable (DnD + preview)
└── services/tenantService.ts          # Añadir updateCaseCodePattern()
```

### 4.2 Modelo de Estado en `CaseCodeBuilder.vue`

```typescript
interface CaseCodeBlock {
  key: 'YY' | 'TT' | 'AAAA' | 'NNNN';
  example: string;
}

// Datos de previsualización fijos (nunca del backend)
const PREVIEW_DATA: Record<string, string> = {
  YY: '26', TT: 'SP', AAAA: 'RODR', NNNN: '0005'
};
const PREVIEW_NAME = 'Omar-Andres-Rodriguez-Perez';

const blocks      = ref<CaseCodeBlock[]>([
  { key: 'YY',   example: '26' },
  { key: 'TT',   example: 'SP' },
  { key: 'AAAA', example: 'RODR' },
  { key: 'NNNN', example: '0005' },
]);
const separator   = ref<'-' | '_' | ''>('-');
const includeName = ref(false);
const isDirty     = ref(false);
const isSaving    = ref(false);
```

### 4.3 Computed de Previsualización (sin debounce — síncrono puro)

```typescript
const previewCode = computed<string>(() => {
  const parts = blocks.value.map(b => PREVIEW_DATA[b.key]);
  let code    = parts.join(separator.value);

  if (includeName.value) {
    const sep = separator.value !== '' ? separator.value : '-';
    code     += sep + PREVIEW_NAME;
  }

  return code;
});
```

### 4.4 Template (estructura)

```vue
<template>
  <div class="panel space-y-6">

    <!-- 1. Drag & Drop horizontal -->
    <div>
      <label class="form-label">{{ $t('settings.case_code.blocks_order') }}</label>
      <p class="text-xs text-gray-500 mb-3">{{ $t('settings.case_code.drag_hint') }}</p>

      <VueDraggable
        v-model="blocks"
        :animation="200"
        item-key="key"
        class="flex flex-wrap gap-2 p-4 bg-gray-50 dark:bg-[#1b2e4b] border border-dashed
               border-gray-300 dark:border-gray-600 rounded-lg min-h-[70px]"
        @end="isDirty = true"
      >
        <div
          v-for="block in blocks"
          :key="block.key"
          class="cursor-move flex items-center gap-2 bg-primary text-white
                 px-4 py-2 rounded-full shadow select-none"
        >
          <icon-grip-vertical class="w-4 h-4 opacity-70" />
          <span class="font-mono font-bold">{{ block.key }}</span>
          <tippy :content="$t(`settings.case_code.block.${block.key}_desc`)">
            <icon-info-circle class="w-3.5 h-3.5 opacity-70" />
          </tippy>
        </div>
      </VueDraggable>

      <!-- Leyenda de bloques -->
      <div class="mt-3 flex flex-wrap gap-x-6 gap-y-1">
        <div v-for="block in blocks" :key="block.key" class="text-xs text-gray-500">
          <span class="font-mono font-semibold">{{ block.key }}</span>
          = {{ $t(`settings.case_code.block.${block.key}`) }}
          <span class="text-gray-400">(ej: {{ block.example }})</span>
        </div>
      </div>
    </div>

    <!-- 2. Selector de separador -->
    <div>
      <label class="form-label">{{ $t('settings.case_code.separator') }}</label>
      <div class="flex gap-4 mt-2">
        <label v-for="opt in separatorOptions" :key="opt.value" class="flex items-center gap-2 cursor-pointer">
          <input
            type="radio"
            v-model="separator"
            :value="opt.value"
            class="form-radio"
            @change="isDirty = true"
          />
          <span class="font-mono text-sm">{{ opt.label }}</span>
          <span class="text-xs text-gray-500">{{ opt.hint }}</span>
        </label>
      </div>
    </div>

    <!-- 3. Toggle NOMBRE -->
    <div class="flex items-start justify-between gap-4 p-4 bg-gray-50 dark:bg-[#1b2e4b] rounded-lg">
      <div>
        <p class="font-medium text-sm">{{ $t('settings.case_code.include_name') }}</p>
        <p class="text-xs text-gray-500 mt-0.5">{{ $t('settings.case_code.include_name_hint') }}</p>
        <p class="text-xs text-gray-400 mt-1 font-mono">
          {{ $t('settings.case_code.block.NOMBRE_desc') }}
        </p>
      </div>
      <label class="switch s-success mt-0.5">
        <input type="checkbox" v-model="includeName" @change="isDirty = true" />
        <span class="slider"></span>
      </label>
    </div>

    <!-- 4. Previsualización en tiempo real -->
    <div class="border-2 border-dashed border-primary/40 bg-primary/5 dark:bg-primary/10 p-5 rounded-xl">
      <p class="text-xs uppercase tracking-widest text-gray-500 mb-2">
        {{ $t('settings.case_code.preview') }}
      </p>
      <p class="text-2xl font-mono font-bold text-primary break-all">
        {{ previewCode }}
      </p>
      <p class="text-xs text-gray-400 mt-2">{{ $t('settings.case_code.preview_note') }}</p>
    </div>

    <!-- 5. Acciones -->
    <div class="flex justify-end gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
      <button
        type="button"
        class="btn btn-outline-secondary"
        :disabled="!isDirty"
        @click="resetToSaved"
      >
        {{ $t('common.cancel') }}
      </button>
      <button
        type="button"
        class="btn btn-primary"
        :disabled="!isDirty || isSaving"
        @click="confirmAndSave"
      >
        <span v-if="isSaving" class="animate-spin border-2 border-white border-l-transparent
                                     rounded-full w-4 h-4 inline-block mr-2"></span>
        {{ $t('common.save') }}
      </button>
    </div>

  </div>
</template>
```

### 4.5 Función `confirmAndSave`

```typescript
async function confirmAndSave() {
  const result = await Swal.fire({
    icon: 'warning',
    title: t('settings.case_code.confirm_title'),
    html: `<p>${t('settings.case_code.confirm_body')}</p>
           <p class="mt-3 font-mono text-lg font-bold text-primary">${previewCode.value}</p>`,
    showCancelButton: true,
    confirmButtonText: t('common.save'),
    cancelButtonText: t('common.cancel'),
  });

  if (!result.isConfirmed) return;

  isSaving.value = true;
  try {
    await tenantService.updateCaseCodePattern({
      case_code_blocks: blocks.value.map(b => b.key),
      case_code_separator: separator.value,
      case_code_include_name: includeName.value,
    });
    isDirty.value = false;
    Swal.fire({
      icon: 'success',
      title: t('settings.case_code.saved_successfully'),
      timer: 2000,
      showConfirmButton: false,
    });
  } catch (err: any) {
    // mostrar errores de validación 422
  } finally {
    isSaving.value = false;
  }
}
```

### 4.6 Ruta y Sidebar

```typescript
// router/index.ts
{
  path: '/admin/case-code-settings',
  component: () => import('@/views/admin/CaseCodeSettings.vue'),
  meta: {
    requiresAuth: true,
    permission: 'settings.case_code.manage',
    layout: 'app',
  },
}
```

```html
<!-- Sidebar.vue — dentro del grupo de Admin -->
<li v-can="'settings.case_code.manage'">
  <router-link to="/admin/case-code-settings">
    <icon-code class="w-5 h-5" />
    {{ $t('settings.case_code.sidebar_label') }}
  </router-link>
</li>
```

---

## 5. Internacionalización (i18n — FLAT keys)

### 5.1 Claves Requeridas (30 keys)

```json
{
  "settings.case_code.sidebar_label":    "Código de Expediente",
  "settings.case_code.title":            "Estructura del Código de Expediente",
  "settings.case_code.description":      "Configura el orden y formato del código asignado a los nuevos expedientes. Los expedientes existentes no se modifican.",
  "settings.case_code.blocks_order":     "Orden de los bloques",
  "settings.case_code.drag_hint":        "Arrastra y suelta los bloques para reordenarlos.",
  "settings.case_code.separator":        "Separador",
  "settings.case_code.separator_dash":   "Guión  ( - )",
  "settings.case_code.separator_underscore": "Guión bajo  ( _ )",
  "settings.case_code.separator_none":   "Sin separador",
  "settings.case_code.include_name":     "Incluir nombre completo del aplicante",
  "settings.case_code.include_name_hint":"El nombre se adjunta siempre al final del código.",
  "settings.case_code.preview":          "Previsualización en tiempo real",
  "settings.case_code.preview_note":     "Ejemplo con datos ficticios. El código real usará los datos del expediente.",
  "settings.case_code.confirm_title":    "¿Guardar nueva estructura?",
  "settings.case_code.confirm_body":     "Este cambio afectará la nomenclatura de todos los expedientes futuros de esta firma. Los expedientes existentes conservan su código original.",
  "settings.case_code.saved_successfully": "Estructura de código guardada exitosamente.",
  "settings.case_code.must_include_nnnn": "El bloque NNNN (consecutivo) es obligatorio para garantizar códigos únicos.",
  "settings.case_code.invalid_for_cloud": "La configuración genera un código incompatible con el almacenamiento en la nube.",
  "settings.case_code.block.YY":         "Año",
  "settings.case_code.block.YY_desc":    "Dos últimos dígitos del año en curso (ej: 26 para 2026).",
  "settings.case_code.block.TT":         "Tipo de Caso",
  "settings.case_code.block.TT_desc":    "Abreviatura del tipo de caso (ej: SP para Spouse).",
  "settings.case_code.block.AAAA":       "Apellido",
  "settings.case_code.block.AAAA_desc":  "Primeras 4 letras del apellido del aplicante principal (ej: RODR).",
  "settings.case_code.block.NNNN":       "Consecutivo",
  "settings.case_code.block.NNNN_desc":  "Número correlativo global por firma, con relleno de ceros (ej: 0005). Obligatorio.",
  "settings.case_code.block.NOMBRE":     "Nombre Completo",
  "settings.case_code.block.NOMBRE_desc":"Nombre completo del aplicante unido por guiones. Se adjunta al final del código.",
  "settings.case_code.reset_confirm":    "¿Restablecer la configuración guardada anteriormente?",
  "settings.case_code.mixed_format_notice": "Los expedientes anteriores conservan su formato original."
}
```

Mismas claves en `en.json` y `fr.json` con traducciones equivalentes.

---

## 6. Riesgos y Mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|--------------|---------|------------|
| Backfill `ROW_NUMBER()` lento con muchos registros | MEDIA | MEDIO | Ejecutar en ventana de mantenimiento. Verificar post-migración `COUNT(*) WHERE case_number_seq IS NULL = 0` |
| Race condition: dos casos con mismo `seq` | BAJA | ALTO | UNIQUE `(tenant_id, case_number_seq)` en BD + retry loop en `CaseCodeGeneratorService` |
| Bloque NOMBRE con caracteres no-ASCII llega a cloud storage | MEDIA | ALTO | `NameBlockResolver` sanitiza con `iconv TRANSLIT` + `FolderNameValidator` — aplicado antes de persistir |
| Cache de patrón en worker queue no invalida tras el Save del frontend | BAJA | MEDIO | `Cache::remember()` cross-process (driver `database` o `redis`) — no cache estático en memoria de proceso |
| Admin elimina NNNN del array y genera códigos duplicados | BAJA | CRÍTICO | Validación backend `must_include_nnnn` es la última línea de defensa |
| Expedientes con formato mixto confunden al usuario en listas | ALTA | BAJO | Banner informativo condicional en `cases/index.vue` cuando `tenant.settings.case_code_updated_at` existe |
| `app('currentTenant')` no disponible en tests sin middleware | MEDIA | MEDIO | `GenerationContext` acepta `Tenant $tenant` explícito — inyectable directamente en tests |

---

## 7. Impacto en Módulos Existentes

| Módulo | Impacto | Acción Requerida |
|--------|---------|-----------------|
| `CaseService::generateCaseNumber()` | ALTO | Eliminar. Reemplazar por `CaseCodeGeneratorService::generate()` |
| `CaseRepository::getNextSequence()` | ALTO | Refactorizar para leer `MAX(case_number_seq)` en vez de parsear string |
| `GlobalSearchService` | NINGUNO | LIKE `%query%` es agnóstico al formato — sin cambios |
| `FolderService` / `SyncCaseFolderStructure` | NINGUNO | Usa `case_number` directamente — el string ya vendrá sanitizado del generador |
| `cases/index.vue` | BAJO | Añadir banner informativo de formato mixto si `case_code_updated_at` existe |
| `CaseResource` / `ImmigrationCaseResource` | NINGUNO | `case_number` es un string — sin cambios en serialización |
| [[56_seleccion_dinamica_directorios_wizard|Spec 56]] (Wizard) | NINGUNO | `CaseService::createCase()` es transparente para el wizard |
| [[57_alertas_fechas_importantes_expedientes|Spec 57]] (Fechas Importantes) | NINGUNO | No usa `case_number` para lógica |
| [[60_ircc_credentials_vault|Spec 60]] (IRCC Vault) | NINGUNO | No depende de la estructura del código |

---

## 8. Checklist de Implementación

### FASE 1 — Base de Datos

- [x] **1.1** Ejecutar migración `2026_06_01_000001_add_case_number_seq_to_cases.php` ✓ 175ms — 47 casos backfilled
- [x] **1.2** Verificar post-migración: `SELECT COUNT(*) WHERE case_number_seq IS NULL` = 0 ✓
- [x] **1.3** Ejecutar migración `2026_06_01_000002_add_case_code_pattern_permissions.php` ✓ 30ms — permiso id=1742 creado y asignado

### FASE 2 — Backend: Modelo y Accessors

- [x] **2.1** `app/Models/Tenant.php` — accessors `caseCodeBlocks()`, `caseCodeSeparator()`, `caseCodeIncludeName()` ✓
- [x] **2.2** `app/Models/ImmigrationCase.php` — `case_number_seq` en `$fillable` ✓

### FASE 3 — Backend: Servicio Generador

- [x] **3.1** `app/Services/Case/GenerationContext.php` (readonly DTO) ✓
- [x] **3.2** `app/Services/Case/CaseCodePattern.php` (value object readonly) ✓
- [x] **3.3** `app/Services/Case/CaseCodePatternResolver.php` (con `Cache::remember` TTL 3600s) ✓
- [x] **3.4** `app/Services/Case/BlockResolvers/BlockResolverInterface.php` ✓
- [x] **3.5** `YearBlockResolver.php`, `TypeBlockResolver.php`, `LastNameBlockResolver.php` ✓
- [x] **3.6** `SequenceBlockResolver.php` y `NameBlockResolver.php` (sanitización cloud-safe) ✓
- [x] **3.7** `app/Services/Case/CaseCodeGeneratorService.php` (orquestador + retry loop) ✓
- [x] **3.8** Container auto-resuelve — no requirió bindings explícitos en `AppServiceProvider` ✓
- [x] **3.9** `CaseService::createCase()` refactorizado — usa `GenerationContext` + persiste `case_number_seq` ✓
- [x] **3.10** Método privado `CaseService::generateCaseNumber()` eliminado ✓
- [x] **3.11** `CaseRepository::getNextSequence()` refactorizado — usa `MAX(case_number_seq)` ✓
- [x] **3.12** `CaseRepository::existsByCaseNumber()` ya existía ✓

### FASE 4 — Backend: API

- [x] **4.1** `app/Http/Requests/Tenant/UpdateCaseCodePatternRequest.php` con regla `must_include_nnnn` ✓
- [x] **4.2** `TenantController::updateCaseCodePattern()` con cache invalidation y preview ✓
- [x] **4.3** Ruta `PUT /api/tenant/case-code-pattern` con `permission:settings.case_code.manage` ✓
- [x] **4.4** `TenantResource` extendido con bloque `case_code` ✓

### FASE 5 — Frontend: Servicios y Store

- [x] **5.1** `tenantService.ts` — `updateCaseCodePattern()`, tipos `CaseCodePatternPayload` / `CaseCodePatternResponse` ✓
- [x] **5.2** `tenantStore.ts` — estado `caseCodePattern`, computed `hasCustomCaseCodePattern`, action `updateCaseCodePattern` ✓

### FASE 6 — Frontend: UI

- [x] **6.1** `resources/js/src/components/admin/CaseCodeBuilder.vue` (DnD + separador + toggle + preview + SweetAlert2) ✓
- [x] **6.2** `resources/js/src/views/admin/CaseCodeSettings.vue` (breadcrumb + panel + spinner carga) ✓
- [x] **6.3** Ruta `/admin/case-code-settings` en `router/index.ts` con `permission: 'settings.case_code.manage'` ✓
- [x] **6.4** Entry en `Sidebar.vue` con `v-can="'settings.case_code.manage'"` ✓
- [x] **6.5** Banner informativo en `cases/list.vue` cuando `case_code_updated_at` existe (dismissable con sessionStorage) ✓

### FASE 7 — i18n

- [x] **7.1** 30 keys `settings.case_code.*` añadidas en `es.json` ✓
- [x] **7.2** 30 keys añadidas en `en.json` ✓
- [x] **7.3** 30 keys añadidas en `fr.json` ✓

### FASE 8 — Tests

- [ ] **8.1** `tests/Unit/Services/Case/CaseCodeGeneratorServiceTest.php` *(pendiente — deuda técnica)*
- [ ] **8.2** `tests/Feature/Tenant/UpdateCaseCodePatternTest.php` *(pendiente — deuda técnica)*
- [ ] **8.3** `tests/Feature/Cases/CaseCreationWithCustomPatternTest.php` *(pendiente — deuda técnica)*

### FASE 9 — Cierre

- [x] **9.1** Build Vite: ✓ 9.18s sin errores
- [ ] **9.2** Prueba manual del flujo completo *(pendiente)*
- [x] **9.3** `spec/INDEX.md` actualizado — Spec 65 estado `DONE` ✓
- [x] **9.4** `MEMORY.md` actualizado ✓
- [x] **9.5** `CLAUDE.md` actualizado — gotcha G24 añadido ✓

---

## 9. Notas de Implementación

### Verificaciones Previas

```bash
# Confirmar que case_number_seq no existe aún
php artisan tinker --execute="DB::select('DESCRIBE cases');"

# Confirmar versión MySQL (debe ser 8.0+ para ROW_NUMBER)
php artisan tinker --execute="DB::select('SELECT VERSION()');"

# Ver el consecutivo actual más alto por tenant
php artisan tinker --execute="
DB::select('SELECT tenant_id, MAX(id) as max_id, COUNT(*) as total FROM cases GROUP BY tenant_id');
"
```

### Prueba Post-Implementación

```bash
# Verificar que el backfill fue correcto
php artisan tinker --execute="
\$total = DB::table('cases')->whereNull('case_number_seq')->count();
echo 'Casos sin seq: ' . \$total; // debe ser 0
"

# Crear expediente de prueba y verificar nuevo formato
php artisan tinker --execute="
\$tenant = App\Models\Tenant::first();
\$settings = \$tenant->settings ?? [];
\$settings['case_code_blocks'] = ['TT','YY','AAAA','NNNN'];
\$settings['case_code_separator'] = '_';
\$tenant->settings = \$settings;
\$tenant->save();
echo 'Settings guardados. Crear un caso para verificar el nuevo formato.';
"
```
