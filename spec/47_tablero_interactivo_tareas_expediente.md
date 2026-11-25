---
tipo: spec
numero: 47
modulo: Workflows y Tareas
dominio: WFLOW
estado: DONE
tags: [spec, workflows, done]
relacionados:
  - "[[46_workflows_dinamicos_motor_etapas|Spec 46]]"
  - "[[48_etapas_personalizadas_por_expediente|Spec 48]]"
---
# Especificacion Tecnica: Tablero Interactivo de Tareas en Edicion de Expediente

## Metadata
- **Fecha:** 2026-04-27
- **Version:** 1.0
- **Arquitecto:** Claude Code (Architect Agent)
- **Estado:** Implementado
- **Dependencias:** [[46_workflows_dinamicos_motor_etapas|Spec 46]] (Motor de Workflows Dinamicos), [[40_trash_papelera_reciclaje|Spec 40]] (Trash Papelera Reciclaje), [[21_tareas_tab_expediente|Spec 21]] (Tareas Tab Expediente)

---

## 1. Resumen Ejecutivo

### Problema
Tras implementar el motor de Workflows (Spec 46), las tareas de un expediente quedan estaticamente vinculadas a las etapas definidas por la plantilla del `case_type`. El consultor no puede:
- Eliminar tareas de plantilla que no aplican al caso particular.
- Agregar tareas ad-hoc no contempladas en la plantilla.
- Mover tareas entre etapas (ej. adelantar "Traduccion" de Etapa 2 a Etapa 1).
- Restaurar tareas eliminadas por error.

Esto fuerza al consultor a "vivir con la plantilla" en lugar de adaptarla al caso real.

### Alcance
- **Tareas ad-hoc:** crear tareas manuales (sin `task_template_id`) dentro de cualquier etapa del expediente.
- **Drag & Drop multi-lista:** mover tareas dentro de la misma etapa y entre etapas distintas, con persistencia inmediata.
- **Soft-delete + Papelera por expediente:** eliminar tareas envia a papelera; tab dedicada en `cases/edit.vue` permite restaurar.
- **API real-time con optimistic update:** cada movimiento se persiste inmediatamente; UI muestra estado optimista con rollback en error.
- **Componentizacion:** descomponer `WorkflowTaskChecklist` en `TaskBoard` + `StageColumn` + `TaskCard` + `NewTaskModal` + `TrashTab`.
- **Accesibilidad:** alternativa por menu kebab "Mover a etapa..." y atajos de teclado para usuarios sin raton.

### Fuera de alcance (v1)
- Edicion masiva de tareas (bulk select + bulk action).
- Templates personalizados por consultor (sigue siendo a nivel admin/tenant).
- Historial de movimientos visible en UI (queda registrado en `activity_log` pero sin vista dedicada).
- Notificaciones a otros usuarios al mover/eliminar tareas asignadas.

---

## 2. Arquitectura

### 2.1 Diagrama de Componentes

```
EDIT VIEW (Vue SPA)
    |
    v
[cases/edit.vue]
    |-- TaskBoard.vue              (contenedor multi-columna)
    |   |-- StageColumn.vue        (una por stage del workflow_snapshot)
    |   |   |-- TaskCard.vue       (drag handle, checkbox, kebab menu)
    |   |   |-- AddTaskButton      (abre NewTaskModal)
    |   |-- NewTaskModal.vue       (form: subject, description, type, priority, due_date)
    |   |-- TrashTab.vue           (lista soft-deleted del caso)
    |
    v (REST API)
[Laravel API Layer]
    |-- CaseTaskController (extender)
    |   |-- moveWorkflowTask        PATCH /cases/{case}/workflow-tasks/{task}/move
    |   |-- createWorkflowTask      POST  /cases/{case}/workflow-tasks
    |   |-- deleteWorkflowTask      DELETE /cases/{case}/workflow-tasks/{task}
    |   |-- restoreWorkflowTask     POST  /cases/{case}/workflow-tasks/{task}/restore
    |   |-- listTrashedTasks        GET   /cases/{case}/workflow-tasks/trashed
    |
    v
[Application Services]
    |-- CaseTaskService (extender)
    |   |-- moveTask                (transaccional, re-indexa source y target)
    |   |-- createAdHocTask         (sin task_template_id)
    |   |-- softDeleteTask          (recalcula progress)
    |   |-- restoreTask             (recalcula progress)
    |-- TrashService (extender)
    |   |-- agregar tipo 'task'
    |
    v
[Domain Models]
    |-- Task (sin cambios estructurales: ya tiene SoftDeletes, sort_order, workflow_stage_id)
    |
    v
[Cross-cutting]
    |-- Pinia store useCaseTasksStore (optimistic updates)
    |-- Activity log en cada move/create/delete/restore
```

