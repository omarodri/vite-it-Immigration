---
tipo: spec
numero: 49
modulo: Workflows y Tareas
dominio: WFLOW
estado: DONE
tags: [spec, workflows, done]
relacionados:
  - "[[19_todo_list_integracion_casos|Spec 19]]"
  - "[[46_workflows_dinamicos_motor_etapas|Spec 46]]"
---
# Especificacion Tecnica: Todos Core desde Workflow de Expediente

## Metadata
- **Fecha:** 2026-05-02
- **Version:** 1.0
- **Arquitecto:** Claude Code (Architect Agent)
- **Estado:** Implementado (Fase 10 — Smoke Tests pendientes)
- **Dependencias:** [[46_workflows_dinamicos_motor_etapas|Spec 46]] (Motor de Workflows Dinamicos), [[47_tablero_interactivo_tareas_expediente|Spec 47]] (Tablero Interactivo de Tareas), [[48_etapas_personalizadas_por_expediente|Spec 48]] (Etapas Personalizadas), [[19_todo_list_integracion_casos|Spec 19]] (Todo List Integracion Casos)

---

## 1. Resumen Ejecutivo

### Problema
Tras la implementacion del motor de workflows (Specs 46-48), cada expediente genera tareas (`Task`) instanciadas desde plantillas (`task_templates`). Sin embargo, estas tareas son entidades del tablero del expediente (vista del attorney/admin) y **no aparecen en la bandeja operativa del consultor** (la lista de Todos). El consultor que recibe el expediente no tiene visibilidad automatica de su carga de trabajo derivada del ciclo de vida.

Esto obliga al consultor a:
- Revisar manualmente el tablero de cada expediente para saber que tiene pendiente.
- Crear Todos manualmente, duplicando informacion ya definida en la plantilla.
- Perder el vinculo entre el avance del expediente y el estado operativo de su agenda.

### Alcance
- **Generacion automatica de Todos Core:** al instanciar el workflow de un expediente (wizard o adicion posterior), crear un `Todo` correspondiente por cada `Task` generada desde plantilla.
- **Marcado `is_core`:** distinguir estos todos operativos de los todos ad-hoc manuales.
- **Sincronizacion bidireccional de estado:** completar un Todo Core cierra la Task correspondiente y viceversa.
- **Modal de confirmacion UX:** confirmacion explicita antes de crear o eliminar Todos Core vinculados al ciclo de vida.
- **Gestion del ciclo de vida:** propagacion coherente cuando se añaden, modifican o eliminan Tasks del workflow en expedientes existentes.
- **Reasignacion de consultor:** cuando el `assigned_to` del expediente cambia, los Todos Core pendientes siguen al nuevo consultor.

### Fuera de alcance (v1)
- Notificaciones push al consultor cuando se le asigna un Todo Core.
- Vista consolidada de Todos Core agrupados por expediente (sigue siendo la vista de todos existente con filtro).
- Logica de dias habiles para calcular due_date (heredar formula simple de `WorkflowInstantiator`).
- Promocion bidireccional de prioridad (cambios en `Task.priority` no se propagan al Todo Core tras la creacion inicial).

---

## 2. Arquitectura

### 2.1 Decision: Acoplamiento a `Task` (no a `CaseTask` legacy)

`CaseTask` (checklist plano) es una capa legacy marcada como tal en `ImmigrationCase::legacyChecklist()`. El modelo activo del ciclo de vida es `App\Models\Task` (instanciado por `WorkflowInstantiator`). **Los Todos Core se acoplan a `Task`, no a `CaseTask`.**

Razon: `Task` tiene `task_template_id`, `case_stage_id`, `SoftDeletes`, `LogsActivity` y el observer `TaskWorkflowObserver` ya activo. Reanimar `CaseTask` introduciria una segunda fuente de verdad.

### 2.2 Decision: FKs explicitas vs polimorfismo

Se usa **FK directa `task_id`** en la tabla `todos`, no polimorfismo (`source_type` / `source_id`).

Razon: un Todo Core tiene exactamente un origen posible (`Task`). El polimorfismo añade overhead de query (`whereHasMorph`) y elimina la integridad referencial FK sin beneficio. El unico costo (mas migraciones futuras si aparece un nuevo origen) es aceptable y gestionable.

### 2.3 Decision: `is_core` boolean vs enum `type`

Se usa **`is_core` boolean** (default `false`) como discriminador.

Razon: semanticamente un todo es core o no lo es — no hay una tercera categoria hoy. El enum se reserva para iteraciones futuras si aparecen tipos adicionales (delegado, recordatorio externo). Hoy YAGNI manda.

### 2.4 Decision: Sincronizacion sincrona vs asincrona

Se usa **Eloquent Observer sincrono** (no Event + Listener async de queue).

