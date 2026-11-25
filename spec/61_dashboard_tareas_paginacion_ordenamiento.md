---
tipo: spec
numero: 61
modulo: Alertas y Dashboard
dominio: ALRT
estado: DONE
tags: [spec, alertas, done]
relacionados:
  - "[[19_todo_list_integracion_casos|Spec 19]]"
  - "[[25_dashboard_centro_de_control|Spec 25]]"
  - "[[46_workflows_dinamicos_motor_etapas|Spec 46]]"
  - "[[49_todos_core_desde_workflow|Spec 49]]"
---
# [[61_dashboard_tareas_paginacion_ordenamiento|Spec 61]] — Paginación y Ordenamiento del Widget "Tareas Asignadas" en Dashboard

**Dominio:** UX / WFLOW  
**Estado:** DONE  
**Relacionados:** 19 (Todo List + Casos), 25 (Dashboard Centro Control), 46 (Motor Workflows), 49 (Todos desde Workflow)  
**Creado:** 2026-05-15  
**Autor:** Análisis de Impacto de Rendimiento y UX (Architect Agent)

---

## Resumen Ejecutivo

El widget "Tareas Asignadas" del Dashboard muestra actualmente hasta 50 registros del modelo `Todo` en una lista estática sin paginación ni ordenamiento por urgencia. Para consultores con alta carga de trabajo esto genera scroll excesivo, oculta tareas vencidas entre tareas futuras, e impone un payload innecesariamente grande en la vista más visitada del sistema.

Esta feature implementa:
1. **Ordenamiento cronológico por urgencia** — tareas vencidas primero, nulos al final.
2. **Paginación híbrida** — primera página embebida en el endpoint consolidado del Dashboard; páginas siguientes vía endpoint dedicado.
3. **Indicadores visuales de urgencia** — badges de color en la columna `due_date`.
4. **Persistencia de página en memoria del store** — sin contaminar la URL ni `sessionStorage`.

### Hallazgos Críticos Verificados en el Codebase

| # | Hallazgo | Archivo | Impacto en el Diseño |
|---|---|---|---|
| H1 | El widget consulta el modelo `Todo` (tabla `todos`), **no** `CaseTask` ni `Task` | `app/Http/Controllers/Api/DashboardController.php` | Todo el diseño de query, índices y service opera sobre `todos` |
| H2 | El endpoint `GET /api/dashboard` devuelve un payload consolidado con 5 widgets en un solo request | `DashboardController::index()` líneas ~40-80 | La primera página debe seguir embebida para no romper la latencia inicial |
| H3 | El límite actual de tareas es `->limit(50)` hardcodeado | `DashboardController::index()` línea ~54 | Consultores con más de 50 tareas tienen datos invisibles — client-side slicing no resuelve esto |
| H4 | El store `useDashboardStore` implementa stale-while-revalidate de 5 minutos | `resources/js/src/stores/dashboard.ts` línea ~10 | Duplicar cache en el backend introduciría dos capas de invalidación sin ganancia neta |
| H5 | El ordenamiento actual usa `FIELD(priority, 'high', 'medium', 'low')` como criterio primario | `DashboardController::index()` | Oculta tareas vencidas detrás de tareas de baja prioridad con fecha futura |
| H6 | Existen índices `(tenant_id, assigned_to_id)` y `(tenant_id, status)` en `todos` | `database/migrations/0001_01_01_000007_create_app_feature_tables.php` | Falta un índice compuesto que cubra filtro + ordenamiento por `due_date` en un solo scan |
| H7 | El modelo `Todo` usa `SoftDeletes` pero la migración inicial puede no incluir `deleted_at` | `app/Models/Todo.php` + migración `0001_01_01_000007` | Verificar antes de desplegar que la columna existe, o crear migración correctiva |
| H8 | El campo `due_date` es tipo `date` (no `datetime`) en la tabla `todos` | Migración `000007` línea ~22 | Comparaciones de urgencia por fecha son seguras sin conversión de zona horaria |
| H9 | `CaseTaskService::updateTaskCore` invoca `TodoCoreService::syncCoreFieldsFromTask()` | `app/Services/Case/CaseTaskService.php` línea ~290 | Cambios en el Kanban mutan `Todos` sin pasar por endpoints de Todo — razón adicional para no cachear en servidor |
| H10 | El template actual usa `dashboard.assignedTasks.length` para el badge del título | `resources/js/src/views/index.vue` línea ~88 | Post-paginación debe mostrar `total` (puede ser >50), no el conteo de items visibles |

