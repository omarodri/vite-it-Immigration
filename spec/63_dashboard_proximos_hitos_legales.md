---
tipo: spec
numero: 63
modulo: Alertas y Dashboard
dominio: ALRT
estado: DONE
tags: [spec, alertas, done]
relacionados:
  - "[[57_alertas_fechas_importantes_expedientes|Spec 57]]"
  - "[[25_dashboard_centro_de_control|Spec 25]]"
  - "[[61_dashboard_tareas_paginacion_ordenamiento|Spec 61]]"
  - "[[12_fechas_importantes_dinamicas|Spec 12]]"
  - "[[07_epic_2.1_expedientes_core|Spec 07]]"
---
# [[63_dashboard_proximos_hitos_legales|Spec 63]] — Widget "Próximos Hitos Legales" en el Dashboard

**Dominio:** ALRT / UX  
**Estado:** DONE  
**Relacionados:** 57 (Radar Fechas Importantes), 25 (Dashboard Centro Control), 61 (Widget Tareas Dashboard), 12 (Fechas Importantes v1), 07 (Core Expedientes)  
**Creado:** 2026-05-15  
**Autor:** Análisis de Impacto — Architect Agent

---

## Resumen Ejecutivo

El Dashboard carece de visibilidad inmediata sobre los hitos legales próximos de los expedientes activos. Los consultores deben navegar al módulo de Alertas (`/expiration-alerts`) para ver fechas críticas como audiencias, plazos de envío de pruebas o vencimientos de permisos. Esto genera fricción operativa en la pantalla más visitada del sistema.

Esta feature agrega un widget **"Próximos hitos legales"** al sidebar derecho del Dashboard, visualmente idéntico al widget de "Documentos por vencer" existente. Muestra los 10 hitos más urgentes en una ventana de ±30 días, con badges de urgencia, enlace directo al expediente y estado vacío ilustrativo.

### Hallazgos Críticos Verificados en el Codebase

| # | Hallazgo | Archivo | Impacto en el Diseño |
|---|---|---|---|
| H1 | `CaseImportantDate` NO usa `BelongsToTenant` — aislamiento multi-tenant via JOIN explícito con `cases.tenant_id` (D-07) | `app/Models/CaseImportantDate.php` | Todo query DEBE hacer `join('cases', ...)` + `where('cases.tenant_id', $user->tenant_id)` |
| H2 | La tabla física de `ImmigrationCase` es `cases`, no `immigration_cases` (D-04 / G11) | `app/Models/ImmigrationCase.php` línea `protected $table = 'cases'` | JOINs crudos deben usar `'cases'` |
| H3 | El global scope `SoftDeletes` de `ImmigrationCase` **no se aplica** en JOINs crudos | Laravel Eloquent behavior | Agregar `->whereNull('cases.deleted_at')` explícito para excluir casos eliminados |
| H4 | Para [[57_alertas_fechas_importantes_expedientes|Spec 57]], `urgency_bucket` y `days_diff` se calculan en **PHP/backend** y se serializan en el JSON | `resources/js/src/types/important-date-alert.ts` + `importantDateAlertService.ts` | No existe `computeUrgencyBucket()` en frontend — seguir el patrón establecido y calcular en backend |
| H5 | `UrgencyBadge.vue` ya existe y recibe `urgency-bucket` + `days-diff` como props | `resources/js/src/components/alerts/UrgencyBadge.vue` | Reutilizar directamente — no crear nuevo componente de badge |
| H6 | El endpoint `/api/dashboard` es consolidado — todos los widgets consumen de una sola request | `DashboardController::index()` | Agregar `upcoming_milestones` al response de `index()`, mismo patrón que `expiring_documents` |
| H7 | El patrón de serialización para `expiring_documents` es `->map(fn ...)` inline en el controller | `DashboardController.php` líneas ~96-117 | Usar el mismo patrón inline en `UpcomingMilestonesService` — no crear Resource dedicado |
| H8 | El widget de "Documentos por vencer" usa botón `btn-outline-warning` en el footer y enlaza a `/expiration-alerts` | `resources/js/src/views/index.vue` líneas ~255 | El botón del nuevo widget enlaza también a `/expiration-alerts` — ruta ya existente en Spec 57 |
| H9 | `ImmigrationCase::STATUS_ACTIVE = 'active'` e `STATUS_INACTIVE = 'inactive'` son las dos constantes de estado operativo | `app/Models/ImmigrationCase.php` líneas 70-72 | Filtrar `whereIn('cases.status', ['active', 'inactive'])` |
| H10 | `scopeInDateWindow(Builder $query, string $from, string $to)` ya existe en `CaseImportantDate` | `app/Models/CaseImportantDate.php` | Reutilizar el scope en lugar de `whereBetween` manual |

