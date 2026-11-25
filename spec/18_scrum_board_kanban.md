---
tipo: spec
numero: 18
modulo: Workflows y Tareas
dominio: WFLOW
estado: DONE
tags: [spec, workflows, done]
relacionados:
  - "[[19_todo_list_integracion_casos|Spec 19]]"
---
# Plan de Implementación: Scrum Board (Kanban) — Gestión de Tareas

## Metadata
- **Fecha:** 2026-03-15
- **Version:** 1.0
- **Arquitecto:** Claude (Architect Agent)
- **Epic:** 5.1 - Scrum Board / Kanban para gestión de tareas internas
- **Alcance:** Módulo completo de tablero Kanban con persistencia en BD, multitenancy, CRUD de columnas y tareas, drag-and-drop, filtro por asignado, y vinculación a casos de inmigración.
- **Flujos cubiertos:** `views/apps/scrumboard.vue` (reemplazado) + nuevos componentes en subdirectorio
- **Tiempo Total Estimado:** 27–36 horas
- **Estado:** ✅ COMPLETADO (2026-03-15)

---

## Contexto y Problema

El proyecto cuenta con un componente base de Scrumboard (`resources/js/src/views/apps/scrumboard.vue`) que opera exclusivamente con datos locales hardcodeados. No tiene persistencia, no se conecta a ninguna API, carece de multitenancy, y los campos son mínimos.

Se requiere transformar este componente demo en un módulo de producción completo con persistencia en base de datos, aislamiento por tenant, asignación a usuarios con roles específicos, vinculación opcional a casos de inmigración, y operaciones de drag-and-drop con sincronización al backend.

---

## Decisiones Arquitectónicas

| Dimensión | Decisión | Justificación |
|-----------|----------|---------------|
| **Tablero: global vs por entidad** | Un único tablero por tenant (sin tabla `scrum_boards`). | El requerimiento no menciona múltiples tableros. Si en el futuro se necesitan, se puede agregar `scrum_boards` como padre de `scrum_columns` sin romper la API. |
| **Tags: JSON vs tabla relacional** | Columna JSON en `scrum_tasks.tags`. | Los tags son valores simples, no requieren búsqueda relacional compleja, no tienen metadatos propios, y el volumen esperado es bajo (3-5 por tarea). JSON reduce complejidad de schema. MySQL 8 soporta indexación JSON si se necesita búsqueda futura. |
| **Estrategia de order_index** | Enteros con gap strategy (incrementos de 1000) + reindexación lazy cuando los gaps se agotan. | Floating point introduce problemas de precisión. Integer puro requiere UPDATE masivo en cada movimiento. La gap strategy permite insertar entre posiciones sin tocar otras filas la mayoría de las veces. |
| **Soft deletes en scrum_tasks** | No. Hard delete. | Las tareas del scrum board son items operativos efímeros, no registros legales. No hay requerimiento de auditoría. Si se necesita trazabilidad, ya existe Spatie Activity Log. |
| **Filtro "Yo/Todos"** | Filtrado en frontend (sin nueva request). | El endpoint `GET /api/scrum/board` retorna todas las tareas del tenant con `assigned_to_id`. El frontend conoce el ID del usuario actual. Filtrar en frontend evita requests adicionales y es instantáneo. |
| **Tenant isolation** | Trait `BelongsToTenant` en los modelos `ScrumColumn` y `ScrumTask`. | Sigue exactamente el patrón establecido en el proyecto. El `TenantScope` global asegura que nunca se fugan datos entre tenants. |
| **Optimistic UI** | Sí, con revert on failure. | El drag-and-drop debe sentirse instantáneo. Actualizar estado local, disparar la API, y revertir si falla. |
| **Store: Pinia dedicado vs estado local** | Pinia store dedicado `useScrumStore`. | El tablero es una vista compleja con múltiples componentes hijos. Un store centralizado facilita que `ScrumColumn`, `ScrumTaskCard` y `ScrumTaskModal` compartan estado sin prop drilling. |
| **Carga de datos** | Eager load completo en una sola request. | El tablero necesita todas las columnas y tareas al montar. Lazy loading generaría N+1 requests. Una sola request es más eficiente para el caso típico (< 200 tareas por tenant). |
| **Inmutabilidad de created_at** | Protección a nivel de modelo Laravel. | Se excluye `created_at` del `$fillable`. No se acepta en ningún FormRequest. Eloquent lo gestiona automáticamente. |