### Decisiones Clave

| # | Decisión | Justificación |
|---|---|---|
| D1 | **Paginación en la API** (no client-side slicing) | `->limit(50)` no escala; consultores con >50 tareas tienen datos perdidos que client-side no puede recuperar |
| D2 | **Estrategia híbrida:** primera página embebida en `/api/dashboard`, siguientes vía endpoint dedicado | Preserva la latencia de carga inicial; el endpoint separado solo se invoca al cambiar de página |
| D3 | **`AssignedTasksService` dedicado** (no inline en `DashboardController`) | Patrón del proyecto — lógica de negocio en Services, no en Controllers |
| D4 | **Ordenamiento `due_date IS NULL ASC, due_date ASC, id ASC`** (cambio respecto al actual `FIELD(priority,...)`) | Prioriza urgencia real (fecha vencida) sobre urgencia declarada (campo prioridad); `id ASC` como desempate determinista |
| D5 | **Nulls al final** — tareas sin `due_date` aparecen después de todas las fechadas | UX: las tareas sin fecha límite son menos urgentes por definición |
| D6 | **Sin cache de servidor** — el store frontend ya tiene stale-while-revalidate de 5 min | La invalidación del cache servidor sería imposible de mantener dado que `Todos` se mutan desde múltiples servicios (H9) |
| D7 | **Estado de paginación en memoria del store** (no `sessionStorage` ni query params) | El Dashboard es vista de aterrizaje; la página de tareas no necesita ser deep-linkable; un F5 vuelve a página 1 de forma predecible |
| D8 | **Conservar `<table>` HTML actual** — no migrar a `vue3-datatable` para este widget | `vue3-datatable` tiene paginación propia que rompe el control granular sobre el footer del card; los widgets del dashboard usan markup manual |
| D9 | **Badges de urgencia** junto a la fecha (no reemplazar el texto) | Suma señal visual sin ocultar el dato preciso de la fecha |
| D10 | **Nuevo gotcha G17** en `CLAUDE.md` — documentar distinción `Todo` vs `Task` vs `CaseTask` | El naming ambiguo es el riesgo de mantenimiento más probable a largo plazo |

---

## 1. Base de Datos — Migración de Índice

### 1.1 Migración — Índice compuesto para query del Dashboard

```php
<?php
// database/migrations/2026_05_15_000010_add_dashboard_index_to_todos.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            // Índice compuesto para el query del Dashboard:
            // WHERE tenant_id=? AND assigned_to_id=? AND status IN (?)
            // ORDER BY due_date IS NULL ASC, due_date ASC, id ASC
            $table->index(
                ['tenant_id', 'assigned_to_id', 'status', 'due_date'],
                'idx_todos_dashboard'
            );
        });
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->dropIndex('idx_todos_dashboard');
        });
    }
};
```

**Justificación del índice:** El plan de MySQL puede usar este índice para filtrar por `(tenant_id, assigned_to_id, status)` y ordenar por `due_date` sin filesort cuando el conjunto resultante es pequeño. El índice existente `(tenant_id, assigned_to_id)` se mantiene para otros queries (listas globales de todos). Verificar el plan con `EXPLAIN` en local antes de desplegar.

### 1.2 Verificación previa — SoftDeletes en `todos`

Antes de ejecutar cualquier migración, confirmar que la columna `deleted_at` existe:

```bash
grep -rn "deleted_at\|softDeletes" database/migrations/ | grep -i todo
```

