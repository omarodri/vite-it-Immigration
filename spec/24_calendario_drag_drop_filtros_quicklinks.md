---
tipo: spec
numero: 24
modulo: Calendario y Eventos
dominio: CAL
estado: DONE
tags: [spec, calendario, done]
relacionados:
  - "[[22_calendario_legal_integrado|Spec 22]]"
  - "[[45_sincronizacion_calendarios_externos|Spec 45]]"
---
# [[24_calendario_drag_drop_filtros_quicklinks|Spec 24]]: Calendario Interactivo v2 — Drag & Drop, Filtro Asignado, Quick Links

**Fecha:** 2026-03-16
**Estado:** ✅ COMPLETADO (2026-03-16)
**Epic:** Mejoras de Experiencia en Calendario Legal
**Autor:** Architect Agent

---

## 1. Resumen Ejecutivo

Tres mejoras ortogonales sobre `calendar.vue` y `EventFormModal.vue` que aumentan la fluidez de edición y la navegabilidad hacia expedientes y clientes:

1. **Persistencia reactiva (Drag & Drop):** Mover o redimensionar un evento en FullCalendar persiste automáticamente vía `PATCH /api/events/{id}/reschedule` — sin abrir el modal.
2. **Filtro "Asignado a":** Selector en el header del calendario que lista únicamente los usuarios con eventos en el rango visible, más la opción `@yo` para el usuario autenticado.
3. **Quick Links en el modal:** Iconos de navegación junto al campo "Caso" y "Cliente" que abren `/cases/{id}` y `/clients/{id}` en pestaña nueva.

---

## 2. Estado Actual vs. Objetivo

| Elemento | Estado actual | Estado objetivo |
|---|---|---|
| `eventDrop` / `eventResize` | No implementado (editable: true pero sin callbacks) | Callbacks que llaman `PATCH /reschedule` con rollback |
| `PATCH /events/{id}/reschedule` | No existe | Endpoint mínimo: solo start_date + end_date |
| Filtro "Asignado a" en calendar | No existe | Select derivado de eventos cargados + opción `@yo` |
| Filtro `assigned_to_id` en API | No existe | `->when($request->assigned_to_id, ...)` en index() |
| Quick link al caso en modal | No existe | `<router-link target="_blank">` + icon-external-link |
| Quick link al cliente en modal | No existe | Idem, requiere `client_id` en EventResource |
| `client_id` en EventResource | No expuesto | `'client_id' => $this->client_id` en extendedProps |

---

## 3. Análisis de Impacto Arquitectónico

### 3.1 Backend — Endpoint `PATCH /reschedule`

**Decisión: endpoint dedicado en lugar de reusar `PUT /api/events/{id}`.**

`UpdateEventRequest` ya usa `sometimes`, lo que permitiría técnicamente enviar solo fechas al `PUT`. Sin embargo:
- PUT implica representación completa del recurso (semántica REST)
- Cambios futuros a `UpdateEventRequest` podrían romper el drag & drop silenciosamente
- Un endpoint específico es autoexplicativo en logs de auditoría

El método `reschedule()` solo toca `start_date` y `end_date`, garantizando que `client_name_snapshot`, `case_id` y demás campos no se alteren durante el arrastre.

### 3.2 Filtro Asignado — Derivación Client-Side

**Decisión: derivar assignees del array de eventos ya cargado (sin endpoint extra).**

FullCalendar llama `fetchCalendarEvents` al montar y al navegar. Los eventos retornados incluyen `extendedProps.assigned_to`. Un `computed` Vue itera el array local (< 200 eventos/mes en este contexto) y construye un `Map` de IDs únicos — coste O(n) negligible.

Ventaja: el filtro es automáticamente reactivo al rango de fechas visible. Al cambiar de mes, `events.value` se actualiza y `assigneesInView` recalcula sin round-trip adicional.

### 3.3 `client_id` en EventResource

`Event.$fillable` incluye `client_id` y existe la relación `client()`. El campo está en la tabla pero no se serializa en `EventResource`. Es una FK directa — no requiere `load()` adicional. Fix de una línea.

### 3.4 Optimistic UI y Rollback

FullCalendar ejecuta el movimiento visual antes de llamar el callback (optimistic). El argumento `info` de `eventDrop`/`eventResize` expone `info.revert()` — función nativa que restaura la posición anterior. El rollback se activa únicamente en el bloque `catch` del PATCH.

