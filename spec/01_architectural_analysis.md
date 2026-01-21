# Análisis Arquitectónico: Vristo POC

## Información del Documento

- **Fecha de Análisis:** 2026-01-19
- **Versión del Proyecto:** POC (Proof of Concept)
- **Analista:** Claude Code (Arquitecto de Software)

---

## 1. Resumen Ejecutivo

Vristo POC es una aplicación full-stack de administración de ventas que implementa una arquitectura SPA (Single Page Application) moderna. El proyecto combina Laravel 12 como backend API con Vue 3.5 + TypeScript como frontend, utilizando Laravel Sanctum para autenticación basada en cookies de sesión.

### Estado Actual

- **Madurez del Proyecto:** Etapa temprana (POC)
- **Funcionalidades Core Implementadas:**
  - Autenticación completa (login, registro, logout)
  - Recuperación de contraseña (forgot/reset password)
  - Gestión de sesión con Sanctum
  - Navigation guards en frontend
  - UI theme system (16 idiomas, dark mode, RTL)

- **Áreas Pendientes de Desarrollo:**
  - Sistema de roles y permisos
  - Gestión de usuarios (CRUD)
  - Verificación de email
  - Two-Factor Authentication (2FA)
  - Módulos de negocio (ventas, inventario, etc.)

---

## 2. Análisis de Arquitectura Actual

### 2.1 Stack Tecnológico

#### Backend
| Tecnología | Versión | Propósito | Estado |
|------------|---------|-----------|--------|
| PHP | 8.2+ | Lenguaje servidor | Estable |
| Laravel | 12.0 | Framework backend | Última versión |
| Laravel Sanctum | 4.2 | Autenticación SPA | Implementado |
| MySQL | 8.0 | Base de datos | Configurado |

#### Frontend
| Tecnología | Versión | Propósito | Estado |
|------------|---------|-----------|--------|
| Vue.js | 3.5.13 | Framework UI | Última versión |
| TypeScript | 5.7.0 | Type safety | Implementado |
| Vite | 6.0.0 | Build tool | Configurado |
| Vue Router | 4.5.0 | Client routing | 60+ rutas |
| Pinia | 2.3.0 | State management | En uso |
| Tailwind CSS | 3.4.17 | CSS framework | Configurado |
| Axios | 1.13.2 | HTTP client | Implementado |

### 2.2 Patrones Arquitecturales Implementados

#### 2.2.1 Separación Frontend-Backend (SPA Architecture)

**Fortalezas:**
1. **Separación de Responsabilidades Clara:**
   - Backend: API server, autenticación, lógica de negocio
   - Frontend: UI/UX, routing client-side, state management

2. **Catch-All Route Pattern:**
   ```php
   // routes/web.php
   Route::get('/{any}', [AppController::class, 'index'])->where('any', '.*');
   ```
   - Delega toda la navegación a Vue Router
   - Backend solo sirve el HTML inicial
   - Permite deep linking sin configuración de servidor

3. **API-First Design:**
   - Todas las operaciones pasan por `/api/*` endpoints
   - Preparado para mobile apps o third-party integrations
   - RESTful structure

**Áreas de Mejora:**
1. **Falta de versionado de API:** No hay `/api/v1/` structure
2. **No hay middleware de rate limiting** en endpoints públicos
3. **Falta documentación de API** (Swagger/OpenAPI)

#### 2.2.2 Autenticación con Sanctum (SPA Cookie-Based)

**Implementación Actual:**

```typescript
// Frontend: services/authService.ts
async login(credentials: LoginCredentials): Promise<AuthResponse> {
    await this.getCsrfCookie();  // CSRF token fetch
    const response = await api.post<AuthResponse>('/login', credentials);
    return response.data;
}
```

```php
// Backend: AuthController.php
public function login(LoginRequest $request): JsonResponse {
    if (!Auth::attempt($request->only('email', 'password'))) {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }
    $request->session()->regenerate();
    return response()->json(['message' => 'Login successful', 'user' => Auth::user()]);
}
```

**Fortalezas:**
1. **Seguridad Mejorada:**
   - CSRF protection habilitado
   - Session-based authentication (más seguro que tokens en localStorage)
   - Cookie httpOnly (no accesible desde JavaScript)

2. **Flujo Completo Implementado:**
   - Login/Register/Logout
   - Password reset con tokens
   - Verificación de token antes de reset
   - Session regeneration en login

3. **State Management Robusto:**
   - Pinia store (`auth.ts`) centraliza estado de autenticación
   - `fetchUser()` verifica sesión en initial load
   - Error handling consistente

