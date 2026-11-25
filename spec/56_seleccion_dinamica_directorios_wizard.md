---
tipo: spec
numero: 56
modulo: Expedientes
dominio: CASES
estado: DONE
tags: [spec, expedientes, done]
relacionados:
  - "[[09_epic_2.2_case_wizard|Spec 09]]"
  - "[[27_file_manager_system|Spec 27]]"
  - "[[30_cloud_folder_sync_por_tenant|Spec 30]]"
---
# [[56_seleccion_dinamica_directorios_wizard|Spec 56]]: Selección Dinámica de Directorios en el Wizard de Creación de Expediente

**Versión:** 1.0
**Fecha:** 2026-05-11
**Branch:** `dynamic-case-folders`
**Stack:** Laravel 12 + Vue 3.5 + MySQL 8.0 + Pinia + Sanctum SPA
**Dependencias:** [[07_epic_2.1_expedientes_core|Spec 07]] (Expedientes Core), [[09_epic_2.2_case_wizard|Spec 09]] (Gestión de Documentos), [[27_file_manager_system|Spec 27]] (Estructura de Carpetas en Cloud Storage), [[30_cloud_folder_sync_por_tenant|Spec 30]] (Wizard de Creación de Expediente)

---

## Resumen Ejecutivo

Sustituye la creación de **14 carpetas en español hardcoded** en `FolderService::createDefaultStructure()` por un nuevo paso en el wizard de creación de expediente (`StepFolders`) que permite al usuario:

1. Marcar/desmarcar carpetas del catálogo predefinido (traducidas al idioma del expediente).
2. Añadir carpetas personalizadas con validación de nombres seguros para filesystem y cloud storage.
3. Aprobar visualmente la estructura final antes de crear el expediente.

El catálogo de carpetas vive en `config/case_folders.php` (no en base de datos: catálogo cerrado, versionable, sin coste runtime). Las traducciones viven en los 3 archivos `locales/*.json` (unicamente ES, EN y FR a ser implementados) bajo el namespace FLAT `folder_templates.*`. El frontend resuelve los nombres en el idioma elegido (`caseDetails.language`) y envía al backend strings ya traducidos junto con un flag `is_custom`.

El flujo de creación mantiene la **sincronía actual** (DB síncrono, cloud asíncrono via `SyncCaseFolderStructure`), por lo que la única ruta crítica para el usuario sigue siendo el INSERT en `document_folders` — sin cambios de latencia perceptibles. El job de sincronización con el proveedor cloud ya itera `DocumentFolder::byCase()`, por lo que no requiere modificación.

### Decisiones Clave

| # | Decisión | Justificación |
|---|---|---|
| 1 | **Catálogo en `config/case_folders.php`** (no en DB) | Catálogo cerrado del producto, versionable en git, `config:cache` lo aplana en memoria. Una tabla DB añadiría JOINs, migración inicial y endpoints CRUD sin valor para v1. |
| 2 | **Traducciones en i18n** bajo namespace `folder_templates.*` FLAT | Reutiliza la infraestructura de 3 idiomas existente, unicamente ES, EN y FR (G6). El catálogo solo expone `i18n_key`; el frontend resuelve. |
| 3 | **Frontend envía strings ya resueltos** + flag `is_custom` | Single source of truth: lo que el usuario ve es lo que se persiste. Carpetas predefinidas y ad-hoc viajan bajo el mismo contrato. |
| 4 | **Locale: `state.caseDetails.language`** | Refleja el idioma del cliente/expediente, no la UI del operador. Coherente con la generación de documentos legales ([[14_ciclo_de_vida_checklist_dinamico|Spec 14]]). |
| 5 | **Columna `is_default` ya existente** distingue catálogo vs ad-hoc | El backend no inventa esquema nuevo; `DocumentFolder.is_default = false` ya significa "creada manualmente". |
| 6 | **Nuevo paso `StepFolders`** insertado entre `StepDetails` (4) y `StepChecklist` (5 → 6) | El usuario decide la estructura antes de ver el resumen. Insertarlo antes de Summary minimiza retrocesos. |
| 7 | **Sincronía DB / asincronía cloud** sin cambios | El job `SyncCaseFolderStructure` ya lee `DocumentFolder::byCase()`. Persistir N filas en MySQL es O(<50ms) incluso para 24 carpetas. Las 14–24 llamadas HTTP a OneDrive/Drive (30–90s reales) siguen en queue. |
| 8 | **Backward compatibility**: si `folders` no llega en el payload → fallback al catálogo completo | Compatibilidad con callers existentes (imports, API externa, tests legacy). |
| 9 | **Validación dual frontend + backend** de nombres reservados Windows y caracteres prohibidos | Defensa en profundidad. Frontend bloquea el error temprano; backend es la autoridad final (G5). |
| 10 | **Máximo 10 carpetas personalizadas + 14 predefinidas = 24 carpetas máx** | Cota razonable para una operación que se replica en cloud HTTP-call por carpeta; mitiga abuso y timeouts. |

---

## 1. Modelado de Datos

### 1.1 Sin migración de base de datos

La tabla `document_folders` ya contiene todas las columnas necesarias:

```sql
CREATE TABLE document_folders (
    id, tenant_id, case_id, parent_id, name, sort_order,
    is_default BOOLEAN,    -- true = catálogo, false = ad-hoc del wizard
    category VARCHAR,      -- opcional, mapea a Document::CATEGORY_*
    external_id, sync_status, synced_at, created_at, updated_at
);
```

> **No se introducen migraciones.** El campo `is_default` ya distingue origen, y `category` ya soporta el mapeo al catálogo de constantes en `Document`.

### 1.2 Catálogo de carpetas — `config/case_folders.php`

```php
<?php

// config/case_folders.php
return [
    /*
    |--------------------------------------------------------------------------
    | Default Folder Catalog
    |--------------------------------------------------------------------------
    |
    | Keys correspond to i18n entries under `folder_templates.<key>` across
    | all 16 locale files (FLAT, see G6). The 'category' value maps to the
    | constants defined in App\Models\Document::CATEGORY_*.
    |
    */
    'defaults' => [
        ['key' => 'admission',     'category' => 'admission',     'i18n_key' => 'folder_templates.admission',     'enabled_by_default' => true],
        ['key' => 'archive',       'category' => 'archive',       'i18n_key' => 'folder_templates.archive',       'enabled_by_default' => true],
        ['key' => 'hearing',       'category' => 'hearing',       'i18n_key' => 'folder_templates.hearing',       'enabled_by_default' => true],
        ['key' => 'letters',       'category' => 'letters',       'i18n_key' => 'folder_templates.letters',       'enabled_by_default' => true],
        ['key' => 'communication', 'category' => 'communication', 'i18n_key' => 'folder_templates.communication', 'enabled_by_default' => true],
        ['key' => 'accounting',    'category' => 'accounting',    'i18n_key' => 'folder_templates.accounting',    'enabled_by_default' => true],
        ['key' => 'contract',      'category' => 'contract',      'i18n_key' => 'folder_templates.contract',      'enabled_by_default' => true],
        ['key' => 'documents',     'category' => 'documents',     'i18n_key' => 'folder_templates.documents',     'enabled_by_default' => true],
        ['key' => 'links',         'category' => 'links',         'i18n_key' => 'folder_templates.links',         'enabled_by_default' => true],
        ['key' => 'evidence',      'category' => 'evidence',      'i18n_key' => 'folder_templates.evidence',      'enabled_by_default' => true],
        ['key' => 'forms',         'category' => 'forms',         'i18n_key' => 'folder_templates.forms',         'enabled_by_default' => true],
        ['key' => 'history',       'category' => 'history',       'i18n_key' => 'folder_templates.history',       'enabled_by_default' => true],
        ['key' => 'other',         'category' => 'other',         'i18n_key' => 'folder_templates.other',         'enabled_by_default' => true],
        ['key' => 'questionary',   'category' => 'questionary',   'i18n_key' => 'folder_templates.questionary',   'enabled_by_default' => true],
    ],

    'validation' => [
        'name_max_length'     => 100,
        'name_min_length'     => 1,
        'forbidden_chars'     => ['<', '>', ':', '"', '/', '\\', '|', '?', '*'],
        'forbidden_chars_regex' => '/[<>:"\/\\\\|?*]/u',
        'leading_dot_regex'   => '/^\.+\s*$/u',
        'reserved_names'      => [
            'CON', 'PRN', 'AUX', 'NUL',
            'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9',
            'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9',
        ],
        'max_custom_per_case' => 10,
        'max_total_per_case'  => 30,
    ],
];
```

