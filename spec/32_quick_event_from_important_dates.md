---
tipo: spec
numero: 32
modulo: Calendario y Eventos
dominio: CAL
estado: DONE
tags: [spec, calendario, done]
relacionados:
  - "[[12_fechas_importantes_dinamicas|Spec 12]]"
  - "[[22_calendario_legal_integrado|Spec 22]]"
  - "[[57_alertas_fechas_importantes_expedientes|Spec 57]]"
---
# [[32_quick_event_from_important_dates|Spec 32]]: Creacion Rapida de Eventos desde Fechas Importantes

## Informacion del Documento

- **Fecha:** 2026-03-24
- **Tipo:** Analisis de Impacto Tecnico
- **Scope:** Accion rapida para crear eventos en calendario desde la seccion de Fechas Importantes del expediente
- **Dependencias:** [[12_fechas_importantes_dinamicas|Spec 12]] (Fechas Importantes Dinamicas), [[22_calendario_legal_integrado|Spec 22]] (Calendario Legal Integrado), [[23_eventos_tab_expediente|Spec 23]] (Eventos Tab Expediente)
- **Estado:** Completo — 3 fases implementadas (0 archivos backend, 2 Vue, 3 traducciones)

---

## 1. Resumen Ejecutivo

Agregar un icono de accion rapida junto a cada "Fecha Importante" en la vista de detalle del expediente (`show.vue`). Al hacer clic, el sistema crea automaticamente un evento en el calendario pre-poblado con los datos del expediente, sin requerir que el usuario complete un formulario.

### Hallazgo Clave: Infraestructura Existente

El analisis revela que **el 90% de la infraestructura ya existe**:

| Componente | Estado | Notas |
|---|---|---|
| `POST /api/events` | Completo | Acepta `title`, `start_date`, `end_date`, `all_day`, `category`, `case_id`, `assigned_to_id` |
| `StoreEventRequest` | Completo | Valida que `case_id` exista en el tenant, `assigned_to_id` exista y este activo |
| `EventController::store()` | Completo | Auto-resuelve `client_id` y `client_name_snapshot` desde el case |
| `EventResource` | Completo | Formato FullCalendar con `extendedProps` |
| `DateManager.vue` | Completo | Renderiza fechas con status badges (overdue/upcoming/future) |
| `CaseEventTab.vue` | Completo | Tab de eventos contextual al caso |
| `EventFormModal.vue` | Completo | Modo contextual con case bloqueado |
| Icono calendario | Completo | `icon-calendar.vue` disponible |

**Lo que falta:** Un boton en `DateManager.vue` que llame a `POST /api/events` con datos pre-mapeados.

---

## 2. Reglas de Negocio

| # | Regla | Descripcion |
|---|---|---|
| RN1 | Mapeo Automatico | Titulo = label de la fecha, Fecha = due_date, Categoria = 'importante', Todo el dia = true |
| RN2 | Asignacion | El evento se asigna al consultor del caso (`case.assigned_to`). Si no hay consultor asignado, se asigna al usuario que hizo clic |
| RN3 | Auditoria | `created_by` = usuario que hizo clic (el que dispara la accion). `assigned_to_id` = consultor del caso |
| RN4 | Fechas sin valor | Si `due_date` es null, el icono no aparece (no se puede crear evento sin fecha) |
| RN5 | Duplicados | Entidades independientes. Se puede crear multiples eventos desde la misma fecha. El icono siempre esta disponible |
| RN6 | Fechas pasadas | Permitido. Una fecha importante pasada puede necesitar un recordatorio o registro historico |
| RN7 | Independencia | Modificar la fecha importante NO actualiza el evento creado. Son entidades independientes despues de la creacion |

---

## 3. Analisis de Impacto por Capa

### 3.1 Backend y API (Laravel)

#### Endpoint: Reutilizar `POST /api/events` existente

**NO se necesita un endpoint nuevo.** El `EventController::store()` ya acepta todos los campos requeridos y realiza la logica necesaria:

```php
// EventController::store() ya hace esto:
$event = Event::create([
    'tenant_id'   => auth()->user()->tenant_id,
    'created_by'  => auth()->id(),                    // ← usuario que hizo clic
    'title'       => $validated['title'],              // ← label de la fecha
    'start_date'  => $validated['start_date'],         // ← due_date
    'end_date'    => $validated['end_date'],            // ← due_date (mismo dia)
    'all_day'     => $validated['all_day'],             // ← true
    'category'    => $validated['category'],            // ← 'importante'
    'case_id'     => $validated['case_id'],             // ← case.id
    'assigned_to_id' => $validated['assigned_to_id'],   // ← case.assigned_to
]);

// Auto-resuelve client_id y snapshot desde case_id
if ($event->case_id) {
    $case = ImmigrationCase::find($event->case_id);
    if ($case) {
        $event->update([
            'client_id' => $case->client_id,
            'client_name_snapshot' => $case->client?->full_name,
        ]);
    }
}
```

