---
tipo: spec
numero: 53
modulo: Autenticación y Seguridad
dominio: AUTH
estado: DONE
tags: [spec, auth, done]
relacionados:
  - "[[54_sesion_unica_fase2_endurecimiento|Spec 54]]"
---
# [[53_sesion_unica_por_usuario|Spec 53]]: Sesión Única por Usuario — Single Active Session Enforcement

**Versión:** 1.0
**Fecha:** 2026-05-10
**Branch:** `single-session-enforcement`
**Stack:** Laravel 12 + Vue 3.5 + MySQL 8.0 + Pinia + Sanctum SPA
**Dependencias:** [[34_security_hardening|Spec 34]] (Security Hardening), [[51_timesheet_control_tiempo|Spec 51]] (Timesheet Control de Tiempo), [[52_timesheet_gobernanza_cronometros|Spec 52]] (Gobernanza de Cronómetros)

---

## Resumen Ejecutivo

Implementación de una restricción de sesión única por usuario en Vite-IT Immigration: al iniciar sesión en un nuevo navegador o dispositivo, todas las sesiones activas anteriores del mismo usuario quedan invalidadas automáticamente, garantizando un único punto de acceso activo a la vez.

El enfoque elegido se basa en tres pilares:

1. `SESSION_DRIVER=database` — tabla `sessions` MySQL como fuente de verdad enumerable
2. Columna `users.current_session_id` — apunta declarativamente a la sesión "ganadora" (no inferida por orden temporal)
3. Middleware `EnsureSingleSession` — valida en cada request autenticado que la sesión activa es la vigente

### Decisiones Clave

| # | Decisión | Justificación |
|---|---|---|
| 1 | **`current_session_id` en `users` (no solo tabla `sessions`)** | Necesitamos una verdad declarativa. Múltiples sesiones del mismo `user_id` coexisten transitoriamente durante el login. Una columna apuntando a "la sesión válida" es determinista; inferirlo del `last_activity` no lo es. |
| 2 | **No usar `Auth::logoutOtherDevices($password)` nativo** | Requiere la contraseña en plaintext en la request — inviable en login automático con 2FA activo. Acopla el mecanismo al password. |
| 3 | **`SESSION_DRIVER=database` (no Redis)** | Listar y destruir sesiones por `user_id` es trivial en SQL con índice. El proyecto ya usa MySQL para queues; no se requiere infraestructura nueva. Adecuado hasta ~10k usuarios concurrentes. |
| 4 | **`establishSingleSession()` se llama DESPUÉS del 2FA** | Si se llamara al verificar solo las credenciales, un atacante con usuario/contraseña podría expulsar al usuario legítimo sin tener el código TOTP. |
| 5 | **Auto-stop del timer en el backend antes de revocar** | El frontend solo se entera del kick cuando hace el siguiente request, momento en el cual la sesión ya fue invalidada. El `stopTimer()` del frontend fallaría con otro 401. La responsabilidad primaria es del backend. |
| 6 | **401 + `reason: 'session_revoked'` (no 403 ni 440)** | El interceptor de Axios ya maneja 401. Extender con detección de `reason` en el body es el camino de menor fricción. 419 ya está usado para CSRF; 403 ya significa permisos insuficientes. |
| 7 | **No eximir al `super-admin`** | Es la cuenta más privilegiada — comprometer una sesión de super-admin es el escenario más crítico. Si necesita trabajar en paralelo, múltiples pestañas del mismo browser comparten sesión. |
| 8 | **Reverb (WebSockets) en Fase 2 opcional** | El backend garantiza el auto-stop del timer y la invalidación ocurre en el siguiente request. `AutoStopExpiredTimersJob` (ya existente) es la red de seguridad final. Reverb requeriría servidor WebSocket — desproporcionado para Fase 1. |
| 9 | **BroadcastChannel('session') para multi-tab** | Propaga el kick a todas las pestañas del mismo browser en paralelo. Mismo patrón que `BroadcastChannel('timesheet')` ya implementado en Spec 52. |
| 10 | **Scope por `user_id` global (no por `user_id + tenant_id`)** | Una sesión = un dispositivo = una identidad humana. El tenant activo se gestiona dentro de la sesión (switch de tenant), no con múltiples logins. |

---

## 1. Modelado de Datos

### 1.1 Session Driver — Comparativa