### 3.5 Concurrencia

Last-write-wins. Para equipos pequeños (consultoría de inmigración) el riesgo de colisión es bajo. Mitigación pragmática: respuesta 409 si `updated_at` del servidor es posterior al `updated_at_check` opcional en el payload. El frontend llama `info.revert()` + toast de aviso.

---

## 4. Backend Laravel

### 4.1 `RescheduleEventRequest`

```php
// app/Http/Requests/Event/RescheduleEventRequest.php
namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleEventRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }
}
```

### 4.2 `EventController::reschedule()`

```php
/**
 * PATCH /api/events/{event}/reschedule
 * Persiste el movimiento o redimensionado desde FullCalendar.
 * Solo actualiza start_date y end_date — no toca snapshot ni case_id.
 */
public function reschedule(RescheduleEventRequest $request, Event $event): EventResource
{
    $event->update($request->validated());
    $event->load(['assignedTo', 'immigrationCase']);
    return new EventResource($event);
}
```

### 4.3 Filtro `assigned_to_id` en `index()`

```php
$events = Event::with(['assignedTo', 'immigrationCase'])
    ->inDateRange($request->start, $request->end)
    ->when($request->case_id, fn ($q, $id) => $q->where('case_id', $id))
    ->when($request->assigned_to_id, fn ($q, $id) => $q->where('assigned_to_id', $id))  // ← AÑADIR
    ->orderBy('start_date')
    ->get();
```

### 4.4 `client_id` en `EventResource`

```php
'extendedProps' => [
    'description'  => $this->description,
    'category'     => $this->category,
    'location'     => $this->location,
    'assigned_to'  => $this->whenLoaded('assignedTo', fn () => ...),
    'case_id'      => $this->case_id,
    'client_id'    => $this->client_id,           // ← AÑADIR
    'case_number'  => $this->whenLoaded('immigrationCase', ...),
    'client_name'  => $this->client_name_snapshot,
],
```

### 4.5 Ruta nueva

```php
// routes/api.php — junto al bloque de events
Route::patch('/events/{event}/reschedule', [EventController::class, 'reschedule']);
```

---

## 5. Frontend Vue.js

### 5.1 `EventEditPayload` — Extensión

```typescript
// components/EventFormModal.vue
export interface EventEditPayload {
    id: number | null;
    title: string;
    start: string;
    end: string;
    description: string;
    category: string;
    assigned_to_id: number | null;
    case_id: number | null;
    case_number: string | null;
    client_id: number | null;          // ← NUEVO
    client_name: string;
    location: string;
    all_day: boolean;
}
```

### 5.2 `calendar.vue` — Cambios

**Estado nuevo:**
```typescript
const events = ref<any[]>([]);                    // almacén local de eventos cargados
const filterAssigneeId = ref<number | null>(null); // filtro activo
const isSavingDrag = ref(false);                   // spinner de drag

const assigneesInView = computed(() => {
    const seen = new Map<number, string>();
    events.value.forEach(ev => {
        const a = ev.extendedProps?.assigned_to;
        if (a?.id) seen.set(a.id, a.name);
    });
    return [...seen.entries()].map(([id, name]) => ({ id, name }));
});
```

**`fetchCalendarEvents` actualizado:**
```typescript
async function fetchCalendarEvents(fetchInfo, successCb, failureCb) {
    try {
        const params: Record<string, any> = {
            start: fetchInfo.startStr,
            end:   fetchInfo.endStr,
        };
        if (filterAssigneeId.value !== null) {
            params.assigned_to_id = filterAssigneeId.value;
        }
        const res = await api.get('/events', { params });
        events.value = res.data.data ?? [];
        successCb(events.value);
    } catch {
        failureCb(new Error(t('calendar.save_failed')));
    }
}
```

**Callbacks de Drag & Drop:**
```typescript
async function handleReschedule(info: any) {
    const id = parseInt(info.event.id);
    isSavingDrag.value = true;
    try {
        await api.patch(`/events/${id}/reschedule`, {
            start_date: info.event.startStr,
            end_date:   info.event.endStr || info.event.startStr,
        });
    } catch {
        info.revert();
        showMessage(t('calendar.save_failed'), 'error');
    } finally {
        isSavingDrag.value = false;
    }
}
```

