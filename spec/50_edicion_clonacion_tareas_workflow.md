---
tipo: spec
numero: 50
modulo: Workflows y Tareas
dominio: WFLOW
estado: DONE
tags: [spec, workflows, done]
relacionados:
  - "[[46_workflows_dinamicos_motor_etapas|Spec 46]]"
  - "[[49_todos_core_desde_workflow|Spec 49]]"
---
# Especificacion Tecnica: Edicion y Clonacion de Tareas Workflow en Expedientes

## Metadata
- **Fecha:** 2026-05-04
- **Version:** 1.0
- **Arquitecto:** Claude Code (Architect Agent)
- **Estado:** Implementado (Fase 10 — Smoke Tests pendientes)
- **Dependencias:** [[46_workflows_dinamicos_motor_etapas|Spec 46]] (Motor de Workflows Dinamicos), [[47_tablero_interactivo_tareas_expediente|Spec 47]] (Tablero Interactivo de Tareas), [[48_etapas_personalizadas_por_expediente|Spec 48]] (Etapas Personalizadas), [[49_todos_core_desde_workflow|Spec 49]] (Todos Core desde Workflow)

---

## 1. Resumen Ejecutivo

### Problema
En la vista de edicion de expedientes (`views/cases/edit.vue`), las tareas instanciadas desde plantillas de workflow (`task_template_id IS NOT NULL`, denominadas "core") solo permiten moverlas de etapa o eliminarlas. No es posible modificar su contenido (`subject`, `description`, `priority`, `assigned_to`), lo que obliga a eliminar y recrear la tarea manualmente cuando se necesita personalizar una instancia para el expediente especifico.

Adicionalmente, no existe mecanismo para duplicar una tarea core dentro del mismo expediente, lo cual es necesario para procesos paralelos (ej: dos solicitudes del mismo tipo para diferentes integrantes del expediente).

### Alcance
- **Edicion de campos core de `Task`:** permitir modificar `subject`, `description`, `priority`, `assigned_to` en tareas instanciadas desde plantilla, de forma localizada al expediente.
- **Clonacion de tareas workflow:** crear un nuevo registro `Task` a partir de uno existente, preservando `task_template_id` (para estadisticas) y registrando `cloned_from_task_id` (para auditoria).
- **Indicadores visuales:** badge para tareas core, highlight efimero para tarea recien clonada.
- **Activity log:** registrar edicion y clonacion en el historial del expediente.
- **i18n:** claves FLAT en los 16 idiomas del sistema.

### Fuera de alcance (v1)
- Edicion de campos operativos (`status`, `due_date`, `sort_order`) — ya existen endpoints dedicados.
- Propagacion de cambios al `TaskTemplate` maestro (intencionalmente bloqueado).
- Edicion masiva de tareas.
- Historial de versiones de la tarea (solo activity log basico).

---

## 2. Arquitectura

### 2.1 Modelo de datos actual (sin cambios de schema en v1)

El modelo `Task` (`app/Models/Task.php`) ya dispone de todos los campos necesarios:
- `subject`, `description`, `priority`, `assigned_to` — campos editables de la instancia
- `task_template_id` — FK al template maestro (NULL en tareas ad-hoc). Identifica tareas "core".
- `case_id`, `case_stage_id`, `workflow_stage_id` — relaciones de ubicacion

El `TaskResource` ya expone `is_adhoc` (derivado de `task_template_id === null`), por lo que el frontend ya puede distinguir tareas core de ad-hoc.

### 2.2 Decision: Nuevo campo `cloned_from_task_id`

Se agrega `cloned_from_task_id` (FK self-referenced, `nullOnDelete`) a la tabla `tasks` para:
- Trazabilidad de origen en clonacion
- Auditoria: quien clono, desde que tarea
- Estadisticas futuras de frecuencia de clonacion por template

La clave `is_adhoc` del Resource sigue siendo `task_template_id === null`. El clon PRESERVA `task_template_id` del original para que siga contando en el progreso del expediente.

### 2.3 Decision: No restringir edicion a "solo custom"

