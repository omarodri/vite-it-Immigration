---
tipo: spec
numero: 62
modulo: Calendario y Eventos
dominio: CAL
estado: DONE
tags: [spec, calendario, done]
relacionados:
  - "[[22_calendario_legal_integrado|Spec 22]]"
  - "[[24_calendario_drag_drop_filtros_quicklinks|Spec 24]]"
  - "[[45_sincronizacion_calendarios_externos|Spec 45]]"
---
# [[62_sincronizacion_calendarios_externos|Spec 62]] — Sincronización de Calendarios Externos — Completar Gaps de Sync Bidireccional

**Dominio:** CAL
**Estado:** DONE
**Relacionados:** 22 (Calendario Legal), 24 (Calendario Drag & Drop), 45 (Sync Calendarios Externos — base)
**Creado:** 2026-05-15
**Autor:** Análisis de Impacto — Architect Agent

---

## Resumen Ejecutivo

El [[45_sincronizacion_calendarios_externos|Spec 45]] estableció la infraestructura base de sincronización bidireccional de calendarios externos: `CalendarSyncService`, `GoogleCalendarService`, `MicrosoftCalendarService`, `UserCalendarOAuthController`, `EventSyncObserver`, `PullCalendarEventsJob`, y la tabla `calendar_sync_status`. Esa base es **funcional pero incompleta**: cubre el flujo OAuth, el push del observer, el pull periódico cada 15 minutos y la persistencia de tokens — pero deja sin resolver nueve gaps observables que degradan la experiencia del usuario y, en algunos casos, impiden el uso real del feature.

Este spec **no rediseña** la arquitectura existente. Su objetivo es **cerrar los gaps de deuda técnica acumulada** y elevar la feature de "funcional en escenario feliz" a "robusta en producción multi-tenant". Los gaps cubiertos son: backfill inicial al conectar, reset de circuit breaker desde UI, manejo descriptivo de errores OAuth en callback, pull on-demand para sesiones `paused`, **soporte multi-calendar real** (Google + Microsoft simultáneos en un mismo usuario), indicadores visuales de sync en `apps/calendar.vue`, tests Feature del flujo OAuth, configuración tenant del rango de backfill, y notificación al usuario tras expiración de token irrecuperable.

El cambio arquitecturalmente más significativo es la **eliminación del supuesto "un proveedor activo por usuario"** en `CalendarSyncService::getConnectedProvider()` y `pullEvents()`. Ese supuesto, embebido implícitamente en el Spec 45, contradice el modelo de datos (la tabla `calendar_sync_status` ya permite múltiples filas `(user_id, provider)` con una constraint única compuesta). El refactor introduce `getConnectedProviders(): Collection<string>` y desplaza la decisión "qué proveedores procesar" del service al caller (job o controlador), preservando retrocompatibilidad mediante un wrapper.

El segundo cambio más impactante es la introducción de una **ventana de backfill configurable a nivel de tenant** (`tenant.settings.calendar_backfill_months`) con default 6 meses hacia atrás + 12 meses hacia adelante. Reemplaza el actual `now()->subDay()` que provoca que el usuario perciba "no se sincronizó nada" al conectar por primera vez. La ventana también pasa explícitamente a `GoogleCalendarService::listEvents(timeMin, timeMax)` y `MicrosoftCalendarService::listEvents(startDateTime, endDateTime)`, eliminando otra ambigüedad del Spec 45.

### Hallazgos Críticos Verificados en el Codebase

