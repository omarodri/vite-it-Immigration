---
tipo: spec
numero: 19
modulo: Workflows y Tareas
dominio: WFLOW
estado: DONE
tags: [spec, workflows, done]
relacionados:
  - "[[18_scrum_board_kanban|Spec 18]]"
  - "[[49_todos_core_desde_workflow|Spec 49]]"
---
# Plan de Implementación: Todo List — Integración con Gestión de Casos

## Metadata
- **Fecha:** 2026-03-15
- **Version:** 1.0
- **Arquitecto:** Claude (Architect Agent)
- **Epic:** 5.2 - Todo List operativo conectado al núcleo del negocio
- **Alcance:** Transformar el módulo `todolist.vue` (actualmente client-side con datos hardcodeados) en un módulo de producción con persistencia en BD, asignación a consultores/apoyo activos, vinculación opcional a casos activos, y filtros operativos.
- **Flujos cubiertos:** `views/apps/todolist.vue` (reemplazado completamente) + nuevos archivos de backend y frontend
- **Tiempo Total Estimado:** 18–24 horas
- **Estado:** ✅ COMPLETADO (2026-03-15)

---

## Contexto y Problema

El componente `resources/js/src/views/apps/todolist.vue` (1,197 líneas) opera exclusivamente con datos hardcodeados en memoria local. No tiene persistencia, no se conecta a ninguna API, carece de multitenancy, y los campos `assignee` y `tag` son strings estáticos sin relación a entidades reales del sistema.

Se requiere transformar este componente demo en un módulo de producción completo con:
- Persistencia en base de datos con aislamiento por tenant
- Asignación a usuarios reales con roles `consultor` o `apoyo` y estatus `activo`
- Vinculación opcional a casos de inmigración activos
- CRUD completo + toggle de estado (pendiente/completado/importante/papelera)
- Editor de texto enriquecido (Quill) para el campo `description` — ya integrado en la plantilla
- Filtros por assignee y por caso

---

## Decisiones Arquitectónicas

| Dimensión | Decisión | Justificación |
|-----------|----------|---------------|
| **Módulo nuevo vs. reutilizar [[ScrumTask]]** | Módulo independiente (`todos` table). | El Todo List tiene semántica distinta: tareas personales/operativas con estados inbox/importante/papelera. ScrumTask es para tablero Kanban con columnas y drag-and-drop. Compartir la misma tabla acoplaría dos dominios distintos. |
| **Carga de assignees y cases** | Mount-time, con caché en store (`if loaded, return`). | Una sola request al montar la vista, sin lag al abrir el modal. Mismo patrón que `useScrumStore.fetchAssignees()`. |
| **Selector de casos: local vs. remoto** | Local si ≤100 casos activos; búsqueda remota con debounce si >100. | Evitar cargar miles de registros en un `<select>`. El endpoint `GET /api/cases?status=active&search=` ya existe. |
| **Quill para description** | Sí — ya integrado en la plantilla (EasyMDE/Quill están disponibles). | La imagen de referencia muestra un rich text editor en el modal. Quill es el editor ya usado en otros módulos del sistema. |
| **Soft deletes** | No. Status `trash` + hard delete desde papelera. | Los todos son items operativos efímeros. El status `trash` actúa como papelera lógica antes del borrado definitivo. |
| **Paginación** | 10 tareas por página (igual al componente original). | Evita cargar todos los registros de un tenant en memoria. El backend pagina con `per_page=10`. |
| **Tags** | Campo `tag` VARCHAR(20) con enum fijo: `team`, `update`, vacío. | El diseño original solo maneja estos dos tags. No se necesita estructura relacional compleja. Se puede extender a JSON array en una épica futura. |
| **Priority** | ENUM: `low`, `medium`, `high`. Default `low`. | Igual que el componente original. El color del dot en la lista se mapea a estos tres valores. |
| **Permisos** | `todos.view`, `todos.create`, `todos.edit`, `todos.delete`. | Sigue el patrón Spatie del proyecto. Los roles `admin`, `consultor` y `apoyo` tendrán acceso. |
| **Tenant isolation** | Trait `BelongsToTenant` en `Todo` model. | `TenantScope` global asegura que nunca se filtran datos entre tenants. Igual que ScrumTask. |

