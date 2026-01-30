# Analisis de Impacto: Laravel Socialite OAuth (Google/Microsoft)

## Metadata
- **Fecha de Analisis:** 2026-01-25
- **Analista:** Claude Code
- **Version del Proyecto:** POC Fase 4 Backend / Fase 5 Frontend completadas
- **Esquema de Auth Actual:** Laravel Sanctum SPA (Cookie-Based Sessions)

---

## 1. Resumen Ejecutivo

### 1.1 Veredicto de Compatibilidad

| Aspecto | Compatibilidad | Notas |
|---------|---------------|-------|
| Sanctum SPA Mode | **COMPATIBLE** | Socialite funciona con session-based auth |
| Base de Datos | **COMPATIBLE** | Requiere migracion adicional |
| Frontend SPA | **COMPATIBLE** | Requiere flujo de redirect modificado |
| Roles/Permisos (Spatie) | **COMPATIBLE** | Asignacion automatica de rol por defecto |
| Email Verification | **PARCIAL** | Emails de providers se consideran verificados |
| 2FA (Futuro) | **COMPATIBLE** | Se puede requerir 2FA post-OAuth |

### 1.2 Evaluacion General

**RECOMENDACION: VIABLE PARA IMPLEMENTACION**

Socialite es completamente compatible con el esquema actual de Sanctum SPA. La integracion requiere:
- Migracion de base de datos para campos OAuth
- Nuevo controlador para OAuth callbacks
- Modificaciones al frontend para manejo de redirects
- Logica de vinculacion de cuentas existentes

---

## 2. Arquitectura Actual vs. Propuesta

### 2.1 Flujo de Autenticacion Actual

```
┌─────────────────────────────────────────────────────────────────┐
│                      FLUJO ACTUAL (Sanctum SPA)                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Frontend (Vue)              Backend (Laravel)                  │
│  ─────────────              ─────────────────                   │
│                                                                 │
│  1. GET /sanctum/csrf-cookie ───────────────> Set CSRF Cookie   │
│                                                                 │
│  2. POST /api/login ────────────────────────> Auth::attempt()   │
│     {email, password}                         Session::regen()  │
│                              <───────────────  Cookie + User    │
│                                                                 │
│  3. GET /api/user ──────────────────────────> Auth::user()      │
│     (con cookie)             <───────────────  User data        │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 2.2 Flujo Propuesto con Socialite

```
┌─────────────────────────────────────────────────────────────────┐
│                    FLUJO OAuth (Socialite)                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Frontend (Vue)        Backend          Provider (Google/MS)    │
│  ─────────────        ─────────         ─────────────────────   │
│                                                                 │
│  1. Click "Login with Google"                                   │
│     ↓                                                           │
│  2. window.location = /api/auth/google/redirect                 │
│                    ───────────────>                             │
│  3.                              Redirect to Google ──────────> │
│                                                                 │
│  4.                              <────── User authorizes ────── │
│                                                                 │
│  5.                              GET /api/auth/google/callback  │
│                                  ←────────────── code, state    │
│                                                                 │
│  6.                              Exchange code for tokens       │
│                                  Get user info                  │
│                                  Create/Update user             │
│                                  Auth::login($user)             │
│                                  Session::regenerate()          │
│                                                                 │
│  7.                    <─────── Redirect to frontend app        │
│                                 (with session cookie)           │
│                                                                 │
│  8. GET /api/user ─────────────>                                │
│     (con cookie)     <───────── User data                       │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 2.3 Comparacion de Flujos

| Aspecto | Auth Tradicional | OAuth Socialite |
|---------|-----------------|-----------------|
| **Credenciales** | Email + Password | Token de provider |
| **Session** | Cookie-based (igual) | Cookie-based (igual) |
| **CSRF** | Requerido | Requerido (state parameter) |
| **Persistencia** | Session table | Session table (igual) |
| **Logout** | Auth::logout() | Auth::logout() (igual) |

---