| # | Hallazgo | Archivo | Impacto en el Diseño |
|---|---|---|---|
| H1 | `CalendarSyncService::pullEvents()` usa `$since = $syncStatus?->last_pull_at ?? now()->subDay()` — backfill efectivo de 24h al conectar | `app/Services/Calendar/CalendarSyncService.php` línea 87 | El usuario que conecta por primera vez no ve sus eventos históricos. Requiere reemplazar por ventana configurable `[now-Nmeses, now+12meses]` |
| H2 | `getConnectedProvider(User)` devuelve **solo el primer proveedor activo** vía `->first()` | `CalendarSyncService.php` línea 176-188 | Un usuario con Google + Microsoft conectados solo sincroniza uno. Refactor obligatorio para soportar multi-provider real |
| H3 | `PullCalendarEventsJob` itera `CalendarSyncStatus` `status='active'` pero llama `pullEvents($user)` (no `pullEvents($user, $provider)`) | `app/Jobs/PullCalendarEventsJob.php` | Cada fila de la tabla dispara un pull que **internamente vuelve a buscar el primer proveedor**, ignorando la fila iterada. Bug latente que el refactor de H2 obliga a corregir |
| H4 | `CalendarSyncStatus` usa `BelongsToTenant` (línea 13 del modelo) — multi-tenant correcto | `app/Models/CalendarSyncStatus.php` | Sin cambios necesarios. El refactor multi-provider preserva el aislamiento por `TenantScope` |
| H5 | Circuit breaker: tras 5 errores consecutivos `status` pasa a `'error'`. Pull job **omite** estos registros. No hay endpoint de reset | `CalendarSyncService::pullEvents()` + `PullCalendarEventsJob` | Único recovery actual: desconectar + reconectar. Necesita endpoint `POST /api/calendar-oauth/{provider}/retry` y botón en UI |
| H6 | Endpoint `POST /api/calendar-oauth/pull` filtra por `where('status', 'active')` (controlador línea 276) | `app/Http/Controllers/Api/UserCalendarOAuthController.php` | Sesiones `paused` o `error` no se pueden refrescar manualmente — incluso después del fix de H5, el endpoint pull debe permitir explícitamente `paused` |
| H7 | El callback OAuth redirige a `/users/profile?calendar_error=...` pero `profile.vue` no muestra mensaje descriptivo ni sugerencia de acción | `app/Http/Controllers/Api/UserCalendarOAuthController.php::callback()` + `resources/js/src/views/users/profile.vue` | Usuario ve la página de perfil sin feedback claro. Requiere componente `CalendarConnectionStatus.vue` con manejo de query params y toast/banner descriptivo |
| H8 | `apps/calendar.vue` no muestra timestamp del último pull exitoso, ni spinner durante sync, ni badge "fuente externa" en eventos | `resources/js/src/views/apps/calendar.vue` | El usuario no tiene retroalimentación visual de la sincronización. `Event.sync_source` (`'google'`, `'outlook'`, `null`) ya existe en el modelo pero no se renderiza |
| H9 | El proyecto no usa Font Awesome. Iconografía actual: Tabler icons en `components/icon/*.vue` o SVG inline | `resources/js/src/components/icon/` | Los badges/spinners de sync deben usar iconos del proyecto. Crear `icon-calendar-sync.vue` si no existe (no asumir librerías externas) |
| H10 | El cron `Schedule::job(new PullCalendarEventsJob)->everyFifteenMinutes()` no respeta `last_pull_at` a nivel de fila — el `ShouldBeUnique` solo evita ejecuciones paralelas del job, no del usuario individual | `routes/console.php` + `PullCalendarEventsJob` | Si el pull on-demand acaba de correr (cooldown 15min), el cron lo repetirá inmediatamente. Necesita verificación `last_pull_at < now()->subMinutes(15)` por fila |
| H11 | `unplugin-vue-i18n` falla en build si valores i18n contienen `<` o `>` literales (Gotcha G10 en `CLAUDE.md`) | `vite.config.ts` | Mensajes de error tipo "Token expired (re-auth needed)" no pueden incluir paréntesis angulares. Usar paréntesis redondos o comillas |
| H12 | El modelo `ImmigrationCase` mapea a tabla `cases` (Gotcha G11) — no aplica directamente a este spec, pero sí a las queries de eventos asociados a expediente | `app/Models/ImmigrationCase.php` | Cualquier JOIN raw entre `events` y `cases` debe usar `'cases'`, no `'immigration_cases'` |
| H13 | `OauthToken` tiene `purpose='calendar'` a nivel usuario y `purpose='storage'` a nivel tenant — coexisten | `app/Models/OauthToken.php` + Spec 45 | El refresh de token de calendario y el de storage son flujos separados — no romper el de storage al modificar `OAuthTokenService` |
| H14 | No existen tests Feature para el flujo OAuth de calendario en `tests/Feature/Calendar/` | `tests/` | Refactor riesgoso sin red de seguridad. Crear suite mínima antes de ejecutar los cambios de H2-H3 |
| H15 | El proyecto usa Sanctum SPA con cookies HttpOnly — los `services` frontend no pasan tokens explícitos pero deben llamar `/sanctum/csrf-cookie` si la sesión está vieja (Gotcha implícito en `api.ts`) | `resources/js/src/services/api.ts` | Las nuevas llamadas (retry, on-demand pull) no requieren tratamiento especial; `api.ts` ya maneja 419 y 401 |

### Decisiones Clave

