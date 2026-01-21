# Especificacion Tecnica: Sistema de Autenticacion y Arquitectura Base

## Metadata
- **Fecha:** 2026-01-19
- **Version:** 1.0
- **Arquitecto:** Claude Code (Architect Agent)
- **Estado:** Implementado parcialmente

---

## 1. Problema

El proyecto Vristo POC necesita evolucionar de un POC basico a una aplicacion enterprise-ready con:
- Sistema de autenticacion robusto (completado)
- Roles y permisos
- Gestion de usuarios (CRUD)
- Verificacion de email
- Two-Factor Authentication (2FA)
- Arquitectura Clean con Service Layer

---

## 2. Estado Actual del Proyecto

### 2.1 Stack Tecnologico

| Capa | Tecnologia | Version | Estado |
|------|------------|---------|--------|
| Backend | Laravel | 12.0 | Configurado |
| Backend | PHP | 8.2+ | Configurado |
| Backend | Laravel Sanctum | 4.2 | Implementado |
| Frontend | Vue.js | 3.5.13 | Configurado |
| Frontend | TypeScript | 5.7.0 | Implementado |
| Frontend | Vue Router | 4.5.0 | 60+ rutas |
| Frontend | Pinia | 2.3.0 | En uso |
| Frontend | Tailwind CSS | 3.4.17 | Configurado |
| Database | MySQL | 8.0 | Configurado |

### 2.2 Funcionalidades Implementadas

| Funcionalidad | Estado | Archivos Principales |
|---------------|--------|---------------------|
| Login/Register | Completado | AuthController, authService.ts, auth.ts |
| Logout | Completado | Header.vue, AuthController |
| Password Reset | Completado | PasswordResetController, reset-password.vue |
| Navigation Guards | Completado | router/index.ts |
| User Info en Header | Completado | Header.vue |
| Session Management | Completado | Sanctum cookie-based |

### 2.3 Arquitectura Actual

```
Frontend (Vue SPA)
    |
    v
[Presentation Layer]
    - Vue Components (99+ views)
    - Pinia Stores (auth.ts, index.ts)
    - Services (authService.ts, api.ts)
    |
    v HTTP (Axios + CSRF)
    |
Backend (Laravel)
    |
    v
[Routes] api.php, web.php
    |
    v
[Controllers] AuthController, PasswordResetController
    |
    v
[Form Requests] LoginRequest, RegisterRequest, etc.
    |
    v
[Models] User (Eloquent)
    |
    v
[Database] MySQL
```

---

## 3. Impacto Arquitectural

### 3.1 Archivos Backend Existentes

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php
│   │   ├── AppController.php
│   │   └── Api/
│   │       ├── AuthController.php          [IMPLEMENTADO]
│   │       └── PasswordResetController.php [IMPLEMENTADO]
│   ├── Requests/
│   │   └── Auth/
│   │       ├── LoginRequest.php            [IMPLEMENTADO]
│   │       ├── RegisterRequest.php         [IMPLEMENTADO]
│   │       ├── ForgotPasswordRequest.php   [IMPLEMENTADO]
│   │       └── ResetPasswordRequest.php    [IMPLEMENTADO]
│   └── Middleware/
│       └── (11 middlewares estandar)
├── Models/
│   └── User.php                            [MODIFICADO - sendPasswordResetNotification]
├── Notifications/
│   └── ResetPasswordNotification.php       [IMPLEMENTADO]
└── Providers/
    └── (5 service providers estandar)

routes/
├── api.php                                 [MODIFICADO - rutas auth]
└── web.php                                 [catch-all route]
```

### 3.2 Archivos Frontend Existentes

```
resources/js/src/
├── services/
│   ├── api.ts                              [IMPLEMENTADO]
│   └── authService.ts                      [IMPLEMENTADO]
├── stores/
│   ├── index.ts                            (app store)
│   └── auth.ts                             [IMPLEMENTADO]
├── router/
│   └── index.ts                            [MODIFICADO - guards + reset-password]
├── views/auth/
│   ├── boxed-signin.vue                    [MODIFICADO - funcional]
│   ├── boxed-signup.vue                    [MODIFICADO - funcional]
│   ├── boxed-password-reset.vue            [MODIFICADO - funcional]
│   ├── cover-password-reset.vue            [MODIFICADO - funcional]
│   └── reset-password.vue                  [IMPLEMENTADO - nuevo]
└── components/layout/
    └── Header.vue                          [MODIFICADO - logout + user info]