A diferencia de un approach que requeriria "clonar primero para editar", en este sistema la edicion de campos core de una `Task` es una operacion directa y segura porque:
- La `Task` es una instancia especifica del expediente — no es el template maestro (`TaskTemplate`).
- El template maestro (`task_templates` table) no es tocado en ningun caso.
- El historial queda en `activity_log` via `LogsActivity` trait ya presente en `Task`.

Por tanto, el endpoint de edicion es directo (`PATCH`), sin doble confirmacion.

### 2.4 Decision: El clon conserva `task_template_id`

El clon es una tarea core paralela — debe contribuir al calculo de progreso del expediente (que ya filtra por `whereNotNull('task_template_id')`). Esto es el comportamiento correcto para procesos paralelos.

### 2.5 Diagrama de flujo

```
USUARIO en tablero del expediente
    │
    ├── [Editar tarea] → TaskEditModal.vue
    │       │ PATCH /api/cases/{case}/workflow-tasks/{task}/update-core
    │       │ CaseTaskService::updateTaskCore()
    │       ▼
    │   Task actualizada (subject/description/priority/assigned_to)
    │   + activity_log: 'Edito campos core de tarea'
    │   + React: store.updateTaskInBoard(task) → re-render inmediato
    │
    └── [Clonar tarea] → confirmacion Swal (opcional)
            │ POST /api/cases/{case}/workflow-tasks/{task}/clone
            │ CaseTaskService::cloneTask()
            ▼
        Nueva Task (task_template_id copiado, cloned_from_task_id=original.id)
        sort_order = original.sort_order + 1 (insertar inmediatamente despues)
        + activity_log: 'Clono tarea desde template'
        + React: store.insertTaskAfter(originalId, clone) → re-render
        + highlight efimero 3s en la tarea clonada
```

---

## 3. Cambios en Base de Datos

### 3.1 Migracion: Columna `cloned_from_task_id` en `tasks`

**Archivo:** `database/migrations/2026_05_04_000001_add_cloned_from_task_id_to_tasks_table.php`

```php
public function up(): void
{
    Schema::table('tasks', function (Blueprint $table) {
        $table->unsignedBigInteger('cloned_from_task_id')
              ->nullable()
              ->after('task_template_id');

        $table->foreign('cloned_from_task_id')
              ->references('id')
              ->on('tasks')
              ->nullOnDelete();

        $table->index(['case_id', 'cloned_from_task_id']);
    });
}

public function down(): void
{
    Schema::table('tasks', function (Blueprint $table) {
        $table->dropForeign(['cloned_from_task_id']);
        $table->dropIndex(['case_id', 'cloned_from_task_id']);
        $table->dropColumn('cloned_from_task_id');
    });
}
```

### 3.2 Migracion: Permisos de edicion y clonacion

**Archivo:** `database/migrations/2026_05_04_000002_add_workflow_task_edit_clone_permissions.php`

Cumple Gotcha G4: permisos en migracion para sobrevivir `migrate:fresh` en produccion.

```php
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

public function up(): void
{
    $permissions = [
        'workflow_tasks.update_core',
        'workflow_tasks.clone',
    ];

    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    foreach (['super-admin', 'admin', 'attorney', 'paralegal'] as $roleName) {
        $role = Role::where('name', $roleName)->first();
        if ($role) {
            $role->givePermissionTo($permissions);
        }
    }
}

public function down(): void
{
    Permission::whereIn('name', [
        'workflow_tasks.update_core',
        'workflow_tasks.clone',
    ])->delete();
}
```

---

## 4. Cambios en Backend

### 4.1 Modelo `Task`

**Archivo:** `app/Models/Task.php`

Cambios:
- Agregar `cloned_from_task_id` al `$fillable`
- Agregar relacion `clonedFrom()`

```php
protected $fillable = [
    // ...existentes...
    'cloned_from_task_id',
];

public function clonedFrom(): BelongsTo
{
    return $this->belongsTo(Task::class, 'cloned_from_task_id');
}
```

### 4.2 Request: `UpdateWorkflowTaskCoreRequest`

**Archivo:** `app/Http/Requests/Workflow/UpdateWorkflowTaskCoreRequest.php`

```php
<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowTaskCoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('workflow_tasks.update_core');
    }

    public function rules(): array
    {
        return [
            'subject'     => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority'    => ['nullable', 'in:low,medium,high,urgent'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
```