**Áreas de Mejora:**
1. **Email verification no implementada** (campo `email_verified_at` existe pero no se usa)
2. **No hay 2FA/MFA**
3. **No hay rate limiting** en login/register endpoints
4. **Token expiration** no configurado (`expiration: null` en config/sanctum.php)

#### 2.2.3 Navigation Guards (Vue Router)

**Implementación:**

```typescript
// router/index.ts
const publicRoutes = [
    'boxed-signin', 'boxed-signup', 'cover-login', 'cover-register',
    'boxed-password-reset', 'cover-password-reset', 'reset-password',
    // ... error pages, etc.
];

router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore();

    // Check auth on initial load
    if (!authChecked && !authStore.isAuthenticated) {
        authChecked = true;
        await authStore.fetchUser();
    }

    // Redirect logic
    if (!authStore.isAuthenticated && !isPublicRoute) {
        next({ name: 'boxed-signin' });
    } else if (authStore.isAuthenticated && isPublicRoute) {
        next({ name: 'home' });
    } else {
        next();
    }
});
```

**Fortalezas:**
1. **Protección de rutas automática**
2. **Verificación de sesión en initial load** (evita flickering)
3. **Redirección inteligente** (logged users no ven login)
4. **Flag `authChecked`** evita múltiples calls al backend

**Áreas de Mejora:**
1. **No hay role-based routing** (falta meta: { roles: ['admin'] })
2. **Lista pública hardcoded** (debería venir de configuración)
3. **No hay manejo de rutas no encontradas** (404 handling)

#### 2.2.4 Form Request Validation (Laravel)

**Implementación:**

```php
// app/Http/Requests/Auth/LoginRequest.php
public function rules(): array {
    return [
        'email' => ['required', 'string', 'email'],
        'password' => ['required', 'string'],
    ];
}
```

**Fortalezas:**
1. **Validación centralizada** en Request classes
2. **Separation of concerns** (no validación en controllers)
3. **Custom error messages** configurables

**Áreas de Mejora:**
1. **No hay validación de frontend con Vuelidate** (librería instalada pero no usada)
2. **Falta validación de business rules** (ej: email domain restrictions)

### 2.3 Estructura de Base de Datos

**Esquema Actual:**

```sql
-- Tablas Base (4 originales + 4 nuevas)
users
  - id (PK)
  - name
  - email (unique)
  - email_verified_at (nullable) ⚠️ No implementado
  - password
  - remember_token
  - created_at, updated_at

password_reset_tokens
  - email (PK)
  - token
  - created_at

personal_access_tokens (Sanctum - no usado en SPA)
  - id (PK)
  - tokenable_type, tokenable_id
  - name, token, abilities
  - last_used_at, expires_at
  - created_at, updated_at

failed_jobs
sessions (nueva)
cache, cache_locks (nuevas)
jobs, job_batches (nuevas)
```

**Fortalezas:**
1. **Schema normalizado** (3NF)
2. **Timestamps en todas las tablas**
3. **Índices apropiados** (email unique)
4. **Password reset tokens** con timestamp

**Áreas de Mejora (Crítico):**
1. **No hay tabla de roles/permissions**
2. **No hay tabla de profiles** (datos adicionales de usuario)
3. **No hay tabla de activity_logs/audit_trail**
4. **No hay soft deletes** implementado en User model
5. **Falta tabla de sessions** para gestión avanzada (aunque Laravel usa database sessions)

---

## 3. Evaluación de Calidad Arquitectónica

### 3.1 Principios SOLID

| Principio | Aplicación | Evaluación |
|-----------|------------|------------|
| **Single Responsibility** | Controllers delgados, Request validation separada | ⭐⭐⭐⭐ Bueno |
| **Open/Closed** | No hay extensión clara (falta abstracción) | ⭐⭐ Mejorable |
| **Liskov Substitution** | No aplicable (no hay herencia compleja) | N/A |
| **Interface Segregation** | No hay interfaces definidas | ⭐ Débil |
| **Dependency Inversion** | No hay repositorios ni service layer | ⭐ Débil |

### 3.2 Clean Architecture Compliance

```
Actual:
┌─────────────────────────────────────────┐
│  Presentation Layer (Vue Components)    │
├─────────────────────────────────────────┤
│  State Management (Pinia Stores)        │
├─────────────────────────────────────────┤
│  Services (authService.ts)              │
├─────────────────────────────────────────┤
│  API Layer (Axios)                      │
└─────────────────────────────────────────┘
          ↕ HTTP
┌─────────────────────────────────────────┐
│  Routes (web.php, api.php)              │
├─────────────────────────────────────────┤
│  Controllers (AuthController)           │
├─────────────────────────────────────────┤
│  Request Validation                     │
├─────────────────────────────────────────┤
│  Models (User)                          │  ⚠️ No hay Service Layer
├─────────────────────────────────────────┤  ⚠️ No hay Repository Pattern
│  Database                               │  ⚠️ No hay Domain Layer
└─────────────────────────────────────────┘

Ideal (Clean Architecture):
┌─────────────────────────────────────────┐
│  Presentation (Controllers/Views)       │
├─────────────────────────────────────────┤
│  Application (Services/Use Cases)       │  ❌ FALTA
├─────────────────────────────────────────┤
│  Domain (Entities/Business Logic)       │  ❌ FALTA
├─────────────────────────────────────────┤
│  Infrastructure (Repositories/DB)       │  ❌ FALTA
└─────────────────────────────────────────┘
```