### 1.3 Categorías válidas

| Key | Category constant | Spanish (es) | English (en) |
|---|---|---|---|
| `admission`     | `CATEGORY_ADMISSION`     | Admisión      | Admission     |
| `archive`       | `CATEGORY_ARCHIVE`       | Archivo       | Archive       |
| `hearing`       | `CATEGORY_HEARING`       | Audiencias    | Hearings      |
| `letters`       | `CATEGORY_LETTERS`       | Cartas        | Letters       |
| `communication` | `CATEGORY_COMMUNICATION` | Comunicaciones| Communications|
| `accounting`    | `CATEGORY_ACCOUNTING`    | Contabilidad  | Accounting    |
| `contract`      | `CATEGORY_CONTRACT`      | Contrato      | Contract      |
| `documents`     | `CATEGORY_DOCUMENTS`     | Documentos    | Documents     |
| `links`         | `CATEGORY_LINKS`         | Enlaces       | Links         |
| `evidence`      | `CATEGORY_EVIDENCE`      | Evidencia     | Evidence      |
| `forms`         | `CATEGORY_FORMS`         | Formularios   | Forms         |
| `history`       | `CATEGORY_HISTORY`       | Historial     | History       |
| `other`         | `CATEGORY_OTHER`         | Otros         | Other         |
| `questionary`   | `CATEGORY_QUESTIONARY`   | Cuestionarios | Questionnaires|

---

## 2. Backend — Capa de Dominio

### 2.1 `FolderNameValidator`

Validador stateless reutilizable por `FolderService`, `StoreCaseRequest` y `createFolder` (endpoint de carpetas ad-hoc post-creación).

```php
<?php

// app/Services/Document/FolderNameValidator.php
namespace App\Services\Document;

use Illuminate\Validation\ValidationException;

final class FolderNameValidator
{
    /**
     * Validate a folder name against filesystem-safe rules.
     * Throws ValidationException on failure (caught by Laravel → 422).
     *
     * @param  string  $name        Folder name to validate
     * @param  string  $field       Field path for the error message (e.g. "folders.0.name")
     * @param  array<string,mixed>|null $config  Optional override of config('case_folders.validation')
     */
    public static function validate(string $name, string $field = 'name', ?array $config = null): void
    {
        $rules = $config ?? config('case_folders.validation', []);
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw ValidationException::withMessages([
                $field => __('validation.folder_name.required'),
            ]);
        }

        $min = $rules['name_min_length'] ?? 1;
        $max = $rules['name_max_length'] ?? 100;

        if (mb_strlen($trimmed) < $min) {
            throw ValidationException::withMessages([
                $field => __('validation.folder_name.min', ['min' => $min]),
            ]);
        }

        if (mb_strlen($trimmed) > $max) {
            throw ValidationException::withMessages([
                $field => __('validation.folder_name.max', ['max' => $max]),
            ]);
        }

        $forbiddenRegex = $rules['forbidden_chars_regex'] ?? '/[<>:"\/\\\\|?*]/u';
        if (preg_match($forbiddenRegex, $trimmed)) {
            throw ValidationException::withMessages([
                $field => __('validation.folder_name.invalid_chars'),
            ]);
        }

        $leadingDotRegex = $rules['leading_dot_regex'] ?? '/^\.+\s*$/u';
        if (preg_match($leadingDotRegex, $trimmed)) {
            throw ValidationException::withMessages([
                $field => __('validation.folder_name.leading_dot'),
            ]);
        }

        // Windows-reserved names (case-insensitive, base name only)
        $base = mb_strtoupper(pathinfo($trimmed, PATHINFO_FILENAME));
        $reserved = $rules['reserved_names'] ?? [];
        if (in_array($base, $reserved, true)) {
            throw ValidationException::withMessages([
                $field => __('validation.folder_name.reserved'),
            ]);
        }
    }

    /**
     * Returns true if name is valid, without throwing (useful for filtering).
     */
    public static function isValid(string $name, ?array $config = null): bool
    {
        try {
            self::validate($name, 'name', $config);
            return true;
        } catch (ValidationException) {
            return false;
        }
    }
}
```

### 2.2 `FolderService::createSelectedStructure()` (reemplaza `createDefaultStructure()`)