### Decisiones Clave

| # | Decisión | Justificación |
|---|---|---|
| D1 | **Componente independiente `UpcomingMilestonesWidget.vue`** — no crear `DashboardAlertList.vue` genérico | `ExpirationAlertBadge` y `UrgencyBadge` tienen interfaces distintas; routing diverge (`/cases/` vs `/clients/`); solo 2 widgets no justifican abstracción (Rule of Three) |
| D2 | **`days_diff` y `urgency_bucket` calculados en PHP** — no en frontend | Patrón establecido en Spec 57: el backend ya computa estos campos para `ImportantDateAlert`. Consistencia > micro-optimización; no existe utilidad frontend para esto |
| D3 | **Ventana ±30 días** (30 días pasados + 30 días futuros) | Hitos vencidos recientemente y no marcados como completados siguen siendo accionables |
| D4 | **`upcoming_milestones` embebido en `/api/dashboard`** — no endpoint separado | Todos los widgets del dashboard consumen del mismo endpoint consolidado; la query es selectiva (LIMIT 10, índice sobre `due_date`) |
| D5 | **Posición: entre Upcoming Events y Expiring Documents** en el sidebar derecho | El sidebar se convierte en "columna de alertas": eventos de hoy → hitos de expedientes → documentos por vencer. Jerarquía cronológica lógica |
| D6 | **Serialización inline** con `->map(fn ...)` — no `Resource` dedicado | Mismo patrón de `$expiringDocuments`; 7 campos planos sin transformaciones complejas |
| D7 | **`whereNull('cases.deleted_at')` explícito** en el JOIN | El scope de `SoftDeletes` no aplica en JOINs crudos — este filtro es la única protección contra mostrar hitos de casos eliminados |
| D8 | **Orden triple**: `due_date ASC, sort_order ASC, id ASC` | Determinismo cuando múltiples hitos comparten la misma fecha (`sort_order` es el criterio de negocio configurado por el usuario) |
| D9 | **Reutilizar `UrgencyBadge.vue`** sin modificarlo | El componente ya tiene los 5 buckets y clases de color definidos; props `urgency-bucket` + `days-diff` coinciden exactamente con la interfaz que necesitamos |
| D10 | **Permiso `cases.view`** como guarda en el controller | Un consultor sin acceso a expedientes no debe ver hitos; mismo patrón que `$recentCases` y `$upcomingEvents` |

---

## 1. Base de Datos — Índice (condicional)

### 1.1 Verificación previa

Antes de crear la migración, verificar si ya existe índice sobre `case_important_dates.due_date`:

```bash
grep -rn "due_date\|case_important_dates" database/migrations/ | grep "index\|Index"
```

Si no existe, crear:

### 1.2 Migración — Índice compuesto

```php
<?php
// database/migrations/2026_05_15_000020_add_due_date_index_to_case_important_dates.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_important_dates', function (Blueprint $table) {
            // Cubre whereBetween(due_date) + JOIN sobre case_id en la query del dashboard
            $table->index(
                ['due_date', 'case_id'],
                'idx_caseimportantdates_duedate_case'
            );
        });
    }

    public function down(): void
    {
        Schema::table('case_important_dates', function (Blueprint $table) {
            $table->dropIndex('idx_caseimportantdates_duedate_case');
        });
    }
};
```

