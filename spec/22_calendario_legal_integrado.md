---
tipo: spec
numero: 22
modulo: Calendario y Eventos
dominio: CAL
estado: DONE
tags: [spec, calendario, done]
relacionados:
  - "[[23_eventos_tab_expediente|Spec 23]]"
  - "[[24_calendario_drag_drop_filtros_quicklinks|Spec 24]]"
---
# [[22_calendario_legal_integrado|Spec 22]]: Calendario Legal Integrado — Eventos vinculados a Expedientes

**Fecha:** 2026-03-16
**Estado:** ✅ COMPLETADO (2026-03-16)
**Epic:** Módulo de Calendario Profesional
**Autor:** Architect Agent

---

## 1. Resumen Ejecutivo

Transformar el módulo de Calendario (`resources/js/src/views/apps/calendar.vue`) de una herramienta genérica con estado local en un sistema de agendamiento legal totalmente integrado al backend. Los eventos estarán vinculados a expedientes (`cases`) y responsables (`users`), con soporte completo de i18n (ES/EN/FR) y vistas por año, mes, semana y día.

---

## 2. Estado Actual vs. Objetivo

| Capa | Estado actual | Estado objetivo |
|------|--------------|-----------------|
| DB `events` | Migración existe: `case_id`, `client_id`, `category`, `created_by` | + `assigned_to_id`, `client_name_snapshot` |
| Backend | **Cero**: sin Model, Controller, Resource, Request ni rutas | Implementación completa |
| Frontend `calendar.vue` | 100% local state, texto hardcoded en inglés | API + i18n + nuevos campos + nuevas categorías |

---

## 3. Modelado de Datos

### 3.1 Nueva Migración Requerida

La tabla `events` existente (migración `2026_02_08_221208_create_events_table.php`) tiene `created_by` (autoría) pero carece de `assigned_to_id` (responsable) y `client_name_snapshot` (histórico desnormalizado).

```php
// Nueva migración: add_assigned_to_id_and_client_snapshot_to_events_table
$table->foreignId('assigned_to_id')
      ->nullable()
      ->after('created_by')
      ->constrained('users')
      ->nullOnDelete();

$table->string('client_name_snapshot')
      ->nullable()
      ->after('case_id');
```

### 3.2 Esquema Final de `events`

```
events
├── id
├── tenant_id              → FK tenants (cascade)
├── created_by             → FK users — autoría del registro
├── assigned_to_id         → FK users — responsable del evento ← NUEVO
├── case_id                → FK cases (nullable, nullOnDelete)
├── client_id              → FK clients (nullable, nullOnDelete)
├── client_name_snapshot   → string — registro histórico ← NUEVO
├── title
├── description
├── start_date             → datetime (UTC)
├── end_date               → datetime (UTC) — siempre >= start_date
├── all_day                → boolean
├── location
├── category               → string: primera_sesion|seguimiento|importante|personal
├── sync_source            → enum: local|outlook|google
├── external_id
└── timestamps + soft_deletes
```

### 3.3 Decisión de Diseño: `client_name` — Snapshot vs. Relación

**Decisión: Snapshot (`client_name_snapshot`).**

Un evento de calendario es un registro histórico. Si el nombre del cliente se corrige en el sistema después de la creación del evento, el evento pasado no debe reflejar ese cambio. El snapshot se escribe **una sola vez** en `store()` y nunca se sobreescribe en `update()`.

**Implicación:** Si `case_id` se pone a `null` por eliminación del caso (`nullOnDelete`), el `client_name_snapshot` sigue siendo válido — es la razón principal de su existencia.

### 3.4 Categorías — Estrategia de Traducción

El campo `category` en la DB almacena únicamente la **clave de identificación**, no la cadena traducida. La traducción vive en los archivos JSON de Vue I18n:

```
DB: category = "primera_sesion"
  ↓ EventResource lo expone tal cual
  ↓ Vue: $t('calendar.category_primera_sesion')
       → EN: "First Session"
       → ES: "Primera sesión"
       → FR: "Première séance"
```

**Mapa de categorías y colores FullCalendar:**