```php
<?php

// app/Services/Document/FolderService.php — método nuevo
// REEMPLAZA createDefaultStructure($case) en su totalidad.

/**
 * Create folder structure for a case based on the wizard selection.
 *
 * @param  ImmigrationCase  $case
 * @param  array<int,array{name:string,category:?string,is_custom?:bool}>|null  $folders
 *         If null, falls back to the full default catalog (backward compatibility).
 */
public function createSelectedStructure(ImmigrationCase $case, ?array $folders = null): void
{
    // Guard: skip if folders already exist for this case (idempotent retries)
    if (DocumentFolder::where('case_id', $case->id)->exists()) {
        Log::info('FolderService: Folders already exist, skipping', [
            'case_id' => $case->id,
        ]);
        return;
    }

    // Resolve folder list — fallback to full catalog if no selection provided
    $selection = $folders !== null
        ? $this->normalizeSelection($folders)
        : $this->defaultCatalogSelection();

    if (empty($selection)) {
        Log::info('FolderService: Empty folder selection, case created without folders', [
            'case_id' => $case->id,
        ]);
        return;
    }

    // Storage configuration
    $tenant = $case->tenant ?? Tenant::find($case->tenant_id);
    $isLocal = !$tenant || !in_array($tenant->storage_type ?? 'local', ['onedrive', 'google_drive', 'sharepoint'], true);
    $rootPath = $tenant && $tenant->base_folder_path
        ? "tenants/{$case->tenant_id}/{$tenant->base_folder_path}/cases/{$case->case_number}"
        : "tenants/{$case->tenant_id}/cases/{$case->case_number}";

    Log::info('FolderService: Creating selected folder structure', [
        'case_id'      => $case->id,
        'case_number'  => $case->case_number,
        'tenant_id'    => $case->tenant_id,
        'storage_type' => $tenant?->storage_type ?? 'local',
        'is_local'     => $isLocal,
        'folder_count' => count($selection),
    ]);

    if ($isLocal) {
        Storage::disk('local')->makeDirectory($rootPath);
    }

    $created = 0;
    $seenLower = []; // case-insensitive duplicate guard within the same batch

    foreach ($selection as $index => $folderData) {
        $name = trim($folderData['name']);
        $lower = mb_strtolower($name);

        if (isset($seenLower[$lower])) {
            Log::warning('FolderService: Duplicate folder name in selection, skipping', [
                'case_id' => $case->id,
                'name'    => $name,
            ]);
            continue;
        }
        $seenLower[$lower] = true;

        // Backend validation (defense in depth)
        if (!FolderNameValidator::isValid($name)) {
            Log::warning('FolderService: Invalid folder name in selection, skipping', [
                'case_id' => $case->id,
                'name'    => $name,
            ]);
            continue;
        }

        $folderPath = $rootPath . '/' . $name;

        if ($isLocal) {
            Storage::disk('local')->makeDirectory($folderPath);
        }

        DocumentFolder::create([
            'tenant_id'   => $case->tenant_id,
            'case_id'     => $case->id,
            'parent_id'   => null,
            'name'        => $name,
            'sort_order'  => $index,
            'is_default'  => !($folderData['is_custom'] ?? false),
            'category'    => $folderData['category'] ?? null,
            'external_id' => $isLocal ? $folderPath : null,
            'sync_status' => $isLocal ? 'synced' : 'pending',
            'synced_at'   => $isLocal ? now() : null,
        ]);
        $created++;
    }

    if ($isLocal && $created > 0) {
        $case->update([
            'root_external_folder_id' => $rootPath,
            'folder_sync_status'      => 'synced',
            'folder_synced_at'        => now(),
        ]);
    }

    Log::info('FolderService: Folder structure created', [
        'case_id'         => $case->id,
        'folders_created' => $created,
    ]);
}

/**
 * Backwards compatible alias — keeps legacy callers (imports, tests) working.
 */
public function createDefaultStructure(ImmigrationCase $case): void
{
    $this->createSelectedStructure($case, null);
}

/**
 * Normalize and clamp the user selection.
 *
 * @param  array<int,array<string,mixed>>  $folders
 * @return array<int,array{name:string,category:?string,is_custom:bool}>
 */
private function normalizeSelection(array $folders): array
{
    $maxTotal = (int) config('case_folders.validation.max_total_per_case', 30);

    return collect($folders)
        ->take($maxTotal)
        ->map(fn ($f) => [
            'name'      => (string) ($f['name'] ?? ''),
            'category'  => isset($f['category']) ? (string) $f['category'] : null,
            'is_custom' => (bool) ($f['is_custom'] ?? false),
        ])
        ->filter(fn ($f) => $f['name'] !== '')
        ->values()
        ->all();
}

/**
 * Build a selection from the full catalog (Spanish names — kept for backward
 * compatibility with the previous hardcoded structure).
 *
 * @return array<int,array{name:string,category:string,is_custom:bool}>
 */
private function defaultCatalogSelection(): array
{
    $defaults = config('case_folders.defaults', []);

    // Map keys → Spanish names to preserve current behavior for legacy callers.
    $spanish = [
        'admission' => 'Admisión',     'archive' => 'Archivo',
        'hearing'   => 'Audiencias',   'letters' => 'Cartas',
        'communication' => 'Comunicaciones', 'accounting' => 'Contabilidad',
        'contract'  => 'Contrato',     'documents' => 'Documentos',
        'links'     => 'Enlaces',      'evidence'  => 'Evidencia',
        'forms'     => 'Formularios',  'history'   => 'Historial',
        'other'     => 'Otros',        'questionary' => 'Cuestionarios',
    ];

    return collect($defaults)
        ->filter(fn ($d) => ($d['enabled_by_default'] ?? true) === true)
        ->map(fn ($d) => [
            'name'      => $spanish[$d['key']] ?? ucfirst((string) $d['key']),
            'category'  => $d['category'] ?? null,
            'is_custom' => false,
        ])
        ->values()
        ->all();
}
```

### 2.3 Integración en `CaseService::createCase()`

```php
<?php

// app/Services/Case/CaseService.php — método createCase()

public function createCase(array $data): ImmigrationCase
{
    // Extract the optional folder selection BEFORE it reaches ImmigrationCase::create()
    $foldersInput = $data['folders'] ?? null;
    unset($data['folders']);

    return DB::transaction(function () use ($data, $foldersInput) {
        $case = new ImmigrationCase($data);
        $case->tenant_id = app('currentTenant')->id;
        // ... existing code (case_code generation, etc.)
        $case->save();

        // DB folder creation — synchronous, inside the transaction
        $this->folderService->createSelectedStructure($case, $foldersInput);

        return $case;
    }, attempts: 3);

    // After commit — cloud sync is dispatched outside the transaction
    // (existing behavior, unchanged)
}
```

> **Posición del dispatch del job cloud:** ya está fuera de la transacción en el método actual (búscalo bajo `SyncCaseFolderStructure::dispatch`). No cambia: el job lee `DocumentFolder::byCase()` y crea cada carpeta en el proveedor cloud. Como `DocumentFolder.is_default` no afecta a la sincronización cloud, las carpetas ad-hoc se sincronizan igual que las estándar.

### 2.4 `StoreCaseRequest` — Reglas adicionales

```php
<?php

// app/Http/Requests/Case/StoreCaseRequest.php — método rules()

public function rules(): array
{
    $validation = config('case_folders.validation', []);
    $maxTotal   = (int) ($validation['max_total_per_case'] ?? 30);
    $maxLen     = (int) ($validation['name_max_length'] ?? 100);
    $forbidden  = $validation['forbidden_chars_regex'] ?? '/[<>:"\/\\\\|?*]/u';
    $leadingDot = $validation['leading_dot_regex'] ?? '/^\.+\s*$/u';

    $validCategories = collect(config('case_folders.defaults', []))
        ->pluck('category')
        ->filter()
        ->unique()
        ->values()
        ->all();

    return array_merge(parent::rules(), [
        // ... existing rules ...

        // New folder selection rules
        'folders'              => ['sometimes', 'array', "max:{$maxTotal}"],
        'folders.*.name'       => [
            'required_with:folders',
            'string',
            "max:{$maxLen}",
            "not_regex:{$forbidden}",
            "not_regex:{$leadingDot}",
        ],
        'folders.*.category'   => ['nullable', 'string', Rule::in($validCategories)],
        'folders.*.is_custom'  => ['sometimes', 'boolean'],
    ]);
}

public function withValidator(Validator $validator): void
{
    $validator->after(function (Validator $v) {
        $folders = (array) $this->input('folders', []);
        if (empty($folders)) {
            return;
        }

        $reserved = array_map('mb_strtoupper', (array) config('case_folders.validation.reserved_names', []));
        $maxCustom = (int) config('case_folders.validation.max_custom_per_case', 10);

        $seenLower = [];
        $customCount = 0;

        foreach ($folders as $i => $f) {
            $name = trim((string) ($f['name'] ?? ''));
            if ($name === '') continue;

            // Windows-reserved names
            $base = mb_strtoupper(pathinfo($name, PATHINFO_FILENAME));
            if (in_array($base, $reserved, true)) {
                $v->errors()->add("folders.{$i}.name", __('validation.folder_name.reserved'));
            }

            // Case-insensitive duplicates
            $lower = mb_strtolower($name);
            if (isset($seenLower[$lower])) {
                $v->errors()->add("folders.{$i}.name", __('validation.folder_name.duplicate'));
            }
            $seenLower[$lower] = $i;

            // Custom folder count
            if (!empty($f['is_custom'])) {
                $customCount++;
            }
        }

        if ($customCount > $maxCustom) {
            $v->errors()->add('folders', __('validation.folder_name.too_many_custom', ['max' => $maxCustom]));
        }
    });
}
```