```

---

## 4. Propuesta de Solucion: Proximas Fases

### 4.1 Arquitectura Objetivo (Clean Architecture)

```
Frontend (Vue SPA)
    |
    v
[Presentation Layer]
    - Vue Components
    - Pinia Stores
    - Services
    |
    v HTTP
    |
Backend (Laravel)
    |
    v
[Controllers] - Thin controllers, only HTTP handling
    |
    v
[Application Layer] - Services (Business Logic)    [NUEVO]
    |
    v
[Domain Layer] - Entities, Value Objects           [NUEVO]
    |
    v
[Infrastructure] - Repositories (Data Access)      [NUEVO]
    |
    v
[Database]
```

### 4.2 Funcionalidades Pendientes por Prioridad

| # | Funcionalidad | Prioridad | Complejidad | Tiempo Est. |
|---|---------------|-----------|-------------|-------------|
| 1 | Rate Limiting | CRITICO | Baja | 3h |
| 2 | Testing Setup | ALTO | Media | 8h |
| 3 | Email Verification | MEDIO | Media | 4h |
| 4 | Roles y Permisos (Spatie) | ALTO | Alta | 12h |
| 5 | CRUD Usuarios (Admin) | ALTO | Alta | 8h |
| 6 | Profile Management | MEDIO | Media | 6h |
| 7 | Two-Factor Auth (2FA) | MEDIO | Alta | 8h |
| 8 | Service Layer | MEDIO | Muy Alta | 16h |
| 9 | Repository Pattern | BAJO | Alta | 8h |
| 10 | API Versioning | BAJO | Media | 4h |

---

## 5. Plan de Implementacion

### Fase 1: Seguridad y Testing (Semanas 1-2)

#### 1.1 Rate Limiting (3h)

**Backend - Nuevos archivos:**
```
config/rate-limiting.php (nuevo)
```

**Backend - Modificaciones:**
```php
// app/Providers/RouteServiceProvider.php
RateLimiter::for('login', function (Request $request) {
    return [
        Limit::perMinute(5)->by($request->email.$request->ip()),
        Limit::perDay(20)->by($request->ip()),
    ];
});

RateLimiter::for('password-reset', function (Request $request) {
    return Limit::perHour(3)->by($request->email.$request->ip());
});

// routes/api.php
Route::middleware(['throttle:login'])->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});
```

#### 1.2 Testing Setup (8h)

**Backend:**
```bash
# Ya incluido en Laravel, configurar:
phpunit.xml
tests/Feature/Api/AuthTest.php
tests/Unit/Services/UserServiceTest.php
```

**Frontend:**
```bash
npm install -D vitest @vue/test-utils happy-dom
```

```
vitest.config.ts (nuevo)
tests/unit/stores/auth.spec.ts (nuevo)
tests/unit/components/Header.spec.ts (nuevo)
```

### Fase 2: Roles y Permisos (Semanas 3-4)

#### 2.1 Instalacion Spatie

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

#### 2.2 Nuevos Archivos Backend (14)

```
app/
├── Http/
│   ├── Controllers/Api/
│   │   ├── UserController.php
│   │   └── RoleController.php
│   └── Requests/
│       └── User/
│           ├── StoreUserRequest.php
│           └── UpdateUserRequest.php
├── Policies/
│   └── UserPolicy.php
database/
├── migrations/
│   └── xxxx_create_permission_tables.php (auto Spatie)
└── seeders/
    └── RolePermissionSeeder.php
```

#### 2.3 Nuevos Archivos Frontend (8)

```
resources/js/src/
├── views/admin/
│   └── users/
│       ├── list.vue
│       ├── create.vue
│       └── edit.vue
├── services/
│   ├── userService.ts
│   └── roleService.ts
├── stores/
│   └── user.ts
└── types/
    └── user.ts