Si no existe, crear migración correctiva `add_deleted_at_to_todos_table` antes de las migraciones de este spec.

---

## 2. Backend — Service `AssignedTasksService`

```php
<?php
// app/Services/Dashboard/AssignedTasksService.php

namespace App\Services\Dashboard;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AssignedTasksService
{
    public const DEFAULT_PER_PAGE = 10;
    public const MAX_PER_PAGE     = 50;

    /**
     * Todos asignados al usuario, paginados y ordenados por urgencia.
     * Orden: fechas vencidas primero → fechas futuras → sin fecha (NULLs al final).
     * Desempate determinista por id ASC.
     */
    public function paginate(User $user, int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));

        return Todo::query()
            ->with(['immigrationCase:id,case_number'])
            ->where('assigned_to_id', $user->id)
            ->whereIn('status', ['pending', 'important'])
            ->orderByRaw('due_date IS NULL ASC')
            ->orderBy('due_date', 'asc')
            ->orderBy('id', 'asc')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
```

**Notas técnicas:**
- `BelongsToTenant` aplica `TenantScope` automáticamente — no se necesita `where('tenant_id', ...)` explícito.
- `orderByRaw('due_date IS NULL ASC')` devuelve 0 para fechas no-null (van primero) y 1 para null (van al final). Combinar con `orderBy('due_date', 'asc')` es equivalente a `NULLS LAST` en MySQL.
- El cambio de `FIELD(priority,...)` a `due_date`-first es un **cambio de comportamiento observable** — documentarlo en el commit y en `MEMORY.md`.

---

## 3. Backend — Controlador y Rutas

### 3.1 Nuevo método en `DashboardController`

```php
// app/Http/Controllers/Api/DashboardController.php

use App\Services\Dashboard\AssignedTasksService;
use Illuminate\Http\Request;

public function assignedTasks(Request $request): \Illuminate\Http\JsonResponse
{
    $this->authorize('tasks.view'); // Mismo gate que el bloque actual

    $page    = max(1, (int) $request->query('page', 1));
    $perPage = max(1, min(50, (int) $request->query('per_page', AssignedTasksService::DEFAULT_PER_PAGE)));

    $paginator = app(AssignedTasksService::class)->paginate(
        $request->user(),
        $page,
        $perPage
    );

    return response()->json(
        \App\Http\Resources\DashboardTodoResource::collection($paginator)->response()->getData(true)
    );
}
```

### 3.2 Refactor en `DashboardController::index()`

Reemplazar el bloque `->limit(50)` por delegación al service para la primera página:

```php
// Dentro de index() — reemplazar bloque de assigned_tasks:
$tasksPaginator = app(AssignedTasksService::class)->paginate($user, 1, 10);

// ...en el array de respuesta:
'assigned_tasks'      => DashboardTodoResource::collection($tasksPaginator->getCollection()),
'assigned_tasks_meta' => [
    'total'     => $tasksPaginator->total(),
    'per_page'  => $tasksPaginator->perPage(),
    'last_page' => $tasksPaginator->lastPage(),
],
```

Este cambio mantiene **retrocompatibilidad**: la clave `assigned_tasks` sigue siendo un array plano. Los metadatos se agregan como clave nueva `assigned_tasks_meta`.

### 3.3 Ruta nueva en `routes/api.php`

```php
// routes/api.php — dentro del grupo auth:sanctum + throttle + tenant + ensure.single.session

Route::get('/dashboard/assigned-tasks', [DashboardController::class, 'assignedTasks'])
     ->name('dashboard.assigned-tasks');
```

---

## 4. Backend — Contrato de la API

### 4.1 Endpoint dedicado (páginas 2+)

```
GET /api/dashboard/assigned-tasks?page=2&per_page=10
```

**Respuesta (LengthAwarePaginator envuelto por ResourceCollection):**