Razon: el cambio de estado debe reflejarse de inmediato en la UI del consultor. Encolar la propagacion a la Task introduciria latencia visible (el todo queda completo pero la Task sigue abierta hasta que el worker procese el job). La propagacion es un simple `UPDATE tasks SET status='resolved'` — no justifica el costo de latencia de queue.

### 2.5 Diagrama de componentes

```
WIZARD (useCaseWizard.ts)
    │ submit()
    │ → muestra TodoCoreConfirmationModal (escenario 1)
    │ → POST /api/cases
    ▼
[CaseService::createCase()]
    │
    ▼
[WorkflowInstantiator::instantiate()]
    │ crea Tasks desde task_templates
    │
    ▼
[TodoCoreService::createForCase(ImmigrationCase)]
    │ crea un Todo Core por cada Task con task_template_id
    │ idempotente (firstOrCreate)
    ▼
[todos tabla]  ←───────────────────────────────┐
                                                │
CONSULTOR completa Todo                         │
    │                                           │
    ▼                                           │
[TodoObserver::updated()]                       │
    │ DB::transaction                           │
    ▼                                           │
[Task::update(status → 'resolved')]             │
    │                                           │
    ▼                                           │
[TaskWorkflowObserver::updated()]               │
    │ TodoCoreService::syncFromTaskStatus()     │
    └───────────────────────────────────────────┘
    (guard: $syncingFromTask flag, anti-loop)

ATTORNEY completa Task desde tablero
    │
    ▼
[TaskWorkflowObserver::updated()]
    │ TodoCoreService::syncFromTaskStatus()
    │ DB::transaction
    ▼
[Todo::update(status → 'complete')]
    │
    ▼
[TodoObserver::updated()]
    │ guard: $syncingFromTask = true → return early
    └─ (sin loop)
```

### 2.6 Diagrama de estados

```
Task.status        Todo.status
-----------        -----------
new            ←→  pending
assigned       ←→  pending
in_progress    ←→  pending
resolved       ←→  complete
closed         ←→  complete

Reabrir Task (resolved → in_progress) → Todo vuelve a pending
Reabrir Todo (complete → pending) → Task vuelve a in_progress
```

---

## 3. Cambios en Base de Datos

### 3.1 Migracion: Columnas core en tabla `todos`

**Archivo:** `database/migrations/2026_05_02_000001_add_core_columns_to_todos_table.php`

```php
public function up(): void
{
    Schema::table('todos', function (Blueprint $table) {
        $table->foreignId('task_id')
              ->nullable()
              ->after('case_id')
              ->constrained('tasks')
              ->nullOnDelete();

        $table->foreignId('task_template_id')
              ->nullable()
              ->after('task_id')
              ->constrained('task_templates')
              ->nullOnDelete();

        $table->boolean('is_core')->default(false)->after('task_template_id');

        $table->enum('source', ['manual', 'wizard', 'workflow_add', 'workflow_sync'])
              ->default('manual')
              ->after('is_core');

        $table->softDeletes()->after('updated_at');

        $table->index(['tenant_id', 'is_core', 'status']);
        $table->index('task_id');
        $table->index('task_template_id');
        $table->unique(['task_id', 'is_core'], 'todos_core_per_task_unique');
    });
}

public function down(): void
{
    Schema::table('todos', function (Blueprint $table) {
        $table->dropUnique('todos_core_per_task_unique');
        $table->dropIndex(['task_id']);
        $table->dropIndex(['task_template_id']);
        $table->dropForeign(['task_id']);
        $table->dropForeign(['task_template_id']);
        $table->dropColumn(['task_id', 'task_template_id', 'is_core', 'source', 'deleted_at']);
    });
}
```

### 3.2 Migracion: Permisos de Todos Core

**Archivo:** `database/migrations/2026_05_02_000002_seed_todo_core_permissions.php`

Cumple Gotcha G4: los permisos se crean en migracion, no en seeder, para sobrevivir `migrate:fresh` en produccion.

```php
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

public function up(): void
{
    $permissions = [
        'todo_core.view',
        'todo_core.create',
        'todo_core.complete',
        'todo_core.delete',
    ];

    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    foreach (['admin', 'attorney', 'paralegal'] as $roleName) {
        $role = Role::findByName($roleName, 'web');
        if ($role) {
            $role->givePermissionTo($permissions);
        }
    }
}

public function down(): void
{
    Permission::whereIn('name', [
        'todo_core.view', 'todo_core.create',
        'todo_core.complete', 'todo_core.delete',
    ])->delete();
}
```

---

## 4. Cambios en Backend

### 4.1 Modelo `Todo`

**Archivo:** `app/Models/Todo.php`

Cambios:
- Agregar `SoftDeletes`
- Agregar `task_id`, `task_template_id`, `is_core`, `source` al fillable
- Agregar relaciones `task()`, `taskTemplate()`
- Agregar scope `scopeCore()`

