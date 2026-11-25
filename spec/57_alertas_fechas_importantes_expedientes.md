---
tipo: spec
numero: 57
modulo: Alertas y Dashboard
dominio: ALRT
estado: DONE
tags: [spec, alertas, done]
relacionados:
  - "[[12_fechas_importantes_dinamicas|Spec 12]]"
  - "[[25_dashboard_centro_de_control|Spec 25]]"
  - "[[32_quick_event_from_important_dates|Spec 32]]"
---
# [[57_alertas_fechas_importantes_expedientes|Spec 57]]: Radar Operativo de Fechas Importantes en el Módulo de Alertas

**Versión:** 1.0
**Fecha:** 2026-05-11
**Branch:** `feature/important-dates-alerts`
**Stack:** Laravel 12 + Vue 3.5 + TypeScript + MySQL 8.0 + Pinia + Sanctum SPA
**Dependencias:** [[07_epic_2.1_expedientes_core|Spec 07]] (Expedientes Core), [[12_fechas_importantes_dinamicas|Spec 12]] (Fechas Importantes Dinámicas), [[22_calendario_legal_integrado|Spec 22]] (Calendario Legal), [[36_documentos_legales_y_alertas_vencimiento|Spec 36]] (Alertas de Vencimiento de Documentos), [[45_sincronizacion_calendarios_externos|Spec 45]] (Sincronización Calendarios Externos)

---

## Resumen Ejecutivo

Extiende el módulo de alertas (`expiration-alerts.vue`) con un segundo **Tab dedicado** a "Fechas Importantes" (`CaseImportantDate`) que actúa como un **radar operativo** de hitos de expedientes. Muestra hitos ocurridos en los últimos 30 días y los próximos 30 días (ventana de 60 días centrada en hoy), permitiendo a los consultores priorizar acciones con información de urgencia codificada por color e integración directa con el calendario.

### Hallazgos Críticos Verificados en el Codebase

| # | Hallazgo | Archivo | Impacto en el Diseño |
|---|---|---|---|
| H1 | `CaseImportantDate` **NO tiene `BelongsToTenant` ni columna `tenant_id`** | `app/Models/CaseImportantDate.php` | Aislamiento multi-tenant vía JOIN explícito con `immigration_cases.tenant_id`. `TenantScope` automático no aplica. |
| H2 | Campos reales: `due_date` (no `date`), `label` (no `name`), FK `case_id` | `app/Models/CaseImportantDate.php` | Todos los contratos API y tipos TypeScript deben usar estos nombres exactos. |
| H3 | Ya existe `ExpirationAlertController` con patrón establecido (filtros, paginación, Resource) | `app/Http/Controllers/Api/ExpirationAlertController.php` | El nuevo controller debe imitar exactamente ese patrón de convenciones. |
| H4 | `expiration-alerts.vue` está acoplado tipográficamente a `LegalDocument` | `resources/js/src/views/alerts/expiration-alerts.vue` | Refuerza la decisión de **Tabs separados** en lugar de tabla unificada. |
| H5 | `EventFormModal` ya soporta `lockedCaseId`, `lockedCaseNumber`, `lockedClientName` y emite `saved` | `resources/js/src/components/EventFormModal.vue:306-318` | Reutilizable sin modificación mayor. Solo requiere ampliar el emit para devolver `eventId`. |
| H6 | No existe columna `calendar_event_id` en `case_important_dates` | `database/migrations/0001_01_01_000006_create_business_tables.php:341-351` | Requiere nueva migración para la funcionalidad de deduplicación. |

### Decisiones Clave

| # | Decisión | Justificación |
|---|---|---|
| 1 | **Cálculo de urgencia en Backend (API Resource)** | Una fuente de verdad para timezone del tenant; permite `ORDER BY urgency_level` en paginación server-side; filtros por urgencia se traducen a cláusulas SQL indexables. |
| 2 | **Endpoint separado `GET /api/alerts/important-dates`** | Coherente con `/expiration-alerts` existente; SRP claro; dominios disjuntos no deben compartir endpoint. |
| 3 | **Tab nuevo en `expiration-alerts.vue`** | `LegalDocument` y `CaseImportantDate` son tipos TypeScript disjuntos; filtros son distintos entre dominios; estándar SaaS (Clio, MyCase). |
| 4 | **JOIN explícito sobre `immigration_cases.tenant_id`** | El modelo no tiene `BelongsToTenant`; la seguridad multi-tenant debe ser explícita y verificable. |
| 5 | **Columna FK `calendar_event_id` con `nullOnDelete`** | Relación 1:1 semántica; `nullOnDelete` garantiza que si el evento se borra el hito reaparece disponible. |
| 6 | **Refetch manual + `visibilitychange`** para actualización | Pragmático sin infraestructura adicional; cambios en hitos son poco frecuentes. |
| 7 | **`counts_by_urgency` en `meta`** del response | Permite badges de contador en pills de filtro sin requests adicionales. |
| 8 | **Reutilizar permisos `cases.view` y `cases.update`** | Evita proliferación de permisos; los consultores que ven expedientes deben ver sus hitos. |

---

## 1. Modelado de Datos

### 1.1 Migración: Columna `calendar_event_id`

Nueva migración para trazar la relación entre un hito y su evento de calendario, habilitando la deduplicación.

```php
// database/migrations/2026_05_11_000001_add_calendar_event_id_to_case_important_dates.php

public function up(): void
{
    Schema::table('case_important_dates', function (Blueprint $table) {
        $table->foreignId('calendar_event_id')
              ->nullable()
              ->after('due_date')
              ->constrained('events')
              ->nullOnDelete();  // si el evento se borra, el hito reaparece como disponible

        $table->index(['case_id', 'calendar_event_id'], 'idx_cid_ceid');
    });
}

public function down(): void
{
    Schema::table('case_important_dates', function (Blueprint $table) {
        $table->dropForeign(['calendar_event_id']);
        $table->dropIndex('idx_cid_ceid');
        $table->dropColumn('calendar_event_id');
    });
}
```

**Por qué columna directa (no tabla pivot):** La relación es semánticamente 1:1 (un hito tiene a lo sumo un evento espejo). Una tabla pivot sería sobre-ingeniería. `nullOnDelete` evita huérfanos: si el usuario borra el evento, el hito reaparece con el botón "Agregar al Calendario" disponible.

### 1.2 Índices de Base de Datos Requeridos

La tabla `case_important_dates` ya tiene `INDEX(case_id)`. Los índices críticos adicionales están en `immigration_cases`:

```sql
-- En immigration_cases (verificar existencia antes de crear)
ALTER TABLE immigration_cases ADD INDEX idx_tenant_active     (tenant_id, deleted_at);
ALTER TABLE immigration_cases ADD INDEX idx_tenant_assigned   (tenant_id, assigned_user_id);
ALTER TABLE immigration_cases ADD INDEX idx_tenant_case_type  (tenant_id, case_type_id);
```

> **Nota:** No se crea `INDEX(tenant_id, due_date)` en `case_important_dates` porque esa tabla no tiene columna `tenant_id` (H1). El optimizador de MySQL usará los índices de `immigration_cases` correctamente para volúmenes moderados.

### 1.3 Esquema Actualizado de `case_important_dates`

```sql
CREATE TABLE case_important_dates (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id          BIGINT UNSIGNED NOT NULL,
    label            VARCHAR(255)    NOT NULL,
    due_date         DATE            NOT NULL,
    sort_order       INT             DEFAULT 0,
    calendar_event_id BIGINT UNSIGNED NULL,  -- NUEVO (Spec 57)
    created_at       TIMESTAMP       NULL,
    updated_at       TIMESTAMP       NULL,

    FOREIGN KEY (case_id)           REFERENCES immigration_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (calendar_event_id) REFERENCES events(id)            ON DELETE SET NULL,
    INDEX idx_due_date   (due_date),
    INDEX idx_cid_ceid   (case_id, calendar_event_id)  -- NUEVO
);
```

---

## 2. Backend — Capa de Dominio

### 2.1 Modelo: `CaseImportantDate`

Agregar la relación y actualizar `$fillable`:

```php
// app/Models/CaseImportantDate.php (modificación)

protected $fillable = [
    'case_id', 'label', 'due_date', 'sort_order', 'calendar_event_id',  // NUEVO
];

// Relación con evento de calendario (nullable)
public function calendarEvent(): BelongsTo
{
    return $this->belongsTo(Event::class, 'calendar_event_id');
}

// Scope para ventana temporal configurable
public function scopeInWindow(Builder $query, Carbon $from, Carbon $to): Builder
{
    return $query->whereBetween('case_important_dates.due_date', [$from, $to]);
}
```

### 2.2 Policy: `CaseImportantDatePolicy`

Necesaria para prevenir acceso cross-tenant (el modelo no tiene `BelongsToTenant`).

```php
// app/Policies/CaseImportantDatePolicy.php

namespace App\Policies;

use App\Models\CaseImportantDate;
use App\Models\User;

class CaseImportantDatePolicy
{
    public function view(User $user, CaseImportantDate $date): bool
    {
        return $user->tenant_id === $date->immigrationCase->tenant_id
            && $user->can('cases.view');
    }

    public function linkEvent(User $user, CaseImportantDate $date): bool
    {
        return $user->tenant_id === $date->immigrationCase->tenant_id
            && $user->can('cases.update');
    }
}
```

Registrar en `AuthServiceProvider::policies()`:

```php
CaseImportantDate::class => CaseImportantDatePolicy::class,
```

### 2.3 Service: `ImportantDateAlertService`

```php
// app/Services/Alert/ImportantDateAlertService.php

namespace App\Services\Alert;

use App\Models\CaseImportantDate;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ImportantDateAlertService
{
    public function getAlerts(int $tenantId, array $filters): LengthAwarePaginator
    {
        $tz    = $this->resolveTenantTimezone($tenantId);
        $today = Carbon::now($tz)->startOfDay();
        $from  = $today->copy()->subDays((int) ($filters['window_past_days']   ?? 30));
        $to    = $today->copy()->addDays((int) ($filters['window_future_days'] ?? 30))->endOfDay();

        $query = CaseImportantDate::query()
            ->select('case_important_dates.*')
            // Multi-tenancy explícita — el modelo no tiene BelongsToTenant (H1)
            ->join('immigration_cases', 'immigration_cases.id', '=', 'case_important_dates.case_id')
            ->where('immigration_cases.tenant_id', $tenantId)
            ->whereNull('immigration_cases.deleted_at')
            ->inWindow($from, $to)
            ->with([
                'immigrationCase:id,tenant_id,case_number,client_id,case_type_id,assigned_user_id',
                'immigrationCase.client:id,first_name,last_name',
                'immigrationCase.caseType:id,name',
                'immigrationCase.assignedUser:id,name',
                'calendarEvent:id,title,start',
            ]);

        $this->applyFilters($query, $filters, $today);
        $this->applySort($query, $filters['sort_by'] ?? 'urgency', $filters['sort_dir'] ?? 'asc', $today);

        return $query->paginate((int) ($filters['per_page'] ?? 25));
    }

    private function applyFilters($query, array $filters, Carbon $today): void
    {
        if (!empty($filters['consultant_id'])) {
            $query->where('immigration_cases.assigned_user_id', $filters['consultant_id']);
        }

        if (!empty($filters['case_type_id'])) {
            $query->where('immigration_cases.case_type_id', $filters['case_type_id']);
        }

        if ($s = $filters['search'] ?? null) {
            $query->where(function ($q) use ($s) {
                $q->where('case_important_dates.label', 'like', "%{$s}%")
                  ->orWhere('immigration_cases.case_number', 'like', "%{$s}%");
            });
        }

        match ($filters['urgency'] ?? 'all') {
            'overdue'   => $query->where('case_important_dates.due_date', '<', $today->toDateString()),
            'today'     => $query->whereDate('case_important_dates.due_date', $today),
            'this_week' => $query->whereBetween('case_important_dates.due_date', [
                                $today->toDateString(),
                                $today->copy()->addDays(7)->toDateString(),
                            ]),
            'upcoming'  => $query->where('case_important_dates.due_date', '>=', $today->toDateString()),
            default     => null,
        };
    }

    private function applySort($query, string $sortBy, string $sortDir, Carbon $today): void
    {
        match ($sortBy) {
            'urgency'     => $query->orderByRaw('ABS(DATEDIFF(case_important_dates.due_date, ?)) ASC', [$today->toDateString()])
                                   ->orderBy('case_important_dates.due_date', 'asc'),
            'case_number' => $query->orderBy('immigration_cases.case_number', $sortDir),
            default       => $query->orderBy('case_important_dates.due_date', $sortDir),
        };
    }

    private function resolveTenantTimezone(int $tenantId): string
    {
        // Obtener timezone del tenant; default UTC
        return \App\Models\Tenant::find($tenantId)?->settings['timezone'] ?? config('app.timezone', 'UTC');
    }
}
```

> **Nota de Seguridad:** `select('case_important_dates.*')` es obligatorio para evitar colisión de columnas tras el JOIN. La autoridad multi-tenant es el WHERE sobre `immigration_cases.tenant_id`, no `TenantScope` (que no aplica a este modelo).

### 2.4 API Resource: `ImportantDateAlertResource`