### 4.3 Servicio `CaseTaskService` — nuevos metodos

**Archivo:** `app/Services/Case/CaseTaskService.php`

Agregar metodos `updateTaskCore()` y `cloneTask()`:

```php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Actualiza los campos core de una Task instanciada.
 * Aplica al expediente en curso. No toca TaskTemplate.
 */
public function updateTaskCore(ImmigrationCase $case, Task $task, array $data): Task
{
    return DB::transaction(function () use ($case, $task, $data) {
        $task->update([
            'subject'     => $data['subject'],
            'description' => $data['description'] ?? $task->description,
            'priority'    => $data['priority'] ?? $task->priority,
            'assigned_to' => $data['assigned_to'] ?? $task->assigned_to,
        ]);

        activity()
            ->causedBy(Auth::user())
            ->performedOn($case)
            ->withProperties([
                'task_id'   => $task->id,
                'subject'   => $task->subject,
                'is_core'   => $task->task_template_id !== null,
            ])
            ->log('Edito campos core de tarea');

        return $task->fresh(['assignee']);
    });
}

/**
 * Clona una Task dentro del mismo expediente y etapa.
 * La copia se inserta inmediatamente despues del original (sort_order + 1).
 * Preserva task_template_id para estadisticas de progreso.
 */
public function cloneTask(ImmigrationCase $case, Task $original): Task
{
    return DB::transaction(function () use ($case, $original) {
        // Empujar sort_order de tareas que vienen despues del original
        Task::where('case_id', $case->id)
            ->where('case_stage_id', $original->case_stage_id)
            ->where('sort_order', '>', $original->sort_order)
            ->orderByDesc('sort_order')
            ->increment('sort_order');

        $clone = $original->replicate(['created_at', 'updated_at', 'deleted_at']);
        $clone->cloned_from_task_id = $original->id;
        $clone->sort_order          = $original->sort_order + 1;
        $clone->status              = 'new';
        $clone->save();

        $this->recalculateCaseProgress($case);

        activity()
            ->causedBy(Auth::user())
            ->performedOn($case)
            ->withProperties([
                'cloned_task_id'    => $clone->id,
                'original_task_id'  => $original->id,
                'subject'           => $clone->subject,
                'case_stage_id'     => $clone->case_stage_id,
            ])
            ->log('Clono tarea desde template');

        return $clone->fresh(['assignee']);
    });
}
```

### 4.4 Controlador `CaseTaskController` — nuevos metodos

**Archivo:** `app/Http/Controllers/Api/CaseTaskController.php`

```php
use App\Http\Requests\Workflow\UpdateWorkflowTaskCoreRequest;

/**
 * PATCH /api/cases/{case}/workflow-tasks/{workflowTask}/update-core
 * Actualiza subject/description/priority/assigned_to de una tarea workflow.
 */
public function updateWorkflowTaskCore(
    UpdateWorkflowTaskCoreRequest $request,
    ImmigrationCase $case,
    Task $workflowTask
): JsonResponse {
    if ($workflowTask->case_id !== $case->id) {
        return response()->json(['message' => 'Task not found for this case.'], 404);
    }

    $this->authorize('update', $case);

    $task = $this->caseTaskService->updateTaskCore($case, $workflowTask, $request->validated());

    return response()->json([
        'data'    => new TaskResource($task),
        'message' => 'Task updated successfully.',
    ]);
}

/**
 * POST /api/cases/{case}/workflow-tasks/{workflowTask}/clone
 * Clona una tarea workflow dentro del mismo expediente y etapa.
 */
public function cloneWorkflowTask(
    Request $request,
    ImmigrationCase $case,
    Task $workflowTask
): JsonResponse {
    if ($workflowTask->case_id !== $case->id) {
        return response()->json(['message' => 'Task not found for this case.'], 404);
    }

    $this->authorize('update', $case);

    if (! $request->user()->can('workflow_tasks.clone')) {
        return response()->json(['message' => 'Forbidden.'], 403);
    }

    $clone = $this->caseTaskService->cloneTask($case, $workflowTask);

    return response()->json([
        'data' => new TaskResource($clone),
        'meta' => ['cloned_from_task_id' => $workflowTask->id],
        'message' => 'Task cloned successfully.',
    ], 201);
}
```

