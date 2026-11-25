---
tipo: spec
numero: 46
modulo: Workflows y Tareas
dominio: WFLOW
estado: DONE
tags: [spec, workflows, done]
relacionados:
  - "[[47_tablero_interactivo_tareas_expediente|Spec 47]]"
  - "[[48_etapas_personalizadas_por_expediente|Spec 48]]"
  - "[[49_todos_core_desde_workflow|Spec 49]]"
  - "[[50_edicion_clonacion_tareas_workflow|Spec 50]]"
---
# Especificacion Tecnica: Motor de Workflows Dinamicos (Tipos de Expediente -> Etapas -> Tareas)

## Metadata
- **Fecha:** 2026-04-26
- **Version:** 1.0
- **Arquitecto:** Claude Code (Architect Agent)
- **Estado:** Implementado (Fases 5 y 9 omitidas por error en data y diferimiento)
- **Dependencias:** [[07_epic_2.1_expedientes_core|Spec 07]] (Expedientes Core), [[09_epic_2.2_case_wizard|Spec 09]] (Case Wizard), [[14_ciclo_de_vida_checklist_dinamico|Spec 14]] (Checklist Dinamico), [[21_tareas_tab_expediente|Spec 21]] (Tareas Tab Expediente)

---

## 1. Resumen Ejecutivo

### Problema
El ciclo de vida de un expediente esta hoy hardcoded:
- `cases.stage` es un string-enum con 8 valores fijos definidos en `app/Models/ImmigrationCase.php:103-131`.
- El "checklist" del expediente vive en `case_tasks` (tabla simple: label + is_completed) y se alimenta de un array hardcoded `DEFAULT_CASE_TASKS` en `resources/js/src/types/case.ts:80`.
- No existe forma de que un tenant defina sus propias etapas y tareas obligatorias por tipo de expediente.

### Alcance
- CRUD anidado de **3 niveles** administrable por el admin de cada tenant:
  - Nivel 1: `case_types` (existente, sin cambios estructurales)
  - Nivel 2: `workflow_stages` (NUEVO) -- secuencia ordenada por tipo de expediente
  - Nivel 3: `task_templates` (NUEVO) -- plantillas de tareas obligatorias por etapa
- Internacionalizacion **trilingue ES/EN/FR** para nombres y descripciones de etapas y tipos de tarea, persistida en BD via tabla polimorfica `translations`.
- Generador automatico (`WorkflowInstantiator`) que materializa stages + tasks al crear un expediente.
- Migracion de `case_tasks` -> `tasks` (entidad existente con assigned_to, due_date, status, type).
- Snapshot de workflow por expediente para que cambios admin no afecten expedientes en curso.
- Visualizacion del Roadmap (StageProgressBar) en `cases/show.vue`.
- Seed de plantillas por defecto cubriendo los 9 tipos de proceso migratorio descritos.

### Fuera de alcance (v1)
- Versionado completo de workflows con tabla `workflow_versions` (se usa snapshot JSON en `cases.workflow_snapshot`).
- Propagacion automatica de cambios de plantilla a expedientes en curso.
- Idiomas distintos a ES/EN/FR en la UI admin (la tabla `translations` queda preparada, pero el constructor solo expone esos 3).
- Reglas de transicion condicionales (if/then) entre etapas.

---

## 2. Arquitectura

### 2.1 Diagrama de Componentes

```
ADMIN (UI Vue)
    |
    v
[Vue SPA -- /admin/workflows]
    |-- WorkflowList.vue         (lista de case_types con # de stages)
    |-- WorkflowBuilder.vue      (3 columnas + drag&drop)
    |-- StageEditorModal.vue     (CRUD stage + tabs ES/EN/FR)
    |-- TaskTemplateModal.vue    (CRUD template + tabs ES/EN/FR)
    |-- TrilingualInput.vue      (componente reutilizable)
    |
    v (REST API)
[Laravel API Layer]
    |-- Admin/WorkflowStageController
    |-- Admin/TaskTemplateController
    |
    v
[Application Services]
    |-- WorkflowStageService     (CRUD + reorder + invalidate cache)
    |-- TaskTemplateService      (CRUD + reorder + invalidate cache)
    |-- WorkflowInstantiator     (instancia stages+tasks al crear case)
    |-- TranslationResolver      (resuelve i18n con fallback a ES)
    |
    v
[Domain Models]
    |-- WorkflowStage  (BelongsToTenant, HasTranslations)
    |-- TaskTemplate   (BelongsToTenant, HasTranslations)
    |-- Translation    (morphMany polimorfico)
    |-- ImmigrationCase (+ current_stage_id, + workflow_snapshot)
    |-- Task           (+ workflow_stage_id, + task_template_id, + sort_order)
    |
    v
[Cross-cutting]
    |-- TaskObserver           (recalcula progress en case)
    |-- WorkflowCacheManager   (cache por tenant, TTL 1h)
```

### 2.2 Flujo: Admin define un workflow

```
1. Admin entra a /admin/workflows
2. Selecciona un case_type ("Refugiados y Apelaciones")
3. Anade stages (drag para reordenar): "Admision", "Inicio de Proteccion", ...
4. Para cada stage anade task_templates con nombres en ES/EN/FR
5. Marca task_templates como is_required y blocks_stage_completion
6. Cada CRUD persiste filas en workflow_stages, task_templates y translations
7. Cache de workflows del tenant se invalida automaticamente
```

### 2.3 Flujo: Consultor crea un expediente

```
1. Wizard step 1: selecciona case_type
2. Wizard step 5 (StepChecklist): GET /api/cases/workflow-preview/{case_type_id}
   - Devuelve stages + templates con nombres ya traducidos al language del case
3. Consultor desmarca templates con is_required=false que no apliquen
4. POST /api/cases con excluded_template_ids[]
5. CaseService::createCase:
   a. Crea cases (sin stage hardcoded)
   b. Llama WorkflowInstantiator::instantiate($case, $excludedIds)
      - Lee stages + templates del case_type
      - Inserta filas en `tasks` (subject, due_date, type, priority desde plantilla)
      - Set cases.current_stage_id = primer stage
      - Set cases.workflow_snapshot = JSON con secuencia congelada
   c. FolderService::createDefaultStructure (sin cambios)
6. Response incluye `tasks` agrupadas por stage
```

### 2.4 Flujo: Avance de etapa

```
1. Consultor cierra ultima task de la etapa actual (PATCH /api/tasks/{id}/status)
2. TaskObserver::updated:
   a. WorkflowInstantiator::recalculateProgress -> updateQuietly progress
   b. Si todas las tasks bloqueantes de la etapa estan en status='closed':
      - Marca el case como "stage_ready_to_advance" (computed, no persistido)
3. Consultor pulsa "Avanzar etapa" en cases/show.vue
4. PATCH /api/cases/{id}/advance-stage -> WorkflowInstantiator::advanceStage
   a. lockForUpdate sobre el case
   b. Verifica que no queden tasks bloqueantes activas
   c. Avanza current_stage_id al siguiente stage por sort_order
   d. activity()->log('Avanzo a etapa: ...')
5. Si la nueva etapa es is_terminal: status='closed' y closed_at=now()
```

---

## 3. Cambios en Base de Datos

### 3.1 Migracion: Crear tabla `translations`

**Archivo:** `database/migrations/2026_04_26_000001_create_translations_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->morphs('translatable'); // translatable_type, translatable_id (+index)
            $table->string('locale', 5);
            $table->string('field', 50);
            $table->text('value');
            $table->timestamps();

            $table->unique(
                ['translatable_type', 'translatable_id', 'locale', 'field'],
                'translations_morph_locale_field_unique'
            );
            $table->index(['tenant_id', 'translatable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
```

### 3.2 Migracion: Crear tabla `workflow_stages`

**Archivo:** `database/migrations/2026_04_26_000002_create_workflow_stages_table.php`

```php
public function up(): void
{
    Schema::create('workflow_stages', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
        $table->foreignId('case_type_id')->constrained('case_types')->cascadeOnDelete();
        $table->string('code', 50);
        $table->unsignedSmallInteger('sort_order')->default(0);
        $table->boolean('is_active')->default(true);
        $table->boolean('is_terminal')->default(false);
        $table->string('color', 20)->default('primary'); // tailwind color name
        $table->timestamps();
        $table->softDeletes();

        $table->unique(['case_type_id', 'code']);
        $table->index(['tenant_id', 'case_type_id', 'sort_order']);
    });
}
```

### 3.3 Migracion: Crear tabla `task_templates`

**Archivo:** `database/migrations/2026_04_26_000003_create_task_templates_table.php`

```php
public function up(): void
{
    Schema::create('task_templates', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
        $table->foreignId('workflow_stage_id')->constrained('workflow_stages')->cascadeOnDelete();
        $table->string('code', 80);
        $table->unsignedSmallInteger('sort_order')->default(0);
        $table->boolean('is_required')->default(true);
        $table->boolean('blocks_stage_completion')->default(true);
        $table->enum('default_type', ['translation','case_creation','accounting','filing','document','other'])
              ->default('other');
        $table->enum('default_priority', ['urgent','high','medium','low'])->default('medium');
        $table->unsignedSmallInteger('due_offset_days')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();
        $table->softDeletes();

        $table->unique(['workflow_stage_id', 'code']);
        $table->index(['tenant_id', 'workflow_stage_id', 'sort_order']);
    });
}
```

### 3.4 Migracion: Anadir columnas a `cases`

**Archivo:** `database/migrations/2026_04_26_000004_add_workflow_columns_to_cases.php`

```php
public function up(): void
{
    Schema::table('cases', function (Blueprint $table) {
        $table->foreignId('current_stage_id')->nullable()->after('case_type_id')
              ->constrained('workflow_stages')->nullOnDelete();
        $table->json('workflow_snapshot')->nullable()->after('current_stage_id');
    });

    // Backfill: poblar current_stage_id basado en cases.stage:string
    // (queda nullable; los cases viejos sin workflow definido no se tocan)
}

public function down(): void
{
    Schema::table('cases', function (Blueprint $table) {
        $table->dropForeign(['current_stage_id']);
        $table->dropColumn(['current_stage_id', 'workflow_snapshot']);
    });
}
```

### 3.5 Migracion: Anadir columnas a `tasks`

**Archivo:** `database/migrations/2026_04_26_000005_add_workflow_columns_to_tasks.php`