| # | Decisión | Justificación |
|---|---|---|
| D1 | **Refactor `getConnectedProvider()` → `getConnectedProviders(): Collection<string>`** preservando el método singular como deprecated wrapper que devuelve `->first()` | Permite soporte multi-provider real sin romper callers existentes en un primer despliegue. Wrapper se elimina en Fase 4 una vez migrados todos los callers |
| D2 | **Backfill configurable por tenant** (`tenant.settings.calendar_backfill_months` default 6) con tope hardcodeado de 24 meses | El backfill consume cuota de API del proveedor. Permitir > 24 meses introduce riesgo de throttling sin valor tangible — los eventos > 2 años suelen ser ruido |
| D3 | **El backfill inicial corre en `UserCalendarOAuthController::callback()` mediante dispatch sincrono de `PullCalendarEventsJob` con `force=true`**, no en background | El usuario espera ver sus eventos inmediatamente tras conectar. Async generaría una ventana donde el usuario "ve un calendario vacío y se desconecta pensando que falló" |
| D4 | **Endpoint `POST /api/calendar-oauth/{provider}/retry`** que resetea `error_count=0`, `status='active'`, `last_error=null` y dispara pull on-demand inmediato | Separar el reset semántico ("entiendo el error, vuelve a intentar") del flujo on-demand normal. Auditable en logs como acción explícita del usuario |
| D5 | **Multi-provider en `PullCalendarEventsJob`**: iterar la tabla `calendar_sync_status` y llamar `pullEvents($user, $provider)` con argumento explícito | Cada fila representa un (usuario, proveedor); el job no debe asumir "un solo proveedor por usuario". El argumento explícito elimina el bug latente de H3 |
| D6 | **Componente `CalendarConnectionStatus.vue`** que centraliza: badges de estado por proveedor, mensajes de error con sugerencias, botón retry, indicador "última sync hace X min" | Hoy la lógica está dispersa entre `profile.vue` y la sección `CalendarConnections`. Un componente cohesivo facilita pruebas y futuras integraciones (ej: notificaciones push) |
| D7 | **Sin webhooks (mantener polling)** — confirmar que el Spec 45 ya descartó webhooks y respetar esa decisión | Webhooks de Google Calendar requieren dominio público + cert válido; Microsoft Graph requiere endpoint expuesto + renovación cada 4230 minutos. Fuera del alcance de este spec |
| D8 | **Anti-loop con flag estático `$isPulling` se mantiene como está**, documentando explícitamente la limitación process-scoped en `CLAUDE.md` (ya documentada como G8) | Migrar a Redis cache key requeriría infraestructura no aprobada. El sistema en producción usa un solo worker por defecto |
| D9 | **Configuración de backfill en `tenant.settings` JSON column** (no nueva columna SQL) | El modelo `Tenant` ya tiene `settings: array` cast. Una nueva columna obliga a migración + posible squash. JSON column escala mejor para futuras settings (timeout, max_events_per_pull, etc.) |
| D10 | **Notificación de token expirado irrecuperable**: registro en `activity_log` + flag `last_error` legible en UI + email diferido a `Phase 4` (queue: low) | Email inmediato bloquea el job; email diferido evita spam si el refresh recupera. La UI muestra el error de forma persistente hasta retry/reconexión |

---

## 1. Backend — Configuración Tenant (Backfill Window)

### 1.1 Cast y default en `Tenant`

El modelo `Tenant` ya expone `settings` como `array` (cast Eloquent). Agregar accessor de conveniencia:

```php
// app/Models/Tenant.php — agregar método:

public function calendarBackfillMonths(): int
{
    $value = (int) data_get($this->settings, 'calendar_backfill_months', 6);
    return max(1, min(24, $value));
}

public function calendarLookaheadMonths(): int
{
    $value = (int) data_get($this->settings, 'calendar_lookahead_months', 12);
    return max(1, min(24, $value));
}
```

**Razón de los clamps:** evitar que un super-admin malintencionado o un bug de UI guarde `999` y dispare un backfill imposible de cumplir.

### 1.2 Endpoint admin para actualizar settings

`TenantController::updateSettings()` ya existe ([[28_multi_tenant_branding_storage|Spec 28]]). Solo se agrega validación a los dos nuevos campos:

```php
$request->validate([
    'calendar_backfill_months'  => 'sometimes|integer|min:1|max:24',
    'calendar_lookahead_months' => 'sometimes|integer|min:1|max:24',
]);
```

---

## 2. Backend — Refactor `CalendarSyncService` Multi-Provider

### 2.1 Nuevo método principal

```php
// app/Services/Calendar/CalendarSyncService.php

/**
 * Lista todos los proveedores conectados y NO en error para un usuario.
 * Sustituye el supuesto "un solo proveedor activo" del Spec 45.
 */
public function getConnectedProviders(User $user): \Illuminate\Support\Collection
{
    return CalendarSyncStatus::where('user_id', $user->id)
        ->whereIn('status', ['active', 'paused'])
        ->pluck('provider');
}

/**
 * Wrapper deprecated — preservado para no romper callers existentes en Fase 1.
 * Eliminar en Fase 4 una vez todos los callers migrados.
 *
 * @deprecated Usar getConnectedProviders() y procesar cada provider.
 */
public function getConnectedProvider(User $user): ?string
{
    return $this->getConnectedProviders($user)->first();
}
```

### 2.2 Pull con proveedor explícito

```php
public function pullEvents(User $user, ?string $provider = null): void
{
    // Si no se especifica provider: iterar todos los conectados (compatibilidad)
    if ($provider === null) {
        foreach ($this->getConnectedProviders($user) as $p) {
            $this->pullEvents($user, $p);
        }
        return;
    }

    $syncStatus = CalendarSyncStatus::where('user_id', $user->id)
        ->where('provider', $provider)
        ->first();

    if (!$syncStatus || $syncStatus->status === 'error') {
        return;
    }

    [$service, $token] = $this->resolveServiceAndToken($provider, $user);

    // Ventana de backfill desde settings del tenant
    $tenant = $user->tenant;
    $backfillMonths  = $tenant->calendarBackfillMonths();
    $lookaheadMonths = $tenant->calendarLookaheadMonths();

    // Si es la primera vez (last_pull_at NULL) → usar ventana completa
    // Si ya hubo pull previo → usar incremental desde last_pull_at
    $since = $syncStatus->last_pull_at ?? now()->subMonths($backfillMonths);
    $until = now()->addMonths($lookaheadMonths);

    try {
        self::$isPulling = true;
        $events = $service->listEvents($token, $since, $until);
        $this->processExternalEvents($events, $user, $provider);

        $syncStatus->update([
            'last_pull_at' => now(),
            'error_count' => 0,
            'last_error'  => null,
        ]);
    } catch (\Throwable $e) {
        $this->handlePullError($syncStatus, $e);
        throw $e;
    } finally {
        self::$isPulling = false;
    }
}
```

