---
tipo: spec
numero: 23
modulo: Calendario y Eventos
dominio: CAL
estado: DONE
tags: [spec, calendario, done]
relacionados:
  - "[[22_calendario_legal_integrado|Spec 22]]"
---
# [[23_eventos_tab_expediente|Spec 23]]: Tab "Eventos" en Vista de Expediente (show.vue)

**Fecha:** 2026-03-16
**Estado:** ✅ COMPLETADO (2026-03-16)
**Epic:** Integración de Eventos en Expediente
**Autor:** Architect Agent

---

## 1. Resumen Ejecutivo

Implementar una pestaña "Eventos" dentro de la vista detallada de expediente (`resources/js/src/views/cases/show.vue`) que muestre y permita gestionar los eventos del calendario vinculados al `case_id` activo. La implementación exige refactorizar el modal de eventos de `calendar.vue` hacia un componente compartido (`EventFormModal.vue`) para eliminar duplicación de lógica y garantizar coherencia visual en todo el sistema.

---

## 2. Estado Actual vs. Objetivo

| Capa | Estado actual | Estado objetivo |
|------|--------------|-----------------|
| `EventController::index()` | Filtra solo por rango de fechas | + filtro `?case_id=` |
| `EventController` | Sin método `clone` | + método `clone` + ruta |
| `calendar.vue` | Modal acoplado (~580 líneas) | Modal extraído, ~200 líneas |
| `EventFormModal.vue` | No existe | Componente compartido reutilizable |
| `CaseEventTab.vue` | No existe | Lista + CRUD + clone vinculado al caso |
| `show.vue` | 6 tabs (sin Eventos) | + tab "Eventos" |

---

## 3. Análisis de Impacto Arquitectónico

### 3.1 Backend — Filtrado de Endpoints

**Decisión: Parámetro `?case_id=` en el endpoint existente.** No se crea `/api/cases/{id}/events`.

El `EventController::index()` ya usa el patrón `->when()` para filtros opcionales — coherente con `TodoController`. El FullCalendar en `calendar.vue` sigue usando el mismo endpoint sin `case_id`, sin breaking changes.

**Cambio mínimo requerido:**
```php
$events = Event::with(['assignedTo', 'immigrationCase'])
    ->inDateRange($request->start, $request->end)
    ->when($request->case_id, fn ($q, $id) => $q->where('case_id', $id))  // ← AÑADIR
    ->orderBy('start_date')
    ->get();
```

### 3.2 Clonación — Integridad de `case_id`

Al clonar un evento vinculado a un caso, la copia **hereda automáticamente el mismo `case_id`**. El `client_name_snapshot` también se hereda del original (ya calculado). No hay opción de desvincular en la clonación — el contexto del expediente es fijo.

`replicate()` + `save()` es una sola operación SQL INSERT (atómica). No se requiere `DB::transaction` para la clonación simple. Si en el futuro se agregan `event_participants`, se necesitaría transacción para el INSERT en la tabla pivot.

```php
public function clone(Event $event): JsonResponse
{
    $newEvent = $event->replicate(['created_by', 'created_at', 'updated_at', 'deleted_at']);
    $newEvent->created_by = auth()->id();
    $newEvent->title      = $event->title . ' (copia)';
    $newEvent->save();

    $newEvent->load(['assignedTo', 'immigrationCase']);

    return (new EventResource($newEvent))->response()->setStatusCode(201);
}
```

### 3.3 Frontend — Decisión de Refactorización

**Decisión: Extraer `EventFormModal.vue` como componente compartido.**

El modal es el componente más complejo (~250 líneas con multiselect, validación de fechas, timezone helpers, búsqueda asíncrona de casos, snapshot de cliente). Duplicarlo entre `calendar.vue` y `CaseEventTab.vue` crearía deuda técnica inmediata: cualquier corrección habría que aplicarla en dos lugares. El patrón `CaseTodoTab` no extrajo su modal porque era más simple; el modal de eventos tiene lógica de negocio suficiente para justificar la extracción.

### 3.4 Arquitectura de Componentes