```php
public function up(): void
{
    Schema::table('tasks', function (Blueprint $table) {
        $table->foreignId('workflow_stage_id')->nullable()->after('case_id')
              ->constrained('workflow_stages')->nullOnDelete();
        $table->foreignId('task_template_id')->nullable()->after('workflow_stage_id')
              ->constrained('task_templates')->nullOnDelete();
        $table->unsignedSmallInteger('sort_order')->default(0)->after('status');

        $table->index(['case_id', 'workflow_stage_id', 'sort_order'], 'tasks_case_stage_order_idx');
    });
}

public function down(): void
{
    Schema::table('tasks', function (Blueprint $table) {
        $table->dropIndex('tasks_case_stage_order_idx');
        $table->dropForeign(['workflow_stage_id']);
        $table->dropForeign(['task_template_id']);
        $table->dropColumn(['workflow_stage_id', 'task_template_id', 'sort_order']);
    });
}
```

### 3.6 Migracion: Permisos de workflows

**Archivo:** `database/migrations/2026_04_26_000006_seed_workflow_permissions.php`

```php
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

public function up(): void
{
    $perms = ['workflows.view', 'workflows.create', 'workflows.update', 'workflows.delete'];

    foreach ($perms as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    foreach (['admin', 'super-admin'] as $roleName) {
        $role = Role::where('name', $roleName)->first();
        if ($role) $role->givePermissionTo($perms);
    }

    // attorney y paralegal pueden VER pero no editar
    foreach (['attorney', 'paralegal'] as $roleName) {
        $role = Role::where('name', $roleName)->first();
        if ($role) $role->givePermissionTo('workflows.view');
    }
}

public function down(): void
{
    Permission::whereIn('name', ['workflows.view','workflows.create','workflows.update','workflows.delete'])
              ->delete();
}
```

---

## 4. Backend -- Nuevos Archivos

### 4.1 Trait `HasTranslations`

**Archivo:** `app/Models/Traits/HasTranslations.php`

```php
<?php

namespace App\Models\Traits;

use App\Models\Translation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasTranslations
{
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * Resolve a translated field for the given locale.
     * Falls back to ES if the locale is missing.
     */
    public function trans(string $field, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        $loaded = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->get();

        $row = $loaded->first(fn ($t) => $t->locale === $locale && $t->field === $field);
        if ($row) return $row->value;

        if ($locale !== 'es') {
            $fallback = $loaded->first(fn ($t) => $t->locale === 'es' && $t->field === $field);
            if ($fallback) return $fallback->value;
        }
        return null;
    }

    /**
     * Set translations for a field across multiple locales.
     * @param array<string,string> $byLocale e.g. ['es' => '...', 'en' => '...', 'fr' => '...']
     */
    public function setTranslations(string $field, array $byLocale): void
    {
        foreach ($byLocale as $locale => $value) {
            if ($value === null || $value === '') {
                $this->translations()
                     ->where('locale', $locale)
                     ->where('field', $field)
                     ->delete();
                continue;
            }
            $this->translations()->updateOrCreate(
                ['locale' => $locale, 'field' => $field],
                ['value' => $value, 'tenant_id' => $this->tenant_id ?? null]
            );
        }
    }

    /**
     * Returns ['name' => ['es' => '...', 'en' => '...'], 'description' => [...]]
     */
    public function translationsByField(): array
    {
        $loaded = $this->relationLoaded('translations') ? $this->translations : $this->translations()->get();
        $out = [];
        foreach ($loaded as $t) {
            $out[$t->field][$t->locale] = $t->value;
        }
        return $out;
    }
}
```

### 4.2 Modelo `Translation`

**Archivo:** `app/Models/Translation.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Translation extends Model
{
    protected $fillable = ['tenant_id', 'translatable_type', 'translatable_id', 'locale', 'field', 'value'];

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}
```

### 4.3 Modelo `WorkflowStage`

**Archivo:** `app/Models/WorkflowStage.php`

```php
<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WorkflowStage extends Model
{
    use BelongsToTenant, HasFactory, HasTranslations, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'case_type_id', 'code', 'sort_order',
        'is_active', 'is_terminal', 'color',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'is_terminal' => 'boolean',
        'sort_order'  => 'integer',
    ];

    public function caseType(): BelongsTo
    {
        return $this->belongsTo(CaseType::class);
    }

    public function taskTemplates(): HasMany
    {
        return $this->hasMany(TaskTemplate::class)->orderBy('sort_order');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(ImmigrationCase::class, 'current_stage_id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'sort_order', 'is_active', 'is_terminal'])
            ->logOnlyDirty()
            ->useLogName('workflow_stages')
            ->dontSubmitEmptyLogs();
    }
}
```

### 4.4 Modelo `TaskTemplate`

**Archivo:** `app/Models/TaskTemplate.php`

```php
<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TaskTemplate extends Model
{
    use BelongsToTenant, HasFactory, HasTranslations, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'workflow_stage_id', 'code', 'sort_order',
        'is_required', 'blocks_stage_completion',
        'default_type', 'default_priority', 'due_offset_days', 'is_active',
    ];

    protected $casts = [
        'is_required'             => 'boolean',
        'blocks_stage_completion' => 'boolean',
        'is_active'               => 'boolean',
        'sort_order'              => 'integer',
        'due_offset_days'         => 'integer',
    ];

    public function workflowStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'sort_order', 'is_required', 'blocks_stage_completion',
                       'default_type', 'default_priority', 'due_offset_days', 'is_active'])
            ->logOnlyDirty()
            ->useLogName('task_templates')
            ->dontSubmitEmptyLogs();
    }
}
```

### 4.5 Servicio `TranslationResolver`

**Archivo:** `app/Services/Workflow/TranslationResolver.php`

```php
<?php

namespace App\Services\Workflow;

use Illuminate\Database\Eloquent\Model;

class TranslationResolver
{
    /**
     * Resolve a translated field with locale fallback to ES.
     * Returns the field value as-is if the model does not use HasTranslations.
     */
    public function resolve(Model $model, string $field, string $locale = 'es'): ?string
    {
        if (method_exists($model, 'trans')) {
            return $model->trans($field, $locale);
        }
        return $model->{$field} ?? null;
    }
}
```

### 4.6 Servicio `WorkflowInstantiator`

**Archivo:** `app/Services/Workflow/WorkflowInstantiator.php`

```php
<?php

namespace App\Services\Workflow;

use App\Exceptions\StageBlockedException;
use App\Models\ImmigrationCase;
use App\Models\Task;
use App\Models\WorkflowStage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WorkflowInstantiator
{
    public function __construct(private TranslationResolver $i18n) {}

    /**
     * Materialize stages + tasks from templates onto a freshly created case.
     * No-op if the case_type has no workflow stages defined.
     *
     * @param array<int> $excludedTemplateIds
     */
    public function instantiate(ImmigrationCase $case, array $excludedTemplateIds = []): void
    {
        $stages = WorkflowStage::with(['taskTemplates' => fn ($q) => $q->where('is_active', true)
                                                                       ->orderBy('sort_order'),
                                       'taskTemplates.translations',
                                       'translations'])
            ->where('case_type_id', $case->case_type_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($stages->isEmpty()) return;

        $locale = $case->language ?? 'es';

        $case->workflow_snapshot = $this->buildSnapshot($stages, $locale);
        $case->current_stage_id  = $stages->first()->id;
        $case->saveQuietly();

        $rows = [];
        $now  = now();
        foreach ($stages as $stage) {
            foreach ($stage->taskTemplates as $tmpl) {
                if (in_array($tmpl->id, $excludedTemplateIds, true)) continue;
                if (!$tmpl->is_required && in_array($tmpl->id, $excludedTemplateIds, true)) continue;

                $rows[] = [
                    'tenant_id'         => $case->tenant_id,
                    'case_id'           => $case->id,
                    'workflow_stage_id' => $stage->id,
                    'task_template_id'  => $tmpl->id,
                    'requester_id'      => Auth::id() ?? $case->assigned_to ?? 1,
                    'assigned_to'       => $case->assigned_to,
                    'subject'           => $this->i18n->resolve($tmpl, 'name', $locale)
                                              ?? "Template #{$tmpl->id}",
                    'description'       => $this->i18n->resolve($tmpl, 'description', $locale),
                    'type'              => $tmpl->default_type,
                    'priority'          => $tmpl->default_priority,
                    'status'            => 'new',
                    'due_date'          => $tmpl->due_offset_days
                                              ? $now->copy()->addDays($tmpl->due_offset_days)
                                              : null,
                    'sort_order'        => $tmpl->sort_order,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }
        }

        if ($rows) Task::insert($rows);
        $this->recalculateProgress($case);
    }

    public function recalculateProgress(ImmigrationCase $case): void
    {
        $base  = Task::where('case_id', $case->id)->whereNotNull('task_template_id');
        $total = (clone $base)->count();
        $done  = (clone $base)->whereIn('status', ['resolved', 'closed'])->count();

        $case->updateQuietly([
            'progress' => $total ? (int) round($done / $total * 100) : 0,
        ]);
    }

    public function advanceStage(ImmigrationCase $case): ?WorkflowStage
    {
        return DB::transaction(function () use ($case) {
            $case = ImmigrationCase::lockForUpdate()->findOrFail($case->id);
            if (!$case->current_stage_id) return null;

            $blocking = Task::where('case_id', $case->id)
                ->where('workflow_stage_id', $case->current_stage_id)
                ->whereIn('status', ['new', 'assigned', 'in_progress'])
                ->whereHas('template', fn ($q) => $q->where('blocks_stage_completion', true))
                ->exists();

            if ($blocking) {
                throw new StageBlockedException(
                    'No se puede avanzar: quedan tareas obligatorias pendientes en la etapa actual.'
                );
            }

            $current = WorkflowStage::find($case->current_stage_id);
            $next = WorkflowStage::where('case_type_id', $case->case_type_id)
                ->where('is_active', true)
                ->where('sort_order', '>', $current->sort_order)
                ->orderBy('sort_order')
                ->first();

            $updates = ['current_stage_id' => $next?->id];
            if ($next?->is_terminal) {
                $updates['status']    = ImmigrationCase::STATUS_CLOSED;
                $updates['closed_at'] = now();
            }
            $case->update($updates);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($case)
                ->withProperties([
                    'from_stage_id' => $current->id,
                    'to_stage_id'   => $next?->id,
                    'from_code'     => $current->code,
                    'to_code'       => $next?->code,
                ])
                ->log('Avanzo etapa del expediente ' . $case->case_number);

            return $next;
        });
    }

    /**
     * Build a JSON-serializable snapshot of the workflow at the moment of instantiation.
     * Used to render the roadmap if a stage is later renamed or deleted.
     */
    private function buildSnapshot($stages, string $locale): array
    {
        return [
            'locale'      => $locale,
            'captured_at' => now()->toIso8601String(),
            'stages'      => $stages->map(fn ($s) => [
                'id'          => $s->id,
                'code'        => $s->code,
                'sort_order'  => $s->sort_order,
                'is_terminal' => $s->is_terminal,
                'color'       => $s->color,
                'name'        => $this->i18n->resolve($s, 'name', $locale),
                'description' => $this->i18n->resolve($s, 'description', $locale),
            ])->values()->all(),
        ];
    }
}
```

