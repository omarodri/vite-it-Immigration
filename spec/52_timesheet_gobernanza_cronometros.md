---
tipo: spec
numero: 52
modulo: Timesheet
dominio: TIME
estado: DONE
tags: [spec, timesheet, done]
relacionados:
  - "[[51_timesheet_control_tiempo|Spec 51]]"
  - "[[53_sesion_unica_por_usuario|Spec 53]]"
---
# [[52_timesheet_gobernanza_cronometros|Spec 52]]: Gobernanza de Cronómetros — Concurrencia, Visibilidad Global y Persistencia

**Versión:** 1.0
**Fecha:** 2026-05-10
**Branch:** `timesheet-governance`
**Stack:** Laravel 12 + Vue 3.5 + MySQL 8.0 + Pinia + Sanctum SPA

---

## Resumen Ejecutivo

Extensión del sistema de control de tiempo ([[51_timesheet_control_tiempo|Spec 51]]) para blindar la integridad de los registros contra errores humanos y fallos de sesión. Añade auto-detención de cronómetros huérfanos, un indicador global en el Header, advertencia en cierre de sesión, validación de concurrencia con modal informativo y soporte multi-pestaña.

### Decisiones Clave

| # | Decisión | Justificación |
|---|---|---|
| 1 | **Scheduled Job (no Middleware) para auto-stop** | El Middleware solo actúa si el usuario envía peticiones; el caso de fuga es exactamente quien se fue sin hacer nada. El scheduler actúa incondicionalmente cada minuto. |
| 2 | **`navigator.sendBeacon` para cierre de pestaña** | El interceptor de 401 no puede hacer el stop call porque la sesión ya expiró (el call también fallaría con 401). `sendBeacon` es best-effort; el scheduler es la red de seguridad real. |
| 3 | **Polling 60 s (no WebSockets/Reverb)** | Reverb requeriría servidor WebSocket, canales de auth y configuración de broadcasting — desproporcionado para este único caso de uso. Reconsiderar si ≥5 señales en tiempo real entran al roadmap. |
| 4 | **`tenants.settings.max_timer_duration` en JSON** | La columna `settings` ya existe con cast `array` en `Tenant.php`. No se necesita migración de estructura. |
| 5 | **Elapsed en Header derivado de `started_at` del servidor** | El ticker JS es solo visualización. `duration_seconds` siempre se computa como `TIMESTAMPDIFF(SECOND, started_at, NOW())` en el servidor al hacer stop. Drift acumulado = 0. |
| 6 | **`BroadcastChannel('timesheet')` para multi-tab** | Sincroniza el estado del timer entre pestañas del mismo origen sin round-trips adicionales al servidor. |
| 7 | **Permisos nuevos en migración dedicada (no seeder)** | Regla G4 del proyecto — los permisos deben sobrevivir `migrate:fresh` en staging/producción. |
| 8 | **Hidratación de `activeTimer` solo en boot de app** | Hidratar en cada cambio de ruta genera tráfico innecesario. El store Pinia + BroadcastChannel mantienen la coherencia local; el scheduler mantiene la coherencia del servidor. |

---

## 1. Modelado de Datos

### 1.1 Sin cambios de esquema en `time_logs`

La tabla ya tiene:
- Índice `idx_active_timer (user_id, ended_at)` — correcto para `WHERE user_id = ? AND ended_at IS NULL`.
- `started_at`, `ended_at`, `duration_seconds`, `source` — suficientes para auto-stop.
- `BelongsToTenant` — el job itera por tenant para respetar `max_timer_duration` por tenant.

### 1.2 Configuración de tenant

`tenants.settings` es JSON con cast `array`. Se añade la clave `max_timer_duration` (entero, minutos).

```php
// Valor por defecto si la clave no existe
$maxMin = (int) data_get($tenant->settings, 'max_timer_duration', config('session.lifetime', 120));
```

Validación del endpoint de settings:

```php
'max_timer_duration' => 'required|integer|min:15|max:1440'
```

### 1.3 Nuevos permisos (migración)

| Permiso | Descripción |
|---|---|
| `time_logs.stop_others` | Detener el cronómetro de otro usuario |
| `time_logs.configure` | Configurar `max_timer_duration` en el tenant |

Asignación:
- `super-admin`, `admin`: ambos permisos
- `attorney`, `paralegal`, `client`: ninguno de los dos nuevos