### 2.3 Manejo de error con circuit breaker

```php
private function handlePullError(CalendarSyncStatus $status, \Throwable $e): void
{
    $status->increment('error_count');
    $status->update([
        'last_error' => substr($e->getMessage(), 0, 500),
    ]);

    if ($status->error_count >= 5) {
        $status->update(['status' => 'error']);
        \Log::warning('Calendar sync circuit breaker opened', [
            'user_id'  => $status->user_id,
            'provider' => $status->provider,
            'error'    => $e->getMessage(),
        ]);
    }
}
```

---

## 3. Backend — Endpoint de Retry

### 3.1 Nuevo método en `UserCalendarOAuthController`

```php
// app/Http/Controllers/Api/UserCalendarOAuthController.php

public function retry(Request $request, string $provider): JsonResponse
{
    $user = Auth::user();

    if (!in_array($provider, ['google', 'microsoft'], true)) {
        return response()->json(['error' => 'invalid_provider'], 422);
    }

    $syncStatus = CalendarSyncStatus::where('user_id', $user->id)
        ->where('provider', $provider)
        ->first();

    if (!$syncStatus) {
        return response()->json(['error' => 'no_connection'], 404);
    }

    // Reset del circuit breaker
    $syncStatus->update([
        'status'      => 'active',
        'error_count' => 0,
        'last_error'  => null,
    ]);

    // Pull inmediato (síncrono) para feedback inmediato al usuario
    try {
        $this->syncService->pullEvents($user, $provider);
        return response()->json([
            'success'    => true,
            'pulled_at'  => now()->toIso8601String(),
            'provider'   => $provider,
        ]);
    } catch (\Throwable $e) {
        \Log::warning('Calendar retry failed', [
            'user_id'  => $user->id,
            'provider' => $provider,
            'error'    => $e->getMessage(),
        ]);
        return response()->json([
            'success'  => false,
            'error'    => 'sync_failed',
            'message'  => $e->getMessage(),
        ], 502);
    }
}
```

### 3.2 Ruta nueva

```php
// routes/api.php — dentro del grupo auth:sanctum + tenant
Route::prefix('calendar-oauth')->group(function () {
    // ...rutas existentes...
    Route::post('/{provider}/retry', [UserCalendarOAuthController::class, 'retry'])
         ->where('provider', 'google|microsoft')
         ->name('calendar-oauth.retry');
});
```

### 3.3 Cambio en endpoint `pull` para permitir status `paused`

```php
// UserCalendarOAuthController::pull() línea ~275

// ANTES:
$statuses = CalendarSyncStatus::where('user_id', $user->id)
    ->where('status', 'active')
    ->get();

// DESPUÉS:
$statuses = CalendarSyncStatus::where('user_id', $user->id)
    ->whereIn('status', ['active', 'paused'])
    ->get();
```

Reasoning: `paused` es un estado válido del lado Microsoft (provisioning vía credenciales tenant) y debe permitir refresh manual. Solo `error` queda excluido — para esos se usa `retry`.

---

## 4. Backend — Backfill Inicial en Callback OAuth

### 4.1 Cambio en `UserCalendarOAuthController::callback()`

Tras crear `CalendarSyncStatus` y antes del redirect a `/users/profile`:

```php
// Disparar pull inicial sincrono con ventana completa de backfill
try {
    $this->syncService->pullEvents($user, $provider);
} catch (\Throwable $e) {
    \Log::warning('Initial backfill failed (non-fatal)', [
        'user_id'  => $user->id,
        'provider' => $provider,
        'error'    => $e->getMessage(),
    ]);
    // No revertir la conexión — el pull puede recuperarse en el siguiente cron
}

return redirect()->away(
    config('app.frontend_url') . "/users/profile?calendar_connected={$provider}"
);
```

**Justificación de "no revertir":** si el backfill falla pero la conexión OAuth fue exitosa, el usuario tiene tokens válidos. El siguiente pull periódico o un retry manual recuperarán. Revertir generaría un loop frustrante de "reconectar repetidamente".

---

## 5. Backend — Job Refactor Multi-Provider

### 5.1 `PullCalendarEventsJob`

```php
// app/Jobs/PullCalendarEventsJob.php

public function handle(CalendarSyncService $syncService): void
{
    $statuses = CalendarSyncStatus::where('status', 'active')
        ->where(function ($q) {
            $q->whereNull('last_pull_at')
              ->orWhere('last_pull_at', '<', now()->subMinutes(15));
        })
        ->with('user')
        ->get();

    foreach ($statuses as $status) {
        if (!$status->user) continue;

        try {
            $syncService->pullEvents($status->user, $status->provider);
        } catch (\Throwable $e) {
            // El service ya escribe last_error y dispara circuit breaker.
            // El log aquí es para correlación operacional del job.
            \Log::warning('PullCalendarEventsJob: provider failed', [
                'sync_status_id' => $status->id,
                'user_id'        => $status->user_id,
                'provider'       => $status->provider,
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
```