### 4.7 Excepcion `StageBlockedException`

**Archivo:** `app/Exceptions/StageBlockedException.php`

```php
<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StageBlockedException extends HttpException
{
    public function __construct(string $message = 'Stage cannot be advanced')
    {
        parent::__construct(422, $message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code'    => 'STAGE_BLOCKED',
        ], 422);
    }
}
```

### 4.8 Servicio `WorkflowStageService`

**Archivo:** `app/Services/Workflow/WorkflowStageService.php`

```php
<?php

namespace App\Services\Workflow;

use App\Models\WorkflowStage;
use Illuminate\Support\Facades\DB;

class WorkflowStageService
{
    public function listByCaseType(int $caseTypeId)
    {
        return WorkflowStage::with(['translations', 'taskTemplates' => fn($q) => $q->orderBy('sort_order')])
            ->where('case_type_id', $caseTypeId)
            ->orderBy('sort_order')
            ->get();
    }

    public function create(array $data): WorkflowStage
    {
        return DB::transaction(function () use ($data) {
            $translations = $data['translations'] ?? [];
            unset($data['translations']);

            $data['sort_order'] ??= (WorkflowStage::where('case_type_id', $data['case_type_id'])->max('sort_order') ?? -1) + 1;

            $stage = WorkflowStage::create($data);

            foreach ($translations as $field => $byLocale) {
                $stage->setTranslations($field, $byLocale);
            }

            return $stage->load('translations', 'taskTemplates');
        });
    }

    public function update(WorkflowStage $stage, array $data): WorkflowStage
    {
        return DB::transaction(function () use ($stage, $data) {
            $translations = $data['translations'] ?? [];
            unset($data['translations']);

            $stage->update($data);

            foreach ($translations as $field => $byLocale) {
                $stage->setTranslations($field, $byLocale);
            }

            return $stage->fresh(['translations', 'taskTemplates']);
        });
    }

    public function delete(WorkflowStage $stage): void
    {
        // Soft-delete; FK restrictions on cases.current_stage_id are nullOnDelete
        $stage->delete();
    }

    /**
     * Bulk reorder by id list.
     * @param array<int> $orderedIds
     */
    public function reorder(int $caseTypeId, array $orderedIds): void
    {
        DB::transaction(function () use ($caseTypeId, $orderedIds) {
            foreach ($orderedIds as $idx => $id) {
                WorkflowStage::where('id', $id)
                    ->where('case_type_id', $caseTypeId)
                    ->update(['sort_order' => $idx]);
            }
        });
    }
}
```

### 4.9 Servicio `TaskTemplateService`

**Archivo:** `app/Services/Workflow/TaskTemplateService.php`

```php
<?php

namespace App\Services\Workflow;

use App\Models\TaskTemplate;
use Illuminate\Support\Facades\DB;

class TaskTemplateService
{
    public function create(array $data): TaskTemplate
    {
        return DB::transaction(function () use ($data) {
            $translations = $data['translations'] ?? [];
            unset($data['translations']);

            $data['sort_order'] ??= (TaskTemplate::where('workflow_stage_id', $data['workflow_stage_id'])
                                       ->max('sort_order') ?? -1) + 1;

            $template = TaskTemplate::create($data);

            foreach ($translations as $field => $byLocale) {
                $template->setTranslations($field, $byLocale);
            }

            return $template->load('translations');
        });
    }

    public function update(TaskTemplate $template, array $data): TaskTemplate
    {
        return DB::transaction(function () use ($template, $data) {
            $translations = $data['translations'] ?? [];
            unset($data['translations']);

            $template->update($data);

            foreach ($translations as $field => $byLocale) {
                $template->setTranslations($field, $byLocale);
            }

            return $template->fresh('translations');
        });
    }

    public function delete(TaskTemplate $template): void
    {
        $template->delete();
    }

    /**
     * @param array<int> $orderedIds
     */
    public function reorder(int $stageId, array $orderedIds): void
    {
        DB::transaction(function () use ($stageId, $orderedIds) {
            foreach ($orderedIds as $idx => $id) {
                TaskTemplate::where('id', $id)
                    ->where('workflow_stage_id', $stageId)
                    ->update(['sort_order' => $idx]);
            }
        });
    }
}
```

### 4.10 Controllers admin

**Archivo:** `app/Http/Controllers/Api/Admin/WorkflowStageController.php`

```php
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\StoreWorkflowStageRequest;
use App\Http\Requests\Workflow\UpdateWorkflowStageRequest;
use App\Http\Resources\WorkflowStageResource;
use App\Models\CaseType;
use App\Models\WorkflowStage;
use App\Services\Workflow\WorkflowStageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowStageController extends Controller
{
    public function __construct(private WorkflowStageService $service) {}

    public function index(CaseType $caseType): JsonResponse
    {
        $this->authorize('viewAny', WorkflowStage::class);
        $stages = $this->service->listByCaseType($caseType->id);
        return response()->json(['data' => WorkflowStageResource::collection($stages)]);
    }

    public function store(StoreWorkflowStageRequest $request, CaseType $caseType): JsonResponse
    {
        $this->authorize('create', WorkflowStage::class);
        $data = $request->validated();
        $data['case_type_id'] = $caseType->id;
        $stage = $this->service->create($data);
        return response()->json(['data' => new WorkflowStageResource($stage)], 201);
    }

    public function update(UpdateWorkflowStageRequest $request, WorkflowStage $stage): JsonResponse
    {
        $this->authorize('update', $stage);
        $stage = $this->service->update($stage, $request->validated());
        return response()->json(['data' => new WorkflowStageResource($stage)]);
    }

    public function destroy(WorkflowStage $stage): JsonResponse
    {
        $this->authorize('delete', $stage);
        $this->service->delete($stage);
        return response()->json(['message' => 'Stage deleted'], 200);
    }

    public function reorder(Request $request, CaseType $caseType): JsonResponse
    {
        $this->authorize('update', WorkflowStage::class);
        $request->validate(['ordered_ids' => 'required|array', 'ordered_ids.*' => 'integer|exists:workflow_stages,id']);
        $this->service->reorder($caseType->id, $request->input('ordered_ids'));
        return response()->json(['message' => 'Stages reordered']);
    }
}
```

**Archivo:** `app/Http/Controllers/Api/Admin/TaskTemplateController.php`

```php
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\StoreTaskTemplateRequest;
use App\Http\Requests\Workflow\UpdateTaskTemplateRequest;
use App\Http\Resources\TaskTemplateResource;
use App\Models\TaskTemplate;
use App\Models\WorkflowStage;
use App\Services\Workflow\TaskTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskTemplateController extends Controller
{
    public function __construct(private TaskTemplateService $service) {}

    public function index(WorkflowStage $stage): JsonResponse
    {
        $this->authorize('viewAny', TaskTemplate::class);
        $templates = $stage->taskTemplates()->with('translations')->orderBy('sort_order')->get();
        return response()->json(['data' => TaskTemplateResource::collection($templates)]);
    }

    public function store(StoreTaskTemplateRequest $request, WorkflowStage $stage): JsonResponse
    {
        $this->authorize('create', TaskTemplate::class);
        $data = $request->validated();
        $data['workflow_stage_id'] = $stage->id;
        $template = $this->service->create($data);
        return response()->json(['data' => new TaskTemplateResource($template)], 201);
    }

    public function update(UpdateTaskTemplateRequest $request, TaskTemplate $template): JsonResponse
    {
        $this->authorize('update', $template);
        $template = $this->service->update($template, $request->validated());
        return response()->json(['data' => new TaskTemplateResource($template)]);
    }

    public function destroy(TaskTemplate $template): JsonResponse
    {
        $this->authorize('delete', $template);
        $this->service->delete($template);
        return response()->json(['message' => 'Template deleted']);
    }

    public function reorder(Request $request, WorkflowStage $stage): JsonResponse
    {
        $this->authorize('update', TaskTemplate::class);
        $request->validate(['ordered_ids' => 'required|array', 'ordered_ids.*' => 'integer|exists:task_templates,id']);
        $this->service->reorder($stage->id, $request->input('ordered_ids'));
        return response()->json(['message' => 'Templates reordered']);
    }
}
```

### 4.11 Form Requests

**Archivo:** `app/Http/Requests/Workflow/StoreWorkflowStageRequest.php`

```php
<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowStageRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code'                  => ['required', 'string', 'max:50'],
            'sort_order'            => ['nullable', 'integer'],
            'is_active'             => ['boolean'],
            'is_terminal'           => ['boolean'],
            'color'                 => ['nullable', 'string', 'max:20'],
            'translations'          => ['required', 'array'],
            'translations.name'    => ['required', 'array'],
            'translations.name.es' => ['required', 'string', 'max:255'],
            'translations.name.en' => ['nullable', 'string', 'max:255'],
            'translations.name.fr' => ['nullable', 'string', 'max:255'],
            'translations.description'    => ['nullable', 'array'],
            'translations.description.es' => ['nullable', 'string'],
            'translations.description.en' => ['nullable', 'string'],
            'translations.description.fr' => ['nullable', 'string'],
        ];
    }
}
```

**Archivo:** `app/Http/Requests/Workflow/UpdateWorkflowStageRequest.php` (mismas reglas, todas opcionales excepto `translations.name.es` cuando se envia translations).

**Archivo:** `app/Http/Requests/Workflow/StoreTaskTemplateRequest.php`