```
resources/js/src/
├── components/
│   └── EventFormModal.vue          ← NUEVO (compartido)
│
└── views/
    ├── apps/
    │   └── calendar.vue             ← REFACTORED (usa EventFormModal)
    └── cases/
        └── components/
            └── CaseEventTab.vue     ← NUEVO (usa EventFormModal)
```

### 3.5 Diagrama de Flujo de Datos

```
show.vue
└── CaseEventTab (props: caseId, caseNumber, clientName)
    ├── onMounted → GET /api/events?case_id=X
    │              → GET /api/events/assignees
    ├── Lista de eventos (tabla)
    │   └── [Editar]  → EventFormModal (mode: contextual, lockedCaseId=X)
    │   └── [Clonar]  → POST /api/events/{id}/clone
    │   └── [Eliminar]→ DELETE /api/events/{id} + confirm
    └── EventFormModal
        ├── Props: lockedCaseId=X, lockedCaseNumber="26-RT-RODR-0060"
        ├── Campo "Caso": readonly display (sin Multiselect)
        ├── submit → POST /api/events (payload incluye case_id fijo)
        └── @saved → refreshList()

calendar.vue (modo global)
└── EventFormModal
    ├── Props: sin lockedCaseId
    ├── Campo "Caso": VueMultiselect asíncrono
    └── submit → POST /api/events (payload case_id = selected | null)
```

---

## 4. Backend Laravel

### 4.1 `EventController::index()` — Filtro `case_id`

```php
// app/Http/Controllers/Api/EventController.php

public function index(Request $request): AnonymousResourceCollection
{
    $events = Event::with(['assignedTo', 'immigrationCase'])
        ->inDateRange($request->start, $request->end)
        ->when($request->case_id, fn ($q, $id) => $q->where('case_id', $id))
        ->orderBy('start_date')
        ->get();

    return EventResource::collection($events);
}
```

### 4.2 `EventController::clone()` — Método nuevo

```php
// Añadir a EventController

public function clone(Event $event): JsonResponse
{
    $newEvent = $event->replicate(['created_by', 'created_at', 'updated_at', 'deleted_at']);
    $newEvent->created_by = auth()->id();
    $newEvent->title      = $event->title . ' (copia)';
    $newEvent->save();

    $newEvent->load(['assignedTo', 'immigrationCase']);

    return (new EventResource($newEvent))->response()->setStatusCode(201);
}
```

### 4.3 Ruta de clonación

```php
// routes/api.php — dentro del grupo auth:sanctum

Route::post('/events/{event}/clone', [EventController::class, 'clone']);
// (va junto a las rutas de events existentes)
```

---

## 5. API — `EventResource` (ya completo, sin cambios)

El `EventResource` actual ya expone `case_id` en `extendedProps`, lo que permite al frontend pre-llenar el modal al editar. No requiere modificación.

```json
{
  "id": 42,
  "title": "Primera consulta",
  "start": "2026-03-16T14:00:00+00:00",
  "end":   "2026-03-16T15:00:00+00:00",
  "allDay": false,
  "className": "info",
  "extendedProps": {
    "description": "...",
    "category": "primera_sesion",
    "location": "Oficina principal",
    "assigned_to": { "id": 5, "name": "María López" },
    "case_id": 12,
    "case_number": "26-RT-RODR-0060",
    "client_name": "Juan Rodríguez"
  }
}
```

---

## 6. Frontend Vue.js

### 6.1 `EventFormModal.vue` — Componente Compartido

**Ubicación:** `resources/js/src/components/EventFormModal.vue`

#### Props Interface

```typescript
interface Props {
    show: boolean;                     // v-model:show
    eventData?: EventEditPayload | null; // null = crear nuevo, objeto = editar
    lockedCaseId?: number | null;      // Modo contextual: fija el case_id
    lockedCaseNumber?: string;         // Display readonly en modo contextual
    lockedClientName?: string;         // Display readonly en modo contextual
}

const emit = defineEmits<{
    'update:show': [boolean];
    'saved': [];      // El padre llama refreshCalendar() / refreshList()
    'deleted': [];
}>();
```