### 2.5 Controller del catálogo — `CaseFolderTemplateController`

```php
<?php

// app/Http/Controllers/Api/CaseFolderTemplateController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CaseFolderTemplateController extends Controller
{
    /**
     * Returns the catalog of default folders.
     *
     * Names are NOT translated server-side: the frontend resolves i18n_key
     * using the locale chosen for the case (caseDetails.language).
     *
     * GET /api/case-folders/defaults
     */
    public function index(): JsonResponse
    {
        $defaults = collect(config('case_folders.defaults', []))
            ->map(fn ($d) => [
                'key'                => $d['key'],
                'i18n_key'           => $d['i18n_key'],
                'category'           => $d['category'] ?? null,
                'enabled_by_default' => (bool) ($d['enabled_by_default'] ?? true),
            ])
            ->values();

        return response()->json([
            'data'       => $defaults,
            'validation' => [
                'name_max_length'     => (int) config('case_folders.validation.name_max_length', 100),
                'forbidden_chars'     => (array) config('case_folders.validation.forbidden_chars', []),
                'reserved_names'      => (array) config('case_folders.validation.reserved_names', []),
                'max_custom_per_case' => (int) config('case_folders.validation.max_custom_per_case', 10),
                'max_total_per_case'  => (int) config('case_folders.validation.max_total_per_case', 30),
            ],
        ]);
    }
}
```

### 2.6 Ruta

```php
<?php

// routes/api.php — dentro del grupo auth:sanctum + tenant

Route::get('case-folders/defaults', [CaseFolderTemplateController::class, 'index'])
    ->name('case-folders.defaults');
```

> **Permiso:** ninguno adicional. Cualquier usuario autenticado de cualquier tenant puede ver el catálogo (es metadata del producto, no datos del tenant).

---

## 3. Frontend — Tipos y Estado

### 3.1 Tipos TypeScript

```typescript
// resources/js/src/types/case.ts (additions)

export interface FolderTemplate {
    key: string;
    i18n_key: string;
    category: string | null;
    enabled_by_default: boolean;
}

export interface FolderValidationConfig {
    name_max_length: number;
    forbidden_chars: string[];
    reserved_names: string[];
    max_custom_per_case: number;
    max_total_per_case: number;
}

export interface FolderTemplateResponse {
    data: FolderTemplate[];
    validation: FolderValidationConfig;
}

/**
 * Folder payload sent to POST /api/cases.
 * Both predefined and custom folders use the same shape.
 */
export interface CaseFolderInput {
    name: string;
    category: string | null;
    is_custom: boolean;
}

// Extend the existing CreateCaseData
export interface CreateCaseData {
    // ... existing fields ...
    folders?: CaseFolderInput[];
}
```

### 3.2 Service `caseFolderTemplateService`

```typescript
// resources/js/src/services/caseFolderTemplateService.ts
import api from '@/services/api';
import type { FolderTemplateResponse } from '@/types/case';

let cache: FolderTemplateResponse | null = null;

export const caseFolderTemplateService = {
    /**
     * Returns the folder catalog. Cached in memory for the SPA session
     * because the catalog is a static product asset that never changes
     * at runtime.
     */
    async getDefaults(force = false): Promise<FolderTemplateResponse> {
        if (cache && !force) return cache;
        const { data } = await api.get<FolderTemplateResponse>('/api/case-folders/defaults');
        cache = data;
        return data;
    },

    clearCache(): void {
        cache = null;
    },
};
```

### 3.3 Composable `useCaseWizard.ts` — Cambios

```typescript
// resources/js/src/composables/useCaseWizard.ts (additions)

import type { CaseFolderInput } from '@/types/case';

// Extend WizardState
export interface WizardState {
    // ... existing fields ...
    folders: {
        selected: CaseFolderInput[];  // ordered, final list ready to POST
    };
}

// Extend WIZARD_STEPS — INSERT 'folders' between 'details' (4) and 'checklist'.
// IMPORTANT: this shifts checklist/summary step numbers; update any hardcoded
// step indices in StepSummary / progress indicator.
export const WIZARD_STEPS = [
    { id: 'type',      component: 'StepType',      order: 1 },
    { id: 'client',    component: 'StepClient',    order: 2 },
    { id: 'companion', component: 'StepCompanion', order: 3 },
    { id: 'folders',   component: 'StepFolders',   order: 4 }, // ← NEW
    { id: 'details',   component: 'StepDetails',   order: 5 },
    { id: 'checklist', component: 'StepChecklist', order: 6 }, // was 5
    { id: 'summary',   component: 'StepSummary',   order: 7 }, // was 6
] as const;

// Default state initializer
function createInitialState(): WizardState {
    return {
        // ... existing fields ...
        folders: { selected: [] },
    };
}

// Validation helper (called from wizard navigation guard)
export function isStepFoldersValid(state: WizardState): boolean {
    // Empty selection is legal — user is warned but allowed to continue.
    // Validation only blocks if any name violates basic rules.
    return state.folders.selected.every(f => f.name.trim().length > 0);
}
```

### 3.4 Componente `FolderSelector.vue`