El Resource es donde se computan los campos derivados de urgencia. Esta es la **única fuente de verdad** para `urgency_level`, `urgency_bucket` y `days_diff`.

```php
// app/Http/Resources/Alert/ImportantDateAlertResource.php

namespace App\Http\Resources\Alert;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportantDateAlertResource extends JsonResource
{
    private Carbon $today;

    public function __construct($resource, Carbon $today)
    {
        parent::__construct($resource);
        $this->today = $today;
    }

    public function toArray(Request $request): array
    {
        $dueDate  = Carbon::parse($this->due_date);
        $daysDiff = $this->today->diffInDays($dueDate, false); // negativo = pasado

        return [
            'id'              => $this->id,
            'case_id'         => $this->case_id,
            'case_number'     => $this->immigrationCase->case_number,
            'case_url'        => "/cases/{$this->case_id}",
            'client_id'       => $this->immigrationCase->client_id,
            'client_name'     => $this->immigrationCase->client->full_name ?? null,
            'case_type'       => [
                'id'   => $this->immigrationCase->caseType?->id,
                'name' => $this->immigrationCase->caseType?->name,
            ],
            'consultant'      => [
                'id'   => $this->immigrationCase->assignedUser?->id,
                'name' => $this->immigrationCase->assignedUser?->name,
            ],
            'label'           => $this->label,
            'due_date'        => $this->due_date,
            'days_diff'       => (int) $daysDiff,
            'urgency_level'   => $this->computeUrgencyLevel($daysDiff),
            'urgency_bucket'  => $this->computeUrgencyBucket($daysDiff),
            'has_calendar_event' => !is_null($this->calendar_event_id)
                                    && !$this->calendarEvent?->trashed(),
            'calendar_event_id'  => $this->calendar_event_id,
        ];
    }

    private function computeUrgencyLevel(float $daysDiff): int
    {
        return match (true) {
            $daysDiff < -7  => 1,  // overdue_critical
            $daysDiff < 0   => 2,  // overdue_recent
            $daysDiff == 0  => 3,  // today
            $daysDiff <= 7  => 4,  // upcoming_week
            default         => 5,  // upcoming_month
        };
    }

    private function computeUrgencyBucket(float $daysDiff): string
    {
        return match (true) {
            $daysDiff < -7  => 'overdue_critical',
            $daysDiff < 0   => 'overdue_recent',
            $daysDiff == 0  => 'today',
            $daysDiff <= 7  => 'upcoming_week',
            default         => 'upcoming_month',
        };
    }
}
```

### 2.5 Controller: `ImportantDateAlertController`

```php
// app/Http/Controllers/Api/ImportantDateAlertController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Alert\ImportantDateAlertCollection;
use App\Models\CaseImportantDate;
use App\Models\Event;
use App\Services\Alert\ImportantDateAlertService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportantDateAlertController extends Controller
{
    public function __construct(private ImportantDateAlertService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CaseImportantDate::class);

        $tenantId = auth()->user()->tenant_id;
        $tz       = $this->service->resolveTenantTimezone($tenantId); // exponer método
        $today    = Carbon::now($tz)->startOfDay();

        $validated = $request->validate([
            'consultant_id'      => ['nullable', 'integer', 'exists:users,id'],
            'case_type_id'       => ['nullable', 'integer', 'exists:case_types,id'],
            'urgency'            => ['nullable', 'string', 'in:overdue,today,this_week,upcoming,all'],
            'search'             => ['nullable', 'string', 'max:100'],
            'sort_by'            => ['nullable', 'string', 'in:due_date,urgency,case_number'],
            'sort_dir'           => ['nullable', 'string', 'in:asc,desc'],
            'per_page'           => ['nullable', 'integer', 'min:1', 'max:100'],
            'page'               => ['nullable', 'integer', 'min:1'],
            'window_past_days'   => ['nullable', 'integer', 'min:1', 'max:90'],
            'window_future_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $paginated = $this->service->getAlerts($tenantId, $validated);

        $resource = ImportantDateAlertCollection::make($paginated, $today);

        return response()->json(array_merge(
            $resource->toArray($request),
            [
                'meta' => array_merge(
                    $resource->paginationMeta($paginated),
                    [
                        'window' => [
                            'from'  => $today->copy()->subDays($validated['window_past_days'] ?? 30)->toDateString(),
                            'to'    => $today->copy()->addDays($validated['window_future_days'] ?? 30)->toDateString(),
                            'today' => $today->toDateString(),
                        ],
                        'counts_by_urgency' => $this->service->getCountsByUrgency($tenantId, $validated, $today),
                    ]
                ),
            ]
        ));
    }

    public function linkEvent(Request $request, int $id): JsonResponse
    {
        $date = CaseImportantDate::findOrFail($id);
        $this->authorize('linkEvent', $date);

        $validated = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
        ]);

        // Verificar que el evento pertenece al mismo tenant
        $event = Event::where('id', $validated['event_id'])
                      ->where('tenant_id', auth()->user()->tenant_id)
                      ->firstOrFail();

        $date->update(['calendar_event_id' => $event->id]);

        return response()->json([
            'data' => new \App\Http\Resources\Alert\ImportantDateAlertResource(
                $date->fresh(['immigrationCase.client', 'immigrationCase.caseType', 'immigrationCase.assignedUser', 'calendarEvent']),
                Carbon::now($this->service->resolveTenantTimezone(auth()->user()->tenant_id))
            ),
        ]);
    }
}
```

### 2.6 Rutas API

Agregar dentro del grupo `auth:sanctum + tenant` en `routes/api.php`:

```php
// routes/api.php — dentro del grupo existente de alertas

Route::prefix('alerts')->group(function () {
    // Existente:
    Route::get('expiration-alerts', [ExpirationAlertController::class, 'index']);

    // NUEVO (Spec 57):
    Route::get('important-dates',             [ImportantDateAlertController::class, 'index']);
    Route::post('important-dates/{id}/link-event', [ImportantDateAlertController::class, 'linkEvent']);
});
```

---

## 3. API Contract

### 3.1 `GET /api/alerts/important-dates`

**Query Parameters:**

| Param | Tipo | Valores | Default |
|---|---|---|---|
| `consultant_id` | int | ID de `users` | (todos) |
| `case_type_id` | int | ID de `case_types` | (todos) |
| `urgency` | string | `overdue` \| `today` \| `this_week` \| `upcoming` \| `all` | `all` |
| `search` | string | Busca en `label`, `case_number` | — |
| `sort_by` | string | `due_date` \| `urgency` \| `case_number` | `urgency` |
| `sort_dir` | string | `asc` \| `desc` | `asc` |
| `per_page` | int | 1-100 | `25` |
| `page` | int | — | `1` |
| `window_past_days` | int | 1-90 | `30` |
| `window_future_days` | int | 1-90 | `30` |