#### Detección de Modo

```typescript
const isContextualMode = computed(() => props.lockedCaseId != null);
```

```vue
<!-- Campo "Caso" en el modal — condicional por modo -->
<template v-if="isContextualMode">
    <!-- Modo contextual: readonly -->
    <div class="form-input bg-gray-50 dark:bg-gray-900 cursor-not-allowed text-gray-500">
        #{{ lockedCaseNumber }}{{ lockedClientName ? ' — ' + lockedClientName : '' }}
    </div>
</template>
<template v-else>
    <!-- Modo global: búsqueda asíncrona -->
    <Multiselect
        v-model="selectedCase"
        :options="caseOptions"
        :loading="isLoadingCases"
        :internal-search="false"
        label="label"
        track-by="id"
        :placeholder="$t('calendar.search_case')"
        @search-change="onCaseSearch"
        @select="onCaseSelected"
        @remove="onCaseCleared"
    />
</template>
```

#### Validación de Fechas — 4 Reglas

```typescript
// Regla 1: start_date >= now (solo nuevos eventos)
const minStart = computed(() =>
    params.value.id ? '' : dateFormat(new Date())
);

// Regla 2: end_date >= start_date (siempre)
const minEnd = computed(() => params.value.start || '');

// Regla 3: Al cambiar start_date → auto-fill end_date (misma fecha, hora vacía)
function onStartDateChange(event: Event) {
    const dateStr = (event.target as HTMLInputElement).value;
    if (!dateStr) return;
    const startDate = dateStr.split('T')[0];
    const currentEndTime = params.value.end?.split('T')[1] ?? '';
    params.value.end = currentEndTime
        ? `${startDate}T${currentEndTime}`
        : `${startDate}T${dateStr.split('T')[1] ?? ''}`;
    if (params.value.end < dateStr) params.value.end = dateStr;
}

// Regla 4: all_day → campos deshabilitados y no obligatorios
// En template: :disabled="params.all_day" :required="!params.all_day"
```

#### Diseño del Modal (campos en orden)

```
┌───────────────────────────────────────────────────────┐
│ [x]   Agregar Evento / Editar Evento                   │
├───────────────────────────────────────────────────────┤
│ Título del Evento *                                    │
│ [___________________________________]                  │
│                                                       │
│ Asignado a *                                          │
│ [Select consultor...              ▾]                  │
│                                                       │
│ Caso Relacionado                                      │
│ [Modo global: Multiselect búsqueda asíncrona         ]│
│ [Modo contextual: #26-RT-RODR-0060 — Juan Rodríguez  ]│
│                  (campo readonly, no editable)         │
│                                                       │
│ Desde *                  Hasta *                      │
│ [2026-03-16 14:00]       [2026-03-16 15:00]          │
│                                                       │
│ ☐ Todo el día  (si checked → desactiva fechas)        │
│                                                       │
│ Descripción                                           │
│ [Rich Text / textarea                       ]         │
│                                                       │
│ Lugar                                                 │
│ [___________________________________]                  │
│                                                       │
│ Categoría *                                           │
│ ● Primera sesión  ○ Seguimiento                       │
│ ○ Importante      ○ Personal                          │
│                                                       │
│  [Eliminar]         [Cancelar]  [Crear/Actualizar]    │
└───────────────────────────────────────────────────────┘
```

**Nota z-index:** El modal usa `class="relative z-[51]"` — mismo estándar que `CaseTodoTab` y todos los modales de `show.vue`.

### 6.2 `calendar.vue` — Post-Refactoring

Después de extraer el modal, `calendar.vue` pasa de ~580 a ~200 líneas:

```vue
<!-- calendar.vue — solo conserva FullCalendar y delega el modal -->
<FullCalendar ref="calendarRef" :options="calendarOptions" />

<EventFormModal
    v-model:show="isAddEventModal"
    :event-data="editingEvent"
    @saved="refreshCalendar"
    @deleted="refreshCalendar"
/>
```