```

#### 2.4 Base de Datos (Spatie)

```sql
-- Tablas creadas automaticamente por Spatie
roles (id, name, guard_name, created_at, updated_at)
permissions (id, name, guard_name, created_at, updated_at)
model_has_permissions (permission_id, model_type, model_id)
model_has_roles (role_id, model_type, model_id)
role_has_permissions (permission_id, role_id)
```

#### 2.5 Roles y Permisos Iniciales

```php
// database/seeders/RolePermissionSeeder.php
$permissions = [
    'users.view', 'users.create', 'users.update', 'users.delete',
    'roles.view', 'roles.create', 'roles.update', 'roles.delete',
    'profiles.view', 'profiles.update',
    'settings.view', 'settings.update',
    'activity-logs.view',
];

$roles = [
    'admin' => Permission::all(),
    'editor' => ['users.view', 'profiles.view', 'profiles.update'],
    'user' => ['profiles.view', 'profiles.update'],
];
```

### Fase 3: Profile y 2FA (Semanas 5-6)

#### 3.1 Profile Management

**Backend:**
```
app/Models/UserProfile.php
app/Http/Controllers/Api/ProfileController.php
app/Http/Requests/Profile/UpdateProfileRequest.php
database/migrations/xxxx_create_user_profiles_table.php
```

**Base de datos:**
```sql
CREATE TABLE user_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    city VARCHAR(100) NULL,
    country VARCHAR(100) NULL,
    avatar_url VARCHAR(255) NULL,
    bio TEXT NULL,
    date_of_birth DATE NULL,
    timezone VARCHAR(50) DEFAULT 'UTC',
    language VARCHAR(10) DEFAULT 'en',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### 3.2 Two-Factor Authentication

**Dependencia:**
```bash
composer require pragmarx/google2fa-laravel
```

**Backend:**
```
app/Http/Controllers/Api/TwoFactorController.php
app/Http/Requests/Auth/TwoFactorRequest.php
app/Services/TwoFactorService.php
database/migrations/xxxx_add_two_factor_to_users.php
```

**Base de datos (agregar a users):**
```sql
ALTER TABLE users ADD COLUMN two_factor_secret TEXT NULL;
ALTER TABLE users ADD COLUMN two_factor_recovery_codes TEXT NULL;
ALTER TABLE users ADD COLUMN two_factor_confirmed_at TIMESTAMP NULL;
```

**Frontend:**
```
resources/js/src/views/auth/two-factor-challenge.vue
resources/js/src/views/users/two-factor-settings.vue
resources/js/src/services/twoFactorService.ts
```

### Fase 4: Arquitectura Clean (Semanas 7-8)

#### 4.1 Service Layer

```
app/Services/
├── Auth/
│   ├── AuthService.php
│   └── PasswordResetService.php
└── User/
    └── UserService.php
```

#### 4.2 Repository Pattern

```
app/Repositories/
├── Contracts/
│   ├── UserRepositoryInterface.php
│   └── RoleRepositoryInterface.php
└── Eloquent/
    ├── UserRepository.php
    └── RoleRepository.php

app/Providers/
└── RepositoryServiceProvider.php
```

---

## 6. API Endpoints Especificados

### 6.1 Endpoints Existentes (Implementados)

| Metodo | Endpoint | Descripcion | Auth |
|--------|----------|-------------|------|
| POST | /api/register | Registrar usuario | No |
| POST | /api/login | Iniciar sesion | No |
| POST | /api/logout | Cerrar sesion | Si |
| GET | /api/user | Obtener usuario actual | Si |
| POST | /api/forgot-password | Solicitar reset | No |
| POST | /api/reset-password | Restablecer password | No |
| GET | /api/verify-token/{token}/{email} | Verificar token reset | No |

### 6.2 Endpoints Planificados (Fase 2-3)

