---
tipo: spec
numero: 25
modulo: Alertas y Dashboard
dominio: ALRT
estado: DONE
tags: [spec, alertas, done]
relacionados:
  - "[[36_documentos_legales_y_alertas_vencimiento|Spec 36]]"
  - "[[57_alertas_fechas_importantes_expedientes|Spec 57]]"
---
# [[25_dashboard_centro_de_control|Spec 25]] — Dashboard: Centro de Control

**Fecha:** 2026-03-17
**Estado:** ✅ COMPLETADO (2026-03-17)

---

## Resumen Ejecutivo

Reemplazo completo del `index.vue` actual por un Dashboard productivo que consolida métricas, tareas, eventos y expedientes recientes en una sola llamada API (`GET /api/dashboard`).

---

## 1. Análisis: Qué Existe vs. Qué Crear

### Modelos/Tablas existentes utilizables

| Modelo | Tabla | Tenant-aware | Campos clave para dashboard |
|--------|-------|-------------|----------------------------|
| `ImmigrationCase` | `cases` | ✅ | `status`, `progress` (0-100), `stage`, `priority`, `assigned_to` |
| `Todo` | `todos` | ✅ | `assigned_to_id`, `case_id`, `tag`, `priority`, `status`, `due_date` |
| `Event` | `events` | ✅ | `assigned_to_id`, `created_by`, `start_date`, `all_day`, `category` |
| `Client` | `clients` | ✅ | `first_name`, `last_name`, `full_name` (accessor) |
| `CaseType` | `case_types` | ✅ | `name`, `code` |
| `CaseImportantDate` | `case_important_dates` | via case | `case_id`, `label`, `due_date` |

**Nota crítica sobre "Tareas":**
- `Todo` = tareas asignables con `assigned_to_id`, `priority`, `due_date` → **fuente del widget de tareas**
- `CaseTask` = checklist de ciclo de vida del expediente (sin asignado, sin deadline personal) → NO aplica al dashboard

**Fecha límite para Recent Cases:** El modelo `ImmigrationCase` no tiene una columna `deadline` directa. Se usa la primera `CaseImportantDate.due_date >= hoy` via eager loading.

### Lo que NO existe y debe crearse

| Item | Decisión |
|------|----------|
| `user_case_history` table | **Crear** — no hay mecanismo para rastrear expedientes vistos. Activity log solo registra writes. |
| `DashboardController` | Crear |
| `Dashboard*Resource` (3 clases) | Crear |
| `stores/dashboard.ts` | Crear |
| `services/dashboardService.ts` | Crear |
| `types/dashboard.ts` | Crear |
| 4 sub-componentes Vue | Crear |
| Reescritura de `index.vue` | Modificar |

---

## 2. Migración: `user_case_history`

**Archivo:** `database/migrations/2026_03_17_000001_create_user_case_history_table.php`

**Estrategia:** Una fila por par `(user_id, case_id)` — upsert al ver un expediente, sort por `viewed_at DESC`. No se necesita Model con `BelongsToTenant` porque el acceso siempre es por `user_id` + `tenant_id` del usuario autenticado.

```php
Schema::create('user_case_history', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
    $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
    $table->timestamp('viewed_at');

    $table->unique(['user_id', 'case_id']);                   // 1 fila por par
    $table->index(['user_id', 'tenant_id', 'viewed_at']);     // índice para el query de dashboard
});
```

**Cuándo escribir:** En `CaseController::show()`, después de `$this->authorize('view', $case)`:

```php
\DB::table('user_case_history')->upsert(
    [
        'user_id'   => auth()->id(),
        'case_id'   => $case->id,
        'tenant_id' => auth()->user()->tenant_id,
        'viewed_at' => now(),
    ],
    uniqueBy: ['user_id', 'case_id'],
    update:   ['viewed_at']
);
```
→ Un único SQL `INSERT ... ON DUPLICATE KEY UPDATE` — overhead despreciable.

---

## 3. Endpoint: `GET /api/dashboard`