### 4.5 Rutas

**Archivo:** `routes/api.php` — dentro del grupo `auth:sanctum + tenant`

```php
Route::patch('/cases/{case}/workflow-tasks/{workflowTask}/update-core', [CaseTaskController::class, 'updateWorkflowTaskCore']);
Route::post('/cases/{case}/workflow-tasks/{workflowTask}/clone',        [CaseTaskController::class, 'cloneWorkflowTask']);
```

Agregar despues de las rutas existentes de workflow-tasks (linea ~144).

### 4.6 Resource `TaskResource`

**Archivo:** `app/Http/Resources/TaskResource.php`

Agregar campo `cloned_from_task_id`:

```php
'cloned_from_task_id' => $this->cloned_from_task_id,
```

---

## 5. Cambios en Frontend

### 5.1 Tipos TypeScript

**Archivo:** `resources/js/src/types/case.ts`

Agregar/actualizar interface `WorkflowTask`:

```typescript
export interface WorkflowTask {
  id: number;
  case_id: number;
  workflow_stage_id: number | null;
  case_stage_id: number | null;
  task_template_id: number | null;
  cloned_from_task_id: number | null;
  requester_id: number | null;
  assigned_to: number | null;
  subject: string;
  description: string | null;
  type: string;
  priority: 'low' | 'medium' | 'high' | 'urgent';
  status: 'new' | 'assigned' | 'in_progress' | 'resolved' | 'closed';
  sort_order: number;
  due_date: string | null;
  estimated_hours: string | null;
  actual_hours: string | null;
  is_adhoc: boolean;
  assignee: { id: number; name: string } | null;
  created_at: string;
  updated_at: string;
}

export interface UpdateWorkflowTaskCorePayload {
  subject: string;
  description?: string | null;
  priority?: 'low' | 'medium' | 'high' | 'urgent';
  assigned_to?: number | null;
}
```

### 5.2 Servicio frontend de tareas workflow

**Archivo:** `resources/js/src/services/caseTaskService.ts` (o donde resida el servicio de workflow tasks)

Agregar metodos:

```typescript
import api from '@/services/api';
import type { WorkflowTask, UpdateWorkflowTaskCorePayload } from '@/types/case';

export const caseTaskService = {
  // ...existentes...

  async updateTaskCore(
    caseId: number,
    taskId: number,
    payload: UpdateWorkflowTaskCorePayload
  ): Promise<WorkflowTask> {
    const { data } = await api.patch(
      `/cases/${caseId}/workflow-tasks/${taskId}/update-core`,
      payload
    );
    return data.data;
  },

  async cloneTask(
    caseId: number,
    taskId: number
  ): Promise<{ task: WorkflowTask; cloned_from_task_id: number }> {
    const { data } = await api.post(
      `/cases/${caseId}/workflow-tasks/${taskId}/clone`
    );
    return {
      task: data.data,
      cloned_from_task_id: data.meta.cloned_from_task_id,
    };
  },
};
```

### 5.3 Store de expedientes / tareas

**Archivo:** `resources/js/src/stores/case.ts`

Agregar:

```typescript
const recentlyClonedTaskId = ref<number | null>(null);

async function updateTaskCore(
  caseId: number,
  taskId: number,
  payload: UpdateWorkflowTaskCorePayload
): Promise<WorkflowTask> {
  const updated = await caseTaskService.updateTaskCore(caseId, taskId, payload);
  // Actualizar en el estado local del tablero
  updateTaskInStages(updated);
  return updated;
}

async function cloneTask(caseId: number, taskId: number): Promise<WorkflowTask> {
  const { task, cloned_from_task_id } = await caseTaskService.cloneTask(caseId, taskId);
  insertTaskAfterInStages(cloned_from_task_id, task);
  recentlyClonedTaskId.value = task.id;
  setTimeout(() => { recentlyClonedTaskId.value = null; }, 3000);
  return task;
}

// Helper: reemplaza la tarea actualizada en la estructura de etapas
function updateTaskInStages(updated: WorkflowTask): void {
  for (const stage of stages.value) {
    const idx = stage.tasks.findIndex(t => t.id === updated.id);
    if (idx !== -1) {
      stage.tasks[idx] = { ...stage.tasks[idx], ...updated };
      break;
    }
  }
}

// Helper: inserta el clon inmediatamente despues del original en la etapa
function insertTaskAfterInStages(originalId: number, clone: WorkflowTask): void {
  for (const stage of stages.value) {
    const idx = stage.tasks.findIndex(t => t.id === originalId);
    if (idx !== -1) {
      stage.tasks.splice(idx + 1, 0, clone);
      break;
    }
  }
}
```