```typescript
// Script reducido — eliminar todo lo del modal:
// - params, defaultParams, isSaving
// - assignees, caseOptions, selectedCase, isLoadingCases
// - saveEvent(), deleteEvent(), onCaseSearch(), onCaseSelected()
// - toUtcIso(), toLocalDatetimeInput()
// - onStartDateChange()
// Conservar: calendarRef, calendarOptions, editingEvent, isAddEventModal
// Conservar: editEvent(), editDate(), dateFormat(), refreshCalendar()
```

### 6.3 `CaseEventTab.vue` — Lista de Eventos

**Ubicación:** `resources/js/src/views/cases/components/CaseEventTab.vue`

**Props:**
```typescript
defineProps<{
    caseId: number;
    caseNumber: string;
    clientName: string;
}>()
```

**Layout de la lista:**

```vue
<!-- Toolbar -->
<div class="flex flex-wrap items-center gap-3 mb-4">
    <button @click="openAddModal()" class="btn btn-primary btn-sm gap-1">
        <icon-plus /> {{ $t('cases.event_add') }}
    </button>
    <input v-model="searchQuery" @keyup="onSearch" class="form-input text-sm py-1.5" />
    <select v-model="filterAssigneeId" class="form-select text-sm py-1.5 w-auto">
        <option :value="null">{{ $t('todo_filter_all_assignees') }}</option>
        <option v-for="a in assignees" :value="a.id">{{ a.name }}</option>
    </select>
</div>

<!-- Filtros de categoría -->
<div class="flex gap-2 flex-wrap mb-4">
    <button
        v-for="cat in CATEGORY_FILTERS"
        :key="cat.value"
        class="px-3 py-1 rounded-full text-sm font-medium transition-colors"
        :class="filterCategory === cat.value ? cat.solidClass : 'bg-gray-100 dark:bg-gray-800 text-gray-600 hover:bg-primary/10 hover:text-primary'"
        @click="setCategory(cat.value)"
    >{{ $t(cat.i18nKey) }}</button>
</div>

<!-- Tabla -->
<table class="table-hover">
    <tbody>
        <tr v-for="ev in events" :key="ev.id" class="group cursor-pointer">
            <td @click="openEditModal(ev)">
                <div class="font-semibold group-hover:text-primary">{{ ev.title }}</div>
                <div class="text-white-dark text-sm line-clamp-1">{{ ev.extendedProps.description }}</div>
            </td>
            <td>
                <span class="badge rounded-full" :class="categoryBadgeClass(ev.extendedProps.category)">
                    {{ $t('calendar.category_' + ev.extendedProps.category) }}
                </span>
            </td>
            <td class="whitespace-nowrap text-sm text-white-dark">
                {{ formatEventDate(ev.start) }}
            </td>
            <td>
                <!-- Avatar del asignado -->
                <div v-if="ev.extendedProps.assigned_to"
                     class="grid place-content-center h-7 w-7 rounded-full bg-primary text-white text-xs font-semibold"
                     :title="ev.extendedProps.assigned_to.name">
                    {{ getInitials(ev.extendedProps.assigned_to.name) }}
                </div>
            </td>
            <td>
                <!-- Menú de acciones -->
                <Popper placement="bottom-end" offsetDistance="0">
                    <a href="javascript:;"><icon-horizontal-dots class="rotate-90 opacity-70" /></a>
                    <template #content="{ close }">
                        <ul @click="close()">
                            <li><a @click="openEditModal(ev)"><icon-pencil-paper /> {{ $t('todo_edit') }}</a></li>
                            <li><a @click="cloneEvent(ev)"><icon-copy /> {{ $t('cases.event_clone') }}</a></li>
                            <li><a @click="confirmDelete(ev)"><icon-trash-lines /> {{ $t('todo_delete') }}</a></li>
                        </ul>
                    </template>
                </Popper>
            </td>
        </tr>
    </tbody>
</table>

<!-- Modal compartido -->
<EventFormModal
    v-model:show="showModal"
    :event-data="editingEvent"
    :locked-case-id="caseId"
    :locked-case-number="caseNumber"
    :locked-client-name="clientName"
    @saved="refreshList"
    @deleted="refreshList"
/>
```