| Clave DB | Color FullCalendar | EN | ES | FR |
|----------|-------------------|----|----|-----|
| `primera_sesion` | `info` (azul) | First Session | Primera sesión | Première séance |
| `seguimiento` | `warning` (naranja) | Follow-up | Seguimiento | Suivi |
| `importante` | `danger` (rojo) | Important | Importante | Important |
| `personal` | `success` (verde) | Personal | Personal | Personnel |

Los `className` de FullCalendar ya están mapeados en el CSS global del tema — no se requiere cambio de CSS.

---

## 4. Backend Laravel

### 4.1 Modelo `Event`

```php
// app/Models/Event.php
class Event extends Model {
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'created_by', 'assigned_to_id',
        'client_id', 'case_id', 'client_name_snapshot',
        'title', 'description', 'start_date', 'end_date',
        'all_day', 'location', 'category',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date'   => 'datetime',
        'all_day'    => 'boolean',
    ];

    public function scopeForTenant($q) {
        return $q->where('tenant_id', auth()->user()->tenant_id);
    }

    public function assignedTo()      { return $this->belongsTo(User::class, 'assigned_to_id'); }
    public function immigrationCase() { return $this->belongsTo(ImmigrationCase::class, 'case_id'); }
    public function client()          { return $this->belongsTo(Client::class); }
    public function creator()         { return $this->belongsTo(User::class, 'created_by'); }
    public function participants()    { return $this->belongsToMany(User::class, 'event_participants')
                                                    ->withPivot('confirmed'); }
}
```

### 4.2 Scopes en Modelos Relacionados

```php
// En User.php — agregar scope
public function scopeActiveConsultants($q) {
    return $q->where('is_active', true)
             ->whereHas('roles', fn($r) =>
                 $r->whereIn('name', ['consultant', 'support', 'admin'])
             );
}

// En ImmigrationCase.php — verificar que existe
public function scopeActive($q) {
    return $q->where('status', 'active');
}
```

### 4.3 Requests de Validación

```php
// app/Http/Requests/Event/StoreEventRequest.php
public function rules(): array {
    return [
        'title'          => 'required|string|max:255',
        'start_date'     => 'required|date',
        'end_date'       => 'required|date|after_or_equal:start_date',
        'assigned_to_id' => 'required|exists:users,id',
        'case_id'        => 'nullable|exists:cases,id',
        'category'       => ['required', Rule::in(['primera_sesion', 'seguimiento', 'importante', 'personal'])],
        'description'    => 'nullable|string|max:2000',
        'all_day'        => 'boolean',
        'location'       => 'nullable|string|max:255',
    ];
}

// app/Http/Requests/Event/UpdateEventRequest.php
// Mismas reglas pero todos opcionales (sometimes) excepto las de coherencia de fechas
public function rules(): array {
    return [
        'title'          => 'sometimes|required|string|max:255',
        'start_date'     => 'sometimes|required|date',
        'end_date'       => 'sometimes|required|date|after_or_equal:start_date',
        'assigned_to_id' => 'sometimes|required|exists:users,id',
        'case_id'        => 'nullable|exists:cases,id',
        'category'       => ['sometimes', Rule::in(['primera_sesion', 'seguimiento', 'importante', 'personal'])],
        'description'    => 'nullable|string|max:2000',
        'all_day'        => 'boolean',
        'location'       => 'nullable|string|max:255',
    ];
}
```

### 4.4 `EventResource` — Formato para FullCalendar

```php
// app/Http/Resources/EventResource.php
public function toArray(Request $request): array {
    return [
        // Campos nativos FullCalendar
        'id'        => $this->id,
        'title'     => $this->title,
        'start'     => $this->start_date->toIso8601String(),
        'end'       => $this->end_date->toIso8601String(),
        'allDay'    => $this->all_day,
        'className' => $this->getCategoryColor(),

        // Datos extendidos (accesibles en event.extendedProps)
        'extendedProps' => [
            'description'  => $this->description,
            'category'     => $this->category,
            'location'     => $this->location,
            'assigned_to'  => $this->whenLoaded('assignedTo', fn() => [
                'id'   => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
            ]),
            'case_number'  => $this->whenLoaded('immigrationCase',
                fn() => $this->immigrationCase?->case_number
            ),
            'client_name'  => $this->client_name_snapshot,
        ],
    ];
}

private function getCategoryColor(): string {
    return match($this->category) {
        'primera_sesion' => 'info',
        'seguimiento'    => 'warning',
        'importante'     => 'danger',
        'personal'       => 'success',
        default          => 'primary',
    };
}
```