Cambios respecto al Spec 45:
- **Filtro `last_pull_at < now()-15min`** previene re-pull tras un on-demand reciente (H10).
- **`pullEvents($user, $provider)` con argumento explícito** elimina el bug latente de H3.
- **Try/catch por fila** asegura que un usuario con problemas no bloquee al resto del tenant.

---

## 6. Frontend — Componente `CalendarConnectionStatus.vue`

### 6.1 Estructura del componente

```
resources/js/src/components/calendar/CalendarConnectionStatus.vue
```

Responsabilidades:
- Recibe `provider: 'google' | 'microsoft'` como prop.
- Muestra estado actual (`connected | disconnected | error | paused`) con badge tipado.
- Renderiza `last_pull_at` relativo ("Hace 3 min", "Hace 2 horas") con `useDateFormat`.
- Si `status === 'error'`: muestra `last_error` truncado + botón "Reintentar".
- Botón "Conectar" / "Desconectar" según estado.
- Spinner durante operación (loading state local).

### 6.2 Manejo de query params en `users/profile.vue`

Al montar la vista:

```typescript
import { onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { showToast } from '@/utils/toast';
import { t } from '@/composables/use-i18n';

const route = useRoute();
const router = useRouter();

onMounted(() => {
    const connected = route.query.calendar_connected as string | undefined;
    const error     = route.query.calendar_error as string | undefined;

    if (connected) {
        showToast('success', t('calendar_sync.connected', { provider: t(`calendar_sync.provider_${connected}`) }));
    } else if (error) {
        const errorKey = `calendar_sync.error_${error}`;
        showToast('error', t(errorKey, t('calendar_sync.error_generic')));
    }

    if (connected || error) {
        router.replace({ query: { ...route.query, calendar_connected: undefined, calendar_error: undefined } });
    }
});
```

### 6.3 Códigos de error normalizados

El backend ya redirige a `?calendar_error={code}`. Los códigos esperados:

| Código | Significado | Mensaje sugerido en UI |
|---|---|---|
| `state_mismatch` | CSRF/state inválido | "La sesión de conexión expiró. Intente nuevamente." |
| `oauth_denied` | Usuario rechazó permisos | "Debe autorizar el acceso al calendario para sincronizar." |
| `token_exchange_failed` | El intercambio code→token falló | "No se pudo obtener acceso al calendario. Verifique credenciales del tenant." |
| `provider_unsupported` | Provider no es google/microsoft | "Proveedor de calendario no soportado." |
| `tenant_not_configured` | El tenant no tiene credenciales OAuth | "El administrador debe configurar las credenciales del proveedor." |

---

## 7. Frontend — Indicadores en `apps/calendar.vue`

### 7.1 Badge de fuente en eventos

`Event.sync_source` puede ser `'google'`, `'outlook'`, o `null` (creado localmente). En el `eventDidMount` de FullCalendar:

```typescript
function eventDidMount(info: EventMountArg) {
    const source = info.event.extendedProps.sync_source as string | null;
    if (source) {
        const badge = document.createElement('span');
        badge.className = 'fc-event-source-badge';
        badge.textContent = source === 'google' ? 'G' : 'O';
        badge.title = t(`calendar_sync.source_${source}`);
        info.el.querySelector('.fc-event-title')?.prepend(badge);
    }
}
```

CSS asociado en `assets/css/components.css`:

```css
.fc-event-source-badge {
    display: inline-block;
    padding: 0 4px;
    margin-right: 4px;
    font-size: 9px;
    font-weight: 700;
    color: white;
    background-color: #805dca; /* secondary */
    border-radius: 3px;
}
```

### 7.2 Barra de estado superior

Sobre la grilla del calendario:

```vue
<div class="flex items-center justify-between mb-3 px-1 text-xs text-gray-500">
    <span v-if="syncStore.lastPullAt">
        {{ t('calendar_sync.last_sync_at', { time: formatRelative(syncStore.lastPullAt) }) }}
    </span>
    <span v-else>{{ t('calendar_sync.never_synced') }}</span>
    <button
        class="btn btn-sm btn-outline-primary"
        :disabled="syncStore.isPulling"
        @click="syncStore.refreshNow()"
    >
        <span v-if="syncStore.isPulling" class="spinner-border spinner-border-sm" />
        {{ syncStore.isPulling ? t('calendar_sync.refreshing') : t('calendar_sync.refresh_now') }}
    </button>
</div>
```

---

## 8. Frontend — Store y Service Updates

### 8.1 Nuevo método en `calendarSyncService.ts`

```typescript
// resources/js/src/services/calendarSyncService.ts

retry(provider: 'google' | 'microsoft'): Promise<{ success: boolean; pulled_at?: string; message?: string }> {
    return api.post(`/calendar-oauth/${provider}/retry`).then(r => r.data);
},

pullOnDemand(): Promise<{ pulled: boolean; providers?: string[]; reason?: string }> {
    return api.post('/calendar-oauth/pull').then(r => r.data);
},
```

### 8.2 Nuevo store `useCalendarSyncStore`

```
resources/js/src/stores/calendarSync.ts
```

Estado:

```typescript
interface ProviderStatus {
    provider: 'google' | 'microsoft';
    status: 'active' | 'paused' | 'error' | 'disconnected';
    last_pull_at: string | null;
    last_push_at: string | null;
    last_error: string | null;
    error_count: number;
}

interface CalendarSyncState {
    providers: ProviderStatus[];
    isLoading: boolean;
    isPulling: boolean;
    isRetrying: Record<string, boolean>;
}
```

Acciones: `fetchStatus()`, `refreshNow()`, `retry(provider)`, `disconnect(provider)`.

---

## 9. Internacionalización (i18n)

**Regla crítica:** Keys FLAT. Ningún valor contiene `<` ni `>` literal (Gotcha G10). Actualizar `es.json`, `en.json`, `fr.json`.

| Key | Valor (es) | Valor (en) | Valor (fr) |
|-----|-----------|-----------|-----------|
| `calendar_sync.connected` | `"Calendario {provider} conectado"` | `"{provider} calendar connected"` | `"Calendrier {provider} connecté"` |
| `calendar_sync.provider_google` | `"Google"` | `"Google"` | `"Google"` |
| `calendar_sync.provider_microsoft` | `"Microsoft"` | `"Microsoft"` | `"Microsoft"` |
| `calendar_sync.source_google` | `"Evento importado de Google"` | `"Event imported from Google"` | `"Événement importé de Google"` |
| `calendar_sync.source_outlook` | `"Evento importado de Outlook"` | `"Event imported from Outlook"` | `"Événement importé d'Outlook"` |
| `calendar_sync.last_sync_at` | `"Última sincronización: {time}"` | `"Last sync: {time}"` | `"Dernière sync: {time}"` |
| `calendar_sync.never_synced` | `"Sin sincronización todavía"` | `"Not yet synchronized"` | `"Pas encore synchronisé"` |
| `calendar_sync.refresh_now` | `"Actualizar ahora"` | `"Refresh now"` | `"Actualiser maintenant"` |
| `calendar_sync.refreshing` | `"Actualizando..."` | `"Refreshing..."` | `"Actualisation..."` |
| `calendar_sync.retry` | `"Reintentar"` | `"Retry"` | `"Réessayer"` |
| `calendar_sync.status_active` | `"Sincronizando"` | `"Syncing"` | `"Synchronisation"` |
| `calendar_sync.status_paused` | `"Pausado"` | `"Paused"` | `"En pause"` |
| `calendar_sync.status_error` | `"Error de sincronización"` | `"Sync error"` | `"Erreur de synchronisation"` |
| `calendar_sync.status_disconnected` | `"Desconectado"` | `"Disconnected"` | `"Déconnecté"` |
| `calendar_sync.error_generic` | `"Ocurrió un error al conectar el calendario"` | `"An error occurred connecting the calendar"` | `"Une erreur est survenue lors de la connexion"` |
| `calendar_sync.error_state_mismatch` | `"La sesión de conexión expiró. Intente nuevamente."` | `"Connection session expired. Try again."` | `"Session de connexion expirée. Réessayez."` |
| `calendar_sync.error_oauth_denied` | `"Debe autorizar el acceso al calendario para sincronizar."` | `"You must grant calendar access to sync."` | `"Vous devez autoriser l'accès au calendrier."` |
| `calendar_sync.error_token_exchange_failed` | `"No se pudo obtener acceso al calendario."` | `"Could not obtain calendar access."` | `"Impossible d'obtenir l'accès au calendrier."` |
| `calendar_sync.error_tenant_not_configured` | `"El administrador debe configurar las credenciales del proveedor."` | `"Admin must configure provider credentials."` | `"L'administrateur doit configurer les identifiants."` |
| `calendar_sync.token_expired` | `"Su token de calendario expiró. Reconecte para continuar."` | `"Your calendar token expired. Reconnect to continue."` | `"Votre jeton de calendrier a expiré."` |
| `calendar_sync.backfill_months_label` | `"Meses de historial a sincronizar"` | `"Months of history to sync"` | `"Mois d'historique à synchroniser"` |
| `calendar_sync.lookahead_months_label` | `"Meses futuros a sincronizar"` | `"Future months to sync"` | `"Mois futurs à synchroniser"` |

---

## 10. Riesgos y Mitigaciones