## 3. Cambios Requeridos

### 3.1 Backend

#### 3.1.1 Instalacion de Paquetes

```bash
composer require laravel/socialite
```

**Impacto:** Agrega ~20 archivos al vendor, sin conflictos con Sanctum.

#### 3.1.2 Migracion de Base de Datos

```php
// database/migrations/xxxx_add_oauth_fields_to_users_table.php

Schema::table('users', function (Blueprint $table) {
    $table->string('google_id')->nullable()->unique()->after('email');
    $table->string('microsoft_id')->nullable()->unique()->after('google_id');
    $table->string('avatar_url')->nullable()->after('microsoft_id');
    $table->boolean('has_password')->default(true)->after('avatar_url');
});
```

**Campos:**
- `google_id`: ID unico del usuario en Google
- `microsoft_id`: ID unico del usuario en Microsoft
- `has_password`: Indica si el usuario puede hacer login con password
- `avatar_url`: URL del avatar del provider

#### 3.1.3 Nuevo Controlador

```php
// app/Http/Controllers/Api/SocialAuthController.php

class SocialAuthController extends Controller
{
    // GET /api/auth/{provider}/redirect
    public function redirect(string $provider)
    {
        // Validar provider
        // Retornar redirect URL de Socialite
    }

    // GET /api/auth/{provider}/callback
    public function callback(string $provider)
    {
        // Obtener usuario de Socialite
        // Buscar/crear usuario en BD
        // Vincular o crear cuenta
        // Login con session
        // Redirect al frontend
    }
}
```

#### 3.1.4 Nuevas Rutas

```php
// routes/api.php

// OAuth routes (no auth required)
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->where('provider', 'google|microsoft');

Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->where('provider', 'google|microsoft');
```

#### 3.1.5 Configuracion de Providers

```php
// config/services.php

'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT_URL', '/api/auth/google/callback'),
],

'microsoft' => [
    'client_id' => env('MICROSOFT_CLIENT_ID'),
    'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
    'redirect' => env('MICROSOFT_REDIRECT_URL', '/api/auth/microsoft/callback'),
    'tenant' => env('MICROSOFT_TENANT_ID', 'common'),
],
```

#### 3.1.6 Variables de Entorno

```env
# .env

# Google OAuth
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URL=http://vristo-poc.test/api/auth/google/callback

# Microsoft OAuth
MICROSOFT_CLIENT_ID=your-microsoft-client-id
MICROSOFT_CLIENT_SECRET=your-microsoft-client-secret
MICROSOFT_REDIRECT_URL=http://vristo-poc.test/api/auth/microsoft/callback
MICROSOFT_TENANT_ID=common
```

### 3.2 Frontend

#### 3.2.1 Servicio de OAuth

```typescript
// resources/js/src/services/oauthService.ts

export const oauthService = {
    async redirectToProvider(provider: 'google' | 'microsoft'): Promise<void> {
        // Redirect a la URL del backend
        window.location.href = `/api/auth/${provider}/redirect`;
    }
};
```

#### 3.2.2 Modificacion de Vistas de Login

```vue
<!-- views/auth/boxed-signin.vue -->
<template>
    <!-- Existing form -->

    <div class="divider">O continuar con</div>

    <div class="flex gap-4">
        <button @click="loginWithGoogle" class="btn btn-outline-dark flex-1">
            <IconGoogle class="w-5 h-5 mr-2" />
            Google
        </button>
        <button @click="loginWithMicrosoft" class="btn btn-outline-dark flex-1">
            <IconMicrosoft class="w-5 h-5 mr-2" />
            Microsoft
        </button>
    </div>
</template>
```

#### 3.2.3 Pagina de Callback

```vue
<!-- views/auth/oauth-callback.vue -->
<template>
    <div class="flex items-center justify-center min-h-screen">
        <div class="text-center">
            <div v-if="isLoading" class="animate-spin ...">
                <!-- Loading spinner -->
            </div>
            <div v-else-if="error" class="text-danger">
                {{ error }}
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
// Al montar, verificar estado de autenticacion
// y redirigir al dashboard o mostrar error
</script>
```