| Criterio | `database` | `redis` |
|---|---|---|
| Listar sesiones por `user_id` | SQL indexado — `WHERE user_id = ?` | Requiere Sets secundarios o SCAN — ineficiente |
| Invalidar sesión específica | `DELETE WHERE id = ?` — atómico, transaccional | `DEL session:{id}` — atómico sin transacción |
| Infraestructura extra | Ninguna (MySQL ya en uso para queues) | Requiere Redis HA |
| Escalabilidad | Adecuado hasta ~10k concurrentes con índices | Mejor para >50k concurrentes |
| Garbage collection | Gestionado por Laravel vía `lottery` | TTL nativo de Redis |
| Migración a futuro | Un cambio de driver + copy de datos | — |

**Decisión: `SESSION_DRIVER=database`**

### 1.2 Migración: tabla `sessions`

```bash
php artisan session:table
php artisan migrate
```

Tabla generada por Laravel:

```php
Schema::create('sessions', function (Blueprint $table) {
    $table->string('id')->primary();
    $table->foreignId('user_id')->nullable()->index();
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->longText('payload');
    $table->integer('last_activity')->index();
});
```

### 1.3 Migración: columnas en `users`

```php
// database/migrations/2026_05_10_000002_add_single_session_to_users.php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('current_session_id', 64)->nullable()->after('remember_token');
        $table->timestamp('session_invalidated_at')->nullable()->after('current_session_id');
        $table->index('current_session_id');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropIndex(['current_session_id']);
        $table->dropColumn(['current_session_id', 'session_invalidated_at']);
    });
}
```

**Por qué `session_invalidated_at`:** timestamp informativo para auditoría. Permite mostrar al usuario "tu sesión fue cerrada el dd/mm/yyyy a las hh:mm" y calcular cuándo ocurrió la expulsión.

### 1.4 Migración: campo `stop_reason` en `time_logs`

Si la columna no existe (verificar contra Spec 52):

```php
// database/migrations/2026_05_10_000003_add_stop_reason_to_time_logs.php
$table->string('stop_reason', 32)->nullable()->after('ended_at');
// valores: 'manual' | 'auto_expired' | 'session_revoked'
```

### 1.5 Actualizar `User::$fillable` y `$casts`

```php
// app/Models/User.php
protected $fillable = [
    // ... campos existentes ...
    'current_session_id',
    'session_invalidated_at',
];

protected $casts = [
    // ... casts existentes ...
    'session_invalidated_at' => 'datetime',
];
```

---

## 2. Backend Laravel

### 2.1 Método `establishSingleSession()` en `AuthService`

```php
// app/Services/Auth/AuthService.php

/**
 * Marca la sesión actual como única válida e invalida cualquier sesión previa.
 * Debe llamarse DESPUÉS de que Auth::login() haya ocurrido y DESPUÉS de pasar
 * el 2FA si el usuario lo tiene habilitado.
 */
public function establishSingleSession(User $user): void
{
    // 1) Regenerar sesión — mitigar session fixation
    //    (Sanctum ya llama regenerate en el flujo normal, pero lo forzamos
    //    por si establishSingleSession se invoca desde rutas no estándar)
    session()->regenerate();
    $newSessionId = session()->getId();

    // 2) Auto-detener timer activo ANTES de borrar las sesiones antiguas
    //    Preserva el time_log; si falla, AutoStopExpiredTimersJob actúa en ≤1 min
    if ($user->current_session_id && $user->current_session_id !== $newSessionId) {
        $this->stopActiveTimerForRevokedSession($user);
    }

    // 3) Borrar TODAS las sesiones DB del usuario excepto la nueva
    DB::table('sessions')
        ->where('user_id', $user->id)
        ->where('id', '!=', $newSessionId)
        ->delete();

    // 4) Apuntar current_session_id a la nueva sesión ganadora
    $user->forceFill([
        'current_session_id'     => $newSessionId,
        'session_invalidated_at' => now(),
    ])->saveQuietly(); // saveQuietly: no disparar observers innecesariamente
}

private function stopActiveTimerForRevokedSession(User $user): void
{
    $activeTimer = \App\Models\TimeLog::where('user_id', $user->id)
        ->whereNull('ended_at')
        ->first();

    if ($activeTimer) {
        app(\App\Services\Timesheet\TimeLogService::class)
            ->stopTimer($activeTimer->id, $user, reason: 'session_revoked');
    }
}
```

### 2.2 Flujo de login — con y sin 2FA

**Sin 2FA:**