```php
<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskTemplateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code'                    => ['required', 'string', 'max:80'],
            'sort_order'              => ['nullable', 'integer'],
            'is_required'             => ['boolean'],
            'blocks_stage_completion' => ['boolean'],
            'default_type'            => ['nullable', 'in:translation,case_creation,accounting,filing,document,other'],
            'default_priority'        => ['nullable', 'in:urgent,high,medium,low'],
            'due_offset_days'         => ['nullable', 'integer', 'min:0', 'max:3650'],
            'is_active'               => ['boolean'],
            'translations.name'       => ['required', 'array'],
            'translations.name.es'    => ['required', 'string', 'max:255'],
            'translations.name.en'    => ['nullable', 'string', 'max:255'],
            'translations.name.fr'    => ['nullable', 'string', 'max:255'],
            'translations.description.*' => ['nullable', 'string'],
        ];
    }
}
```

**Archivo:** `app/Http/Requests/Workflow/UpdateTaskTemplateRequest.php` (mismas reglas, opcionales).

### 4.12 Resources

**Archivo:** `app/Http/Resources/WorkflowStageResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowStageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'case_type_id' => $this->case_type_id,
            'code'         => $this->code,
            'sort_order'   => $this->sort_order,
            'is_active'    => $this->is_active,
            'is_terminal'  => $this->is_terminal,
            'color'        => $this->color,
            'translations' => $this->translationsByField(),
            'task_templates_count' => $this->whenCounted('taskTemplates'),
            'task_templates'       => TaskTemplateResource::collection($this->whenLoaded('taskTemplates')),
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
```

**Archivo:** `app/Http/Resources/TaskTemplateResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TaskTemplateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                      => $this->id,
            'workflow_stage_id'       => $this->workflow_stage_id,
            'code'                    => $this->code,
            'sort_order'              => $this->sort_order,
            'is_required'             => $this->is_required,
            'blocks_stage_completion' => $this->blocks_stage_completion,
            'default_type'            => $this->default_type,
            'default_priority'        => $this->default_priority,
            'due_offset_days'         => $this->due_offset_days,
            'is_active'               => $this->is_active,
            'translations'            => $this->translationsByField(),
            'created_at'              => $this->created_at,
            'updated_at'              => $this->updated_at,
        ];
    }
}
```

### 4.13 Policies

**Archivo:** `app/Policies/WorkflowStagePolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowStage;

class WorkflowStagePolicy
{
    public function viewAny(User $user): bool { return $user->can('workflows.view'); }
    public function view(User $user, WorkflowStage $stage): bool
    {
        return $user->can('workflows.view') && $stage->tenant_id === $user->tenant_id;
    }
    public function create(User $user): bool { return $user->can('workflows.create'); }
    public function update(User $user, WorkflowStage $stage): bool
    {
        return $user->can('workflows.update') && $stage->tenant_id === $user->tenant_id;
    }
    public function delete(User $user, WorkflowStage $stage): bool
    {
        return $user->can('workflows.delete') && $stage->tenant_id === $user->tenant_id;
    }
}
```

**Archivo:** `app/Policies/TaskTemplatePolicy.php` (analogo).

### 4.14 Observer `TaskWorkflowObserver`

**Archivo:** `app/Observers/TaskWorkflowObserver.php`

```php
<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\Workflow\WorkflowInstantiator;

class TaskWorkflowObserver
{
    public function __construct(private WorkflowInstantiator $instantiator) {}

    public function updated(Task $task): void
    {
        if ($task->wasChanged('status') && $task->case_id && $task->task_template_id) {
            $this->instantiator->recalculateProgress($task->immigrationCase);
        }
    }

    public function deleted(Task $task): void
    {
        if ($task->case_id && $task->task_template_id) {
            $this->instantiator->recalculateProgress($task->immigrationCase);
        }
    }
}
```

### 4.15 Endpoint nuevo para preview de workflow desde wizard

**Archivo:** `app/Http/Controllers/Api/CaseController.php` (metodo nuevo `workflowPreview`)

```php
public function workflowPreview(int $caseTypeId, Request $request, WorkflowStageService $service)
{
    $stages = $service->listByCaseType($caseTypeId);
    return response()->json(['data' => WorkflowStageResource::collection($stages->load('taskTemplates.translations'))]);
}
```

### 4.16 Endpoint nuevo `advanceStage`

```php
// CaseController
public function advanceStage(ImmigrationCase $case, WorkflowInstantiator $instantiator)
{
    $this->authorize('update', $case);
    $next = $instantiator->advanceStage($case);
    return response()->json([
        'message'  => 'Etapa avanzada',
        'next'     => $next ? new WorkflowStageResource($next->load('translations')) : null,
        'case'     => new CaseResource($case->fresh(['currentStage.translations', 'tasks.template'])),
    ]);
}
```

---

## 5. Backend -- Archivos a Modificar

### 5.1 `ImmigrationCase` model

```php
// app/Models/ImmigrationCase.php

protected $fillable = [
    // ... existentes
    'current_stage_id',
    'workflow_snapshot',
];

protected $casts = [
    // ... existentes
    'workflow_snapshot' => 'array',
];

public function currentStage(): BelongsTo
{
    return $this->belongsTo(WorkflowStage::class, 'current_stage_id');
}

// Reemplazar relacion tasks() actual (que apuntaba a CaseTask):
public function tasks(): HasMany
{
    return $this->hasMany(Task::class, 'case_id')->orderBy('sort_order');
}

// Mantener legacy_checklist() solo durante Fase 1 de migracion:
public function legacyChecklist(): HasMany
{
    return $this->hasMany(CaseTask::class, 'case_id')->orderBy('sort_order');
}

// Actualizar getActivitylogOptions: cambiar 'stage' por 'current_stage_id'
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logOnly([
            'status', 'priority', 'progress', 'assigned_to',
            'description', 'current_stage_id', 'ircc_status',
            'final_result', 'fees', 'service_type',
        ])
        ->logOnlyDirty()
        ->useLogName('cases')
        ->dontSubmitEmptyLogs();
}
```

### 5.2 `Task` model

**Archivo:** `app/Models/Task.php` (verificar existe; si no, crearlo). Anadir:

```php
protected $fillable = [
    // ... existentes
    'workflow_stage_id', 'task_template_id', 'sort_order',
];

public function template(): BelongsTo
{
    return $this->belongsTo(TaskTemplate::class, 'task_template_id');
}

public function workflowStage(): BelongsTo
{
    return $this->belongsTo(WorkflowStage::class);
}
```

### 5.3 `CaseService::createCase`

```php
// app/Services/Case/CaseService.php

public function __construct(
    private CaseRepositoryInterface $caseRepository,
    private CaseTypeRepositoryInterface $caseTypeRepository,
    private CaseTaskService $caseTaskService, // mantener Fase 1; eliminar Fase 2
    private FolderService $folderService,
    private CaseFolderSyncService $caseFolderSyncService,
    private \App\Services\Workflow\WorkflowInstantiator $workflowInstantiator,
) {}

public function createCase(array $data): ImmigrationCase
{
    $case = DB::transaction(function () use ($data) {
        $companionIds        = $data['companion_ids'] ?? [];
        $excludedTemplateIds = $data['excluded_template_ids'] ?? [];
        $importantDatesData  = array_key_exists('important_dates', $data) && !empty($data['important_dates'])
            ? $data['important_dates']
            : $this->getDefaultImportantDates();

        unset($data['companion_ids'], $data['excluded_template_ids'],
              $data['important_dates'], $data['case_tasks']); // case_tasks deprecated

        $caseType = $this->caseTypeRepository->findById($data['case_type_id']);
        $client   = Client::findOrFail($data['client_id']);

        $data['case_number'] = $this->generateCaseNumber($caseType, $client);
        $data['status']   ??= ImmigrationCase::STATUS_ACTIVE;
        $data['priority'] ??= ImmigrationCase::PRIORITY_MEDIUM;
        $data['progress'] ??= 0;
        $data['language'] ??= 'es';

        $case = $this->caseRepository->create($data);

        if (!empty($companionIds)) $case->companions()->sync($companionIds);

        $datesWithCaseId = array_map(fn ($d) => array_merge($d, ['case_id' => $case->id]), $importantDatesData);
        $case->importantDates()->createMany($datesWithCaseId);

        // Reemplaza CaseTaskService::syncTasks
        $this->workflowInstantiator->instantiate($case, $excludedTemplateIds);

        activity()
            ->causedBy(Auth::user())
            ->performedOn($case)
            ->withProperties([
                'case_number'      => $case->case_number,
                'client_id'        => $case->client_id,
                'case_type'        => $caseType->name,
                'companions_count' => count($companionIds),
            ])
            ->log('Created case: ' . $case->case_number);

        return $case->load(['client', 'caseType', 'currentStage.translations',
                            'assignedTo.profile', 'companions', 'importantDates', 'tasks.template']);
    });

    // ... folder creation (sin cambios)
    return $case;
}
```

### 5.4 `routes/api.php`

```php
use App\Http\Controllers\Api\Admin\WorkflowStageController;
use App\Http\Controllers\Api\Admin\TaskTemplateController;

// Dentro del grupo auth:sanctum + tenant:
Route::get('/cases/workflow-preview/{caseTypeId}', [CaseController::class, 'workflowPreview']);
Route::patch('/cases/{case}/advance-stage', [CaseController::class, 'advanceStage']);

// Grupo admin de workflows
Route::prefix('admin/workflow')
    ->middleware(['auth:sanctum', 'tenant', 'role:admin|super-admin'])
    ->group(function () {
        Route::get('/case-types/{caseType}/stages', [WorkflowStageController::class, 'index']);
        Route::post('/case-types/{caseType}/stages', [WorkflowStageController::class, 'store']);
        Route::patch('/case-types/{caseType}/stages/reorder', [WorkflowStageController::class, 'reorder']);
        Route::put('/stages/{stage}', [WorkflowStageController::class, 'update']);
        Route::delete('/stages/{stage}', [WorkflowStageController::class, 'destroy']);

        Route::get('/stages/{stage}/templates', [TaskTemplateController::class, 'index']);
        Route::post('/stages/{stage}/templates', [TaskTemplateController::class, 'store']);
        Route::patch('/stages/{stage}/templates/reorder', [TaskTemplateController::class, 'reorder']);
        Route::put('/templates/{template}', [TaskTemplateController::class, 'update']);
        Route::delete('/templates/{template}', [TaskTemplateController::class, 'destroy']);
    });
```

### 5.5 `AppServiceProvider::boot`