**Justificación:** El optimizador usa este índice para `whereBetween('due_date', [$from, $to])` y luego accede al `case_id` incluido en el índice para el JOIN, sin necesidad de leer la fila completa.

---

## 2. Backend — Servicio `UpcomingMilestonesService`

```php
<?php
// app/Services/Dashboard/UpcomingMilestonesService.php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\CaseImportantDate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class UpcomingMilestonesService
{
    public const WINDOW_DAYS_BACK    = 30;
    public const WINDOW_DAYS_FORWARD = 30;
    public const LIMIT               = 10;

    /**
     * Retorna los próximos hitos legales para el tenant del usuario en una ventana de ±30 días.
     *
     * NOTA MULTI-TENANT: CaseImportantDate no usa BelongsToTenant (D-07).
     * El aislamiento se garantiza via JOIN explícito en cases.tenant_id.
     */
    public function getUpcoming(User $user): Collection
    {
        $today = Carbon::today();
        $from  = $today->copy()->subDays(self::WINDOW_DAYS_BACK)->toDateString();
        $to    = $today->copy()->addDays(self::WINDOW_DAYS_FORWARD)->toDateString();

        return CaseImportantDate::query()
            ->select([
                'case_important_dates.id',
                'case_important_dates.case_id',
                'case_important_dates.label',
                'case_important_dates.due_date',
                'case_important_dates.sort_order',
            ])
            ->join('cases', 'case_important_dates.case_id', '=', 'cases.id')
            ->where('cases.tenant_id', $user->tenant_id)
            ->whereNull('cases.deleted_at')                          // soft-delete manual — D-07
            ->whereIn('cases.status', ['active', 'inactive'])
            ->whereNotNull('case_important_dates.due_date')
            ->scopeInDateWindow($from, $to)                          // reutilizar scope del modelo
            ->with([
                'immigrationCase:id,case_number,client_id',
                'immigrationCase.client:id,first_name,last_name',
            ])
            ->orderBy('case_important_dates.due_date', 'asc')
            ->orderBy('case_important_dates.sort_order', 'asc')
            ->orderBy('case_important_dates.id', 'asc')              // desempate determinístico
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (CaseImportantDate $m) => $this->serialize($m, $today));
    }

    private function serialize(CaseImportantDate $m, Carbon $today): array
    {
        $dueDate  = $m->due_date;                                    // Carbon (cast date:Y-m-d)
        $daysDiff = $today->diffInDays($dueDate, false);             // negativo = pasado

        return [
            'id'            => $m->id,
            'case_id'       => $m->case_id,
            'case_number'   => $m->immigrationCase?->case_number,
            'client_name'   => $m->immigrationCase?->client?->full_name,
            'label'         => $m->label,
            'due_date'      => $dueDate?->format('Y-m-d'),
            'days_diff'     => (int) $daysDiff,
            'urgency_bucket' => $this->urgencyBucket((int) $daysDiff),
        ];
    }

    private function urgencyBucket(int $daysDiff): string
    {
        if ($daysDiff < -7) return 'overdue_critical';
        if ($daysDiff < 0)  return 'overdue_recent';
        if ($daysDiff === 0) return 'today';
        if ($daysDiff <= 7) return 'upcoming_week';
        return 'upcoming_month';
    }
}
```

**Notas técnicas:**
- `Carbon::diffInDays($date, false)` devuelve valor negativo si `$dueDate` es anterior a `$today` — equivalente a la lógica de Spec 57.
- `->scopeInDateWindow($from, $to)` llama al scope ya definido en el modelo en lugar de duplicar `whereBetween`.
- El scope debe llamarse como método en el query builder: si Eloquent no lo resuelve directamente sobre el join query, usar `->whereBetween('case_important_dates.due_date', [$from, $to])` como fallback.
- Los buckets de urgencia son **idénticos** a los definidos en `UrgencyBadge.vue` — consistencia garantizada.

---

## 3. Backend — Modificación de `DashboardController`

### 3.1 Agregar al método `index()`