**`calendarOptions` actualizado:**
```typescript
const calendarOptions = computed(() => ({
    // ... opciones existentes ...
    eventDrop:   (info: any) => handleReschedule(info),
    eventResize: (info: any) => handleReschedule(info),
}));
```

**Header actualizado (template):**
```vue
<!-- Spinner drag -->
<span v-if="isSavingDrag"
    class="animate-spin border-2 border-primary border-l-transparent rounded-full w-4 h-4 inline-block">
</span>

<!-- Filtro asignado -->
<select v-model="filterAssigneeId" class="form-select text-sm py-1 w-auto" @change="refreshCalendar">
    <option :value="null">{{ $t('calendar.filter_all') }}</option>
    <option :value="currentUserId">{{ $t('calendar.filter_me') }}</option>
    <option v-for="a in assigneesInView" :key="a.id" :value="a.id">{{ a.name }}</option>
</select>
```

**`editEvent()` actualizado para propagar `client_id`:**
```typescript
editingEvent.value = {
    // ... campos existentes ...
    case_id:     ext.case_id ?? null,
    case_number: ext.case_number ?? null,
    client_id:   ext.client_id ?? null,   // ← NUEVO
    client_name: ext.client_name ?? '',
    // ...
};
```

### 5.3 `EventFormModal.vue` — Quick Links

**Campo "Caso" — modo global + link:**
```vue
<template v-else>
    <div class="flex gap-2 items-start">
        <div class="flex-1">
            <Multiselect ... />
        </div>
        <router-link
            v-if="params.case_id"
            :to="`/cases/${params.case_id}`"
            target="_blank"
            class="mt-1 flex items-center justify-center w-8 h-9 rounded border
                   border-gray-200 dark:border-gray-700 text-gray-400
                   hover:text-primary hover:border-primary transition-colors shrink-0"
            :title="$t('calendar.open_case')"
        >
            <icon-external-link class="w-4 h-4" />
        </router-link>
    </div>
</template>
```

**Campo "Caso" — modo contextual + link:**
```vue
<template v-if="isContextualMode">
    <div class="flex gap-2 items-center">
        <div class="form-input flex-1 bg-gray-50 dark:bg-gray-900 cursor-not-allowed text-gray-500">
            #{{ props.lockedCaseNumber }}{{ props.lockedClientName ? ' — ' + props.lockedClientName : '' }}
        </div>
        <router-link
            :to="`/cases/${props.lockedCaseId}`"
            target="_blank"
            class="flex items-center justify-center w-8 h-9 rounded border
                   border-gray-200 dark:border-gray-700 text-gray-400
                   hover:text-primary hover:border-primary transition-colors shrink-0"
            :title="$t('calendar.open_case')"
        >
            <icon-external-link class="w-4 h-4" />
        </router-link>
    </div>
</template>
```

**Campo "Cliente" — modo global + link:**
```vue
<div class="mb-5" v-if="!isContextualMode && params.client_name">
    <label>{{ $t('calendar.client_name') }}</label>
    <div class="flex gap-2 items-center">
        <input
            type="text"
            class="form-input flex-1 bg-gray-50 dark:bg-gray-900 cursor-not-allowed text-gray-500"
            :value="params.client_name"
            readonly
        />
        <router-link
            v-if="params.client_id"
            :to="`/clients/${params.client_id}`"
            target="_blank"
            class="flex items-center justify-center w-8 h-9 rounded border
                   border-gray-200 dark:border-gray-700 text-gray-400
                   hover:text-primary hover:border-primary transition-colors shrink-0"
            :title="$t('calendar.open_client')"
        >
            <icon-external-link class="w-4 h-4" />
        </router-link>
    </div>
</div>
```

---

## 6. i18n — Claves Nuevas

### `en.json`
```json
"calendar.filter_all": "All assignees",
"calendar.filter_me": "Me",
"calendar.open_case": "Open case",
"calendar.open_client": "Open client"
```

### `es.json`
```json
"calendar.filter_all": "Todos los asignados",
"calendar.filter_me": "Yo",
"calendar.open_case": "Abrir expediente",
"calendar.open_client": "Abrir cliente"
```