```php
// Registrar el observer (gotcha G7)
Task::observe(\App\Observers\TaskWorkflowObserver::class);

// Registrar policies
Gate::policy(\App\Models\WorkflowStage::class, \App\Policies\WorkflowStagePolicy::class);
Gate::policy(\App\Models\TaskTemplate::class, \App\Policies\TaskTemplatePolicy::class);
```

### 5.6 `CaseResource`

Anadir al output:

```php
'current_stage'      => new WorkflowStageResource($this->whenLoaded('currentStage')),
'workflow_snapshot'  => $this->workflow_snapshot,
'tasks'              => TaskResource::collection($this->whenLoaded('tasks')),
```

---

## 6. Frontend -- Nuevos Archivos

### 6.1 Tipos TypeScript

**Archivo:** `resources/js/src/types/workflow.ts`

```typescript
export type Locale = 'es' | 'en' | 'fr';
export type TranslationsByField = Record<string, Partial<Record<Locale, string>>>;

export interface WorkflowStage {
  id: number;
  case_type_id: number;
  code: string;
  sort_order: number;
  is_active: boolean;
  is_terminal: boolean;
  color: string;
  translations: TranslationsByField;
  task_templates_count?: number;
  task_templates?: TaskTemplate[];
  created_at?: string;
  updated_at?: string;
}

export interface TaskTemplate {
  id: number;
  workflow_stage_id: number;
  code: string;
  sort_order: number;
  is_required: boolean;
  blocks_stage_completion: boolean;
  default_type: 'translation'|'case_creation'|'accounting'|'filing'|'document'|'other';
  default_priority: 'urgent'|'high'|'medium'|'low';
  due_offset_days: number | null;
  is_active: boolean;
  translations: TranslationsByField;
}

export interface WorkflowSnapshot {
  locale: Locale;
  captured_at: string;
  stages: Array<{
    id: number; code: string; sort_order: number;
    is_terminal: boolean; color: string;
    name: string | null; description: string | null;
  }>;
}

export const SUPPORTED_LOCALES: Locale[] = ['es', 'en', 'fr'];
```

### 6.2 Servicio API

**Archivo:** `resources/js/src/services/workflowService.ts`

```typescript
import api from './api';
import type { WorkflowStage, TaskTemplate, TranslationsByField } from '@/types/workflow';

export interface StagePayload {
  code: string;
  sort_order?: number;
  is_active?: boolean;
  is_terminal?: boolean;
  color?: string;
  translations: TranslationsByField;
}

export interface TemplatePayload {
  code: string;
  sort_order?: number;
  is_required?: boolean;
  blocks_stage_completion?: boolean;
  default_type?: TaskTemplate['default_type'];
  default_priority?: TaskTemplate['default_priority'];
  due_offset_days?: number | null;
  is_active?: boolean;
  translations: TranslationsByField;
}

export const workflowService = {
  // Stages
  listStages: (caseTypeId: number) =>
    api.get<{ data: WorkflowStage[] }>(`/admin/workflow/case-types/${caseTypeId}/stages`),
  createStage: (caseTypeId: number, payload: StagePayload) =>
    api.post<{ data: WorkflowStage }>(`/admin/workflow/case-types/${caseTypeId}/stages`, payload),
  updateStage: (id: number, payload: Partial<StagePayload>) =>
    api.put<{ data: WorkflowStage }>(`/admin/workflow/stages/${id}`, payload),
  deleteStage: (id: number) =>
    api.delete(`/admin/workflow/stages/${id}`),
  reorderStages: (caseTypeId: number, orderedIds: number[]) =>
    api.patch(`/admin/workflow/case-types/${caseTypeId}/stages/reorder`, { ordered_ids: orderedIds }),

  // Templates
  listTemplates: (stageId: number) =>
    api.get<{ data: TaskTemplate[] }>(`/admin/workflow/stages/${stageId}/templates`),
  createTemplate: (stageId: number, payload: TemplatePayload) =>
    api.post<{ data: TaskTemplate }>(`/admin/workflow/stages/${stageId}/templates`, payload),
  updateTemplate: (id: number, payload: Partial<TemplatePayload>) =>
    api.put<{ data: TaskTemplate }>(`/admin/workflow/templates/${id}`, payload),
  deleteTemplate: (id: number) =>
    api.delete(`/admin/workflow/templates/${id}`),
  reorderTemplates: (stageId: number, orderedIds: number[]) =>
    api.patch(`/admin/workflow/stages/${stageId}/templates/reorder`, { ordered_ids: orderedIds }),

  // Wizard
  workflowPreview: (caseTypeId: number) =>
    api.get<{ data: WorkflowStage[] }>(`/cases/workflow-preview/${caseTypeId}`),

  // Case operations
  advanceStage: (caseId: number) =>
    api.patch(`/cases/${caseId}/advance-stage`),
};
```

### 6.3 Store Pinia

**Archivo:** `resources/js/src/stores/useWorkflowStore.ts`

```typescript
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { WorkflowStage, TaskTemplate } from '@/types/workflow';
import { workflowService, type StagePayload, type TemplatePayload } from '@/services/workflowService';

export const useWorkflowStore = defineStore('workflow', () => {
    const stages = ref<WorkflowStage[]>([]);
    const isLoading = ref(false);
    const currentCaseTypeId = ref<number | null>(null);

    async function loadStages(caseTypeId: number) {
        isLoading.value = true;
        try {
            currentCaseTypeId.value = caseTypeId;
            const { data } = await workflowService.listStages(caseTypeId);
            stages.value = data.data;
        } finally {
            isLoading.value = false;
        }
    }

    async function createStage(payload: StagePayload) {
        if (!currentCaseTypeId.value) return;
        const { data } = await workflowService.createStage(currentCaseTypeId.value, payload);
        stages.value.push(data.data);
    }

    async function updateStage(id: number, payload: Partial<StagePayload>) {
        const { data } = await workflowService.updateStage(id, payload);
        const idx = stages.value.findIndex(s => s.id === id);
        if (idx >= 0) stages.value[idx] = { ...stages.value[idx], ...data.data };
    }

    async function deleteStage(id: number) {
        await workflowService.deleteStage(id);
        stages.value = stages.value.filter(s => s.id !== id);
    }

    async function reorderStages(orderedIds: number[]) {
        if (!currentCaseTypeId.value) return;
        await workflowService.reorderStages(currentCaseTypeId.value, orderedIds);
        stages.value.sort((a, b) => orderedIds.indexOf(a.id) - orderedIds.indexOf(b.id));
        stages.value.forEach((s, i) => (s.sort_order = i));
    }

    async function createTemplate(stageId: number, payload: TemplatePayload) {
        const { data } = await workflowService.createTemplate(stageId, payload);
        const stage = stages.value.find(s => s.id === stageId);
        if (stage) stage.task_templates = [...(stage.task_templates ?? []), data.data];
    }

    async function updateTemplate(stageId: number, id: number, payload: Partial<TemplatePayload>) {
        const { data } = await workflowService.updateTemplate(id, payload);
        const stage = stages.value.find(s => s.id === stageId);
        if (stage?.task_templates) {
            const idx = stage.task_templates.findIndex(t => t.id === id);
            if (idx >= 0) stage.task_templates[idx] = data.data;
        }
    }

    async function deleteTemplate(stageId: number, id: number) {
        await workflowService.deleteTemplate(id);
        const stage = stages.value.find(s => s.id === stageId);
        if (stage?.task_templates) stage.task_templates = stage.task_templates.filter(t => t.id !== id);
    }

    async function reorderTemplates(stageId: number, orderedIds: number[]) {
        await workflowService.reorderTemplates(stageId, orderedIds);
        const stage = stages.value.find(s => s.id === stageId);
        if (stage?.task_templates) {
            stage.task_templates.sort((a, b) => orderedIds.indexOf(a.id) - orderedIds.indexOf(b.id));
            stage.task_templates.forEach((t, i) => (t.sort_order = i));
        }
    }

    return {
        stages, isLoading, currentCaseTypeId,
        loadStages, createStage, updateStage, deleteStage, reorderStages,
        createTemplate, updateTemplate, deleteTemplate, reorderTemplates,
    };
});
```

### 6.4 Componente reutilizable `TrilingualInput.vue`

**Archivo:** `resources/js/src/components/shared/TrilingualInput.vue`

```vue
<template>
    <div>
        <label class="block text-sm font-semibold mb-2">{{ label }}</label>
        <div class="border border-[#e0e6ed] dark:border-[#1b2e4b] rounded">
            <div class="flex border-b border-[#e0e6ed] dark:border-[#1b2e4b] bg-gray-50 dark:bg-gray-900">
                <button
                    v-for="loc in locales"
                    :key="loc"
                    type="button"
                    class="px-4 py-2 text-xs font-semibold uppercase transition"
                    :class="activeLocale === loc
                        ? 'bg-white dark:bg-gray-800 text-primary border-b-2 border-primary'
                        : 'text-gray-500 hover:text-primary'"
                    @click="activeLocale = loc"
                >
                    {{ loc }}
                    <span v-if="loc === requiredLocale" class="text-danger ml-0.5">*</span>
                    <span
                        v-else-if="!modelValue?.[loc]"
                        class="ml-1 text-warning"
                        :title="$t('workflow.translation_missing')"
                    >!</span>
                </button>
            </div>
            <div class="p-2">
                <textarea
                    v-if="textarea"
                    :value="modelValue?.[activeLocale] ?? ''"
                    @input="onInput($event)"
                    :rows="rows"
                    :maxlength="maxlength"
                    class="form-textarea w-full text-sm"
                    :placeholder="placeholder"
                />
                <input
                    v-else
                    type="text"
                    :value="modelValue?.[activeLocale] ?? ''"
                    @input="onInput($event)"
                    :maxlength="maxlength"
                    class="form-input w-full text-sm"
                    :placeholder="placeholder"
                />
            </div>
        </div>
        <p v-if="hint" class="text-xs text-gray-400 mt-1">{{ hint }}</p>
    </div>
</template>

<script lang="ts" setup>
import { ref } from 'vue';
import type { Locale, TranslationsByField } from '@/types/workflow';

const props = defineProps<{
    modelValue: Partial<Record<Locale, string>> | undefined;
    label: string;
    locales?: Locale[];
    requiredLocale?: Locale;
    textarea?: boolean;
    rows?: number;
    maxlength?: number;
    placeholder?: string;
    hint?: string;
}>();

const emit = defineEmits<{ (e: 'update:modelValue', v: Partial<Record<Locale, string>>): void }>();

const locales = props.locales ?? (['es', 'en', 'fr'] as Locale[]);
const activeLocale = ref<Locale>(props.requiredLocale ?? locales[0]);

function onInput(e: Event) {
    const value = (e.target as HTMLInputElement | HTMLTextAreaElement).value;
    emit('update:modelValue', { ...(props.modelValue ?? {}), [activeLocale.value]: value });
}
</script>
```