### 2.2 Flujo: Mover tarea entre etapas

```
1. Usuario arrastra TaskCard de StageColumn A a StageColumn B en posicion 2
2. VueDraggable @end -> useCaseTasksStore.moveTask(taskId, sourceStageId, targetStageId, targetIndex)
3. Store mutua tasksByStage inmediatamente (UI cambia)
4. Store marca task como _pending=true (overlay spinner)
5. PATCH /api/cases/{case}/workflow-tasks/{task}/move
   { source_stage_id, target_stage_id, target_index }
6. Backend (CaseTaskService::moveTask):
   a. Validar que target_stage_id pertenece al case_type del case
   b. DB::transaction:
      - Update task: workflow_stage_id = target_stage_id
      - Re-indexar source_stage_id (compactar sort_order tras hueco)
      - Re-indexar target_stage_id (insertar en target_index, desplazar resto)
   c. activity()->log('Movio tarea X de Etapa A a Etapa B')
7. Response 200 -> store quita _pending
8. Response error -> store revierte mutacion + toast
```

### 2.3 Flujo: Crear tarea ad-hoc

```
1. Usuario click "+ Nueva tarea" dentro de StageColumn
2. NewTaskModal abre con form:
   - subject (requerido), description, type, priority, due_date
   - assigned_to: dropdown con usuarios que poseen el rol consultor O apoyo
     (permission=tasks.create, exclude_roles=admin,user). Ver sección 2.3.1.
3. Submit -> POST /api/cases/{case}/workflow-tasks
   { workflow_stage_id, subject, description, type, priority, due_date, assigned_to }
4. Backend:
   a. Authorize update sobre el case
   b. Validar que workflow_stage_id pertenece al case_type
   c. Crear Task con task_template_id=NULL, requester_id=Auth::id(),
      sort_order = max + 1 dentro de la etapa
   d. Recalcular progress del case
5. Response 201 -> store agrega task al final de la columna
6. Modal cierra
```

#### 2.3.1 Regla de Asignación ("Asignar a")

El dropdown "Asignar a" en `NewTaskModal` y `WorkflowTaskEditModal` debe listar todos los
usuarios activos del tenant que puedan trabajar tareas de expediente — actualmente los roles
`consultor` y `apoyo`. La consulta usa:

```
GET /users/staff?permission=tasks.create&exclude_roles=admin,user
```

- `permission=tasks.create` captura todos los roles con esa capacidad (consultor, apoyo).
- `exclude_roles=admin,user` evita que aparezcan gestores y cuentas sin rol de trabajo.
- No duplica registros si un usuario tiene múltiples roles (el endpoint deduplica por `users.id`).
- En `TaskBoard.vue`, la lista se carga una sola vez en `onMounted` y se pasa como prop
  `:users="staffUsers"` a `WorkflowTaskEditModal`.
- En `NewTaskModal.vue`, la lista se carga lazy al abrir el modal por primera vez.

### 2.4 Flujo: Eliminar y restaurar

```
1. Usuario click kebab -> "Eliminar"
2. Si task tiene status != 'new' o description: SweetAlert2 confirmacion
   Si task vacia: ejecuta directo + toast "Deshacer" 5s (no implementado en v1, solo confirmacion estandar)
3. DELETE /api/cases/{case}/workflow-tasks/{task}
   - Backend: $task->delete() (soft); recalcular progress
4. Store remueve task de tasksByStage
5. Tab "Papelera" del expediente (TrashTab):
   - GET /api/cases/{case}/workflow-tasks/trashed
   - Lista cards con boton "Restaurar"
6. Restore: POST /api/cases/{case}/workflow-tasks/{task}/restore
   - Backend: $task->restore(); si workflow_stage_id existe en workflow_snapshot, mantener;
     si stage fue eliminada, set workflow_stage_id=NULL
   - sort_order = max + 1 de su etapa (al final)
   - Recalcular progress
7. Store agrega task de vuelta a la columna
```

---

## 3. Cambios en Base de Datos