### `fr.json`
```json
"calendar.filter_all": "Tous les assignés",
"calendar.filter_me": "Moi",
"calendar.open_case": "Ouvrir le dossier",
"calendar.open_client": "Ouvrir le client"
```

---

## 7. Icono Requerido

El icono `icon-external-link` debe existir en `resources/js/src/components/icon/`. Si no existe, usar `icon-eye` o `icon-arrow-forward` como alternativa (ya presentes en la plantilla).

---

## 8. Checklist de Implementación

### Backend
- [x] **B-1.** Crear `app/Http/Requests/Event/RescheduleEventRequest.php`
- [x] **B-2.** Añadir método `reschedule()` en `EventController`
- [x] **B-3.** Registrar `Route::patch('/events/{event}/reschedule', ...)` en `routes/api.php`
- [x] **B-4.** Añadir filtro `->when($request->assigned_to_id, ...)` en `EventController::index()`
- [x] **B-5.** Añadir `'client_id' => $this->client_id` en `EventResource::extendedProps`

### Frontend — `EventFormModal.vue`
- [x] **F-1.** Añadir `client_id: number | null` a `EventEditPayload`
- [x] **F-2.** Añadir `params.client_id` al estado interno del modal (`FormParams` + `defaultParams`)
- [x] **F-3.** Propagar `client_id` en `populateForm()`
- [x] **F-4.** Quick link al caso — modo global (junto a Multiselect) — usa `icon-link`
- [x] **F-5.** Quick link al caso — modo contextual (junto al campo readonly) — usa `icon-link`
- [x] **F-6.** Quick link al cliente — modo global (junto al campo readonly de cliente) — usa `icon-link`
- [x] **F-7.** Importar `icon-link` (alternativa disponible en la plantilla)

### Frontend — `calendar.vue`
- [x] **F-8.** Añadir refs: `events`, `filterAssigneeId`, `isSavingDrag`
- [x] **F-9.** Añadir computed `assigneesInView` y `currentUserId`
- [x] **F-10.** Actualizar `fetchCalendarEvents` para almacenar en `events.value` y pasar `assigned_to_id`
- [x] **F-11.** Implementar `handleReschedule(info)` con `info.revert()` en catch
- [x] **F-12.** Añadir `eventDrop` y `eventResize` en `calendarOptions`
- [x] **F-13.** Añadir spinner `isSavingDrag` en el header del calendario
- [x] **F-14.** Añadir `<select>` de filtro "Asignado a" con opción `@yo`
- [x] **F-15.** Propagar `client_id` en `editEvent()` desde `ext.client_id`

### i18n
- [x] **I-1.** Añadir 4 claves en `locales/en.json`
- [x] **I-2.** Añadir 4 claves en `locales/es.json`
- [x] **I-3.** Añadir 4 claves en `locales/fr.json`

### Validación Final
- [x] **V-1.** `npm run build` → 0 errores TypeScript (`✓ built in 8.33s`)
- [x] **V-2.** Drag de evento → PATCH a `/reschedule` con solo start/end en payload
- [x] **V-3.** Resize de evento → mismo endpoint, end_date actualiza
- [x] **V-4.** Fallo de API → `info.revert()` restaura posición visual
- [x] **V-5.** Filtro `@yo` → solo muestra eventos del usuario autenticado
- [x] **V-6.** Filtro por asignado → solo muestra eventos de ese usuario
- [x] **V-7.** Quick link caso → abre `/cases/{id}` en pestaña nueva
- [x] **V-8.** Quick link cliente → abre `/clients/{id}` en pestaña nueva
- [x] **V-9.** Modal sin case_id/client_id → iconos no visibles (v-if correcto)

---

## 9. Archivos a Modificar/Crear

| Archivo | Tipo |
|---|---|
| `app/Http/Requests/Event/RescheduleEventRequest.php` | CREAR |
| `app/Http/Controllers/Api/EventController.php` | MODIFICAR |
| `app/Http/Resources/EventResource.php` | MODIFICAR |
| `routes/api.php` | MODIFICAR |
| `resources/js/src/components/EventFormModal.vue` | MODIFICAR |
| `resources/js/src/views/apps/calendar.vue` | MODIFICAR |
| `resources/js/src/locales/en.json` | MODIFICAR |
| `resources/js/src/locales/es.json` | MODIFICAR |
| `resources/js/src/locales/fr.json` | MODIFICAR |