**Evaluación:** ⭐⭐ (2/5)
- Actualmente es un "fat model" approach sin capas de abstracción
- Controllers acceden directamente a Models (no hay repositorios)
- Business logic tiende a mezclarse en controllers
- No hay separación entre domain entities y database models

### 3.3 Seguridad

| Aspecto | Estado | Evaluación |
|---------|--------|------------|
| CSRF Protection | Implementado (Sanctum) | ✅ Bueno |
| SQL Injection | Protegido (Eloquent ORM) | ✅ Bueno |
| XSS Protection | Vue escapa por defecto | ✅ Bueno |
| Password Hashing | bcrypt (Laravel default) | ✅ Bueno |
| Session Security | httpOnly cookies | ✅ Bueno |
| Rate Limiting | No implementado | ❌ Crítico |
| Email Verification | No implementado | ⚠️ Importante |
| 2FA/MFA | No implementado | ⚠️ Importante |
| CORS Configuration | Default (localhost) | ⚠️ Revisar en producción |
| API Versioning | No implementado | ⚠️ Importante |
| Input Validation | Parcial (backend only) | ⚠️ Mejorable |
| Audit Trail | No implementado | ❌ Falta |

### 3.4 Performance

| Aspecto | Estado | Impacto |
|---------|--------|---------|
| **Frontend** |
| Code Splitting | Lazy loading de rutas | ✅ Óptimo |
| Asset Optimization | Vite production build | ✅ Óptimo |
| State Persistence | localStorage (Pinia) | ✅ Bueno |
| API Caching | No implementado | ⚠️ Mejorable |
| **Backend** |
| Query Optimization | No hay eager loading | ⚠️ Mejorable |
| Database Indexing | Solo email unique | ⚠️ Mejorable |
| Response Caching | No implementado | ⚠️ Mejorable |
| Queue System | Migrations creadas pero no usado | ⚠️ Pendiente |

### 3.5 Mantenibilidad

| Aspecto | Evaluación | Notas |
|---------|------------|-------|
| **Estructura del Código** | ⭐⭐⭐⭐ | Clara separación frontend/backend |
| **Naming Conventions** | ⭐⭐⭐⭐ | Consistente (camelCase/PascalCase) |
| **Type Safety** | ⭐⭐⭐⭐ | TypeScript bien tipado |
| **Documentación** | ⭐⭐ | Solo CLAUDE.md, falta API docs |
| **Testing** | ⭐ | No hay tests implementados |
| **Error Handling** | ⭐⭐⭐ | Try/catch en frontend, falta logger |

---

## 4. Próximos Pasos Recomendados

### 4.1 Funcionalidades Core (Prioridad Alta)

#### A. CRUD de Usuarios con Roles (Admin Panel)

**Impacto Arquitectural:**

**Backend:**
```
Nuevos archivos (14):
1. app/Models/Role.php
2. app/Models/Permission.php
3. app/Http/Controllers/Api/UserController.php
4. app/Http/Controllers/Api/RoleController.php
5. app/Http/Requests/User/StoreUserRequest.php
6. app/Http/Requests/User/UpdateUserRequest.php
7. app/Policies/UserPolicy.php
8. database/migrations/xxxx_create_roles_table.php
9. database/migrations/xxxx_create_permissions_table.php
10. database/migrations/xxxx_create_role_user_table.php
11. database/migrations/xxxx_create_permission_role_table.php
12. database/migrations/xxxx_add_role_fields_to_users_table.php
13. database/seeders/RoleSeeder.php
14. database/seeders/PermissionSeeder.php

Modificaciones (3):
- routes/api.php (nuevas rutas)
- app/Models/User.php (relaciones)
- app/Providers/AuthServiceProvider.php (policies)
```

**Frontend:**
```
Nuevos archivos (8):
1. resources/js/src/views/admin/users/list.vue
2. resources/js/src/views/admin/users/create.vue
3. resources/js/src/views/admin/users/edit.vue
4. resources/js/src/views/admin/roles/list.vue
5. resources/js/src/services/userService.ts
6. resources/js/src/services/roleService.ts
7. resources/js/src/stores/user.ts
8. resources/js/src/types/user.ts

Modificaciones (2):
- router/index.ts (nuevas rutas admin)
- components/layout/Sidebar.vue (menú admin)
```