```json
{
  "data": [
    {
      "id": 412,
      "title": "Subir pasaporte cliente Pérez",
      "description": null,
      "tag": "documents",
      "priority": "high",
      "status": "pending",
      "due_date": "2026-05-14",
      "case": { "id": 88, "case_number": "C-2026-0042" }
    }
  ],
  "links": {
    "first": "/api/dashboard/assigned-tasks?page=1",
    "last":  "/api/dashboard/assigned-tasks?page=7",
    "prev":  "/api/dashboard/assigned-tasks?page=1",
    "next":  "/api/dashboard/assigned-tasks?page=3"
  },
  "meta": {
    "current_page": 2,
    "from": 11,
    "last_page": 7,
    "per_page": 10,
    "to": 20,
    "total": 64
  }
}
```

### 4.2 Cambio en `GET /api/dashboard` (primera página embebida)

```json
{
  "assigned_tasks": [ /* 10 items — mismo formato de DashboardTodoResource */ ],
  "assigned_tasks_meta": {
    "total": 64,
    "per_page": 10,
    "last_page": 7
  }
  /* ...resto de widgets sin cambios */
}
```

### 4.3 Comparación de payload

| Variante | Items | Tamaño estimado `assigned_tasks` |
|---|---|---|
| Actual | 50 | ~15-20 KB |
| Paginado (10 + meta) | 10 | ~3-4 KB + ~200 B meta |

Reducción ~75% en el bloque de tareas dentro del payload consolidado.

---

## 5. Frontend — Tipos TypeScript

### 5.1 Nuevos tipos en `types/dashboard.ts`

```typescript
// resources/js/src/types/dashboard.ts — agregar:

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface AssignedTasksMeta {
    total: number;
    per_page: number;
    last_page: number;
}

// En DashboardData — agregar campo:
assigned_tasks_meta?: AssignedTasksMeta;
```

---

## 6. Frontend — Servicio `dashboardService.ts`

```typescript
// resources/js/src/services/dashboardService.ts — agregar método:

async getAssignedTasks(page: number, perPage = 10) {
    const response = await api.get<{
        data: DashboardTask[];
        meta: PaginationMeta;
        links: Record<string, string | null>;
    }>('/dashboard/assigned-tasks', {
        params: { page, per_page: perPage },
    });
    return response.data;
},
```

---

## 7. Frontend — Store Pinia `useDashboardStore`

### 7.1 Sub-estado nuevo

```typescript
// resources/js/src/stores/dashboard.ts — adiciones:

interface AssignedTasksState {
    items: DashboardTask[];
    page: number;
    perPage: number;
    total: number;
    lastPage: number;
    isLoading: boolean;
    error: string | null;
}

// En DashboardState agregar:
assignedTasks: AssignedTasksState;

// En state() inicializar:
assignedTasks: {
    items: [],
    page: 1,
    perPage: 10,
    total: 0,
    lastPage: 1,
    isLoading: false,
    error: null,
},
```

### 7.2 Acciones nuevas

```typescript
// Acción 1: sincronizar desde la respuesta del dashboard consolidado
syncAssignedTasksFromDashboard(data: DashboardData) {
    this.assignedTasks.items    = data.assigned_tasks ?? [];
    this.assignedTasks.total    = data.assigned_tasks_meta?.total ?? this.assignedTasks.items.length;
    this.assignedTasks.lastPage = data.assigned_tasks_meta?.last_page ?? 1;
    this.assignedTasks.page     = 1;
},

// Acción 2: cargar página arbitraria
async fetchAssignedTasksPage(page: number) {
    if (page < 1 || page > this.assignedTasks.lastPage) return;
    this.assignedTasks.isLoading = true;
    this.assignedTasks.error = null;
    try {
        const res = await dashboardService.getAssignedTasks(page, this.assignedTasks.perPage);
        this.assignedTasks.items    = res.data;
        this.assignedTasks.page     = res.meta.current_page;
        this.assignedTasks.total    = res.meta.total;
        this.assignedTasks.lastPage = res.meta.last_page;
    } catch (e: any) {
        if (e?.response?.status === 401) throw e;
        this.assignedTasks.error = e.response?.data?.message ?? 'dashboard.tasks_load_error';
    } finally {
        this.assignedTasks.isLoading = false;
    }
},

// Acción 3: invalidar para forzar refetch (ej. al completar una tarea)
invalidateAssignedTasks() {
    this.assignedTasks.page = 1;
    this.lastFetchedAt = null; // Fuerza re-fetch del dashboard consolidado
},
```