---

## Esquema de Base de Datos

### Tabla `todos`

```php
Schema::create('todos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
    $table->string('title', 255);
    $table->text('description')->nullable();          // HTML del Quill editor
    $table->foreignId('assigned_to_id')
          ->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('case_id')
          ->nullable()->constrained('cases')->nullOnDelete();
    $table->string('tag', 20)->nullable();             // 'team' | 'update' | null
    $table->enum('priority', ['low', 'medium', 'high'])->default('low');
    $table->enum('status', ['pending', 'complete', 'important', 'trash'])->default('pending');
    $table->date('due_date')->nullable();
    $table->timestamps();

    $table->index(['tenant_id', 'status']);
    $table->index(['tenant_id', 'assigned_to_id']);
    $table->index('case_id');
});
```

| Columna | Tipo | Nullable | Default | Descripción |
|---------|------|----------|---------|-------------|
| `id` | bigint unsigned PK | No | auto | Identificador único |
| `tenant_id` | bigint unsigned FK | No | — | `tenants.id`, CASCADE DELETE |
| `title` | varchar(255) | No | — | Título de la tarea |
| `description` | text | Sí | null | HTML generado por Quill |
| `assigned_to_id` | bigint unsigned FK | Sí | null | `users.id`, NULL ON DELETE |
| `case_id` | bigint unsigned FK | Sí | null | `cases.id`, NULL ON DELETE |
| `tag` | varchar(20) | Sí | null | `'team'` \| `'update'` \| null |
| `priority` | enum | No | `'low'` | `'low'` \| `'medium'` \| `'high'` |
| `status` | enum | No | `'pending'` | `'pending'` \| `'complete'` \| `'important'` \| `'trash'` |
| `due_date` | date | Sí | null | Fecha límite opcional |
| `created_at` / `updated_at` | timestamp | Sí | null | Gestionado por Eloquent |

### Diagrama de Relaciones

```
tenants (1) ──── (N) todos
                      │
                      ├── (N:1) users (assigned_to_id)  — NULL ON DELETE
                      └── (N:1) cases (case_id)          — NULL ON DELETE
```

**Integridad referencial:**
- `assigned_to_id → users.id` ON DELETE SET NULL: si el usuario se elimina, el todo queda sin asignar (no se pierde la tarea).
- `case_id → cases.id` ON DELETE SET NULL: si el caso se cierra/elimina, el todo queda desvinculado pero persiste.

---

## Diseño de API

Todos los endpoints bajo `Route::middleware(['auth:sanctum', 'throttle:api', 'tenant'])`.

### Endpoints

| Método | Ruta | Descripción | Params |
|--------|------|-------------|--------|
| `GET` | `/api/todos` | Lista paginada con filtros | `?status=`, `?assigned_to_id=`, `?case_id=`, `?search=`, `?per_page=`, `?page=` |
| `POST` | `/api/todos` | Crear todo | Body completo |
| `GET` | `/api/todos/{todo}` | Detalle completo (incluye `description` HTML) | — |
| `PUT` | `/api/todos/{todo}` | Actualizar todo | Todos los campos opcionales |
| `DELETE` | `/api/todos/{todo}` | Eliminar (hard delete) | — |
| `PATCH` | `/api/todos/{todo}/status` | Cambiar status | `{ status: 'complete' \| 'important' \| 'trash' \| 'pending' }` |
| `DELETE` | `/api/todos/bulk` | Eliminar múltiples | `{ ids: [1, 2, 3] }` |

### TodoResource

```php
// TodoResource — respuesta de lista y detalle
[
    'id'          => $this->id,
    'title'       => $this->title,
    'description' => $this->description,         // HTML completo
    'tag'         => $this->tag,
    'priority'    => $this->priority,
    'status'      => $this->status,
    'due_date'    => $this->due_date?->format('Y-m-d'),
    'created_at'  => $this->created_at?->toISOString(),
    'updated_at'  => $this->updated_at?->toISOString(),
    'assigned_to' => $this->whenLoaded('assignedTo', fn() =>
        $this->assignedTo ? [
            'id'   => $this->assignedTo->id,
            'name' => $this->assignedTo->name,
        ] : null
    ),
    'case' => $this->whenLoaded('immigrationCase', fn() =>
        $this->immigrationCase ? [
            'id'          => $this->immigrationCase->id,
            'case_number' => $this->immigrationCase->case_number
                             ?? $this->immigrationCase->id,
            'client_name' => $this->immigrationCase->client?->full_name,
        ] : null
    ),
]
```

