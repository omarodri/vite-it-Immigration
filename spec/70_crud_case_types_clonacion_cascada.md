---
tipo: spec
numero: 70
modulo: Administración / Expedientes
dominio: ADMIN/CASES
estado: PART
tags: [spec, admin, case-types, crud, clonacion, deep-copy, workflow, rbac, soft-delete]
relacionados:
  - "[[07_epic_2.1_expedientes_core|Spec 07]]"
  - "[[46_workflows_dinamicos_motor_etapas|Spec 46]]"
  - "[[50_edicion_clonacion_tareas_workflow|Spec 50]]"
  - "[[65_panel_configuracion_codigo_expediente|Spec 65]]"
  - "[[66_rbac_granular_workflow_tasks_case_code|Spec 66]]"
  - "[[67_rbac_granular_fase2|Spec 67]]"
---
# [[70_crud_case_types_clonacion_cascada|Spec 70]] — CRUD de Tipos de Expediente con Clonación en Cascada

**Dominio:** ADMIN / CASES  
**Estado:** PART (Fases 1-5 implementadas · Fase 6 tests pendiente)  
**Relacionados:** 07 (Core Expedientes), 46 (Motor Workflows Dinámicos), 50 (Edición/Clonación Tareas), 65 (Panel Config. Código), 66 (RBAC Granular Workflow+CaseCode), 67 (RBAC Fase 2 — labels i18n roles)  
**Creado:** 2026-05-28  
**Autor:** Análisis de Impacto — Architect Agent + Validación de Reglas de Negocio

---

## Resumen Ejecutivo

El sistema actual gestiona los tipos de expediente (`case_types`) como entidades semi-fijas. No existe panel administrativo para que el Super Administrador o Administrador cree, edite, elimine o duplique tipos de expediente junto con toda su estructura de workflow asociada.

Esta spec introduce:

1. **CRUD completo** de `case_types` con formulario trilingüe (ES/EN/FR).
2. **Soft Delete libre** — sin restricción de negocio que lo bloquee. El soft-delete de un tipo es siempre seguro porque los expedientes son autocontenidos (snapshot). Se muestra un **aviso informativo** si existen casos activos, pero nunca un bloqueo. El verdadero fix es técnico: corregir la relación `caseType()` en `ImmigrationCase` para usar `withTrashed()` y añadir null-safety en los Resources que acceden a `$case->caseType->property`.
3. **Clonación en Cascada (Deep Copy)** — la operación crítica: al clonar un `CaseType`, el sistema duplica automáticamente todas sus `WorkflowStage`, todas sus `TaskTemplate` (con sort_order preservado), y todas sus traducciones polimórficas en 3 idiomas.
4. **RBAC granular** con 5 permisos nuevos (`case_types.view/create/update/delete/clone`) integrados al panel de administración existente.

```
CaseType  (origen)                     CaseType (clon — tenant-propio, is_active=false)
  └── WorkflowStage[]                    └── WorkflowStage[] ← IDs nuevos, sort_order preservado
        └── TaskTemplate[]                     └── TaskTemplate[] ← IDs nuevos, FK al nuevo Stage
              └── translations[]                     └── translations[] ← copiadas (es/en/fr)
```

---

## Hallazgos Críticos Verificados en el Codebase

| # | Hallazgo | Archivo | Impacto en el Diseño |
|---|---------|---------|----------------------|
| H1 | `CaseType` **NO usa `BelongsToTenant`** — usa su propio global scope `tenant_or_global` (permite tipos con `tenant_id = NULL` globales, visibles para todos) | `app/Models/CaseType.php:16-33` | El clon debe forzar siempre `tenant_id = tenant_actual` — jamás copiar `NULL`. Queries de unicidad deben usar `withoutGlobalScope('tenant_or_global')` + filtro `tenant_id` explícito |
| H2 | `case_types.code` tiene `->unique()` **GLOBAL** (no por tenant) | `database/migrations/0001_01_01_000006_create_business_tables.php:107` | Hay que migrar a `unique(['tenant_id','code'])` antes de implementar clone. El campo es `string(10)` — sufijos largos como `"_COPY"` no caben |
| H3 | `case_types` **NO tiene SoftDeletes** ni `deleted_at` | `app/Models/CaseType.php` | Requiere migración + trait. `WorkflowStage` y `TaskTemplate` ya usan `SoftDeletes` |
| H4 | `cases.case_type_id` usa `->restrictOnDelete()` a nivel BD | `database/migrations/0001_01_01_000006_create_business_tables.php:122` | La BD ya bloquea hard-delete con casos. Con soft-delete la restricción no aplica (el registro físico persiste). Refuerza la seguridad de soft-delete como único flujo expuesto en API |
| H5 | Los expedientes guardan `workflow_snapshot` JSON autocontenido y materializan sus propias `case_stages` y `tasks` | `app/Services/Workflow/WorkflowInstantiator.php` | Los expedientes históricos **no dependen** de las plantillas vivas — soft-delete de un `CaseType` es completamente seguro |
| H6 | Las **traducciones son polimórficas relacionales** — tabla `translations` con `(translatable_type, translatable_id, locale, field, value)`. `CaseType` usa columna `name` directa; `WorkflowStage` y `TaskTemplate` sí usan el trait `HasTranslations` | `app/Models/Traits/HasTranslations.php` | El clone de traducciones usa `translationsByField()` + `setTranslations()` del trait existente |
| H7 | Patrón canónico de clonación ya existe en `TaskTemplateService::clone()` (líneas 59-80) — replica el modelo, recalcula `sort_order = MAX+1`, copia traducciones vía `setTranslations()` | `app/Services/Workflow/TaskTemplateService.php:59-80` | El nuevo `CaseTypeService::clone()` sigue este patrón exactamente, excepto que preserva `sort_order` original (no recalcula) porque el árbol clonado es aislado bajo un nuevo `case_type_id` |
| H8 | `workflow_stages.code` está **activamente en uso**: se copia a `CaseStage.code`, se registra en activity log (`from_code`/`to_code`) y se serializa en el `workflow_snapshot` | `app/Services/Workflow/WorkflowInstantiator.php:47,198-199,214` | No se elimina el campo `code` de `workflow_stages`. Al clonar con un nuevo `case_type_id`, el code original no colisiona (la unique es por `tenant_id, case_type_id, code`) |
| H9 | El `code` de `CaseType` alimenta el bloque `TT` del patrón de `case_number` (`YY-TT-AAAA-NNNN`, gotcha G24) | `app/Services/Case/BlockResolvers/TypeBlockResolver.php` | Un clon con code auto-generado (`"AS2"`) contaminaría números de expedientes si se activa sin revisar. El clon nace con `is_active = false` para forzar revisión |
| H10 | No existen permisos `case_types.*` en el sistema — solo existe el global scope y la restricción de `super-admin` en rutas | `database/migrations/` (verificado: sin migración de permisos `case_types`) | Requiere migración dedicada con `Permission::firstOrCreate` (G4 / D-03) |
| H11 | `ImmigrationCase::caseType()` es un `BelongsTo(CaseType::class)` **sin `withTrashed()`**. Si el tipo está soft-deleted, Eloquent devuelve `null`. `DashboardCaseResource` accede a `$this->caseType->id/name/code` sin null-check → `NullPointerException` en producción | `app/Models/ImmigrationCase.php:214` · `app/Http/Resources/DashboardCaseResource.php:28-31` | Dos fixes obligatorios: (1) añadir `->withTrashed()` a la relación, (2) añadir null-safety (`?->`) en todos los Resources que acceden a propiedades del tipo. Estos fixes son el requisito previo para permitir soft-delete sin restricción |

---

## Decisiones Clave