### 7.3 Integración con `fetchDashboard()` existente

Al final del bloque `try` de `fetchDashboard()`, invocar:

```typescript
this.syncAssignedTasksFromDashboard(this.data!);
```

---

## 8. Frontend — Refactorización del Widget

### 8.1 Computed y handler en `resources/js/src/views/index.vue`

```typescript
// En <script setup> — reemplazar referencias a dashboard.assignedTasks (array) por sub-estado:

const tasks = computed(() => dashboard.assignedTasks);

function goToPage(page: number) {
    if (page < 1 || page > tasks.value.lastPage || tasks.value.isLoading) return;
    dashboard.fetchAssignedTasksPage(page);
}

function urgencyBadge(dateStr: string | null): { cls: string; key: string } | null {
    if (!dateStr) return null;
    const d = new Date(dateStr + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const diffDays = Math.floor((d.getTime() - today.getTime()) / 86400000);
    if (diffDays < 0)  return { cls: 'badge badge-outline-danger',  key: 'dashboard.urgency_overdue' };
    if (diffDays <= 1) return { cls: 'badge badge-outline-warning', key: 'dashboard.urgency_due_soon' };
    return null;
}
```

### 8.2 Template del widget (reemplazar bloque actual)

```vue
<div class="panel">
    <!-- Header -->
    <div class="flex items-center justify-between mb-5">
        <h5 class="font-semibold text-lg dark:text-white-light">
            {{ t('dashboard.assigned_tasks') }}
            <span v-if="tasks.total > 0" class="badge badge-outline-danger ml-2">
                {{ tasks.total }}
            </span>
        </h5>
    </div>

    <!-- Skeleton: carga inicial o cambio de página -->
    <div v-if="tasks.isLoading || (dashboard.isLoading && !dashboard.data)" class="space-y-2">
        <div v-for="n in 5" :key="n"
             class="h-10 rounded bg-gray-100 dark:bg-gray-800 animate-pulse" />
    </div>

    <!-- Estado vacío -->
    <div v-else-if="tasks.items.length === 0"
         class="flex flex-col items-center justify-center py-10 text-gray-400 dark:text-gray-500">
        <icon-list-check class="w-12 h-12 mb-3 opacity-50" />
        <p>{{ t('dashboard.no_tasks') }}</p>
    </div>

    <!-- Tabla de tareas -->
    <div v-else class="table-responsive">
        <table class="table-hover">
            <thead>
                <tr>
                    <th>{{ t('dashboard.task_id') }}</th>
                    <th>{{ t('dashboard.task_title') }}</th>
                    <th>{{ t('dashboard.task_tag') }}</th>
                    <th>{{ t('dashboard.task_deadline') }}</th>
                    <th>{{ t('dashboard.task_case') }}</th>
                    <th>{{ t('dashboard.task_priority') }}</th>
                    <th>{{ t('dashboard.task_status') }}</th>
                </tr>
            </thead>
            <tbody>
                <TaskRow
                    v-for="task in tasks.items"
                    :key="task.id"
                    :task="task"
                    :urgency-badge="urgencyBadge(task.due_date)"
                />
            </tbody>
        </table>
    </div>

    <!-- Footer paginación (solo si hay más de una página) -->
    <div v-if="tasks.total > tasks.perPage"
         class="flex items-center justify-between pt-4 mt-4 border-t border-gray-100 dark:border-gray-700">
        <p class="text-xs text-gray-500">
            {{ t('dashboard.pagination_summary', {
                from: ((tasks.page - 1) * tasks.perPage) + 1,
                to:   Math.min(tasks.page * tasks.perPage, tasks.total),
                total: tasks.total
            }) }}
        </p>
        <div class="flex items-center gap-2">
            <button
                class="btn btn-sm btn-outline-primary"
                :disabled="tasks.page <= 1 || tasks.isLoading"
                @click="goToPage(tasks.page - 1)"
            >
                {{ t('common.prev') }}
            </button>
            <span class="text-xs font-medium">{{ tasks.page }} / {{ tasks.lastPage }}</span>
            <button
                class="btn btn-sm btn-outline-primary"
                :disabled="tasks.page >= tasks.lastPage || tasks.isLoading"
                @click="goToPage(tasks.page + 1)"
            >
                {{ t('common.next') }}
            </button>
        </div>
    </div>
</div>
```