```php
// app/Http/Controllers/Api/DashboardController.php
// Agregar import en la cabecera:
use App\Services\Dashboard\UpcomingMilestonesService;

// Dentro de index(), después del bloque de $expiringDocuments:

$upcomingMilestones = $user->can('cases.view')
    ? app(UpcomingMilestonesService::class)->getUpcoming($user)
    : collect();
```

### 3.2 Agregar al array de respuesta

```php
return response()->json([
    'data' => [
        'metrics'              => [...],
        'assigned_tasks'       => DashboardTodoResource::collection($assignedTasks),
        'assigned_tasks_meta'  => $assignedTasksMeta,
        'upcoming_events'      => DashboardEventResource::collection($upcomingEvents),
        'recent_cases'         => DashboardCaseResource::collection($recentCases),
        'expiring_documents'   => $expiringDocuments,
        'upcoming_milestones'  => $upcomingMilestones,  // ← nuevo
    ],
]);
```

**Impacto en rendimiento:** 1 query adicional con LIMIT 10, índice sobre `due_date`. Estimación: 5–20ms en MySQL con datos típicos. No justifica endpoint separado ni lazy loading.

---

## 4. Backend — Contrato de API

### 4.1 Campo nuevo en `GET /api/dashboard`

```json
{
  "data": {
    "upcoming_milestones": [
      {
        "id": 14,
        "case_id": 88,
        "case_number": "26-PR-RODR-0001",
        "client_name": "Rodríguez García, Omar",
        "label": "Audiencia CISR",
        "due_date": "2026-05-18",
        "days_diff": 3,
        "urgency_bucket": "upcoming_week"
      },
      {
        "id": 22,
        "case_id": 91,
        "case_number": "26-TN-SMITH-0003",
        "client_name": "Smith Johnson, Jane",
        "label": "Envío de pruebas",
        "due_date": "2026-05-10",
        "days_diff": -5,
        "urgency_bucket": "overdue_recent"
      }
    ]
  }
}
```

### 4.2 Tabla de campos serializados

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | `number` | ID de `case_important_dates` |
| `case_id` | `number` | Para construir el enlace `/cases/{case_id}` |
| `case_number` | `string \| null` | Código del expediente (ej. `26-PR-RODR-0001`) |
| `client_name` | `string \| null` | `Client::full_name` (accessor) — respeta formato del tenant |
| `label` | `string` | Tipo de hito (ej. "Audiencia CISR") |
| `due_date` | `string` | Fecha en formato `YYYY-MM-DD` |
| `days_diff` | `number` | Días desde hoy — negativo=pasado, 0=hoy, positivo=futuro |
| `urgency_bucket` | `UrgencyBucket` | `overdue_critical \| overdue_recent \| today \| upcoming_week \| upcoming_month` |

---

## 5. Frontend — Tipos TypeScript

### 5.1 Nuevo interface en `types/dashboard.ts`

```typescript
// resources/js/src/types/dashboard.ts — agregar:

import type { UrgencyBucket } from '@/types/important-date-alert';

export interface DashboardMilestone {
    id: number;
    case_id: number;
    case_number: string | null;
    client_name: string | null;
    label: string;
    due_date: string;           // YYYY-MM-DD
    days_diff: number;          // negativo = vencido, 0 = hoy, positivo = futuro
    urgency_bucket: UrgencyBucket;
}
```

### 5.2 Actualizar `DashboardData`

```typescript
export interface DashboardData {
    metrics: DashboardMetrics;
    assigned_tasks: DashboardTask[];
    upcoming_events: DashboardEvent[];
    recent_cases: DashboardCase[];
    expiring_documents?: DashboardExpiringDocument[];
    upcoming_milestones?: DashboardMilestone[];        // ← nuevo
    assigned_tasks_meta?: AssignedTasksMeta;
}
```

---

## 6. Frontend — Store Pinia `useDashboardStore`

### 6.1 Getter nuevo (sin sub-estado propio — igual que `expiringDocuments`)