**Validacion cruzada ya implementada en `StoreEventRequest`:**
- `case_id` → `exists:cases,id` con scope de tenant
- `assigned_to_id` → `exists:users,id` con scope de tenant + activo
- `start_date`, `end_date` → requeridos, `end_date >= start_date`
- `category` → debe estar en `Event::$categories` (incluye 'importante')

**Impacto backend: CERO archivos que modificar.**

#### Decision: Auditoria

| Campo | Valor | Razon |
|---|---|---|
| `created_by` | Usuario que hizo clic | Quien disparo la accion (trazabilidad) |
| `assigned_to_id` | Consultor del caso | Quien debe atender el evento (responsabilidad) |

Esto es el comportamiento por defecto del endpoint actual — `created_by = auth()->id()` y `assigned_to_id` viene del payload.

### 3.2 Frontend — Componentes a Modificar

#### Archivo 1: `resources/js/src/components/DateManager.vue`

**Cambio:** Agregar icono de calendario en modo readonly junto a cada fecha que tenga `due_date`.

**Estado actual del modo readonly (lineas 86-105):**
```vue
<div v-for="(date, index) in dates" :key="index" class="flex items-center gap-3 ...">
    <div class="flex-1 min-w-0">
        <p class="text-sm font-medium">{{ date.label }}</p>
        <p class="text-xs text-gray-400">{{ date.due_date || '—' }}</p>
    </div>
    <span class="badge ...">{{ statusLabel }}</span>
</div>
```

**Cambio propuesto:**
```vue
<div v-for="(date, index) in dates" :key="index" class="flex items-center gap-3 ...">
    <div class="flex-1 min-w-0">
        <p class="text-sm font-medium">{{ date.label }}</p>
        <p class="text-xs text-gray-400">{{ date.due_date || '—' }}</p>
    </div>
    <span class="badge ...">{{ statusLabel }}</span>
    <!-- Quick Event Action -->
    <button
        v-if="date.due_date && showQuickEvent"
        type="button"
        class="..."
        :title="$t('cases.create_calendar_event')"
        @click="$emit('quick-event', date)"
    >
        <icon-calendar class="w-4 h-4" />
    </button>
</div>
```

**Nuevos props:**
- `showQuickEvent: boolean` (default: false) — solo se activa desde show.vue, no en create/edit

**Nuevo emit:**
- `quick-event` con payload `ImportantDate`

**Impacto:** Minimo. Se agrega un boton condicional y un emit. No altera la logica existente.

#### Archivo 2: `resources/js/src/views/cases/show.vue`

**Cambio:** Manejar el emit `quick-event` del DateManager, llamar a `POST /api/events` y mostrar confirmacion.

**Estado actual (lineas 309-315):**
```vue
<DateManager v-model="caseData.important_dates" :readonly="true" />
```

**Cambio propuesto:**
```vue
<DateManager
    v-model="caseData.important_dates"
    :readonly="true"
    :show-quick-event="true"
    @quick-event="createQuickEvent"
/>
```

**Nueva funcion `createQuickEvent`:**
```typescript
async function createQuickEvent(date: ImportantDate) {
    if (!date.due_date) return;

    try {
        await api.post('/events', {
            title: date.label,
            start_date: date.due_date,
            end_date: date.due_date,
            all_day: true,
            category: 'importante',
            case_id: caseData.value.id,
            assigned_to_id: caseData.value.assigned_to ?? authStore.user?.id,
        });

        // Toast de confirmacion con enlace al calendario
        success(t('cases.event_created_from_date'));
    } catch (err) {
        error(t('cases.event_creation_failed'));
    }
}
```

**Impacto:** Se agrega un prop, un handler, y una llamada API directa. No se necesita un store ni un servicio nuevo — la llamada es una operacion atomica sin estado compartido.

#### Archivo 3: Traducciones (`es.json`, `en.json`, `fr.json`)

Agregar keys:
```json
"cases.create_calendar_event": "Crear evento en calendario",
"cases.event_created_from_date": "Evento creado en el calendario",
"cases.event_creation_failed": "Error al crear el evento"
```

### 3.3 API — Transferencia de Datos

**Payload enviado desde el frontend:**

```typescript
{
    title: "Fecha limite legal",           // date.label
    start_date: "2026-04-15",              // date.due_date
    end_date: "2026-04-15",               // date.due_date (mismo dia)
    all_day: true,                         // siempre true
    category: "importante",                // siempre 'importante'
    case_id: 42,                           // case.id
    assigned_to_id: 7,                     // case.assigned_to ?? auth.user.id
}
```

**Respuesta del backend (EventResource):**

```json
{
    "data": {
        "id": 123,
        "title": "Fecha limite legal",
        "start": "2026-04-15",
        "end": "2026-04-15",
        "allDay": true,
        "className": "danger",
        "backgroundColor": "#e7515a",
        "extendedProps": {
            "category": "importante",
            "case_id": 42,
            "case_number": "26-PQ-VEGA-0003",
            "client_name": "Pedro Vega",
            "assigned_to": { "id": 7, "name": "Ana Consultor" }
        }
    }
}
```