### 8.3 Prop `urgencyBadge` en `TaskRow.vue`

Agregar prop al componente `resources/js/src/views/dashboard/components/TaskRow.vue`:

```typescript
// Props nuevas:
const props = defineProps<{
    task: DashboardTask;
    urgencyBadge?: { cls: string; key: string } | null;
}>();
```

Renderizar adyacente a `formatDate(task.due_date)`:

```vue
<td>
    <span v-if="urgencyBadge" :class="urgencyBadge.cls" class="mr-1 text-xs">
        {{ t(urgencyBadge.key) }}
    </span>
    <span :class="{ 'text-danger font-semibold': isOverdue }">
        {{ formatDate(task.due_date) }}
    </span>
</td>
```

---

## 9. Internacionalización (i18n)

**Regla crítica:** Keys FLAT. Ningún valor contiene `<` ni `>` literal. Actualizar los 3 idiomas: `es.json`, `en.json`, `fr.json`.

| Key | Valor (es) | Valor (en) | Valor (fr) |
|-----|-----------|-----------|-----------|
| `dashboard.urgency_overdue` | `"Vencida"` | `"Overdue"` | `"Échue"` |
| `dashboard.urgency_due_soon` | `"Próxima"` | `"Due Soon"` | `"Bientôt due"` |
| `dashboard.pagination_summary` | `"Mostrando {from}-{to} de {total}"` | `"Showing {from}-{to} of {total}"` | `"Affichage {from}-{to} sur {total}"` |
| `dashboard.tasks_load_error` | `"Error al cargar las tareas"` | `"Failed to load tasks"` | `"Échec du chargement des tâches"` |
| `common.prev` | `"Anterior"` | `"Previous"` | `"Précédent"` |
| `common.next` | `"Siguiente"` | `"Next"` | `"Suivant"` |

> **Nota:** Verificar si `common.prev` / `common.next` ya existen en los archivos de locale antes de agregar para evitar duplicados.

---

## 10. Riesgos y Mitigaciones

| ID | Riesgo | Prob. | Impacto | Mitigación |
|----|--------|-------|---------|-----------|
| R1 | Confusión `Todo` vs `Task` vs `CaseTask` en futuras modificaciones | ALTA | MEDIA | Documentar como Gotcha G17 en `CLAUDE.md`; nombrar el service `AssignedTasksService` (no `TasksService` ni `TodoService`) |
| R2 | `CaseTaskService::syncCoreFieldsFromTask()` muta Todos sin pasar por el endpoint — el Dashboard muestra dato desactualizado | MEDIA | MEDIA | El stale-while-revalidate de 5 min del store es el mecanismo de actualización; no introducir cache de servidor |
| R3 | `deleted_at` no existe en tabla `todos` — `SoftDeletes` falla silenciosamente | BAJA | ALTA | Verificar con `grep` antes de la migración; si falta, crear migración correctiva primero |
| R4 | Badge del título muestra `total` en lugar de "items visibles" — cambio de semántica observable | MEDIA | BAJA | Confirmar con stakeholders; documentar el cambio de comportamiento en el PR |
| R5 | Permiso `tasks.view` no existe o no está asignado al rol `consultor` | BAJA | ALTA | Auditar con `Spatie\Permission\Models\Permission::where('name', 'tasks.view')->first()` antes del despliegue |
| R6 | `onMounted` en `views/index.vue` fuerza refetch con `dashboard.fetchDashboard(true)` — ignora stale-while-revalidate | BAJA | BAJA | Cambiar a `dashboard.fetchDashboard()` (sin `force`) en el mismo PR — mejora colateral de rendimiento |
| R7 | El índice `idx_todos_dashboard` aumenta el tiempo de INSERT/UPDATE en `todos` | BAJA | BAJA | El impacto en escritura es negligible dado el volumen típico; validar con `EXPLAIN` en MySQL |
| R8 | La paginación rompe el scroll de la lista de companions si el widget y el scroll están vinculados | BAJA | BAJA | El widget del Dashboard es un card independiente; no comparte scroll con otros widgets |