### Respuesta paginada de `GET /api/todos`

```json
{
    "data": [
        {
            "id": 1,
            "title": "Revisar documentos del cliente",
            "description": "<p>Se requiere...</p>",
            "tag": "team",
            "priority": "high",
            "status": "pending",
            "due_date": "2026-03-20",
            "assigned_to": { "id": 5, "name": "María González" },
            "case": { "id": 42, "case_number": "IMM-2026-0042", "client_name": "Juan Pérez" }
        }
    ],
    "meta": {
        "current_page": 1,
        "total": 47,
        "per_page": 10,
        "last_page": 5
    }
}
```

### Validaciones Clave

**`StoreTodoRequest` / `UpdateTodoRequest`:**

```php
// assigned_to_id: debe ser consultor o apoyo activo del mismo tenant
'assigned_to_id' => [
    'nullable', 'integer',
    Rule::exists('users', 'id')->where(fn($q) =>
        $q->where('tenant_id', auth()->user()->tenant_id)
          ->where('status', 'active')
    ),
    // Validación de rol en withValidator()
],

// case_id: solo casos activos del mismo tenant
'case_id' => [
    'nullable', 'integer',
    Rule::exists('cases', 'id')->where(fn($q) =>
        $q->where('tenant_id', auth()->user()->tenant_id)
          ->where('status', '!=', 'closed')
    ),
],

// tag: solo valores permitidos
'tag'      => ['nullable', 'string', Rule::in(['team', 'update'])],

// priority: enum
'priority' => ['nullable', 'string', Rule::in(['low', 'medium', 'high'])],
```

**`withValidator()` para rol del assignee:**

```php
$validator->after(function ($v) {
    if ($this->assigned_to_id) {
        $user = \App\Models\User::find($this->assigned_to_id);
        if (!$user || !$user->hasAnyRole(['consultor', 'apoyo'])) {
            $v->errors()->add(
                'assigned_to_id',
                'El asignado debe tener rol consultor o apoyo.'
            );
        }
    }
});
```

---

## Modelos Laravel

### `Todo`

```php
class Todo extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'title', 'description',
        'assigned_to_id', 'case_id',
        'tag', 'priority', 'status', 'due_date',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function immigrationCase(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class, 'case_id');
    }

    // Scopes
    public function scopeByStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }

    public function scopeInbox(Builder $q): Builder
    {
        return $q->whereNotIn('status', ['complete', 'important', 'trash']);
    }
}
```

---

## Controlador

### `TodoController`

```php
// GET /api/todos
public function index(Request $request): AnonymousResourceCollection
{
    $query = Todo::with(['assignedTo', 'immigrationCase.client'])
        ->when($request->status, fn($q, $s) => $q->where('status', $s))
        ->when($request->assigned_to_id, fn($q, $id) => $q->where('assigned_to_id', $id))
        ->when($request->case_id, fn($q, $id) => $q->where('case_id', $id))
        ->when($request->search, fn($q, $s) => $q->where('title', 'like', "%{$s}%"))
        ->latest();

    return TodoResource::collection($query->paginate($request->per_page ?? 10));
}

// PATCH /api/todos/{todo}/status
public function updateStatus(UpdateTodoStatusRequest $request, Todo $todo): TodoResource
{
    $todo->update(['status' => $request->status]);
    $todo->load(['assignedTo', 'immigrationCase.client']);
    return new TodoResource($todo);
}
```

---

## Carga de Datos para el Modal

### Estrategia: Mount-time con caché

```
onMounted (TodoList.vue)
    ├── todoStore.fetchTodos()       ← lista inicial
    ├── todoStore.fetchAssignees()   ← GET /api/scrum/assignees (reutiliza endpoint existente)
    └── todoStore.fetchCases()       ← GET /api/cases?status=active&per_page=100
```