```typescript
// resources/js/src/stores/dashboard.ts — agregar en getters:

upcomingMilestones: (state): DashboardMilestone[] =>
    state.data?.upcoming_milestones ?? [],
```

No se necesita sub-estado independiente porque el widget no pagina (fijo en 10 ítems).

---

## 7. Frontend — Componente `UpcomingMilestonesWidget.vue`

```vue
<!-- resources/js/src/views/dashboard/components/UpcomingMilestonesWidget.vue -->

<script lang="ts" setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDashboardStore } from '@/stores/dashboard';
import UrgencyBadge from '@/components/alerts/UrgencyBadge.vue';
import IconClock from '@/components/icon/icon-clock.vue';

const { t } = useI18n();
const dashboard = useDashboardStore();

const milestones = computed(() => dashboard.upcomingMilestones);
</script>

<template>
    <div class="panel">
        <div class="flex items-center justify-between mb-5">
            <h5 class="font-semibold text-lg dark:text-white-light">
                {{ t('dashboard.upcoming_milestones') }}
            </h5>
        </div>

        <!-- Skeleton: carga inicial -->
        <div v-if="dashboard.isLoading && !dashboard.data" class="flex items-center justify-center py-10">
            <span class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10 inline-block align-middle"></span>
        </div>

        <!-- Empty state -->
        <div
            v-else-if="!milestones.length"
            class="flex flex-col items-center justify-center py-6 text-gray-400 dark:text-gray-500"
        >
            <icon-clock class="w-10 h-10 mb-2 opacity-50" />
            <p class="text-sm">{{ t('dashboard.no_upcoming_milestones') }}</p>
        </div>

        <!-- Lista de hitos -->
        <div v-else class="space-y-3">
            <router-link
                v-for="m in milestones"
                :key="m.id"
                :to="`/cases/${m.case_id}`"
                class="flex items-start gap-2 p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
            >
                <UrgencyBadge
                    :urgency-bucket="m.urgency_bucket"
                    :days-diff="m.days_diff"
                    class="shrink-0 mt-0.5"
                />
                <div class="min-w-0">
                    <p class="text-sm font-medium truncate">
                        {{ m.case_number ?? '—' }}
                        <span v-if="m.client_name" class="font-normal text-gray-500">
                            — {{ m.client_name }}
                        </span>
                    </p>
                    <p class="text-xs text-gray-500 truncate">{{ m.label }}</p>
                    <p class="text-xs text-gray-400">{{ m.due_date }}</p>
                </div>
            </router-link>
        </div>

        <!-- Footer — enlace a panel completo de alertas -->
        <router-link
            to="/expiration-alerts"
            class="btn btn-outline-warning w-full mt-5 text-center text-sm"
        >
            {{ t('dashboard.view_all_milestones') }}
        </router-link>
    </div>
</template>
```

**Notas:**
- `UrgencyBadge` recibe exactamente `urgency-bucket` y `days-diff` — misma interfaz que en `ImportantDatesTable.vue` (Spec 57). Sin modificaciones al componente existente.
- El enlace de footer apunta a `/expiration-alerts` — la vista del Radar de Fechas Importantes (Spec 57) que ya está implementada. No se crea una nueva ruta.
- `m.case_number ?? '—'` — fallback defensivo para el caso H9 (cliente eliminado con caso activo).

---

## 8. Frontend — Integración en `views/index.vue`

### 8.1 Import del componente

```typescript
// En <script lang="ts" setup> de resources/js/src/views/index.vue:
import UpcomingMilestonesWidget from '@/views/dashboard/components/UpcomingMilestonesWidget.vue';
```

### 8.2 Posición en el template

Insertar entre el widget de Upcoming Events y Expiring Documents en el sidebar derecho:

```vue
<!-- Right sidebar (1/3) -->
<div class="space-y-6">
    <!-- Upcoming Events (sin cambios) -->
    <div class="panel">...</div>

    <!-- ← NUEVO: Próximos Hitos Legales -->
    <UpcomingMilestonesWidget />

    <!-- Expiring Documents (sin cambios) -->
    <div class="panel">...</div>
</div>
```