**Base de Datos:**
```sql
-- Nueva estructura
roles
  - id (PK)
  - name (unique)
  - display_name
  - description
  - created_at, updated_at

permissions
  - id (PK)
  - name (unique)
  - display_name
  - description
  - created_at, updated_at

role_user (pivot)
  - role_id (FK)
  - user_id (FK)
  - created_at

permission_role (pivot)
  - permission_id (FK)
  - role_id (FK)
  - created_at
```

**Complejidad:** ⭐⭐⭐⭐ Alta
**Tiempo Estimado:** 8-12 horas
**Prioridad:** 🔴 Alta (base para todo el sistema de autorización)

---

#### B. Email Verification

**Impacto Arquitectural:**

**Backend:**
```
Nuevos archivos (3):
1. app/Http/Controllers/Api/EmailVerificationController.php
2. app/Notifications/VerifyEmailNotification.php
3. routes/api.php (nuevas rutas)

Modificaciones (2):
- app/Models/User.php (implement MustVerifyEmail)
- app/Http/Middleware/EnsureEmailIsVerified.php (habilitar)
```

**Frontend:**
```
Nuevos archivos (2):
1. resources/js/src/views/auth/verify-email.vue
2. resources/js/src/views/auth/email-verification-notice.vue

Modificaciones (3):
- services/authService.ts (métodos verification)
- stores/auth.ts (estado verification)
- router/index.ts (nuevas rutas + middleware)
```

**Complejidad:** ⭐⭐ Media-Baja
**Tiempo Estimado:** 3-4 horas
**Prioridad:** 🟡 Media (mejora seguridad pero no bloqueante)

---

#### C. Profile Management

**Impacto Arquitectural:**

**Backend:**
```
Nuevos archivos (5):
1. app/Models/UserProfile.php
2. app/Http/Controllers/Api/ProfileController.php
3. app/Http/Requests/Profile/UpdateProfileRequest.php
4. app/Http/Requests/Profile/UpdatePasswordRequest.php
5. database/migrations/xxxx_create_user_profiles_table.php

Modificaciones (2):
- app/Models/User.php (relación hasOne profile)
- routes/api.php
```

**Frontend:**
```
Modificaciones (2):
- resources/js/src/views/users/profile.vue (actualmente es demo)
- resources/js/src/views/users/user-account-settings.vue (funcional)

Nuevos archivos (2):
1. resources/js/src/services/profileService.ts
2. resources/js/src/stores/profile.ts
```

**Base de Datos:**
```sql
user_profiles
  - id (PK)
  - user_id (FK unique)
  - phone
  - address
  - city
  - country
  - avatar_url
  - bio
  - date_of_birth
  - timezone
  - language
  - created_at, updated_at
```

**Complejidad:** ⭐⭐⭐ Media
**Tiempo Estimado:** 4-6 horas
**Prioridad:** 🟡 Media (mejora UX)

---

#### D. Two-Factor Authentication (2FA)

**Impacto Arquitectural:**

**Backend:**
```
Dependencia nueva:
- composer require pragmarx/google2fa-laravel

Nuevos archivos (6):
1. app/Http/Controllers/Api/TwoFactorController.php
2. app/Http/Requests/Auth/TwoFactorRequest.php
3. app/Services/TwoFactorService.php
4. database/migrations/xxxx_add_two_factor_to_users.php
5. config/google2fa.php
6. app/Notifications/TwoFactorCodeNotification.php

Modificaciones (2):
- app/Models/User.php (campos 2FA)
- app/Http/Controllers/Api/AuthController.php (check 2FA en login)
```

**Frontend:**
```
Nuevos archivos (3):
1. resources/js/src/views/auth/two-factor-challenge.vue
2. resources/js/src/views/users/two-factor-settings.vue
3. resources/js/src/services/twoFactorService.ts

Modificaciones (3):
- stores/auth.ts (flujo 2FA)
- router/index.ts (ruta challenge)
- views/users/user-account-settings.vue (tab 2FA)
```

**Base de Datos:**
```sql
-- Agregar a users table
users
  + two_factor_secret (nullable, encrypted)
  + two_factor_recovery_codes (nullable, encrypted)
  + two_factor_confirmed_at (nullable, timestamp)
```

**Complejidad:** ⭐⭐⭐⭐ Alta
**Tiempo Estimado:** 6-8 horas
**Prioridad:** 🟡 Media-Alta (seguridad avanzada)

---

### 4.2 Mejoras de Arquitectura (Prioridad Media)