### 5.4 Componente `WorkflowTaskEditModal.vue` (NUEVO)

**Archivo:** `resources/js/src/components/cases/WorkflowTaskEditModal.vue`

```vue
<script lang="ts" setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { WorkflowTask, UpdateWorkflowTaskCorePayload } from '@/types/case';

const props = defineProps<{
  modelValue: boolean;
  task: WorkflowTask | null;
  caseId: number;
  users: { id: number; name: string }[];
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void;
  (e: 'saved', task: WorkflowTask): void;
}>();

const { t } = useI18n();

const form = ref<UpdateWorkflowTaskCorePayload>({
  subject: '',
  description: null,
  priority: 'medium',
  assigned_to: null,
});

const saving = ref(false);
const errors = ref<Partial<UpdateWorkflowTaskCorePayload>>({});

watch(() => props.task, (task) => {
  if (task) {
    form.value = {
      subject:     task.subject,
      description: task.description,
      priority:    task.priority,
      assigned_to: task.assigned_to,
    };
    errors.value = {};
  }
}, { immediate: true });

function close() {
  emit('update:modelValue', false);
}

async function handleSave() {
  if (!props.task || !form.value.subject?.trim()) return;
  saving.value = true;
  errors.value = {};
  try {
    const { useCaseStore } = await import('@/stores/case');
    const store = useCaseStore();
    const updated = await store.updateTaskCore(props.caseId, props.task.id, form.value);
    emit('saved', updated);
    close();
  } catch (e: any) {
    if (e?.response?.data?.errors) {
      errors.value = e.response.data.errors;
    }
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <div
    v-if="modelValue && task"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    @click.self="close"
  >
    <div class="panel w-full max-w-lg">
      <div class="flex items-center justify-between border-b border-[#e0e6ed] px-5 py-4 dark:border-[#1b2e4b]">
        <h5 class="text-lg font-semibold">{{ t('workflow_task.edit_modal_title') }}</h5>
        <div class="flex items-center gap-2">
          <span
            v-if="!task.is_adhoc"
            class="badge bg-warning/20 text-warning"
          >
            {{ t('workflow_task.badge_core') }}
          </span>
          <span
            v-if="task.cloned_from_task_id"
            class="badge bg-info/20 text-info"
          >
            {{ t('workflow_task.badge_cloned') }}
          </span>
          <button type="button" class="text-gray-400 hover:text-gray-600" @click="close">
            <icon-x class="h-5 w-5" />
          </button>
        </div>
      </div>

      <div class="space-y-4 p-5">
        <!-- Subject -->
        <div>
          <label class="form-label">{{ t('workflow_task.field_subject') }} *</label>
          <input
            v-model="form.subject"
            type="text"
            class="form-input"
            :class="{ 'border-danger': errors.subject }"
          />
          <p v-if="errors.subject" class="mt-1 text-xs text-danger">{{ errors.subject }}</p>
        </div>

        <!-- Description -->
        <div>
          <label class="form-label">{{ t('workflow_task.field_description') }}</label>
          <textarea
            v-model="form.description"
            rows="4"
            class="form-textarea"
          />
        </div>

        <!-- Priority + Assigned to -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="form-label">{{ t('workflow_task.field_priority') }}</label>
            <select v-model="form.priority" class="form-select">
              <option value="low">{{ t('priority.low') }}</option>
              <option value="medium">{{ t('priority.medium') }}</option>
              <option value="high">{{ t('priority.high') }}</option>
              <option value="urgent">{{ t('priority.urgent') }}</option>
            </select>
          </div>
          <div>
            <label class="form-label">{{ t('workflow_task.field_assigned_to') }}</label>
            <select v-model="form.assigned_to" class="form-select">
              <option :value="null">— {{ t('common.unassigned') }} —</option>
              <option v-for="user in users" :key="user.id" :value="user.id">
                {{ user.name }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-3 border-t border-[#e0e6ed] px-5 py-4 dark:border-[#1b2e4b]">
        <button type="button" class="btn btn-outline-secondary" @click="close">
          {{ t('common.cancel') }}
        </button>
        <button
          type="button"
          class="btn btn-primary"
          :disabled="saving || !form.subject?.trim()"
          @click="handleSave"
        >
          <span v-if="saving" class="animate-spin mr-2">⟳</span>
          {{ t('common.save') }}
        </button>
      </div>
    </div>
  </div>
</template>
```