---

## 9. Internacionalización (i18n)

**Regla crítica:** Keys FLAT. Ningún valor contiene `<` ni `>` literal. Actualizar los 3 idiomas: `es.json`, `en.json`, `fr.json`.

| Key | es | en | fr |
|-----|----|----|-----|
| `dashboard.upcoming_milestones` | `"Próximos hitos legales"` | `"Upcoming legal milestones"` | `"Prochaines échéances légales"` |
| `dashboard.no_upcoming_milestones` | `"Sin hitos próximos en los próximos 30 días"` | `"No upcoming milestones in the next 30 days"` | `"Aucune échéance à venir dans les 30 prochains jours"` |
| `dashboard.view_all_milestones` | `"Ver todos los hitos"` | `"View all milestones"` | `"Voir toutes les échéances"` |

> **Nota:** Verificar que las keys `alerts.urgency.*` y `alerts.days_overdue` / `alerts.days_remaining` ya existan (fueron creadas en Spec 57) — son requeridas por `UrgencyBadge.vue`.

---

## 10. Riesgos y Edge Cases

| ID | Riesgo | Prob. | Impacto | Mitigación |
|----|--------|-------|---------|-----------|
| R1 | Múltiples hitos la misma fecha — orden no determinístico | MEDIA | BAJA | Orden triple: `due_date ASC, sort_order ASC, id ASC` |
| R2 | Sin hitos en ventana ±30 días | ALTA (común) | BAJA | Empty state con `icon-clock` + mensaje i18n |
| R3 | `due_date = null` en algún registro | BAJA | BAJA | `->whereNotNull('case_important_dates.due_date')` excluye estos registros |
| R4 | Rol `cliente` sin `cases.view` | MEDIA | BAJA | `$user->can('cases.view')` → retorna `collect()` → widget muestra empty state |
| R5 | Caso soft-deleted con hitos vigentes visible en widget | BAJA | ALTA | `->whereNull('cases.deleted_at')` en el JOIN — **crítico** |
| R6 | Cliente eliminado (soft-delete) → `client_name = null` | BAJA | BAJA | Fallback `m.case_number ?? '—'` en template; `client_name` muestra null graciosamente |
| R7 | Regresión de rendimiento en `/api/dashboard` | BAJA | MEDIA | Query LIMIT 10 con índice; medir con `DB::enableQueryLog()` antes/después |
| R8 | Keys i18n de `UrgencyBadge` (`alerts.urgency.*`) ausentes | BAJA | MEDIA | Verificar existencia en los 3 locale files antes de merge |
| R9 | `scopeInDateWindow` no disponible en query con JOIN | BAJA | BAJA | Si el scope no resuelve en el query builder con join, reemplazar por `->whereBetween('case_important_dates.due_date', [$from, $to])` |
| R10 | `diffInDays` de Carbon con `false` no retorna negativo correctamente | BAJA | MEDIA | Verificar en tests: `Carbon::parse('2026-05-10')->diffInDays(Carbon::parse('2026-05-15'), false)` debe retornar `-5` |

---

## 11. Checklist de Implementación

### Fase 1 — Preparación y Base de Datos ✅ Completada (2026-05-15)

- [x] Verificar si existe índice sobre `case_important_dates.due_date` en las migraciones existentes — **ya existe** en migración `0001_01_01_000006_create_business_tables.php` línea 350
- [x] No se crea nueva migración — índice existente es suficiente
- [x] Confirmar que keys i18n `alerts.urgency.*`, `alerts.days_overdue`, `alerts.days_remaining` existen en es/en/fr — **confirmado** (Spec 57)

### Fase 2 — Backend ✅ Completada (2026-05-15)