#### E. Implementar Service Layer + Repository Pattern

**Objetivo:** Separar lógica de negocio de controllers y abstraer acceso a datos.

**Estructura Propuesta:**

```
app/
├── Services/                          # Nueva carpeta
│   ├── Auth/
│   │   ├── AuthService.php           # Lógica de autenticación
│   │   ├── PasswordResetService.php  # Lógica de password reset
│   │   └── EmailVerificationService.php
│   └── User/
│       └── UserService.php           # CRUD + business logic
│
├── Repositories/                      # Nueva carpeta
│   ├── Contracts/
│   │   ├── UserRepositoryInterface.php
│   │   └── RoleRepositoryInterface.php
│   └── Eloquent/
│       ├── UserRepository.php        # Implementación Eloquent
│       └── RoleRepository.php
│
└── Providers/
    └── RepositoryServiceProvider.php  # Bindings
```

**Ejemplo de Implementación:**

```php
// app/Repositories/Contracts/UserRepositoryInterface.php
interface UserRepositoryInterface {
    public function findById(int $id): ?User;
    public function findByEmail(string $email): ?User;
    public function create(array $data): User;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function paginate(int $perPage = 15);
}

// app/Repositories/Eloquent/UserRepository.php
class UserRepository implements UserRepositoryInterface {
    public function findById(int $id): ?User {
        return User::find($id);
    }
    // ... resto de métodos
}

// app/Services/User/UserService.php
class UserService {
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RoleRepositoryInterface $roleRepository
    ) {}

    public function createUser(array $data): User {
        // Business logic
        $user = $this->userRepository->create($data);
        $this->roleRepository->assignDefaultRole($user);
        event(new UserCreated($user));
        return $user;
    }
}

// app/Http/Controllers/Api/UserController.php
class UserController extends Controller {
    public function __construct(private UserService $userService) {}

    public function store(StoreUserRequest $request) {
        $user = $this->userService->createUser($request->validated());
        return response()->json($user, 201);
    }
}
```

**Impacto:**
- **Archivos nuevos:** ~15
- **Refactoring:** Todos los controllers existentes
- **Complejidad:** ⭐⭐⭐⭐⭐ Muy Alta
- **Tiempo:** 12-16 horas
- **Prioridad:** 🟡 Media (mejora mantenibilidad pero no bloqueante)

**Beneficios:**
1. Testabilidad (mock repositories fácilmente)
2. Reutilización de lógica
3. Cambio de ORM sin afectar business logic
4. Cumplimiento de SOLID (DIP)

---

#### F. API Versioning + Documentation

**Objetivo:** Preparar API para evolución sin breaking changes.

**Estructura:**

```
routes/
└── api/
    ├── v1.php  # Versión 1 (actual)
    └── v2.php  # Versión 2 (futura)

app/Http/Controllers/Api/
├── V1/
│   ├── AuthController.php
│   └── UserController.php
└── V2/
    └── AuthController.php  # Nuevas features
```

**Configuración:**

```php
// routes/api.php
Route::prefix('v1')->group(base_path('routes/api/v1.php'));
Route::prefix('v2')->group(base_path('routes/api/v2.php'));

// Frontend: services/api.ts
const API_VERSION = import.meta.env.VITE_API_VERSION || 'v1';
const api = axios.create({
    baseURL: `/api/${API_VERSION}`,
});
```

**Swagger/OpenAPI Documentation:**

```bash
composer require darkaonline/l5-swagger
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
```

```php
// Ejemplo de documentación
/**
 * @OA\Post(
 *     path="/api/v1/login",
 *     tags={"Authentication"},
 *     summary="User login",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"email", "password"},
 *             @OA\Property(property="email", type="string", format="email"),
 *             @OA\Property(property="password", type="string", format="password")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Login successful"),
 *     @OA\Response(response=422, description="Validation error")
 * )
 */
public function login(LoginRequest $request) { ... }
```

**Complejidad:** ⭐⭐⭐ Media
**Tiempo:** 4-6 horas
**Prioridad:** 🟢 Baja (útil pero no urgente)

---

#### G. Rate Limiting + Security Hardening

**Objetivo:** Proteger endpoints de abuse y ataques.

**Implementación:**

```php
// app/Http/Kernel.php
protected $middlewareAliases = [
    'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
];

// routes/api.php
Route::middleware(['throttle:login'])->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['throttle:password-reset'])->group(function () {
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
});

// config/rate-limiting.php (nuevo)
return [
    'login' => [
        'max_attempts' => 5,
        'decay_minutes' => 15,
    ],
    'password-reset' => [
        'max_attempts' => 3,
        'decay_minutes' => 60,
    ],
    'api' => [
        'max_attempts' => 60,
        'decay_minutes' => 1,
    ],
];
```