```vue
<!-- resources/js/src/components/cases/wizard/FolderSelector.vue -->
<script lang="ts" setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { caseFolderTemplateService } from '@/services/caseFolderTemplateService';
import type { CaseFolderInput, FolderTemplate, FolderValidationConfig } from '@/types/case';

const props = defineProps<{
    modelValue: CaseFolderInput[];
    locale: string;  // case language: es | en | fr | ...
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: CaseFolderInput[]): void;
}>();

const { t, te } = useI18n();

const templates = ref<FolderTemplate[]>([]);
const validation = ref<FolderValidationConfig | null>(null);
const loading = ref(true);

// Map: template.key → enabled boolean
const checked = ref<Record<string, boolean>>({});
// Custom folders entered by user
const customFolders = ref<{ name: string; error: string | null }[]>([]);
const newCustomName = ref('');
const newCustomError = ref<string | null>(null);

onMounted(async () => {
    const res = await caseFolderTemplateService.getDefaults();
    templates.value = res.data;
    validation.value = res.validation;

    // Initial state: hydrate from modelValue OR default catalog
    if (props.modelValue.length > 0) {
        templates.value.forEach(tpl => {
            const translated = resolveName(tpl);
            checked.value[tpl.key] = props.modelValue.some(
                f => !f.is_custom && f.name === translated
            );
        });
        customFolders.value = props.modelValue
            .filter(f => f.is_custom)
            .map(f => ({ name: f.name, error: null }));
    } else {
        templates.value.forEach(tpl => {
            checked.value[tpl.key] = tpl.enabled_by_default;
        });
    }

    loading.value = false;
    emitSelection();
});

// Resolve translated name from i18n key in the case's chosen locale
function resolveName(tpl: FolderTemplate): string {
    // Fallback chain: case locale → English → key
    if (te(tpl.i18n_key, props.locale)) {
        return t(tpl.i18n_key, props.locale);
    }
    if (te(tpl.i18n_key, 'en')) {
        return t(tpl.i18n_key, 'en');
    }
    return tpl.key;
}

// Validation for a candidate name (mirrors backend FolderNameValidator)
function validateName(name: string, ignoreIndex: number | null = null): string | null {
    if (!validation.value) return null;
    const trimmed = name.trim();

    if (trimmed.length === 0) return t('wizard.step_folders.empty');
    if (trimmed.length > validation.value.name_max_length) {
        return t('validation.folder_name.max', { max: validation.value.name_max_length });
    }
    if (/[<>:"/\\|?*]/.test(trimmed)) return t('wizard.step_folders.invalid_chars');
    if (/^\.+\s*$/.test(trimmed)) return t('validation.folder_name.leading_dot');

    const base = trimmed.split('.')[0].toUpperCase();
    if (validation.value.reserved_names.includes(base)) {
        return t('wizard.step_folders.reserved_name');
    }

    // Case-insensitive duplicate check against ALL current names
    const lower = trimmed.toLowerCase();
    const allNames = currentNamesLower(ignoreIndex);
    if (allNames.includes(lower)) {
        return t('wizard.step_folders.duplicate');
    }

    return null;
}

function currentNamesLower(ignoreCustomIndex: number | null): string[] {
    const names: string[] = [];
    templates.value.forEach(tpl => {
        if (checked.value[tpl.key]) names.push(resolveName(tpl).toLowerCase());
    });
    customFolders.value.forEach((cf, i) => {
        if (i !== ignoreCustomIndex && cf.name.trim()) names.push(cf.name.trim().toLowerCase());
    });
    return names;
}

const customCount = computed(() => customFolders.value.length);
const maxCustom = computed(() => validation.value?.max_custom_per_case ?? 10);
const canAddCustom = computed(() => customCount.value < maxCustom.value);

const totalSelected = computed(() => {
    const std = Object.values(checked.value).filter(Boolean).length;
    return std + customCount.value;
});

const noSelection = computed(() => totalSelected.value === 0);

function addCustom() {
    if (!canAddCustom.value) {
        newCustomError.value = t('wizard.step_folders.max_custom_reached', { n: maxCustom.value });
        return;
    }
    const err = validateName(newCustomName.value);
    if (err) {
        newCustomError.value = err;
        return;
    }
    customFolders.value.push({ name: newCustomName.value.trim(), error: null });
    newCustomName.value = '';
    newCustomError.value = null;
    emitSelection();
}

function removeCustom(index: number) {
    customFolders.value.splice(index, 1);
    emitSelection();
}

function updateCustom(index: number, value: string) {
    customFolders.value[index].name = value;
    customFolders.value[index].error = validateName(value, index);
    emitSelection();
}

function toggleStandard(_key: string) {
    emitSelection();
}

function emitSelection() {
    const result: CaseFolderInput[] = [];
    templates.value.forEach(tpl => {
        if (checked.value[tpl.key]) {
            result.push({
                name: resolveName(tpl),
                category: tpl.category,
                is_custom: false,
            });
        }
    });
    customFolders.value.forEach(cf => {
        if (cf.name.trim() && !cf.error) {
            result.push({
                name: cf.name.trim(),
                category: null,
                is_custom: true,
            });
        }
    });
    emit('update:modelValue', result);
}

// Re-resolve names when the case locale changes
watch(() => props.locale, () => emitSelection());
</script>

<template>
    <div class="folder-selector">
        <div v-if="loading" class="text-center py-4 text-gray-500">
            {{ t('common.loading') }}
        </div>

        <template v-else>
            <!-- Predefined catalog -->
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-6">
                <label
                    v-for="tpl in templates"
                    :key="tpl.key"
                    class="flex items-center gap-2 p-3 border rounded cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                >
                    <input
                        type="checkbox"
                        v-model="checked[tpl.key]"
                        @change="toggleStandard(tpl.key)"
                        class="form-checkbox text-primary"
                    />
                    <span class="text-sm">{{ t(tpl.i18n_key, locale) }}</span>
                </label>
            </div>

            <!-- Custom folders -->
            <div class="mb-4">
                <h4 class="font-semibold mb-2">
                    {{ t('wizard.step_folders.add_custom') }}
                    <span class="text-xs text-gray-500 ml-2">
                        ({{ customCount }}/{{ maxCustom }})
                    </span>
                </h4>

                <ul v-if="customFolders.length > 0" class="mb-3 space-y-2">
                    <li v-for="(cf, i) in customFolders" :key="i" class="flex items-center gap-2">
                        <input
                            type="text"
                            :value="cf.name"
                            @input="updateCustom(i, ($event.target as HTMLInputElement).value)"
                            class="form-input flex-1"
                            :class="{ 'border-danger': cf.error }"
                        />
                        <button
                            type="button"
                            @click="removeCustom(i)"
                            class="text-danger hover:underline text-sm"
                        >
                            {{ t('common.remove') }}
                        </button>
                    </li>
                </ul>

                <p v-if="customFolders.some(cf => cf.error)" class="text-danger text-xs mb-2">
                    {{ customFolders.find(cf => cf.error)?.error }}
                </p>

                <div class="flex items-center gap-2">
                    <input
                        v-model="newCustomName"
                        type="text"
                        :placeholder="t('wizard.step_folders.custom_placeholder')"
                        :disabled="!canAddCustom"
                        class="form-input flex-1"
                        @keyup.enter="addCustom"
                    />
                    <button
                        type="button"
                        @click="addCustom"
                        :disabled="!canAddCustom || !newCustomName.trim()"
                        class="btn btn-primary"
                    >
                        + {{ t('common.add') }}
                    </button>
                </div>
                <p v-if="newCustomError" class="text-danger text-xs mt-1">{{ newCustomError }}</p>
            </div>

            <!-- Empty selection warning -->
            <div
                v-if="noSelection"
                class="bg-warning/10 border border-warning text-warning-dark p-3 rounded text-sm"
            >
                {{ t('wizard.step_folders.no_folders_warning') }}
            </div>

            <!-- Summary counter -->
            <div v-else class="text-sm text-gray-500 mt-2">
                {{ t('wizard.step_folders.total_selected', { n: totalSelected }) }}
            </div>
        </template>
    </div>
</template>
```

### 3.5 Vista `StepFolders.vue`