```php
namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Todo extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'assigned_to_id',
        'case_id',
        'task_id',
        'task_template_id',
        'is_core',
        'source',
        'tag',
        'priority',
        'status',
        'due_date',
    ];

    protected $casts = [
        'due_date' => 'date',
        'is_core'  => 'boolean',
    ];

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function immigrationCase(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class, 'case_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function taskTemplate(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class, 'task_template_id');
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeCore(Builder $query): Builder
    {
        return $query->where('is_core', true);
    }
}
```

### 4.2 Servicio `TodoCoreService` (NUEVO)

**Archivo:** `app/Services/Todo/TodoCoreService.php`

```php
namespace App\Services\Todo;

use App\Models\ImmigrationCase;
use App\Models\Task;
use App\Models\Todo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class TodoCoreService
{
    /**
     * Crea un Todo Core para una Task dada.
     * Idempotente: usa firstOrCreate para evitar duplicados.
     *
     * @param string $source  wizard|workflow_add|workflow_sync
     */
    public function createForTask(Task $task, string $source = 'wizard', ?Carbon $referenceDate = null): ?Todo
    {
        if (! $task->task_template_id) {
            return null;
        }

        $assignedTo = $task->assigned_to
            ?? $task->requester_id
            ?? Auth::id();

        $dueDate = null;
        if ($task->template && $task->template->due_offset_days) {
            $base    = $referenceDate ?? now();
            $dueDate = $base->copy()->addDays($task->template->due_offset_days)->toDateString();
        }

        return Todo::firstOrCreate(
            [
                'task_id' => $task->id,
                'is_core' => true,
            ],
            [
                'tenant_id'        => $task->tenant_id,
                'title'            => $task->subject,
                'description'      => $task->description,
                'assigned_to_id'   => $assignedTo,
                'case_id'          => $task->case_id,
                'task_template_id' => $task->task_template_id,
                'source'           => $source,
                'priority'         => $this->mapPriority($task->priority),
                'status'           => 'pending',
                'due_date'         => $dueDate,
            ]
        );
    }

    /**
     * Crea Todos Core para todas las Tasks con task_template_id de un expediente.
     * Idempotente por diseno (firstOrCreate en createForTask).
     */
    public function createForCase(ImmigrationCase $case, string $source = 'wizard'): void
    {
        $referenceDate = $case->created_at ? Carbon::parse($case->created_at) : now();

        $case->tasks()
            ->whereNotNull('task_template_id')
            ->whereNull('deleted_at')
            ->with('template')
            ->get()
            ->each(fn (Task $task) => $this->createForTask($task, $source, $referenceDate));
    }

    /**
     * Sincroniza el estado del Todo Core cuando cambia el estado de la Task.
     * Llamado desde TaskWorkflowObserver.
     */
    public function syncFromTaskStatus(Task $task): void
    {
        $todo = Todo::where('task_id', $task->id)->where('is_core', true)->first();

        if (! $todo) {
            return;
        }

        $newStatus = in_array($task->status, ['resolved', 'closed']) ? 'complete' : 'pending';

        if ($todo->status !== $newStatus) {
            $todo->updateQuietly(['status' => $newStatus]);
        }
    }

    /**
     * Gestiona el Todo Core cuando su Task es eliminada del workflow.
     *
     * @param string $strategy  soft_delete|keep_orphan|hard_delete
     */
    public function removeForTask(Task $task, string $strategy = 'soft_delete'): void
    {
        $todo = Todo::where('task_id', $task->id)->where('is_core', true)->first();

        if (! $todo) {
            return;
        }

        match ($strategy) {
            'soft_delete' => $this->softDeleteByStatus($todo),
            'hard_delete' => $todo->forceDelete(),
            'keep_orphan' => $todo->updateQuietly(['task_id' => null]),
            default       => $this->softDeleteByStatus($todo),
        };
    }

    /**
     * Reasigna Todos Core pendientes a un nuevo usuario.
     */
    public function reassignPendingForCase(ImmigrationCase $case, int $newUserId): void
    {
        Todo::where('case_id', $case->id)
            ->where('is_core', true)
            ->where('status', 'pending')
            ->update(['assigned_to_id' => $newUserId]);
    }

    private function softDeleteByStatus(Todo $todo): void
    {
        if (in_array($todo->status, ['pending', 'trash'])) {
            $todo->delete();
        }
        // complete e important: se preservan como historico
    }

    private function mapPriority(string $taskPriority): string
    {
        return match ($taskPriority) {
            'urgent', 'high' => 'high',
            'medium'         => 'medium',
            default          => 'low',
        };
    }
}
```

### 4.3 Modificacion: `WorkflowInstantiator::instantiate()`

**Archivo:** `app/Services/Workflow/WorkflowInstantiator.php`

Agregar llamada a `TodoCoreService::createForCase()` al final de la transaccion, despues del `Task::insert($rows)`.