**Ruta a agregar en `routes/api.php`** (dentro del grupo `auth:sanctum` + `tenant`):
```php
Route::get('/dashboard', [DashboardController::class, 'index']);
```

**Archivo:** `app/Http/Controllers/Api/DashboardController.php`

### Lógica del controlador

```php
public function index(Request $request): JsonResponse
{
    $user   = $request->user();
    $today  = now()->toDateString();

    // ── Métricas ──────────────────────────────────────────────────────────
    $activeCasesCount = $user->can('cases.view')
        ? ImmigrationCase::active()->byAssignee($user->id)->count()
        : 0;

    $todayEventsCount = Event::where(fn ($q) =>
            $q->where('assigned_to_id', $user->id)
              ->orWhere('created_by', $user->id)
        )->whereDate('start_date', $today)->count();

    $pendingTodosCount = Todo::where('assigned_to_id', $user->id)
        ->whereIn('status', ['pending', 'important'])
        ->count();

    // ── Tareas Asignadas (máx 50) ─────────────────────────────────────────
    $assignedTasks = $user->can('tasks.view')
        ? Todo::with(['immigrationCase:id,case_number'])
              ->where('assigned_to_id', $user->id)
              ->whereIn('status', ['pending', 'important'])
              ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
              ->orderBy('due_date')
              ->limit(50)
              ->get()
        : collect();

    // ── Próximos Eventos (próximos 10) ────────────────────────────────────
    $upcomingEvents = Event::where(fn ($q) =>
            $q->where('assigned_to_id', $user->id)
              ->orWhere('created_by', $user->id)
        )->where('start_date', '>=', now())
         ->orderBy('start_date')
         ->limit(10)
         ->get();

    // ── Expedientes Recientes (últimos 8 vistos) ───────────────────────────
    $recentCases = collect();
    if ($user->can('cases.view')) {
        $recentCaseIds = DB::table('user_case_history')
            ->where('user_id', $user->id)
            ->where('tenant_id', $user->tenant_id)
            ->orderByDesc('viewed_at')
            ->limit(8)
            ->pluck('case_id');

        if ($recentCaseIds->isNotEmpty()) {
            $recentCases = ImmigrationCase::with([
                    'client:id,first_name,last_name',
                    'caseType:id,name,code',
                    'importantDates' => fn ($q) => $q
                        ->whereNotNull('due_date')
                        ->whereDate('due_date', '>=', $today)
                        ->orderBy('due_date')
                        ->limit(1),
                ])
                ->whereIn('id', $recentCaseIds)
                ->get()
                ->sortBy(fn ($c) => $recentCaseIds->search($c->id))
                ->values();
        }
    }

    return response()->json([
        'data' => [
            'metrics'         => [
                'active_cases_assigned_to_me' => $activeCasesCount,
                'today_events'                => $todayEventsCount,
                'pending_todos'               => $pendingTodosCount,
            ],
            'assigned_tasks'  => DashboardTodoResource::collection($assignedTasks),
            'upcoming_events' => DashboardEventResource::collection($upcomingEvents),
            'recent_cases'    => DashboardCaseResource::collection($recentCases),
        ],
    ]);
}
```

**Nota de rendimiento:** El `BelongsToTenant` global scope se aplica automáticamente en todas las queries de `ImmigrationCase`, `Todo` y `Event` — aislamiento de tenant garantizado sin código extra.

---

## 4. API Resources

### `DashboardTodoResource`
```php
// app/Http/Resources/DashboardTodoResource.php
return [
    'id'          => $this->id,
    'title'       => $this->title,
    'description' => $this->description,
    'tag'         => $this->tag,
    'priority'    => $this->priority,
    'status'      => $this->status,
    'due_date'    => $this->due_date?->format('Y-m-d'),
    'case'        => $this->whenLoaded('immigrationCase', fn () => $this->immigrationCase
        ? ['id' => $this->immigrationCase->id, 'case_number' => $this->immigrationCase->case_number]
        : null
    ),
];
```