---

## 2. Backend Laravel

### 2.1 Enriquecer `TimeLogService::getActiveTimerForUser`

Agregar `.client` al eager-load existente para que el `TimeLogResource` pueda exponer `case_code` y `client.name` al frontend.

```php
// app/Services/Timesheet/TimeLogService.php
return TimeLog::byUser($user->id)
    ->running()
    ->with(['immigrationCase.client', 'todo'])  // añadir .client
    ->first();
```

### 2.2 `TimeLogResource` — campos adicionales

Asegurar que el resource exponga:

```php
'immigration_case' => [
    'id'        => $this->immigrationCase?->id,
    'case_code' => $this->immigrationCase?->case_code,
    'client'    => [
        'id'   => $this->immigrationCase?->client?->id,
        'name' => $this->immigrationCase?->client?->full_name,
    ],
],
'user' => [
    'id'   => $this->user?->id,
    'name' => $this->user?->name,
],
```

### 2.3 Job `AutoStopExpiredTimersJob`

**Archivo:** `app/Jobs/AutoStopExpiredTimersJob.php`

```php
<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class AutoStopExpiredTimersJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable;

    public function uniqueId(): string
    {
        return 'auto-stop-timers';
    }

    public function handle(): void
    {
        $defaultMinutes = (int) config('session.lifetime', 120);

        Tenant::query()->chunkById(100, function ($tenants) use ($defaultMinutes) {
            foreach ($tenants as $tenant) {
                $maxMinutes = (int) data_get($tenant->settings, 'max_timer_duration', $defaultMinutes);

                $count = DB::table('time_logs')
                    ->where('tenant_id', $tenant->id)
                    ->whereNull('ended_at')
                    ->whereNotNull('started_at')
                    ->whereNull('deleted_at')
                    ->where('started_at', '<', now()->subMinutes($maxMinutes))
                    ->update([
                        'ended_at'         => now(),
                        'duration_seconds' => DB::raw('TIMESTAMPDIFF(SECOND, started_at, NOW())'),
                        'description'      => DB::raw("CONCAT(COALESCE(description,''), ' [auto-stopped: session expired]')"),
                        'updated_at'       => now(),
                    ]);

                if ($count > 0) {
                    logger()->info("AutoStopExpiredTimers: stopped {$count} timer(s) for tenant {$tenant->id}");
                }
            }
        });
    }
}
```

**Registro en `routes/console.php`:**

```php
use App\Jobs\AutoStopExpiredTimersJob;

Schedule::job(new AutoStopExpiredTimersJob)->everyMinute()->withoutOverlapping();
```

`ShouldBeUnique` + `withoutOverlapping()` sigue el patrón de `PullCalendarEventsJob` (ver CLAUDE.md G8).

### 2.4 Endpoint `beacon-stop`

Ruta a agregar en `routes/api.php` dentro del grupo `auth:sanctum + tenant`:

```php
Route::post('me/active-timer/beacon-stop', [TimeLogController::class, 'beaconStop']);
```

Método del controller:

```php
public function beaconStop(Request $r): JsonResponse
{
    $log = $this->service->getActiveTimerForUser($r->user());
    if ($log) {
        $this->service->stopTimer($log->id, $r->user());
    }
    return response()->json(['ok' => true]);
}
```

### 2.5 Endpoint de configuración del tenant

Agregar en el controller de tenant settings o crear un endpoint específico:

```php
// PATCH /api/admin/tenant/settings
public function updateSettings(Request $r): JsonResponse
{
    $this->authorize('time_logs.configure');
    $data = $r->validate(['max_timer_duration' => 'required|integer|min:15|max:1440']);

    $tenant = app('tenant');
    $tenant->settings = array_merge($tenant->settings ?? [], $data);
    $tenant->save();

    return response()->json(['settings' => $tenant->settings]);
}
```

**Entrega al frontend:** añadir `max_timer_duration` al payload del endpoint `/api/auth/me` (ya hidrata `useUserStore` en el boot de la app — sin request adicional).

### 2.6 Permisos — migración dedicada

**Archivo:** `database/migrations/2026_05_10_000001_add_timer_governance_permissions.php`