```php
// Al inicio del archivo, agregar:
use App\Services\Todo\TodoCoreService;

// En el metodo instantiate(), dentro de la transaccion, al final:
if ($rows) {
    Task::insert($rows);
}

// NUEVO: crear Todos Core para cada Task instanciada
$case->load('tasks.template'); // refrescar relacion tras insert masivo
app(TodoCoreService::class)->createForCase($case, 'wizard');

$this->recalculateProgress($case);
```

### 4.4 Observer `TodoObserver` (NUEVO)

**Archivo:** `app/Observers/TodoObserver.php`

Sincroniza Todo → Task cuando un Todo Core se completa o se reabre. Usa flags estaticos anti-loop (patron del proyecto, igual que `CalendarSyncService::$isPulling`).

```php
namespace App\Observers;

use App\Models\Task;
use App\Models\Todo;
use Illuminate\Support\Facades\DB;

class TodoObserver
{
    /**
     * Flag anti-loop: se activa desde TaskWorkflowObserver
     * para evitar que el update del Todo relance este observer.
     */
    public static bool $syncingFromTask = false;

    public function updated(Todo $todo): void
    {
        if (self::$syncingFromTask) {
            return;
        }

        if (! $todo->is_core || ! $todo->task_id) {
            return;
        }

        if (! $todo->wasChanged('status')) {
            return;
        }

        $task = Task::find($todo->task_id);

        if (! $task) {
            return;
        }

        $targetTaskStatus = $todo->status === 'complete' ? 'resolved' : 'in_progress';

        if ($task->status === $targetTaskStatus) {
            return;
        }

        // Resolved/closed no se reabren desde el todo (solo el attorney puede hacerlo)
        if ($todo->status !== 'complete' && in_array($task->status, ['resolved', 'closed'])) {
            return;
        }

        DB::transaction(function () use ($task, $targetTaskStatus) {
            TaskWorkflowObserver::$syncingFromTodo = true;
            try {
                $task->update(['status' => $targetTaskStatus]);
            } finally {
                TaskWorkflowObserver::$syncingFromTodo = false;
            }
        });
    }
}
```

### 4.5 Modificacion: `TaskWorkflowObserver`

**Archivo:** `app/Observers/TaskWorkflowObserver.php`

Agregar flag `$syncingFromTodo` y llamada a `TodoCoreService::syncFromTaskStatus()`.

```php
namespace App\Observers;

use App\Models\Task;
use App\Services\Todo\TodoCoreService;
use App\Services\Workflow\WorkflowInstantiator;

class TaskWorkflowObserver
{
    public static bool $syncingFromTodo = false;

    public function __construct(private WorkflowInstantiator $instantiator) {}

    public function updated(Task $task): void
    {
        if ($task->wasChanged('status') && $task->case_id && $task->task_template_id) {
            $case = $task->immigrationCase;
            if ($case) {
                $this->instantiator->recalculateProgress($case);
            }

            // Sync Todo Core ← Task (sin loop)
            if (! self::$syncingFromTodo) {
                TodoObserver::$syncingFromTask = true;
                try {
                    app(TodoCoreService::class)->syncFromTaskStatus($task);
                } finally {
                    TodoObserver::$syncingFromTask = false;
                }
            }
        }
    }

    public function deleted(Task $task): void
    {
        if ($task->case_id && $task->task_template_id) {
            $case = $task->immigrationCase;
            if ($case) {
                $this->instantiator->recalculateProgress($case);
            }
        }
    }
}
```

### 4.6 Modificacion: `CaseObserver`

**Archivo:** `app/Observers/CaseObserver.php`

Reasignar Todos Core pendientes cuando cambia el consultor del expediente.

```php
namespace App\Observers;

use App\Models\ImmigrationCase;
use App\Services\Todo\TodoCoreService;

class CaseObserver
{
    public function updated(ImmigrationCase $case): void
    {
        if ($case->wasChanged('assigned_to') && $case->assigned_to) {
            app(TodoCoreService::class)->reassignPendingForCase($case, $case->assigned_to);
        }
    }
}
```

### 4.7 Modificacion: `AppServiceProvider`

**Archivo:** `app/Providers/AppServiceProvider.php`

Registrar el nuevo `TodoObserver`. Cumple Gotcha G7.

```php
use App\Observers\TodoObserver;
use App\Models\Todo;

// En boot():
Todo::observe(TodoObserver::class);
```

### 4.8 Modificacion: `CaseTaskController`

**Archivo:** `app/Http/Controllers/Api/CaseTaskController.php`

Extender los metodos `store()` y `destroy()` para:
- `store()`: aceptar `create_todo_core` (boolean, default `true`) y `source` (`workflow_add`)
- `destroy()`: aceptar `cascade_todo` (`soft_delete` | `keep_orphan` | `hard_delete`, default `soft_delete`)

```php
// En store(), tras crear la Task:
if ($request->boolean('create_todo_core', true) && $task->task_template_id) {
    app(TodoCoreService::class)->createForTask($task, 'workflow_add');
}

// En destroy(), antes de eliminar la Task:
$strategy = $request->input('cascade_todo', 'soft_delete');
app(TodoCoreService::class)->removeForTask($task, $strategy);
$task->delete();
```