**Sin migraciones nuevas requeridas.** La tabla `tasks` ya tiene:
- `workflow_stage_id` (FK nullable, nullOnDelete)
- `task_template_id` (FK nullable; NULL == ad-hoc)
- `sort_order` (smallint default 0)
- `deleted_at` (SoftDeletes ya activado en el modelo)
- Indice compuesto `(case_id, workflow_stage_id, sort_order)`

**Diferenciacion ad-hoc vs plantilla:**
- `task_template_id IS NOT NULL` -> instanciada de plantilla
- `task_template_id IS NULL` -> tarea ad-hoc del expediente

---

## 4. Cambios en Backend

### 4.1 Form Requests

**`app/Http/Requests/Workflow/MoveTaskRequest.php`** (NUEVO)

```php
public function rules(): array
{
    $case = $this->route('case');
    return [
        'source_stage_id' => ['required', 'integer', 'exists:workflow_stages,id'],
        'target_stage_id' => [
            'required', 'integer',
            Rule::exists('workflow_stages', 'id')
                ->where('case_type_id', $case->case_type_id),
        ],
        'target_index' => ['required', 'integer', 'min:0'],
    ];
}
```

**`app/Http/Requests/Workflow/CreateAdHocTaskRequest.php`** (NUEVO)

```php
public function rules(): array
{
    $case = $this->route('case');
    return [
        'workflow_stage_id' => [
            'required', 'integer',
            Rule::exists('workflow_stages', 'id')
                ->where('case_type_id', $case->case_type_id),
        ],
        'subject'     => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
        'type'        => ['required', 'in:filing,document,other'],
        'priority'    => ['required', 'in:low,medium,high,urgent'],
        'due_date'    => ['nullable', 'date'],
        'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
    ];
}
```

### 4.2 Service Layer

**`app/Services/Case/CaseTaskService.php`** (extender)

```php
public function moveTask(ImmigrationCase $case, Task $task, int $sourceStageId, int $targetStageId, int $targetIndex): Task
{
    return DB::transaction(function () use ($case, $task, $sourceStageId, $targetStageId, $targetIndex) {
        // 1. Mover la task a target stage
        $task->workflow_stage_id = $targetStageId;
        $task->save();

        // 2. Re-indexar target stage (insertar en targetIndex)
        $targetTasks = Task::where('case_id', $case->id)
            ->where('workflow_stage_id', $targetStageId)
            ->where('id', '!=', $task->id)
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->values();

        $targetIndex = min($targetIndex, $targetTasks->count());
        $targetTasks->splice($targetIndex, 0, [$task]);

        foreach ($targetTasks as $i => $t) {
            Task::where('id', $t->id)->update(['sort_order' => $i]);
        }

        // 3. Re-indexar source stage si es distinta
        if ($sourceStageId !== $targetStageId) {
            $sourceTasks = Task::where('case_id', $case->id)
                ->where('workflow_stage_id', $sourceStageId)
                ->orderBy('sort_order')->orderBy('id')
                ->get();
            foreach ($sourceTasks as $i => $t) {
                Task::where('id', $t->id)->update(['sort_order' => $i]);
            }
        }

        activity()
            ->causedBy(Auth::user())
            ->performedOn($case)
            ->withProperties([
                'task_id'         => $task->id,
                'from_stage_id'   => $sourceStageId,
                'to_stage_id'     => $targetStageId,
                'target_index'    => $targetIndex,
            ])
            ->log('Movio tarea entre etapas');

        return $task->fresh();
    });
}

public function createAdHocTask(ImmigrationCase $case, array $data): Task
{
    return DB::transaction(function () use ($case, $data) {
        $maxOrder = Task::where('case_id', $case->id)
            ->where('workflow_stage_id', $data['workflow_stage_id'])
            ->max('sort_order') ?? -1;

        $task = Task::create([
            'tenant_id'         => $case->tenant_id,
            'case_id'           => $case->id,
            'workflow_stage_id' => $data['workflow_stage_id'],
            'task_template_id'  => null,
            'requester_id'      => Auth::id(),
            'assigned_to'       => $data['assigned_to'] ?? $case->assigned_to,
            'subject'           => $data['subject'],
            'description'       => $data['description'] ?? null,
            'type'              => $data['type'],
            'priority'          => $data['priority'],
            'status'            => 'new',
            'due_date'          => $data['due_date'] ?? null,
            'sort_order'        => $maxOrder + 1,
        ]);

        $this->recalculateCaseProgress($case);
        return $task;
    });
}

public function softDeleteTask(ImmigrationCase $case, Task $task): void
{
    DB::transaction(function () use ($case, $task) {
        $task->delete();
        $this->recalculateCaseProgress($case);
        activity()->causedBy(Auth::user())->performedOn($case)
            ->withProperties(['task_id' => $task->id, 'subject' => $task->subject])
            ->log('Envio tarea a papelera');
    });
}

public function restoreTask(ImmigrationCase $case, int $taskId): Task
{
    return DB::transaction(function () use ($case, $taskId) {
        $task = Task::onlyTrashed()->where('case_id', $case->id)->findOrFail($taskId);
        $task->restore();

        $maxOrder = Task::where('case_id', $case->id)
            ->where('workflow_stage_id', $task->workflow_stage_id)
            ->max('sort_order') ?? -1;
        $task->update(['sort_order' => $maxOrder + 1]);

        $this->recalculateCaseProgress($case);
        return $task->fresh();
    });
}

private function recalculateCaseProgress(ImmigrationCase $case): void
{
    $total = Task::where('case_id', $case->id)->whereNotNull('task_template_id')->count();
    $done  = Task::where('case_id', $case->id)
        ->whereNotNull('task_template_id')
        ->whereIn('status', ['resolved', 'closed'])
        ->count();
    $case->updateQuietly([
        'progress' => $total ? (int) round($done / $total * 100) : 0,
    ]);
}
```