**Frontend Error Handling:**

```typescript
// services/api.ts
api.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 429) {
            showNotification({
                title: 'Too Many Requests',
                message: 'Please wait before trying again',
                type: 'error'
            });
        }
        return Promise.reject(error);
    }
);
```

**Complejidad:** ⭐⭐ Baja
**Tiempo:** 2-3 horas
**Prioridad:** 🔴 Alta (seguridad crítica)

---

### 4.3 Testing Strategy (Prioridad Alta)

**Objetivo:** Implementar pirámide de testing (Unit > Integration > E2E).

#### Backend Testing (PHPUnit)

```php
// tests/Unit/Services/UserServiceTest.php
class UserServiceTest extends TestCase {
    public function test_can_create_user_with_default_role() {
        $userService = new UserService(
            $this->mock(UserRepositoryInterface::class),
            $this->mock(RoleRepositoryInterface::class)
        );

        $user = $userService->createUser([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $this->assertInstanceOf(User::class, $user);
    }
}

// tests/Feature/Api/AuthTest.php
class AuthTest extends TestCase {
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials() {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password'
        ]);

        $response->assertOk()
                 ->assertJsonStructure(['user', 'message']);
    }

    public function test_user_cannot_login_with_invalid_credentials() {
        $response = $this->postJson('/api/login', [
            'email' => 'wrong@example.com',
            'password' => 'wrongpassword'
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }
}
```

#### Frontend Testing (Vitest + Vue Test Utils)

```bash
npm install -D vitest @vue/test-utils happy-dom
```

```typescript
// tests/unit/stores/auth.spec.ts
import { setActivePinia, createPinia } from 'pinia';
import { useAuthStore } from '@/stores/auth';
import { describe, it, expect, beforeEach, vi } from 'vitest';

describe('Auth Store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('should login successfully', async () => {
        const authStore = useAuthStore();

        vi.mock('@/services/authService', () => ({
            default: {
                login: vi.fn().mockResolvedValue({
                    user: { id: 1, name: 'Test', email: 'test@example.com' },
                    message: 'Login successful'
                })
            }
        }));

        await authStore.login({ email: 'test@example.com', password: 'password' });

        expect(authStore.isAuthenticated).toBe(true);
        expect(authStore.user?.name).toBe('Test');
    });
});
```

**Complejidad:** ⭐⭐⭐⭐ Alta (requiere setup)
**Tiempo:** 8-12 horas (setup + tests básicos)
**Prioridad:** 🔴 Alta (calidad del código)

---

## 5. Roadmap de Implementación

### Fase 1: Fundamentos (Semanas 1-2)

**Objetivo:** Establecer base sólida de seguridad y arquitectura.

| # | Tarea | Prioridad | Tiempo | Dependencias |
|---|-------|-----------|--------|--------------|
| 1 | Rate Limiting + Security Hardening | 🔴 Alta | 3h | Ninguna |
| 2 | Email Verification | 🟡 Media | 4h | Ninguna |
| 3 | Backend Testing Setup | 🔴 Alta | 4h | Ninguna |
| 4 | Frontend Testing Setup | 🔴 Alta | 4h | Ninguna |
| **Total Fase 1** | | | **15h** | |

### Fase 2: Roles y Permisos (Semanas 3-4)

**Objetivo:** Sistema completo de autorización.

| # | Tarea | Prioridad | Tiempo | Dependencias |
|---|-------|-----------|--------|--------------|
| 5 | DB Migrations (Roles/Permissions) | 🔴 Alta | 2h | Fase 1 |
| 6 | Backend: Models + Policies | 🔴 Alta | 4h | #5 |
| 7 | Backend: Controllers + Routes | 🔴 Alta | 3h | #6 |
| 8 | Frontend: Services + Stores | 🔴 Alta | 3h | #7 |
| 9 | Frontend: Admin UI (Users CRUD) | 🔴 Alta | 6h | #8 |
| 10 | Tests (Roles/Permissions) | 🟡 Media | 4h | #6-9 |
| **Total Fase 2** | | | **22h** | |

### Fase 3: Profile y 2FA (Semanas 5-6)

**Objetivo:** Mejorar experiencia de usuario y seguridad avanzada.

| # | Tarea | Prioridad | Tiempo | Dependencias |
|---|-------|-----------|--------|--------------|
| 11 | Profile Management Backend | 🟡 Media | 3h | Fase 2 |
| 12 | Profile Management Frontend | 🟡 Media | 4h | #11 |
| 13 | Two-Factor Authentication Backend | 🟡 Media | 5h | Fase 2 |
| 14 | Two-Factor Authentication Frontend | 🟡 Media | 4h | #13 |
| 15 | Tests (Profile + 2FA) | 🟡 Media | 4h | #11-14 |
| **Total Fase 3** | | | **20h** | |