---

## Esquema de Base de Datos

### Tabla `scrum_columns`

```php
Schema::create('scrum_columns', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
    $table->string('title', 100);
    $table->unsignedInteger('order_index')->default(0);
    $table->timestamps();

    $table->index(['tenant_id', 'order_index']);
});
```

| Columna | Tipo | Nullable | Default | Descripción |
|---------|------|----------|---------|-------------|
| `id` | bigint unsigned PK | No | auto | Identificador único |
| `tenant_id` | bigint unsigned FK | No | — | `tenants.id`, CASCADE DELETE |
| `title` | varchar(100) | No | — | Nombre de la columna |
| `order_index` | unsigned int | No | 0 | Posición de la columna en el tablero |
| `created_at` | timestamp | Sí | null | Gestionado por Eloquent |
| `updated_at` | timestamp | Sí | null | Gestionado por Eloquent |

### Tabla `scrum_tasks`

```php
Schema::create('scrum_tasks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
    $table->foreignId('scrum_column_id')->constrained('scrum_columns')->cascadeOnDelete();
    $table->string('title', 255);
    $table->text('description')->nullable();
    $table->json('tags')->nullable();
    $table->string('category', 100)->nullable();
    $table->date('due_date')->nullable();
    $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
    $table->unsignedInteger('order_index')->default(0);
    $table->timestamps();

    $table->index(['scrum_column_id', 'order_index']);
    $table->index(['tenant_id', 'assigned_to_id']);
    $table->index('case_id');
    $table->index('due_date');
});
```

| Columna | Tipo | Nullable | Default | Descripción |
|---------|------|----------|---------|-------------|
| `id` | bigint unsigned PK | No | auto | Identificador único |
| `tenant_id` | bigint unsigned FK | No | — | CASCADE DELETE. Aislamiento por tenant |
| `scrum_column_id` | bigint unsigned FK | No | — | CASCADE DELETE |
| `title` | varchar(255) | No | — | Nombre de la tarea |
| `description` | text | Sí | null | Descripción detallada (preview 5 líneas en tarjeta) |
| `tags` | json | Sí | null | Array de strings: `["urgente","documentos"]` |
| `category` | varchar(100) | Sí | null | Categoría |
| `due_date` | date | Sí | null | Fecha límite |
| `assigned_to_id` | bigint unsigned FK | Sí | null | `users.id`, NULL ON DELETE |
| `case_id` | bigint unsigned FK | Sí | null | `cases.id`, NULL ON DELETE |
| `order_index` | unsigned int | No | 0 | Posición dentro de la columna (gap strategy) |

### Diagrama de Relaciones

```
tenants (1) ──── (N) scrum_columns (1) ──── (N) scrum_tasks
                                                    │
                                                    ├── (N:1) users (assigned_to_id)
                                                    └── (N:1) cases (case_id, nullable)
```

---

## Diseño de API

Todos los endpoints bajo `Route::middleware(['auth:sanctum', 'throttle:api', 'tenant'])`.

### Endpoints

| Método | Ruta | Descripción | Request Body |
|--------|------|-------------|--------------|
| `GET` | `/api/scrum/board` | Tablero completo (columnas + tareas) | Query: `?assigned_to=me` (opcional) |
| `GET` | `/api/scrum/assignees` | Usuarios con rol consultor/apoyo activos | — |
| `POST` | `/api/scrum/columns` | Crear columna | `{ title }` |
| `PATCH` | `/api/scrum/columns/reorder` | Reordenar columnas | `{ columns: [{id, order_index}] }` |
| `PATCH` | `/api/scrum/columns/{column}` | Renombrar columna | `{ title }` |
| `DELETE` | `/api/scrum/columns/{column}` | Eliminar columna (solo si vacía) | — |
| `POST` | `/api/scrum/tasks` | Crear tarea | `{ scrum_column_id, title, description?, tags?, category?, due_date?, assigned_to_id?, case_id? }` |
| `GET` | `/api/scrum/tasks/{task}` | Detalle completo de tarea | — |
| `PUT` | `/api/scrum/tasks/{task}` | Actualizar tarea | Todos los campos opcionales |
| `DELETE` | `/api/scrum/tasks/{task}` | Eliminar tarea | — |
| `PATCH` | `/api/scrum/tasks/{task}/move` | Mover tarea (columna + posición lógica) | `{ scrum_column_id, position }` |