**Respuesta 200:**

```json
{
  "data": [
    {
      "id": 42,
      "case_id": 17,
      "case_number": "INM-2026-0017",
      "case_url": "/cases/17",
      "client_id": 9,
      "client_name": "Maria Lopez",
      "case_type": { "id": 3, "name": "Residencia Permanente" },
      "consultant": { "id": 5, "name": "Juan Perez" },
      "label": "Cita Biométricos",
      "due_date": "2026-05-08",
      "days_diff": -3,
      "urgency_level": 2,
      "urgency_bucket": "overdue_recent",
      "has_calendar_event": false,
      "calendar_event_id": null
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": {
    "current_page": 1,
    "last_page": 4,
    "per_page": 25,
    "total": 87,
    "from": 1,
    "to": 25,
    "window": {
      "from": "2026-04-11",
      "to": "2026-06-10",
      "today": "2026-05-11"
    },
    "counts_by_urgency": {
      "overdue_critical": 3,
      "overdue_recent": 8,
      "today": 2,
      "upcoming_week": 14,
      "upcoming_month": 60
    }
  }
}
```

### 3.2 `POST /api/alerts/important-dates/{id}/link-event`

**Body:**
```json
{ "event_id": 123 }
```

**Respuesta 200:**
```json
{
  "data": { /* ImportantDateAlertResource con has_calendar_event: true */ }
}
```

**Respuesta 403:** El `event_id` pertenece a otro tenant.
**Respuesta 404:** El hito no existe o no pertenece al tenant.

---

## 4. Frontend — Tipos TypeScript

```typescript
// resources/js/src/types/important-date-alert.ts

export type UrgencyBucket =
    | 'overdue_critical'
    | 'overdue_recent'
    | 'today'
    | 'upcoming_week'
    | 'upcoming_month';

export interface ImportantDateAlert {
    id: number;
    case_id: number;
    case_number: string;
    case_url: string;
    client_id: number;
    client_name: string | null;
    case_type: { id: number; name: string } | null;
    consultant: { id: number; name: string } | null;
    label: string;
    due_date: string;           // 'YYYY-MM-DD'
    days_diff: number;          // negativo = pasado, 0 = hoy
    urgency_level: 1 | 2 | 3 | 4 | 5;
    urgency_bucket: UrgencyBucket;
    has_calendar_event: boolean;
    calendar_event_id: number | null;
}

export interface ImportantDateAlertMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    window: { from: string; to: string; today: string };
    counts_by_urgency: Record<UrgencyBucket, number>;
}

export interface ImportantDateFilters {
    consultant_id: number | null;
    case_type_id: number | null;
    urgency: 'overdue' | 'today' | 'this_week' | 'upcoming' | 'all';
    search: string;
    sort_by: 'due_date' | 'urgency' | 'case_number';
    sort_dir: 'asc' | 'desc';
    per_page: number;
    window_past_days: number;
    window_future_days: number;
}
```

---

## 5. Frontend — Service

```typescript
// resources/js/src/services/importantDateAlertService.ts

import api from '@/services/api';
import type { ImportantDateFilters, ImportantDateAlert, ImportantDateAlertMeta } from '@/types/important-date-alert';

export interface ImportantDateAlertResponse {
    data: ImportantDateAlert[];
    meta: ImportantDateAlertMeta & { links: Record<string, string | null> };
}

const importantDateAlertService = {
    async list(filters: Partial<ImportantDateFilters>, page = 1): Promise<ImportantDateAlertResponse> {
        const { data } = await api.get('/alerts/important-dates', {
            params: { ...filters, page },
        });
        return data;
    },

    async linkEvent(dateId: number, eventId: number): Promise<ImportantDateAlert> {
        const { data } = await api.post(`/alerts/important-dates/${dateId}/link-event`, {
            event_id: eventId,
        });
        return data.data;
    },
};

export default importantDateAlertService;
```

---

## 6. Frontend — Store Pinia

```typescript
// resources/js/src/stores/useImportantDatesStore.ts

import { defineStore } from 'pinia';
import { ref, reactive } from 'vue';
import importantDateAlertService from '@/services/importantDateAlertService';
import type {
    ImportantDateAlert,
    ImportantDateAlertMeta,
    ImportantDateFilters,
} from '@/types/important-date-alert';

export const useImportantDatesStore = defineStore('importantDates', () => {
    const items     = ref<ImportantDateAlert[]>([]);
    const meta      = ref<ImportantDateAlertMeta | null>(null);
    const isLoading = ref(false);
    const error     = ref<string | null>(null);

    const filters = reactive<ImportantDateFilters>({
        consultant_id:      null,
        case_type_id:       null,
        urgency:            'all',
        search:             '',
        sort_by:            'urgency',
        sort_dir:           'asc',
        per_page:           25,
        window_past_days:   30,
        window_future_days: 30,
    });

    async function fetch(page = 1): Promise<void> {
        isLoading.value = true;
        error.value     = null;
        try {
            const response = await importantDateAlertService.list(filters, page);
            items.value = response.data;
            meta.value  = response.meta;
        } catch (e: any) {
            error.value = e?.response?.data?.message ?? 'Error loading alerts';
        } finally {
            isLoading.value = false;
        }
    }

    // Actualización optimista tras vincular evento (evita re-fetch)
    function markAsLinkedToEvent(dateId: number, eventId: number): void {
        const item = items.value.find((i) => i.id === dateId);
        if (item) {
            item.calendar_event_id  = eventId;
            item.has_calendar_event = true;
        }
    }

    function resetFilters(): void {
        Object.assign(filters, {
            consultant_id: null, case_type_id: null, urgency: 'all',
            search: '', sort_by: 'urgency', sort_dir: 'asc', per_page: 25,
        });
    }

    return { items, meta, isLoading, error, filters, fetch, markAsLinkedToEvent, resetFilters };
});
```

> **Por qué store dedicado y no estado local:** `useDashboardStore` prevé mostrar un widget de "Hitos urgentes esta semana" reutilizando estos datos. Centralizar evita doble fetch. El store también permite que `AddToCalendarButton` actualice el estado sin prop-drilling.

---

## 7. Frontend — Componentes UI

### 7.1 Árbol de Componentes