### 4.3 Controller

**`app/Http/Controllers/Api/CaseTaskController.php`** (extender)

Metodos nuevos (delegan al service): `moveWorkflowTask`, `createWorkflowTask`, `deleteWorkflowTask`, `restoreWorkflowTask`, `listTrashedWorkflowTasks`.

Cada uno:
- `$this->authorize('update', $case);`
- Valida con Form Request
- Llama al service
- Retorna `TaskResource` o coleccion

### 4.4 Rutas

**`routes/api.php`** (extender bloque `auth:sanctum + tenant`)

```php
Route::post('/cases/{case}/workflow-tasks', [CaseTaskController::class, 'createWorkflowTask']);
Route::patch('/cases/{case}/workflow-tasks/{workflowTask}/move', [CaseTaskController::class, 'moveWorkflowTask']);
Route::delete('/cases/{case}/workflow-tasks/{workflowTask}', [CaseTaskController::class, 'deleteWorkflowTask']);
Route::post('/cases/{case}/workflow-tasks/{workflowTaskId}/restore', [CaseTaskController::class, 'restoreWorkflowTask']);
Route::get('/cases/{case}/workflow-tasks/trashed', [CaseTaskController::class, 'listTrashedWorkflowTasks']);
```

> Nota: el endpoint `reorder` existente queda deprecado en favor de `move`, pero se mantiene por compatibilidad.

---

## 5. Cambios en Frontend

### 5.1 Componentes nuevos

```
resources/js/src/components/cases/board/
├── TaskBoard.vue           # Contenedor; recibe tasks[], stages[], caseId, editable
├── StageColumn.vue         # Una por stage; envuelve VueDraggable group="case-tasks"
├── TaskCard.vue            # Card con drag handle, checkbox, kebab menu (Mover/Eliminar)
├── NewTaskModal.vue        # Modal con form (subject, description, type, priority, due_date)
└── TrashTab.vue            # Tab papelera del expediente
```

**Lineamientos graficos (consistencia con el proyecto):**
- Colores Tailwind: usar tokens del proyecto (`primary`, `success`, `warning`, `danger`, `info`).
- Cards con `rounded-md`, `shadow-sm`, `border` `border-gray-200 dark:border-gray-700`.
- Hover state: `hover:shadow-md transition`.
- Stage column header con dot de color (matchea `stage.color`).
- Soporte modo oscuro (`dark:` prefix).
- Drag handle: icono `IconGrip` o similar a la izquierda de la card, cursor `grab`/`grabbing`.

### 5.2 Pinia Store

**`resources/js/src/stores/useCaseTasksStore.ts`** (NUEVO)

```typescript
state: () => ({
    tasksByStage: {} as Record<number, WorkflowTask[]>,
    trashedTasks: [] as WorkflowTask[],
    pendingTaskIds: new Set<number>(),
})

actions:
- loadTasks(caseId, tasks)         // hidrata desde currentCase.tasks
- moveTask(caseId, taskId, sourceStageId, targetStageId, targetIndex)
    // 1. mutar local optimista
    // 2. pendingTaskIds.add(taskId)
    // 3. await API
    // 4. ok: pendingTaskIds.delete; error: rollback + toast
- createTask(caseId, payload)
- deleteTask(caseId, taskId)
- restoreTask(caseId, taskId)
- loadTrash(caseId)
```