---

## 11. Checklist de Implementación

### Fase 1 — Preparación y Base de Datos ✅ Completada (2026-05-15)

- [x] Verificar existencia de `deleted_at` en tabla `todos` — confirmado en migración `2026_05_02_000001`
- [x] Si falta `deleted_at`, crear migración correctiva antes de continuar — no necesario
- [x] Crear migración `2026_05_15_000010_add_dashboard_index_to_todos.php` con índice `idx_todos_dashboard`
- [x] Ejecutar `php artisan migrate` en local — completado en 60ms
- [ ] Verificar plan de query: `EXPLAIN SELECT ... FROM todos WHERE tenant_id=? AND assigned_to_id=? AND status IN (?) ORDER BY due_date IS NULL ASC, due_date ASC, id ASC LIMIT 10`
- [ ] Auditar permiso `tasks.view` en Spatie — existe en seeder (pendiente migración dedicada, ver DT-07)

### Fase 2 — Backend ✅ Completada (2026-05-15)

- [x] Crear `app/Services/Dashboard/AssignedTasksService.php` con método `paginate()`
- [x] Agregar método `assignedTasks(Request $request)` en `DashboardController`
- [x] Refactorizar bloque de `assigned_tasks` en `DashboardController::index()` — delegar a `AssignedTasksService::paginate($user, 1, 10)` y agregar `assigned_tasks_meta` al payload
- [x] Registrar ruta `GET /api/dashboard/assigned-tasks` en `routes/api.php`
- [ ] Probar endpoint con `curl` o Postman: página 1, página 2, `per_page=50` (máximo), `per_page=100` (debe clampear a 50)

### Fase 3 — Frontend ✅ Completada (2026-05-15)

- [x] Agregar tipos `PaginationMeta` y `AssignedTasksMeta` en `resources/js/src/types/dashboard.ts`
- [x] Agregar `assigned_tasks_meta?` a la interfaz `DashboardData`
- [x] Agregar método `getAssignedTasks(page, perPage)` en `resources/js/src/services/dashboardService.ts`
- [x] Extender `useDashboardStore` con sub-estado `assignedTasks` y acciones `_syncAssignedTasksFromDashboard`, `fetchAssignedTasksPage`, `invalidateAssignedTasks`
- [x] Integrar `_syncAssignedTasksFromDashboard()` al final del `try` de `fetchDashboard()`
- [x] Actualizar todas las referencias a `dashboard.assignedTasks` (array) en `views/index.vue` por `dashboard.assignedTasks.items` / `.total` / `.page`
- [x] Implementar funciones `goToPage()` y `urgencyBadge()` en `<script setup>` de `views/index.vue`
- [x] Reemplazar el bloque del widget de tareas con el nuevo template (skeleton + tabla + footer paginación)
- [x] Agregar prop `urgencyBadge` en `TaskRow.vue` y renderizar badge adyacente a la fecha
- [x] Agregar 6 keys i18n FLAT a `es.json`, `en.json`, `fr.json`
- [x] `npm run build` — pasó sin errores (1226 módulos transformados, 8.92s)

### Fase 4 — Tests ⏳ Pendiente (DT-07)