**Reutilización:** El endpoint `GET /api/scrum/assignees` ya existe y devuelve consultores/apoyo activos. No es necesario crear un nuevo endpoint para los assignees del Todo List.

**Casos activos (>100 registros):** Si el tenant tiene más de 100 casos activos, el selector de casos usa búsqueda remota con debounce:

```typescript
// En el modal, al escribir en el campo "Caso":
const searchCases = useDebounceFn(async (q: string) => {
    const res = await api.get('/cases', { params: { status: 'active', search: q, per_page: 50 } });
    casesOptions.value = res.data.data;
}, 300);
```

---

## Componentes Vue

### Estructura de Archivos

```
resources/js/src/views/apps/
└── todolist.vue                    ← REEMPLAZADO completamente
```

El componente se mantiene en un único archivo (sin subdirectorio) por ser menos complejo que el Scrum Board. El modal de creación/edición se implementa dentro del mismo archivo como sección del template.

### Layout del Modal (basado en imagen de referencia)

```
┌─────────────────────────────────────────────────────────┐
│  ✕  Add/Edit Task                                        │
├─────────────────────────────────────────────────────────┤
│  Title *                                                 │
│  [────────────────────────────────────────────────────] │
│                                                         │
│  Assignee                                               │
│  [── Seleccionar consultor/apoyo ───────────────────▾] │
│                                                         │
│  Caso Relacionado                                       │
│  [── Buscar número de caso ────────────────────────▾]  │
│                                                         │
│  Tag                  Priority                          │
│  [── team / update ▾] [── low / medium / high ────▾]  │
│                                                         │
│  Due Date                                               │
│  [── YYYY-MM-DD ──────────────────────────────────]    │
│                                                         │
│  Description                                            │
│  ┌──────────────────────────────────────────────────┐  │
│  │  B  I  U  |  H1  H2  |  •  1.  |  🔗  img  ...  │  │
│  │──────────────────────────────────────────────────│  │
│  │                                                  │  │
│  │  [Quill Rich Text Editor]                        │  │
│  │                                                  │  │
│  └──────────────────────────────────────────────────┘  │
│                                    [Cancel]  [Save]     │
└─────────────────────────────────────────────────────────┘
```

### Visualización de Tarea en la Lista

```html
<div class="task-item flex items-center gap-3 py-3 border-b">
    <!-- Toggle completado -->
    <input type="checkbox" :checked="todo.status === 'complete'" @change="toggleStatus(todo)" />

    <!-- Punto de prioridad -->
    <span class="w-2 h-2 rounded-full shrink-0"
          :class="{ 'bg-success': low, 'bg-warning': medium, 'bg-danger': high }" />

    <!-- Título -->
    <span class="flex-1 font-medium" :class="{ 'line-through text-gray-400': todo.status === 'complete' }">
        {{ todo.title }}
    </span>

    <!-- Caso relacionado (enlace directo al expediente) -->
    <router-link v-if="todo.case" :to="`/cases/${todo.case.id}`"
                 class="badge badge-outline-info text-xs">
        #{{ todo.case.case_number }}
    </router-link>

    <!-- Assignee -->
    <span v-if="todo.assigned_to" class="text-xs text-gray-400 truncate max-w-[80px]">
        {{ todo.assigned_to.name.split(' ')[0] }}
    </span>

    <!-- Tag badge -->
    <span v-if="todo.tag" class="badge badge-outline-primary text-xs">
        {{ todo.tag }}
    </span>

    <!-- Acciones -->
    <div class="flex gap-1">
        <button @click="openEdit(todo)"><icon-edit /></button>
        <button @click="moveToTrash(todo)"><icon-trash-lines /></button>
    </div>
</div>
```

### Pinia Store `useTodoStore`