### 4.9 Ruta opcional (future iteration)

```php
// routes/api.php — dentro del grupo auth:sanctum + tenant
Route::post('/cases/{case}/tasks/{task}/resync-todo', [CaseTaskController::class, 'resyncTodo']);
```

---

## 5. Cambios en Frontend

### 5.1 Tipos TypeScript

**Archivo:** `resources/js/src/types/todo.ts` (crear o extender)

```typescript
export type TodoStatus = 'pending' | 'complete' | 'important' | 'trash';
export type TodoPriority = 'low' | 'medium' | 'high';
export type TodoSource = 'manual' | 'wizard' | 'workflow_add' | 'workflow_sync';

export interface Todo {
    id: number;
    tenant_id: number;
    title: string;
    description: string | null;
    assigned_to_id: number | null;
    case_id: number | null;
    task_id: number | null;
    task_template_id: number | null;
    is_core: boolean;
    source: TodoSource;
    tag: string | null;
    priority: TodoPriority;
    status: TodoStatus;
    due_date: string | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
}

export interface TodoCoreConfirmPayload {
    scenario: 'wizard_create' | 'add_to_existing' | 'remove_from_existing';
    tasksAffected: TodoCoreTaskSummary[];
    consultantName?: string;
    cascadeStrategy?: 'soft_delete' | 'keep_orphan';
}

export interface TodoCoreTaskSummary {
    id?: number;
    subject: string;
    assignedToName?: string;
    dueDate?: string;
}
```

### 5.2 Servicio `todoService` (modificacion)

**Archivo:** `resources/js/src/services/todoService.ts`

Agregar metodos para Todos Core:

```typescript
const listCore = (caseId: number) =>
    api.get<Todo[]>(`/todos?case_id=${caseId}&is_core=1`);

const createCore = (taskId: number, createTodoCore: boolean = true) =>
    api.post(`/cases/${taskId}/resync-todo`, { create_todo_core: createTodoCore });

const removeCoreForTask = (caseId: number, taskId: number, strategy: string = 'soft_delete') =>
    api.delete(`/cases/${caseId}/tasks/${taskId}`, { data: { cascade_todo: strategy } });
```

### 5.3 Composable `useCaseWizard.ts` (modificacion)

**Archivo:** `resources/js/src/composables/useCaseWizard.ts`

Interceptar `submit()` para mostrar el modal de confirmacion antes de enviar. Agregar estado `showTodoCoreModal` y handler de confirmacion.

```typescript
// Nuevo estado
const showTodoCoreModal = ref(false);
const pendingSubmit = ref(false);

// Modificar submit():
async function submit(): Promise<ImmigrationCase | null> {
    // Contar tareas que generaran Todos Core
    const templateCount = wizardState.value.excludedTemplateIds
        ? allTemplates.value.length - wizardState.value.excludedTemplateIds.length
        : allTemplates.value.length;

    if (templateCount > 0 && wizardState.value.caseDetails.assigned_to) {
        showTodoCoreModal.value = true;
        return null; // el modal llamara a confirmSubmit()
    }

    return await doSubmit();
}

async function confirmSubmit(): Promise<ImmigrationCase | null> {
    showTodoCoreModal.value = false;
    return await doSubmit();
}

// doSubmit() contiene la logica de submit existente
```

### 5.4 Componente `TodoCoreConfirmationModal.vue` (NUEVO)

**Archivo:** `resources/js/src/components/cases/TodoCoreConfirmationModal.vue`

```vue
<script lang="ts" setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { TodoCoreTaskSummary } from '@/types/todo';

const props = defineProps<{
    show: boolean;
    scenario: 'wizard_create' | 'add_to_existing' | 'remove_from_existing';
    tasksAffected: TodoCoreTaskSummary[];
    consultantName?: string;
}>();

const emit = defineEmits<{
    (e: 'confirm', payload: { cascadeStrategy?: string }): void;
    (e: 'cancel'): void;
}>();

const { t } = useI18n();

const title = computed(() => t('todo_core.confirmation_title'));

const message = computed(() => {
    const count = props.tasksAffected.length;
    const consultant = props.consultantName || '';
    if (props.scenario === 'wizard_create') {
        return t('todo_core.confirm_create_from_wizard', { count, consultant });
    }
    if (props.scenario === 'add_to_existing') {
        return t('todo_core.confirm_create_for_existing_case', { consultant });
    }
    return t('todo_core.confirm_remove_with_todo', { consultant });
});

const isRemoveScenario = computed(() => props.scenario === 'remove_from_existing');
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="panel w-full max-w-lg">
            <div class="panel-header">
                <h5 class="text-lg font-semibold">{{ title }}</h5>
            </div>
            <div class="panel-body space-y-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ message }}</p>

                <ul v-if="tasksAffected.length" class="space-y-2">
                    <li
                        v-for="task in tasksAffected"
                        :key="task.subject"
                        class="flex items-center gap-2 rounded border border-gray-200 px-3 py-2 text-sm dark:border-gray-700"
                    >
                        <span class="flex-1 font-medium">{{ task.subject }}</span>
                        <span v-if="task.dueDate" class="text-xs text-gray-500">
                            {{ $t('todo_core.summary_due_label', { date: task.dueDate }) }}
                        </span>
                    </li>
                </ul>

                <div v-if="isRemoveScenario" class="flex flex-col gap-2 sm:flex-row">
                    <button
                        type="button"
                        class="btn btn-danger flex-1"
                        @click="emit('confirm', { cascadeStrategy: 'soft_delete' })"
                    >
                        {{ $t('todo_core.action_cascade_soft_delete') }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-secondary flex-1"
                        @click="emit('confirm', { cascadeStrategy: 'keep_orphan' })"
                    >
                        {{ $t('todo_core.action_cascade_keep_orphan') }}
                    </button>
                </div>

                <div v-else class="flex gap-2">
                    <button
                        type="button"
                        class="btn btn-primary flex-1"
                        @click="emit('confirm', {})"
                    >
                        {{ $t('todo_core.action_confirm') }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-outline-secondary flex-1"
                        @click="emit('cancel')"
                    >
                        {{ $t('todo_core.action_cancel') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
```