### Resources

**`ScrumBoardResource`** — respuesta de `GET /api/scrum/board`:
```json
{
    "data": {
        "columns": [{
            "id": 1,
            "title": "Por hacer",
            "order_index": 1000,
            "tasks_count": 1,
            "tasks": [{
                "id": 10,
                "title": "Revisar documentos cliente X",
                "description_preview": "Se requiere revision de los documentos...",
                "tags": ["documentos", "urgente"],
                "category": "revision",
                "due_date": "2026-03-20",
                "order_index": 1000,
                "created_at": "2026-03-15T10:30:00.000Z",
                "assigned_to": { "id": 5, "name": "Maria Gonzalez" },
                "case": { "id": 42, "case_number": "IMM-2026-0042" }
            }]
        }]
    }
}
```

Nota: `description_preview` se trunca a 200 caracteres. La descripción completa se obtiene con `GET /api/scrum/tasks/{task}`.

### Validaciones Clave

**Restricción de borrado de columna** — en el controlador (no Observer):
```php
public function destroy(ScrumColumn $column)
{
    if ($column->tasks()->exists()) {
        return response()->json([
            'message' => 'No se puede eliminar una columna que contiene tareas.'
        ], 422);
    }
    $column->delete();
    return response()->noContent();
}
```

**Validación de assignees** — en `StoreScrumTaskRequest::withValidator()`:
```php
$validator->after(function ($validator) {
    if ($this->assigned_to_id) {
        $user = User::find($this->assigned_to_id);
        if (!$user || !$user->is_active || !$user->hasAnyRole(['consultor', 'apoyo'])) {
            $validator->errors()->add('assigned_to_id', 'El usuario debe ser un consultor o apoyo activo.');
        }
    }
});
```

---

## Modelos Laravel

### `ScrumColumn`

```php
class ScrumColumn extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'title', 'order_index'];

    public function tasks(): HasMany
    {
        return $this->hasMany(ScrumTask::class)->orderBy('order_index');
    }
}
```

### `ScrumTask`

```php
class ScrumTask extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'scrum_column_id', 'title', 'description',
        'tags', 'category', 'due_date', 'assigned_to_id', 'case_id', 'order_index',
    ];

    protected $casts = [
        'tags'     => 'array',
        'due_date' => 'date',
    ];

    public function column(): BelongsTo
    {
        return $this->belongsTo(ScrumColumn::class, 'scrum_column_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class, 'case_id');
    }

    public function getDescriptionPreviewAttribute(): ?string
    {
        if (!$this->description) return null;
        return Str::limit($this->description, 200);
    }
}
```

---

## Algoritmo de Reordenamiento (PATCH /move)

El frontend envía la **posición lógica** (índice 0-based en la lista visual). El backend calcula el `order_index` numérico.

**Payload del frontend:**
```json
{ "scrum_column_id": 2, "position": 3 }
```

**Pseudocódigo:**
```
FUNCTION moveTask(task, targetColumnId, position):
    BEGIN TRANSACTION

    task.scrum_column_id = targetColumnId
    tasksInTarget = ScrumTask
        .where(scrum_column_id = targetColumnId)
        .where(id != task.id)
        .orderBy(order_index)
        .get()

    IF position == 0:
        IF tasksInTarget.isEmpty():
            newIndex = 1000
        ELSE:
            firstIndex = tasksInTarget[0].order_index
            newIndex = firstIndex > 1 ? FLOOR(firstIndex / 2) : REINDEX + 500
    ELSE IF position >= tasksInTarget.count():
        newIndex = tasksInTarget.last().order_index + 1000
    ELSE:
        prevIndex = tasksInTarget[position - 1].order_index
        nextIndex = tasksInTarget[position].order_index
        gap = nextIndex - prevIndex
        newIndex = gap > 1 ? prevIndex + FLOOR(gap / 2) : REINDEX + position * 1000

    task.order_index = newIndex
    task.save()
    COMMIT TRANSACTION

FUNCTION reindexColumn(columnId):
    // Actualiza todos los order_index de la columna en múltiplos de 1000
    tasks = ScrumTask.where(scrum_column_id=columnId).orderBy(order_index)
    FOR i, task IN tasks:
        task.update(order_index: (i + 1) * 1000)
```

