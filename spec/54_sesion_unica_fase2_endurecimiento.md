---
tipo: spec
numero: 54
modulo: Autenticación y Seguridad
dominio: AUTH
estado: DONE
tags: [spec, auth, done]
relacionados:
  - "[[53_sesion_unica_por_usuario|Spec 53]]"
---
# [[54_sesion_unica_fase2_endurecimiento|Spec 54]]: Sesión Única Fase 2 — Endurecimiento y Trazabilidad

**Versión:** 1.0
**Fecha:** 2026-05-10
**Branch:** `single-session-phase2`
**Stack:** Laravel 12 + Vue 3.5 + MySQL 8.0 + Pinia + Sanctum SPA
**Dependencias:** [[53_sesion_unica_por_usuario|Spec 53]] (Sesión Única por Usuario — Fase 1)

---

## Resumen Ejecutivo

Fase 2 mínima viable del sistema de sesión única implementado en Spec 53. Se abordan tres ejes:

1. **R5 — Refactorización a Event + Listeners:** `AuthService::establishSingleSession()` se convierte en un God Method al crecer. Se extrae la lógica de efectos secundarios a Listeners desacoplados, disparados por el evento `SessionRevoked`.
2. **7.7 — Trazabilidad:** Cada expulsión de sesión queda registrada en `Activity` (Spatie ActivityLog) con IP, user-agent, timestamp e IP del nuevo login.
3. **7.5 — Detección de abuso (Kicking DoS):** Un atacante con credenciales robadas no puede expulsar indefinidamente al usuario legítimo. Se registran revocaciones en `session_revocations` y se bloquea temporalmente al usuario tras N kicks en una ventana de tiempo.

### Decisiones Clave

| # | Decisión | Justificación |
|---|---|---|
| 1 | **Event + Listeners (no Domain Events con DDD)** | El proyecto usa el patrón de Eventos de Laravel nativos. Coherente con `EventSyncObserver` → `SyncEventToCalendarJob`. Añadir DDD sería over-engineering. |
| 2 | **Evento sincrónico para timer-stop, asincrónico para log y detección** | El stop del timer debe completarse antes de que el login responda (si falla, la sesión no debe quedar en estado inconsistente). Log y detección pueden diferirse a la cola. |
| 3 | **`session_revocations` como tabla separada (no reutilizar `activity_log`)** | La tabla de activity es genérica y denormalizada. Para consultar "¿cuántas revocaciones en los últimos 10 min por user?" se necesita una tabla específica con índice compuesto `(victim_user_id, created_at)`. |
| 4 | **Bloqueo en `users.security_locked_until` (no Redis)** | Consistente con la decisión de Spec 53 de no requerir Redis. El unlock se hace por expiración temporal (no hay UI de desbloqueo manual en Fase 2). |
| 5 | **Umbral: 5 kicks en 10 minutos → bloqueo de 30 minutos** | Equilibrio entre seguridad y falsos positivos. Un usuario que cambia de dispositivo varias veces en 10 min es señal clara de abuso. Ajustable vía constantes. |
| 6 | **`DetectKickingAbuse` listener en cola `high`** | La detección no bloquea el login del atacante en Fase 2 (solo bloquea la cuenta víctima). En Fase 3 se puede elevar a sincrónico para bloquear antes. |
| 7 | **`current_session_id` nunca en payload de evento** | El evento lleva solo `userId`, `newSessionId` (para contexto), `ip`, `userAgent`. El receptor nunca expone el session_id en logs o emails. |

---

## 1. Modelado de Datos

### 1.1 Migración: tabla `session_revocations`

```php
// database/migrations/2026_05_10_000004_create_session_revocations_table.php
Schema::create('session_revocations', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('victim_user_id');   // usuario cuya sesión fue revocada
    $table->string('revoking_ip', 45)->nullable();   // IP del nuevo login que causó el kick
    $table->text('revoking_user_agent')->nullable();  // User-Agent del nuevo login
    $table->string('stop_reason', 32)->default('new_login'); // new_login | password_change
    $table->timestamps();

    $table->index(['victim_user_id', 'created_at'], 'sr_victim_created_idx');
    $table->foreign('victim_user_id')->references('id')->on('users')->onDelete('cascade');
});
```

### 1.2 Migración: columna `security_locked_until` en `users`

```php
// database/migrations/2026_05_10_000005_add_security_lock_to_users.php
Schema::table('users', function (Blueprint $table) {
    $table->timestamp('security_locked_until')->nullable()->after('session_invalidated_at');
});
```