```php
// app/Services/Auth/AuthService.php — método login()
public function login(array $credentials, bool $remember = false): User
{
    if (!Auth::attempt($credentials, $remember)) {
        throw new InvalidCredentialsException();
    }

    $user = Auth::user();

    if ($user->two_factor_enabled) {
        session(['2fa.user_id' => $user->id, '2fa.remember' => $remember]);
        Auth::logout();
        return $user; // El controller responde con two_factor_required: true
    }

    // Sin 2FA: establecer sesión única inmediatamente
    $this->establishSingleSession($user);

    return $user;
}
```

**Con 2FA (en el controller o service de verificación):**

```php
// app/Services/TwoFactorService.php o TwoFactorController.php
// Después de validar el código TOTP/SMS:

Auth::loginUsingId(session('2fa.user_id'), session('2fa.remember'));
$user = Auth::user();

// Solo AQUÍ se hace el kick — el atacante con credenciales sin TOTP nunca llega a este punto
app(AuthService::class)->establishSingleSession($user);

session()->forget(['2fa.user_id', '2fa.remember']);
```

### 2.3 Extender `TimeLogService::stopTimer()` con razón

```php
// app/Services/Timesheet/TimeLogService.php
public function stopTimer(int|string $timeLogId, User $user, string $reason = 'manual'): TimeLog
{
    $log = TimeLog::findOrFail($timeLogId);

    $log->update([
        'ended_at'         => now(),
        'duration_seconds' => now()->diffInSeconds($log->started_at),
        'stop_reason'      => $reason,
    ]);

    return $log->fresh();
}
```

### 2.4 Middleware `EnsureSingleSession`

```php
// app/Http/Middleware/EnsureSingleSession.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureSingleSession
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Super-admin: misma regla. Ver Decisión #7.
        // Si en el futuro se decide eximir:
        // if ($user->hasRole('super-admin')) return $next($request);

        $currentSessionId = $request->session()->getId();

        if ($user->current_session_id !== null
            && $user->current_session_id !== $currentSessionId) {

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'message'    => 'Your session has been ended because you signed in from another device.',
                'reason'     => 'session_revoked',
                'revoked_at' => $user->session_invalidated_at?->toIso8601String(),
            ], 401);
        }

        return $next($request);
    }
}
```

### 2.5 Registro en Kernel y rutas

**Alias en `app/Http/Kernel.php`:**

```php
protected $middlewareAliases = [
    // ... aliases existentes ...
    'ensure.single.session' => \App\Http\Middleware\EnsureSingleSession::class,
];
```

**Orden en `routes/api.php`:**

```php
Route::middleware([
    'auth:sanctum',          // 1° Resuelve usuario desde cookie + CSRF
    'tenant',                // 2° Resuelve tenant activo
    'ensure.single.session', // 3° Valida que la sesión es la única vigente
])->group(function () {
    // todas las rutas autenticadas
});
```

**Rutas EXCLUIDAS del middleware** (puntos de entrada del flujo de auth):
- `POST /api/auth/login`
- `POST /api/auth/two-factor-verify`
- `POST /api/auth/forgot-password`
- `POST /api/auth/reset-password`
- `GET /sanctum/csrf-cookie`

### 2.6 Cambio de contraseña — invalidar otras sesiones

```php
// En el controller/service que maneja PATCH /api/profile/password

// Opción adoptada: mantener la sesión actual activa, cerrar las demás (mejor UX)
$currentSessionId = session()->getId();

DB::table('sessions')
    ->where('user_id', $user->id)
    ->where('id', '!=', $currentSessionId)
    ->delete();

$user->update(['current_session_id' => $currentSessionId]);
```

---

## 3. Frontend Vue.js / TypeScript

### 3.1 Nuevo store `useSessionStore`

```typescript
// resources/js/src/stores/useSessionStore.ts
import { defineStore } from 'pinia';

const SESSION_KEY = 'session_kicked_out';

interface KickedPayload {
  revokedAt?: string;
}

export const useSessionStore = defineStore('session', {
  state: () => ({
    kickedOut: false,
    kickedOutAt: null as string | null,
  }),

  actions: {
    setKickedOut(payload: KickedPayload) {
      this.kickedOut = true;
      this.kickedOutAt = payload.revokedAt ?? new Date().toISOString();
      // Persistir en sessionStorage para sobrevivir un refresh de página
      sessionStorage.setItem(SESSION_KEY, JSON.stringify({
        kickedOut: true,
        kickedOutAt: this.kickedOutAt,
      }));
    },

    consume() {
      // Llamar después de mostrar el mensaje para evitar que persista entre logins
      this.kickedOut = false;
      this.kickedOutAt = null;
      sessionStorage.removeItem(SESSION_KEY);
    },

    hydrateFromStorage() {
      const raw = sessionStorage.getItem(SESSION_KEY);
      if (raw) {
        const data = JSON.parse(raw);
        this.kickedOut = data.kickedOut ?? false;
        this.kickedOutAt = data.kickedOutAt ?? null;
      }
    },
  },
});
```