#### 3.2.4 Nuevas Rutas de Frontend

```typescript
// router/index.ts

{
    path: '/auth/oauth-callback',
    name: 'oauth-callback',
    component: () => import('@/views/auth/oauth-callback.vue'),
    meta: { layout: 'auth' }
},
```

### 3.3 Modelo User

```php
// app/Models/User.php

protected $fillable = [
    'name',
    'email',
    'password',
    'google_id',      // Nuevo
    'microsoft_id',   // Nuevo
    'avatar_url',     // Nuevo
    'has_password',   // Nuevo
];

protected $casts = [
    'email_verified_at' => 'datetime',
    'has_password' => 'boolean',  // Nuevo
];

// Nuevos metodos
public function hasOAuthProvider(): bool
{
    return $this->google_id || $this->microsoft_id;
}

public function canLoginWithPassword(): bool
{
    return $this->has_password && $this->password;
}
```

---

## 4. Escenarios de Vinculacion de Cuentas

### 4.1 Matriz de Escenarios

| Escenario | Email Existente | OAuth ID | Accion |
|-----------|-----------------|----------|--------|
| Usuario nuevo | No | No | Crear cuenta + OAuth ID |
| Usuario existente sin OAuth | Si | No | Vincular OAuth ID |
| Usuario existente con OAuth | Si | Si | Login directo |
| Mismo email, diferente provider | Si | Parcial | Vincular segundo provider |
| Email ya usado, otro user | Si (otro) | No | **ERROR** - Conflicto |

### 4.2 Logica de Vinculacion Propuesta

```php
// app/Services/SocialAuthService.php

public function findOrCreateUser(
    string $provider,
    SocialiteUser $socialUser
): User {
    $providerIdField = "{$provider}_id";

    // 1. Buscar por OAuth ID
    $user = User::where($providerIdField, $socialUser->getId())->first();
    if ($user) {
        return $user; // Login existente
    }

    // 2. Buscar por email
    $user = User::where('email', $socialUser->getEmail())->first();
    if ($user) {
        // Vincular provider a cuenta existente
        $user->update([$providerIdField => $socialUser->getId()]);
        return $user;
    }

    // 3. Crear usuario nuevo
    return User::create([
        'name' => $socialUser->getName(),
        'email' => $socialUser->getEmail(),
        $providerIdField => $socialUser->getId(),
        'avatar_url' => $socialUser->getAvatar(),
        'password' => null,
        'has_password' => false,
        'email_verified_at' => now(), // OAuth emails son verificados
    ]);
}
```

### 4.3 Consideracion: Verificacion de Email

**Politica Propuesta:**
- Emails de Google/Microsoft se consideran **verificados automaticamente**
- El provider ya verifico la propiedad del email
- Reduce friccion en el registro

```php
// En findOrCreateUser()
'email_verified_at' => now(), // Auto-verificado via OAuth
```

---

## 5. Analisis de Riesgos

### 5.1 Matriz de Riesgos

| Riesgo | Probabilidad | Impacto | Mitigacion |
|--------|--------------|---------|------------|
| **Usuarios sin password** | Alta | Medio | Campo `has_password`, flujo de "crear password" |
| **Conflicto de emails** | Media | Alto | Validacion previa, merge manual de cuentas |
| **Token de provider expirado** | Baja | Bajo | Re-autenticacion automatica |
| **Cambio de email en provider** | Baja | Alto | Usar ID de provider como identificador principal |
| **Desvinculacion de cuenta** | Media | Medio | Requiere password antes de desvincular |
| **Provider no disponible** | Baja | Medio | Login tradicional como fallback |
| **CORS issues en redirect** | Media | Alto | Configuracion correcta de dominios |
| **Session fixation** | Baja | Alto | Regenerar session en callback |
| **2FA bypass** | Media | Alto | Requerir 2FA post-OAuth si esta habilitado |