| ID | Riesgo | Prob. | Impacto | Mitigación |
|----|--------|-------|---------|-----------|
| R1 | El refactor `getConnectedProvider()` → `getConnectedProviders()` rompe llamadas no detectadas en grep | MEDIA | ALTA | Wrapper deprecated en Fase 1 + búsqueda exhaustiva con `grep -rn "getConnectedProvider"` en todo el proyecto antes de eliminar |
| R2 | Backfill inicial sincrono en el callback puede tardar >30s con muchos eventos y agotar el timeout HTTP | MEDIA | MEDIA | Limitar la ventana en el backfill inicial a 3 meses (no la ventana completa del tenant). El cron posterior completará el rango |
| R3 | El cron de pull periódico ahora itera por proveedor — duplica la cantidad de llamadas HTTP externas | MEDIA | MEDIA | El filtro `last_pull_at < now()-15min` y el throttling natural por usuario limitan el impacto. Monitorear con `Log::info` en el job durante primera semana |
| R4 | Reset de circuit breaker desde UI permite spam de retry contra un endpoint roto | BAJA | MEDIA | Rate-limit en la ruta retry (`throttle:5,1` — 5 intentos por minuto por usuario) |
| R5 | El badge `G`/`O` en eventos rompe estilos al renderizar dentro de `.fc-event-title` (FullCalendar reescribe DOM en cada render) | MEDIA | BAJA | Usar `eventDidMount` (re-ejecutado en cada render) y verificar visualmente en vista mes/semana/día |
| R6 | El query param `calendar_error` no se limpia y persiste en historial del navegador | BAJA | BAJA | `router.replace()` después de procesar (ya incluido en el código de §6.2) |
| R7 | El usuario tiene Google y Microsoft conectados y un mismo evento aparece duplicado (creado en uno, syncado al otro, y el segundo lo vuelve a syncar) | MEDIA | ALTA | Verificar que el push de un evento `sync_source != null` está bloqueado en `EventSyncObserver::shouldSync()`. Si no lo está, agregarlo en este spec |
| R8 | `tenant.settings.calendar_backfill_months` no existe en tenants antiguos → `data_get` devuelve `null` → cast a `int` da 0 → ventana vacía | BAJA | ALTA | El accessor `calendarBackfillMonths()` ya hace `max(1, min(24, $value))` con default explícito de 6. Cubierto |
| R9 | Tests Feature requieren mockear HTTP a Google/Microsoft — sin mock, fallan en CI | MEDIA | MEDIA | Usar `Http::fake()` de Laravel para todas las llamadas externas. Documentar en cabecera del test |
| R10 | Tokens OAuth de calendario pueden expirar entre el filtro del job y el momento del pull — `OAuthTokenService::refresh()` debe propagar fallos correctamente | MEDIA | ALTA | El service ya refresca on-demand antes de devolver el token. Si falla, lanzar excepción que `handlePullError()` capture |

---

## 11. Checklist de Implementación

### Fase 1 — Backend Core (Refactor + Endpoint Retry) ✅ Completada (2026-05-15)

- [x] Agregar accessors `calendarBackfillMonths()` y `calendarLookaheadMonths()` en `Tenant`
- [x] Agregar validación de `calendar_backfill_months` / `calendar_lookahead_months` en `UpdateTenantSettingsRequest`
- [x] Refactorizar `CalendarSyncService::pullEvents($user, $provider = null)` con ventana de backfill desde settings
- [x] Agregar `CalendarSyncService::getConnectedProviders()` (plural) — singular marcado `@deprecated`
- [x] Extraer manejo de error a `CalendarSyncService::handlePullError()` privado
- [x] Crear método `UserCalendarOAuthController::retry()` + ruta `POST /api/calendar-oauth/{provider}/retry` con `throttle:5,1`
- [x] Cambiar filtro de `UserCalendarOAuthController::pull()` de `status='active'` a `whereIn(['active', 'paused'])`
- [x] Disparar `pullEvents()` sincrono en `callback()` tras crear `CalendarSyncStatus`
- [x] `EventSyncObserver::shouldSync()` ahora bloquea `sync_source IN ['google','outlook']` (anti-loop multi-provider)
- [x] `CalendarProviderInterface::listEvents()` + `GoogleCalendarService` + `MicrosoftCalendarService` actualizados para aceptar `$until = null` como segundo parámetro opcional

### Fase 2 — Job Refactor y Configuración ✅ Completada (2026-05-15)

- [x] Refactorizar `PullCalendarEventsJob::handle()` para iterar por `(user, provider)` con argumento explícito
- [x] Agregar filtro `last_pull_at < now()->subMinutes(15)` en el query del job (cooldown anti-doble-pull)
- [x] Agregar try/catch por fila para aislar fallos de un usuario respecto al resto
- [ ] Verificar que `OAuthTokenService::refresh()` propaga excepción si el refresh falla irrecuperablemente — pendiente (DT-08)
- [ ] Agregar entrada en `activity_log` cuando un `CalendarSyncStatus` pasa a `status='error'` — pendiente (DT-08)
- [x] Documentado en `CLAUDE.md` — settings `calendar_backfill_months`/`calendar_lookahead_months` y multi-provider

### Fase 3 — Frontend (Store + Indicadores + Retry UI) ✅ Completada (2026-05-15)

- [x] Crear `resources/js/src/stores/calendarSync.ts` con state `google`, `microsoft`, `isPulling`, `isRetrying` y acciones `fetchStatus`, `refreshNow`, `retry`, `disconnect`
- [x] Agregar métodos `retry(provider)` y `pullOnDemand()` en `resources/js/src/services/calendarSyncService.ts`
- [x] Botón Retry agregado en `CalendarConnections.vue` (visible cuando `sync_status === 'error'`) — sustituye la creación de componente separado
- [x] Query params `calendar_connected` / `calendar_error` ya manejados con Swal en `CalendarConnections.vue` (existente, verificado)
- [x] Barra de estado superior en `views/apps/calendar.vue` con `lastPullAt` + botón "Actualizar ahora" con spinner
- [x] Badge G/O en eventos ya existía en `calendar.vue` via slot `eventContent` de FullCalendar (verificado, no requería cambio)
- [x] 18 keys i18n FLAT nuevas en `es.json`, `en.json`, `fr.json` (4 keys del spec ya existían con valores equivalentes — no sobreescritas)
- [x] `npm run build` — ✓ sin errores (9.15s)