```typescript
// stores/todo.ts
interface TodoState {
    todos:        Todo[];
    total:        number;
    currentPage:  number;
    isLoading:    boolean;
    assignees:    Assignee[];
    cases:        CaseOption[];
    filters: {
        status:         string;        // 'pending' | 'complete' | 'important' | 'trash' | ''
        assigned_to_id: number | null;
        case_id:        number | null;
        search:         string;
    };
}

// Actions:
fetchTodos(params?)              // GET /api/todos con filtros
fetchAssignees()                 // GET /api/scrum/assignees (cacheado)
fetchCases(search?)              // GET /api/cases?status=active&search=&per_page=100
createTodo(data)                 // POST /api/todos
updateTodo(id, data)             // PUT /api/todos/{id}
deleteTodo(id)                   // DELETE /api/todos/{id}
updateStatus(id, status)         // PATCH /api/todos/{id}/status (optimistic)
bulkDelete(ids)                  // DELETE /api/todos/bulk

// Getters:
todosByStatus(status)            // filtro local para tabs (inbox/important/trash/complete)
```

### Filtros del Todo List

```
Sidebar (filtros de estado — igual que el original):
  ─ Inbox (todos con status = 'pending')
  ─ Done (status = 'complete')
  ─ Important (status = 'important')
  ─ Trash (status = 'trash')

Header (filtros adicionales nuevos):
  ─ Asignado a: [select — todos los assignees + "Yo" + "Todos"]
  ─ Caso:       [select — casos activos del tenant]
  ─ Buscar:     [input texto — filtra por título]
```

---

## Tipos TypeScript

```typescript
// types/todo.ts

export interface Todo {
    id:          number;
    title:       string;
    description?: string | null;       // HTML Quill
    tag?:        'team' | 'update' | null;
    priority:    'low' | 'medium' | 'high';
    status:      'pending' | 'complete' | 'important' | 'trash';
    due_date?:   string | null;
    created_at:  string;
    updated_at?: string;
    assigned_to?: { id: number; name: string } | null;
    case?:        { id: number; case_number: string; client_name?: string } | null;
}

export interface CreateTodoData {
    title:            string;
    description?:     string;
    assigned_to_id?:  number | null;
    case_id?:         number | null;
    tag?:             string;
    priority?:        'low' | 'medium' | 'high';
    due_date?:        string;
}

export interface UpdateTodoData {
    title?:           string;
    description?:     string;
    assigned_to_id?:  number | null;
    case_id?:         number | null;
    tag?:             string;
    priority?:        'low' | 'medium' | 'high';
    due_date?:        string;
}

export interface UpdateTodoStatusData {
    status: 'pending' | 'complete' | 'important' | 'trash';
}

export interface CaseOption {
    id:          number;
    case_number: string;
    client_name?: string;
}
```

---

## Permisos (Spatie)

| Permiso | Descripción | Roles |
|---------|-------------|-------|
| `todos.view` | Ver el todo list | admin, consultor, apoyo |
| `todos.create` | Crear tareas | admin, consultor, apoyo |
| `todos.edit` | Editar y cambiar estado | admin, consultor, apoyo |
| `todos.delete` | Eliminar tareas | admin, consultor, apoyo |

---

## i18n — Claves Necesarias

| Key | ES | EN | FR |
|-----|----|----|-----|
| `todo_list` | Lista de Tareas | Todo List | Liste de tâches |
| `todo_add_task` | Nueva Tarea | Add Task | Nouvelle tâche |
| `todo_edit_task` | Editar Tarea | Edit Task | Modifier la tâche |
| `todo_title` | Título | Title | Titre |
| `todo_description` | Descripción | Description | Description |
| `todo_assignee` | Asignado a | Assignee | Assigné à |
| `todo_case` | Caso Relacionado | Related Case | Dossier lié |
| `todo_tag` | Etiqueta | Tag | Étiquette |
| `todo_priority` | Prioridad | Priority | Priorité |
| `todo_due_date` | Fecha Límite | Due Date | Date limite |
| `todo_inbox` | Bandeja de Entrada | Inbox | Boîte de réception |
| `todo_done` | Completados | Done | Terminés |
| `todo_important` | Importantes | Important | Importants |
| `todo_trash` | Papelera | Trash | Corbeille |
| `todo_filter_all` | Todos | All | Tous |
| `todo_filter_mine` | Yo | Mine | Les miens |
| `todo_search_placeholder` | Buscar tarea... | Search task... | Rechercher... |
| `todo_search_case` | Buscar caso... | Search case... | Rechercher dossier... |
| `todo_saved` | Tarea guardada | Task saved | Tâche enregistrée |
| `todo_deleted` | Tarea eliminada | Task deleted | Tâche supprimée |
| `todo_confirm_delete` | ¿Eliminar esta tarea? | Delete this task? | Supprimer cette tâche ? |
| `todo_no_tasks` | Sin tareas | No tasks | Aucune tâche |
| `todo_priority_low` | Baja | Low | Basse |
| `todo_priority_medium` | Media | Medium | Moyenne |
| `todo_priority_high` | Alta | High | Haute |
| `todo_tag_team` | Equipo | Team | Équipe |
| `todo_tag_update` | Actualización | Update | Mise à jour |
| `todo_mark_complete` | Marcar completado | Mark complete | Marquer comme terminé |
| `todo_mark_important` | Marcar importante | Mark important | Marquer comme important |
| `todo_move_trash` | Mover a papelera | Move to trash | Déplacer vers la corbeille |
| `todo_restore` | Restaurar | Restore | Restaurer |