### 5.2 Riesgos Detallados

#### 5.2.1 Usuarios Sin Password

**Problema:** Un usuario que se registra via OAuth no tiene password.

**Impacto:**
- No puede hacer login tradicional si OAuth falla
- No puede cambiar password (no tiene)
- Riesgo de perder acceso si desvincula OAuth

**Mitigacion:**
```php
// Agregar campo has_password a users
$table->boolean('has_password')->default(true);

// En login tradicional
if (!$user->has_password) {
    throw ValidationException::withMessages([
        'email' => ['Esta cuenta usa login social. Por favor inicia sesion con Google o Microsoft.'],
    ]);
}

// Flujo "Crear password" para cuentas OAuth
Route::post('/profile/create-password', [ProfileController::class, 'createPassword']);
```

#### 5.2.2 Conflicto de Emails

**Problema:** Usuario A tiene cuenta con email@test.com. Usuario B intenta registrarse con OAuth usando el mismo email.

**Impacto:**
- Podria permitir acceso no autorizado si se auto-vincula
- Conflicto de identidades

**Mitigacion:**
```php
// Opcion A: Requerir confirmacion para vincular
if ($existingUser && !$existingUser->hasOAuthProvider()) {
    // No vincular automaticamente
    // Redirigir a pagina de confirmacion
    return redirect('/auth/confirm-link-account')
        ->with('pending_oauth', [
            'provider' => $provider,
            'email' => $socialUser->getEmail()
        ]);
}

// Opcion B: Permitir solo si el usuario esta logueado
// (vinculacion explicita desde perfil)
```

#### 5.2.3 2FA y OAuth

**Problema:** Si el usuario tiene 2FA habilitado, el OAuth no deberia bypassearlo.

**Impacto:**
- Reduccion de seguridad si 2FA se ignora
- Inconsistencia en politicas de seguridad

**Mitigacion:**
```php
// En el callback de OAuth
$user = $this->socialAuthService->findOrCreateUser($provider, $socialUser);

// Si tiene 2FA habilitado
if ($user->two_factor_confirmed_at) {
    // No completar login, redirigir a challenge
    session(['oauth_user_id' => $user->id]);
    return redirect('/auth/two-factor-challenge');
}

// Sin 2FA, login normal
Auth::login($user);
```

### 5.3 Riesgos de Seguridad Especificos

#### 5.3.1 CSRF en OAuth Flow

**Problema:** El flujo OAuth es vulnerable a CSRF si no se valida el state parameter.

**Mitigacion:** Socialite maneja esto automaticamente con el parametro `state`.

```php
// Socialite ya incluye state parameter
return Socialite::driver($provider)
    ->stateless()  // Solo si es API pura
    ->redirect();
```

**Nota:** Para SPA con sessions, NO usar `stateless()` - mantener state para seguridad.

#### 5.3.2 Session Fixation

**Problema:** Un atacante podria fijar la session antes del OAuth.

**Mitigacion:**
```php
// En el callback, siempre regenerar session
public function callback(string $provider)
{
    $socialUser = Socialite::driver($provider)->user();
    $user = $this->socialAuthService->findOrCreateUser($provider, $socialUser);

    Auth::login($user);
    request()->session()->regenerate();  // IMPORTANTE

    return redirect('/');
}
```

---

## 6. Integracion con Funcionalidades Existentes

### 6.1 Roles y Permisos (Spatie)

**Compatibilidad:** Total

**Consideracion:** Asignar rol por defecto a usuarios nuevos de OAuth.

```php
// En findOrCreateUser(), despues de crear usuario
$user = User::create([...]);
$user->assignRole('user'); // Rol por defecto

return $user;
```

### 6.2 Email Verification

**Compatibilidad:** Parcial (modificacion requerida)

**Consideracion:** Usuarios de OAuth ya tienen email verificado por el provider.