```vue
<!-- resources/js/src/views/cases/wizard/steps/StepFolders.vue -->
<script lang="ts" setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useCaseWizard } from '@/composables/useCaseWizard';
import FolderSelector from '@/components/cases/wizard/FolderSelector.vue';

const { t } = useI18n();
const { state } = useCaseWizard();

// Fallback to UI locale if caseDetails.language is not yet set
const caseLocale = computed(() => state.value.caseDetails.language || 'en');

const selectedFolders = computed({
    get: () => state.value.folders.selected,
    set: (value) => { state.value.folders.selected = value; },
});
</script>

<template>
    <div class="step-folders">
        <h2 class="text-xl font-semibold mb-2">
            {{ t('wizard.step_folders.title') }}
        </h2>
        <p class="text-gray-500 mb-6">
            {{ t('wizard.step_folders.description') }}
        </p>

        <FolderSelector
            v-model="selectedFolders"
            :locale="caseLocale"
        />
    </div>
</template>
```

### 3.6 Cambios en `CaseWizard.vue`

```vue
<!-- resources/js/src/views/cases/wizard/CaseWizard.vue (additions) -->
<script lang="ts" setup>
// ... existing imports ...
import StepFolders from './steps/StepFolders.vue';

const stepComponents = {
    StepClient,
    StepCompanion,
    StepType,
    StepDetails,
    StepFolders,   // ← NEW
    StepChecklist,
    StepSummary,
};

// In the submit handler, include folders in the payload:
async function submitCase() {
    const payload: CreateCaseData = {
        // ... existing fields ...
        folders: state.value.folders.selected,
    };

    const created = await caseService.create(payload);

    // After success, navigate to case detail with a flag to enable the
    // folder sync banner polling.
    router.push({
        name: 'case-show',
        params: { id: created.id },
        query: { just_created: '1' },
    });
}
</script>
```

### 3.7 Cambios en `StepSummary.vue`

```vue
<!-- resources/js/src/views/cases/wizard/steps/StepSummary.vue (additions) -->
<template>
    <!-- ... existing summary sections ... -->

    <!-- Folder structure summary -->
    <section class="mb-6">
        <h3 class="font-semibold mb-2">{{ t('wizard.step_folders.title') }}</h3>

        <div v-if="state.folders.selected.length === 0" class="text-warning text-sm">
            {{ t('wizard.step_folders.no_folders_warning') }}
        </div>

        <div v-else>
            <p class="text-sm text-gray-500 mb-2">
                {{ t('wizard.step_folders.total_selected', { n: state.folders.selected.length }) }}
            </p>
            <ul class="grid grid-cols-2 md:grid-cols-3 gap-1 text-sm">
                <li
                    v-for="f in state.folders.selected"
                    :key="f.name"
                    class="flex items-center gap-2"
                >
                    <span class="icon">📁</span>
                    <span>{{ f.name }}</span>
                    <span v-if="f.is_custom" class="badge-info">
                        {{ t('wizard.step_folders.custom_tag') }}
                    </span>
                </li>
            </ul>
        </div>
    </section>
</template>
```

### 3.8 Banner de sincronización en `show.vue`

```vue
<!-- resources/js/src/views/cases/show.vue (additions) -->
<script lang="ts" setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { caseService } from '@/services/caseService';

const route = useRoute();
const { t } = useI18n();

const syncBanner = ref<{ visible: boolean; synced: number; total: number; provider: string; failed: boolean }>({
    visible: false,
    synced: 0,
    total: 0,
    provider: '',
    failed: false,
});

let pollHandle: ReturnType<typeof setInterval> | null = null;

async function pollFolderSync(caseId: number) {
    try {
        const status = await caseService.getFolderSyncStatus(caseId);
        syncBanner.value = {
            visible: status.status !== 'synced',
            synced: status.synced_count,
            total: status.total_count,
            provider: status.provider,
            failed: status.status === 'failed',
        };
        if (status.status === 'synced' || status.status === 'failed') {
            stopPolling();
        }
    } catch (e) {
        // silent — banner just stays hidden
        stopPolling();
    }
}

function stopPolling() {
    if (pollHandle) {
        clearInterval(pollHandle);
        pollHandle = null;
    }
}

onMounted(() => {
    // Only poll if the case was just created in this navigation
    if (route.query.just_created === '1') {
        const caseId = Number(route.params.id);
        syncBanner.value.visible = true;
        pollFolderSync(caseId);
        pollHandle = setInterval(() => pollFolderSync(caseId), 3000);
    }
});

onBeforeUnmount(() => stopPolling());
</script>

<template>
    <!-- Sync banner at the top of the case detail view -->
    <div
        v-if="syncBanner.visible"
        class="rounded p-3 mb-4 text-sm"
        :class="{
            'bg-info/10 text-info-dark border border-info': !syncBanner.failed,
            'bg-danger/10 text-danger-dark border border-danger': syncBanner.failed,
        }"
    >
        <template v-if="syncBanner.failed">
            {{ t('case.folder_sync.failed') }}
        </template>
        <template v-else-if="syncBanner.synced < syncBanner.total">
            {{ t('case.folder_sync.banner', {
                synced: syncBanner.synced,
                total: syncBanner.total,
                provider: syncBanner.provider,
            }) }}
        </template>
        <template v-else>
            {{ t('case.folder_sync.completed') }}
        </template>
    </div>

    <!-- ... rest of case detail layout ... -->
</template>
```

---

## 4. Internacionalización (i18n)

Las claves son **FLAT** (G6). Se añaden a los **16 archivos** `resources/js/src/locales/*.json`. Aquí van los dos más representativos completos; los otros 14 siguen el mismo patrón.

### 4.1 `es.json` (additions)

```json
{
    "folder_templates.admission": "Admisión",
    "folder_templates.archive": "Archivo",
    "folder_templates.hearing": "Audiencias",
    "folder_templates.letters": "Cartas",
    "folder_templates.communication": "Comunicaciones",
    "folder_templates.accounting": "Contabilidad",
    "folder_templates.contract": "Contrato",
    "folder_templates.documents": "Documentos",
    "folder_templates.links": "Enlaces",
    "folder_templates.evidence": "Evidencia",
    "folder_templates.forms": "Formularios",
    "folder_templates.history": "Historial",
    "folder_templates.other": "Otros",
    "folder_templates.questionary": "Cuestionarios",

    "wizard.step_folders.title": "Directorios",
    "wizard.step_folders.description": "Elige las carpetas que se crearán en tu repositorio para este expediente. Puedes añadir carpetas personalizadas.",
    "wizard.step_folders.add_custom": "Añadir carpeta personalizada",
    "wizard.step_folders.custom_placeholder": "Nombre de carpeta",
    "wizard.step_folders.custom_tag": "Personalizada",
    "wizard.step_folders.empty": "El nombre no puede estar vacío.",
    "wizard.step_folders.max_custom_reached": "Máximo {n} carpetas personalizadas.",
    "wizard.step_folders.duplicate": "Este nombre ya existe.",
    "wizard.step_folders.invalid_chars": "Los caracteres < > : \" / \\ | ? * no están permitidos.",
    "wizard.step_folders.reserved_name": "Este nombre está reservado por el sistema operativo.",
    "wizard.step_folders.no_folders_warning": "Sin carpetas seleccionadas. El expediente se creará sin estructura de directorios.",
    "wizard.step_folders.total_selected": "{n} carpetas seleccionadas.",

    "validation.folder_name.required": "El nombre de la carpeta es obligatorio.",
    "validation.folder_name.min": "El nombre debe tener al menos {min} carácter.",
    "validation.folder_name.max": "El nombre no puede exceder {max} caracteres.",
    "validation.folder_name.invalid_chars": "Los caracteres < > : \" / \\ | ? * no están permitidos.",
    "validation.folder_name.leading_dot": "El nombre no puede consistir únicamente en puntos.",
    "validation.folder_name.reserved": "Este nombre está reservado por el sistema operativo.",
    "validation.folder_name.duplicate": "Nombre de carpeta duplicado.",
    "validation.folder_name.too_many_custom": "Máximo {max} carpetas personalizadas.",

    "case.folder_sync.banner": "Sincronizando {synced} de {total} carpetas con {provider}…",
    "case.folder_sync.completed": "Todas las carpetas se sincronizaron correctamente.",
    "case.folder_sync.failed": "Algunas carpetas no pudieron crearse. Inténtalo desde la pestaña Documentos."
}
```