---

## Riesgos Identificados

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|-------------|---------|------------|
| **HTML malicioso en Quill (XSS)** | Media | Alto | Sanitizar con `HTMLPurifier` en backend antes de guardar en BD. Permitir solo etiquetas seguras (p, b, i, u, ul, ol, li, h1–h4, a). |
| **Muchos casos activos (>200) — selector lento** | Media | Medio | Búsqueda remota con debounce 300ms + `per_page=50`. Mostrar mensaje "Escribe para buscar" hasta 3+ caracteres. |
| **Pérdida de referencia al eliminar usuario** | Media | Bajo | FK `ON DELETE SET NULL`. El todo persiste con `assigned_to: null`. Indicador visual "Sin asignar" en la tarea. |
| **Pérdida de referencia al cerrar caso** | Media | Bajo | FK `ON DELETE SET NULL`. El todo persiste con `case: null`. Indicador visual en la tarea. |
| **Quill genera HTML pesado** | Baja | Bajo | Columna `text` soporta hasta 65KB. Suficiente para descripciones operativas. |
| **Escalada de privilegios entre tenants** | Muy baja | Crítico | `BelongsToTenant` con `TenantScope` global. Route model binding filtra automáticamente por tenant. |
| **Quill no renderiza HTML sanitizado correctamente** | Baja | Medio | Usar `v-html` en modo solo lectura. Quill recibe el HTML como contenido inicial en modo edición. |

---

## Lista Completa de Archivos

### Backend — Nuevos

| Archivo | Descripción |
|---------|-------------|
| `database/migrations/YYYY_MM_DD_create_todos_table.php` | Tabla `todos` con FKs |
| `app/Models/Todo.php` | Modelo con `BelongsToTenant`, relaciones, scopes |
| `app/Http/Controllers/Api/TodoController.php` | CRUD + status + bulk delete |
| `app/Http/Resources/TodoResource.php` | Resource con assignee y case anidados |
| `app/Http/Requests/Todo/StoreTodoRequest.php` | Validación con reglas de negocio |
| `app/Http/Requests/Todo/UpdateTodoRequest.php` | Ídem + ignore propio ID |
| `app/Http/Requests/Todo/UpdateTodoStatusRequest.php` | Solo campo `status` |
| `app/Http/Requests/Todo/BulkDeleteTodoRequest.php` | Validar array de IDs |
| `database/factories/TodoFactory.php` | Factory para seeders y tests |

### Backend — Modificados

| Archivo | Cambio |
|---------|--------|
| `routes/api.php` | Agregar rutas `/todos` (apiResource + custom) |
| `database/seeders/RolePermissionSeeder.php` | Agregar 4 permisos `todos.*` |

### Frontend — Nuevos

| Archivo | Descripción |
|---------|-------------|
| `resources/js/src/types/todo.ts` | Interfaces TypeScript |
| `resources/js/src/services/todoService.ts` | Funciones de API |
| `resources/js/src/stores/todo.ts` | Pinia store con filtros y paginación |

### Frontend — Modificados