### 1.3 Modelo `SessionRevocation`

```php
// app/Models/SessionRevocation.php
class SessionRevocation extends Model
{
    protected $fillable = ['victim_user_id', 'revoking_ip', 'revoking_user_agent', 'stop_reason'];

    public function victim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'victim_user_id');
    }
}
```

**Nota:** `SessionRevocation` NO usa `BelongsToTenant` — es un modelo de infraestructura de seguridad, no de negocio. El scope por tenant no aplica aquí.

---

## 2. Evento y Listeners

### 2.1 Evento `SessionRevoked`

```php
// app/Events/SessionRevoked.php
class SessionRevoked
{
    public function __construct(
        public readonly User   $victim,
        public readonly string $revokingIp,
        public readonly string $revokingUserAgent,
        public readonly string $stopReason = 'new_login',
    ) {}
}
```

El evento NO implementa `ShouldBroadcast` en Fase 2 (WebSockets aplazados a Fase 3).

### 2.2 Listener `StopActiveTimerOnSessionRevoked` (sincrónico)

```php
// app/Listeners/StopActiveTimerOnSessionRevoked.php
class StopActiveTimerOnSessionRevoked
{
    public function handle(SessionRevoked $event): void
    {
        $activeTimer = TimeLog::where('user_id', $event->victim->id)
            ->whereNull('ended_at')
            ->first();

        if ($activeTimer) {
            try {
                app(TimeLogService::class)->stopTimer(
                    $activeTimer->id,
                    $event->victim,
                    reason: 'session_revoked'
                );
            } catch (\Throwable $e) {
                logger()->warning(
                    "SessionRevoked: could not stop timer {$activeTimer->id} " .
                    "for user {$event->victim->id}: {$e->getMessage()}"
                );
            }
        }
    }
}
```

### 2.3 Listener `LogSessionRevocation` (asincrónico, cola `default`)

```php
// app/Listeners/LogSessionRevocation.php
class LogSessionRevocation implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(SessionRevoked $event): void
    {
        // Registrar en tabla específica para detección de abuso
        SessionRevocation::create([
            'victim_user_id'       => $event->victim->id,
            'revoking_ip'          => $event->revokingIp,
            'revoking_user_agent'  => $event->revokingUserAgent,
            'stop_reason'          => $event->stopReason,
        ]);

        // Registrar en ActivityLog (Spatie) para auditoría general
        activity('security')
            ->causedBy($event->victim)
            ->performedOn($event->victim)
            ->withProperties([
                'revoking_ip'         => $event->revokingIp,
                'revoking_user_agent' => $event->revokingUserAgent,
                'stop_reason'         => $event->stopReason,
            ])
            ->log('session_revoked');
    }
}
```

### 2.4 Listener `DetectKickingAbuse` (asincrónico, cola `high`)

```php
// app/Listeners/DetectKickingAbuse.php
class DetectKickingAbuse implements ShouldQueue
{
    public string $queue = 'high';

    private const WINDOW_MINUTES  = 10;
    private const KICK_THRESHOLD  = 5;
    private const LOCK_MINUTES    = 30;

    public function handle(SessionRevoked $event): void
    {
        $windowStart = now()->subMinutes(self::WINDOW_MINUTES);

        $recentKicks = SessionRevocation::where('victim_user_id', $event->victim->id)
            ->where('created_at', '>=', $windowStart)
            ->count();

        if ($recentKicks >= self::KICK_THRESHOLD) {
            $lockedUntil = now()->addMinutes(self::LOCK_MINUTES);

            $event->victim->forceFill([
                'security_locked_until' => $lockedUntil,
            ])->saveQuietly();

            activity('security')
                ->causedBy($event->victim)
                ->performedOn($event->victim)
                ->withProperties([
                    'reason'         => 'kicking_abuse_detected',
                    'kicks_in_window'=> $recentKicks,
                    'locked_until'   => $lockedUntil->toIso8601String(),
                    'revoking_ip'    => $event->revokingIp,
                ])
                ->log('account_security_locked');

            logger()->warning(
                "KickingAbuse: user {$event->victim->id} locked until {$lockedUntil} " .
                "after {$recentKicks} kicks in {self::WINDOW_MINUTES} min from IP {$event->revokingIp}"
            );
        }
    }
}
```