### 5.3 Service capa frontend

**`resources/js/src/services/caseTaskService.ts`** (NUEVO o extender)

```typescript
moveWorkflowTask(caseId, taskId, payload)
createWorkflowTask(caseId, payload)
deleteWorkflowTask(caseId, taskId)
restoreWorkflowTask(caseId, taskId)
listTrashedTasks(caseId)
```

### 5.4 Integracion en edit.vue

- Reemplazar `<WorkflowTaskChecklist :editable="true">` por `<TaskBoard :case-id="..." :stages="..." :tasks="..." />`.
- Mantener `WorkflowTaskChecklist` para `cases/show.vue` (vista readonly).
- Agregar tab "Papelera" en el sistema de tabs existente del edit.

### 5.5 Accesibilidad

- Cada `TaskCard` tiene menu kebab con: "Mover a etapa..." (dropdown con todas las stages disponibles).
- Atajos teclado: `Cmd/Ctrl + ↑/↓` para mover dentro de la columna; menu para entre columnas.
- ARIA: `aria-grabbed`, `aria-dropeffect`, anuncio `aria-live` polite tras mover.
- Foco visible (`focus-visible:ring-2 ring-primary`).

### 5.6 i18n (claves nuevas, FLAT)

Agregar en los 16 locales (`es.json`, `en.json`, ..., `ae.json`):
- `cases.task.add_new`
- `cases.task.move_to_stage`
- `cases.task.confirm_delete_title`
- `cases.task.confirm_delete_text`
- `cases.task.deleted_to_trash`
- `cases.task.restored`
- `cases.task.empty_stage`
- `cases.task.trash_tab`
- `cases.task.trash_empty`
- `cases.task.modal_title_new`
- `cases.task.modal_field_subject`
- `cases.task.modal_field_description`
- `cases.task.modal_field_type`
- `cases.task.modal_field_priority`
- `cases.task.modal_field_due_date`
- `cases.task.modal_field_assigned_to`
- `cases.task.move_failed`

---

## 6. Manejo de Errores y Edge Cases

| Caso | Manejo |
|---|---|
| Mover a stage que no pertenece al case_type | 422 ValidationException -> toast "Etapa invalida" |
| Carrera entre dos consultores moviendo la misma task | `lockForUpdate` en transaccion; segundo update ve estado nuevo |
| Stage eliminada con tasks ad-hoc dentro | FK `nullOnDelete` ya configurado; UI muestra "sin etapa" |
| API falla durante move | Store revierte mutacion local; toast con accion "Reintentar" |
| Restaurar task cuya stage fue eliminada | Set `workflow_stage_id = null`; UI la lista en grupo "Sin etapa" |
| Tarea ad-hoc (sin template) en calculo de progress | NO se cuenta (solo plantilla); ya filtrado por `whereNotNull('task_template_id')` |
| Eliminar task que esta en `status = closed` | Permitido; warning previo en SweetAlert2 |
| Crear tarea con due_date pasada | Permitido (caso valido: registro retroactivo); sin warning |
| target_index mayor que count de la columna | Backend hace `min($targetIndex, count)`, va al final |

---

## 7. Seguridad

- **Authorization:** `$this->authorize('update', $case)` en cada endpoint. La policy de `Case` ya valida tenant + permission.
- **Tenant isolation:** Task tiene `BelongsToTenant`; queries filtran automaticamente.
- **Validacion de pertenencia:** `target_stage_id` debe existir Y pertenecer al `case_type_id` del case (regla custom en Form Request).
- **No exposicion de templates:** eliminar una task **nunca** toca `task_templates`. El FK es `nullOnDelete` desde el lado contrario, no desde Task.
- **CSRF + Sanctum:** ya cubierto por middleware existente.

---

## 8. Performance

- **N de tareas por etapa:** tipicamente 5-15, maximo esperado 30. Re-indexar 30 filas con UPDATE individuales = ~30ms.
- **Indice compuesto:** `(case_id, workflow_stage_id, sort_order)` ya existe; query plan optimal.
- **Optimistic update:** UI no espera al servidor; percepcion instantanea.
- **Sin polling:** no hay sincronizacion entre clientes (v1). Si se requiere, agregar Echo/Broadcasting en v2.