### 6.5 Vista Admin: Lista de workflows

**Archivo:** `resources/js/src/views/admin/workflows/index.vue`

(Lista de case_types con conteo de stages y boton "Configurar")

### 6.6 Vista Admin: Constructor

**Archivo:** `resources/js/src/views/admin/workflows/builder.vue`

(3 columnas con drag & drop usando vue-draggable-plus, CRUD inline de stages y templates)

### 6.7 Modales

**Archivo:** `resources/js/src/components/admin/StageEditorModal.vue`

**Archivo:** `resources/js/src/components/admin/TaskTemplateEditorModal.vue`

### 6.8 `StageProgressBar.vue`

**Archivo:** `resources/js/src/components/cases/StageProgressBar.vue`

```vue
<template>
    <div class="flex items-center gap-1 overflow-x-auto py-2">
        <template v-for="(stage, idx) in stages" :key="stage.id">
            <div class="flex items-center gap-2 shrink-0">
                <div
                    class="flex items-center justify-center w-8 h-8 rounded-full text-xs font-semibold transition"
                    :class="getStageClasses(stage)"
                >
                    {{ idx + 1 }}
                </div>
                <span class="text-xs font-medium" :class="stage.id === currentStageId ? 'text-primary' : 'text-gray-500'">
                    {{ stage.name }}
                </span>
            </div>
            <div
                v-if="idx < stages.length - 1"
                class="h-0.5 flex-1 min-w-[24px]"
                :class="isCompleted(stage) ? 'bg-success' : 'bg-gray-200 dark:bg-gray-700'"
            />
        </template>
    </div>
</template>

<script lang="ts" setup>
import type { WorkflowSnapshot } from '@/types/workflow';
import { computed } from 'vue';

const props = defineProps<{
    snapshot: WorkflowSnapshot | null;
    currentStageId: number | null;
}>();

const stages = computed(() => props.snapshot?.stages ?? []);
const currentIndex = computed(() => stages.value.findIndex(s => s.id === props.currentStageId));

function isCompleted(stage: { id: number }) {
    if (currentIndex.value < 0) return false;
    return stages.value.findIndex(s => s.id === stage.id) < currentIndex.value;
}

function getStageClasses(stage: { id: number; is_terminal: boolean }) {
    if (isCompleted(stage)) return 'bg-success text-white';
    if (stage.id === props.currentStageId) return 'bg-primary text-white ring-4 ring-primary/20';
    return 'bg-gray-200 dark:bg-gray-700 text-gray-500';
}
</script>
```

---

## 7. Frontend -- Archivos a Modificar

### 7.1 `router/index.ts` -- Rutas admin

```typescript
{
    path: '/admin/workflows',
    component: () => import('@/views/admin/workflows/index.vue'),
    meta: { requiresAuth: true, permission: 'workflows.view', layout: 'app' },
},
{
    path: '/admin/workflows/:caseTypeId',
    component: () => import('@/views/admin/workflows/builder.vue'),
    meta: { requiresAuth: true, permission: 'workflows.view', layout: 'app' },
},
```

### 7.2 `Sidebar.vue` -- Entrada admin

Agregar entrada (con `v-can="'workflows.view'"`) bajo la seccion de administracion.

### 7.3 `wizard/steps/StepChecklist.vue` -- Refactor completo

Reemplazar el uso de `DEFAULT_CASE_TASKS` (hardcoded) por carga remota:

```vue
<script lang="ts" setup>
import { ref, computed, watch, inject, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { workflowService } from '@/services/workflowService';
import type { WorkflowStage } from '@/types/workflow';

const wizard = inject<ReturnType<typeof import('@/composables/useCaseWizard').useCaseWizard>>('wizard')!;
const { t } = useI18n();

const stages = ref<WorkflowStage[]>([]);
const isLoading = ref(false);
const hasWorkflow = computed(() => stages.value.length > 0);

const caseTypeId = computed(() => wizard.state.caseType?.id);
const caseLanguage = computed(() => wizard.state.caseDetails.language || 'es');

async function loadWorkflow(typeId: number) {
    isLoading.value = true;
    try {
        const { data } = await workflowService.workflowPreview(typeId);
        stages.value = data.data;
        // Por defecto: todas las plantillas seleccionadas (no excluidas)
        wizard.state.excludedTemplateIds = [];
    } finally {
        isLoading.value = false;
    }
}

watch(caseTypeId, (id) => { if (id) loadWorkflow(id); }, { immediate: true });

function isExcluded(templateId: number) {
    return wizard.state.excludedTemplateIds?.includes(templateId);
}

function toggleTemplate(templateId: number) {
    const list = wizard.state.excludedTemplateIds ?? [];
    wizard.state.excludedTemplateIds = isExcluded(templateId)
        ? list.filter(id => id !== templateId)
        : [...list, templateId];
}

function getTranslated(translations: Record<string, Partial<Record<string, string>>>, field: string) {
    const loc = caseLanguage.value;
    return translations?.[field]?.[loc] ?? translations?.[field]?.es ?? '';
}
</script>
```

Template muestra stages agrupados con templates dentro. Si `!hasWorkflow`, mostrar mensaje "Este tipo de caso no tiene workflow definido. Se creara sin checklist." con link a `/admin/workflows/{caseTypeId}` (si tiene permiso).

### 7.4 `cases/show.vue` -- Roadmap visual

- Reemplazar el bloque actual de `cases.stage` (`show.vue:85-90`) por:
  ```vue
  <StageProgressBar
      v-if="currentCase.workflow_snapshot"
      :snapshot="currentCase.workflow_snapshot"
      :current-stage-id="currentCase.current_stage_id"
  />
  ```
- Tab de tasks: agrupar por `workflow_stage_id`, mostrando progreso por etapa.
- Anadir boton "Avanzar etapa" con confirmacion via SweetAlert2 que llama a `workflowService.advanceStage`.
- Manejar respuesta 422 STAGE_BLOCKED con mensaje al usuario.

### 7.5 `useCaseWizard.ts` -- estado adicional

Agregar al state:
```typescript
excludedTemplateIds: [] as number[],
```

Y en el payload de `submit()` enviar `excluded_template_ids` en lugar de `case_tasks`.

### 7.6 `types/case.ts`

- Eliminar `DEFAULT_CASE_TASKS` (Fase 2).
- Anadir `current_stage_id: number | null` y `workflow_snapshot: WorkflowSnapshot | null` a `ImmigrationCase`.

### 7.7 Regla técnica: VueDraggable y persistencia de orden (bug fix 2026-05-31)

**Causa raíz del bug de persistencia de orden de task templates:**

`vue-draggable-plus` usa `nextTick` (interno: `kt`) dentro de su handler `onEnd`. Esto significa
que `@end` se dispara en un tick asíncrono. Si se usa el patrón separado
`:model-value` + `@update:model-value` + `@end`, hay un intervalo donde la mutación
reactiva aún no ha propagado al computed cuando `@end` lee el estado.

**Patrón ROTO (no usar):**
```html
<VueDraggable
    :model-value="selectedStage.task_templates ?? []"
    @update:model-value="(v) => updateLocalTemplates(v)"
    @end="onTemplatesReorder"
/>
```
```typescript
// BUG: @end puede leer el estado ANTES de que @update:model-value propague
async function onTemplatesReorder() {
    const ids = selectedStage.value.task_templates.map(t => t.id); // puede ser el orden antiguo
    await store.reorderTemplates(selectedStage.value.id, ids);
}
```

**Patrón CORRECTO (usar para listas anidadas):**

Usar `v-model` con un computed escribible + `@update:model-value` para disparar la
persistencia con el valor nuevo pasado directamente como argumento — sin leer estado reactivo:

```typescript
const currentTemplates = computed({
    get: () => selectedStage.value?.task_templates ?? [],
    set: (v: TaskTemplate[]) => {
        if (selectedStage.value) selectedStage.value.task_templates = v;
    },
});
```
```html
<VueDraggable
    v-model="currentTemplates"
    @update:model-value="onTemplatesDragged"
/>
```
```typescript
async function onTemplatesDragged(newTemplates: TaskTemplate[]) {
    if (!selectedStage.value) return;
    const stageId = selectedStage.value.id;
    const ids = newTemplates.map(t => t.id); // el nuevo orden viene DIRECTAMENTE del evento
    await store.reorderTemplates(stageId, ids);
}
```

**Regla general:** Para listas de primer nivel en el store, `v-model="store.list"` + `@end` funciona
correctamente (como las etapas). Para listas **anidadas** dentro de objetos del store, usar
`v-model="writableComputed"` + `@update:model-value="handler(newValue)"` donde el handler
recibe el nuevo array como argumento directo.

---

## 8. Traducciones (i18n)

### 8.1 Claves nuevas para los 16 locales (FLAT, gotcha G6)

```jsonc
"workflow.title": "Workflows",
"workflow.subtitle": "Configura las etapas y tareas obligatorias de cada tipo de expediente",
"workflow.case_types": "Tipos de Expediente",
"workflow.stages": "Etapas",
"workflow.task_templates": "Tipos de Tarea",
"workflow.add_stage": "Anadir etapa",
"workflow.add_task_template": "Anadir tipo de tarea",
"workflow.edit_stage": "Editar etapa",
"workflow.edit_task_template": "Editar tipo de tarea",
"workflow.stage_name": "Nombre de la etapa",
"workflow.stage_description": "Descripcion",
"workflow.stage_code": "Codigo (interno)",
"workflow.stage_color": "Color",
"workflow.stage_is_terminal": "Etapa terminal (cierra el expediente)",
"workflow.template_name": "Nombre de la tarea",
"workflow.template_description": "Descripcion",
"workflow.template_code": "Codigo (interno)",
"workflow.required_task": "Tarea obligatoria",
"workflow.blocks_completion": "Bloquea el avance de etapa hasta completarse",
"workflow.due_offset_days": "Vence X dias despues de creacion del expediente",
"workflow.default_type": "Tipo por defecto",
"workflow.default_priority": "Prioridad por defecto",
"workflow.advance_stage": "Avanzar a la siguiente etapa",
"workflow.stage_blocked": "No se puede avanzar: tareas obligatorias pendientes",
"workflow.no_workflow_defined": "Este tipo de expediente aun no tiene workflow configurado",
"workflow.configure_workflow": "Configurar workflow",
"workflow.translation_missing": "Falta traduccion en este idioma",
"workflow.confirm_delete_stage": "Eliminar esta etapa eliminara tambien sus tipos de tarea. Continuar?",
"workflow.confirm_delete_template": "Eliminar este tipo de tarea?",
"workflow.roadmap": "Roadmap del expediente",
"workflow.tasks_for_stage": "Tareas de esta etapa",
"workflow.add_adhoc_task": "Anadir tarea ad-hoc",
"workflow.stage_completed": "Etapa completada",
"workflow.stage_in_progress": "En curso",
"workflow.stage_pending": "Pendiente",
```