```
views/alerts/expiration-alerts.vue           (REFACTORIZADO: shell con tabs)
├── components/alerts/
│   ├── AlertsTabsNav.vue                    (pills Documents | Important Dates con badges)
│   ├── LegalDocumentsAlertsPanel.vue        (contenido ACTUAL extraído sin cambios)
│   ├── ImportantDatesAlertsPanel.vue        (NUEVO — orquestador)
│   │   ├── ImportantDatesFilters.vue        (dropdowns + pills de urgencia)
│   │   ├── ImportantDatesTable.vue          (tabla con sort por columna)
│   │   ├── UrgencyBadge.vue                 (badge reutilizable)
│   │   └── AddToCalendarButton.vue          (toggle Agregar / Ver)
│   └── (reutilizado) EventFormModal.vue     (sin modificación de lógica, ampliar emit)
```

### 7.2 Paleta de Urgencia

| Nivel | Bucket | Condición | Etiqueta i18n | Clase badge (light/dark) |
|---|---|---|---|---|
| 1 | `overdue_critical` | `days_diff < -7` | `alerts.urgency.overdue_critical` | `bg-danger-light text-danger dark:bg-danger/20` |
| 2 | `overdue_recent` | `-7 ≤ days_diff < 0` | `alerts.urgency.overdue_recent` | `bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300` |
| 3 | `today` | `days_diff == 0` | `alerts.urgency.today` | `bg-warning-light text-warning dark:bg-warning/20` |
| 4 | `upcoming_week` | `0 < days_diff ≤ 7` | `alerts.urgency.this_week` | `bg-info-light text-info dark:bg-info/20` |
| 5 | `upcoming_month` | `7 < days_diff ≤ 30` | `alerts.urgency.upcoming` | `bg-success-light text-success dark:bg-success/20` |

### 7.3 `UrgencyBadge.vue`

```vue
<!-- resources/js/src/components/alerts/UrgencyBadge.vue -->
<script lang="ts" setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { UrgencyBucket } from '@/types/important-date-alert';

const props = defineProps<{
    urgencyLevel: 1 | 2 | 3 | 4 | 5;
    urgencyBucket: UrgencyBucket;
    daysDiff: number;
}>();

const { t } = useI18n();

const config: Record<UrgencyBucket, { i18nKey: string; badgeClass: string }> = {
    overdue_critical: { i18nKey: 'alerts.urgency.overdue_critical', badgeClass: 'bg-danger-light text-danger dark:bg-danger/20' },
    overdue_recent:   { i18nKey: 'alerts.urgency.overdue_recent',   badgeClass: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' },
    today:            { i18nKey: 'alerts.urgency.today',            badgeClass: 'bg-warning-light text-warning dark:bg-warning/20' },
    upcoming_week:    { i18nKey: 'alerts.urgency.this_week',        badgeClass: 'bg-info-light text-info dark:bg-info/20' },
    upcoming_month:   { i18nKey: 'alerts.urgency.upcoming',         badgeClass: 'bg-success-light text-success dark:bg-success/20' },
};

const current = computed(() => config[props.urgencyBucket]);

const daysLabel = computed(() => {
    const abs = Math.abs(props.daysDiff);
    if (props.daysDiff === 0)    return '';
    if (props.daysDiff < 0) return `(${t('alerts.days_ago',       { n: abs })})`;
    return                        `(${t('alerts.days_remaining',  { n: abs })})`;
});
</script>

<template>
    <span :class="['inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold', current.badgeClass]">
        {{ t(current.i18nKey) }}
        <span v-if="daysLabel" class="opacity-75">{{ daysLabel }}</span>
    </span>
</template>
```

### 7.4 `AddToCalendarButton.vue`

```vue
<!-- resources/js/src/components/alerts/AddToCalendarButton.vue -->
<script lang="ts" setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ImportantDateAlert } from '@/types/important-date-alert';

const props  = defineProps<{ item: ImportantDateAlert }>();
const emit   = defineEmits<{ 'add-to-calendar': [item: ImportantDateAlert]; 'view-in-calendar': [eventId: number] }>();
const { t }  = useI18n();

const state = computed(() =>
    props.item.has_calendar_event
        ? { label: t('alerts.important_dates.view_in_calendar'), icon: 'icon-eye',       action: 'view' }
        : { label: t('alerts.important_dates.add_to_calendar'),  icon: 'icon-calendar-plus', action: 'add' }
);

function handleClick() {
    if (state.value.action === 'view') {
        emit('view-in-calendar', props.item.calendar_event_id!);
    } else {
        emit('add-to-calendar', props.item);
    }
}
</script>

<template>
    <button
        type="button"
        class="btn btn-sm btn-outline-primary gap-1"
        @click="handleClick"
    >
        <span :class="state.icon" class="size-4" />
        {{ state.label }}
    </button>
</template>
```

### 7.5 `ImportantDatesAlertsPanel.vue` — Orquestador

```vue
<!-- resources/js/src/components/alerts/ImportantDatesAlertsPanel.vue -->
<script lang="ts" setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useImportantDatesStore } from '@/stores/useImportantDatesStore';
import importantDateAlertService from '@/services/importantDateAlertService';
import ImportantDatesFilters from '@/components/alerts/ImportantDatesFilters.vue';
import ImportantDatesTable   from '@/components/alerts/ImportantDatesTable.vue';
import EventFormModal         from '@/components/EventFormModal.vue';
import type { ImportantDateAlert } from '@/types/important-date-alert';

const store  = useImportantDatesStore();
const router = useRouter();

// Modal state
const modal = ref<{
    open: boolean;
    item: ImportantDateAlert | null;
    eventData: Record<string, any>;
}>({ open: false, item: null, eventData: {} });

function openAddToCalendar(item: ImportantDateAlert) {
    modal.value = {
        open:  true,
        item,
        eventData: {
            id:          null,
            title:       `${item.label} — ${item.case_number}`,
            start:       `${item.due_date}T09:00:00`,
            end:         `${item.due_date}T10:00:00`,
            description: '',
        },
    };
}

async function onEventSaved(eventId?: number) {
    modal.value.open = false;
    if (eventId && modal.value.item) {
        try {
            await importantDateAlertService.linkEvent(modal.value.item.id, eventId);
            store.markAsLinkedToEvent(modal.value.item.id, eventId);
        } catch {
            // Link falló: el evento existe pero el hito no queda vinculado.
            // El siguiente refetch corregirá el estado.
        }
    }
}

function viewInCalendar(eventId: number) {
    router.push(`/apps/calendar?event=${eventId}`);
}

// Refetch al volver a la pestaña (visibilitychange)
function onVisibilityChange() {
    if (document.visibilityState === 'visible') store.fetch();
}

onMounted(() => {
    store.fetch();
    document.addEventListener('visibilitychange', onVisibilityChange);
});

onUnmounted(() => {
    document.removeEventListener('visibilitychange', onVisibilityChange);
});
</script>

<template>
    <div class="space-y-4">
        <ImportantDatesFilters @change="store.fetch(1)" />

        <ImportantDatesTable
            :items="store.items"
            :meta="store.meta"
            :is-loading="store.isLoading"
            @page-change="store.fetch"
            @sort-change="(col, dir) => { store.filters.sort_by = col; store.filters.sort_dir = dir; store.fetch(1); }"
            @add-to-calendar="openAddToCalendar"
            @view-in-calendar="viewInCalendar"
        />

        <EventFormModal
            v-if="modal.open"
            :show="modal.open"
            :event-data="modal.eventData"
            :locked-case-id="modal.item?.case_id"
            :locked-case-number="modal.item?.case_number"
            :locked-client-name="modal.item?.client_name ?? undefined"
            @update:show="modal.open = $event"
            @saved="onEventSaved"
        />
    </div>
</template>
```