### 5.5 Integracion en `cases/edit.vue` y `cases/show.vue`

- Importar `TodoCoreConfirmationModal`
- En el handler de eliminar tarea (`deleteTask()`): mostrar el modal (escenario `remove_from_existing`) antes de la llamada DELETE.
- En el handler de añadir tarea (`addTask()`): mostrar modal (escenario `add_to_existing`) si la tarea tiene `task_template_id`.

### 5.6 Claves i18n FLAT (16 idiomas)

Agregar en **todos** los archivos `resources/js/src/locales/*.json`:

```json
"todo_core.confirmation_title": "...",
"todo_core.confirm_create_from_wizard": "...",
"todo_core.confirm_create_for_existing_case": "...",
"todo_core.confirm_remove_with_todo": "...",
"todo_core.action_cascade_soft_delete": "...",
"todo_core.action_cascade_keep_orphan": "...",
"todo_core.action_confirm": "...",
"todo_core.action_cancel": "...",
"todo_core.summary_count_label": "...",
"todo_core.summary_due_label": "...",
"todo_core.success_created": "...",
"todo_core.success_removed": "...",
"todo_core.error_generic": "..."
```

Traducciones EN y ES definidas. Las 14 lenguas restantes siguen el mismo patron.

**Ingles (en.json):**
```json
"todo_core.confirmation_title": "Confirm Operational Tasks",
"todo_core.confirm_create_from_wizard": "This will create {count} operational tasks (todos) for {consultant}. Continue?",
"todo_core.confirm_create_for_existing_case": "Create the corresponding operational task in {consultant}'s agenda?",
"todo_core.confirm_remove_with_todo": "This task is linked to a todo in {consultant}'s agenda. What should happen to it?",
"todo_core.action_cascade_soft_delete": "Remove both",
"todo_core.action_cascade_keep_orphan": "Keep the todo (mark as orphan)",
"todo_core.action_confirm": "Confirm",
"todo_core.action_cancel": "Cancel",
"todo_core.summary_count_label": "{count} tasks affected",
"todo_core.summary_due_label": "Due: {date}",
"todo_core.success_created": "{count} operational tasks created successfully",
"todo_core.success_removed": "Operational task synced",
"todo_core.error_generic": "Could not synchronize the operational tasks"
```

**Espanol (es.json):**
```json
"todo_core.confirmation_title": "Confirmar tareas operativas",
"todo_core.confirm_create_from_wizard": "Se crearán {count} tareas operativas (todos) para {consultant}. ¿Continuar?",
"todo_core.confirm_create_for_existing_case": "¿Crear la tarea operativa correspondiente en la agenda de {consultant}?",
"todo_core.confirm_remove_with_todo": "Esta tarea está vinculada a un todo en la agenda de {consultant}. ¿Qué hacer con él?",
"todo_core.action_cascade_soft_delete": "Eliminar ambos",
"todo_core.action_cascade_keep_orphan": "Mantener el todo (marcar como huérfano)",
"todo_core.action_confirm": "Confirmar",
"todo_core.action_cancel": "Cancelar",
"todo_core.summary_count_label": "{count} tareas afectadas",
"todo_core.summary_due_label": "Vence: {date}",
"todo_core.success_created": "{count} tareas operativas creadas correctamente",
"todo_core.success_removed": "Tarea operativa sincronizada",
"todo_core.error_generic": "No se pudieron sincronizar las tareas operativas"
```

---

## 6. Manejo de Errores y Edge Cases