### 4.2 `en.json` (additions)

```json
{
    "folder_templates.admission": "Admission",
    "folder_templates.archive": "Archive",
    "folder_templates.hearing": "Hearings",
    "folder_templates.letters": "Letters",
    "folder_templates.communication": "Communications",
    "folder_templates.accounting": "Accounting",
    "folder_templates.contract": "Contract",
    "folder_templates.documents": "Documents",
    "folder_templates.links": "Links",
    "folder_templates.evidence": "Evidence",
    "folder_templates.forms": "Forms",
    "folder_templates.history": "History",
    "folder_templates.other": "Other",
    "folder_templates.questionary": "Questionnaires",

    "wizard.step_folders.title": "Directories",
    "wizard.step_folders.description": "Choose the folders that will be created in your repository for this case. You can add custom folders.",
    "wizard.step_folders.add_custom": "Add custom folder",
    "wizard.step_folders.custom_placeholder": "Folder name",
    "wizard.step_folders.custom_tag": "Custom",
    "wizard.step_folders.empty": "Name cannot be empty.",
    "wizard.step_folders.max_custom_reached": "Maximum {n} custom folders.",
    "wizard.step_folders.duplicate": "This name already exists.",
    "wizard.step_folders.invalid_chars": "Characters < > : \" / \\ | ? * are not allowed.",
    "wizard.step_folders.reserved_name": "This name is reserved by the operating system.",
    "wizard.step_folders.no_folders_warning": "No folders selected. The case will be created without a directory structure.",
    "wizard.step_folders.total_selected": "{n} folders selected.",

    "validation.folder_name.required": "Folder name is required.",
    "validation.folder_name.min": "Name must be at least {min} character.",
    "validation.folder_name.max": "Name cannot exceed {max} characters.",
    "validation.folder_name.invalid_chars": "Characters < > : \" / \\ | ? * are not allowed.",
    "validation.folder_name.leading_dot": "Name cannot consist only of dots.",
    "validation.folder_name.reserved": "This name is reserved by the operating system.",
    "validation.folder_name.duplicate": "Duplicate folder name.",
    "validation.folder_name.too_many_custom": "Maximum {max} custom folders.",

    "case.folder_sync.banner": "Syncing {synced} of {total} folders with {provider}…",
    "case.folder_sync.completed": "All folders synced successfully.",
    "case.folder_sync.failed": "Some folders could not be created. Try again from the Documents tab."
}
```

### 4.3 frances idioma

Aplicar el **mismo set de claves** traducidas en:
`fr.json`.

> **G6 recordatorio:** estas claves son strings FLAT con puntos en el nombre, NO objetos anidados. Si un script Python procesa los JSON puede anidarlas silenciosamente — verificar con `jq 'keys[]'` post-edición.

---

## 5. Riesgos y Casos de Borde

| Riesgo | Probabilidad | Impacto | Mitigación |
|---|---|---|---|
| Sync parcial al cloud (algunas carpetas fallan) | Media | Medio | `syncCaseFolderStructure` captura excepciones por carpeta; `folder_sync_status='failed'` permite reintento manual desde la pestaña Documentos. Banner rojo en `show.vue`. |
| Duplicados case-insensitive (`Documentos` vs `documentos`) | Media (input humano) | Medio | `FolderNameValidator` en frontend + `withValidator` en `StoreCaseRequest`, ambos con `mb_strtolower`. Backup adicional en `FolderService::createSelectedStructure()` con `$seenLower`. |
| Nombres reservados Windows (`CON`, `PRN`, `LPT1`…) | Baja | Alto si pasa | Lista en `config/case_folders.php`, validada en frontend y backend. Bloquear con error 422 antes del INSERT. |
| Reintentos del job duplican carpetas en cloud | Media (jobs con retry) | Medio | `SyncCaseFolderStructure` ya marca `sync_status='synced'` tras éxito; usa `findOrCreateFolder` en el proveedor para idempotencia (OneDrive: `conflictBehavior=fail` + GET previo; Drive: query por nombre). |
| Usuario deselecciona todas las carpetas | Baja | Bajo | Warning amarillo en `StepFolders` y `StepSummary`; permitido (uso legítimo para casos sin estructura predefinida). |
| Más de 30 carpetas por payload (DoS) | Baja | Medio | `max:30` en `StoreCaseRequest`; clamp adicional en `FolderService::normalizeSelection()`. |
| Caracteres Unicode legítimos (acentos, ñ, 中文) | Alta (input no-inglés) | Bajo si se bloquea | Regex `not_regex:/[<>:"\/\\\\|?*]/u` solo bloquea símbolos prohibidos; Unicode pasa. OneDrive y Drive aceptan UTF-8 en nombres. |
| Cambio de `caseDetails.language` después de seleccionar | Media | Bajo | `watch(props.locale)` en `FolderSelector` re-emite la selección con nombres re-traducidos. Las custom no se tocan. |
| Backward compatibility con tests / imports antiguos | Alta inicialmente | Alto si rompe | `createDefaultStructure()` se mantiene como alias hacia `createSelectedStructure($case, null)`. Fallback al catálogo completo en español si no llega `folders`. |
| `config:cache` en producción ignorando cambios | Media en deploy | Medio | Ejecutar `php artisan config:clear && php artisan config:cache` en el pipeline de deploy. Documentar en runbook. |

---

## 6. Checklist de Implementación

### Fase 1 — Backend ✅ COMPLETADA (2026-05-11)

- [x] Crear `config/case_folders.php` con catálogo y reglas de validación
- [x] Crear `app/Services/Document/FolderNameValidator.php`
- [x] Modificar `app/Services/Document/FolderService.php`:
  - [x] Añadir `createSelectedStructure(ImmigrationCase $case, ?array $folders = null)`
  - [x] Convertir `createDefaultStructure()` en alias backward-compatible
  - [x] Añadir `normalizeSelection()` y `defaultCatalogSelection()` privados
- [x] Modificar `app/Services/Case/CaseService.php::createCase()` — extraer `folders` del payload y pasarlo a `FolderService`
- [x] Modificar `app/Http/Requests/Case/StoreCaseRequest.php` — añadir reglas `folders.*` y `withValidator` para reservados/duplicados/cap
- [x] Crear `app/Http/Controllers/Api/CaseFolderTemplateController.php` con `index()`
- [x] Añadir ruta `GET /api/case-folders/defaults` en `routes/api.php` dentro del grupo `auth:sanctum + tenant`