| # | Decisión | Justificación |
|---|---------|---------------|
| D1 | **Clonación síncrona en `DB::transaction`**, NO Job asíncrono | Volumen típico: 4 etapas × 8 tareas × 3 idiomas ≈ 253 INSERTs ≈ 250–500 ms. Muy por debajo del límite HTTP (30s). Ventaja: atomicidad trivial, sin estados intermedios visibles, UX inmediata. Job solo se justificaría con miles de tareas o APIs externas (no es este caso) |
| D2 | **Migrar `case_types.code` a unique compuesta `(tenant_id, code)`** como paso previo | El unique global actual es un bug de multi-tenancy latente (dos tenants no pueden ambos tener `"AS"`). La migración no produce conflictos porque el unique global preexistente ya los previno |
| D3 | **Incluir `deleted_at` en la unique**: `unique(['tenant_id','code','deleted_at'])`  | MySQL incluye filas soft-deleted en índices unique. Sin `deleted_at` en la unique, tras borrar un tipo `"AS"` no se puede crear otro `"AS"` aunque el viejo esté trashed |
| D4 | **Clon nace con `is_active = false` y code auto-generado** (`"AS2"`, `"AS3"`...) | El code es parte del patrón de case_number. Activar un tipo con code automático sin revisión contaminaría expedientes futuros. `is_active=false` fuerza al admin a revisar antes de usar el tipo |
| D5 | **Redirigir al `edit` del clon post-clonación** | Fuerza la revisión inmediata de `name` y `code`. El toggle `is_active` permanece deshabilitado hasta que el code sea editado manualmente (UX de guardrail, no seguridad) |
| D6 | **Cascade soft-delete via `CaseTypeObserver`** hacia stages y task_templates | `WorkflowStage` y `TaskTemplate` ya usan SoftDeletes. El observer garantiza consistencia en listados del builder (que filtran por no-trashed). Sigue el patrón de `ClientObserver`/`CompanionObserver` (CLAUDE.md) |
| D7 | **Soft-delete siempre libre — sin bloqueo** (corrección respecto al análisis inicial) | El `CaseType` es una plantilla independiente de su realización. Los expedientes son autocontenidos: `workflow_snapshot` JSON, `case_stages` y `tasks` materializados. Borrar la plantilla no afecta ningún dato histórico. La restricción era un workaround de un bug técnico (H11), no una regla de negocio. **El flujo correcto:** arreglar `withTrashed()` en la relación + null-safety en Resources, y mostrar un aviso informativo (no un bloqueo) si hay casos activos. El `->restrictOnDelete()` de BD cubre el único caso realmente peligroso: el hard-delete (nunca expuesto en la API) |
| D8 | **Tipos globales (`tenant_id = NULL`) son solo clonables, no editables/eliminables por admin** | Los tipos globales son plantillas del sistema. Un admin puede clonarlos para personalizarlos en su tenant. Solo super-admin puede crear/editar/eliminar tipos globales |
| D9 | **`sort_order` se copia literal** en la clonación del árbol | Al ser un árbol nuevo e independiente bajo un nuevo `case_type_id`, no hay solapamiento. Difiere del clone de `TaskTemplate` individual (que recalcula MAX+1 porque clona a un stage existente) |
| D10 | **`workflow_stages.code` NO se elimina** | Activamente usado en snapshots, activity log y builder. Al cambiar `case_type_id` en el clon, el code original no colisiona bajo la unique compuesta `(tenant_id, case_type_id, code)` |

---

## 1. Base de Datos

### 1.1 Migración A — Alterar `case_types`: SoftDeletes + unique por tenant

```php
<?php
// database/migrations/2026_XX_XX_000001_alter_case_types_add_softdelete_and_tenant_unique.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_types', function (Blueprint $table) {
            // Soft deletes
            $table->softDeletes()->after('is_active');

            // Migrar unique global → unique por tenant con deleted_at
            // Patrón G2: no hay FK sobre code, así que solo dropUnique
            $table->dropUnique(['code']);

            // Unique compuesta incluyendo deleted_at para permitir reutilizar
            // el mismo code después de un soft-delete
            $table->unique(['tenant_id', 'code', 'deleted_at'], 'case_types_tenant_code_deleted_unique');
        });
    }

    public function down(): void
    {
        Schema::table('case_types', function (Blueprint $table) {
            $table->dropUnique('case_types_tenant_code_deleted_unique');
            $table->unique(['code']);
            $table->dropSoftDeletes();
        });
    }
};
```

> **Nota sobre datos existentes:** El unique global preexistente garantiza que no hay codes duplicados entre tenants en producción. La migración no producirá conflictos.

### 1.2 Migración B — Permisos RBAC `case_types.*`

```php
<?php
// database/migrations/2026_XX_XX_000002_seed_case_types_permissions.php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = collect([
            'case_types.view',
            'case_types.create',
            'case_types.update',
            'case_types.delete',
            'case_types.clone',
        ])->map(fn($name) => Permission::firstOrCreate(['name' => $name]));

        // super-admin y admin: acceso total
        foreach (['super-admin', 'admin'] as $roleName) {
            $role = Role::findByName($roleName);
            $role?->givePermissionTo($permissions);
        }

        // consultor: solo lectura
        Role::findByName('consultor')?->givePermissionTo(
            Permission::findByName('case_types.view')
        );

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        collect([
            'case_types.view', 'case_types.create', 'case_types.update',
            'case_types.delete', 'case_types.clone',
        ])->each(fn($name) => Permission::findByName($name)?->delete());
    }
};
```

---

## 2. Backend

### 2.1 Modelo `CaseType`

Agregar a `app/Models/CaseType.php`:

```php
use Illuminate\Database\Eloquent\SoftDeletes;

// Dentro de la clase:
use SoftDeletes;

// Relación con stages del workflow
public function workflowStages(): HasMany
{
    return $this->hasMany(WorkflowStage::class, 'case_type_id')
                ->orderBy('sort_order');
}

// Informativo para el aviso en UI — no es una restricción de borrado
public function activeCasesCount(): int
{
    return ImmigrationCase::where('case_type_id', $this->id)
        ->whereNotIn('status', ['closed', 'archived'])
        ->count();
}

public function isGlobal(): bool
{
    return $this->tenant_id === null;
}
```

### 2.1b Relación `ImmigrationCase::caseType()` — fix obligatorio (H11)

Modificar en `app/Models/ImmigrationCase.php`:

```php
// ANTES (rompe cuando el tipo está soft-deleted):
public function caseType(): BelongsTo
{
    return $this->belongsTo(CaseType::class);
}

// DESPUÉS — con withTrashed() para que la relación devuelva el tipo
// aunque esté soft-deleted, preservando la integridad de la UI:
public function caseType(): BelongsTo
{
    return $this->belongsTo(CaseType::class)->withTrashed();
}
```

### 2.1c Null-safety en Resources — fix obligatorio (H11)

Los Resources que acceden a `$this->caseType->property` sin null-check deben añadir el operador `?->`. Archivos afectados verificados en el codebase:

**`app/Http/Resources/DashboardCaseResource.php`** (acceso directo sin null-check):
```php
// ANTES:
'case_type' => $this->whenLoaded('caseType', fn () => [
    'id'   => $this->caseType->id,
    'name' => $this->caseType->name,
    'code' => $this->caseType->code,
]),

// DESPUÉS — null-safe + badge "(eliminado)" para tipos trashed:
'case_type' => $this->whenLoaded('caseType', fn () => $this->caseType ? [
    'id'      => $this->caseType->id,
    'name'    => $this->caseType->name . ($this->caseType->trashed() ? ' (' . __('case_types.deleted_badge') . ')' : ''),
    'code'    => $this->caseType->code,
    'deleted' => $this->caseType->trashed(),
] : null),
```

**`app/Http/Resources/CaseResource.php`** — ya usa `$this->whenLoaded('caseType', fn () => new CaseTypeResource($this->caseType))`. Asegurarse de que `CaseTypeResource` maneje un modelo trashed sin crash (no requiere cambios adicionales si el constructor acepta el modelo).

**`app/Http/Resources/ImportantDateAlertResource.php`** — ya usa `$this->immigrationCase->caseType?->id` (null-safe). Sin cambios.

### 2.2 `CaseTypeObserver`

Nuevo archivo `app/Observers/CaseTypeObserver.php` — registrar en `AppServiceProvider::boot()` (gotcha G7):

```php
<?php
namespace App\Observers;

use App\Models\CaseType;

class CaseTypeObserver
{
    public function deleting(CaseType $caseType): void
    {
        // No tocar en forceDelete (protegido por restrictOnDelete de BD)
        if ($caseType->isForceDeleting()) return;

        // Cascade soft-delete hacia stages y sus task_templates
        $stages = $caseType->workflowStages()->withTrashed()->get();
        foreach ($stages as $stage) {
            $stage->taskTemplates()->delete();   // soft-delete en cascada
            $stage->delete();                    // soft-delete
        }
    }

    public function restoring(CaseType $caseType): void
    {
        // Restaurar stages y tasks soft-deleted en el mismo evento de borrado
        foreach ($caseType->workflowStages()->onlyTrashed()->get() as $stage) {
            $stage->taskTemplates()->onlyTrashed()
                ->where('deleted_at', '>=', $caseType->deleted_at)
                ->restore();
            $stage->restore();
        }
    }
}
```