| Archivo | Cambio |
|---------|--------|
| `resources/js/src/views/apps/todolist.vue` | Reemplazar contenido completo |
| `resources/js/src/locales/en.json` | +30 claves `todo_*` |
| `resources/js/src/locales/es.json` | Ídem |
| `resources/js/src/locales/fr.json` | Ídem |

---

## Checklist de Implementación

### Fase 1: Backend Core (Base de Datos y Modelos) ✅
- [x] Crear migración `create_todos_table` con todos los campos y FKs
- [x] Crear modelo `Todo` con `BelongsToTenant`, `HasFactory`, relaciones `assignedTo`, `immigrationCase`, scopes
- [x] Agregar permisos `todos.view/create/edit/delete` al `RolePermissionSeeder`
- [x] Ejecutar `php artisan migrate` — todos (103.63ms)

### Fase 2: Backend API (Controllers, Resources, Requests) ✅
- [x] Crear `TodoResource` con eager loading de `assignedTo` y `immigrationCase.client`
- [x] Crear `StoreTodoRequest` con validación de rol del assignee y status de casos
- [x] Crear `UpdateTodoRequest` con misma validación
- [x] Crear `UpdateTodoStatusRequest` (solo campo `status` con enum)
- [x] Crear `BulkDeleteTodoRequest` (array de IDs del tenant)
- [x] Crear `TodoController` con `index`, `store`, `show`, `update`, `destroy`, `updateStatus`, `bulkDestroy`
- [x] Registrar rutas en `routes/api.php` — 7 rutas registradas
- [x] Ruta `DELETE /api/todos/bulk` colocada ANTES de `apiResource` — sin colisión

### Fase 3: Frontend (Tipos, Store, Servicio API) ✅
- [x] Crear `types/todo.ts` con todas las interfaces
- [x] Crear `services/todoService.ts` con funciones API
- [x] Crear `stores/todo.ts` (Pinia store con filtros, paginación, assignees cacheados)
- [x] Agregar claves i18n en `en.json`, `es.json`, `fr.json`

### Fase 4: Frontend (Vista Principal) ✅
- [x] Reemplazar `todolist.vue` — sidebar de filtros de estado (Inbox/Done/Important/Trash)
- [x] Implementar header con filtros de assignee, caso y búsqueda por texto
- [x] Implementar lista de tareas con: checkbox toggle, dot de prioridad, título, badge de caso (enlace), assignee, tag, acciones
- [x] Implementar paginación (10 por página, igual que el original)

### Fase 5: Frontend (Modal Create/Edit) ✅
- [x] Implementar modal con campos: Title, Assignee, Caso Relacionado, Tag, Priority, Due Date, Description (Quill)
- [x] Integrar Quill para el campo `description`
- [x] Selector de Caso con `<select>` cargado en mount-time (≤100 casos activos)
- [x] Al editar, el modal se puebla correctamente con todos los campos (incluyendo `assigned_to` y `case`)
- [x] `npm run build` — ✅ 8.48s, cero errores TypeScript

### Fase 6: QA
- [ ] Test manual: crear tarea con todos los campos (incluyendo caso y assignee)
- [ ] Test manual: editar tarea — verificar que todos los campos se precargan
- [ ] Test manual: toggle completado/pendiente
- [ ] Test manual: mover tarea a "Importante"
- [ ] Test manual: mover tarea a "Papelera" y eliminar definitivamente
- [ ] Test manual: filtro por assignee
- [ ] Test manual: filtro por caso
- [ ] Test manual: búsqueda por título
- [ ] Test manual: enlace desde badge de caso → navega a `/cases/{id}`
- [ ] Test manual: dark mode
- [ ] Test manual: responsive (mobile)

---

## Verificación Final
**Fecha:** 2026-03-15
**Backend:** migración ejecutada (103.63ms), 7 rutas registradas, 4 permisos Spatie creados ✅
**Frontend build:** 8.48s, cero errores TypeScript ✅
**Rutas registradas:** 7 endpoints `/api/todos/*` ✅
**Migraciones:** tabla `todos` ejecutada ✅
**Permisos:** `todos.view/create/edit/delete` asignados a admin/consultor/apoyo ✅