### 6.1 Ejecucion duplicada del Wizard
El unique constraint `(task_id, is_core)` sobre la tabla `todos` previene la duplicacion a nivel BD. El metodo `firstOrCreate` en `TodoCoreService` garantiza idempotencia silenciosa en reintentos.

### 6.2 Task sin `assigned_to`
Si la `Task` no tiene consultor asignado, el Todo Core se asigna al `requester_id` de la task, luego a `Auth::id()`. Si ninguno esta disponible, el todo se crea sin asignado y el admin puede asignarlo manualmente.

### 6.3 Reasignacion de consultor
`CaseObserver::updated()` solo reasigna todos `pending`. Los todos `complete` e `important` quedan con el asignado original (integridad historica).

### 6.4 Task eliminada permanentemente (hard-delete)
La FK `task_id` tiene `nullOnDelete`, por lo que el Todo Core queda huerfano con `task_id = NULL`. Es tratado como un todo manual a partir de ese momento.

### 6.5 Sincronizacion durante pull de calendario
No aplica — Todos Core no interactuan con el subsistema de calendario.

### 6.6 Reabrir Task desde tablero
Si un attorney marca una Task como `in_progress` despues de `resolved`, el Todo Core vuelve a `pending`. El consultor vera el todo reactivarse en su agenda (comportamiento esperado).

---

## 7. Seguridad

- Los Todos Core heredan el `tenant_id` de la Task: el scope global `BelongsToTenant` garantiza aislamiento multi-tenant sin codigo adicional (Gotcha G1).
- El permiso `todo_core.create` protege la creacion via API. Los controllers deben validar con `$this->authorize('todo_core.create')` o usar policies existentes.
- `v-can="'todo_core.create'"` en el wizard oculta el paso de confirmacion del modal si el usuario no tiene el permiso (UX). El backend rechaza en cualquier caso (G5).
- La reasignacion masiva de todos en `CaseObserver` usa `update()` sin disparar observers individuales (mass update de Eloquent). Correcto y deseado — evita loops.

---

## 8. Performance

- `Task::insert($rows)` ya existe en `WorkflowInstantiator`. Los Todos Core se crean con `firstOrCreate` individual (N queries). Para casos con mas de 20 tasks, evaluar insert masivo con `upsert()` en iteracion futura.
- Indice compuesto `[tenant_id, is_core, status]` cubre la query principal de la lista de todos del consultor.
- `task_id` indexado individualmente para la lookup en observers.

---

## 9. Testing

### 9.1 Tests de servicio
- `tests/Unit/Services/Todo/TodoCoreServiceTest.php`
  - `test_creates_todo_core_for_task_with_template`
  - `test_is_idempotent_on_duplicate_call`
  - `test_does_not_create_todo_for_ad_hoc_task`
  - `test_calculates_due_date_from_reference_date`
  - `test_soft_deletes_pending_todo_on_remove`
  - `test_preserves_complete_todo_on_remove`
  - `test_reassigns_pending_todos_on_consultant_change`

### 9.2 Tests de observers
- `tests/Unit/Observers/TodoObserverTest.php`
  - `test_completing_todo_core_marks_task_as_resolved`
  - `test_reopening_todo_core_marks_task_as_in_progress`
  - `test_no_loop_when_syncing_from_task`
  - `test_does_not_sync_non_core_todos`

### 9.3 Tests de integracion
- `tests/Feature/Cases/CreateCaseGeneratesTodoCoreTest.php`
  - `test_wizard_creates_todos_core_for_all_templates`
  - `test_excluded_templates_do_not_generate_todos_core`
  - `test_todos_core_assigned_to_case_consultant`
- `tests/Feature/Cases/TaskTodoSyncTest.php`
  - `test_completing_task_closes_todo_core`
  - `test_completing_todo_closes_task`
  - `test_bidirectional_sync_no_infinite_loop`
- `tests/Feature/Cases/ConsultantReassignmentTest.php`
  - `test_pending_todos_reassigned_on_consultant_change`
  - `test_complete_todos_not_reassigned`

---

## 10. Plan de Implementacion (Checklist)

### Fase 1 — Base de datos
- [x] **1.1** Crear migracion `2026_05_02_000001_add_core_columns_to_todos_table.php` (task_id, task_template_id, is_core, source, softDeletes, indices, unique constraint).
- [x] **1.2** Crear migracion `2026_05_02_000002_seed_todo_core_permissions.php` (permisos todo_core.* para admin, attorney, paralegal).
- [x] **1.3** Ejecutar `php artisan migrate` y verificar esquema.

### Fase 2 — Backend: Modelos y Servicios
- [x] **2.1** Modificar `app/Models/Todo.php`: agregar SoftDeletes, fillable extendido, casts, relaciones `task()` y `taskTemplate()`, scope `scopeCore()`.
- [x] **2.2** Crear `app/Services/Todo/TodoCoreService.php` con metodos `createForTask()`, `createForCase()`, `syncFromTaskStatus()`, `removeForTask()`, `reassignPendingForCase()`.
- [x] **2.3** Modificar `app/Services/Workflow/WorkflowInstantiator.php`: llamar `TodoCoreService::createForCase()` dentro de la transaccion al final de `instantiate()`.