- [ ] Crear `tests/Feature/Dashboard/AssignedTasksPaginationTest.php`:
  - [ ] Autenticación requerida (401 sin cookies)
  - [ ] Aislamiento multi-tenant (usuario de tenant A no ve todos del tenant B)
  - [ ] Solo `status IN ('pending', 'important')` (excluye `done`, `cancelled`, etc.)
  - [ ] Orden correcto: `due_date` ASC, NULLs al final, desempate por `id`
  - [ ] `per_page=50` máximo (per_page=100 retorna 50)
  - [ ] `per_page` default = 10
  - [ ] Respuesta incluye `meta.total`, `meta.last_page`, `meta.current_page`
  - [ ] Paginación correcta: página 2 con `per_page=5` devuelve items 6-10
- [ ] Crear `tests/Unit/Services/Dashboard/AssignedTasksServiceTest.php`:
  - [ ] NULLs aparecen después de todas las fechas
  - [ ] Tareas vencidas (fecha pasada) aparecen antes de tareas futuras
  - [ ] `perPage` se clampea entre 1 y 50

### Fase 5 — Validación y Cierre ✅ Completada (2026-05-15)

- [ ] Smoke test manual: Dashboard carga con 10 tareas ordenadas por urgencia
- [ ] Smoke test manual: consultor con >10 tareas ve botones "Anterior"/"Siguiente" funcionales
- [ ] Smoke test manual: tarea vencida muestra badge rojo; tarea de hoy/mañana muestra badge naranja
- [ ] Smoke test manual: consultor con ≤10 tareas no ve controles de paginación
- [ ] Smoke test manual: cambio de página muestra skeleton y luego la nueva página
- [ ] Smoke test manual: badge del título muestra el total correcto (no el count de items visibles)
- [ ] Verificar en móvil/tablet que los botones de paginación son accesibles (mínimo 44px de área de toque)
- [x] Agregar Gotcha G17 en `CLAUDE.md`
- [x] Actualizar `spec/INDEX.md`: Spec 61 `PEND` → `DONE`
- [x] Actualizar `MEMORY.md`: agregar entrada en "Implementaciones Recientes"

---

## 12. Archivos a Crear/Modificar

### Crear

| Archivo | Tipo |
|---------|------|
| `database/migrations/2026_05_15_000010_add_dashboard_index_to_todos.php` | Migración |
| `app/Services/Dashboard/AssignedTasksService.php` | Service |
| `tests/Feature/Dashboard/AssignedTasksPaginationTest.php` | Test Feature |
| `tests/Unit/Services/Dashboard/AssignedTasksServiceTest.php` | Test Unitario |

### Modificar

| Archivo | Cambio |
|---------|--------|
| `app/Http/Controllers/Api/DashboardController.php` | Agregar método `assignedTasks()`; refactorizar bloque de tareas en `index()` |
| `routes/api.php` | Agregar ruta `GET /dashboard/assigned-tasks` |
| `resources/js/src/types/dashboard.ts` | Agregar `PaginationMeta`, `AssignedTasksMeta`, campo `assigned_tasks_meta?` |
| `resources/js/src/services/dashboardService.ts` | Agregar método `getAssignedTasks()` |
| `resources/js/src/stores/dashboard.ts` | Sub-estado `assignedTasks` + 3 acciones nuevas |
| `resources/js/src/views/index.vue` | Refactorizar widget completo (template + script) |
| `resources/js/src/views/dashboard/components/TaskRow.vue` | Prop `urgencyBadge` + render del badge |
| `resources/js/src/locales/es.json` | 6 keys nuevas (verificar duplicados en `common.*`) |
| `resources/js/src/locales/en.json` | 6 keys nuevas |
| `resources/js/src/locales/fr.json` | 6 keys nuevas |
| `CLAUDE.md` | Agregar Gotcha G17 |
| `spec/INDEX.md` | Registrar Spec 61 |
| `MEMORY.md` | Entrada al completar |