**Hidratar en `main.ts`** (o en `App.vue` `onMounted`):

```typescript
// Llamar antes de montar la app
const sessionStore = useSessionStore();
sessionStore.hydrateFromStorage();
```

### 3.2 Extensión del interceptor en `api.ts`

```typescript
// resources/js/src/services/api.ts

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const status = error.response?.status;
    const reason = error.response?.data?.reason;

    // 419 — CSRF expirado (lógica existente, sin cambios)
    if (status === 419 && !error.config._retry) {
      error.config._retry = true;
      await api.get('/sanctum/csrf-cookie');
      return api.request(error.config);
    }

    if (status === 401) {
      const userStore       = useUserStore();
      const permissionStore = usePermissionStore();
      const authStore       = useAuthStore();
      const timesheetStore  = useTimesheetStore();
      const sessionStore    = useSessionStore();

      // Snapshot defensivo del timer antes de limpiar
      // (el backend ya hizo el stop; esto es defensa en profundidad)
      if (timesheetStore.activeTimer) {
        timesheetStore.persistOfflineSnapshot();
      }

      // Orden estricto de limpieza de stores
      userStore.clearUser();
      permissionStore.clear();
      authStore.reset();
      timesheetStore.clearLocal();

      // Setear mensaje contextual según la razón
      if (reason === 'session_revoked') {
        sessionStore.setKickedOut({
          revokedAt: error.response?.data?.revoked_at,
        });
        // Propagar a otras pestañas del mismo browser
        new BroadcastChannel('session').postMessage({
          type: 'session_revoked',
          revokedAt: error.response?.data?.revoked_at,
        });
      }

      if (!window.location.pathname.startsWith('/auth/')) {
        router.push({ name: 'login' });
      }
    }

    return Promise.reject(error);
  }
);
```

### 3.3 BroadcastChannel multi-tab en `useSessionStore`

Agregar en la inicialización del store (fuera de `state`/`actions`, a nivel de módulo):

```typescript
// resources/js/src/stores/useSessionStore.ts (añadir al final del módulo)

if (typeof window !== 'undefined') {
  const channel = new BroadcastChannel('session');
  channel.onmessage = (event) => {
    if (event.data?.type === 'session_revoked') {
      const sessionStore = useSessionStore();
      const userStore    = useUserStore();
      const router       = useRouter(); // desde vue-router

      sessionStore.setKickedOut({ revokedAt: event.data.revokedAt });
      userStore.clearUser();
      router.push({ name: 'login' });
    }
  };
}
```

### 3.4 Banner de expulsión en la vista de login

```vue
<!-- resources/js/src/views/auth/login.vue (sección a agregar) -->
<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useSessionStore } from '@/stores/useSessionStore';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const sessionStore = useSessionStore();
const kickedOutBanner = ref<string | null>(null);
const kickedOutAt = ref<string | null>(null);

onMounted(() => {
  if (sessionStore.kickedOut) {
    kickedOutBanner.value = t('session.kicked_out');
    kickedOutAt.value = sessionStore.kickedOutAt;
    sessionStore.consume(); // limpiar para que no reaparezca al refrescar
  }
});
</script>

<template>
  <!-- Agregar antes del formulario de login -->
  <div
    v-if="kickedOutBanner"
    class="alert alert-warning mb-5 flex items-start gap-3"
    role="alert"
  >
    <svg class="mt-0.5 h-5 w-5 shrink-0" .../>
    <div>
      <p class="font-semibold">{{ kickedOutBanner }}</p>
      <p class="text-sm text-warning-dark">{{ $t('session.kicked_out_detail') }}</p>
      <p v-if="kickedOutAt" class="mt-1 text-xs text-gray-500">
        {{ $t('session.kicked_out_at', { time: new Date(kickedOutAt).toLocaleString() }) }}
      </p>
    </div>
  </div>
  <!-- ... resto del formulario ... -->
</template>
```

### 3.5 Snapshot defensivo del timer en `useTimesheetStore`