### 5.5 Integracion en la vista del tablero del expediente

**Archivo:** `resources/js/src/views/cases/edit.vue`

Cambios necesarios:
1. Importar `WorkflowTaskEditModal`
2. Agregar estado `showEditModal`, `editingTask`
3. En cada tarjeta de tarea: agregar botones de "Editar" y "Clonar"
4. Handler `openEditModal(task)` y `handleCloneTask(task)`
5. Highlight condicional en tarea recien clonada (`recentlyClonedTaskId`)

```vue
<!-- Boton editar + clonar en cada tarjeta de tarea -->
<div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
  <button
    v-can="'workflow_tasks.update_core'"
    type="button"
    class="btn btn-xs btn-outline-primary"
    @click.stop="openEditModal(task)"
    :title="$t('workflow_task.action_edit')"
  >
    <icon-pencil class="h-3.5 w-3.5" />
  </button>
  <button
    v-can="'workflow_tasks.clone'"
    type="button"
    class="btn btn-xs btn-outline-info"
    @click.stop="handleCloneTask(task)"
    :title="$t('workflow_task.action_clone')"
  >
    <icon-copy class="h-3.5 w-3.5" />
  </button>
</div>

<!-- Clase de highlight en la card -->
<div
  :class="[
    'task-card group relative',
    { 'ring-2 ring-success ring-offset-1 dark:ring-offset-black': store.recentlyClonedTaskId === task.id }
  ]"
>
```

```typescript
// Script setup additions
const showEditModal = ref(false);
const editingTask = ref<WorkflowTask | null>(null);

function openEditModal(task: WorkflowTask) {
  editingTask.value = task;
  showEditModal.value = true;
}

async function handleCloneTask(task: WorkflowTask) {
  try {
    await store.cloneTask(currentCase.value.id, task.id);
    // Toast de exito
    showToast(t('workflow_task.clone_success'), 'success');
  } catch {
    showToast(t('common.error_generic'), 'error');
  }
}

function handleTaskSaved(updated: WorkflowTask) {
  showToast(t('workflow_task.update_success'), 'success');
  showEditModal.value = false;
}
```

### 5.6 Claves i18n nuevas (FLAT, 16 idiomas)

Agregar en **todos** los archivos `resources/js/src/locales/*.json`:

**Ingles (en.json):**
```json
"workflow_task.edit_modal_title": "Edit Task",
"workflow_task.badge_core": "Core",
"workflow_task.badge_cloned": "Cloned",
"workflow_task.field_subject": "Title",
"workflow_task.field_description": "Description",
"workflow_task.field_priority": "Priority",
"workflow_task.field_assigned_to": "Assigned to",
"workflow_task.action_edit": "Edit task",
"workflow_task.action_clone": "Clone task",
"workflow_task.update_success": "Task updated successfully",
"workflow_task.clone_success": "Task cloned successfully",
"workflow_task.clone_confirm_title": "Clone task?",
"workflow_task.clone_confirm_text": "A copy will be created in the same stage."
```