No se necesitan cambios en el Resource ni en el endpoint.

---

## 4. Integridad y Casos de Borde

### 4.1 Fechas Pasadas

**Decision: Permitido.**

Razones:
- Una fecha importante vencida puede requerir un evento de seguimiento ("Revisar fecha limite legal vencida")
- El calendario ya muestra eventos pasados sin restriccion
- `StoreEventRequest` no valida que `start_date` sea futuro
- Bloquear fechas pasadas agregaria complejidad sin valor

### 4.2 Fechas sin Valor (due_date = null)

**Decision: El icono no aparece.** `v-if="date.due_date"` en el boton. No se puede crear un evento sin fecha.

### 4.3 Duplicados

**Decision: Entidades independientes. Permitir duplicados.**

Razones:
- Un usuario puede querer crear multiples eventos para la misma fecha (recordatorio + audiencia)
- Vincular la existencia del evento a la fecha importante crearia acoplamiento innecesario
- Detectar duplicados requeriria consultar la API por cada fecha al renderizar la lista — impacto en performance
- El usuario puede ver eventos existentes en la pestana "Eventos" del mismo expediente

**Alternativa descartada:** Cambiar el icono si ya existe un evento. Requeriria:
- Fetch de todos los eventos del caso al montar DateManager
- Comparar `title + start_date + case_id` para cada fecha
- Mantener sincronizado si se elimina el evento desde el calendario
- Complejidad alta para beneficio marginal

### 4.4 Cambios Posteriores a la Fecha Importante

**Decision: Independientes despues de la creacion.**

- Si el usuario cambia la fecha de "2026-04-15" a "2026-05-01", el evento existente mantiene "2026-04-15"
- El usuario puede editar el evento manualmente desde el calendario o la pestana Eventos
- Sincronizacion automatica (actualizar evento al cambiar fecha) crearia:
  - Necesidad de vincular `important_date_id` al evento
  - Logica de actualizacion bidireccional
  - Conflictos si el usuario ya edito el evento manualmente
  - Complejidad desproporcionada al beneficio

### 4.5 Caso sin Consultor Asignado

Si `case.assigned_to` es null, el evento se asigna al usuario que hizo clic (el usuario actual). Esto cumple la validacion del backend (`assigned_to_id` es required y debe existir).

---

## 5. Resumen de Impacto

| Capa | Archivos Nuevos | Archivos Modificados | Complejidad |
|---|---|---|---|
| Backend (Laravel) | 0 | 0 | Ninguna |
| API / Endpoints | 0 | 0 | Ninguna |
| Frontend (Vue) | 0 | 2 (DateManager.vue, show.vue) | Baja |
| Traducciones | 0 | 3 (es, en, fr) | Trivial |
| Tests | 0 | 0 (endpoint ya testeado) | Ninguna |
| **Total** | **0** | **5** | **Baja** |

Esta es una feature de **impacto minimo** porque reutiliza completamente la infraestructura existente. No requiere migraciones, no requiere nuevos endpoints, no requiere nuevos servicios. Solo agrega un boton que llama a un endpoint existente con datos pre-mapeados.

---

## 6. Checklist de Implementacion

### Fase 1: Componente DateManager

- [x] Agregar prop `showQuickEvent` (boolean, default false)
- [x] Agregar emit `quick-event` con payload `ImportantDate`
- [x] Agregar boton con SVG calendar en modo readonly, visible solo cuando `due_date` existe
- [x] Estilo: boton ghost con hover primary, tooltip con titulo

### Fase 2: Vista de Detalle del Expediente

- [x] Pasar `:show-quick-event="true"` al DateManager en show.vue
- [x] Implementar handler `createQuickEvent(date)` con llamada a `POST /api/events`
- [x] Resolver `assigned_to_id` desde `case.assigned_to` con fallback a undefined (backend validara)
- [x] Mostrar toast de confirmacion con `success()`
- [x] Guard `isCreatingEvent` para evitar doble clic

### Fase 3: Traducciones

- [x] Agregar `cases.create_calendar_event` en es.json, en.json, fr.json
- [x] Agregar `cases.event_created_from_date` en es.json, en.json, fr.json
- [x] Agregar `cases.event_creation_failed` en es.json, en.json, fr.json

---

## 7. Decisiones de Diseno Descartadas

| Alternativa | Razon de Descarte |
|---|---|
| Endpoint simplificado `/api/events/quick` | El endpoint existente ya acepta todos los campos. Crear uno nuevo duplicaria logica |
| Sincronizacion bidireccional fecha ↔ evento | Complejidad alta, conflictos de edicion, acoplamiento innecesario |
| Deteccion de duplicados con icono deshabilitado | Requiere fetch adicional por cada fecha, performance cuestionable, beneficio marginal |
| Modal de confirmacion antes de crear | Elimina el proposito de "accion rapida". El toast posterior es suficiente |
| Campo `important_date_id` en tabla events | Acoplamiento estructural innecesario. Las entidades deben ser independientes |