### 2.3 `CaseTypePolicy`

Nuevo archivo `app/Policies/CaseTypePolicy.php`:

```php
<?php
namespace App\Policies;

use App\Models\CaseType;
use App\Models\User;

class CaseTypePolicy
{
    // Gate::before en AuthServiceProvider ya cubre super-admin y admin → bypass

    public function viewAny(User $user): bool
    {
        return $user->can('case_types.view');
    }

    public function view(User $user, CaseType $caseType): bool
    {
        if (! $user->can('case_types.view')) return false;
        // Tipos globales: cualquiera con el permiso puede verlos
        if ($caseType->isGlobal()) return true;
        return $caseType->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('case_types.create');
    }

    public function update(User $user, CaseType $caseType): bool
    {
        if (! $user->can('case_types.update')) return false;
        // Tipos globales: solo super-admin (cubierto por Gate::before)
        if ($caseType->isGlobal()) return false;
        return $caseType->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, CaseType $caseType): bool
    {
        if (! $user->can('case_types.delete')) return false;
        if ($caseType->isGlobal()) return false;   // globales: intocables
        return $caseType->tenant_id === $user->tenant_id;
    }

    public function clone(User $user, CaseType $caseType): bool
    {
        if (! $user->can('case_types.clone')) return false;
        // Clonar tipos globales al propio tenant: permitido (es el caso de uso principal)
        if ($caseType->isGlobal()) return true;
        return $caseType->tenant_id === $user->tenant_id;
    }
}
```

### 2.4 `CaseTypeService`

Nuevo archivo `app/Services/Case/CaseTypeService.php`:

```php
<?php
namespace App\Services\Case;

use App\Models\CaseType;
use App\Models\WorkflowStage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CaseTypeService
{
    /**
     * Clonación en cascada: CaseType → WorkflowStage[] → TaskTemplate[] → Translations.
     * Síncrona dentro de DB::transaction. ~250–500 ms para 4 etapas × 8 tareas × 3 idiomas.
     */
    public function clone(CaseType $source): CaseType
    {
        return DB::transaction(function () use ($source) {
            $tenantId = Auth::user()->tenant_id;

            // 1) Clonar el CaseType raíz — siempre al tenant actual (H1)
            $newType = $source->replicate(['id', 'created_at', 'updated_at', 'deleted_at']);
            $newType->tenant_id  = $tenantId;
            $newType->code       = $this->generateUniqueCode($source->code, $tenantId);
            $newType->name       = $source->name . ' (copia)';
            $newType->is_active  = false;   // fuerza revisión antes de activar (D4)
            $newType->save();

            // 2) Cargar árbol completo de origen ordenado por sort_order
            $stages = WorkflowStage::with([
                    'translations',
                    'taskTemplates' => fn($q) => $q->orderBy('sort_order'),
                    'taskTemplates.translations',
                ])
                ->where('case_type_id', $source->id)
                ->orderBy('sort_order')
                ->get();

            foreach ($stages as $stage) {
                // 3) Clonar WorkflowStage — nuevo case_type_id evita colisión en unique (tenant_id, case_type_id, code)
                $newStage = $stage->replicate(['id', 'created_at', 'updated_at', 'deleted_at']);
                $newStage->tenant_id    = $tenantId;
                $newStage->case_type_id = $newType->id;
                $newStage->sort_order   = $stage->sort_order;   // D9: preservado literal
                $newStage->save();

                // 4) Copiar traducciones de la etapa vía trait HasTranslations (H6)
                foreach ($stage->translationsByField() as $field => $byLocale) {
                    $newStage->setTranslations($field, $byLocale);
                }

                // 5) Clonar TaskTemplates hijos → FK al NUEVO stage (reconstrucción de relación)
                foreach ($stage->taskTemplates as $tmpl) {
                    $newTmpl = $tmpl->replicate(['id', 'created_at', 'updated_at', 'deleted_at']);
                    $newTmpl->tenant_id         = $tenantId;
                    $newTmpl->workflow_stage_id = $newStage->id;   // FK reconstruida
                    $newTmpl->sort_order        = $tmpl->sort_order;
                    $newTmpl->save();

                    foreach ($tmpl->translationsByField() as $field => $byLocale) {
                        $newTmpl->setTranslations($field, $byLocale);
                    }
                }
            }

            return $newType->load('workflowStages.taskTemplates');
        });
    }

    /**
     * Cuenta expedientes activos para el aviso informativo en UI.
     * NO es una validación de borrado — el soft-delete es siempre libre (D7).
     */
    public function getActiveCasesCount(CaseType $caseType): int
    {
        return \App\Models\ImmigrationCase::where('case_type_id', $caseType->id)
            ->whereNotIn('status', ['closed', 'archived'])
            ->count();
    }

    /**
     * Genera un code único para el clon dentro del tenant.
     * code es string(10) — truncar para dejar margen al sufijo numérico.
     * Protegido por la unique de BD como red de seguridad real (R5).
     */
    private function generateUniqueCode(string $base, int $tenantId): string
    {
        $stem = substr($base, 0, 7);    // deja 3 chars para sufijo "2".."99"
        for ($i = 2; $i <= 99; $i++) {
            $candidate = substr($stem . $i, 0, 10);
            $exists = CaseType::withoutGlobalScope('tenant_or_global')
                ->where('tenant_id', $tenantId)
                ->where('code', $candidate)
                ->exists();
            if (! $exists) return $candidate;
        }
        return substr(strtoupper(Str::random(8)), 0, 10);   // fallback improbable
    }

    public function getWithWorkflowCount(): \Illuminate\Database\Eloquent\Collection
    {
        return CaseType::withCount(['workflowStages', 'workflowStages as tasks_count' => fn($q) =>
                $q->join('task_templates', 'workflow_stages.id', '=', 'task_templates.workflow_stage_id')
            ])
            ->orderBy('name')
            ->get();
    }
}
```

### 2.5 Form Requests

**`app/Http/Requests/CaseType/StoreCaseTypeRequest.php`:**

```php
public function rules(): array
{
    $tenantId = auth()->user()->tenant_id;

    return [
        'name'        => ['required', 'string', 'max:120'],
        'code'        => [
            'required', 'string', 'max:10', 'regex:/^[A-Z0-9_]+$/i',
            // Unique por tenant (excluye trashed — soft-deleted libera el code)
            Rule::unique('case_types')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
        ],
        'category'    => ['required', Rule::in(CaseType::CATEGORIES)],
        'description' => ['nullable', 'string', 'max:1000'],
        'is_active'   => ['boolean'],
    ];
}
```

**`app/Http/Requests/CaseType/UpdateCaseTypeRequest.php`:**

```php
public function rules(): array
{
    $tenantId = auth()->user()->tenant_id;
    $id       = $this->route('caseType')->id;

    return [
        'name'        => ['sometimes', 'string', 'max:120'],
        'code'        => [
            'sometimes', 'string', 'max:10', 'regex:/^[A-Z0-9_]+$/i',
            Rule::unique('case_types')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->ignore($id),
        ],
        'category'    => ['sometimes', Rule::in(CaseType::CATEGORIES)],
        'description' => ['nullable', 'string', 'max:1000'],
        'is_active'   => ['boolean'],
    ];
}
```

### 2.6 `CaseTypeResource`

**`app/Http/Resources/CaseTypeResource.php`:**

```php
public function toArray(Request $request): array
{
    return [
        'id'                   => $this->id,
        'name'                 => $this->name,
        'code'                 => $this->code,
        'category'             => $this->category,
        'description'          => $this->description,
        'is_active'            => $this->is_active,
        'is_deleted'           => $this->trashed(),      // true si soft-deleted
        'is_global'            => $this->isGlobal(),
        'tenant_id'            => $this->tenant_id,
        'workflow_stages_count'=> $this->workflow_stages_count ?? 0,
        'tasks_count'          => $this->tasks_count ?? 0,
        // Informativo para el aviso en UI — no bloquea el borrado (D7)
        'active_cases_count'   => $this->when(
            isset($this->active_cases_count),
            $this->active_cases_count ?? 0
        ),
        'created_at'           => $this->created_at?->toISOString(),
        'updated_at'           => $this->updated_at?->toISOString(),
    ];
}
```

### 2.7 `CaseTypeController`

Completar/crear `app/Http/Controllers/Api/CaseTypeController.php`:

```php
<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CaseType\StoreCaseTypeRequest;
use App\Http\Requests\CaseType\UpdateCaseTypeRequest;
use App\Http\Resources\CaseTypeResource;
use App\Models\CaseType;
use App\Services\Case\CaseTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CaseTypeController extends Controller
{
    public function __construct(private readonly CaseTypeService $caseTypeService) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CaseType::class);
        return CaseTypeResource::collection(
            $this->caseTypeService->getWithWorkflowCount()
        );
    }

    public function store(StoreCaseTypeRequest $request): CaseTypeResource
    {
        $this->authorize('create', CaseType::class);
        $caseType = new CaseType($request->validated());
        $caseType->tenant_id = app('tenant')->id;
        $caseType->save();
        return new CaseTypeResource($caseType);
    }

    public function show(CaseType $caseType): CaseTypeResource
    {
        $this->authorize('view', $caseType);
        return new CaseTypeResource($caseType->load('workflowStages'));
    }

    public function update(UpdateCaseTypeRequest $request, CaseType $caseType): CaseTypeResource
    {
        $this->authorize('update', $caseType);
        $caseType->update($request->validated());
        return new CaseTypeResource($caseType);
    }

    public function destroy(CaseType $caseType): JsonResponse
    {
        $this->authorize('delete', $caseType);

        // Soft-delete siempre libre (D7). El contador informativo se expuso
        // en index() para que el frontend muestre el aviso antes de confirmar.
        // La única protección dura es el ->restrictOnDelete() de BD para hard-delete,
        // que nunca se alcanza desde este endpoint.
        $caseType->delete();    // soft-delete; el observer propaga a stages y tasks

        return response()->json(['message' => __('case_types.deleted')]);
    }

    /**
     * GET /api/admin/case-types/{caseType}/active-cases-count
     * Usado por el frontend para mostrar el aviso informativo antes de confirmar el borrado.
     * Separado de index() para no añadir una query a cada fila del listado.
     */
    public function activeCasesCount(CaseType $caseType): JsonResponse
    {
        $this->authorize('delete', $caseType);
        return response()->json([
            'count' => $this->caseTypeService->getActiveCasesCount($caseType),
        ]);
    }

    /**
     * POST /api/admin/case-types/{caseType}/clone
     * Clonación en cascada síncrona: CaseType → Stages → Tasks → Translations
     */
    public function clone(CaseType $caseType): CaseTypeResource
    {
        $this->authorize('clone', $caseType);
        $clone = $this->caseTypeService->clone($caseType);
        return new CaseTypeResource($clone);
    }
}
```

### 2.8 Rutas API en `routes/api.php`

```php
// Dentro del grupo auth:sanctum + tenant + admin (o middleware de permisos):

Route::prefix('admin')->name('admin.')->group(function () {
    // ...rutas admin existentes...

    // CRUD de tipos de expediente
    Route::apiResource('case-types', CaseTypeController::class);
    // Clonación en cascada
    Route::post('case-types/{caseType}/clone', [CaseTypeController::class, 'clone'])
        ->name('case-types.clone');
    // Conteo informativo de casos activos (para aviso pre-borrado en UI)
    Route::get('case-types/{caseType}/active-cases-count', [CaseTypeController::class, 'activeCasesCount'])
        ->name('case-types.active-cases-count');
});
```

> Verificar con `php artisan route:list | grep case-types` tras implementar.

---

## 3. Frontend

### 3.1 Tipos TypeScript

Añadir/actualizar en `resources/js/src/types/index.ts` (o archivo de tipos de casos):

```typescript
export interface CaseType {
  id: number;
  name: string;
  code: string;                        // máx 10 chars, único por tenant
  category: string;
  description: string | null;
  is_active: boolean;
  is_deleted: boolean;                 // true si está soft-deleted
  is_global: boolean;
  tenant_id: number | null;
  workflow_stages_count: number;
  tasks_count: number;
  active_cases_count?: number;         // informativo — cargado bajo demanda antes de borrar
  created_at: string;
  updated_at: string;
}

export interface CaseTypeForm {
  name: string;
  code: string;
  category: string;
  description: string;
  is_active: boolean;
}
```

### 3.2 `caseTypeService.ts`

Nuevo `resources/js/src/services/caseTypeService.ts`:

```typescript
import api from '@/services/api';
import type { CaseType, CaseTypeForm } from '@/types';

export default {
  list() {
    return api.get<CaseType[]>('/admin/case-types');
  },
  get(id: number) {
    return api.get<CaseType>(`/admin/case-types/${id}`);
  },
  create(data: CaseTypeForm) {
    return api.post<CaseType>('/admin/case-types', data);
  },
  update(id: number, data: Partial<CaseTypeForm>) {
    return api.put<CaseType>(`/admin/case-types/${id}`, data);
  },
  remove(id: number) {
    return api.delete(`/admin/case-types/${id}`);
  },
  clone(id: number) {
    return api.post<CaseType>(`/admin/case-types/${id}/clone`);
  },
  // Consulta el conteo de casos activos justo antes de mostrar el modal de borrado
  getActiveCasesCount(id: number) {
    return api.get<{ count: number }>(`/admin/case-types/${id}/active-cases-count`);
  },
};
```

### 3.3 `admin/case-types/index.vue` — Vista de Listado

```vue
<script lang="ts" setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';
import { useMeta } from '@/composables/use-meta';
import caseTypeService from '@/services/caseTypeService';
import type { CaseType } from '@/types';

useMeta({ title: t('case_types.admin_title') });

const router  = useRouter();
const { t }   = useI18n();
const items   = ref<CaseType[]>([]);
const loading = ref(false);
const cloning = ref<number | null>(null);

onMounted(load);

async function load() {
  loading.value = true;
  try {
    const res = await caseTypeService.list();
    items.value = res.data;
  } finally {
    loading.value = false;
  }
}

async function confirmClone(ct: CaseType) {
  const result = await Swal.fire({
    title:             t('case_types.clone_title'),
    html:              t('case_types.clone_confirm', {
                         name:   ct.name,
                         stages: ct.workflow_stages_count,
                         tasks:  ct.tasks_count,
                       }),
    icon:              'warning',
    showCancelButton:  true,
    confirmButtonText: t('case_types.clone_confirm_btn'),
    cancelButtonText:  t('common.cancel'),
    confirmButtonColor:'#4361ee',
  });

  if (!result.isConfirmed) return;

  cloning.value = ct.id;
  try {
    const res = await caseTypeService.clone(ct.id);
    await Swal.fire({
      icon:  'success',
      title: t('case_types.clone_success_title'),
      text:  t('case_types.clone_success_desc'),
      timer: 1500,
      showConfirmButton: false,
    });
    // D5: redirigir al edit del clon para forzar revisión de name y code
    router.push({ name: 'admin-case-types-edit', params: { id: res.data.id } });
  } catch {
    Swal.fire({ icon: 'error', title: t('common.error'), text: t('case_types.clone_error') });
  } finally {
    cloning.value = null;
  }
}

async function confirmDelete(ct: CaseType) {
  // Consultar el conteo de casos activos para el aviso (no es un bloqueo — D7)
  let activeCasesCount = 0;
  try {
    const res = await caseTypeService.getActiveCasesCount(ct.id);
    activeCasesCount = res.data.count;
  } catch {
    // Si falla la consulta, continuar sin aviso — el borrado sigue siendo libre
  }

  // Componer el texto del modal: aviso adicional si hay casos activos
  const warningHtml = activeCasesCount > 0
    ? `<p class="text-warning mt-2 text-sm">${t('case_types.delete_warning_active_cases', { count: activeCasesCount })}</p>`
    : '';

  const result = await Swal.fire({
    title:             t('case_types.delete_title'),
    html:              `<p>${t('case_types.delete_confirm', { name: ct.name })}</p>${warningHtml}`,
    icon:              activeCasesCount > 0 ? 'warning' : 'question',
    showCancelButton:  true,
    confirmButtonText: t('common.delete'),
    confirmButtonColor:'#e7515a',
  });

  if (!result.isConfirmed) return;

  try {
    await caseTypeService.remove(ct.id);
    items.value = items.value.filter(i => i.id !== ct.id);
  } catch (e: any) {
    Swal.fire({ icon: 'error', title: t('common.error'), text: t('common.error') });
  }
}
</script>

<template>
  <div>
    <!-- Breadcrumb y header -->
    <div class="flex items-center justify-between mb-6">
      <h5 class="font-semibold text-lg dark:text-white">
        {{ $t('case_types.admin_title') }}
      </h5>
      <router-link
        v-can="'case_types.create'"
        :to="{ name: 'admin-case-types-create' }"
        class="btn btn-primary gap-2"
      >
        <icon-plus class="w-5 h-5" />
        {{ $t('case_types.create_btn') }}
      </router-link>
    </div>

    <!-- Tabla -->
    <div class="panel">
      <vue3-datatable
        :rows="items"
        :loading="loading"
        :columns="[
          { field: 'name',                  title: $t('case_types.col_name') },
          { field: 'code',                  title: $t('case_types.col_code') },
          { field: 'category',              title: $t('case_types.col_category') },
          { field: 'workflow_stages_count', title: $t('case_types.col_stages') },
          { field: 'tasks_count',           title: $t('case_types.col_tasks') },
          { field: 'is_active',             title: $t('case_types.col_status') },
          { field: 'actions',               title: $t('common.actions'), sort: false },
        ]"
      >
        <template #code="{ value, row }">
          <span class="badge badge-outline-primary font-mono text-xs">{{ value }}</span>
          <span v-if="row.is_global" class="badge badge-outline-secondary text-xs ml-1">
            {{ $t('case_types.global_badge') }}
          </span>
        </template>

        <template #is_active="{ value }">
          <span :class="value ? 'badge badge-outline-success' : 'badge badge-outline-danger'">
            {{ value ? $t('common.active') : $t('common.inactive') }}
          </span>
        </template>

        <template #actions="{ row }">
          <div class="flex items-center gap-2">
            <!-- Editar -->
            <router-link
              v-can="'case_types.update'"
              :to="{ name: 'admin-case-types-edit', params: { id: row.id } }"
              class="btn btn-sm btn-outline-primary"
            >
              <icon-pencil class="w-4 h-4" />
            </router-link>

            <!-- Clonar -->
            <button
              v-can="'case_types.clone'"
              class="btn btn-sm btn-outline-info"
              :disabled="cloning === row.id"
              @click="confirmClone(row)"
            >
              <icon-copy v-if="cloning !== row.id" class="w-4 h-4" />
              <span v-else class="animate-spin w-4 h-4 border-2 border-info rounded-full border-t-transparent" />
            </button>

            <!-- Eliminar — siempre habilitado; el modal informa si hay casos activos -->
            <button
              v-can="'case_types.delete'"
              class="btn btn-sm btn-outline-danger"
              @click="confirmDelete(row)"
            >
              <icon-trash class="w-4 h-4" />
            </button>
          </div>
        </template>
      </vue3-datatable>
    </div>
  </div>
</template>
```