```typescript
// resources/js/src/stores/useTimesheetStore.ts — método a agregar
persistOfflineSnapshot(): void {
  if (!this.activeTimer) return;
  const snapshot = {
    timeLogId:   this.activeTimer.id,
    caseId:      this.activeTimer.immigration_case_id,
    startedAt:   this.activeTimer.started_at,
    snapshotAt:  new Date().toISOString(),
    reason:      'session_revoked',
  };
  sessionStorage.setItem('timer_offline_snapshot', JSON.stringify(snapshot));
},

clearLocal(): void {
  this.activeTimer = null;
  // NO borrar timer_offline_snapshot aquí — puede ser útil al re-login
},
```

---

## 4. Internacionalización (i18n)

**CRÍTICO: claves FLAT — nunca objetos anidados.**

### `en.json`

```json
"session.kicked_out": "You have been signed out",
"session.kicked_out_detail": "Your session was ended because you signed in on another device or browser.",
"session.kicked_out_at": "Signed out at {time}",
"session.timer_preserved": "Your active timer was saved automatically before signing out."
```

### `es.json`

```json
"session.kicked_out": "Tu sesión ha sido cerrada",
"session.kicked_out_detail": "Tu sesión se cerró porque iniciaste sesión en otro dispositivo o navegador.",
"session.kicked_out_at": "Sesión cerrada a las {time}",
"session.timer_preserved": "Tu cronómetro activo se guardó automáticamente antes de cerrar la sesión."
```

### `fr.json`

```json
"session.kicked_out": "Vous avez été déconnecté",
"session.kicked_out_detail": "Votre session a été fermée car vous vous êtes connecté sur un autre appareil ou navigateur.",
"session.kicked_out_at": "Déconnecté à {time}",
"session.timer_preserved": "Votre chronomètre actif a été enregistré automatiquement avant la déconnexion."
```

### No implementar Idiomas restantes (no aplicar a `de`, `it`, `pt`, `ru`, `pl`, `tr`, `ja`, `zh`, `el`, `hu`, `da`, `sv`, `ae`)

Implementar unicamente `es.json`, `en.json` y `fr.json` 

---

## 5. Seguridad y Casos de Borde

### 5.1 Session Kicking Attack (DoS lateral)

**Vector:** atacante con credenciales robadas hace login repetidamente, expulsando al usuario legítimo de forma continua.

**Mitigaciones en profundidad:**

| Capa | Mecanismo |
|---|---|
| **2FA obligatorio** para `admin` y `super-admin` | Sin el código TOTP, el atacante no completa el login → no produce kick |
| **Rate limiting en `/api/auth/login`** | `RateLimiter::for('login')` — máx 5 intentos/min por IP y email combinados (ya existente; verificar configuración actual) |
| **Email de alerta al usuario expulsado** | Notificación automática "Nuevo inicio de sesión detectado desde IP X en dispositivo Y" usando `LoginAttempt` existente |
| **Detección de patrones** | Si el mismo `user_id` sufre >3 kicks en 10 minutos → bloquear logins temporalmente + notificar admin del tenant |

**Nota:** esta feature convierte un compromiso de credenciales silencioso en uno visible para el usuario legítimo — es una ventaja de seguridad, no solo un riesgo.

### 5.2 2FA — Timing exacto del kick

```
POST /api/auth/login
  ├─ Sin 2FA → Auth::login() + establishSingleSession() → kick inmediato
  └─ Con 2FA → session(['2fa.pending_user_id']) → response: { two_factor_required: true }
               ↓
               [NO se llama establishSingleSession todavía]
               ↓
POST /api/auth/two-factor-verify
  └─ Código TOTP válido → Auth::loginUsingId() + establishSingleSession() → kick aquí
```

**Por qué importa:** un atacante con usuario/contraseña (sin TOTP) NO puede expulsar al usuario legítimo. El kick solo ocurre tras autenticación completa.

### 5.3 Concurrencia — Dos logins casi simultáneos

Si dos requests de login llegan al mismo milisegundo para el mismo `user_id`, ambos pueden pasar el `Auth::attempt()` antes de que ninguno haya escrito `current_session_id`. Resultado: los dos se graban como "ganadores" con `forceFill` — el segundo overwrite gana por serialización de MySQL en nivel de fila.

El resultado es correcto: una sola sesión queda como válida (la última en escribir). El primer login queda revocado por el propio `EnsureSingleSession` en su siguiente request.

Para eliminar el race condition completamente (si se vuelve un problema): envolver `establishSingleSession` en `DB::transaction()` con `SELECT ... FOR UPDATE` en la fila del usuario.