### 7.6 Flujo "Agregar al Calendario" — Diagrama de Pasos

```
1. Click "Agregar al Calendario" en ImportantDatesTable
        ↓
2. emit 'add-to-calendar' con item → ImportantDatesAlertsPanel
        ↓
3. openAddToCalendar: construir eventData pre-poblado
   { title: label + case_number, start: due_date + T09:00, case_id, client_id }
        ↓
4. Abrir EventFormModal con lockedCaseId/Number/ClientName
   (usuario ajusta hora, asignados, descripción — no puede cambiar el caso)
        ↓
5. Usuario submits → EventFormModal crea el evento
        ↓
6. emit 'saved' con eventId (requires ampliar el emit en EventFormModal)
        ↓
7. onEventSaved(eventId):
   a. POST /api/alerts/important-dates/{dateId}/link-event { event_id }
   b. store.markAsLinkedToEvent(dateId, eventId)  ← actualización optimista
   c. EventSyncObserver sincroniza automáticamente a Google/Outlook
        ↓
8. AddToCalendarButton cambia a "Ver en Calendario"
   Click → router.push('/apps/calendar?event=' + eventId)
```

**Cambio requerido en `EventFormModal.vue`:**

```typescript
// Ampliar defineEmits (retrocompatible — payload opcional)
// ANTES:
const emit = defineEmits(['saved', 'update:show']);

// DESPUÉS:
const emit = defineEmits<{
    saved: [eventId?: number];
    'update:show': [value: boolean];
}>();

// Y en el submit handler, emitir el id del evento creado:
emit('saved', createdEvent.id);
```

---

## 8. Refactor de `expiration-alerts.vue`

El refactor extrae el contenido actual a `LegalDocumentsAlertsPanel.vue` y convierte la vista en un shell con tabs:

```vue
<!-- resources/js/src/views/alerts/expiration-alerts.vue (refactorizado) -->
<script lang="ts" setup>
import { ref } from 'vue';
import { useMeta } from '@/composables/use-meta';
import { useI18n } from 'vue-i18n';
import LegalDocumentsAlertsPanel  from '@/components/alerts/LegalDocumentsAlertsPanel.vue';
import ImportantDatesAlertsPanel  from '@/components/alerts/ImportantDatesAlertsPanel.vue';

useMeta({ title: 'Expiration Alerts' });
const { t }       = useI18n();
const activeTab   = ref<'documents' | 'important-dates'>('documents');
</script>

<template>
    <div>
        <!-- Header -->
        <div class="mb-5 flex items-center justify-between">
            <h5 class="text-lg font-semibold dark:text-white-light">
                {{ t('alerts.title') }}
            </h5>
        </div>

        <!-- Tabs -->
        <div class="mb-5 border-b border-[#e0e6ed] dark:border-[#1b2e4b]">
            <ul class="flex flex-wrap gap-2 font-semibold">
                <li>
                    <button
                        type="button"
                        :class="activeTab === 'documents' ? 'border-b-2 border-primary text-primary' : 'text-gray-500 hover:text-primary'"
                        class="p-3.5 py-2 text-sm"
                        @click="activeTab = 'documents'"
                    >
                        {{ t('alerts.tab_documents') }}
                    </button>
                </li>
                <li>
                    <button
                        type="button"
                        :class="activeTab === 'important-dates' ? 'border-b-2 border-primary text-primary' : 'text-gray-500 hover:text-primary'"
                        class="p-3.5 py-2 text-sm"
                        @click="activeTab = 'important-dates'"
                    >
                        {{ t('alerts.tab_important_dates') }}
                    </button>
                </li>
            </ul>
        </div>

        <!-- Panels -->
        <LegalDocumentsAlertsPanel  v-if="activeTab === 'documents'" />
        <ImportantDatesAlertsPanel  v-if="activeTab === 'important-dates'" />
    </div>
</template>
```

---

## 9. Internacionalización (i18n)

Agregar las siguientes claves **FLAT** a los 3 archivos de `resources/js/src/locales/*.json`: unicamente ES, ES y FR.

> **Regla crítica (G6):** Usar exactamente la notación de punto `"alerts.tab_documents"` como clave de primer nivel. Nunca objetos anidados.

### Claves en inglés (`en.json`)

```json
"alerts.tab_documents":                       "Legal Documents",
"alerts.tab_important_dates":                 "Important Dates",
"alerts.title":                               "Expiration Alerts",
"alerts.urgency.overdue_critical":            "Critical Overdue",
"alerts.urgency.overdue_recent":              "Overdue",
"alerts.urgency.today":                       "Today",
"alerts.urgency.this_week":                   "This Week",
"alerts.urgency.upcoming":                    "Upcoming",
"alerts.days_ago":                            "{n} days ago",
"alerts.days_remaining":                      "{n} days left",
"alerts.important_dates.title":               "Case Milestones",
"alerts.important_dates.empty":               "No milestones in the next 60 days.",
"alerts.important_dates.add_to_calendar":     "Add to Calendar",
"alerts.important_dates.view_in_calendar":    "View in Calendar",
"alerts.important_dates.auto_description":    "Milestone for case {case}",
"alerts.filter.all_consultants":              "All Consultants",
"alerts.filter.all_case_types":               "All Case Types",
"alerts.filter.urgency_all":                  "All",
"alerts.filter.urgency_overdue":              "Overdue Only",
"alerts.filter.urgency_today":                "Today",
"alerts.filter.urgency_this_week":            "This Week",
"alerts.filter.urgency_upcoming":             "Upcoming",
"alerts.column.case":                         "Case",
"alerts.column.client":                       "Client",
"alerts.column.milestone":                    "Milestone",
"alerts.column.due_date":                     "Due Date",
"alerts.column.consultant":                   "Consultant",
"alerts.column.status":                       "Status",
"alerts.column.actions":                      "Actions"
```

### Claves en español (`es.json`)