### 3.4 `admin/case-types/edit.vue` — Formulario Create/Edit

```vue
<script lang="ts" setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';
import { useMeta } from '@/composables/use-meta';
import caseTypeService from '@/services/caseTypeService';
import type { CaseType } from '@/types';

const route  = useRoute();
const router = useRouter();
const { t }  = useI18n();

const isEdit    = computed(() => !!route.params.id);
const caseType  = ref<CaseType | null>(null);
const loading   = ref(false);
const saving    = ref(false);
const codeEdited = ref(false);   // D5: guardrail is_active

const form = ref({
  name:        '',
  code:        '',
  category:    '',
  description: '',
  is_active:   false,
});

// D5: desactivar toggle is_active para clones hasta que el code sea editado
const canActivate = computed(() =>
  !isEdit.value || codeEdited.value || (caseType.value?.is_active ?? false)
);

useMeta({ title: computed(() => isEdit.value ? t('case_types.edit_title') : t('case_types.create_title')) });

onMounted(async () => {
  if (!isEdit.value) return;
  loading.value = true;
  try {
    const res  = await caseTypeService.get(Number(route.params.id));
    caseType.value = res.data;
    Object.assign(form.value, {
      name:        res.data.name,
      code:        res.data.code,
      category:    res.data.category,
      description: res.data.description ?? '',
      is_active:   res.data.is_active,
    });
  } finally {
    loading.value = false;
  }
});

async function save() {
  saving.value = true;
  try {
    if (isEdit.value) {
      await caseTypeService.update(Number(route.params.id), form.value);
    } else {
      await caseTypeService.create(form.value);
    }
    router.push({ name: 'admin-case-types' });
  } catch (e: any) {
    Swal.fire({ icon: 'error', title: t('common.error'), text: e?.response?.data?.message });
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div class="max-w-2xl mx-auto">
    <!-- Alerta de revisión para clones (is_active=false, code auto-generado) -->
    <div v-if="isEdit && !caseType?.is_active && !codeEdited"
         class="alert alert-warning mb-4 flex gap-2">
      <icon-alert-triangle class="w-5 h-5 flex-shrink-0" />
      <span>{{ $t('case_types.clone_review_hint') }}</span>
    </div>

    <form @submit.prevent="save" class="panel space-y-5">
      <!-- Nombre -->
      <div>
        <label class="form-label">{{ $t('case_types.field_name') }} *</label>
        <input v-model="form.name" type="text" class="form-input" required maxlength="120" />
      </div>

      <!-- Code (siglas) -->
      <div>
        <label class="form-label">{{ $t('case_types.field_code') }} *</label>
        <input
          v-model="form.code"
          type="text"
          class="form-input font-mono uppercase"
          required
          maxlength="10"
          pattern="[A-Z0-9_]+"
          @input="codeEdited = true"
        />
        <p class="text-xs text-gray-500 mt-1">{{ $t('case_types.code_hint') }}</p>
      </div>

      <!-- Categoría -->
      <div>
        <label class="form-label">{{ $t('case_types.field_category') }} *</label>
        <select v-model="form.category" class="form-select" required>
          <option value="">{{ $t('common.select') }}</option>
          <option value="permanent_residence">{{ $t('case_types.cat_permanent_residence') }}</option>
          <option value="temporary_visa">{{ $t('case_types.cat_temporary_visa') }}</option>
          <option value="asylum_refugee">{{ $t('case_types.cat_asylum_refugee') }}</option>
          <option value="citizenship">{{ $t('case_types.cat_citizenship') }}</option>
          <option value="work_permit">{{ $t('case_types.cat_work_permit') }}</option>
          <option value="other">{{ $t('case_types.cat_other') }}</option>
        </select>
      </div>

      <!-- Descripción -->
      <div>
        <label class="form-label">{{ $t('case_types.field_description') }}</label>
        <textarea v-model="form.description" class="form-textarea" rows="3" maxlength="1000" />
      </div>

      <!-- Activo -->
      <div class="flex items-center gap-3">
        <label class="form-label mb-0">{{ $t('case_types.field_is_active') }}</label>
        <input
          v-model="form.is_active"
          type="checkbox"
          class="form-checkbox"
          :disabled="!canActivate"
        />
        <span v-if="!canActivate" class="text-xs text-warning">
          {{ $t('case_types.activate_after_code_review') }}
        </span>
      </div>

      <!-- Acciones -->
      <div class="flex gap-3 justify-end pt-2">
        <router-link :to="{ name: 'admin-case-types' }" class="btn btn-outline-secondary">
          {{ $t('common.cancel') }}
        </router-link>
        <button type="submit" class="btn btn-primary" :disabled="saving">
          <span v-if="saving" class="animate-spin mr-2 w-4 h-4 border-2 border-white rounded-full border-t-transparent" />
          {{ $t('common.save') }}
        </button>
      </div>
    </form>
  </div>
</template>
```

### 3.5 Router — Rutas Vue

En `resources/js/src/router/index.ts` (dentro del grupo de rutas admin):

```typescript
{
  path: '/admin/case-types',
  name: 'admin-case-types',
  component: () => import('@/views/admin/case-types/index.vue'),
  meta: {
    requiresAuth: true,
    permission: 'case_types.view',
    layout: 'app',
  },
},
{
  path: '/admin/case-types/create',
  name: 'admin-case-types-create',
  component: () => import('@/views/admin/case-types/edit.vue'),
  meta: {
    requiresAuth: true,
    permission: 'case_types.create',
    layout: 'app',
  },
},
{
  path: '/admin/case-types/:id/edit',
  name: 'admin-case-types-edit',
  component: () => import('@/views/admin/case-types/edit.vue'),
  meta: {
    requiresAuth: true,
    permission: 'case_types.update',
    layout: 'app',
  },
},
```

### 3.6 Sidebar — Entrada de Menú

En `resources/js/src/components/layout/Sidebar.vue` — dentro del grupo Admin:

```typescript
// Computed en el bloque <script setup>
const canSeeCaseTypes = computed(() => authStore.hasAnyPermission([
  'case_types.view', 'case_types.create', 'case_types.update',
  'case_types.delete', 'case_types.clone',
]));
```

