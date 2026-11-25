---
tipo: spec
numero: 44
modulo: UX e Internacionalización
dominio: UX
estado: DONE
tags: [spec, ux, done]
---
# [[44_preferencia_visualizacion_nombres|Spec 44]] — Preferencia de Visualización de Nombres

## Contexto

El sistema actualmente concatena `first_name + last_name` de forma hardcodeada en más de 10 puntos distintos del stack (accessors, services, templates Vue). No existe una forma centralizada de cambiar el formato de presentación de nombres de personas (clientes y acompañantes). La empresa debe poder elegir entre:

- **Nombre Apellidos** — Ej: `Omar Rodriguez` (default actual)
- **Apellidos, Nombre** — Ej: `Rodriguez, Omar`

El cambio debe propagarse a todos los módulos: tablas de clientes/expedientes, detalle de expediente, todolist, calendario, búsqueda global, papelera y dashboards.

---

## Análisis Técnico

### 1. Decisión Arquitectónica: Backend vs Frontend

| Estrategia | Ventajas | Desventajas | Veredicto |
|------------|----------|-------------|-----------|
| **Solo Backend (accessor)** | Cobertura automática de PDFs y nuevos endpoints; un único punto de lógica | N+1 queries sin cache; requiere refetch al cambiar setting | ✅ Elegido como gravedad principal |
| **Solo Frontend (composable)** | Reactividad inmediata con Pinia; no requiere refetch | Cada componente debe implementarlo; no cubre PDFs ni exportaciones | ❌ Incompleto solo |
| **Híbrido** | Backend formatea `full_name`; frontend usa `nameFormat` de Pinia para sort y preview | Mayor complejidad inicial | ✅ Elegido |

**Decisión: Híbrido con gravedad en Backend.**

- El accessor `full_name` en `Client` y `Companion` es el único punto de lógica de formato. Todos los servicios y resources usan `$model->full_name`.
- El frontend nunca concatena `first_name + last_name` en templates — siempre consume `full_name` de la API.
- Pinia store guarda `nameFormat` solo para: sorting de tablas, preview en pantalla de settings, y composable de fallback.
- La búsqueda global siempre busca en campos crudos (`first_name LIKE`, `last_name LIKE`) — el formato visual no afecta los resultados.

### 2. Mapa Completo de Puntos de Concatenación Actuales

| Capa | Archivo | Patrón a Reemplazar |
|------|---------|---------------------|
| Model accessor | `app/Models/Client.php:111` | `"{$this->first_name} {$this->last_name}"` |
| Model accessor | `app/Models/Companion.php:96` | `"{$this->first_name} {$this->last_name}"` |
| Service | `app/Services/Search/GlobalSearchService.php:47,95` | `"{$c->first_name} {$c->last_name}"` |
| Service | `app/Services/Trash/TrashService.php:86,112,105,120,264` | `trim("{$model->first_name} {$this->last_name}")` |
| Vue template | `views/clients/list.vue:264,392` | `{{ first_name }} {{ last_name }}` |
| Vue template | `views/cases/list.vue` | `full_name \|\| \`${first_name} ${last_name}\`` |

### 3. Almacenamiento de la Preferencia

No se requiere migración de columna. El campo se almacena en el JSON `settings` de la tabla `tenants`, que ya tiene infraestructura de `getSetting()` / `setSetting()`:

```json
// tenants.settings — nuevo key:
{
  "name_format": "first_last"
}
// valores: "first_last" (default) | "last_first"
```

Compatibilidad hacia atrás garantizada: `getSetting('name_format', 'first_last')` devuelve el default sin migración de datos.

### 4. Backend — Accessor con Cache

El accessor modificado usa `Cache::remember` por `tenant_id` para evitar N+1:

```php
// app/Models/Client.php (y Companion.php — idéntico)
public function getFullNameAttribute(): string
{
    $format = $this->getNameFormat();
    return $format === 'last_first'
        ? "{$this->last_name}, {$this->first_name}"
        : "{$this->first_name} {$this->last_name}";
}

protected function getNameFormat(): string
{
    return Cache::remember(
        "tenant:{$this->tenant_id}:name_format",
        300, // 5 minutos
        fn() => Tenant::find($this->tenant_id)?->getSetting('name_format', 'first_last') ?? 'first_last'
    );
}
```

Invalidación al guardar settings:
```php
Cache::forget("tenant:{$tenant->id}:name_format");
```

### 5. Atributo `sort_name` — Ordenamiento Independiente del Formato

El campo de sort en las tablas debe ser siempre por apellido (estándar legal/migratorio), independientemente del formato visual:

```php
public function getSortNameAttribute(): string
{
    return "{$this->last_name}, {$this->first_name}";
}
```

Las tablas Vue configuran `field: 'sort_name'` para el ordenamiento pero muestran `full_name` en el render.

### 6. `ClientResource.php` — Creación Obligatoria

No existe `ClientResource`. Los clientes se devuelven actualmente desde el controller sin pasar por un Resource. Para estandarizar `full_name` en todas las respuestas de clientes, se crea:

```php
// app/Http/Resources/ClientResource.php
return [
    'id'         => $this->id,
    'full_name'  => $this->full_name,   // accessor formateado
    'sort_name'  => $this->sort_name,   // para sort en tabla
    'first_name' => $this->first_name,  // se mantiene para formularios
    'last_name'  => $this->last_name,
    'initials'   => $this->initials,
    // ... resto de campos
];
```

### 7. Frontend — Pinia y Composable

```typescript
// stores/index.ts — añadir al state:
nameFormat: 'first_last' as 'first_last' | 'last_first',
```