### 2.5 Registro en `EventServiceProvider`

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    \App\Events\SessionRevoked::class => [
        \App\Listeners\StopActiveTimerOnSessionRevoked::class,  // sincrónico
        \App\Listeners\LogSessionRevocation::class,             // async, cola default
        \App\Listeners\DetectKickingAbuse::class,               // async, cola high
    ],
];
```

---

## 3. Refactorización de `AuthService`

### 3.1 `establishSingleSession()` refactorizado

```php
// app/Services/Auth/AuthService.php

public function establishSingleSession(User $user): void
{
    $newSessionId   = session()->getId();
    $revokingIp     = request()->ip();
    $revokingAgent  = request()->userAgent() ?? '';

    $hadPreviousSession = $user->current_session_id
        && $user->current_session_id !== $newSessionId;

    if ($hadPreviousSession) {
        // Disparar evento ANTES de borrar la sesión de BD
        // StopActiveTimerOnSessionRevoked es sincrónico — se resuelve aquí mismo
        event(new \App\Events\SessionRevoked(
            victim:            $user,
            revokingIp:        $revokingIp,
            revokingUserAgent: $revokingAgent,
            stopReason:        'new_login',
        ));
    }

    // Eliminar todas las sesiones previas del mismo usuario
    DB::table('sessions')
        ->where('user_id', $user->id)
        ->where('id', '!=', $newSessionId)
        ->delete();

    // Apuntar current_session_id a la sesión ganadora
    $user->forceFill([
        'current_session_id'     => $newSessionId,
        'session_invalidated_at' => now(),
    ])->saveQuietly();
}
```

**El método privado `stopActiveTimerForRevokedSession()` se elimina** — esa responsabilidad pasa al listener `StopActiveTimerOnSessionRevoked`.

### 3.2 `EnsureSingleSession` — verificación de bloqueo de seguridad

```php
// app/Http/Middleware/EnsureSingleSession.php

public function handle(Request $request, Closure $next): mixed
{
    $user = $request->user();
    if (!$user) return $next($request);

    // Verificar bloqueo temporal por kicking abuse
    if ($user->security_locked_until && $user->security_locked_until->isFuture()) {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message'       => 'This account is temporarily locked due to suspicious activity.',
            'reason'        => 'account_security_locked',
            'locked_until'  => $user->security_locked_until->toIso8601String(),
        ], 401);
    }

    // Verificar sesión única
    $currentSessionId = $request->session()->getId();
    if ($user->current_session_id !== null && $user->current_session_id !== $currentSessionId) {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message'    => 'Your session was ended because you signed in from another device or browser.',
            'reason'     => 'session_revoked',
            'revoked_at' => $user->session_invalidated_at?->toIso8601String(),
        ], 401);
    }

    return $next($request);
}
```

### 3.3 Cambio de contraseña — disparar evento

```php
// app/Http/Controllers/Api/ProfileController.php (changePassword)

// Al invalidar otras sesiones por cambio de contraseña, disparar evento para logging
if ($user->current_session_id && $user->current_session_id !== $currentSessionId) {
    event(new \App\Events\SessionRevoked(
        victim:            $user,
        revokingIp:        $request->ip(),
        revokingUserAgent: $request->userAgent() ?? '',
        stopReason:        'password_change',
    ));
}

DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $currentSessionId)->delete();
$user->forceFill(['current_session_id' => $currentSessionId])->saveQuietly();
```

---

## 4. Modelo User — campos adicionales

```php
// app/Models/User.php
// Agregar en $fillable:
'security_locked_until',

// Agregar en $casts:
'security_locked_until' => 'datetime',

// Agregar en $hidden (no exponer en API):
// security_locked_until ya no es sensible per se, pero sí es un dato interno
```

---

## 5. Frontend — Banner de cuenta bloqueada

```vue
<!-- resources/js/src/views/auth/boxed-signin.vue -->
<!-- Agregar junto al banner de session_revoked existente -->
<div
  v-if="lockedBanner"
  class="flex items-start gap-3 rounded p-3.5 mb-4 text-danger bg-danger-light dark:bg-danger-dark-light"
  role="alert"
>
  <span class="mt-0.5 shrink-0"><!-- icono lock --></span>
  <div>
    <p class="font-semibold">{{ $t('session.account_locked') }}</p>
    <p class="text-sm">{{ $t('session.account_locked_detail') }}</p>
    <p v-if="lockedUntil" class="mt-1 text-xs opacity-75">
      {{ $t('session.account_locked_until', { time: lockedUntil }) }}
    </p>
  </div>