```vue
<!-- En el template, dentro del grupo de menú Admin -->
<li v-if="canSeeCaseTypes">
  <router-link :to="{ name: 'admin-case-types' }" class="group">
    <icon-briefcase class="w-5 h-5" />
    <span>{{ $t('case_types.menu_label') }}</span>
  </router-link>
</li>
```

### 3.7 Claves i18n (FLAT — agregar en `es.json`, `en.json`, `fr.json`)

```json
{
  "case_types.admin_title":          "Tipos de Expediente",
  "case_types.menu_label":           "Tipos de Expediente",
  "case_types.create_title":         "Nuevo Tipo de Expediente",
  "case_types.edit_title":           "Editar Tipo de Expediente",
  "case_types.create_btn":           "Nuevo Tipo",
  "case_types.global_badge":         "Global",
  "case_types.col_name":             "Nombre",
  "case_types.col_code":             "Siglas",
  "case_types.col_category":         "Categoría",
  "case_types.col_stages":           "Etapas",
  "case_types.col_tasks":            "Tareas",
  "case_types.col_status":           "Estado",
  "case_types.field_name":           "Nombre del tipo",
  "case_types.field_code":           "Siglas (código)",
  "case_types.code_hint":            "Máximo 10 caracteres, solo letras mayúsculas, números y guiones bajos.",
  "case_types.field_category":       "Categoría migratoria",
  "case_types.field_description":    "Descripción",
  "case_types.field_is_active":      "Activo",
  "case_types.activate_after_code_review": "Revisa y edita las siglas antes de activar",
  "case_types.clone_review_hint":    "Este tipo fue recién clonado. Edita el nombre y las siglas antes de activarlo.",
  "case_types.clone_title":          "Clonar tipo de expediente",
  "case_types.clone_confirm":        "Se duplicará completamente la configuración de <strong>{name}</strong>, incluyendo <strong>{stages} etapas</strong> y <strong>{tasks} tareas</strong> con todas sus traducciones. El clon quedará inactivo hasta que lo revises.",
  "case_types.clone_confirm_btn":    "Sí, clonar",
  "case_types.clone_success_title":  "¡Clon creado!",
  "case_types.clone_success_desc":   "Se abrirá el formulario para que edites el nombre y las siglas del nuevo tipo.",
  "case_types.clone_error":          "No se pudo completar la clonación. Por favor, inténtalo de nuevo.",
  "case_types.delete_title":                  "Eliminar tipo de expediente",
  "case_types.delete_confirm":               "¿Estás seguro de que deseas eliminar el tipo '{name}'? Esta acción también eliminará su configuración de workflow.",
  "case_types.delete_warning_active_cases":  "Atención: este tipo tiene {count} expediente(s) activo(s). Los expedientes existentes no se verán afectados, pero el tipo ya no estará disponible para nuevos casos.",
  "case_types.deleted":                      "Tipo de expediente eliminado correctamente.",
  "case_types.deleted_badge":                "eliminado",
  "case_types.cat_permanent_residence": "Residencia Permanente",
  "case_types.cat_temporary_visa":      "Visa Temporal",
  "case_types.cat_asylum_refugee":      "Asilo / Refugio",
  "case_types.cat_citizenship":         "Ciudadanía / Naturalización",
  "case_types.cat_work_permit":         "Permiso de Trabajo",
  "case_types.cat_other":               "Otro"
}
```

> **G10:** La clave `clone_confirm` usa HTML en el valor (etiquetas `<strong>`). En Vue I18n usar `v-html` con el resultado de `t()` o definir la clave como `clone_confirm_html` y renderizarla con la directiva `v-html` en el modal de SweetAlert2. **No** escapar con `{'<'}` en este contexto porque el valor va a `html:` de SweetAlert2 (no a un template de Vue). Documentar esto en el código.

---

## 4. Plan de Implementación por Fases

> `XS` ≈ 15 min · `S` ≈ 1 h · `M` ≈ 2–4 h  
> **Estimación total: ~2–3 días de desarrollo**  
> Marcar cada tarea y sub-tarea con `[x]` al completarla. Marcar la fase completa cuando todas sus tareas estén en `[x]`. Las fases son secuenciales; las tareas dentro de cada fase son paralelizables salvo dependencia explícita.

---

### - [x] Fase 1 — Base de Datos (prerequisito de todo)

- [x] **F1.1** `[S]` Crear migración `2026_05_29_000001_alter_case_types_add_softdelete_and_tenant_unique.php`
  - [x] `softDeletes()` después de `is_active`
  - [x] `dropUnique(['code'])` → `unique(['tenant_id','code','deleted_at'])`
  - [x] Ejecutar `php artisan migrate` y verificar con `DESCRIBE case_types`
  - [x] Confirmar que no hay conflictos con datos existentes

- [x] **F1.2** `[XS]` Crear migración `2026_05_29_000002_seed_case_types_permissions.php`
  - [x] 5 permisos: `case_types.{view,create,update,delete,clone}`
  - [x] Asignar a `super-admin` y `admin` (todos), `consultor` (solo `view`)
  - [x] `forgetCachedPermissions()` antes y después
  - [x] Verificar con `php artisan migrate` + `\Spatie\Permission\Models\Permission::all()`

---

### - [x] Fase 2 — Modelo, Observer y Policy (Backend)

> Depende de: Fase 1

- [x] **F2.1** `[S]` Actualizar `app/Models/CaseType.php`
  - [x] Añadir `use SoftDeletes`
  - [x] Relación `workflowStages(): HasMany` (orderBy `sort_order`)
  - [x] Helpers `activeCasesCount(): int` (informativo) y `isGlobal(): bool`
  - [x] Verificar que el global scope `tenant_or_global` existente no colisiona con SoftDeletes

- [x] **F2.1b** `[XS]` Fix obligatorio — `app/Models/ImmigrationCase.php` (H11)
  - [x] Cambiar `return $this->belongsTo(CaseType::class)` → `return $this->belongsTo(CaseType::class)->withTrashed()`
  - [x] Sin este fix, soft-delete de un tipo con casos activos rompe el dashboard y la vista de expediente

- [x] **F2.1c** `[S]` Fix obligatorio — null-safety en Resources (H11)
  - [x] `app/Http/Resources/DashboardCaseResource.php`: añadir `$this->caseType ?` guard + badge `(eliminado)` si `trashed()`
  - [x] `app/Http/Resources/CaseResource.php`: verificar que `CaseTypeResource` acepta modelo trashed sin crash
  - [x] `app/Http/Resources/ImportantDateAlertResource.php`: ya usa `?->`, confirmar sin cambios

- [x] **F2.2** `[S]` Crear `app/Observers/CaseTypeObserver.php`
  - [x] `deleting()`: cascade soft-delete stages → task_templates (si no es forceDelete)
  - [x] `restoring()`: restaurar stages y tasks en cascada
  - [x] Registrar en `AppServiceProvider::boot()` (gotcha G7): `CaseType::observe(CaseTypeObserver::class)`

- [x] **F2.3** `[S]` Crear `app/Policies/CaseTypePolicy.php`
  - [x] Métodos: `viewAny`, `view`, `create`, `update`, `delete`, `clone`
  - [x] Lógica para tipos globales: solo `super-admin` puede update/delete; todos con permiso pueden clonarlos
  - [x] Registrar en `AppServiceProvider`: `Gate::policy(CaseType::class, CaseTypePolicy::class)`

---

### - [x] Fase 3 — Servicio y HTTP (Backend)

> Depende de: Fase 2

- [x] **F3.1** `[M]` Crear `app/Services/Case/CaseTypeService.php`
  - [x] Método `clone(CaseType $source): CaseType` — algoritmo deep copy en `DB::transaction`
    - [x] Clonar `CaseType` raíz con `is_active=false` y `code` auto-generado
    - [x] Iterar `WorkflowStage[]` con `with(['translations','taskTemplates.translations'])`
    - [x] Clonar cada stage con nuevo `case_type_id` y `sort_order` preservado
    - [x] Copiar traducciones de stage vía `translationsByField()` + `setTranslations()`
    - [x] Clonar cada `TaskTemplate` con nuevo `workflow_stage_id` y `sort_order` preservado
    - [x] Copiar traducciones de cada task
  - [x] Método `getActiveCasesCount(CaseType): int` — informativo, para el aviso pre-borrado en UI
  - [x] Método `generateUniqueCode(string $base, int $tenantId): string` — bucle `$stem.$i`, fallback random
  - [x] Método `getWithWorkflowCount(): Collection`