```php
public function up(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    foreach (['time_logs.stop_others', 'time_logs.configure'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    Role::where('name', 'admin')->first()?->givePermissionTo([
        'time_logs.stop_others',
        'time_logs.configure',
    ]);

    Role::where('name', 'super-admin')->first()?->syncPermissions(Permission::all());
}

public function down(): void
{
    Permission::where('name', 'time_logs.stop_others')->delete();
    Permission::where('name', 'time_logs.configure')->delete();
}
```

---

## 3. API y Transferencia de Datos

### 3.1 Endpoints afectados

| Método | Endpoint | Cambio |
|---|---|---|
| `GET` | `/api/me/active-timer` | Añadir eager-load `immigrationCase.client` |
| `POST` | `/api/me/active-timer/beacon-stop` | **Nuevo** — para `sendBeacon` |
| `PATCH` | `/api/admin/tenant/settings` | **Nuevo** — para `max_timer_duration` |

### 3.2 Ausencia de endpoint cross-user en MVP

El modal de conflicto (sección 4.3) solo muestra el timer activo del propio usuario. La vista de "quién tiene activo un timer en este expediente" para supervisores es una extensión futura:

```
GET /api/cases/{case}/active-timers  →  gated por time_logs.view_others
```

No se implementa en este spec para mantener la superficie de permisos mínima.

---

## 4. Frontend Vue.js

### 4.1 Hidratación en boot de app

```typescript
// resources/js/src/App.vue
import { watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useTimesheetStore } from '@/stores/useTimesheetStore';

const authStore = useAuthStore();
const timesheet = useTimesheetStore();

watch(
  () => authStore.isAuthenticated,
  (isAuth) => {
    if (isAuth) timesheet.fetchActiveTimer();
    else timesheet.clearAll();
  },
  { immediate: true }
);
```

### 4.2 Multi-tab con `BroadcastChannel`

Añadir a `useTimesheetStore.ts`:

```typescript
const channel = new BroadcastChannel('timesheet');

// En startTimer:
this.activeTimer = res.data.data;
channel.postMessage({ type: 'started', timer: this.activeTimer });

// En stopTimer:
this.activeTimer = null;
channel.postMessage({ type: 'stopped' });

// Nueva action initBroadcast():
initBroadcast() {
    channel.onmessage = (ev) => {
        if (ev.data.type === 'started') this.activeTimer = ev.data.timer;
        if (ev.data.type === 'stopped') this.activeTimer = null;
    };
}
```

Llamar `timesheet.initBroadcast()` una vez desde `App.vue` junto a la hidratación.

### 4.3 Composable `useBeaconStop`

**Archivo:** `resources/js/src/composables/useBeaconStop.ts`

```typescript
import { useTimesheetStore } from '@/stores/useTimesheetStore';

export function registerBeaconStop(): void {
    const handler = (): void => {
        const ts = useTimesheetStore();
        if (!ts.activeTimer) return;
        const csrf = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '';
        const blob = new Blob(
            [JSON.stringify({ _token: decodeURIComponent(csrf) })],
            { type: 'application/json' }
        );
        navigator.sendBeacon('/api/me/active-timer/beacon-stop', blob);
    };

    window.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') handler();
    });
    window.addEventListener('pagehide', handler);
}
```

Registrar en `App.vue`:

```typescript
import { registerBeaconStop } from '@/composables/useBeaconStop';
onMounted(() => registerBeaconStop());
```

### 4.4 Indicador global en `Header.vue`

Insertar inmediatamente a la izquierda del bloque Theme (~línea 75):

```vue
<!-- Active Timer Indicator -->
<div v-if="timesheet.hasActiveTimer">
  <router-link
    :to="`/cases/${timesheet.activeTimer!.immigration_case.id}`"
    class="flex items-center gap-2 px-3 py-2 rounded-full bg-success/10 hover:bg-success/20 text-success font-semibold transition-colors"
    :title="$t('timer.active_indicator_tooltip')"
  >
    <span class="relative flex h-3 w-3">
      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success opacity-75"></span>
      <span class="relative inline-flex rounded-full h-3 w-3 bg-success"></span>
    </span>
    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <span class="hidden md:inline tabular-nums text-sm">{{ elapsedDisplay }}</span>
  </router-link>
</div>
```

Script a agregar en `Header.vue`:

```typescript
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useTimesheetStore } from '@/stores/useTimesheetStore';

const timesheet = useTimesheetStore();
const now = ref(Date.now());
let tick: ReturnType<typeof setInterval>;

onMounted(() => { tick = setInterval(() => (now.value = Date.now()), 1000); });
onUnmounted(() => clearInterval(tick));

const elapsedDisplay = computed(() => {
    const t = timesheet.activeTimer;
    if (!t?.started_at) return '00:00';
    const sec = Math.max(0, Math.floor((now.value - new Date(t.started_at).getTime()) / 1000));
    const h = String(Math.floor(sec / 3600)).padStart(2, '0');
    const m = String(Math.floor((sec % 3600) / 60)).padStart(2, '0');
    return `${h}:${m}`;
});
```

El ticker local es solo visualización. `duration_seconds` se computa siempre en el servidor al hacer stop.

### 4.5 Advertencia en logout

Modificar `handleLogout()` en `Header.vue`:

```typescript
import Swal from 'sweetalert2';

const handleLogout = async (): Promise<void> => {
    if (timesheet.hasActiveTimer) {
        const result = await Swal.fire({
            title: t('timer.logout_warning_title'),
            text: t('timer.logout_warning_text'),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e7515a',
            cancelButtonColor: '#805dca',
            confirmButtonText: t('timer.stop_and_logout'),
            cancelButtonText: t('cancel'),
            reverseButtons: true,
        });
        if (!result.isConfirmed) return;
        try {
            await timesheet.stopTimer();
        } catch {
            // El scheduler limpiará el timer si el stop falla
        }
    }
    await authStore.logout();
    router.push('/auth/boxed-signin');
};
```

### 4.6 Modal de conflicto de concurrencia

**Archivo:** `resources/js/src/components/timesheet/ActiveTimerConflictModal.vue`

Se muestra cuando el usuario intenta iniciar un cronómetro y `useTimesheetStore().hasActiveTimer === true`.

```vue
<template>
  <div v-if="show" class="modal show d-block" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ $t('timer.conflict_title') }}</h5>
          <button type="button" class="btn-close" @click="$emit('close')" />
        </div>
        <div class="modal-body">
          <p>
            <router-link
              :to="`/cases/${active.immigration_case.id}`"
              class="text-primary fw-bold"
              @click="$emit('close')"
            >
              {{ active.immigration_case.case_code }}
            </router-link>
            &mdash; {{ active.immigration_case.client.name }}
          </p>
          <p class="text-muted">
            {{ $t('timer.started_by') }}: <strong>{{ active.user.name }}</strong>
          </p>
          <p class="text-muted">
            {{ $t('timer.elapsed') }}: <strong>{{ elapsedText }}</strong>
          </p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" @click="$emit('close')">
            {{ $t('cancel') }}
          </button>
          <button
            v-if="isMine"
            class="btn btn-warning"
            :disabled="loading"
            @click="stopAndStartNew"
          >
            {{ $t('timer.stop_previous_start_new') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import { computed, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useTimesheetStore } from '@/stores/useTimesheetStore';
import { formatDuration } from '@/utils/timeFormat';

const props = defineProps<{ show: boolean; newCaseId: number }>();
const emit = defineEmits<{ (e: 'close'): void; (e: 'started'): void }>();

const authStore = useAuthStore();
const timesheet = useTimesheetStore();
const loading = ref(false);

const active = computed(() => timesheet.activeTimer!);
const isMine = computed(() => active.value?.user_id === authStore.user?.id);
const elapsedText = computed(() => {
    if (!active.value?.started_at) return '—';
    const sec = Math.floor((Date.now() - new Date(active.value.started_at).getTime()) / 1000);
    return formatDuration(sec);
});

const stopAndStartNew = async () => {
    loading.value = true;
    try {
        await timesheet.stopTimer();
        await timesheet.startTimer(props.newCaseId, {});
        emit('started');
        emit('close');
    } finally {
        loading.value = false;
    }
};
</script>
```

**Regla de visibilidad:**
- El modal siempre muestra el timer activo del **propio usuario** (el store ya está filtrado por `byUser`).
- El botón "Detener anterior e iniciar este" solo aparece si `isMine === true`.
- Ver timers de otros usuarios requiere `time_logs.view_others` (extensión futura, no en este spec).

### 4.7 Polling de 60 s (mientras el indicador esté montado)

Añadir en el bloque del indicador del Header para actualizar el estado desde el servidor y detectar auto-stops:

```typescript
onMounted(() => {
    tick = setInterval(() => {
        now.value = Date.now();
        if (timesheet.hasActiveTimer) {
            timesheet.fetchActiveTimer(); // 60 s entre llamadas
        }
    }, 60_000);
});
```

Separar el ticker de 1 s (visual) del polling de 60 s (coherencia con servidor).

### 4.8 i18n — claves FLAT a agregar

Añadir a los 16 archivos en `resources/js/src/locales/*.json`:

```json
"timer.active_indicator_tooltip": "Active timer — click to view case",
"timer.logout_warning_title": "Active timer detected",
"timer.logout_warning_text": "You have a running timer. It will be stopped before signing out.",
"timer.stop_and_logout": "Stop timer and sign out",
"timer.conflict_title": "You already have a timer running",
"timer.started_by": "Started by",
"timer.elapsed": "Elapsed",
"timer.stop_previous_start_new": "Stop previous and start new",
"timer.cannot_stop_others": "Cannot stop another user's timer"
```

---

## 5. Riesgos y Performance

### 5.1 Concurrencia

- **Doble click Start en dos pestañas:** `lockForUpdate()` + check de timer activo en transacción ya implementado en `TimeLogService::startTimer`. No se necesita índice unique parcial (MySQL no soporta índices únicos con `NULL` nativamente).
- **Job duplicado:** `ShouldBeUnique` + `withoutOverlapping()` garantiza una sola instancia.

### 5.2 Drift de reloj cliente/servidor

El ticker JS incrementa `now` cada segundo — es solo visualización. Al hacer `stop`, el servidor computa `TIMESTAMPDIFF(SECOND, started_at, NOW())`. El frontend nunca envía un `ended_at` calculado por el cliente. Drift acumulado = 0.

### 5.3 `sendBeacon` bajo presión de memoria

El navegador puede descartar el beacon bajo presión extrema de memoria. El scheduler de 1 minuto es la red de seguridad real. El beacon solo reduce el tiempo de latencia del auto-stop en cierres graciosos.

### 5.4 Registro de riesgos

| Riesgo | Prob. | Impacto | Mitigación |
|---|---|---|---|
| Usuario deja pestaña abierta overnight | Alta | Alto | `AutoStopExpiredTimersJob` cada minuto, capped por `max_timer_duration` |
| Dos pestañas llaman `startTimer` simultáneamente | Media | Medio | `lockForUpdate()` ya en `TimeLogService::startTimer` línea 22-25 |
| Interceptor 401 envía stop que también falla con 401 | Alta | Bajo | Usar `sendBeacon` en `visibilitychange`, no el interceptor 401 |
| Tenant configura `max_timer_duration` en 5 min | Baja | Alto | Validación `min:15` en el endpoint de settings |
| `BroadcastChannel` no disponible (Safari < 15.4) | Baja | Bajo | Fallback gracioso: el polling de 60 s sincroniza igualmente |
| Job auto-stop borra tiempo legítimo | Baja | Alto | `description` se modifica con sufijo `[auto-stopped]` para auditoría; valor de `max_timer_duration` mínimo 15 min |

---

## 6. Diagrama de Arquitectura

```
BROWSER (Tab A)
│
├── CaseDetail.vue → useTimesheetStore.startTimer(caseId)
│       │
│       ├── POST /api/cases/{c}/time-logs/start  (cookie Sanctum)
│       │       ↓ DB::transaction { lockForUpdate } → TimeLog::create
│       │
│       ├── activeTimer = res.data
│       ├── BroadcastChannel('timesheet') ─────────────────► Tab B se sincroniza
│       └── Header.vue (reactivo) → badge pulsante visible
│               └── ticker 1s → elapsedDisplay (solo visual, deriva de started_at)
│               └── polling 60s → fetchActiveTimer() (coherencia con servidor)
│
├── Usuario cierra pestaña / cambia de app
│       └── visibilitychange:hidden → navigator.sendBeacon('/api/me/active-timer/beacon-stop')
│                                         (best-effort; cookie se adjunta automáticamente)
│
├── Usuario hace logout
│       └── handleLogout()
│               └── hasActiveTimer? → SweetAlert2 confirm
│                       └── confirmed → stopTimer() → authStore.logout() → /auth/boxed-signin
│
└── Sesión expira (usuario se fue sin acción)
        └── Scheduler cada minuto → AutoStopExpiredTimersJob (ShouldBeUnique)
                └── Por tenant: leer max_timer_duration de tenant.settings
                └── UPDATE time_logs SET ended_at=NOW(), duration_seconds=TIMESTAMPDIFF(...)
                        WHERE ended_at IS NULL AND started_at < NOW() - INTERVAL ? MINUTE
                └── Próximo login: fetchActiveTimer() retorna null → badge desaparece
```