---

## 9. Testing

### Backend (Feature tests en `tests/Feature/Workflow/`)

`TaskBoardOperationsTest.php`:
1. `test_can_create_ad_hoc_task` - POST crea task con `task_template_id=null`.
2. `test_create_ad_hoc_task_validates_stage_belongs_to_case_type` - 422 si stage no pertenece.
3. `test_can_move_task_within_same_stage` - reorder intra-etapa.
4. `test_can_move_task_to_different_stage` - inter-etapa, source y target re-indexados.
5. `test_move_task_validates_target_stage` - 422 si target_stage_id de otro case_type.
6. `test_can_soft_delete_task_and_restore` - delete + restore funciona; progress se recalcula.
7. `test_progress_excludes_ad_hoc_tasks` - tareas ad-hoc no afectan % progreso.
8. `test_listing_trashed_tasks_returns_only_soft_deleted` - GET /trashed.
9. `test_cannot_move_task_from_other_tenant_case` - 404 ([[BelongsToTenant]] scope).

### Frontend (manual + visual)
- D&D intra y entre columnas con persistencia.
- Optimistic update con rollback al simular error de red.
- Modal de creacion validando campos requeridos.
- SweetAlert2 confirmacion en eliminacion.
- Tab papelera con restore funcional.
- Modo oscuro consistente.
- Soporte teclado: Tab navegacion, menu kebab "Mover a..." accesible.

---

## 10. Plan de Implementacion (Checklist)

### Fase 1 — Backend

- [x] **1.1** Crear `MoveTaskRequest` con validacion de stage->case_type.
- [x] **1.2** Crear `CreateAdHocTaskRequest`.
- [x] **1.3** Extender `CaseTaskService` con metodos `moveTask`, `createAdHocTask`, `softDeleteTask`, `restoreTask`.
- [x] **1.4** Extender `CaseTaskController` con `moveWorkflowTask`, `createWorkflowTask`, `deleteWorkflowTask`, `restoreWorkflowTask`, `listTrashedWorkflowTasks`.
- [x] **1.5** Agregar 5 rutas nuevas en `routes/api.php`.
- [x] **1.6** Crear tests Feature en `tests/Feature/Workflow/TaskBoardOperationsTest.php`.
- [x] **1.7** Verificar que `php artisan test --filter=Workflow` sigue pasando.

### Fase 2 — Frontend componentes

- [x] **2.1** Crear `useCaseTasksStore` (Pinia) con optimistic update + rollback.
- [x] **2.2** Extender `caseTaskService.ts` con los 5 endpoints nuevos.
- [x] **2.3** Crear `TaskCard.vue` (drag handle, checkbox, kebab menu).
- [x] **2.4** Crear `StageColumn.vue` (envuelve VueDraggable group "case-tasks").
- [x] **2.5** Crear `TaskBoard.vue` (contenedor multi-columna).
- [x] **2.6** Crear `NewTaskModal.vue` (form modal).
- [x] **2.7** Crear `TrashTab.vue` (lista papelera + restore).
- [x] **2.8** Reemplazar `WorkflowTaskChecklist` editable por `TaskBoard` en `cases/edit.vue`.
- [x] **2.9** Agregar tab "Papelera" al sistema de tabs de `edit.vue`.

### Fase 3 — i18n y Accesibilidad

- [x] **3.1** Agregar las 17 claves nuevas (FLAT) en los 16 locales.
- [x] **3.2** Implementar menu kebab "Mover a etapa..." en `TaskCard`.
- [x] **3.3** Agregar atajos teclado `Cmd/Ctrl+↑/↓` para reordenar.
- [x] **3.4** Agregar ARIA attributes y `aria-live` regions.

### Fase 4 — QA y refinamiento

- [x] **4.1** Verificar modo oscuro consistente.
- [x] **4.2** Verificar que los tests del Spec 46 siguen pasando.
- [x] **4.3** Validar visual con `npm run dev` (manual).
- [x] **4.4** Smoke test: crear caso, mover tareas, eliminar, restaurar.

---

## 11. Migracion y Despliegue

- **Sin migraciones de BD nuevas.**
- Sin breaking changes en API existente (endpoint `reorder` se mantiene).
- Frontend: descartar componente viejo `WorkflowTaskChecklist` solo en `edit.vue`; el componente sigue usandose en `show.vue`.
- Sin downtime requerido. Deploy estandar: `php artisan optimize` + `npm run build`.