- [x] **F3.2** `[S]` Crear Form Requests
  - [x] `app/Http/Requests/CaseType/StoreCaseTypeRequest.php` — unique `(tenant_id, code)` donde `deleted_at IS NULL`
  - [x] `app/Http/Requests/CaseType/UpdateCaseTypeRequest.php` — igual + `ignore($id)`

- [x] **F3.3** `[S]` Crear `app/Http/Resources/CaseTypeResource.php`
  - [x] Campos: `id`, `name`, `code`, `category`, `description`, `is_active`, `is_deleted`, `is_global`, `tenant_id`, `workflow_stages_count`, `tasks_count`, `active_cases_count` (condicional), timestamps
  - [x] Confirmar ausencia de campo `can_delete` — el borrado es siempre libre (D7)

- [x] **F3.4** `[M]` Crear/completar `app/Http/Controllers/Api/CaseTypeController.php`
  - [x] `index()` con eager-load de conteos
  - [x] `store()` con `tenant_id` del tenant activo
  - [x] `show()` con `workflowStages`
  - [x] `update()`
  - [x] `destroy()` — soft-delete directo sin condiciones, sin 409 (D7)
  - [x] `clone()` — llama al servicio, devuelve `CaseTypeResource` del clon
  - [x] `activeCasesCount()` — devuelve `{ count: int }` para el aviso pre-borrado

- [x] **F3.5** `[XS]` Añadir rutas en `routes/api.php`
  - [x] `apiResource('admin/case-types', CaseTypeController::class)`
  - [x] `POST admin/case-types/{caseType}/clone`
  - [x] `GET admin/case-types/{caseType}/active-cases-count`
  - [x] Verificar con `php artisan route:list | grep case-type` — 7 rutas registradas ✓

---

### - [x] Fase 4 — Frontend: Servicios y Tipos

> Depende de: Fase 3

- [x] **F4.1** `[XS]` Añadir tipos en `resources/js/src/types/case.ts` + re-export en `index.ts`
  - [x] `CaseType` extendida: `is_deleted`, `is_global`, `workflow_stages_count`, `tasks_count`, `active_cases_count?`
  - [x] `CaseTypeCategory` corregida a valores reales del backend (6 valores)
  - [x] Interfaz `CaseTypeForm` creada y exportada

- [x] **F4.2** `[S]` Crear `resources/js/src/services/caseTypeService.ts`
  - [x] `list()`
  - [x] `get(id)`
  - [x] `create(data)`
  - [x] `update(id, data)`
  - [x] `remove(id)`
  - [x] `clone(id)`
  - [x] `getActiveCasesCount(id)` — consulta bajo demanda pre-modal de borrado

---

### - [x] Fase 5 — Frontend: Vistas y Router

> Depende de: Fase 4

- [x] **F5.1** `[M]` Crear `resources/js/src/views/admin/case-types/index.vue`
  - [x] Tabla `vue3-datatable` con columnas: nombre, code (badge), categoría, etapas, tareas, estado, acciones
  - [x] Acción Editar con `v-can="'case_types.update'"`
  - [x] Acción Clonar con `v-can="'case_types.clone'"` + spinner durante operación
  - [x] Acción Eliminar con `v-can="'case_types.delete'"` — siempre habilitada
  - [x] `confirmClone()`: modal SweetAlert2 con conteos de etapas y tareas; redirige al edit del clon (D5)
  - [x] `confirmDelete()`: consulta `getActiveCasesCount()` → si `count > 0`, muestra aviso informativo; **nunca bloquear** (D7)

- [x] **F5.2** `[M]` Crear `resources/js/src/views/admin/case-types/edit.vue`
  - [x] Formulario reutilizable para create y edit
  - [x] Campo `name` (text, maxlength 120)
  - [x] Campo `code` (uppercase, regex `[A-Z0-9_]+`, maxlength 10)
  - [x] Campo `category` (select con 6 opciones)
  - [x] Campo `description` (textarea, opcional)
  - [x] Campo `is_active` (checkbox) con guardrail `canActivate` (D5)
  - [x] Banner de revisión cuando `is_active = false` en modo edit

- [x] **F5.3** `[XS]` Añadir rutas en `resources/js/src/router/index.ts`
  - [x] `/admin/case-types` → `admin-case-types` (meta.permission: `case_types.view`)
  - [x] `/admin/case-types/create` → `admin-case-types-create` (meta.permission: `case_types.create`)
  - [x] `/admin/case-types/:id/edit` → `admin-case-types-edit` (meta.permission: `case_types.update`)

- [x] **F5.4** `[XS]` Añadir entrada en `Sidebar.vue`
  - [x] Computed `canSeeCaseTypes` con `hasAnyPermission([...])` OR sobre los 5 permisos (gotcha G26)
  - [x] Entrada `<li v-if="canSeeCaseTypes">` dentro del grupo Admin con icon + label i18n

- [x] **F5.5** `[S]` Añadir claves i18n FLAT en `es.json`, `en.json`, `fr.json`
  - [x] `es.json` — ~35 claves `case_types.*`
  - [x] `en.json` — mismas claves en inglés
  - [x] `fr.json` — mismas claves en francés
  - [x] Build Vite ✓ sin errores (8.95s)

---

### - [ ] Fase 6 — Pruebas y Cierre

> Depende de: Fases 1–5

- [ ] **F6.1** `[M]` Tests Feature PHP: `tests/Feature/CaseTypeCloneTest.php`
  - [ ] Clonar type con 3 etapas × 5 tareas → verificar 3 nuevos stages con nuevos IDs y `case_type_id` correcto
  - [ ] Verificar traducciones copiadas en los 3 idiomas (es/en/fr)
  - [ ] Verificar `sort_order` preservado exacto en stages y tasks
  - [ ] Verificar que el clon tiene `is_active = false`
  - [ ] Verificar que el `code` del clon es único por tenant
  - [ ] Clonar tipo **global** → clon con `tenant_id` del tenant actual (no NULL) — H1/R2

- [ ] **F6.2** `[S]` Tests Feature PHP: `tests/Feature/CaseTypeDeleteTest.php`
  - [ ] Soft-delete de tipo **con** expedientes activos → 200 (no 409 — D7)
  - [ ] Verificar que stages y tasks del tipo quedan soft-deleted tras el borrado
  - [ ] Soft-delete de tipo **sin** expedientes → 200 + stages/tasks también soft-deleted
  - [ ] Restore → stages y tasks restaurados en cascada
  - [ ] Hard-delete directo con casos → FK `restrictOnDelete` dispara excepción de BD
  - [ ] Expediente cargado después de soft-delete del tipo → `case->caseType` devuelve el tipo trashed (no null) — F2.1b
  - [ ] `DashboardCaseResource` con tipo trashed → no lanza `NullPointerException` — F2.1c

- [ ] **F6.3** `[S]` Tests Feature PHP: `tests/Feature/CaseTypePermissionsTest.php`
  - [ ] `consultor` puede `GET /admin/case-types` pero no `POST`, `PUT`, `DELETE`, `clone`
  - [ ] `apoyo` no puede acceder a ningún endpoint de `case-types`
  - [ ] `admin` tiene acceso total a tipos de su tenant, no a tipos de otro tenant
  - [ ] `super-admin` puede editar tipos globales (`tenant_id = NULL`)

- [ ] **F6.4** `[M]` Smoke test E2E manual
  - [ ] Crear tipo con code `"AS"`, 2 etapas, 3 tareas cada una
  - [ ] Clonar → verificar redirección al edit del clon con `is_active=false`
  - [ ] Editar code a `"AS3"` → verificar que el toggle `is_active` se desbloquea
  - [ ] Activar → crear expediente → verificar `case_number` usa `"AS3"`
  - [ ] Eliminar tipo **con** expediente activo → modal muestra aviso informativo, confirmar → 200
  - [ ] Verificar type, stages y tasks soft-deleted tras el borrado
  - [ ] Abrir expediente existente → carga correctamente con badge "(eliminado)" en el tipo
  - [ ] Abrir Dashboard → sin crashes en el widget de expedientes (null-safety)

- [ ] **F6.5** `[XS]` Actualizar documentación del proyecto
  - [ ] `spec/INDEX.md`: cambiar estado Spec 70 de `PEND` → `DONE`
  - [ ] `MEMORY.md`: añadir entrada en "Implementaciones Recientes"

---

## 5. Riesgos y Mitigaciones