```json
"alerts.tab_documents":                       "Documentos Legales",
"alerts.tab_important_dates":                 "Fechas Importantes",
"alerts.title":                               "Alertas de Vencimiento",
"alerts.urgency.overdue_critical":            "Vencido Crítico",
"alerts.urgency.overdue_recent":              "Vencido",
"alerts.urgency.today":                       "Hoy",
"alerts.urgency.this_week":                   "Esta Semana",
"alerts.urgency.upcoming":                    "Próximo",
"alerts.days_ago":                            "hace {n} días",
"alerts.days_remaining":                      "{n} días restantes",
"alerts.important_dates.title":               "Hitos de Expedientes",
"alerts.important_dates.empty":               "No hay hitos en los próximos 60 días.",
"alerts.important_dates.add_to_calendar":     "Agregar al Calendario",
"alerts.important_dates.view_in_calendar":    "Ver en Calendario",
"alerts.important_dates.auto_description":    "Hito del expediente {case}",
"alerts.filter.all_consultants":              "Todos los Consultores",
"alerts.filter.all_case_types":               "Todos los Tipos",
"alerts.filter.urgency_all":                  "Todos",
"alerts.filter.urgency_overdue":              "Solo Vencidos",
"alerts.filter.urgency_today":                "Hoy",
"alerts.filter.urgency_this_week":            "Esta Semana",
"alerts.filter.urgency_upcoming":             "Próximos",
"alerts.column.case":                         "Expediente",
"alerts.column.client":                       "Cliente",
"alerts.column.milestone":                    "Hito",
"alerts.column.due_date":                     "Fecha",
"alerts.column.consultant":                   "Consultor",
"alerts.column.status":                       "Estado",
"alerts.column.actions":                      "Acciones"
```

---

## 10. Tests

### 10.1 Feature Tests (PHPUnit)

```php
// tests/Feature/Alert/ImportantDateAlertTest.php

class ImportantDateAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_dates_within_window(): void
    {
        // Crea hitos dentro y fuera de la ventana de 60 días
        // Afirma que solo llegan los de la ventana
    }

    public function test_tenant_isolation_prevents_cross_tenant_access(): void
    {
        // Tenant A no puede ver hitos del Tenant B
        // Espera: respuesta vacía, no 403 (filtrado silencioso por diseño)
    }

    public function test_filter_by_consultant_id(): void {}

    public function test_filter_by_case_type_id(): void {}

    public function test_filter_by_urgency_overdue(): void {}

    public function test_urgency_level_computed_correctly(): void
    {
        // Hito con due_date = today - 10 → urgency_level = 1
        // Hito con due_date = today      → urgency_level = 3
        // Hito con due_date = today + 3  → urgency_level = 4
    }

    public function test_link_event_happy_path(): void
    {
        // POST link-event → 200, calendar_event_id actualizado
    }

    public function test_link_event_rejects_event_from_other_tenant(): void
    {
        // Evento de tenant B no puede linkearse a hito de tenant A → 404
    }

    public function test_sort_by_urgency_orders_closest_first(): void {}

    public function test_sort_by_due_date_asc_and_desc(): void {}
}
```

### 10.2 Factory

```php
// database/factories/CaseImportantDateFactory.php

class CaseImportantDateFactory extends Factory
{
    protected $model = CaseImportantDate::class;

    public function definition(): array
    {
        return [
            'case_id'   => ImmigrationCase::factory(),
            'label'     => $this->faker->sentence(3),
            'due_date'  => $this->faker->dateTimeBetween('-60 days', '+60 days')->format('Y-m-d'),
            'sort_order' => $this->faker->numberBetween(0, 10),
            'calendar_event_id' => null,
        ];
    }

    public function overdue(): self
    {
        return $this->state(['due_date' => now()->subDays(rand(1, 60))->toDateString()]);
    }

    public function today(): self
    {
        return $this->state(['due_date' => now()->toDateString()]);
    }

    public function upcoming(int $days = 7): self
    {
        return $this->state(['due_date' => now()->addDays($days)->toDateString()]);
    }
}
```

---

## 11. Análisis de Performance y Escalabilidad

### 11.1 Estimación de Volumen

| Escenario | Casos activos | Hitos/caso | Ventana 60d (%) | Filas en respuesta |
|---|---|---|---|---|
| Tenant pequeño | 50 | 5 | ~30% | ~75 |
| Tenant mediano | 300 | 5 | ~30% | ~450 |
| Tenant grande | 1000 | 8 | ~30% | ~2400 |

**Conclusión:** Paginación server-side con `per_page=25` es la estrategia correcta. El `total` de `meta` permite mostrar contadores correctos por urgency bucket.

### 11.2 Estrategia de Actualización

| Opción | Veredicto | Razón |
|---|---|---|
| Polling cada N seg | Rechazada | Desperdicio de recursos; cambios en hitos son infrecuentes |
| Boton "Actualizar" + visibilitychange | **Recomendada** | Pragmático, sin infraestructura adicional |
| Broadcasting (Pusher/Reverb) | Sobre-ingeniería | El módulo de alertas no es colaborativo en tiempo real |

**Disparadores de refetch:**
1. Al montar el panel (`onMounted`)
2. Al cambiar cualquier filtro (watcher o event)
3. Al volver a la pestaña del navegador (`visibilitychange === 'visible'`)
4. Tras crear evento en el modal (refetch implícito o `markAsLinkedToEvent` optimista)

---

## 12. Riesgos y Mitigaciones

| # | Riesgo | Severidad | Mitigación |
|---|---|---|---|
| R1 | `CaseImportantDate` sin `BelongsToTenant` — fuga cross-tenant si alguien hace `CaseImportantDate::find($id)` por ID directo | **Alta** | JOIN explícito en Service + `CaseImportantDatePolicy` + test de aislamiento de tenant |
| R2 | Timezone: `due_date` es `DATE` (sin hora). `now()` en servidor vs cliente puede diferir en día | **Media** | Backend computa `today` con TZ del tenant; frontend usa `meta.window.today` como referencia, nunca recalcula |
| R3 | Cambiar `due_date` en el expediente no actualiza el evento de calendario vinculado | **Media** | Documentado como comportamiento esperado. Observer en `CaseImportantDate` para propagación es Spec posterior |
| R4 | `EventFormModal::saved` no emite `eventId` actualmente | **Baja** | Ampliar emit a `saved: [eventId?: number]` — cambio retrocompatible; callers existentes ignoran el payload |
| R5 | Soft-delete de evento no nulifica `calendar_event_id` automáticamente | **Baja** | El accessor `has_calendar_event` verifica `!$this->calendarEvent?->trashed()`; `nullOnDelete` aplica solo a hard-delete |
| R6 | i18n: 16 idiomas a actualizar. Anidación accidental rompe el build de Vite | **Media** | Usar claves FLAT desde el inicio; verificar con linter de i18n existente (G6, G10) |