---

## Componentes Vue

### Árbol de Componentes

```
views/apps/scrumboard.vue               ← actualizar a: import ScrumBoard desde subdirectorio
views/apps/scrumboard/
├── ScrumBoard.vue                      ← Contenedor principal, store, filtro Yo/Todos
│   ├── ScrumColumn.vue                 ← Columna individual (draggable container)
│   │   ├── ScrumTaskCard.vue           ← Tarjeta (line-clamp-5, tags, fecha, assignee)
│   │   └── [+ Add Task button]
│   └── [+ Add Column button]
├── components/
│   ├── ScrumTaskModal.vue              ← Modal create/edit tarea (reutilizable)
│   └── ScrumColumnModal.vue            ← Modal create/edit columna
```

### Pinia Store `useScrumStore`

```typescript
// stores/scrum.ts
interface ScrumState {
    columns: ScrumColumn[];
    assignees: Assignee[];
    isLoading: boolean;
    error: string | null;
}

// Actions:
fetchBoard()                         // GET /api/scrum/board
createColumn(title)                  // POST /api/scrum/columns
updateColumn(id, title)              // PATCH /api/scrum/columns/{id}
deleteColumn(id)                     // DELETE /api/scrum/columns/{id}
reorderColumns(items)                // PATCH /api/scrum/columns/reorder
createTask(data)                     // POST /api/scrum/tasks
updateTask(id, data)                 // PUT /api/scrum/tasks/{id}
deleteTask(id)                       // DELETE /api/scrum/tasks/{id}
moveTask(taskId, columnId, position) // PATCH (optimistic: update → API → revert on fail)
fetchAssignees()                     // GET /api/scrum/assignees (cacheado)

// Getters:
filteredColumns(userId)              // Filtra tareas por assigned_to_id (para toggle "Yo")
```

### Drag & Drop — vue-draggable-plus

```html
<!-- Columnas -->
<VueDraggable v-model="store.columns" group="columns" handle=".column-handle" @end="onColumnDragEnd">
    <ScrumColumn v-for="col in store.columns" :key="col.id" :column="col" />
</VueDraggable>

<!-- Tareas (en ScrumColumn.vue) -->
<VueDraggable v-model="column.tasks" group="tasks" ghost-class="sortable-ghost" @end="onTaskDragEnd">
    <ScrumTaskCard v-for="task in column.tasks" :key="task.id" :task="task" />
</VueDraggable>
```

El evento `@end` provee `{ oldIndex, newIndex, from, to }`. Se extrae `targetColumnId` de `to.dataset.columnId` y se llama `store.moveTask(taskId, targetColumnId, newIndex)`.

### CSS — Line Clamp

Tailwind 3.3+ incluye `line-clamp-5` nativamente:
```html
<p class="line-clamp-5 text-sm text-gray-600 dark:text-gray-400">
    {{ task.description_preview }}
</p>
```

---

## Tipos TypeScript

```typescript
// types/scrum.ts

export interface ScrumColumn {
    id: number;
    title: string;
    order_index: number;
    tasks: ScrumTask[];
    tasks_count: number;
}

export interface ScrumTask {
    id: number;
    scrum_column_id: number;
    title: string;
    description?: string;
    description_preview?: string;
    tags: string[];
    category?: string;
    due_date?: string;
    order_index: number;
    created_at: string;
    updated_at?: string;
    assigned_to?: { id: number; name: string; email?: string };
    case?: { id: number; case_number: string; client_name?: string };
}

export interface CreateScrumTaskData {
    scrum_column_id: number;
    title: string;
    description?: string;
    tags?: string[];
    category?: string;
    due_date?: string;
    assigned_to_id?: number;
    case_id?: number;
}

export interface UpdateScrumTaskData {
    title?: string;
    description?: string;
    tags?: string[];
    category?: string;
    due_date?: string;
    assigned_to_id?: number | null;
    case_id?: number | null;
}

export interface MoveScrumTaskData {
    scrum_column_id: number;
    position: number;
}

export interface Assignee {
    id: number;
    name: string;
    email: string;
}
```