### Fase 3 — Backend: Observers
- [x] **3.1** Crear `app/Observers/TodoObserver.php` con flag `$syncingFromTask`, logica de sync Todo → Task, DB::transaction.
- [x] **3.2** Modificar `app/Observers/TaskWorkflowObserver.php`: agregar flag `$syncingFromTodo` y llamada a `TodoCoreService::syncFromTaskStatus()` en `updated()`.
- [x] **3.3** Modificar `app/Observers/CaseObserver.php`: agregar logica de reasignacion en `updated()` cuando cambia `assigned_to`.
- [x] **3.4** Modificar `app/Providers/AppServiceProvider.php`: registrar `Todo::observe(TodoObserver::class)`.

### Fase 4 — Backend: API
- [x] **4.1** Modificar `app/Http/Controllers/Api/CaseTaskController.php`: flags `create_todo_core` en `store()` y `cascade_todo` en `destroy()`.

### Fase 5 — Frontend: Tipos y Servicios
- [x] **5.1** Crear o extender `resources/js/src/types/todo.ts`: tipos `Todo`, `TodoSource`, `TodoCoreConfirmPayload`, `TodoCoreTaskSummary`.
- [x] **5.2** Extender `resources/js/src/services/todoService.ts`: metodos `listCore()`, `createCore()`, `removeCoreForTask()`.

### Fase 6 — Frontend: Modal y Composable
- [x] **6.1** Crear `resources/js/src/components/cases/TodoCoreConfirmationModal.vue`.
- [x] **6.2** Modificar `resources/js/src/composables/useCaseWizard.ts`: interceptar `submit()` para mostrar modal, exponer `showTodoCoreModal` y `confirmTodoCoreAndSubmit()`.

### Fase 7 — Frontend: Vistas
- [x] **7.1** Integrar `TodoCoreConfirmationModal` en `resources/js/src/views/cases/wizard/CaseWizard.vue` (escenario wizard_create).
- [x] **7.2** Integrar `TodoCoreConfirmationModal` en `resources/js/src/views/cases/edit.vue` (escenarios add_to_existing y remove_from_existing).
- [x] **7.3** `show.vue` — no aplica (vista de solo lectura, sin CRUD de tasks).

### Fase 8 — i18n
- [x] **8.1** Agregar 13 claves `todo_core.*` (FLAT) en `resources/js/src/locales/en.json`.
- [x] **8.2** Agregar 13 claves `todo_core.*` (FLAT) en `resources/js/src/locales/es.json`.
- [x] **8.3** Agregar 13 claves `todo_core.*` (FLAT) en los 14 idiomas restantes (fr, de, it, pt, ru, pl, tr, ja, zh, el, hu, da, sv, ae).

### Fase 9 — Testing
- [ ] **9.1** Crear `tests/Unit/Services/Todo/TodoCoreServiceTest.php` (7 tests).
- [ ] **9.2** Crear `tests/Unit/Observers/TodoObserverTest.php` (4 tests).
- [ ] **9.3** Crear `tests/Feature/Cases/CreateCaseGeneratesTodoCoreTest.php` (3 tests).
- [ ] **9.4** Crear `tests/Feature/Cases/TaskTodoSyncTest.php` (3 tests).
- [ ] **9.5** Crear `tests/Feature/Cases/ConsultantReassignmentTest.php` (2 tests).

### Fase 10 — Smoke Tests
- [ ] **10.1** Crear expediente via wizard con 3 tareas seleccionadas → verificar 3 Todos Core en la lista del consultor.
- [ ] **10.2** Completar Todo Core → verificar que la Task se marca como `resolved` en el tablero.
- [ ] **10.3** Completar Task desde tablero → verificar que el Todo Core se marca como `complete`.
- [ ] **10.4** Eliminar tarea del workflow (expediente existente) → modal aparece → elegir "Eliminar ambos" → verificar soft-delete del Todo.
- [ ] **10.5** Cambiar consultor del expediente → verificar que los todos pendientes cambian de asignado.
- [ ] **10.6** Ejecutar wizard dos veces (simulando reintento) → verificar que no se duplican Todos Core.

---

## 11. Notas de Despliegue

- Las migraciones son **no destructivas**: solo agregan columnas con defaults y nullable. No requieren downtime.
- El backfill de expedientes existentes (crear Todos Core para Tasks ya existentes) queda **fuera de este spec**. Los expedientes creados antes de la migracion no generaran Todos Core automaticamente. Se puede agregar un comando `php artisan todos:backfill-core` en iteracion posterior si se decide hacer el backfill.
- El unique constraint `(task_id, is_core)` puede fallar si ya existen Todos manuales con `task_id` seteado manualmente (improbable, pero verificar en staging antes de deploy).