**Por qué `toIso8601String()`:** Envía `2026-03-16T14:00:00+00:00`. FullCalendar interpreta el offset y muestra la hora en el timezone local del navegador — sin ambigüedad.

### 4.5 `EventController`

```php
// app/Http/Controllers/Api/EventController.php

// index — recibe ?start=&end= del event source de FullCalendar
public function index(Request $request): AnonymousResourceCollection {
    $query = Event::with(['assignedTo', 'immigrationCase'])
        ->forTenant()
        ->when($request->start, fn($q, $s) => $q->where('start_date', '>=', $s))
        ->when($request->end,   fn($q, $e) => $q->where('start_date', '<=', $e))
        ->orderBy('start_date');

    return EventResource::collection($query->get());
}

// store — snapshot del cliente al crear
public function store(StoreEventRequest $request): JsonResponse {
    $data = $request->validated();
    $data['tenant_id']  = auth()->user()->tenant_id;
    $data['created_by'] = auth()->id();

    if ($data['case_id'] ?? null) {
        $case = ImmigrationCase::with('client')->findOrFail($data['case_id']);
        $data['client_id']           = $case->client_id;
        $data['client_name_snapshot'] = $case->client?->full_name ?? '';
    }

    $event = Event::create($data);
    $event->load(['assignedTo', 'immigrationCase']);

    return (new EventResource($event))->response()->setStatusCode(201);
}

// assignees — usuarios activos para el select del modal
public function assignees(): JsonResponse {
    $users = User::activeConsultants()
        ->forTenant()
        ->select('id', 'name')
        ->orderBy('name')
        ->get();

    return response()->json(['data' => $users]);
}
```

### 4.6 Rutas en `api.php`

```php
// Dentro del grupo auth:sanctum, throttle:api, tenant
Route::apiResource('events', EventController::class);
Route::get('events-assignees', [EventController::class, 'assignees']);
```

### 4.7 Búsqueda de Casos — Endpoint Reutilizado

**No se crea un endpoint nuevo.** Se reutiliza `GET /api/cases` con parámetros:

```
GET /api/cases?status=active&search=RODR&per_page=20
```

El `CaseController::index()` debe soportar búsqueda que haga JOIN con `clients`:

```php
->when($request->search, fn($q, $s) =>
    $q->where('case_number', 'like', "%{$s}%")
      ->orWhereHas('client', fn($c) =>
          $c->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', "%{$s}%")
      )
)
```

---

## 5. Frontend Vue.js

### 5.1 Nuevas Dependencias / Imports

`vue3-multiselect` ya es dependencia del proyecto — usarlo para el campo de búsqueda de casos.

### 5.2 Estructura del Modal Actualizado

```
┌─────────────────────────────────────────────┐
│ [x]  Agregar Evento / Editar Evento          │
├─────────────────────────────────────────────┤
│ Título del Evento *                          │
│ [___________________________________]        │
│                                              │
│ Asignado a *                                 │
│ [Select consultor...            ▾]           │
│                                              │
│ Caso Relacionado                             │
│ [Buscar por N° de caso o cliente... ▾]       │
│                                              │
│ Cliente                                      │
│ [Juan Rodríguez               ] (readonly)   │
│                                              │
│ Desde *              Hasta *                 │
│ [2026-03-16 14:00]   [2026-03-16 15:00]     │
│                                              │
│ Descripción                                  │
│ [                                    ]       │
│                                              │
│ Badge *                                      │
│ ○ Primera sesión  ○ Seguimiento              │
│ ○ Importante      ○ Personal                 │
│                                              │
│        [Cancelar]  [Crear Evento]            │
└─────────────────────────────────────────────┘
```