- [x] Crear `app/Services/Dashboard/UpcomingMilestonesService.php` con método `getUpcoming(User $user)`
- [x] JOIN explícito con `cases` + `whereNull('cases.deleted_at')` + `whereIn('cases.status', ['active','inactive'])`
- [x] `whereBetween` directo (no scope — más explícito con JOIN)
- [x] Eager loading `immigrationCase:id,case_number,client_id` + `immigrationCase.client:id,first_name,last_name`
- [x] `serialize()` con `Carbon::diffInDays($date, false)` — negativo para pasado ✓
- [x] Buckets de urgencia consistentes con `UrgencyBadge.vue`
- [x] Modificar `DashboardController::index()`: import, llamada gateada por `cases.view`, campo en response
- [x] `php -l` sin errores de sintaxis
- [x] `php artisan route:list` — rutas intactas

### Fase 3 — Frontend ✅ Completada (2026-05-15)

- [x] Agregar `import type { UrgencyBucket } from '@/types/important-date-alert'` en `types/dashboard.ts`
- [x] Agregar interface `DashboardMilestone` en `types/dashboard.ts`
- [x] Agregar campo `upcoming_milestones?: DashboardMilestone[]` en `DashboardData`
- [x] Agregar getter `upcomingMilestones` en `stores/dashboard.ts`
- [x] Crear `views/dashboard/components/UpcomingMilestonesWidget.vue`
- [x] Agregar import del componente en `views/index.vue`
- [x] Insertar `<UpcomingMilestonesWidget />` entre Upcoming Events y Expiring Documents (sidebar derecho)
- [x] Agregar 3 keys i18n FLAT a `es.json`, `en.json`, `fr.json`
- [x] `npm run build` — ✅ sin errores (8.90s)

### Fase 4 — Validación Manual ⏳ Pendiente (smoke tests manuales)

- [ ] Smoke test: dashboard carga el widget con hitos en ventana ±30 días
- [ ] Smoke test: tenant sin hitos en ventana → empty state con icono y mensaje
- [ ] Smoke test: usuario sin `cases.view` → widget vacío (no error)
- [ ] Smoke test: hito vencido hace más de 7 días → badge `overdue_critical` (rojo)
- [ ] Smoke test: hito vencido ≤7 días → badge `overdue_recent` (rojo suave)
- [ ] Smoke test: hito para hoy → badge `today` (naranja)
- [ ] Smoke test: hito para dentro de ≤7 días → badge `upcoming_week` (azul)
- [ ] Smoke test: hito para dentro de >7 días → badge `upcoming_month` (verde)
- [ ] Smoke test: clic en fila → navega a `/cases/{case_id}`
- [ ] Smoke test: botón footer → navega a `/expiration-alerts`
- [ ] Smoke test: dark mode — colores de badge y hover correctos

### Fase 5 — Cierre ✅ Completada (2026-05-15)

- [x] Actualizar `spec/INDEX.md`: Spec 63 `PEND` → `DONE`
- [x] Actualizar `MEMORY.md`: agregar entrada en "Implementaciones Recientes"

---

## 12. Archivos a Crear / Modificar

### Crear

| Archivo | Tipo |
|---------|------|
| `app/Services/Dashboard/UpcomingMilestonesService.php` | Service |
| `resources/js/src/views/dashboard/components/UpcomingMilestonesWidget.vue` | Componente Vue |
| `database/migrations/2026_05_15_000020_add_due_date_index_to_case_important_dates.php` | Migración (condicional) |

### Modificar

| Archivo | Cambio |
|---------|--------|
| `app/Http/Controllers/Api/DashboardController.php` | Llamada a `UpcomingMilestonesService` + campo `upcoming_milestones` en response |
| `resources/js/src/types/dashboard.ts` | Agregar `DashboardMilestone` + actualizar `DashboardData` |
| `resources/js/src/stores/dashboard.ts` | Getter `upcomingMilestones` |
| `resources/js/src/views/index.vue` | Import + inserción de `<UpcomingMilestonesWidget />` |
| `resources/js/src/locales/es.json` | 3 keys nuevas |
| `resources/js/src/locales/en.json` | 3 keys nuevas |
| `resources/js/src/locales/fr.json` | 3 keys nuevas |
| `spec/INDEX.md` | Registrar Spec 63 |
| `MEMORY.md` | Entrada al completar |