### 6.4 Mapa de Badges — Hardcoded (Tailwind JIT)

Las clases dinámicas requieren strings literales — mismo patrón que `todolist.vue`:

```typescript
const CATEGORY_BADGE_OUTLINE: Record<string, string> = {
    primera_sesion: 'badge-outline-info',
    seguimiento:    'badge-outline-warning',
    importante:     'badge-outline-danger',
    personal:       'badge-outline-success',
};

const CATEGORY_BADGE_SOLID: Record<string, string> = {
    primera_sesion: 'text-white bg-info',
    seguimiento:    'text-white bg-warning',
    importante:     'text-white bg-danger',
    personal:       'text-white bg-success',
};

function categoryBadgeClass(category: string): string {
    return CATEGORY_BADGE_OUTLINE[category] ?? 'badge-outline-primary';
}
```

### 6.5 Integración en `show.vue`

```typescript
// Imports
import CaseEventTab from '@/views/cases/components/CaseEventTab.vue';

// Tabs array — insertar entre 'todos' y 'documents'
const tabs = computed(() => [
    { id: 'info',      label: 'cases.tab_information' },
    { id: 'lifecycle', label: 'cases.tab_lifecycle' },
    { id: 'timeline',  label: 'cases.tab_timeline' },
    { id: 'invoices',  label: 'cases.tab_invoices' },
    { id: 'todos',     label: 'cases.tab_todos' },
    { id: 'events',    label: 'cases.tab_events' },   // ← NUEVO
    { id: 'documents', label: 'cases.tab_documents' },
]);
```

```vue
<!-- Tab content en show.vue -->
<div v-else-if="activeTab === 'events'">
    <CaseEventTab
        :case-id="currentCase.id"
        :case-number="currentCase.case_number"
        :client-name="currentCase.client?.full_name ?? ''"
    />
</div>
```

---

## 7. i18n — Claves Nuevas

### `en.json`
```json
"cases.tab_events": "Events",
"cases.event_add": "Add Event",
"cases.event_clone": "Clone"
```

### `es.json`
```json
"cases.tab_events": "Eventos",
"cases.event_add": "Agregar Evento",
"cases.event_clone": "Clonar"
```

### `fr.json`
```json
"cases.tab_events": "Événements",
"cases.event_add": "Ajouter un événement",
"cases.event_clone": "Dupliquer"
```

*Las claves `calendar.category_*`, `calendar.assigned_to`, etc. ya existen desde la implementación del spec 22.*

---

## 8. Riesgos y Mitigaciones

### 8.1 Regresión en `calendar.vue`

Al refactorizar para usar `EventFormModal`, existe riesgo de romper el comportamiento del calendario.

**Mitigación:** Mantener la misma interfaz de `editingEvent` (mismo objeto que hoy pasa `editEvent(data)`) y verificar que `@saved` → `refreshCalendar()` funciona antes de continuar con `CaseEventTab`.

### 8.2 FullCalendar vs. Lista

`calendar.vue` usa FullCalendar con event source function. `CaseEventTab` usa una lista simple. El formato de datos del `EventResource` es compatible con ambos — la lista simplemente accede a `ev.start`, `ev.title`, `ev.extendedProps.*`.

### 8.3 Caso archivado dentro de `CaseEventTab`

Si el caso está archivado, la pestaña de eventos sigue mostrando los eventos históricos (correcto). Para **crear nuevos eventos** desde un caso archivado, se puede evaluar deshabilitar el botón "Agregar Evento" si `currentCase.status !== 'active'` — decisión de negocio a confirmar durante implementación.

---

## 9. Checklist de Implementación

### Backend
- [x] **1.** Modificar `EventController::index()` — añadir filtro `->when($request->case_id, ...)`
- [x] **2.** Añadir método `clone()` en `EventController`
- [x] **3.** Registrar ruta `Route::post('/events/{event}/clone', ...)` en `routes/api.php`