### Fase 4: Refactoring Arquitectónico (Semanas 7-8)

**Objetivo:** Mejorar mantenibilidad y escalabilidad.

| # | Tarea | Prioridad | Tiempo | Dependencias |
|---|-------|-----------|--------|--------------|
| 16 | Service Layer Implementation | 🟡 Media | 8h | Fase 2-3 |
| 17 | Repository Pattern Implementation | 🟡 Media | 6h | #16 |
| 18 | Refactor Controllers | 🟡 Media | 4h | #16-17 |
| 19 | API Versioning Setup | 🟢 Baja | 3h | Fase 1 |
| 20 | Swagger Documentation | 🟢 Baja | 4h | #19 |
| **Total Fase 4** | | | **25h** | |

**Tiempo Total Estimado:** 82 horas (aprox. 2 meses a medio tiempo)

---

## 6. Métricas de Éxito

### 6.1 Cobertura de Testing
- **Backend:** Mínimo 70% code coverage
- **Frontend:** Mínimo 60% code coverage
- **E2E:** Flujos críticos (login, register, password reset)

### 6.2 Performance
- **Time to Interactive (TTI):** < 3s
- **API Response Time:** < 200ms (p95)
- **Lighthouse Score:** > 90

### 6.3 Seguridad
- **Vulnerabilidades conocidas:** 0 (composer audit, npm audit)
- **Rate limiting:** Todos los endpoints públicos protegidos
- **OWASP Top 10:** Mitigado

### 6.4 Mantenibilidad
- **Cyclomatic Complexity:** < 10 por método
- **Duplicación de código:** < 5%
- **Documentación API:** 100% endpoints documentados

---

## 7. Riesgos y Mitigaciones

### 7.1 Riesgos Técnicos

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|--------------|---------|------------|
| **Cambios breaking en Laravel 12** | Media | Alto | Pin version en composer.json, tests automáticos |
| **Complejidad de roles/permisos** | Alta | Medio | Usar paquete probado (spatie/laravel-permission) |
| **Performance con muchos usuarios** | Media | Alto | Implementar caching, eager loading, índices DB |
| **Conflictos en migraciones** | Baja | Medio | Naming convention estricto, code review |

### 7.2 Riesgos de Proceso

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|--------------|---------|------------|
| **Scope creep** | Alta | Alto | Roadmap estricto, no features hasta Fase 4 |
| **Falta de tests** | Media | Alto | Hacer TDD obligatorio desde Fase 1 |
| **Deuda técnica acumulada** | Alta | Medio | Refactoring semanal, code review |

---

## 8. Alternativas Evaluadas

### 8.1 Sistema de Roles

**Opción A: Implementación Custom**
- ✅ Control total
- ❌ Mayor tiempo de desarrollo (22h vs 8h)
- ❌ Mayor superficie de bugs

**Opción B: Spatie Laravel-Permission** (Recomendado)
- ✅ Probado en producción (10k+ proyectos)
- ✅ Documentación extensa
- ✅ Compatible con Sanctum
- ❌ Dependencia externa

```bash
composer require spatie/laravel-permission
```

**Decisión:** Usar Spatie para MVP, evaluar custom si hay limitaciones.

### 8.2 Two-Factor Authentication

**Opción A: Laravel Fortify**
- ✅ Oficial de Laravel
- ✅ Integración nativa
- ❌ Opinionated (estructura fija)

**Opción B: PragmaRX Google2FA** (Recomendado)
- ✅ Flexible
- ✅ Funciona con Sanctum SPA
- ✅ TOTP estándar (compatible con Google Authenticator, Authy)

**Decisión:** Google2FA por flexibilidad.

---

## 9. Conclusiones y Recomendaciones

### 9.1 Fortalezas Actuales

1. **Arquitectura SPA bien implementada**: Separación clara frontend/backend
2. **Autenticación robusta**: Sanctum con CSRF, session-based
3. **Type safety**: TypeScript en frontend
4. **Modern stack**: Laravel 12, Vue 3.5, últimas versiones
5. **UI rica**: 60+ rutas, 16 idiomas, theme system

### 9.2 Áreas Críticas de Mejora

1. **Falta de testing**: 0% coverage actualmente
2. **No hay Service Layer**: Controllers con lógica de negocio
3. **No hay roles/permisos**: Bloqueante para features de admin
4. **No hay rate limiting**: Vulnerable a brute force
5. **Email verification no implementada**: Riesgo de spam accounts

### 9.3 Recomendaciones Inmediatas (Esta Semana)