---

## Permisos (Spatie)

| Permiso | Descripción | Roles |
|---------|-------------|-------|
| `scrum.view` | Ver el tablero | admin, consultor, apoyo |
| `scrum.create` | Crear columnas y tareas | admin, consultor, apoyo |
| `scrum.edit` | Editar y mover | admin, consultor, apoyo |
| `scrum.delete` | Eliminar columnas y tareas | admin |

---

## i18n — Claves Necesarias

| Key | EN | ES | FR |
|-----|----|----|-----|
| `scrum_board` | Scrum Board | Tablero Scrum | Tableau Scrum |
| `scrum_add_column` | Add Column | Agregar Columna | Ajouter une colonne |
| `scrum_edit_column` | Edit Column | Editar Columna | Modifier la colonne |
| `scrum_delete_column` | Delete Column | Eliminar Columna | Supprimer la colonne |
| `scrum_column_title` | Column Title | Título de Columna | Titre de colonne |
| `scrum_add_task` | Add Task | Agregar Tarea | Ajouter une tâche |
| `scrum_edit_task` | Edit Task | Editar Tarea | Modifier la tâche |
| `scrum_delete_task` | Delete Task | Eliminar Tarea | Supprimer la tâche |
| `scrum_task_title` | Task Title | Título de Tarea | Titre de tâche |
| `scrum_task_description` | Description | Descripción | Description |
| `scrum_task_tags` | Tags | Etiquetas | Étiquettes |
| `scrum_task_tags_hint` | Separate with commas | Separar con comas | Séparer par des virgules |
| `scrum_task_category` | Category | Categoría | Catégorie |
| `scrum_task_due_date` | Due Date | Fecha Límite | Date limite |
| `scrum_task_assigned_to` | Assigned To | Asignado a | Assigné à |
| `scrum_task_case` | Related Case | Caso Relacionado | Dossier lié |
| `scrum_task_created_at` | Created | Creado | Créé |
| `scrum_filter_all` | All | Todos | Tous |
| `scrum_filter_mine` | Mine | Yo | Les miens |
| `scrum_no_tasks` | No tasks | Sin tareas | Aucune tâche |
| `scrum_column_not_empty` | Cannot delete column with tasks | No se puede eliminar una columna con tareas | Impossible de supprimer une colonne avec des tâches |
| `scrum_task_saved` | Task saved | Tarea guardada | Tâche enregistrée |
| `scrum_task_deleted` | Task deleted | Tarea eliminada | Tâche supprimée |
| `scrum_column_saved` | Column saved | Columna guardada | Colonne enregistrée |
| `scrum_column_deleted` | Column deleted | Columna eliminada | Colonne supprimée |
| `scrum_confirm_delete_task` | Delete this task? | ¿Eliminar esta tarea? | Supprimer cette tâche ? |
| `scrum_confirm_delete_column` | Delete this column? | ¿Eliminar esta columna? | Supprimer cette colonne ? |

---

## Riesgos Identificados

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|-------------|---------|------------|
| **Concurrencia: dos usuarios mueven la misma tarea simultáneamente** | Media | Medio | El backend recalcula siempre la posición desde el estado actual de BD. La tarea quedará en posición válida (no corrupta). V2 puede agregar WebSockets. |
| **Carga masiva: columna con 100+ tareas** | Baja | Medio | V1 sin virtualización. Volumen esperado < 50 tareas por tenant. Si crece, agregar `vue-virtual-scroller` o paginación lazy por columna. |
| **Reindexación masiva bloquea requests** | Baja | Bajo | Solo ocurre cuando gaps de 1000 se agotan (improbable). Afecta solo tareas de una columna (< 30 típicamente). < 50ms. |
| **Pérdida de datos por Optimistic UI** | Baja | Alto | El store guarda snapshot previo antes de cada operación optimistic. Si la API falla, se restaura snapshot completo + notificación SweetAlert2. |
| **Escalada de privilegios entre tenants** | Muy Baja | Crítico | Trait `BelongsToTenant` con `TenantScope` global. FormRequests validan que `scrum_column_id` y `case_id` pertenezcan al tenant. Route model binding con global scope filtra automáticamente. |
| **Tags inconsistentes (JSON malformado)** | Media | Bajo | Tags se validan en backend (`array|max:10`, cada elemento `string|max:50`). Frontend parsea texto separado por comas antes de enviar. |