---

## 7. Checklist de Implementación

### Fase 1 — Base de Datos
- [x] **M1**: Migración `2026_05_10_000001_add_timer_governance_permissions.php` — permisos `time_logs.stop_others` y `time_logs.configure`
- [x] **M2**: Verificar con `EXPLAIN` que `idx_active_timer` es usado por el `UPDATE` del job — índice existente cubre el patrón de la query

### Fase 2 — Backend Laravel
- [x] **B1**: Extender eager-load en `TimeLogService::getActiveTimerForUser` → `immigrationCase.client`
- [x] **B2**: Actualizar `TimeLogResource` para exponer `immigration_case.case_code`, `immigration_case.client.name`, `user.name`
- [x] **B3**: Crear `app/Jobs/AutoStopExpiredTimersJob.php` (`ShouldQueue + ShouldBeUnique`)
- [x] **B4**: Registrar en `routes/console.php`: `Schedule::job(new AutoStopExpiredTimersJob)->everyMinute()->withoutOverlapping()`
- [x] **B5**: Agregar `POST /api/me/active-timer/beacon-stop` + método `beaconStop` en `TimeLogController`
- [x] **B6**: Implementar `PATCH /api/admin/tenant/settings` con validación `max_timer_duration` (15–1440) — validación en `UpdateTenantSettingsRequest`, lógica en `TenantService::updateSettings`, UI en `TenantSettings.vue`
- [x] **B7**: Añadir `max_timer_duration` al payload de `TenantResource` (bajo `preferences.max_timer_duration`) — expuesto al frontend vía `useTenantStore`
- [x] **B8**: `AutoStopExpiredTimersJob` registrado vía `routes/console.php`; no requiere registro adicional en `AppServiceProvider`

### Fase 3 — Frontend Vue
- [x] **F1**: Hidratación de `fetchActiveTimer()` ya implementada en `stores/auth.ts` (login lines 130, 198, 335)
- [x] **F2**: Añadir `BroadcastChannel('timesheet')` a `useTimesheetStore` (actions: start, stop, clearAll, initBroadcast)
- [x] **F3**: Llamar `timesheetStore.initBroadcast()` desde `app-layout.vue` en `onMounted`
- [x] **F4**: Crear `resources/js/src/composables/useBeaconStop.ts` y registrar en `app-layout.vue`
- [x] **F5**: Insertar badge pulsante en `Header.vue` a la izquierda del bloque Theme
- [x] **F6**: Añadir ticker de 1 s y computed `elapsedDisplay` a `Header.vue`
- [x] **F7**: Añadir polling de 60 s a `Header.vue` (separado del ticker visual)
- [x] **F8**: Modificar `handleLogout()` en `Header.vue` con SweetAlert2 si `hasActiveTimer`
- [x] **F9**: Crear `resources/js/src/components/timesheet/ActiveTimerConflictModal.vue`
- [x] **F10**: Integrar `ActiveTimerConflictModal` en `TimeLogModal.vue` — botón cambia a warning y abre modal con opción "Detener anterior e iniciar nuevo"
- [x] **F11**: Agregar 10 claves i18n FLAT en los 16 archivos `resources/js/src/locales/*.json`

### Seguridad
- [x] **S1**: `time_logs.stop_others` y `time_logs.configure` vía migración (no seeder — G4)
- [x] **S2**: `beaconStop` solo detiene el timer del usuario autenticado (`getActiveTimerForUser` filtra por `byUser($r->user())`)
- [x] **S3**: El job auto-stop itera por tenant explícitamente — no puede fugarse entre tenants
- [x] **S4**: El modal de conflicto NO expone timers de otros usuarios (extensión futura con `time_logs.view_others`)
- [x] **S5**: `BelongsToTenant` confirmado en `TimeLog.php:14` — todas las queries quedan auto-scoped