(Ingles y frances analogos en sus respectivos JSON.)

---

## 9. Seed de Workflows por Defecto

Plantillas globales (tenant_id resuelto al ejecutarse por tenant) cubriendo los 9 tipos de proceso descritos por el usuario.

### 9.1 Estrategia

- **Seeder:** `database/seeders/DefaultWorkflowsSeeder.php`
- **Comando artisan:** `php artisan workflow:seed-defaults {tenant_id?}` que:
  - Sin argumento: itera todos los tenants y semilla los que no tengan workflows definidos.
  - Con `tenant_id`: semilla solo ese tenant.
- **Hook automatico:** `TenantService::createTenant()` despues de crear case_types base llama al seeder con el nuevo tenant_id (en background via `dispatchSync` para no bloquear UX).

### 9.2 Mapeo case_types -> codigos

Los `case_types` actuales en BD tienen `code` corto (RT, EE, FV, etc.). El seeder usa `firstOrCreate` por `code`:

| Codigo | Nombre | Categoria |
|---|---|---|
| FAM | Patrocinio Familiar | permanent_residence |
| EE | Programas de Residencia Economica (Express Entry/PNP) | permanent_residence |
| TRP | Permisos de Residencia Temporal | temporary_residence |
| CIT | Ciudadania Canadiense | citizenship |
| RT | Residencia Temporal Multiple | temporary_residence |
| QC | Residencia Permanente Quebec | permanent_residence |
| FED | Residencia Permanente Federal y Pilotos | permanent_residence |
| REF | Refugiados y Apelaciones | refugee |
| CZD | Ciudadania y Documentos | citizenship |

### 9.3 Estructura del seed (resumen)

(El seed completo se incluye en el archivo `DefaultWorkflowsSeeder.php`. Aqui un resumen.)

**1. Patrocinio Familiar (FAM)**
- A. Evaluacion y Elegibilidad
  - Verificacion de la relacion (cohabitacion/vinculo sanguineo)
  - Evaluacion de antecedentes penales y financieros del patrocinador
  - Calculo de duracion del compromiso de manutencion (Undertaking)
- B. Integracion de Expediente
  - Recopilacion de pruebas de autenticidad
  - Preparacion de formularios (IMM 1344, IMM 5532, etc.)
  - Carga en Permanent Residence Portal
- C. Seguimiento y Post-Envio
  - Gestion de Solicitud de Biometricos y Examenes Medicos
  - Respuesta a PFL (Procedural Fairness Letter)
  - Recepcion de eCOPR (terminal)

**2. Programas de Residencia Economica (EE)**
- A. Fase Pre-ITA
  - Gestion de Evaluacion de Credenciales Educativas (ECA)
  - Registro y monitoreo de examenes de idiomas (IELTS/TEF)
  - Creacion y actualizacion de perfil en Pool de Express Entry
- B. Post-ITA: Gestion de Documentacion
  - Certificados de policia de paises de residencia (6+ meses)
  - Cartas de referencia laboral (TEER/NOC)
  - Verificacion de fondos de asentamiento
- C. Finalizacion
  - Envio de e-APR
  - Monitoreo de portal hasta decision final (terminal)

**3. Permisos de Residencia Temporal (TRP)**
- A. Estrategia y Admision
  - Verificacion de carta de aceptacion (LOA) de DLI
  - Analisis de oferta de empleo y exencion de LMIA
  - Evaluacion de intencion dual y lazos con pais de origen
- B. Preparacion de la Solicitud
  - Carta de Explicacion / Plan de Estudios / Justificacion de Empleo
  - Compilacion de estados financieros
  - Carga en IRCC Secure Account o Portal de Representante
- C. Gestion de Extensiones (terminal)
  - Control de fechas de vencimiento
  - Solicitud de Restablecimiento de Estatus si aplica

**4. Ciudadania Canadiense (CIT)**
- A. Verificacion de Requisitos
  - Calculo de presencia fisica (1,095 dias en 5 anos)
  - Revision de obligaciones fiscales (CRA)
  - Evaluacion de competencia linguistica (CLB 4+)
- B. Tramite Administrativo (terminal)
  - Preparacion y envio de solicitud
  - Preparacion para examen de conocimientos
  - Verificacion de documentos previos a ceremonia

**5. Residencia Temporal Multiple (RT)**
- 1. Admisibilidad y Estrategia
  - Evaluacion de lazos con pais de origen
  - Verificacion de fondos suficientes
  - Analisis de necesidad de CAQ (Quebec)
  - Revision de exenciones de LMIA
- 2. Documentacion y Carga
  - Recoleccion de cartas de aceptacion / contratos de empleo
  - Carta de Explicacion
  - Carga de formularios (IMM 1294, 5257, 5710)
  - Pagos de tasas y biometricos
- 3. Post-Envio y Mantenimiento (terminal)
  - Seguimiento de biometricos y examenes medicos
  - Solicitud de Visitor Record / extensiones
  - Notificacion de cambios de empleador o institucion

**6. Residencia Permanente Quebec (QC)**
- 1. Seleccion de Quebec (MIFI)
  - Creacion de perfil en Arrima
  - Gestion de Expresion de Interes (EI)
  - Recepcion de invitacion y carga de documentos para CSQ
  - Verificacion de cumplimiento de valores de Quebec
- 2. Etapa Federal IRCC (terminal)
  - Carga del CSQ en portal RP IRCC
  - Verificacion de antecedentes penales mundiales
  - Gestion de examenes medicos federales
  - Recepcion de eCOPR

**7. Residencia Permanente Federal y Pilotos (FED)**
- 1. Fase de Invitacion / Elegibilidad
  - Examenes de idiomas y ECA
  - Creacion de perfil de Express Entry
  - Monitoreo de rondas de invitacion (ITA)
- 2. Integracion del Expediente Completo (terminal)
  - Cartas de referencia laboral (NOC/TEER)
  - Narrativa para casos Humanitarios (H&C)
  - Pruebas de relacion genuina para Sponsorship

**8. Refugiados y Apelaciones (REF)**
- 1. Admision
  - Evaluacion de caso
  - Cargar informacion en portal IRCC
  - Producir resumen de expedientes en IRCC
  - Aprobacion de resumen de IRCC
  - Recepcion de formularios firmados
  - Solicitud de copia de expediente al IRCC y CISR
  - Elaboracion del documento de historia (FDA)
  - Confirmar fecha de audiencia
- 2. Inicio de Proteccion / ERAR
  - Evaluacion de riesgos (ERAR/PRRA)
  - Recoleccion de pruebas
  - Preparacion del documento de pruebas
  - Envio para traduccion de pruebas
  - Traduccion de pruebas
  - Envio de Documentos de pruebas a la CISR
- 3. Audiencia
  - Preparacion del cliente para la audiencia
  - Creacion de expediente de apelacion
  - Creacion de expediente corte federal
- 4. Litigio y Apelacion (terminal)
  - Identificacion de errores de derecho/hecho para SAR
  - Preparacion de memorandum de argumentos legales
  - Solicitud de Leave y revision judicial ante Corte Federal

**9. Ciudadania y Documentos (CZD)**
- 1. Evaluacion de Presencia
  - Auditoria de viajes de los ultimos 5 anos
  - Verificacion de declaraciones de impuestos (CRA)
- 2. Tramite y Logistica (terminal)
  - Preparacion para examen de ciudadania
  - Solicitud de Certificado de Ciudadania (hijos/reemplazos)
  - Tramite de Pasaporte o Documento de Viaje

### 9.4 Implementacion del seeder

Estructura: arreglo PHP `WORKFLOW_DATA` en `database/seeders/WorkflowSeedData.php` con la lista completa, y `DefaultWorkflowsSeeder.php` itera sobre el arreglo para cada tenant. Cada `task_template` incluye traducciones ES (obligatorias), EN y FR cuando sea posible (FR/EN se generan o se dejan en blanco para que el admin los complete posteriormente).

---

## 10. Migracion legacy `case_tasks` -> `tasks`

### 10.1 Comando artisan

**Archivo:** `app/Console/Commands/MigrateLegacyCaseTasksCommand.php`

```bash
php artisan workflow:migrate-legacy-tasks
php artisan workflow:migrate-legacy-tasks --tenant=5
php artisan workflow:migrate-legacy-tasks --dry-run
```

Logica:
1. Para cada `case_task` (fila de tabla `case_tasks`):
   - Verifica que su `case` no tenga ya `tasks` con `task_template_id` (evita duplicacion si el case ya fue procesado).
   - Crea fila en `tasks` con:
     - `subject = label`
     - `status = is_completed ? 'closed' : 'new'`
     - `priority = 'medium'`, `type = 'other'`
     - `task_template_id = null` (ad-hoc)
     - `workflow_stage_id = null` (legacy, no asociado a etapa)
2. Loguea totales: cases procesados, tasks migradas, errores.

### 10.2 Cuando ejecutar

- En staging: tras desplegar Sprint 3 backend, ejecutar el comando con `--dry-run` y luego sin flag.
- En produccion: en una ventana de mantenimiento despues de Sprint 5.
- **NO** dropear tabla `case_tasks` hasta validar 1 release que el comportamiento operativo es identico.

### 10.3 Drop final

Migracion futura `2026_05_xx_drop_case_tasks_and_legacy_stage.php`:
- Drop tabla `case_tasks`
- Drop columna `cases.stage` (string legacy)

(Esta migracion se crea **al final** del checklist, despues de validacion en produccion.)

---

## 11. Consideraciones de Seguridad y Multi-tenant