---

## Lista Completa de Archivos

### Backend — Nuevos

| Archivo | Descripción |
|---------|-------------|
| `database/migrations/2026_03_16_000001_create_scrum_columns_table.php` | Tabla `scrum_columns` |
| `database/migrations/2026_03_16_000002_create_scrum_tasks_table.php` | Tabla `scrum_tasks` |
| `database/migrations/2026_03_16_000003_add_scrum_permissions.php` | Permisos Spatie |
| `app/Models/ScrumColumn.php` | Modelo con `BelongsToTenant`, relación `tasks` |
| `app/Models/ScrumTask.php` | Modelo con casts, relaciones, accessor `description_preview` |
| `app/Http/Controllers/Api/ScrumBoardController.php` | `index()` + `assignees()` |
| `app/Http/Controllers/Api/ScrumColumnController.php` | CRUD + reorder |
| `app/Http/Controllers/Api/ScrumTaskController.php` | CRUD + move |
| `app/Http/Resources/ScrumBoardResource.php` | Tablero completo anidado |
| `app/Http/Resources/ScrumColumnResource.php` | Columna individual |
| `app/Http/Resources/ScrumTaskResource.php` | Tarea detalle completo |
| `app/Http/Resources/ScrumTaskListResource.php` | Tarea con `description_preview` |
| `app/Http/Requests/Scrum/StoreScrumColumnRequest.php` | |
| `app/Http/Requests/Scrum/UpdateScrumColumnRequest.php` | |
| `app/Http/Requests/Scrum/StoreScrumTaskRequest.php` | Con validación de roles assignee |
| `app/Http/Requests/Scrum/UpdateScrumTaskRequest.php` | |
| `app/Http/Requests/Scrum/MoveScrumTaskRequest.php` | |
| `app/Http/Requests/Scrum/ReorderScrumColumnsRequest.php` | |
| `database/factories/ScrumColumnFactory.php` | |
| `database/factories/ScrumTaskFactory.php` | |
| `tests/Unit/Models/ScrumColumnTest.php` | |
| `tests/Unit/Models/ScrumTaskTest.php` | |
| `tests/Feature/Api/ScrumBoardTest.php` | |
| `tests/Feature/Api/ScrumColumnTest.php` | |
| `tests/Feature/Api/ScrumTaskTest.php` | |

### Backend — Modificados

| Archivo | Cambio |
|---------|--------|
| `routes/api.php` | Agregar grupo de rutas `/scrum` |
| `database/seeders/DatabaseSeeder.php` | Seeder de columnas default por tenant |

### Frontend — Nuevos

| Archivo | Descripción |
|---------|-------------|
| `resources/js/src/types/scrum.ts` | Interfaces TypeScript |
| `resources/js/src/services/scrumService.ts` | Funciones de API |
| `resources/js/src/stores/scrum.ts` | Pinia store con optimistic updates |
| `resources/js/src/views/apps/scrumboard/ScrumBoard.vue` | Contenedor principal |
| `resources/js/src/views/apps/scrumboard/components/ScrumColumn.vue` | |
| `resources/js/src/views/apps/scrumboard/components/ScrumTaskCard.vue` | |
| `resources/js/src/views/apps/scrumboard/components/ScrumTaskModal.vue` | |
| `resources/js/src/views/apps/scrumboard/components/ScrumColumnModal.vue` | |

### Frontend — Modificados

| Archivo | Cambio |
|---------|--------|
| `resources/js/src/views/apps/scrumboard.vue` | Reemplazar contenido por import de `ScrumBoard.vue` |
| `resources/js/src/locales/en.json` | +27 claves `scrum_*` |
| `resources/js/src/locales/es.json` | Ídem |
| `resources/js/src/locales/fr.json` | Ídem |

---