### Fase 2 — Frontend ✅ COMPLETADA (2026-05-11)

- [x] Extender `resources/js/src/types/case.ts` con `FolderTemplate`, `FolderValidationConfig`, `CaseFolderInput`, `CreateCaseData.folders`
- [x] Crear `resources/js/src/services/caseFolderTemplateService.ts` con caché en memoria
- [x] Modificar `resources/js/src/composables/useCaseWizard.ts`:
  - [x] Extender `WizardState` con `folders.selected`
  - [x] Insertar paso `folders` en `WIZARD_STEPS` (orden 5)
  - [x] Shiftear `checklist` (6) y `summary` (7)
  - [x] Añadir validación de step 5 (siempre válido, selección vacía permitida)
- [x] Crear `resources/js/src/components/cases/wizard/FolderSelector.vue`
- [x] Crear `resources/js/src/views/cases/wizard/steps/StepFolders.vue`
- [x] Modificar `resources/js/src/views/cases/wizard/CaseWizard.vue`:
  - [x] Registrar `StepFolders` en `stepComponents` (lazy-loaded)
  - [x] Incluir `folders: state.folders.selected` en el payload de `submit()`
  - [x] Navegar con `query: { just_created: '1' }` tras éxito
- [x] Modificar `resources/js/src/views/cases/wizard/steps/StepSummary.vue` — añadir sección "Directorios"
- [ ] Modificar `resources/js/src/views/cases/show.vue` — banner de polling con `caseService.getFolderSyncStatus()` *(diferido — PR posterior)*

### Fase 3 — i18n ✅ COMPLETADA PARCIALMENTE (2026-05-11)

- [x] Añadir las claves FLAT en `es.json`, `en.json` y `fr.json` (29 claves: 14 folder_templates + 12 wizard.step_folders + 3 case.folder_sync)
- [ ] Replicar y traducir el mismo set en los otros 13 idiomas (`de, it, pt, ru, pl, tr, ja, zh, el, hu, da, sv, ae`) *(pendiente)*
- [x] Verificado: claves son FLAT (JSON válido, sin objetos anidados)
- **Nota técnica:** La clave `wizard.step_folders.invalid_chars` usa sintaxis Vue I18n `{'<'}` `{'>'}` para escapar los corchetes angulares que el plugin `unplugin-vue-i18n` rechaza como HTML.

### Fase 4 — Tests *(pendiente)*

- [ ] `tests/Unit/Services/Document/FolderNameValidatorTest.php`:
  - [ ] `it_rejects_empty_names`
  - [ ] `it_rejects_names_exceeding_max_length`
  - [ ] `it_rejects_forbidden_filesystem_chars`
  - [ ] `it_rejects_windows_reserved_names_case_insensitive`
  - [ ] `it_rejects_names_consisting_only_of_dots`
  - [ ] `it_accepts_unicode_characters_and_accents`
  - [ ] `it_accepts_legitimate_names_with_spaces`
- [ ] `tests/Feature/Cases/CreateCaseWithFoldersTest.php`:
  - [ ] `it_creates_only_selected_default_folders`
  - [ ] `it_creates_custom_folders_with_is_default_false`
  - [ ] `it_falls_back_to_full_catalog_when_folders_omitted`
  - [ ] `it_rejects_more_than_max_custom_folders`
  - [ ] `it_rejects_duplicate_folder_names_case_insensitive`
  - [ ] `it_rejects_reserved_windows_names`
  - [ ] `it_creates_case_with_zero_folders_when_selection_empty`
  - [ ] `it_dispatches_cloud_sync_job_for_cloud_tenants`
  - [ ] `it_creates_physical_directories_for_local_tenants`
  - [ ] `it_isolates_folder_creation_per_tenant`

### Fase 5 — Validación Manual

- [ ] Crear expediente con selección parcial (5 carpetas) → verificar que solo se crean 5 en `document_folders`
- [ ] Crear expediente con 3 personalizadas → verificar `is_default=false` en las personalizadas
- [ ] Intentar crear carpeta llamada `CON` → verificar 422 con mensaje claro
- [ ] Intentar crear dos carpetas `Documentos` / `documentos` → verificar bloqueo
- [ ] Crear expediente con 0 carpetas → verificar warning en Summary y caso creado sin folders
- [ ] Cambiar `caseDetails.language` de `es` a `en` y verificar que los nombres en el selector cambian (`Admisión` → `Admission`)
- [ ] Crear expediente en tenant con OneDrive → verificar banner azul durante sync + verde al completar
- [ ] Forzar fallo de OneDrive (token inválido) → verificar banner rojo y mensaje de error
- [ ] Ejecutar tests existentes (`tests/Feature/Cases`) para confirmar que el fallback a catálogo completo no rompe nada

---

## 7. Advertencias Específicas del Proyecto (Gotchas)

1. **G1 (Multi-tenancy):** ninguna tabla nueva. `document_folders` ya usa `BelongsToTenant`; las carpetas creadas vía `createSelectedStructure` heredan `tenant_id` del caso. No introducir el catálogo en DB.

2. **G2 (FK-backed indexes):** no aplica — no se modifica esquema.

3. **G5 (v-can/v-role son UX):** la validación de nombres reservados, caracteres prohibidos y duplicados se hace **siempre en backend** (`StoreCaseRequest` + `FolderNameValidator`). El frontend bloquea visualmente, pero el servidor es la autoridad final.

4. **G6 (i18n FLAT):** las 40+ claves nuevas son strings con puntos en el nombre, NO objetos anidados. Tras editar los JSON con cualquier script, verificar con `jq` que las claves siguen siendo planas.

5. **G7 (Observers):** la creación de carpetas **no va en un Observer**. Se invoca explícitamente desde `CaseService::createCase()` dentro de `DB::transaction`. Mezclar esto con observers de cascade soft-delete contamina responsabilidades.

6. **G9 (Cloud storage failures):** el job `SyncCaseFolderStructure` ya usa `ResilientStorageProvider`. No llamar `OneDriveProvider::createFolder()` directamente desde `FolderService` para las carpetas ad-hoc; siempre vía `StorageProviderFactory::makeForTenant()`.

7. **`config:cache` en producción:** si se cachea la configuración (`php artisan config:cache`) y luego se edita `config/case_folders.php`, los cambios no toman efecto hasta `config:clear && config:cache`. Documentar en el runbook de deploy.

8. **Sincronización del job con reintentos:** si `SyncCaseFolderStructure` falla y reintenta, debe ser idempotente respecto a las carpetas ya creadas en cloud. El job actual marca `folder.sync_status='synced'` y skip-ea filas ya sincronizadas; no romper esta lógica al integrar las custom folders.

9. **Backward compatibility crítico:** scripts de import masivo, comandos Artisan y tests legacy invocan `FolderService::createDefaultStructure($case)` sin segundo argumento. Este alias **debe seguir existiendo** y delegando a `createSelectedStructure($case, null)` con fallback al catálogo completo en español.

10. **`StepSummary` indexing:** el paso Summary lee step indices duros en algunos puntos (`order: 6` antes, `order: 7` ahora). Auditar `useCaseWizard.ts`, `CaseWizard.vue` (progress bar) y cualquier `goToStep(n)` hardcoded antes del merge.