**Espanol (es.json):**
```json
"workflow_task.edit_modal_title": "Editar Tarea",
"workflow_task.badge_core": "Core",
"workflow_task.badge_cloned": "Clonada",
"workflow_task.field_subject": "Título",
"workflow_task.field_description": "Descripción",
"workflow_task.field_priority": "Prioridad",
"workflow_task.field_assigned_to": "Asignado a",
"workflow_task.action_edit": "Editar tarea",
"workflow_task.action_clone": "Clonar tarea",
"workflow_task.update_success": "Tarea actualizada correctamente",
"workflow_task.clone_success": "Tarea clonada correctamente",
"workflow_task.clone_confirm_title": "¿Clonar tarea?",
"workflow_task.clone_confirm_text": "Se creará una copia en la misma etapa."
```

Traducir las 12 claves en los 14 idiomas restantes (fr, de, it, pt, ru, pl, tr, ja, zh, el, hu, da, sv, ae).

---

## 6. Manejo de Errores y Edge Cases

### 6.1 Colision de sort_order en clonacion concurrente
El `increment('sort_order')` dentro de `DB::transaction` con `orderByDesc` garantiza que no se produzcan valores duplicados antes de insertar el clon.

### 6.2 Tarea sin `case_stage_id`
Si la tarea no tiene etapa asignada, el clon tampoco la tendra. El `sort_order` se calcula sobre las tareas del mismo grupo (sin etapa).

### 6.3 Recursividad de clonacion
Un clon tiene `cloned_from_task_id` seteado, pero puede clonarse a si mismo. Esto es aceptable: el historial de origen siempre apunta a una generacion (no recursivo). No hay riesgo de bucle en DB.

### 6.4 Edicion de tarea en papelera
El endpoint usa `Task` (con soft-deletes). Las tareas en papelera no aparecen en el tablero (scope por defecto excluye soft-deleted), por lo que el botón de editar solo es visible para tareas activas.

### 6.5 Permisos insuficientes
`v-can` oculta los botones en la UI (UX). El backend verifica permisos con `$request->user()->can()` y `$this->authorize()` (seguridad real, Gotcha G5).

---

## 7. Seguridad

- `BelongsToTenant` en `Task` garantiza aislamiento multi-tenant sin codigo adicional (Gotcha G1).
- El clon hereda `tenant_id` del original a traves de `replicate()`.
- `workflow_tasks.update_core` y `workflow_tasks.clone` son permisos con nombres especificos para poder asignarlos/revocarlos de forma granular por rol.
- El controller verifica `$workflowTask->case_id !== $case->id` antes de cualquier operacion para evitar acceso cross-case.

---

## 8. Performance

- La clonacion usa `replicate()` (1 SELECT + 1 INSERT) + `increment()` en bloque. Para etapas con muchas tareas (>50), el increment es un solo `UPDATE` masivo: eficiente.
- La actualizacion del store es local (sin re-fetch completo del caso), garantizando UX fluida.
- El indice `[case_id, cloned_from_task_id]` cubre queries de auditoria futuras.

---

## 9. Testing

### 9.1 Tests de servicio
- `tests/Unit/Services/Case/CaseTaskServiceCloneTest.php`
  - `test_clone_inserts_after_original`
  - `test_clone_preserves_task_template_id`
  - `test_clone_sets_cloned_from_task_id`
  - `test_clone_starts_with_status_new`
  - `test_clone_increments_sort_order_of_subsequent_tasks`
  - `test_clone_respects_tenant_id`
- `tests/Unit/Services/Case/CaseTaskServiceUpdateCoreTest.php`
  - `test_update_core_changes_subject_description_priority_assigned_to`
  - `test_update_core_logs_activity`
  - `test_update_core_does_not_touch_task_template`

### 9.2 Tests de integracion (Feature)
- `tests/Feature/Cases/WorkflowTaskUpdateCoreTest.php`
  - `test_attorney_can_update_core_fields`
  - `test_paralegal_can_update_core_fields`
  - `test_client_cannot_update_core_fields`
  - `test_cannot_update_task_from_another_case`
  - `test_subject_is_required`
- `tests/Feature/Cases/WorkflowTaskCloneTest.php`
  - `test_attorney_can_clone_task`
  - `test_clone_is_inserted_after_original`
  - `test_clone_has_status_new`
  - `test_clone_preserves_template_reference`
  - `test_cannot_clone_task_from_another_case`

---

## 10. Plan de Implementacion (Checklist)