| Metodo | Endpoint | Descripcion | Permiso |
|--------|----------|-------------|---------|
| GET | /api/v1/users | Listar usuarios | users.view |
| POST | /api/v1/users | Crear usuario | users.create |
| GET | /api/v1/users/{id} | Ver usuario | users.view |
| PUT | /api/v1/users/{id} | Actualizar usuario | users.update |
| DELETE | /api/v1/users/{id} | Eliminar usuario | users.delete |
| GET | /api/v1/roles | Listar roles | roles.view |
| GET | /api/v1/permissions | Listar permisos | roles.view |
| GET | /api/v1/profile | Ver perfil propio | auth |
| PUT | /api/v1/profile | Actualizar perfil | auth |
| POST | /api/v1/profile/avatar | Subir avatar | auth |
| POST | /api/v1/two-factor/enable | Habilitar 2FA | auth |
| POST | /api/v1/two-factor/confirm | Confirmar 2FA | auth |
| POST | /api/v1/two-factor/disable | Deshabilitar 2FA | auth |
| POST | /api/v1/two-factor-challenge | Verificar codigo 2FA | No |

---

## 7. Evaluacion de Seguridad

### 7.1 Estado Actual

| Aspecto | Estado | Evaluacion |
|---------|--------|------------|
| CSRF Protection | Implementado | OK |
| SQL Injection | Protegido (Eloquent) | OK |
| XSS Protection | Vue escapa por defecto | OK |
| Password Hashing | bcrypt | OK |
| Session Security | httpOnly cookies | OK |
| Rate Limiting | NO implementado | CRITICO |
| Email Verification | NO implementado | IMPORTANTE |
| 2FA/MFA | NO implementado | IMPORTANTE |

### 7.2 Recomendaciones Inmediatas

1. **Rate Limiting (CRITICO):** Implementar en login/register/password-reset
2. **Email Verification:** Activar MustVerifyEmail en User model
3. **Token Expiration:** Configurar expiration en config/sanctum.php

---

## 8. Metricas de Exito

### 8.1 Testing
- Backend: Minimo 70% code coverage
- Frontend: Minimo 60% code coverage
- E2E: Flujos criticos cubiertos

### 8.2 Performance
- TTI (Time to Interactive): < 3s
- API Response Time: < 200ms (p95)
- Lighthouse Score: > 90

### 8.3 Seguridad
- 0 vulnerabilidades conocidas
- Rate limiting en todos los endpoints publicos
- OWASP Top 10 mitigado

---

## 9. Riesgos y Mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigacion |
|--------|--------------|---------|------------|
| Breaking changes Laravel 12 | Media | Alto | Pin version, tests automaticos |
| Complejidad roles/permisos | Alta | Medio | Usar Spatie (probado) |
| Performance con muchos users | Media | Alto | Caching, eager loading, indices |
| Scope creep | Alta | Alto | Roadmap estricto |
| Deuda tecnica | Alta | Medio | Refactoring semanal |

---

## 10. Comandos Utiles

```bash
# Testing
./vendor/bin/phpunit                    # Tests PHP
./vendor/bin/phpunit --filter=AuthTest  # Test especifico
npm run test                            # Tests frontend

# Database
php artisan migrate:fresh --seed        # Reset + seed
php artisan db:seed --class=RolePermissionSeeder

# Cache
php artisan config:clear && php artisan route:clear && php artisan cache:clear

# Code Quality
./vendor/bin/pint                       # Laravel Pint
composer audit                          # Vulnerabilidades PHP
npm audit                               # Vulnerabilidades JS

# Deployment
php artisan optimize && npm run build
```

---

## 11. Referencias

- [Laravel 12 Docs](https://laravel.com/docs/12.x)
- [Laravel Sanctum SPA](https://laravel.com/docs/12.x/sanctum#spa-authentication)
- [Spatie Laravel-Permission](https://spatie.be/docs/laravel-permission/v6/introduction)
- [Vue Router Guards](https://router.vuejs.org/guide/advanced/navigation-guards.html)
- [Pinia State Management](https://pinia.vuejs.org/)

---

**Fin del Documento**

*Generado por Claude Code - Architect Agent*
*Fecha: 2026-01-19*