```php
// En findOrCreateUser()
'email_verified_at' => now(), // Auto-verificado

// En AuthController::register() - mantener flujo actual para registro tradicional
// Solo enviar email de verificacion si NO es OAuth
```

### 6.3 Profile Management

**Compatibilidad:** Total

**Consideracion:** Sincronizar avatar de OAuth con perfil.

```php
// Actualizar avatar si cambia en provider
if ($user->avatar_url !== $socialUser->getAvatar()) {
    $user->update(['avatar_url' => $socialUser->getAvatar()]);
}

// En UserProfile, permitir override de avatar
public function getAvatarUrlAttribute()
{
    return $this->custom_avatar ?? $this->user->avatar_url;
}
```

### 6.4 Two-Factor Authentication (Futuro - Fase 5)

**Compatibilidad:** Total (con consideraciones)

**Flujo propuesto:**
1. Usuario hace OAuth login
2. Si tiene 2FA habilitado -> redirigir a challenge
3. Usuario ingresa codigo 2FA
4. Completar login

```php
// En SocialAuthController::callback()
if ($user->hasTwoFactorEnabled()) {
    session(['pending_two_factor_user_id' => $user->id]);
    return redirect('/auth/two-factor-challenge')
        ->with('from', 'oauth');
}
```

---

## 7. Plan de Implementacion

### 7.1 Archivos Nuevos

| Archivo | Proposito |
|---------|-----------|
| `app/Http/Controllers/Api/SocialAuthController.php` | Controlador OAuth |
| `app/Services/SocialAuthService.php` | Logica de negocio OAuth |
| `database/migrations/xxxx_add_oauth_fields_to_users.php` | Campos OAuth |
| `resources/js/src/services/oauthService.ts` | Servicio frontend |
| `resources/js/src/views/auth/oauth-callback.vue` | Pagina de callback |
| `resources/js/src/components/icon/icon-google.vue` | Icono Google |
| `resources/js/src/components/icon/icon-microsoft.vue` | Icono Microsoft |
| `tests/Feature/Api/SocialAuthTest.php` | Tests OAuth |

### 7.2 Archivos Modificados

| Archivo | Cambio |
|---------|--------|
| `app/Models/User.php` | Nuevos campos y metodos |
| `routes/api.php` | Rutas OAuth |
| `config/services.php` | Configuracion providers |
| `.env` / `.env.example` | Variables OAuth |
| `resources/js/src/router/index.ts` | Ruta callback |
| `resources/js/src/views/auth/boxed-signin.vue` | Botones OAuth |
| `resources/js/src/views/auth/cover-login.vue` | Botones OAuth |
| `resources/js/src/stores/auth.ts` | Estado OAuth |

### 7.3 Estimacion de Tiempo

| Tarea | Tiempo |
|-------|--------|
| Instalacion y configuracion Socialite | 1h |
| Migracion de base de datos | 1h |
| SocialAuthController + Service | 4h |
| Frontend (botones, callback, icons) | 3h |
| Integracion con auth store | 2h |
| Tests | 3h |
| Configuracion de providers (Google Console, Azure) | 2h |
| Documentacion | 1h |
| **Total** | **17h** |

### 7.4 Dependencias Externas

| Recurso | Requerido Para |
|---------|---------------|
| Google Cloud Console | Client ID/Secret para Google |
| Azure Portal | Client ID/Secret para Microsoft |
| Dominio verificado | Redirects en produccion |

---

## 8. Configuracion de Providers

### 8.1 Google OAuth 2.0