```typescript
// composables/useFormatName.ts
export function useFormatName() {
    const store = useAppStore();
    function formatName(firstName: string, lastName: string): string {
        return store.nameFormat === 'last_first'
            ? `${lastName}, ${firstName}`
            : `${firstName} ${lastName}`;
    }
    return { formatName };
}
```

### 8. Búsqueda Global — Sin Impacto

`GlobalSearchService` busca sobre `first_name LIKE` y `last_name LIKE` independientemente. Solo cambia el `label` del resultado usando `$c->full_name`. Buscar "Omar" siempre encuentra a "Rodriguez, Omar" y "Omar Rodriguez" por igual.

### 9. PDFs y Documentos Legales

Los documentos legales deben usar un formato fijo por ley. Se añade un método `legalName()` en el modelo que siempre devuelve `"Apellido, Nombre"` sin importar la preferencia del tenant. Las vistas de PDF no usan el accessor `full_name`.

### 10. Reactividad al Cambiar Setting

1. Admin guarda → `PUT /api/tenant/settings` → backend invalida `Cache::forget`
2. Respuesta incluye `name_format` actualizado → frontend actualiza `store.nameFormat`
3. Pinia reactivo → composables y computed se actualizan automáticamente
4. Datos en tablas ya cargados requieren refetch → mensaje informativo: "El formato se aplicará al recargar las listas."

---

## Checklist de Implementación

### Fase 1 — Backend

- [x] `app/Models/Client.php` — `getFullNameAttribute()` con cache por tenant; `getSortNameAttribute()`; `getNameFormat()` privado; `$appends = ['full_name', 'sort_name', 'initials']`
- [x] `app/Models/Companion.php` — mismo cambio completo
- [x] `app/Http/Resources/TenantResource.php` — `name_format` añadido a `preferences`
- [x] `app/Services/Search/GlobalSearchService.php` — selects incluyen `tenant_id`; labels usan `$c->full_name`; trash idem
- [x] `app/Services/Trash/TrashService.php` — 5 concatenaciones reemplazadas por `$model->full_name` / `$model->client->full_name`
- [x] `app/Services/TenantService.php` — `name_format` en `updateSettings()`; `Cache::forget` al guardar
- [x] `app/Repositories/Eloquent/CaseRepository.php` — eager loads de client incluyen `tenant_id`
- [x] `app/Http/Controllers/Api/DashboardController.php` — eager load de client incluye `tenant_id`
- [ ] `app/Http/Resources/ClientResource.php` — omitido: se usó `$appends` en el modelo como alternativa más limpia (sin necesidad de Resource)
- [ ] `app/Http/Controllers/Api/ClientController.php` — omitido: `$appends` en modelo cubre la necesidad sin Resource wrapper

### Fase 2 — Frontend

- [x] `resources/js/src/stores/tenant.ts` — `name_format` en `TenantPreferences` interface; getter `nameFormat`; `updateSettings` tipado
- [x] `resources/js/src/composables/useFormatName.ts` — composable creado
- [x] `resources/js/src/types/client.ts` — `full_name`, `sort_name`, `initials` cambiados a required
- [x] `resources/js/src/types/companion.ts` — `full_name`, `sort_name`, `initials` añadidos como required
- [x] `resources/js/src/views/clients/list.vue` — `full_name` en tabla y cards; `sort_name` como sort field; diálogos de confirmación actualizados
- [x] `resources/js/src/views/clients/show.vue` — breadcrumb, header, acompañantes, diálogos actualizados
- [x] `resources/js/src/views/clients/edit.vue` — breadcrumb usa `useFormatName` composable
- [x] `resources/js/src/views/cases/list.vue` — `full_name` en tabla y cards; initials con fallback
- [x] `resources/js/src/views/cases/show.vue` — cliente y acompañantes usan `full_name`
- [x] `resources/js/src/views/cases/create.vue` — dropdown de clientes usa `full_name`
- [x] `resources/js/src/views/cases/wizard/components/ClientCard.vue` — `full_name`, initials con fallback
- [x] `resources/js/src/views/cases/wizard/components/CompanionCheckbox.vue` — `full_name`, initials con fallback
- [x] `resources/js/src/views/cases/wizard/steps/StepClient.vue` — `full_name`, initials con fallback
- [x] `resources/js/src/views/cases/wizard/steps/StepSummary.vue` — `full_name`, initials con fallback
- [x] `resources/js/src/views/cases/wizard/steps/StepCompanions.vue` — diálogo usa `full_name`

### Fase 3 — Configuración UI

- [x] `resources/js/src/views/admin/tenant/TenantSettings.vue` — selector de tarjetas radio con preview activo; `form.name_format` ligado al store; incluido en `saveSettings()`
- [x] `resources/js/src/locales/es.json` — 5 claves `tenant.name_format*` añadidas
- [x] `resources/js/src/locales/en.json` — 5 claves `tenant.name_format*` añadidas

---

## Riesgos y Mitigaciones

| Riesgo | Mitigación |
|--------|------------|
| N+1 queries en accessor | `Cache::remember` por `tenant_id` con TTL 5 min |
| Tablas no se actualizan al cambiar formato | Notificación al admin; refetch post-save |
| Datos existentes sin `name_format` | `getSetting('name_format', 'first_last')` — default garantizado |
| `ClientController` sin Resource → `full_name` no llega | Crear `ClientResource` es Fase 1 prioritaria |
| PDFs con formato incorrecto | Método `legalName()` separado, no afectado por setting |
| Cache stale después de cambio de setting | `Cache::forget` explícito en `updateSettings()` |