### 5.4 Cambio de contraseña

- **Opción adoptada:** invalidar todas las sesiones excepto la actual (mantiene al usuario logueado en el dispositivo donde cambió la contraseña).
- Si se requiere mayor seguridad: invalidar también la sesión actual y forzar re-login con la nueva contraseña.

### 5.5 `remember_token` entre dispositivos

Sanctum SPA + cookie session no usa `remember_token` directamente para autenticar requests (usa la sesión PHP). Si un usuario tiene "recordarme" activo y otro dispositivo hace login:
1. El cookie "remember" del primer browser sigue siendo válido structuralmente.
2. Cuando ese cookie regenere la sesión PHP (próximo request), el nuevo `session_id` no coincide con `current_session_id` → el middleware lo detecta y lo expulsa.
3. El `remember_token` no necesita rotarse para el mecanismo de single-session.

---

## 6. Fase 2 — Notificación Proactiva con WebSockets (Opcional)

> Esta sección documenta la arquitectura para la Fase 2. No bloquea la Fase 1.

### Cuándo implementar

Implementar Reverb cuando se cumplan ≥2 de las siguientes condiciones:
- Más de 5 señales en tiempo real entran al roadmap del producto
- Los usuarios reportan confusión por ver la UI en estado "activo" en el browser expulsado
- Se implementa una vista "Mis sesiones activas" con capacidad de revocar remotamente

### Arquitectura

```php
// app/Events/SessionRevoked.php
class SessionRevoked implements ShouldBroadcast
{
    public function __construct(public readonly int $userId) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->userId}");
    }

    public function broadcastAs(): string
    {
        return 'SessionRevoked';
    }
}
```

```php
// Dentro de AuthService::establishSingleSession(), después del paso 4:
broadcast(new \App\Events\SessionRevoked($user->id))->toOthers();
```

```typescript
// resources/js/src/services/echo.ts
echo.private(`user.${userStore.id}`)
  .listen('SessionRevoked', (data: { revoked_at?: string }) => {
    timesheetStore.persistOfflineSnapshot();
    sessionStore.setKickedOut({ revokedAt: data.revoked_at });
    useUserStore().clearUser();
    router.push({ name: 'login' });
  });
```

---

## 7. Tests

### 7.1 Tests de Feature (`tests/Feature/Auth/SingleSessionTest.php`)

| Test | Descripción |
|---|---|
| `login_from_second_device_invalidates_first` | Login A, login B → request con sesión A → 401 + `reason=session_revoked` |
| `active_timer_is_persisted_when_session_is_revoked` | Usuario A inicia timer → login B → verificar `TimeLog.ended_at` no nulo y `stop_reason='session_revoked'` |
| `middleware_skips_unauthenticated_requests` | Request a `/api/auth/login` sin sesión → no ejecuta el middleware → 200/422 (no 401) |
| `password_change_keeps_current_session_alive` | Cambiar contraseña → verificar que la sesión actual sigue válida y las otras fueron borradas |
| `2fa_flow_only_kicks_after_second_factor` | Login con credenciales (con 2FA habilitado) → sesión anterior NO se revoca. Completar 2FA → sesión anterior SÍ se revoca |
| `super_admin_is_subject_to_single_session` | Verificar que el rol `super-admin` no está exento del middleware |
| `concurrent_logins_one_session_wins` | Dos logins casi simultáneos del mismo user → al final solo una sesión queda en `sessions` como la `current_session_id` |
| `kicked_session_response_has_correct_structure` | Verificar que el 401 por kick contiene `reason`, `message`, `revoked_at` |

### 7.2 Tests Unitarios (`tests/Unit/Auth/AuthServiceTest.php`)

| Test | Descripción |
|---|---|
| `it_invalidates_previous_sessions_on_login` | Verificar que `DB::table('sessions')` tiene solo la sesión nueva después del método |
| `it_stops_active_timer_before_revoking_session` | Mock de `TimeLogService::stopTimer()` verificando que se invoca con `reason='session_revoked'` |
| `it_does_not_call_stop_timer_when_no_active_timer` | No hay timer activo → `stopTimer()` no se invoca |
| `it_does_not_invalidate_when_first_login` | `current_session_id` es `null` → no borra sesiones existentes de otros usuarios |
| `it_saves_quietly_to_avoid_observer` | `saveQuietly()` no dispara el UserObserver |

---

## 8. Plan de Implementación

### Fase 1 — Core (bloqueante)

#### Configuración