### 11.1 Tenant isolation
- `WorkflowStage` y `TaskTemplate` usan `BelongsToTenant` (gotcha G1).
- Las policies validan `$model->tenant_id === $user->tenant_id` adicionalmente al permiso.
- Endpoints admin estan tras `tenant` middleware + `role:admin|super-admin`.
- Translations son tenant-bound (FK opcional pero usado).

### 11.2 Permisos
- `workflows.view` (admin, super-admin, attorney, paralegal)
- `workflows.create|update|delete` (admin, super-admin)
- En frontend: `v-can="'workflows.update'"` en botones de edicion (UX, no seguridad -- gotcha G5).

### 11.3 Avance de etapa concurrente
- `WorkflowInstantiator::advanceStage` usa `lockForUpdate` dentro de transaccion para evitar avance doble.

### 11.4 Eliminacion de stage con cases activos
- FK `cases.current_stage_id` con `nullOnDelete`: el case queda sin stage actual (se vuelve "fuera de workflow").
- El soft-delete del stage permite recuperarlo.
- UI debe ofrecer "Desactivar" (`is_active=false`) en lugar de "Eliminar" cuando hay cases en uso (warning previo).

### 11.5 Cache invalidation
- Por simplicidad v1: sin cache de Redis. Las queries con eager-load + indices son suficientes.
- v2: si la lista de stages se vuelve cuello de botella, agregar `Cache::tags(['tenant:'.$id,'workflows'])` con invalidacion en observer de Stage/Template.

---

## 12. Checklist de Implementacion

### Fase 1: Infraestructura BD y Modelos
- [x] Crear migracion `2026_04_26_000001_create_translations_table.php`
- [x] Crear migracion `2026_04_26_000002_create_workflow_stages_table.php`
- [x] Crear migracion `2026_04_26_000003_create_task_templates_table.php`
- [x] Crear migracion `2026_04_26_000004_add_workflow_columns_to_cases.php`
- [x] Crear migracion `2026_04_26_000005_add_workflow_columns_to_tasks.php`
- [x] Crear migracion `2026_04_26_000006_seed_workflow_permissions.php`
- [x] Ejecutar `php artisan migrate` y verificar esquema
- [x] Crear trait `app/Models/Traits/HasTranslations.php`
- [x] Crear modelo `app/Models/Translation.php`
- [x] Crear modelo `app/Models/WorkflowStage.php` (con [[BelongsToTenant]] + HasTranslations)
- [x] Crear modelo `app/Models/TaskTemplate.php` (con BelongsToTenant + HasTranslations)
- [x] Anadir `Task` model si no existe; anadir `workflow_stage_id`, `task_template_id`, `sort_order` a fillable
- [x] Modificar `ImmigrationCase`: anadir `current_stage_id`, `workflow_snapshot` a fillable; relacion `currentStage()`; cambiar relacion `tasks()` a apuntar a `Task`; mantener `legacyChecklist()` apuntando a `CaseTask`
- [x] Actualizar `getActivitylogOptions()` en `ImmigrationCase` (cambiar `'stage'` -> `'current_stage_id'`)

### Fase 2: Servicios y Generador
- [x] Crear `app/Services/Workflow/TranslationResolver.php`
- [x] Crear `app/Exceptions/StageBlockedException.php`
- [x] Crear `app/Services/Workflow/WorkflowInstantiator.php` (instantiate, recalculateProgress, advanceStage, buildSnapshot)
- [x] Crear `app/Services/Workflow/WorkflowStageService.php`
- [x] Crear `app/Services/Workflow/TaskTemplateService.php`
- [x] Crear `app/Observers/TaskWorkflowObserver.php`
- [x] Registrar observer en `AppServiceProvider::boot` (gotcha G7)

### Fase 3: API Admin (Controllers + Resources + Policies + Requests)
- [x] Crear `app/Policies/WorkflowStagePolicy.php`
- [x] Crear `app/Policies/TaskTemplatePolicy.php`
- [x] Registrar policies en `AppServiceProvider::boot`
- [x] Crear `app/Http/Requests/Workflow/StoreWorkflowStageRequest.php`
- [x] Crear `app/Http/Requests/Workflow/UpdateWorkflowStageRequest.php`
- [x] Crear `app/Http/Requests/Workflow/StoreTaskTemplateRequest.php`
- [x] Crear `app/Http/Requests/Workflow/UpdateTaskTemplateRequest.php`
- [x] Crear `app/Http/Resources/WorkflowStageResource.php`
- [x] Crear `app/Http/Resources/TaskTemplateResource.php`
- [x] Crear `app/Http/Controllers/Api/Admin/WorkflowStageController.php`
- [x] Crear `app/Http/Controllers/Api/Admin/TaskTemplateController.php`
- [x] Anadir endpoints `workflowPreview` y `advanceStage` a `CaseController`
- [x] Registrar todas las rutas en `routes/api.php` (grupo `admin/workflow`)

### Fase 4: Integracion en [[CaseService]]
- [x] Inyectar `WorkflowInstantiator` en `CaseService`
- [x] Reemplazar lectura de `case_tasks` en `createCase` por llamada a `WorkflowInstantiator::instantiate`
- [x] Aceptar parametro opcional `excluded_template_ids[]` en payload
- [x] Eager-load `currentStage`, `tasks.template` en `getCase`
- [x] Modificar `CaseResource` para incluir `current_stage`, `workflow_snapshot`, `tasks`

### Fase 5: Seed de Workflows por Defecto -- **OMITIDA** (error en data; se ejecutara manualmente desde el admin)
- [ ] ~~Crear `database/seeders/WorkflowSeedData.php` con arreglo de los 9 tipos~~
- [ ] ~~Crear `database/seeders/DefaultWorkflowsSeeder.php`~~
- [ ] ~~Crear comando `php artisan workflow:seed-defaults {tenant?}`~~
- [ ] ~~Hook en `TenantService::createTenant` para auto-seed al crear tenant nuevo~~
- [ ] ~~Ejecutar seed en tenants existentes y validar conteos~~

### Fase 6: Frontend Admin
- [x] Crear `resources/js/src/types/workflow.ts`
- [x] Crear `resources/js/src/services/workflowService.ts`
- [x] Crear `resources/js/src/stores/useWorkflowStore.ts`
- [x] Crear `resources/js/src/components/shared/TrilingualInput.vue`
- [x] Crear `resources/js/src/views/admin/workflows/index.vue`
- [x] Crear `resources/js/src/views/admin/workflows/builder.vue` (3 columnas + drag&drop)
- [x] Crear `resources/js/src/components/admin/StageEditorModal.vue`
- [x] Crear `resources/js/src/components/admin/TaskTemplateEditorModal.vue`
- [x] Anadir rutas `/admin/workflows` y `/admin/workflows/:caseTypeId` en `router/index.ts` con guard `permission: 'workflows.view'`
- [x] Anadir entrada en `Sidebar.vue` con `v-can="'workflows.view'"`

### Fase 7: Frontend Wizard y Show
- [x] Refactor `wizard/steps/StepChecklist.vue`: cargar plantillas remotas via `workflowService.workflowPreview`
- [x] Mostrar fallback "Sin workflow definido" cuando `stages.length === 0`
- [x] Anadir `excludedTemplateIds` al state de `useCaseWizard.ts`
- [x] Modificar payload de `submit()` para enviar `excluded_template_ids`
- [x] Crear `resources/js/src/components/cases/StageProgressBar.vue`
- [x] Modificar `views/cases/show.vue`:
  - [x] Reemplazar render de `cases.stage` por `<StageProgressBar>`
  - [x] Tab de tasks: agrupar por stage usando `tasks.workflow_stage_id`
  - [x] Boton "Avanzar etapa" con confirmacion + manejo de error 422 STAGE_BLOCKED
- [x] Actualizar tipo `ImmigrationCase` en `types/case.ts`: anadir `current_stage_id`, `workflow_snapshot`

### Fase 8: Traducciones
- [x] Anadir bloque `workflow.*` (50 keys nuevas) en `es.json`
- [x] Anadir bloque `workflow.*` en `en.json`
- [x] Anadir bloque `workflow.*` en `fr.json`
- [x] Replicar (al menos las claves de UI) en los otros 13 locales (de, it, pt, ru, pl, tr, ja, zh, el, hu, da, sv, ae) -- placeholder en ingles
- [x] Verificar que todas son **flat** (gotcha G6)

### Fase 9: Migracion legacy `case_tasks` -> `tasks` -- **OMITIDA** (diferida)
- [ ] ~~Crear `app/Console/Commands/MigrateLegacyCaseTasksCommand.php`~~
- [ ] ~~Ejecutar `--dry-run` en staging~~
- [ ] ~~Ejecutar sin flag y validar paridad~~
- [ ] ~~Crear migracion `drop_case_tasks_table` + `drop_stage_column_from_cases`~~

### Fase 10: Testing y QA
- [x] Build de produccion (`npm run build`) sin errores
- [x] Verificacion de rutas backend con `php artisan route:list --path=workflow` (11 rutas registradas) y `--path=advance` (1 ruta)
- [x] Verificacion de sintaxis PHP en CaseService, CaseController, AppServiceProvider
- [ ] Test feature: CRUD de workflow_stages como admin (pendiente, escribir tests)
- [ ] Test feature: CRUD de task_templates como admin
- [ ] Test feature: tenant isolation (admin de tenant A no ve workflows de tenant B)
- [ ] Test feature: crear case con workflow definido instancia tasks correctamente
- [ ] Test feature: advance-stage bloquea cuando hay tareas obligatorias pendientes
- [ ] Test feature: advance-stage en stage terminal cierra el case
- [ ] Test unit: `HasTranslations::trans` con fallback a ES
- [ ] Test unit: `WorkflowInstantiator::buildSnapshot`
- [ ] Test manual: wizard completo con workflow definido
- [ ] Test manual: wizard completo sin workflow (fallback)
- [ ] Test manual: visualizacion del Roadmap en cases/show.vue
- [ ] Test manual: edicion de stage en admin builder con drag&drop
- [ ] Test manual: edicion trilingue en TrilingualInput

---

**Resultado esperado:** sistema escalable que permite a cada tenant definir sus propias metodologias de trabajo (etapas + tareas obligatorias) por tipo de expediente, con i18n trilingue desde la UI, snapshot por expediente para estabilidad operativa, y migracion limpia desde el modelo `case_tasks` actual al modelo `tasks` mas completo.