### Fase 1 — Base de datos
- [x] **1.1** Crear migracion `2026_05_04_000001_add_cloned_from_task_id_to_tasks_table.php`
- [x] **1.2** Crear migracion `2026_05_04_000002_add_workflow_task_edit_clone_permissions.php`
- [ ] **1.3** Ejecutar `php artisan migrate` y verificar esquema

### Fase 2 — Backend: Modelo y Request
- [x] **2.1** Agregar `cloned_from_task_id` a `$fillable` y relacion `clonedFrom()` en `Task.php`
- [x] **2.2** Crear `app/Http/Requests/Workflow/UpdateWorkflowTaskCoreRequest.php`

### Fase 3 — Backend: Servicio
- [x] **3.1** Agregar metodo `updateTaskCore()` en `CaseTaskService.php`
- [x] **3.2** Agregar metodo `cloneTask()` en `CaseTaskService.php`

### Fase 4 — Backend: Controller y Rutas
- [x] **4.1** Agregar metodo `updateWorkflowTaskCore()` en `CaseTaskController.php`
- [x] **4.2** Agregar metodo `cloneWorkflowTask()` en `CaseTaskController.php`
- [x] **4.3** Agregar rutas `PATCH .../update-core` y `POST .../clone` en `routes/api.php`
- [x] **4.4** Agregar `cloned_from_task_id` al `TaskResource`

### Fase 5 — Frontend: Tipos y Servicios
- [x] **5.1** Actualizar interface `WorkflowTask` en `types/workflow.ts` (agregar `cloned_from_task_id`)
- [x] **5.2** Agregar `UpdateWorkflowTaskCorePayload` en `types/workflow.ts`
- [x] **5.3** Agregar metodos `updateWorkflowTaskCore()` y `cloneWorkflowTask()` en `caseTaskService.ts`

### Fase 6 — Frontend: Store
- [x] **6.1** Agregar `recentlyClonedTaskId` ref con auto-clear (3s) en `useCaseTasksStore`
- [x] **6.2** Agregar acciones `updateTaskCore()` y `cloneTask()` en el store
- [x] **6.3** Logica de insercion y actualizacion in-place en el store

### Fase 7 — Frontend: Componente Modal
- [x] **7.1** Crear `resources/js/src/components/cases/board/WorkflowTaskEditModal.vue`

### Fase 8 — Frontend: Integracion en el tablero
- [x] **8.1** Integrar `WorkflowTaskEditModal` en `TaskBoard.vue` con estado y handlers
- [x] **8.2** Agregar opciones "Editar" y "Clonar" en el menu kebab de `TaskCard.vue`
- [x] **8.3** Handlers `onEditTask()`, `onCloneTask()`, `onTaskSaved()` en `TaskBoard.vue`
- [x] **8.4** Prop `highlighted` y clase `ring-2 ring-success` en `TaskCard.vue` via `StageColumn.vue`
- [x] **8.5** Badges "Core" y "Clonada" en las tarjetas de tarea (`TaskCard.vue`)

### Fase 9 — i18n
- [x] **9.1** Agregar 12 claves `workflow_task.*` (FLAT) en `en.json`
- [x] **9.2** Agregar 12 claves `workflow_task.*` (FLAT) en `es.json`
- [x] **9.3** Agregar 12 claves `workflow_task.*` (FLAT) en los 14 idiomas restantes

### Fase 10 — Testing y QA
- [ ] **10.1** Crear tests unitarios de servicio (clone + update_core)
- [ ] **10.2** Crear tests de feature (RBAC, validacion, cross-case)
- [ ] **10.3** Smoke test: editar tarea core → verificar cambios en tablero sin recargar
- [ ] **10.4** Smoke test: clonar tarea → verificar que aparece debajo del original con highlight
- [ ] **10.5** Smoke test: verificar que `cloned_from_task_id` queda en DB
- [ ] **10.6** Smoke test: verificar activity log con entradas de edicion y clonacion

---

## 11. Notas de Despliegue

- Las migraciones son no destructivas: solo agregan columna nullable y permisos.
- No requieren downtime ni backfill de datos existentes.
- Verificar en staging que los permisos `workflow_tasks.update_core` y `workflow_tasks.clone` quedan asignados a los roles correctos tras `php artisan migrate`.