---

## 13. Checklist de Implementación

### Fase 1 — Backend (cimientos)

- [x] **1.1** Migración `2026_05_12_000001_add_calendar_event_id_to_case_important_dates.php` *(aplicada — `php artisan migrate` ejecutado)*
- [x] **1.2** Modelo `CaseImportantDate`: `calendar_event_id` en `$fillable` + casts, relación `calendarEvent()`, scope `scopeInDateWindow()`
- [x] **1.3** Policy `CaseImportantDatePolicy` (métodos `viewAny` y `update`); registrada en `AppServiceProvider` vía `Gate::policy()`
- [x] **1.4** Resource `App\Http\Resources\ImportantDateAlertResource` con campos derivados via `DATEDIFF()` SQL *(sin subdirectorio; `counts_by_urgency` y `window` se inyectan inline en el controller)*
- [x] **1.5** *(Fusionado en 1.7)* `counts_by_urgency` implementado como método privado `countsByUrgency()` en el controller; sin Collection separada
- [x] **1.6** *(Fusionado en 1.7)* Lógica de query + filtros implementada directamente en `ImportantDateAlertController::index()`; sin Service separado
- [x] **1.7** Controller `App\Http\Controllers\Api\ImportantDateAlertController` (métodos `index` y `linkEvent`)
- [x] **1.8** Rutas en `routes/api.php`: `GET /important-date-alerts` + `POST /important-date-alerts/{id}/link-event` *(ruta sin prefijo `/alerts/` para seguir el patrón de `/expiration-alerts`)*
- [x] **1.9** Factory `CaseImportantDateFactory` actualizada con estados `overdue()`, `today()`, `upcoming()`
- [x] **1.10** Feature tests en `tests/Feature/Alert/ImportantDateAlertTest.php`

### Fase 2 — Frontend (servicios y types)

- [x] **2.1** Tipos TypeScript en `resources/js/src/types/important-date-alert.ts`
- [x] **2.2** Service `resources/js/src/services/importantDateAlertService.ts`
- [x] **2.3** Store `resources/js/src/stores/useImportantDatesStore.ts`

### Fase 3 — Frontend (UI)

- [x] **3.1** Refactor `expiration-alerts.vue` → shell con tabs (breadcrumb preservado)
- [x] **3.2** Contenido extraído a `LegalDocumentsAlertsPanel.vue` sin cambios de lógica
- [x] **3.3** Componente `UrgencyBadge.vue` con paleta de colores por bucket
- [x] **3.4** Componente `AddToCalendarButton.vue` con estados "Agregar" / "Ver"
- [x] **3.5** Componente `ImportantDatesFilters.vue` (dropdowns + pills de urgencia con contadores del `counts_by_urgency`)
- [x] **3.6** Componente `ImportantDatesTable.vue` (tabla con sort headers asc/desc, paginación, loading/empty states)
- [x] **3.7** Orquestador `ImportantDatesAlertsPanel.vue` (integra modal, visibilitychange, actualización optimista)
- [x] **3.8** `EventFormModal.vue` emit extendido: `saved: [eventId?: number]` — retrocompatible; callers existentes sin cambios

### Fase 4 — i18n y QA

- [x] **4.1** 24 claves FLAT agregadas a los 16 archivos de `resources/js/src/locales/*.json` (en/es/fr traducidos; resto con valor en inglés)
- [x] **4.2** Build de Vite ejecutado exitosamente — `✓ built in 8.99s` — sin errores de i18n ni TypeScript
- [ ] **4.3** QA del flujo "Agregar al Calendario" de extremo a extremo *(requiere prueba manual en navegador)*
- [ ] **4.4** QA de sincronización: verificar que el evento aparece en Google/Outlook via `EventSyncObserver` *(requiere prueba manual)*
- [ ] **4.5** Prueba de timezone: crear tenant con TZ distinta al servidor *(requiere prueba manual)*
- [ ] **4.6** Prueba de aislamiento cross-tenant *(cubierta por test automatizado `test_tenant_isolation`)*

---

## Apéndice: Archivos Involucrados

### Archivos Nuevos

| Archivo | Propósito |
|---|---|
| `database/migrations/2026_05_11_000001_add_calendar_event_id_to_case_important_dates.php` | Columna FK para deduplicación de calendario |
| `app/Policies/CaseImportantDatePolicy.php` | Autorización cross-tenant |
| `app/Services/Alert/ImportantDateAlertService.php` | Lógica de negocio: query, filtros, urgencia |
| `app/Http/Resources/Alert/ImportantDateAlertResource.php` | Transformación + campos derivados |
| `app/Http/Resources/Alert/ImportantDateAlertCollection.php` | Wrapper con `counts_by_urgency` |
| `app/Http/Controllers/Api/ImportantDateAlertController.php` | Controlador REST |
| `database/factories/CaseImportantDateFactory.php` | Factory para tests |
| `tests/Feature/Alert/ImportantDateAlertTest.php` | Tests de integración |
| `resources/js/src/types/important-date-alert.ts` | Tipos TypeScript |
| `resources/js/src/services/importantDateAlertService.ts` | Servicio API frontend |
| `resources/js/src/stores/useImportantDatesStore.ts` | Store Pinia |
| `resources/js/src/components/alerts/UrgencyBadge.vue` | Badge de urgencia reutilizable |
| `resources/js/src/components/alerts/AddToCalendarButton.vue` | Botón Agregar/Ver calendario |
| `resources/js/src/components/alerts/ImportantDatesFilters.vue` | Panel de filtros |
| `resources/js/src/components/alerts/ImportantDatesTable.vue` | Tabla con sort |
| `resources/js/src/components/alerts/ImportantDatesAlertsPanel.vue` | Orquestador del tab |
| `resources/js/src/components/alerts/LegalDocumentsAlertsPanel.vue` | Contenido extraído de expiration-alerts.vue |

### Archivos Modificados

| Archivo | Cambio |
|---|---|
| `app/Models/CaseImportantDate.php` | Agregar `$fillable`, relación, scope |
| `app/Providers/AuthServiceProvider.php` | Registrar `CaseImportantDatePolicy` |
| `routes/api.php` | Agregar 2 rutas en grupo alerts |
| `resources/js/src/views/alerts/expiration-alerts.vue` | Refactor a shell con tabs |
| `resources/js/src/components/EventFormModal.vue` | Ampliar emit `saved: [eventId?: number]` |
| `resources/js/src/locales/*.json` (×16) | Agregar ~27 claves FLAT de alertas |