### `DashboardEventResource`
```php
// app/Http/Resources/DashboardEventResource.php
return [
    'id'          => $this->id,
    'title'       => $this->title,
    'start_date'  => $this->all_day
                         ? $this->start_date->toDateString()
                         : $this->start_date->toIso8601String(),
    'end_date'    => $this->all_day
                         ? $this->end_date->toDateString()
                         : $this->end_date->toIso8601String(),
    'all_day'     => $this->all_day,
    'category'    => $this->category,
    'hex_color'   => $this->getCategoryHex(),
    'location'    => $this->location,
    'client_name' => $this->client_name_snapshot,
];
```

### `DashboardCaseResource`
```php
// app/Http/Resources/DashboardCaseResource.php
return [
    'id'             => $this->id,
    'case_number'    => $this->case_number,
    'status'         => $this->status,
    'priority'       => $this->priority,
    'priority_label' => $this->priority_label,
    'progress'       => $this->progress,
    'stage'          => $this->stage,
    'stage_label'    => $this->stage_label,
    'client'         => $this->whenLoaded('client', fn () => [
        'id'        => $this->client->id,
        'full_name' => $this->client->full_name,
    ]),
    'case_type'      => $this->whenLoaded('caseType', fn () => [
        'id'   => $this->caseType->id,
        'name' => $this->caseType->name,
        'code' => $this->caseType->code,
    ]),
    'next_deadline'  => $this->whenLoaded('importantDates',
        fn () => $this->importantDates->first()?->due_date?->format('Y-m-d')
    ),
];
```

### JSON Response Shape

```json
{
  "data": {
    "metrics": {
      "active_cases_assigned_to_me": 12,
      "today_events": 3,
      "pending_todos": 7
    },
    "assigned_tasks": [
      {
        "id": 45, "title": "Revisar documentos", "description": "...",
        "tag": "documentos", "priority": "high", "status": "pending",
        "due_date": "2026-03-20",
        "case": { "id": 12, "case_number": "26-RT-MART-0003" }
      }
    ],
    "upcoming_events": [
      {
        "id": 8, "title": "Primera sesión",
        "start_date": "2026-03-18T10:00:00+00:00",
        "end_date": "2026-03-18T11:00:00+00:00",
        "all_day": false, "category": "primera_sesion",
        "hex_color": "#2196f3", "location": "Oficina", "client_name": "María García"
      }
    ],
    "recent_cases": [
      {
        "id": 12, "case_number": "26-RT-MART-0003",
        "status": "active", "priority": "high", "priority_label": "Alta",
        "progress": 45, "stage": "document_collection", "stage_label": "Recolección",
        "client": { "id": 5, "full_name": "Carlos Martínez" },
        "case_type": { "id": 2, "name": "Work Permit", "code": "WK" },
        "next_deadline": "2026-04-01"
      }
    ]
  }
}
```

---

## 5. Frontend: TypeScript Types

**Archivo:** `resources/js/src/types/dashboard.ts`

```typescript
export interface DashboardMetrics {
    active_cases_assigned_to_me: number;
    today_events: number;
    pending_todos: number;
}

export interface DashboardTask {
    id: number;
    title: string;
    description: string | null;
    tag: string | null;
    priority: 'low' | 'medium' | 'high';
    status: 'pending' | 'important';
    due_date: string | null;
    case: { id: number; case_number: string } | null;
}

export interface DashboardEvent {
    id: number;
    title: string;
    start_date: string;
    end_date: string;
    all_day: boolean;
    category: string;
    hex_color: string;
    location: string | null;
    client_name: string | null;
}

export interface DashboardCase {
    id: number;
    case_number: string;
    status: string;
    priority: string;
    priority_label: string;
    progress: number;
    stage: string | null;
    stage_label: string | null;
    client: { id: number; full_name: string } | null;
    case_type: { id: number; name: string; code: string } | null;
    next_deadline: string | null;
}

export interface DashboardData {
    metrics: DashboardMetrics;
    assigned_tasks: DashboardTask[];
    upcoming_events: DashboardEvent[];
    recent_cases: DashboardCase[];
}
```