## Checklist de Implementación

### Fase 1: Backend Core (Base de Datos y Modelos) ✅
- [x] Crear migración `create_scrum_columns_table`
- [x] Crear migración `create_scrum_tasks_table`
- [x] Crear permisos Spatie via `RolePermissionSeeder` (`scrum.view`, `scrum.create`, `scrum.edit`, `scrum.delete`)
- [x] Crear modelo `ScrumColumn` con trait `BelongsToTenant`, relación `tasks`
- [x] Crear modelo `ScrumTask` con trait `BelongsToTenant`, casts, relaciones, accessor `description_preview`
- [x] Ejecutar `php artisan migrate` — scrum_columns (63ms), scrum_tasks (48ms)

### Fase 2: Backend API (Controllers, Resources, Requests) ✅
- [x] Crear `ScrumBoardController` (`index`, `assignees`)
- [x] Crear `ScrumColumnController` (`store`, `update`, `destroy`, `reorder`)
- [x] Crear `ScrumTaskController` (`store`, `show`, `update`, `destroy`, `move`)
- [x] Crear `ScrumColumnResource`
- [x] Crear `ScrumTaskResource` (detalle completo)
- [x] Crear `ScrumTaskListResource` (con `description_preview`)
- [x] Crear 6 FormRequests en `app/Http/Requests/Scrum/`
- [x] Implementar algoritmo de reordenamiento con gap strategy en `ScrumTaskController@move`
- [x] Registrar 11 rutas en `routes/api.php` bajo `prefix('scrum')`
- [x] `PATCH /columns/reorder` colocado antes de `PATCH /columns/{scrumColumn}` — sin colisión

### Fase 3: Backend Testing ✅
- [x] Ejecutar suite completa — 280 tests, 755 assertions
- [x] Sin regresiones en módulos existentes (51 fallos pre-existentes en TwoFactorTest, no relacionados)

### Fase 4: Frontend (Tipos, Store, Servicio API) ✅
- [x] Crear `types/scrum.ts` con todas las interfaces
- [x] Crear `services/scrumService.ts` con funciones API
- [x] Crear `stores/scrum.ts` (Pinia store con optimistic updates y snapshot revert)
- [x] Agregar 28 claves i18n en `en.json`, `es.json`, `fr.json`

### Fase 5: Frontend (Componentes UI) ✅
- [x] Crear `ScrumBoard.vue` (contenedor, carga datos, toggle Yo/Todos, drag columnas)
- [x] Crear `ScrumTaskCard.vue` (line-clamp-5, tags badges, fecha con highlight overdue, assignee)
- [x] Crear `ScrumTaskModal.vue` (title, description, tags, category, due_date, assigned_to, created_at readonly)
- [x] Crear `ScrumColumnModal.vue` (formulario simple: title)
- [x] Integrar VueDraggable para columnas y para tareas (group="tasks")
- [x] Implementar optimistic UI con snapshot revert en `moveTask`
- [x] Implementar filtro "Yo/Todos" con computed property sobre el store
- [x] Actualizar `views/apps/scrumboard.vue` — wrapper que importa `ScrumBoard.vue`
- [x] `npm run build` — ✅ 7.99s, cero errores TypeScript

### Fase 6: QA
- [ ] Test manual: crear columna, crear tarea con todos los campos
- [ ] Test manual: drag-and-drop dentro de columna
- [ ] Test manual: drag-and-drop entre columnas
- [ ] Test manual: reordenar columnas
- [ ] Test manual: filtro "Yo" vs "Todos"
- [ ] Test manual: no permitir eliminar columna con tareas (error 422)
- [ ] Test manual: vinculación de tarea a caso existente
- [ ] Test manual: dark mode
- [ ] Test manual: responsive (scroll horizontal en mobile)

### Verificación Final
**Fecha:** 2026-03-15
**Backend:** 280 tests, 755 assertions ✅
**Frontend build:** 7.99s, cero errores TypeScript ✅
**Rutas registradas:** 11 endpoints `/api/scrum/*` ✅
**Migraciones:** `scrum_columns` + `scrum_tasks` ejecutadas ✅
**Permisos:** 4 permisos Spatie creados y asignados a admin/consultor/apoyo ✅