1. **Implementar rate limiting** (3h) - Crítico para seguridad
2. **Setup de tests** (8h) - Fundamental para calidad
3. **Email verification** (4h) - Baja fricción, alto valor

### 9.4 Recomendaciones de Mediano Plazo (Próximas 4 Semanas)

1. **Sistema de roles completo** (Fase 2 completa)
2. **Service Layer + Repositories** (mejorar arquitectura)
3. **Profile management** (mejorar UX)

### 9.5 Visión a Largo Plazo

**Evolución hacia Clean Architecture:**

```
Mes 1-2:  Fundamentos + Roles
Mes 3-4:  Service Layer + Repositories
Mes 5-6:  Domain Layer + Use Cases
Mes 7-8:  Microservices preparation (si es necesario)
```

**Stack Tecnológico Futuro:**
- **Caching:** Redis (Laravel Cache + Session)
- **Queue:** Laravel Horizon (jobs asíncronos)
- **Search:** Laravel Scout + Algolia/Meilisearch
- **Logs:** Laravel Telescope (desarrollo) + Sentry (producción)
- **CI/CD:** GitHub Actions (tests automáticos)

---

## 10. Apéndices

### 10.1 Checklist de Implementación (Features)

#### Email Verification
```
Backend:
[ ] User model implements MustVerifyEmail
[ ] EmailVerificationController.php creado
[ ] Rutas /email/verify y /email/resend configuradas
[ ] Middleware verified aplicado a rutas protegidas
[ ] Notification VerifyEmail customizada

Frontend:
[ ] Vista verify-email.vue creada
[ ] Vista email-verification-notice.vue creada
[ ] authService.ts métodos agregados
[ ] Router guards actualizados
[ ] Manejo de errores implementado

Tests:
[ ] Unit: EmailVerificationService
[ ] Feature: Email verification flow
[ ] E2E: Usuario completa verificación
```

#### Roles y Permisos
```
Backend:
[ ] Spatie/permission instalado
[ ] Migrations ejecutadas (roles, permissions, pivots)
[ ] Models Role, Permission creados
[ ] Seeder con roles default (admin, user, editor)
[ ] UserPolicy implementada
[ ] RoleController con CRUD
[ ] Middleware role:admin configurado

Frontend:
[ ] roleService.ts + userService.ts creados
[ ] Stores actualizados
[ ] Admin UI (users list, create, edit)
[ ] Role assignment UI
[ ] Directivas v-can (permisos en templates)

Tests:
[ ] Unit: RoleService, UserService
[ ] Feature: CRUD roles, assign roles
[ ] E2E: Admin gestiona usuarios
```

### 10.2 Comandos Útiles

```bash
# Testing
./vendor/bin/phpunit                    # Todos los tests PHP
./vendor/bin/phpunit --filter=AuthTest  # Test específico
npm run test                            # Tests frontend (cuando esté configurado)

# Database
php artisan migrate:fresh --seed        # Reset completo con seeders
php artisan db:seed --class=RoleSeeder  # Seed específico

# Cache
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Code Quality
./vendor/bin/pint                       # Laravel Pint (formatter)
composer audit                          # Check vulnerabilidades
npm audit                               # Check vulnerabilidades frontend

# Deployment
php artisan optimize                    # Cache todo para producción
npm run build                           # Build frontend optimizado
```

### 10.3 Recursos Adicionales

**Documentación:**
- [Laravel 12 Docs](https://laravel.com/docs/12.x)
- [Laravel Sanctum SPA Authentication](https://laravel.com/docs/12.x/sanctum#spa-authentication)
- [Spatie Laravel-Permission](https://spatie.be/docs/laravel-permission/v6/introduction)
- [Vue Router Navigation Guards](https://router.vuejs.org/guide/advanced/navigation-guards.html)
- [Pinia State Management](https://pinia.vuejs.org/)

**Paquetes Recomendados:**
```bash
# Backend
composer require spatie/laravel-permission      # Roles y permisos
composer require spatie/laravel-activitylog     # Audit trail
composer require barryvdh/laravel-debugbar      # Debugging
composer require laravel/telescope              # Monitoring
composer require darkaonline/l5-swagger         # API docs

# Frontend
npm install @vuelidate/core @vuelidate/validators  # Form validation
npm install @vueuse/core                           # Vue composables
npm install vitest @vue/test-utils                 # Testing
```

---

**Fin del Documento de Análisis Arquitectónico**

**Próximos pasos sugeridos:**
1. Revisar este documento con el equipo
2. Priorizar features según necesidades de negocio
3. Crear issues en GitHub/Jira para cada task
4. Comenzar con Fase 1 (Fundamentos + Testing)

**Contacto para consultas arquitectónicas:**
Claude Code - Software Architect Specialist