| # | Tarea | Archivo | Estado |
|---|---|---|---|
| 1.1 | `SESSION_DRIVER=database` en `.env` (todos los entornos) | `.env` | ✅ Completado |
| 1.2 | Revisar `config/session.php`: `http_only=true`, `secure=true` en producción, `same_site='lax'` | `config/session.php` | ✅ Completado |
| 1.3 | Tabla `sessions` — ya existía en la base de datos | — | ✅ Completado |

#### Migraciones

| # | Tarea | Archivo | Estado |
|---|---|---|---|
| 2.1 | Migración `add_single_session_to_users` | `database/migrations/2026_05_10_000002_add_single_session_to_users.php` | ✅ Completado |
| 2.2 | Migración `add_stop_reason_to_time_logs` | `database/migrations/2026_05_10_000003_add_stop_reason_to_time_logs.php` | ✅ Completado |

#### Backend

| # | Tarea | Archivo | Estado |
|---|---|---|---|
| 3.1 | Agregar `establishSingleSession()` y `stopActiveTimerForRevokedSession()` | `app/Services/Auth/AuthService.php` | ✅ Completado |
| 3.2 | Invocar `establishSingleSession()` en login sin 2FA | `app/Services/Auth/AuthService.php` (método `login()`) | ✅ Completado |
| 3.3 | Invocar `establishSingleSession()` al completar 2FA | `app/Services/Auth/AuthService.php` (método `twoFactorChallenge()`) | ✅ Completado |
| 3.4 | Extender `TimeLogService::stopTimer()` con parámetro `reason` | `app/Services/Timesheet/TimeLogService.php` | ✅ Completado |
| 3.5 | Crear middleware `EnsureSingleSession` | `app/Http/Middleware/EnsureSingleSession.php` | ✅ Completado |
| 3.6 | Registrar alias en Kernel | `app/Http/Kernel.php` | ✅ Completado |
| 3.7 | Aplicar middleware a los 4 grupos autenticados | `routes/api.php` | ✅ Completado |
| 3.8 | Invalidar otras sesiones al cambiar contraseña | `app/Http/Controllers/Api/ProfileController.php` | ✅ Completado |
| 3.9 | Agregar `current_session_id` y `session_invalidated_at` a `$fillable`, `$casts` y `$hidden` | `app/Models/User.php` | ✅ Completado |
| 3.10 | Agregar `stop_reason` a `TimeLog::$fillable` | `app/Models/TimeLog.php` | ✅ Completado |
| 3.11 | Actualizar `AutoStopExpiredTimersJob` con `stop_reason: 'auto_expired'` | `app/Jobs/AutoStopExpiredTimersJob.php` | ✅ Completado |

#### Frontend

| # | Tarea | Archivo | Estado |
|---|---|---|---|
| 4.1 | Crear `useSessionStore` con persistencia en `sessionStorage` y BroadcastChannel | `resources/js/src/stores/useSessionStore.ts` | ✅ Completado |
| 4.2 | Extender interceptor 401 en `api.ts` para detectar `reason: 'session_revoked'` | `resources/js/src/services/api.ts` | ✅ Completado |
| 4.3 | Hidratar `useSessionStore` desde `sessionStorage` en bootstrap de app | `resources/js/src/main.ts` | ✅ Completado |
| 4.4 | Agregar banner de expulsión en la vista de login | `resources/js/src/views/auth/boxed-signin.vue` | ✅ Completado |
| 4.5 | Agregar `persistOfflineSnapshot()` y `clearLocal()` en `useTimesheetStore` | `resources/js/src/stores/useTimesheetStore.ts` | ✅ Completado |
| 4.6 | BroadcastChannel('session') para propagación multi-tab en `useSessionStore` | `resources/js/src/stores/useSessionStore.ts` | ✅ Completado |

#### i18n

| # | Tarea | Archivo | Estado |
|---|---|---|---|
| 5.1 | Claves `session.*` (4 claves) en los **16 idiomas** (en, es, fr, de, it, pt, ru, pl, tr, ja, zh, el, hu, da, sv, ae) | `resources/js/src/locales/*.json` | ✅ Completado |
| 5.2 | Formato FLAT verificado — build TypeScript sin errores | — | ✅ Completado |

#### Tests

| # | Tarea | Archivo | Estado |
|---|---|---|---|
| 6.1 | Suite `SingleSessionTest.php` (8 casos feature) | `tests/Feature/Auth/SingleSessionTest.php` | ⏳ Pendiente |
| 6.2 | Tests unitarios para `AuthService` (5 casos) | `tests/Unit/Auth/AuthServiceTest.php` | ⏳ Pendiente |