### Fase 4 — Tests y Validación ⏳ Pendiente

- [ ] Crear `tests/Feature/Calendar/CalendarOAuthFlowTest.php`:
  - [ ] `test_redirect_returns_url_for_google()`
  - [ ] `test_redirect_returns_auto_connected_for_microsoft_with_tenant_credentials()`
  - [ ] `test_callback_creates_sync_status_and_redirects_to_profile()`
  - [ ] `test_callback_with_invalid_state_redirects_with_error()`
  - [ ] `test_status_endpoint_returns_all_providers()`
  - [ ] `test_disconnect_revokes_token_and_deletes_status()`
- [ ] Crear `tests/Feature/Calendar/CalendarRetryEndpointTest.php`:
  - [ ] `test_retry_resets_circuit_breaker()`
  - [ ] `test_retry_returns_404_when_no_connection()`
  - [ ] `test_retry_is_rate_limited()`
- [ ] Crear `tests/Unit/Services/Calendar/CalendarSyncServiceMultiProviderTest.php`:
  - [ ] `test_pull_events_with_explicit_provider_only_pulls_that_provider()`
  - [ ] `test_pull_events_without_provider_iterates_all_connected()`
  - [ ] `test_backfill_window_uses_tenant_settings()`
  - [ ] `test_circuit_breaker_opens_after_5_consecutive_errors()`
  - [ ] `test_anti_loop_flag_prevents_observer_during_pull()`
- [ ] Eliminar el wrapper deprecated `getConnectedProvider()` tras confirmar 0 callers restantes (`grep -rn`)
- [ ] Smoke test manual: conectar Google, verificar backfill de 6 meses
- [ ] Smoke test manual: forzar error 5 veces, verificar status='error', usar retry, verificar recovery
- [ ] Smoke test manual: conectar Google + Microsoft simultáneamente, verificar ambos sincronizan
- [ ] Smoke test manual: badge G/O visible en eventos del calendario
- [ ] Actualizar `spec/INDEX.md`: Spec 62 `PEND` → `DONE`
- [ ] Actualizar `MEMORY.md` con entrada en "Implementaciones Recientes"
- [ ] Marcar Spec 45 como `PART` o referenciar Spec 62 como continuación

---

## 12. Archivos a Crear/Modificar

### Crear

| Archivo | Tipo |
|---------|------|
| `resources/js/src/components/calendar/CalendarConnectionStatus.vue` | Componente Vue |
| `resources/js/src/stores/calendarSync.ts` | Store Pinia |
| `tests/Feature/Calendar/CalendarOAuthFlowTest.php` | Test Feature |
| `tests/Feature/Calendar/CalendarRetryEndpointTest.php` | Test Feature |
| `tests/Unit/Services/Calendar/CalendarSyncServiceMultiProviderTest.php` | Test Unit |

### Modificar

| Archivo | Cambio |
|---------|--------|
| `app/Models/Tenant.php` | Agregar `calendarBackfillMonths()`, `calendarLookaheadMonths()` |
| `app/Services/Calendar/CalendarSyncService.php` | `getConnectedProviders()`, refactor `pullEvents($user, $provider)`, `handlePullError()` |
| `app/Http/Controllers/Api/UserCalendarOAuthController.php` | Nuevo método `retry()`; ampliar filtro de `pull()` a `paused`; disparar backfill en `callback()` |
| `app/Http/Controllers/Api/TenantController.php` | Validar `calendar_backfill_months`, `calendar_lookahead_months` |
| `app/Jobs/PullCalendarEventsJob.php` | Iterar por `(user, provider)`, filtro `last_pull_at`, try/catch por fila |
| `app/Observers/EventSyncObserver.php` | Verificar bloqueo de push para eventos con `sync_source != null` |
| `routes/api.php` | Agregar ruta `POST /calendar-oauth/{provider}/retry` con `throttle:5,1` |
| `resources/js/src/services/calendarSyncService.ts` | Métodos `retry()`, `pullOnDemand()` |
| `resources/js/src/views/users/profile.vue` | Integrar `CalendarConnectionStatus`, manejar query params con toast |
| `resources/js/src/views/apps/calendar.vue` | Barra de estado superior, badge G/O en `eventDidMount` |
| `resources/css/components.css` | Estilos `.fc-event-source-badge` |
| `resources/js/src/locales/es.json` | 22 keys FLAT (sección `calendar_sync.*`) |
| `resources/js/src/locales/en.json` | 22 keys FLAT |
| `resources/js/src/locales/fr.json` | 22 keys FLAT |
| `CLAUDE.md` | Documentar settings `calendar_backfill_months`/`calendar_lookahead_months`; nota sobre multi-provider |
| `spec/INDEX.md` | Registrar Spec 62 |
| `spec/45_sincronizacion_calendarios_externos.md` | Cabecera: nota "Continuado en Spec 62" |
| `MEMORY.md` | Entrada al completar |