### 5.3 Parámetros del Formulario Actualizados

```typescript
interface EventParams {
    id: number | null;
    title: string;
    start: string;           // datetime-local string
    end: string;
    description: string;
    category: string;        // 'primera_sesion' | 'seguimiento' | 'importante' | 'personal'
    assigned_to_id: number | null;
    case_id: number | null;
    client_name: string;     // readonly, auto-populated
    location: string;
    all_day: boolean;
}

const defaultParams: EventParams = {
    id: null, title: '', start: '', end: '',
    description: '', category: 'primera_sesion',
    assigned_to_id: null, case_id: null,
    client_name: '', location: '', all_day: false,
};
```

### 5.4 Event Source — Integración con FullCalendar

Reemplazar el array local `events` por un event source con fetch function:

```typescript
const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    locale: store.locale,   // ← reactivo al idioma
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay',
    },
    editable: true,
    dayMaxEvents: true,
    selectable: true,
    events: async (fetchInfo: any, successCb: Function, failureCb: Function) => {
        try {
            const res = await api.get('/events', {
                params: { start: fetchInfo.startStr, end: fetchInfo.endStr }
            });
            successCb(res.data.data);
        } catch {
            failureCb(new Error('Failed to load events'));
        }
    },
    eventClick: (e: any) => editEvent(e),
    select:     (e: any) => editDate(e),
}));

// Post-CRUD: refetch sin re-renderizar todo el calendario
const refreshCalendar = () => {
    calendarRef.value?.getApi().refetchEvents();
};
```

### 5.5 Búsqueda Asíncrona de Casos con Debounce

```typescript
interface CaseOption {
    id: number;
    case_number: string;
    client_name: string;
    label: string;           // "#26-RT-RODR-0060 — Juan Rodríguez"
}

const caseOptions = ref<CaseOption[]>([]);
const selectedCase = ref<CaseOption | null>(null);
const isLoadingCases = ref(false);
let caseSearchDebounce: ReturnType<typeof setTimeout> | null = null;

const onCaseSearch = (query: string) => {
    if (caseSearchDebounce) clearTimeout(caseSearchDebounce);
    if (query.length < 2) { caseOptions.value = []; return; }
    isLoadingCases.value = true;
    caseSearchDebounce = setTimeout(async () => {
        const res = await api.get('/cases', {
            params: { status: 'active', search: query, per_page: 20 }
        });
        caseOptions.value = res.data.data.map((c: any) => ({
            id: c.id,
            case_number: c.case_number,
            client_name: c.client?.full_name ?? '',
            label: `#${c.case_number} — ${c.client?.full_name ?? ''}`,
        }));
        isLoadingCases.value = false;
    }, 350);
};

const onCaseSelected = (option: CaseOption) => {
    params.value.case_id    = option.id;
    params.value.client_name = option.client_name;  // auto-populate readonly
};

const onCaseCleared = () => {
    params.value.case_id    = null;
    params.value.client_name = '';
};
```

### 5.6 Manejo de Timezone — Funciones Helper

```typescript
// Convierte datetime-local string → ISO UTC string para enviar al backend
const toUtcIso = (localStr: string): string => {
    if (!localStr) return '';
    return new Date(localStr).toISOString();
};

// Convierte ISO UTC string → datetime-local string para el input del modal
const toLocalDatetimeInput = (isoStr: string): string => {
    if (!isoStr) return '';
    const d = new Date(isoStr);
    const offset = d.getTimezoneOffset();
    const local = new Date(d.getTime() - offset * 60_000);
    return local.toISOString().slice(0, 16);
};
```

### 5.7 CRUD de Eventos

```typescript
const saveEvent = async () => {
    if (!params.value.title || !params.value.start || !params.value.end ||
        !params.value.assigned_to_id || !params.value.category) return;

    isSaving.value = true;
    try {
        const payload = {
            title:           params.value.title,
            description:     params.value.description,
            start_date:      toUtcIso(params.value.start),
            end_date:        toUtcIso(params.value.end),
            assigned_to_id:  params.value.assigned_to_id,
            case_id:         params.value.case_id,
            category:        params.value.category,
            all_day:         params.value.all_day,
            location:        params.value.location,
        };

        if (params.value.id) {
            await api.put(`/events/${params.value.id}`, payload);
            showMessage(t('calendar.event_saved'));
        } else {
            await api.post('/events', payload);
            showMessage(t('calendar.event_saved'));
        }

        isAddEventModal.value = false;
        refreshCalendar();
    } catch (err: any) {
        showMessage(err.response?.data?.message ?? t('calendar.save_failed'), 'error');
    } finally {
        isSaving.value = false;
    }
};