| # | Riesgo | Probabilidad | Impacto | Mitigación |
|---|--------|-------------|---------|------------|
| R1 | `unique(['tenant_id','code','deleted_at'])` — MySQL evalúa NULL != NULL, lo que permite múltiples filas con `(tenant_id, code, deleted_at=NULL)` si hay datos corruptos previos | Baja | Alto | La validación en `StoreCaseTypeRequest` (PHP, `whereNull('deleted_at')`) es la primera capa. La unique de BD es la red de seguridad. Testear en staging con intento de duplicado |
| R2 | Clonar tipo **global** (`tenant_id = NULL`) podría copiar `NULL` si `replicate()` no se sobreescribe | Media | Crítico | El service fuerza `$newType->tenant_id = $tenantId` **siempre** antes de `save()`. Cubierto en F6.1 |
| R3 | `generateUniqueCode` tiene ventana de carrera: check-then-insert | Baja | Bajo | La unique de BD es la red de seguridad real. Si falla, el `DB::transaction` hace rollback y el controller devuelve error manejable. El bucle solo mejora UX |
| R4 | Volumen patológico (20 etapas × 20 tareas) → ~2.500 filas → 2–4 s | Muy baja | Bajo | Aceptable (<30 s). Si supera 5 s en producción, considerar batch-insert. No migrar a Job sin medición real |
| R5 | SoftDeletes en `CaseType` + global scope `tenant_or_global` — posible conflicto entre `withTrashed()` de la relación y el scope propio del modelo | Media | Bajo | El `withTrashed()` en `ImmigrationCase::caseType()` afecta la carga del modelo relacionado, no el scope de `CaseType` al consultarlo directamente. Testear explícitamente (F6.2) |
| R6 | `HasTranslations::translationsByField()` podría no existir en el trait | Media | Alto | Verificar en `app/Models/Traits/HasTranslations.php` antes de Fase 3 (H6). Si no existe, es el único método a añadir al trait |
| R7 | Tipos globales en el mismo panel admin — frontend puede no cubrir el flujo para super-admin | Baja | Bajo | El `edit.vue` funciona igual. El bypass en `auth.ts` cubre `super-admin` (gotcha G25) |
| R8 | `html:` de SweetAlert2 en modales de clonación y borrado — XSS si `ct.name` viene del servidor sin sanitizar | Baja (dato de BD controlado) | Medio | Sanitizar `ct.name` con `DOMPurify.sanitize()` antes de interpolarlo. Los conteos (`stages`, `tasks`, `count`) son enteros — sin riesgo |
| R9 | Llamada extra a `getActiveCasesCount()` al abrir el modal de borrado — latencia adicional por request HTTP | Baja | Mínimo | El endpoint es una query COUNT simple (< 5 ms). Si la llamada falla, el modal se muestra sin aviso — degradación elegante implementada en el `try/catch` |

---

## 6. Casos de Borde Documentados

### CB-1 — Tipo global con code `"AS"` y tenant con code `"AS"` pre-migración
**Escenario:** Antes de la migración, el unique global ya impide que dos tenants tengan `"AS"`. Post-migración, un tipo global `(tenant_id=NULL, code="AS")` convive con un tipo de tenant `(tenant_id=1, code="AS")` — la unique `(tenant_id, code)` los considera distintos.  
**Regla:** Esto es correcto y esperado. Los tipos globales son del sistema, los de tenant son del tenant. No hay colisión semántica.

### CB-2 — Clonar un tipo que ya tiene casos
**Escenario:** El tipo origen tiene 500 expedientes activos y un workflow de 6 etapas × 15 tareas.  
**Comportamiento:** La clonación procede normalmente — copia solo la plantilla, no los expedientes. Los expedientes siguen apuntando al tipo original. El clon nace virgen, sin expedientes asociados.

### CB-3 — Restaurar un tipo eliminado cuando sus stages/tasks ya fueron re-creados manualmente
**Escenario:** Se soft-delete un tipo, se re-crea el mismo workflow manualmente, luego se hace restore del tipo original.  
**Comportamiento:** El restore del observer solo restaura tasks/stages cuyo `deleted_at >= caseType.deleted_at`. Si el admin re-creó stages con nuevos IDs post-borrado, no se restaurarán registros con IDs anteriores. Los stages restaurados pueden coexistir con los nuevos — el builder los mostrará todos. El admin debe revisar y eliminar los duplicados.

### CB-4 — Code auto-generado colisiona con uno existente entre `generateUniqueCode` y el INSERT
**Escenario:** Race condition donde dos usuarios clonan simultáneamente el mismo tipo.  
**Comportamiento:** La unique de BD falla el segundo INSERT. El `DB::transaction` hace rollback completo. El controller captura la excepción `QueryException` y retorna 500 o 422 con mensaje i18n. El primer clon se creó correctamente. El segundo usuario debe reintentar.  
**Mitigación adicional:** Envolver el catch en el controller para distinguir `unique constraint violation` y retornar 409 con mensaje amigable.

### CB-5 — Eliminar tipo con expedientes `archived` y `closed`
**Escenario:** Todos los expedientes del tipo están `archived` o `closed`.  
**Comportamiento:** `getActiveCasesCount()` devuelve `0` — el modal no muestra aviso y el soft-delete procede sin fricción. Los expedientes archivados mantienen su `case_type_id` con FK al registro soft-deleted (físicamente presente en BD). El `->restrictOnDelete()` de BD nunca se activa en soft-delete.

### CB-6 — Builder de workflow sobre el clon antes de activarlo
**Escenario:** El admin quiere modificar el workflow del clon antes de activarlo.  
**Comportamiento esperado:** El builder existente (`/admin/workflows/builder`) ya funciona con cualquier `case_type_id`. El clon con `is_active=false` es editable desde el builder normalmente. Agregar un enlace "Configurar workflow →" en el `edit.vue` que apunte al builder filtrado por el `id` del tipo.

---

## Archivos Impactados

### Nuevos — Backend
- `database/migrations/2026_XX_XX_000001_alter_case_types_add_softdelete_and_tenant_unique.php`
- `database/migrations/2026_XX_XX_000002_seed_case_types_permissions.php`
- `app/Observers/CaseTypeObserver.php`
- `app/Policies/CaseTypePolicy.php`
- `app/Services/Case/CaseTypeService.php`
- `app/Http/Requests/CaseType/StoreCaseTypeRequest.php`
- `app/Http/Requests/CaseType/UpdateCaseTypeRequest.php`
- `app/Http/Resources/CaseTypeResource.php`

### Nuevos — Frontend
- `resources/js/src/services/caseTypeService.ts`
- `resources/js/src/views/admin/case-types/index.vue`
- `resources/js/src/views/admin/case-types/edit.vue`

### Nuevos — Tests
- `tests/Feature/CaseTypeCloneTest.php`
- `tests/Feature/CaseTypeDeleteTest.php`
- `tests/Feature/CaseTypePermissionsTest.php`

### Modificados — Backend
- `app/Models/CaseType.php` — SoftDeletes trait + relación `workflowStages()` + helpers
- `app/Models/ImmigrationCase.php` — **fix obligatorio H11**: `caseType()` añade `->withTrashed()`
- `app/Http/Resources/DashboardCaseResource.php` — **fix obligatorio H11**: null-safety en acceso a `$this->caseType`
- `app/Http/Controllers/Api/CaseTypeController.php` — CRUD completo + `clone()` + `activeCasesCount()`
- `app/Providers/AppServiceProvider.php` — registrar `CaseTypeObserver`
- `app/Providers/AuthServiceProvider.php` — registrar `CaseTypePolicy`
- `routes/api.php` — rutas admin `case-types` + `clone` + `active-cases-count`

### Modificados — Frontend
- `resources/js/src/router/index.ts` — 3 rutas admin case-types
- `resources/js/src/components/layout/Sidebar.vue` — computed `canSeeCaseTypes` + entrada de menú
- `resources/js/src/types/index.ts` — interfaz `CaseType` y `CaseTypeForm`
- `resources/js/src/locales/es.json` — ~35 claves FLAT
- `resources/js/src/locales/en.json` — ~35 claves FLAT
- `resources/js/src/locales/fr.json` — ~35 claves FLAT

### Sin cambios requeridos
- `app/Models/WorkflowStage.php` — ya tiene SoftDeletes y trait HasTranslations
- `app/Services/Workflow/TaskTemplateService.php` — patrón de referencia, no se modifica
- `app/Services/Workflow/WorkflowInstantiator.php` — los snapshots de casos son independientes
- `database/migrations/0001_01_01_000006_create_business_tables.php` — se corrige via nueva migración