**Pasos:**
1. Ir a [Google Cloud Console](https://console.cloud.google.com/)
2. Crear proyecto o seleccionar existente
3. APIs & Services > Credentials
4. Create Credentials > OAuth 2.0 Client ID
5. Application type: Web application
6. Authorized redirect URIs:
   - `http://localhost:5173/api/auth/google/callback` (dev)
   - `http://vristo-poc.test/api/auth/google/callback` (local)
   - `https://vristo.com/api/auth/google/callback` (prod)

**Scopes requeridos:**
- `openid`
- `profile`
- `email`

### 8.2 Microsoft OAuth 2.0

**Pasos:**
1. Ir a [Azure Portal](https://portal.azure.com/)
2. Azure Active Directory > App registrations
3. New registration
4. Redirect URI:
   - `http://localhost:5173/api/auth/microsoft/callback`
   - `https://vristo.com/api/auth/microsoft/callback`
5. Certificates & secrets > New client secret
6. API permissions > Microsoft Graph > User.Read

**Tenant options:**
- `common` - Cualquier cuenta Microsoft
- `organizations` - Solo cuentas de trabajo/escuela
- `consumers` - Solo cuentas personales
- `{tenant-id}` - Tenant especifico

---

## 9. Testing

### 9.1 Tests Unitarios

```php
// tests/Feature/Api/SocialAuthTest.php

class SocialAuthTest extends TestCase
{
    public function test_redirect_to_google_returns_redirect_url()
    {
        $response = $this->get('/api/auth/google/redirect');
        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location'));
    }

    public function test_google_callback_creates_new_user()
    {
        // Mock Socialite
        $this->mockSocialiteFacade('google', [
            'id' => '123456789',
            'name' => 'Test User',
            'email' => 'test@gmail.com',
            'avatar' => 'https://...',
        ]);

        $response = $this->get('/api/auth/google/callback');

        $this->assertDatabaseHas('users', [
            'email' => 'test@gmail.com',
            'google_id' => '123456789',
        ]);
    }

    public function test_google_callback_links_existing_user()
    {
        $user = User::factory()->create(['email' => 'test@gmail.com']);

        $this->mockSocialiteFacade('google', [
            'id' => '123456789',
            'name' => 'Test User',
            'email' => 'test@gmail.com',
        ]);

        $response = $this->get('/api/auth/google/callback');

        $user->refresh();
        $this->assertEquals('123456789', $user->google_id);
    }

    public function test_oauth_user_has_email_verified()
    {
        $this->mockSocialiteFacade('google', [
            'id' => '123456789',
            'name' => 'Test User',
            'email' => 'test@gmail.com',
        ]);

        $this->get('/api/auth/google/callback');

        $user = User::where('email', 'test@gmail.com')->first();
        $this->assertNotNull($user->email_verified_at);
    }
}
```

### 9.2 Tests de Integracion

```php
public function test_full_oauth_flow()
{
    // 1. Get redirect URL
    $response = $this->get('/api/auth/google/redirect');
    $response->assertRedirect();

    // 2. Simulate callback
    $this->mockSocialiteFacade('google', [...]);
    $response = $this->get('/api/auth/google/callback');

    // 3. Verify session is authenticated
    $this->assertAuthenticated();

    // 4. Can access protected routes
    $response = $this->get('/api/user');
    $response->assertOk();
}
```

---

## 10. Consideraciones de UX

### 10.1 Mensajes de Error

| Escenario | Mensaje |
|-----------|---------|
| Provider no disponible | "El servicio de {provider} no esta disponible. Intenta mas tarde o usa email/password." |
| Email ya registrado (conflicto) | "Ya existe una cuenta con este email. Inicia sesion con tu password para vincular tu cuenta de {provider}." |
| Error de autorizacion | "No pudimos verificar tu identidad con {provider}. Por favor intenta de nuevo." |
| Cuenta deshabilitada | "Tu cuenta esta deshabilitada. Contacta al administrador." |

### 10.2 Flujos de Usuario

**Nuevo usuario via OAuth:**
1. Click "Login con Google"
2. Autoriza en Google
3. Redirect al dashboard
4. (Opcional) Prompt para crear password de respaldo

**Usuario existente vinculando OAuth:**
1. Login con email/password
2. Ir a Configuracion > Seguridad
3. Click "Vincular cuenta de Google"
4. Autoriza en Google
5. Cuenta vinculada

**Usuario OAuth queriendo password:**
1. Login con OAuth
2. Ir a Configuracion > Seguridad
3. Click "Crear password"
4. Ingresar nuevo password
5. `has_password` = true

---

## 11. Conclusiones

### 11.1 Beneficios de Implementar Socialite

1. **Reduccion de friccion:** Registro/login en 2 clicks
2. **Menos passwords:** Usuarios no necesitan recordar otra password
3. **Emails verificados:** Google/Microsoft ya verificaron el email
4. **Datos pre-llenados:** Nombre y avatar automaticos
5. **Seguridad delegada:** Providers manejan su propia seguridad

### 11.2 Consideraciones Importantes

1. **Usuarios sin password:** Implementar flujo de "crear password de respaldo"
2. **Vinculacion de cuentas:** Decidir politica de auto-vinculacion
3. **2FA:** Requerir 2FA post-OAuth si esta habilitado
4. **Dependencia externa:** Mantener login tradicional como fallback

### 11.3 Recomendacion Final

**IMPLEMENTAR** Laravel Socialite para Google y Microsoft OAuth.

La integracion es completamente compatible con el esquema actual de Sanctum SPA y agrega valor significativo en terminos de UX y conversion de usuarios.

**Prioridad sugerida:** Despues de Fase 6 Frontend (User Create/Edit)

**Fase propuesta:** Backend Fase 5B / Frontend Fase 10

---

## 12. Apendice: Ejemplo de Implementacion

### 12.1 SocialAuthController Completo

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SocialAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function __construct(
        private SocialAuthService $socialAuthService
    ) {}

    public function redirect(string $provider): RedirectResponse
    {
        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
            $user = $this->socialAuthService->findOrCreateUser($provider, $socialUser);

            // Verificar si tiene 2FA
            if ($user->two_factor_confirmed_at) {
                session(['pending_two_factor_user_id' => $user->id]);
                return redirect(config('app.frontend_url') . '/auth/two-factor-challenge');
            }

            // Login y regenerar session
            auth()->login($user);
            $request->session()->regenerate();

            return redirect(config('app.frontend_url') . '/');

        } catch (\Exception $e) {
            report($e);
            return redirect(config('app.frontend_url') . '/auth/login')
                ->with('error', 'Error al autenticar con ' . ucfirst($provider));
        }
    }
}
```

### 12.2 SocialAuthService Completo

```php
<?php