---

## 6. Pinia Store

**Archivo:** `resources/js/src/stores/dashboard.ts`

- Estado: `data`, `isLoading`, `error`, `lastFetchedAt`
- Getter `isStale`: true si los datos tienen > 5 minutos
- Acción `fetchDashboard(force = false)`: skip si no es stale (excepto refresh manual)
- Acción `invalidate()`: resetea `lastFetchedAt` para forzar recarga en próxima visita

---

## 7. Componentes Vue

**Directorio:** `resources/js/src/views/dashboard/components/`

### `StatCircle.vue`
- Props: `value: number`, `label: string`, `color: string`, `loading?: boolean`
- UI: círculo grande con número centrado + label. Usar ApexCharts `radialBar` o CSS puro (mismo patrón que `analytics.vue`).
- Colores: `primary` (expedientes), `warning` (eventos hoy), `info` (tareas pendientes)

### `TaskRow.vue`
- Props: `task: DashboardTask`
- Columnas: ID · Título · Tag · Vencimiento · Expediente (router-link) · Prioridad badge · Estado badge
- Priority badge: `high` → `badge-danger`, `medium` → `badge-warning`, `low` → `badge-secondary`

### `EventSidebarItem.vue`
- Props: `event: DashboardEvent`
- Layout: dot de color (hex_color inline) + título + nombre cliente + fecha formateada
- Highlight si `start_date` es hoy ("Hoy" en lugar de la fecha)

### `RecentCaseItem.vue`
- Props: `dashboardCase: DashboardCase`
- Layout:
  ```
  [case_number → /cases/id]  [case_type badge]
  client.full_name  ·  stage_label
  ████████░░░░  45%                    (barra de progreso)
  next_deadline o "Sin fecha"
  ```
- Barra de progreso: `progress` % como ancho de `div` con `bg-primary`

---

## 8. Layout `index.vue` — Estructura

```
┌────────────────────────────────────────────────────────────┐
│  Quick Access Cards (grid 2 cols)                          │
│  [📁 Expedientes | Ver lista | + Crear]                    │
│  [👤 Clientes    | Ver lista | + Crear]                    │
├──────────────────────────────────────┬─────────────────────┤
│  Stat Circles (grid 3 cols)          │                     │
│  [●42 Mis exp.]  [●6 Hoy]  [●3 Pen] │  Próximos Eventos   │
├──────────────────────────────────────┤  (sticky sidebar)   │
│  Tareas Asignadas (tabla completa)   │                     │
├──────────────────────────────────────│                     │
│  Expedientes Recientes (grid 2 cols) │  [+ Agregar evento] │
│  8 cards con progress bar            │                     │
└──────────────────────────────────────┴─────────────────────┘
```

Grid CSS: `xl:grid-cols-3` — columna izquierda `xl:col-span-2`, sidebar `xl:col-span-1`.

---

## 9. i18n Keys

Agregar en `en.json`, `es.json`, `fr.json`:

| Key | EN | ES |
|-----|----|----|
| `dashboard.title` | Dashboard | Dashboard |
| `dashboard.expedientes` | Cases | Expedientes |
| `dashboard.clientes` | Clients | Clientes |
| `dashboard.ver_lista` | View list | Ver lista |
| `dashboard.crear_nuevo` | Create new | Crear nuevo |
| `dashboard.mis_expedientes_activos` | My active cases | Mis expedientes activos |
| `dashboard.eventos_hoy` | Today's events | Eventos de hoy |
| `dashboard.tareas_pendientes` | Pending tasks | Tareas pendientes |
| `dashboard.tareas_asignadas` | Assigned tasks | Tareas asignadas |
| `dashboard.proximos_eventos` | Upcoming events | Próximos eventos |
| `dashboard.expedientes_recientes` | Recent cases | Expedientes recientes |
| `dashboard.sin_tareas_pendientes` | No pending tasks | Sin tareas pendientes |
| `dashboard.sin_eventos` | No upcoming events | Sin próximos eventos |
| `dashboard.sin_expedientes_recientes` | No recent cases | Sin expedientes recientes |
| `dashboard.ver_calendario` | Open calendar | Abrir calendario |
| `dashboard.agregar_evento` | Add event | Agregar evento |
| `dashboard.task.id` | ID | ID |
| `dashboard.task.title` | Title | Título |
| `dashboard.task.tag` | Tag | Categoría |
| `dashboard.task.deadline` | Deadline | Vencimiento |
| `dashboard.task.case` | Case | Expediente |
| `dashboard.task.priority` | Priority | Prioridad |
| `dashboard.task.status` | Status | Estado |
| `dashboard.case.progress` | Progress | Progreso |
| `dashboard.case.next_deadline` | Next deadline | Próximo vencimiento |
| `dashboard.case.no_deadline` | No date | Sin fecha |

---

## 10. Riesgos y Seguridad

| Riesgo | Mitigación |
|--------|-----------|
| Fuga de datos cross-tenant en `user_case_history` | Query siempre con `AND tenant_id = auth()->user()->tenant_id` |
| Consultores ven tareas de otros | Todas las queries filtran por `assigned_to_id = auth()->id()` |
| Rendimiento al cargar 3 datasets en login | Caching en store (5 min stale), un único endpoint consolidado |
| `importantDates` N+1 | Eager loading con `->limit(1)` por caso: `with(['importantDates' => fn ($q) => $q->limit(1)])`  |
| `user_case_history` crece sin límite | Opcionalmente: `LIMIT 8` en SELECT + DELETE filas extra periódicamente (no urgente para MVP) |

---

## 11. Checklist de Implementación

> **✅ COMPLETADO (2026-03-17)** — Build: `✓ built in 8.38s`, migración ejecutada (batch 13)

### Backend

- [x] **1.** Crear migración `user_case_history`
- [x] **2.** Ejecutar migración: `php artisan migrate`
- [x] **3.** Crear `app/Http/Resources/DashboardTodoResource.php`
- [x] **4.** Crear `app/Http/Resources/DashboardEventResource.php`
- [x] **5.** Crear `app/Http/Resources/DashboardCaseResource.php`
- [x] **6.** Crear `app/Http/Controllers/Api/DashboardController.php`
- [x] **7.** Modificar `routes/api.php` — agregar `Route::get('/dashboard', ...)`
- [x] **8.** Modificar `CaseController::show()` — agregar upsert a `user_case_history`

### Frontend

- [x] **9.** Crear `resources/js/src/types/dashboard.ts`
- [x] **10.** Crear `resources/js/src/services/dashboardService.ts`
- [x] **11.** Crear `resources/js/src/stores/dashboard.ts`
- [x] **12.** Crear `resources/js/src/views/dashboard/components/StatCircle.vue`
- [x] **13.** Crear `resources/js/src/views/dashboard/components/TaskRow.vue`
- [x] **14.** Crear `resources/js/src/views/dashboard/components/EventSidebarItem.vue`
- [x] **15.** Crear `resources/js/src/views/dashboard/components/RecentCaseItem.vue`
- [x] **16.** Reescribir `resources/js/src/views/index.vue`
- [x] **17.** Agregar keys `dashboard.*` en `en.json`
- [x] **18.** Agregar keys `dashboard.*` en `es.json`
- [x] **19.** Agregar keys `dashboard.*` en `fr.json`

---

## 12. Orden de Implementación (por dependencias)

```
1 → 2 → 3 → 4 → 5 → 6 → 7 → 8   (backend, en orden)
         ↓
         9 → 10 → 11 → 12-15 → 16 → 17-19   (frontend, en orden)
```

Los pasos 3-5 (Resources) deben existir antes de crear el controlador (paso 6).
Los pasos 9-11 (types/service/store) deben existir antes de los componentes (12-16).