const deleteEvent = async () => {
    if (!params.value.id) return;
    const confirmed = await Swal.fire({
        title: t('calendar.confirm_delete'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: t('calendar.delete'),
        cancelButtonText: t('calendar.cancel'),
    });
    if (!confirmed.isConfirmed) return;
    await api.delete(`/events/${params.value.id}`);
    showMessage(t('calendar.event_deleted'));
    isAddEventModal.value = false;
    refreshCalendar();
};
```

### 5.8 Leyenda del Header — Traducida y Reactiva

```vue
<!-- Reemplazar leyenda hardcoded -->
<div class="flex items-center flex-wrap gap-4 mt-2">
    <div v-for="cat in categories" :key="cat.value" class="flex items-center gap-1.5">
        <div class="h-2.5 w-2.5 rounded-sm" :class="`bg-${cat.color}`"></div>
        <span class="text-sm">{{ $t(cat.i18nKey) }}</span>
    </div>
</div>
```

---

## 6. Riesgos y Estrategias de Mitigación

### 6.1 Zonas Horarias — Riesgo Alto

**Problema:** `<input type="datetime-local">` devuelve `"2026-03-16T14:00"` sin timezone. Si el servidor asume UTC y el usuario está en Toronto (UTC-4), el evento queda registrado 4 horas adelantado.

**Solución:**
- Frontend: `new Date(localStr).toISOString()` convierte a UTC antes de enviar.
- Backend: Verificar `'timezone' => 'UTC'` en `config/app.php` y `date.timezone = UTC` en `php.ini`.
- `EventResource`: Devolver `toIso8601String()` con offset explícito para que FullCalendar calcule correctamente.

### 6.2 Caso Archivado — Consistencia Histórica

- La FK `case_id` usa `nullOnDelete()` — solo se pone a NULL si el caso se **elimina** (soft delete no lo activa).
- Si el caso se **archiva** (status → archived), el `case_id` sigue apuntando al caso.
- **Decisión:** Mantener el vínculo histórico. En el `EventResource`, exponer `case_number` aunque el caso esté archivado. El frontend puede mostrar el número en gris con un badge "archivado" si `case.status !== 'active'`.

### 6.3 Consultor Desactivado — Coherencia

- Si el consultor se marca `is_active = false` (sin eliminarse), el `assigned_to_id` sigue válido.
- Al abrir el modal de edición de un evento con consultor inactivo, su nombre aparece como opción seleccionada. La lista de nuevas asignaciones solo muestra activos (`scopeActiveConsultants`).
- **No** se invalidan eventos pasados — son registros históricos correctos.

### 6.4 Eliminación en Cascada

- Si el caso se elimina: `case_id = null`, pero `client_name_snapshot` conserva el nombre.
- Si el consultor se elimina: `assigned_to_id = null`. El evento queda "sin asignado" — aceptable para histórico.
- Si el cliente se elimina: `client_id = null`, pero `client_name_snapshot` conserva el nombre.

---

## 7. Claves i18n Completas

### `en.json`
```json
"calendar.title": "Calendar",
"calendar.create_event": "Create Event",
"calendar.edit_event": "Edit Event",
"calendar.add_event": "Add Event",
"calendar.update_event": "Update Event",
"calendar.event_title": "Event Title",
"calendar.event_title_placeholder": "Enter event title",
"calendar.from": "From",
"calendar.to": "To",
"calendar.description": "Description",
"calendar.description_placeholder": "Enter event description",
"calendar.badge": "Badge",
"calendar.assigned_to": "Assigned To",
"calendar.assigned_to_placeholder": "Select consultant...",
"calendar.related_case": "Related Case",
"calendar.search_case": "Search by case # or client name...",
"calendar.client_name": "Client",
"calendar.location": "Location",
"calendar.location_placeholder": "Optional location",
"calendar.all_day": "All day",
"calendar.category_primera_sesion": "First Session",
"calendar.category_seguimiento": "Follow-up",
"calendar.category_importante": "Important",
"calendar.category_personal": "Personal",
"calendar.cancel": "Cancel",
"calendar.delete": "Delete",
"calendar.confirm_delete": "Delete this event?",
"calendar.event_saved": "Event saved successfully",
"calendar.event_deleted": "Event deleted",
"calendar.save_failed": "Failed to save event"
```

### `es.json`
```json
"calendar.title": "Calendario",
"calendar.create_event": "Crear Evento",
"calendar.edit_event": "Editar Evento",
"calendar.add_event": "Agregar Evento",
"calendar.update_event": "Actualizar Evento",
"calendar.event_title": "Título del Evento",
"calendar.event_title_placeholder": "Ingrese el título del evento",
"calendar.from": "Desde",
"calendar.to": "Hasta",
"calendar.description": "Descripción",
"calendar.description_placeholder": "Ingrese la descripción del evento",
"calendar.badge": "Categoría",
"calendar.assigned_to": "Asignado a",
"calendar.assigned_to_placeholder": "Seleccionar consultor...",
"calendar.related_case": "Caso Relacionado",
"calendar.search_case": "Buscar por N° de caso o nombre de cliente...",
"calendar.client_name": "Cliente",
"calendar.location": "Lugar",
"calendar.location_placeholder": "Lugar opcional",
"calendar.all_day": "Todo el día",
"calendar.category_primera_sesion": "Primera sesión",
"calendar.category_seguimiento": "Seguimiento",
"calendar.category_importante": "Importante",
"calendar.category_personal": "Personal",
"calendar.cancel": "Cancelar",
"calendar.delete": "Eliminar",
"calendar.confirm_delete": "¿Eliminar este evento?",
"calendar.event_saved": "Evento guardado exitosamente",
"calendar.event_deleted": "Evento eliminado",
"calendar.save_failed": "Error al guardar el evento"
```

### `fr.json`
```json
"calendar.title": "Calendrier",
"calendar.create_event": "Créer un événement",
"calendar.edit_event": "Modifier l'événement",
"calendar.add_event": "Ajouter un événement",
"calendar.update_event": "Mettre à jour",
"calendar.event_title": "Titre de l'événement",
"calendar.event_title_placeholder": "Saisir le titre",
"calendar.from": "Du",
"calendar.to": "Au",
"calendar.description": "Description",
"calendar.description_placeholder": "Saisir la description",
"calendar.badge": "Catégorie",
"calendar.assigned_to": "Assigné à",
"calendar.assigned_to_placeholder": "Sélectionner un consultant...",
"calendar.related_case": "Dossier associé",
"calendar.search_case": "Rechercher par N° de dossier ou nom du client...",
"calendar.client_name": "Client",
"calendar.location": "Lieu",
"calendar.location_placeholder": "Lieu optionnel",
"calendar.all_day": "Toute la journée",
"calendar.category_primera_sesion": "Première séance",
"calendar.category_seguimiento": "Suivi",
"calendar.category_importante": "Important",
"calendar.category_personal": "Personnel",
"calendar.cancel": "Annuler",
"calendar.delete": "Supprimer",
"calendar.confirm_delete": "Supprimer cet événement ?",
"calendar.event_saved": "Événement enregistré avec succès",
"calendar.event_deleted": "Événement supprimé",
"calendar.save_failed": "Échec de l'enregistrement"
```

---

## 8. Checklist de Implementación

### Migraciones DB
- [x] **1.** Crear migración `add_assigned_to_and_snapshot_to_events_table`
  - `assigned_to_id` → FK users, nullable, nullOnDelete
  - `client_name_snapshot` → string, nullable

### Backend Laravel
- [x] **2.** Crear `app/Models/Event.php` con relaciones, scopes y casts
- [x] **3.** Agregar `scopeActiveConsultants()` en `app/Models/User.php`
- [x] **4.** Verificar `scopeActive()` en `app/Models/ImmigrationCase.php`
- [x] **5.** Crear `app/Http/Requests/Event/StoreEventRequest.php`
- [x] **6.** Crear `app/Http/Requests/Event/UpdateEventRequest.php`
- [x] **7.** Crear `app/Http/Resources/EventResource.php` (formato FullCalendar + extendedProps)
- [x] **8.** Crear `app/Http/Controllers/Api/EventController.php`
  - `index` con filtro de rango de fechas
  - `store` con snapshot del cliente
  - `update`
  - `destroy`
  - `assignees` — lista de usuarios activos
- [x] **9.** Registrar rutas en `routes/api.php`
  - `Route::apiResource('events', EventController::class)`
  - `Route::get('events-assignees', [EventController::class, 'assignees'])`
- [x] **10.** Verificar que `CaseController::index()` soporte búsqueda por nombre de cliente (JOIN con clients)

### Archivos de Traducción JSON
- [x] **11.** Agregar todas las claves `calendar.*` en `locales/en.json`
- [x] **12.** Agregar todas las claves `calendar.*` en `locales/es.json`
- [x] **13.** Agregar todas las claves `calendar.*` en `locales/fr.json`

### Componente Vue `calendar.vue`
- [x] **14.** Agregar `useI18n` + `useAppStore` + `api` service
- [x] **15.** Reemplazar `getEvents()` local por FullCalendar event source function (`GET /api/events?start=&end=`)
- [x] **16.** Conectar `locale` de FullCalendar al `store.locale` (reactivo)
- [x] **17.** Actualizar leyenda del header con las 4 nuevas categorías traducidas
- [x] **18.** Actualizar `defaultParams` y `params` con nuevos campos (`assigned_to_id`, `case_id`, `client_name`, `category`, `location`, `all_day`)
- [x] **19.** Agregar campo "Asignado a" en modal (`<select>` poblado desde `GET /api/events-assignees`)
- [x] **20.** Agregar campo "Caso" en modal (`VueMultiselect` asíncrono con debounce 350ms)
- [x] **21.** Agregar campo "Cliente" en modal (`<input readonly>` auto-poblado al seleccionar caso)
- [x] **22.** Reemplazar categorías Work/Travel con las 4 nuevas (radio buttons con i18n)
- [x] **23.** Agregar campo "Ubicación" (opcional)
- [x] **24.** Implementar helpers `toUtcIso()` y `toLocalDatetimeInput()` para timezone
- [x] **25.** Convertir `saveEvent()` para llamar `POST /api/events` y `PUT /api/events/{id}`
- [x] **26.** Implementar `deleteEvent()` con `DELETE /api/events/{id}` + confirmación SweetAlert
- [x] **27.** Reemplazar `calendar.value.getApi()` por `calendarRef.value?.getApi().refetchEvents()` post-CRUD
- [x] **28.** Reemplazar todos los textos hardcoded en inglés con `$t('calendar.*')`
- [x] **29.** Agregar botón "Eliminar" en modal cuando `params.id` no es null

---

## 9. Notas Técnicas Finales

- **FullCalendar `refetchEvents()`** es la forma idiomática de recargar eventos sin destruir/recrear el componente. Usar siempre después de cualquier operación CRUD.
- **`vue3-multiselect`** ya es dependencia del proyecto. No agregar nuevas dependencias.
- **`assigned_to_id` es obligatorio** según los requerimientos. Si el usuario actual siempre es el asignado por defecto, se puede pre-poblar con `auth().user().id` en el frontend.
- **Vistas por año:** FullCalendar no incluye vista de año en los plugins estándar. Requiere el plugin adicional `@fullcalendar/multimonth`. Evaluar si es necesario o si las vistas mes/semana/día son suficientes para MVP.
- **Permisos:** Agregar permisos `events.create`, `events.update`, `events.delete`, `events.view` en la tabla de permisos y proteger el controller con `$this->authorize()`.