### Fase 2 — Mejoras Opcionales

| # | Tarea | Prioridad |
|---|---|---|
| 7.1 | Instalar y configurar Laravel Reverb | Baja |
| 7.2 | `App\Events\SessionRevoked` + broadcast en `establishSingleSession()` | Baja |
| 7.3 | Laravel Echo listener en frontend — canal privado `user.{id}` | Baja |
| 7.4 | Email de alerta al usuario expulsado (IP + device + timestamp) | Media |
| 7.5 | Detección de patrones de kicking (>3 en 10 min → bloqueo temporal) | Media |
| 7.6 | Vista "Mis sesiones recientes" en perfil del usuario | Baja |
| 7.7 | Logging en `Activity` model en cada evento `session_revoked` | Media |

---

## 9. Matriz de Riesgos

| Riesgo | Probabilidad | Impacto | Mitigación |
|---|---|---|---|
| Timer activo se pierde durante el kick | Baja | Alto | Triple defensa: backend stop antes de revocar → frontend snapshot → `AutoStopExpiredTimersJob` (Spec 52) |
| Race condition: dos logins simultáneos | Muy baja | Medio | Idempotencia de `forceFill` — el segundo overwrite gana; `EnsureSingleSession` corrige al primer request |
| Session fixation | Baja | Alto | `session()->regenerate()` explícito antes de fijar `current_session_id` |
| Kicking DoS por atacante con credenciales | Media | Medio | 2FA + rate limit + alertas por email + bloqueo tras N kicks/ventana |
| Usuarios confundidos por kick inesperado | Media | Bajo | Mensaje claro en login + timestamp de cuándo ocurrió + email de notificación (Fase 2) |
| Background tabs no detectan kick | Alta sin Reverb | Bajo | `BroadcastChannel('session')` propaga entre tabs del mismo browser |
| Cambio de `SESSION_DRIVER` invalida sesiones al hacer deploy | Alta en deploy | Bajo | Comunicar mantenimiento planificado; el re-login es aceptable una sola vez |
| Performance con muchos usuarios concurrentes | Baja | Medio | Índices en `sessions.user_id` y `users.current_session_id`; monitorear con APM post-deploy |

---

## 10. Archivos a Modificar o Crear

| Archivo | Acción |
|---|---|
| `app/Services/Auth/AuthService.php` | Modificar — agregar `establishSingleSession()` y `stopActiveTimerForRevokedSession()` |
| `app/Http/Controllers/Api/AuthController.php` | Modificar — invocar `establishSingleSession()` post-login sin 2FA |
| `app/Services/TwoFactorService.php` | Modificar — invocar `establishSingleSession()` post-verificación TOTP |
| `app/Services/Timesheet/TimeLogService.php` | Modificar — extender `stopTimer()` con parámetro `reason` |
| `app/Http/Middleware/EnsureSingleSession.php` | Crear nuevo |
| `app/Http/Kernel.php` | Modificar — registrar alias del middleware |
| `app/Http/Controllers/Api/ProfileController.php` | Modificar — invalidar otras sesiones al cambiar contraseña |
| `app/Models/User.php` | Modificar — `$fillable`, `$casts` |
| `routes/api.php` | Modificar — aplicar middleware al grupo autenticado |
| `database/migrations/2026_05_10_000002_add_single_session_to_users.php` | Crear nuevo |
| `database/migrations/2026_05_10_000003_add_stop_reason_to_time_logs.php` | Crear nuevo (si Spec 52 no lo incluyó) |
| `config/session.php` | Revisar — `http_only`, `secure`, `same_site` |
| `.env` | Modificar — `SESSION_DRIVER=database` |
| `resources/js/src/stores/useSessionStore.ts` | Crear nuevo |
| `resources/js/src/services/api.ts` | Modificar — extender interceptor 401 |
| `resources/js/src/main.ts` | Modificar — hidratar `useSessionStore` |
| `resources/js/src/views/auth/login.vue` | Modificar — banner de expulsión |
| `resources/js/src/stores/useTimesheetStore.ts` | Modificar — agregar `persistOfflineSnapshot()` y `clearLocal()` |
| `resources/js/src/locales/en.json` + otros 15 | Modificar — claves `session.*` FLAT |
| `tests/Feature/Auth/SingleSessionTest.php` | Crear nuevo |
| `tests/Unit/Auth/AuthServiceTest.php` | Crear nuevo o modificar si existe |