</div>
```

```typescript
// Agregar en la lógica del componente
const lockedBanner = ref<boolean>(false);
const lockedUntil  = ref<string | null>(null);

// En el interceptor api.ts — agregar caso:
if (reason === 'account_security_locked') {
  lockedUntil.value = error.response?.data?.locked_until
    ? new Date(error.response.data.locked_until).toLocaleTimeString()
    : null;
  lockedBanner.value = true;
}
```

---

## 6. i18n — Nuevas claves

Agregar en los **16 archivos** de idioma (`en`, `es`, `fr`, `de`, `it`, `pt`, `ru`, `pl`, `tr`, `ja`, `zh`, `el`, `hu`, `da`, `sv`, `ae`):

```json
"session.account_locked": "Account temporarily locked",
"session.account_locked_detail": "Too many login attempts from different devices were detected. Try again later.",
"session.account_locked_until": "Account unlocked at {time}"
```

---

## 7. Tests

### 7.1 Tests de Feature (`tests/Feature/Auth/SingleSessionPhase2Test.php`)

| Test | Descripción |
|---|---|
| `kicking_abuse_locks_account_after_threshold` | 5 kicks en 10 min → cuenta bloqueada → login retorna 401 + `reason=account_security_locked` |
| `lock_expires_automatically` | `security_locked_until` en el pasado → login permitido normalmente |
| `session_revocation_is_logged_to_activity` | Kick → verificar que existe registro en `activity_log` con `description='session_revoked'` |
| `session_revocation_is_recorded_in_revocations_table` | Kick → verificar que existe registro en `session_revocations` con `victim_user_id` correcto |
| `password_change_logs_session_revocation` | Cambio de contraseña con otra sesión activa → verifica registro en `session_revocations` con `stop_reason='password_change'` |
| `stop_timer_listener_fires_on_session_revoked_event` | Evento `SessionRevoked` disparado → `StopActiveTimerOnSessionRevoked` para el timer |
| `timer_stop_failure_does_not_abort_login` | `TimeLogService::stopTimer()` lanza excepción → el login sigue adelante (no 500) |

---

## 8. Plan de Implementación

### Fase 2a — Refactorización Arquitectónica (R5)

| # | Tarea | Archivo | Estado |
|---|---|---|---|
| 1.1 | Crear directorio `app/Events/` | — | ✅ Completado |
| 1.2 | Crear `SessionRevoked` event | `app/Events/SessionRevoked.php` | ✅ Completado |
| 1.3 | Crear directorio `app/Listeners/` | — | ✅ Completado |
| 1.4 | Crear listener `StopActiveTimerOnSessionRevoked` (sincrónico) | `app/Listeners/StopActiveTimerOnSessionRevoked.php` | ✅ Completado |
| 1.5 | Registrar listeners en `EventServiceProvider` | `app/Providers/EventServiceProvider.php` | ✅ Completado |
| 1.6 | Refactorizar `establishSingleSession()` para usar `event(new SessionRevoked(...))` | `app/Services/Auth/AuthService.php` | ✅ Completado |
| 1.7 | Eliminar `stopActiveTimerForRevokedSession()` de `AuthService` | `app/Services/Auth/AuthService.php` | ✅ Completado |

### Fase 2b — Trazabilidad (7.7)

| # | Tarea | Archivo | Estado |
|---|---|---|---|
| 2.1 | Crear listener `LogSessionRevocation` (ShouldQueue, cola default) | `app/Listeners/LogSessionRevocation.php` | ✅ Completado |
| 2.2 | Migración tabla `session_revocations` | `database/migrations/2026_05_10_000004_create_session_revocations_table.php` | ✅ Completado |
| 2.3 | Crear modelo `SessionRevocation` | `app/Models/SessionRevocation.php` | ✅ Completado |
| 2.4 | Registrar listener `LogSessionRevocation` en `EventServiceProvider` | `app/Providers/EventServiceProvider.php` | ✅ Completado |
| 2.5 | Disparar `SessionRevoked` en `ProfileController::changePassword()` | `app/Http/Controllers/Api/ProfileController.php` | ✅ Completado |

### Fase 2c — Detección de Abuso (7.5)

| # | Tarea | Archivo | Estado |
|---|---|---|---|
| 3.1 | Migración columna `security_locked_until` en `users` | `database/migrations/2026_05_10_000005_add_security_lock_to_users.php` | ✅ Completado |
| 3.2 | Agregar `security_locked_until` a `$fillable` y `$casts` en `User` | `app/Models/User.php` | ✅ Completado |
| 3.3 | Crear listener `DetectKickingAbuse` (ShouldQueue, cola high) | `app/Listeners/DetectKickingAbuse.php` | ✅ Completado |
| 3.4 | Registrar listener `DetectKickingAbuse` en `EventServiceProvider` | `app/Providers/EventServiceProvider.php` | ✅ Completado |
| 3.5 | Actualizar `EnsureSingleSession` con verificación de `security_locked_until` | `app/Http/Middleware/EnsureSingleSession.php` | ✅ Completado |

### Fase 2d — Frontend e i18n

| # | Tarea | Archivo | Estado |
|---|---|---|---|
| 4.1 | Banner `account_security_locked` en login | `resources/js/src/views/auth/boxed-signin.vue` | ✅ Completado |
| 4.2 | Manejar `reason: 'account_security_locked'` en interceptor api.ts | `resources/js/src/services/api.ts` | ✅ Completado |
| 4.3 | Extender `useSessionStore` con estado `accountLocked`/`accountLockedUntil` | `resources/js/src/stores/useSessionStore.ts` | ✅ Completado |
| 4.4 | Agregar 3 claves `session.account_locked*` en los 16 idiomas | `resources/js/src/locales/*.json` | ✅ Completado |

### Tests

| # | Tarea | Archivo | Estado |
|---|---|---|---|
| 5.1 | Suite `SingleSessionPhase2Test.php` (7 casos feature) | `tests/Feature/Auth/SingleSessionPhase2Test.php` | ⏳ Pendiente |

---

## 9. Matriz de Riesgos

| Riesgo | Probabilidad | Impacto | Mitigación |
|---|---|---|---|
| `DetectKickingAbuse` ejecuta lento y bloquea la cola | Baja | Medio | Se ejecuta en cola `high` de forma asincrónica — no bloquea el request de login |
| Falso positivo: usuario legítimo viajando con múltiples dispositivos | Baja | Medio | Umbral de 5 en 10 min es conservador; se libera automáticamente en 30 min |
| `session_revocations` crece indefinidamente | Media (largo plazo) | Bajo | Agregar limpieza en `trash:purge` daily: `SessionRevocation::where('created_at', '<', now()->subDays(90))->delete()` |
| El evento `SessionRevoked` falla antes de que el listener sincrónico complete | Muy baja | Alto | PHP lanza la excepción hacia arriba; el login falla con 500. Mitigation: añadir try-catch en `establishSingleSession()` alrededor de `event(...)` con logger |
| Importar `SessionRevoked` en `AuthService` crea dependencia circular | Baja | Bajo | El evento importa solo `User` y tipos primitivos; `AuthService` importa el evento. Sentido único. |

---

## 10. Archivos a Modificar o Crear

| Archivo | Acción |
|---|---|
| `app/Events/SessionRevoked.php` | Crear nuevo |
| `app/Listeners/StopActiveTimerOnSessionRevoked.php` | Crear nuevo |
| `app/Listeners/LogSessionRevocation.php` | Crear nuevo |
| `app/Listeners/DetectKickingAbuse.php` | Crear nuevo |
| `app/Models/SessionRevocation.php` | Crear nuevo |
| `app/Providers/EventServiceProvider.php` | Modificar — registrar listeners |
| `app/Services/Auth/AuthService.php` | Modificar — refactorizar `establishSingleSession()`, eliminar `stopActiveTimerForRevokedSession()` |
| `app/Http/Middleware/EnsureSingleSession.php` | Modificar — añadir verificación de `security_locked_until` |
| `app/Http/Controllers/Api/ProfileController.php` | Modificar — disparar `SessionRevoked` al cambiar contraseña |
| `app/Models/User.php` | Modificar — `$fillable`, `$casts` para `security_locked_until` |
| `database/migrations/2026_05_10_000004_create_session_revocations_table.php` | Crear nuevo |
| `database/migrations/2026_05_10_000005_add_security_lock_to_users.php` | Crear nuevo |
| `resources/js/src/views/auth/boxed-signin.vue` | Modificar — banner de cuenta bloqueada |
| `resources/js/src/services/api.ts` | Modificar — caso `account_security_locked` |
| `resources/js/src/locales/*.json` (16 archivos) | Modificar — 3 claves nuevas `session.account_locked*` |
| `tests/Feature/Auth/SingleSessionPhase2Test.php` | Crear nuevo |