### Frontend — Componente Modal Compartido
- [x] **4.** Crear `resources/js/src/components/EventFormModal.vue`
  - Props: `show` (v-model), `eventData`, `lockedCaseId`, `lockedCaseNumber`, `lockedClientName`
  - Emits: `update:show`, `saved`, `deleted`
  - Computed `isContextualMode` para detectar modo
  - Campo "Caso": Multiselect asíncrono (global) / readonly display (contextual)
  - Validación fechas: 4 reglas (min-start, min-end, auto-fill end, all_day desactiva)
  - Timezone helpers: `toUtcIso()`, `toLocalDatetimeInput()`
  - CRUD: `saveEvent()` y `deleteEvent()` con confirmación SweetAlert
  - `z-[51]` en el Dialog

### Frontend — Refactoring `calendar.vue`
- [x] **5.** Eliminar bloque del modal del template de `calendar.vue`
- [x] **6.** Eliminar lógica del modal del script de `calendar.vue` (params, assignees, caseOptions, saveEvent, deleteEvent, etc.)
- [x] **7.** Importar y usar `EventFormModal` en `calendar.vue` con `v-model:show` + `@saved` + `@deleted`
- [x] **8.** Verificar que `calendar.vue` conserva toda su funcionalidad (build + prueba manual)

### Frontend — Tab de Eventos
- [x] **9.** Crear `resources/js/src/views/cases/components/CaseEventTab.vue`
  - Props: `caseId`, `caseNumber`, `clientName`
  - Fetch en `onMounted`: `GET /api/events?case_id=X` + `GET /api/events/assignees`
  - Filtros: búsqueda (debounce 400ms) + categoría (chips) + asignado (select)
  - Tabla con: título+desc, badge categoría (mapa hardcoded), fecha, avatar asignado, menú ⋮
  - Acciones: Editar (modal), Clonar (`POST /api/events/{id}/clone`), Eliminar (confirm)
  - Usa `EventFormModal` en modo contextual (`lockedCaseId`, `lockedCaseNumber`, `lockedClientName`)
  - Loading spinner, estado vacío con mensaje i18n
- [x] **10.** Actualizar `show.vue`
  - Importar `CaseEventTab`
  - Añadir `{ id: 'events', label: 'cases.tab_events' }` al array `tabs`
  - Añadir bloque `v-else-if="activeTab === 'events'"` con `<CaseEventTab>`
  - Pasar `:client-name="currentCase.client?.full_name ?? ''"`

### i18n
- [x] **11.** Añadir `cases.tab_events`, `cases.event_add`, `cases.event_clone` en `locales/en.json`
- [x] **12.** Añadir mismas claves en `locales/es.json`
- [x] **13.** Añadir mismas claves en `locales/fr.json`

### Validación Final
- [x] **14.** Ejecutar `npm run build` — verificar 0 errores TypeScript
- [x] **15.** Verificar `calendar.vue` (modo global) funciona correctamente post-refactoring
- [x] **16.** Verificar pestaña "Eventos" en `show.vue` filtra por `case_id`
- [x] **17.** Verificar clonación hereda `case_id` del original

---

## 10. Notas Técnicas Finales

- **`replicate()`** de Eloquent excluye automáticamente `id`, `created_at`, `updated_at`. La lista de exclusiones explícitas en el método `clone` solo necesita `created_by` y `deleted_at`.
- **[[BelongsToTenant]]** auto-asigna `tenant_id` en la creación — no se necesita asignarlo manualmente en `clone()`.
- **FullCalendar event source** en `calendar.vue` recibe el array del API directamente. El `EventResource` formatea `start`/`end` como ISO 8601 con offset — compatible con FullCalendar y también con el formato de display de la lista en `CaseEventTab`.
- **Paginación en CaseEventTab:** No implementar paginación en MVP — los eventos por caso serán pocos. Si escala, agregar `per_page` + paginación en una iteración posterior.
- **`Popper` está registrado globalmente** en `main.ts` — no necesita import local en `CaseEventTab`.