namespace App\Services;

use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class SocialAuthService
{
    public function findOrCreateUser(string $provider, SocialiteUser $socialUser): User
    {
        $providerIdField = "{$provider}_id";

        // Buscar por OAuth ID
        $user = User::where($providerIdField, $socialUser->getId())->first();
        if ($user) {
            $this->updateUserFromProvider($user, $socialUser);
            return $user;
        }

        // Buscar por email
        $user = User::where('email', $socialUser->getEmail())->first();
        if ($user) {
            $user->update([
                $providerIdField => $socialUser->getId(),
            ]);
            $this->updateUserFromProvider($user, $socialUser);
            return $user;
        }

        // Crear usuario nuevo
        $user = User::create([
            'name' => $socialUser->getName(),
            'email' => $socialUser->getEmail(),
            $providerIdField => $socialUser->getId(),
            'avatar_url' => $socialUser->getAvatar(),
            'password' => null,
            'has_password' => false,
            'email_verified_at' => now(),
        ]);

        // Asignar rol por defecto
        $user->assignRole('user');

        return $user;
    }

    private function updateUserFromProvider(User $user, SocialiteUser $socialUser): void
    {
        // Actualizar avatar si cambio
        if ($socialUser->getAvatar() && $user->avatar_url !== $socialUser->getAvatar()) {
            $user->update(['avatar_url' => $socialUser->getAvatar()]);
        }
    }
}
```

---

**Fin del Documento de Analisis**

*Generado por Claude Code*
*Fecha: 2026-01-25*
