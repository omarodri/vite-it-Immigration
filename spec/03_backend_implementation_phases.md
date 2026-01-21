# Plan de Implementacion Backend: Vristo POC

## Metadata
- **Fecha:** 2026-01-20
- **Version:** 1.0
- **Arquitecto:** Claude Code (Architect Agent)
- **Tiempo Total Estimado:** ~88 horas

---

## Resumen de Fases

| Fase | Nombre | Tiempo | Prioridad |
|------|--------|--------|-----------|
| 1 | Seguridad Basica y Testing Setup | 12h | CRITICO |
| 2 | Roles y Permisos (Spatie) | 16h | ALTO |
| 3 | Email Verification | 6h | MEDIO |
| 4 | Profile Management | 8h | MEDIO |
| 5 | Two-Factor Authentication | 12h | MEDIO |
| 6 | Service Layer y Repository Pattern | 16h | MEDIO |
| 7 | Activity Logs y Auditoria | 8h | BAJO |
| 8 | Optimizacion y Documentacion | 10h | BAJO |

---

## FASE 1: Seguridad Basica y Testing Setup

### Objetivo
Establecer las bases de seguridad criticas y configurar el entorno de testing para desarrollo TDD.

### Prerequisitos
- Proyecto Laravel funcionando con Sanctum
- MySQL configurado y migraciones base ejecutadas

### Tareas

#### 1.1 Rate Limiting (3h)
- [ ] Configurar `RateLimiter::for('login')` en RouteServiceProvider
- [ ] Configurar `RateLimiter::for('password-reset')`
- [ ] Configurar `RateLimiter::for('api')` para endpoints generales
- [ ] Crear archivo `config/rate-limiting.php` con configuracion centralizada
- [ ] Actualizar `routes/api.php` con middleware throttle
- [ ] Implementar manejo de errores 429 en respuestas JSON

#### 1.2 Testing Setup Backend (5h)
- [ ] Configurar `phpunit.xml` con base de datos de testing
- [ ] Crear `tests/TestCase.php` base con traits necesarios
- [ ] Configurar RefreshDatabase trait
- [ ] Crear factory para User: `database/factories/UserFactory.php`
- [ ] Crear test base: `tests/Feature/Api/AuthTest.php`
- [ ] Implementar tests para login/register/logout
- [ ] Implementar tests para password reset flow

#### 1.3 Security Hardening (4h)
- [ ] Configurar `config/sanctum.php` con token expiration
- [ ] Revisar y ajustar `config/cors.php` para produccion
- [ ] Configurar headers de seguridad en middleware
- [ ] Implementar logging de intentos de login fallidos
- [ ] Crear tabla `login_attempts` para tracking

### Archivos Afectados

**Nuevos:**
```
config/rate-limiting.php
database/migrations/xxxx_create_login_attempts_table.php
tests/Feature/Api/AuthTest.php
tests/Feature/Api/PasswordResetTest.php
```

**Modificados:**
```
app/Providers/RouteServiceProvider.php
routes/api.php
config/sanctum.php
config/cors.php
phpunit.xml
```

### Criterios de Aceptacion
- [ ] Rate limiting activo en endpoints de autenticacion
- [ ] Tests pasan con `./vendor/bin/phpunit`
- [ ] Login bloqueado despues de 5 intentos fallidos
- [ ] Logs de intentos fallidos registrados en BD

### Dependencias para Siguiente Fase
- Rate limiting configurado (requerido para Fase 2)
- Testing setup completo (requerido para TDD)

---

## FASE 2: Roles y Permisos (Spatie)

### Objetivo
Implementar sistema completo de roles y permisos usando Spatie Laravel Permission.

### Prerequisitos
- Fase 1 completada
- Tests de autenticacion pasando

### Tareas

#### 2.1 Instalacion y Configuracion Spatie (2h)
- [ ] Ejecutar `composer require spatie/laravel-permission`
- [ ] Publicar configuracion: `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`
- [ ] Ejecutar migraciones de Spatie
- [ ] Configurar `config/permission.php`
- [ ] Agregar trait HasRoles a modelo User

#### 2.2 Seeders de Roles y Permisos (3h)
- [ ] Crear `database/seeders/RolePermissionSeeder.php`
- [ ] Definir permisos base: users.*, roles.*, profiles.*, settings.*, activity-logs.*
- [ ] Crear roles: admin, editor, user
- [ ] Asignar permisos a roles
- [ ] Actualizar `DatabaseSeeder.php`

#### 2.3 Middleware de Autorizacion (3h)
- [ ] Crear middleware `CheckPermission.php`
- [ ] Crear middleware `CheckRole.php`
- [ ] Registrar middlewares en `app/Http/Kernel.php`
- [ ] Configurar middleware aliases

#### 2.4 User Controller con Permisos (4h)
- [ ] Crear `app/Http/Controllers/Api/UserController.php`
- [ ] Implementar metodos: index, show, store, update, destroy
- [ ] Crear `app/Http/Requests/User/StoreUserRequest.php`
- [ ] Crear `app/Http/Requests/User/UpdateUserRequest.php`
- [ ] Aplicar policies de autorizacion

#### 2.5 Role Controller (2h)
- [ ] Crear `app/Http/Controllers/Api/RoleController.php`
- [ ] Implementar endpoints: GET /roles, GET /permissions
- [ ] Crear rutas protegidas en `routes/api.php`

#### 2.6 Tests de Roles y Permisos (2h)
- [ ] Crear `tests/Feature/Api/UserControllerTest.php`
- [ ] Crear `tests/Feature/Api/RoleControllerTest.php`
- [ ] Tests para autorizacion (admin puede, user no puede)
- [ ] Tests para asignacion de roles

### Archivos Afectados

**Nuevos:**
```
app/Http/Controllers/Api/UserController.php
app/Http/Controllers/Api/RoleController.php
app/Http/Requests/User/StoreUserRequest.php
app/Http/Requests/User/UpdateUserRequest.php
app/Http/Middleware/CheckPermission.php
app/Http/Middleware/CheckRole.php
app/Policies/UserPolicy.php
database/seeders/RolePermissionSeeder.php
tests/Feature/Api/UserControllerTest.php
tests/Feature/Api/RoleControllerTest.php
```

**Modificados:**
```
app/Models/User.php (agregar HasRoles trait)
app/Http/Kernel.php
routes/api.php
database/seeders/DatabaseSeeder.php
```

### Criterios de Aceptacion
- [ ] 3 roles creados: admin, editor, user
- [ ] 13+ permisos definidos
- [ ] Admin puede gestionar usuarios
- [ ] User regular no puede acceder a CRUD usuarios
- [ ] Todos los tests pasan

### Dependencias para Siguiente Fase
- Sistema de roles funcionando
- Middleware de permisos configurado

---

## FASE 3: Email Verification

### Objetivo
Implementar verificacion de email obligatoria para nuevos usuarios.

### Prerequisitos
- Fase 2 completada
- Configuracion de mail (SMTP o Mailtrap)

### Tareas

#### 3.1 Configuracion del Modelo User (1h)
- [ ] Implementar `MustVerifyEmail` interface en User model
- [ ] Configurar `email_verified_at` campo
- [ ] Actualizar factory con estado verificado/no verificado

#### 3.2 Controller y Rutas (2h)
- [ ] Crear `app/Http/Controllers/Api/EmailVerificationController.php`
- [ ] Implementar metodos: send, verify
- [ ] Crear rutas en `routes/api.php`
- [ ] Configurar rate limiting para reenvio

#### 3.3 Notificacion Personalizada (1h)
- [ ] Crear `app/Notifications/VerifyEmailNotification.php`
- [ ] Personalizar template de email
- [ ] Configurar URL de verificacion para SPA

#### 3.4 Middleware de Verificacion (1h)
- [ ] Habilitar middleware `EnsureEmailIsVerified`
- [ ] Configurar excepciones de rutas
- [ ] Implementar respuesta JSON para no verificados

#### 3.5 Tests (1h)
- [ ] Crear `tests/Feature/Api/EmailVerificationTest.php`
- [ ] Test envio de email
- [ ] Test verificacion exitosa
- [ ] Test acceso denegado sin verificar

### Archivos Afectados

**Nuevos:**
```
app/Http/Controllers/Api/EmailVerificationController.php
app/Notifications/VerifyEmailNotification.php
tests/Feature/Api/EmailVerificationTest.php
```

**Modificados:**
```
app/Models/User.php
routes/api.php
app/Http/Kernel.php
```

### Criterios de Aceptacion
- [ ] Usuario nuevo recibe email de verificacion
- [ ] Click en link verifica cuenta
- [ ] Rutas protegidas requieren email verificado
- [ ] Reenvio limitado a 1 por minuto

### Dependencias para Siguiente Fase
- Email verification funcionando
- Mail driver configurado

---

## FASE 4: Profile Management

### Objetivo
Implementar gestion de perfiles de usuario con informacion extendida.

### Prerequisitos
- Fase 3 completada
- Verificacion de email funcionando

### Tareas

#### 4.1 Modelo y Migracion (2h)
- [ ] Crear migracion: `xxxx_create_user_profiles_table.php`
- [ ] Crear `app/Models/UserProfile.php`
- [ ] Configurar relacion hasOne en User
- [ ] Agregar campos: phone, address, city, country, avatar_url, bio, timezone, language

#### 4.2 Profile Controller (3h)
- [ ] Crear `app/Http/Controllers/Api/ProfileController.php`
- [ ] Implementar metodos: show, update
- [ ] Crear `app/Http/Requests/Profile/UpdateProfileRequest.php`
- [ ] Implementar upload de avatar

#### 4.3 Avatar Upload (2h)
- [ ] Configurar disco de storage para avatars
- [ ] Implementar validacion de imagen (size, type)
- [ ] Generar thumbnails (opcional)
- [ ] Crear ruta POST /profile/avatar

#### 4.4 Tests (1h)
- [ ] Crear `tests/Feature/Api/ProfileControllerTest.php`
- [ ] Test obtener perfil propio
- [ ] Test actualizar perfil
- [ ] Test upload avatar

### Archivos Afectados

**Nuevos:**
```
app/Models/UserProfile.php
app/Http/Controllers/Api/ProfileController.php
app/Http/Requests/Profile/UpdateProfileRequest.php
database/migrations/xxxx_create_user_profiles_table.php
tests/Feature/Api/ProfileControllerTest.php
```

**Modificados:**
```
app/Models/User.php (relacion profile)
routes/api.php
config/filesystems.php
```

### Criterios de Aceptacion
- [ ] Usuario puede ver y editar su perfil
- [ ] Avatar se guarda correctamente
- [ ] Validaciones funcionan
- [ ] Timezone y language persisten

### Dependencias para Siguiente Fase
- Profile management completo
- Storage configurado

---

## FASE 5: Two-Factor Authentication

### Objetivo
Implementar autenticacion de dos factores usando TOTP (Google Authenticator compatible).

### Prerequisitos
- Fase 4 completada
- Profile funcionando

### Tareas

#### 5.1 Instalacion Google2FA (1h)
- [ ] Ejecutar `composer require pragmarx/google2fa-laravel`
- [ ] Publicar configuracion
- [ ] Configurar `config/google2fa.php`

#### 5.2 Migracion Campos 2FA (1h)
- [ ] Crear migracion: `xxxx_add_two_factor_to_users.php`
- [ ] Agregar campos: two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at
- [ ] Ejecutar migracion

#### 5.3 Two Factor Controller (4h)
- [ ] Crear `app/Http/Controllers/Api/TwoFactorController.php`
- [ ] Implementar enable: genera QR code
- [ ] Implementar confirm: verifica codigo y activa
- [ ] Implementar disable: desactiva 2FA
- [ ] Generar recovery codes

#### 5.4 Two Factor Service (3h)
- [ ] Crear `app/Services/TwoFactorService.php`
- [ ] Logica de generacion de secreto
- [ ] Logica de verificacion de codigo
- [ ] Logica de recovery codes

#### 5.5 Modificar Login Flow (2h)
- [ ] Actualizar AuthController para check 2FA
- [ ] Crear endpoint two-factor-challenge
- [ ] Implementar flujo: login -> 2FA check -> complete login
- [ ] Crear `app/Http/Requests/Auth/TwoFactorRequest.php`

#### 5.6 Tests (1h)
- [ ] Crear `tests/Feature/Api/TwoFactorTest.php`
- [ ] Test habilitar 2FA
- [ ] Test login con 2FA
- [ ] Test recovery codes

### Archivos Afectados

**Nuevos:**
```
app/Http/Controllers/Api/TwoFactorController.php
app/Http/Requests/Auth/TwoFactorRequest.php
app/Services/TwoFactorService.php
database/migrations/xxxx_add_two_factor_to_users.php
config/google2fa.php
tests/Feature/Api/TwoFactorTest.php
```

**Modificados:**
```
app/Models/User.php
app/Http/Controllers/Api/AuthController.php
routes/api.php
```

### Criterios de Aceptacion
- [ ] Usuario puede habilitar 2FA con QR code
- [ ] Login requiere codigo cuando 2FA activo
- [ ] Recovery codes funcionan
- [ ] Usuario puede deshabilitar 2FA

### Dependencias para Siguiente Fase
- 2FA funcionando completamente
- Flujo de login actualizado

---

## FASE 6: Service Layer y Repository Pattern

### Objetivo
Refactorizar codigo existente para implementar arquitectura Clean con capas de servicio y repositorios.

### Prerequisitos
- Fases 1-5 completadas
- Tests existentes pasando

### Tareas

#### 6.1 Estructura de Carpetas (1h)
- [ ] Crear estructura `app/Services/`
- [ ] Crear estructura `app/Repositories/Contracts/`
- [ ] Crear estructura `app/Repositories/Eloquent/`

#### 6.2 Repository Interfaces (3h)
- [ ] Crear `UserRepositoryInterface.php`
- [ ] Crear `RoleRepositoryInterface.php`
- [ ] Definir contratos: findById, findByEmail, create, update, delete, paginate

#### 6.3 Eloquent Repositories (4h)
- [ ] Crear `UserRepository.php` implementando interface
- [ ] Crear `RoleRepository.php` implementando interface
- [ ] Implementar todos los metodos

#### 6.4 Service Provider (2h)
- [ ] Crear `app/Providers/RepositoryServiceProvider.php`
- [ ] Registrar bindings de interfaces a implementaciones
- [ ] Registrar provider en `config/app.php`

#### 6.5 User Service (3h)
- [ ] Crear `app/Services/User/UserService.php`
- [ ] Mover logica de negocio de controllers
- [ ] Inyectar repositorios
- [ ] Implementar transacciones DB

#### 6.6 Auth Service (2h)
- [ ] Crear `app/Services/Auth/AuthService.php`
- [ ] Crear `app/Services/Auth/PasswordResetService.php`
- [ ] Refactorizar AuthController

#### 6.7 Refactorizar Controllers (1h)
- [ ] Adelgazar UserController
- [ ] Adelgazar AuthController
- [ ] Controllers solo manejan HTTP

### Archivos Afectados

**Nuevos:**
```
app/Repositories/Contracts/UserRepositoryInterface.php
app/Repositories/Contracts/RoleRepositoryInterface.php
app/Repositories/Eloquent/UserRepository.php
app/Repositories/Eloquent/RoleRepository.php
app/Providers/RepositoryServiceProvider.php
app/Services/User/UserService.php
app/Services/Auth/AuthService.php
app/Services/Auth/PasswordResetService.php
```

**Modificados:**
```
app/Http/Controllers/Api/UserController.php
app/Http/Controllers/Api/AuthController.php
config/app.php
```

### Criterios de Aceptacion
- [ ] Todos los tests existentes siguen pasando
- [ ] Controllers tienen < 20 lineas por metodo
- [ ] Business logic en Services
- [ ] Data access en Repositories
- [ ] Dependency Injection funcionando

### Dependencias para Siguiente Fase
- Arquitectura limpia establecida
- Servicios inyectables

---

## FASE 7: Activity Logs y Auditoria

### Objetivo
Implementar sistema de auditoria para rastrear cambios en el sistema.

### Prerequisitos
- Fase 6 completada
- Service Layer funcionando

### Tareas

#### 7.1 Instalacion Spatie Activity Log (1h)
- [ ] Ejecutar `composer require spatie/laravel-activitylog`
- [ ] Publicar migracion y configuracion
- [ ] Ejecutar migracion

#### 7.2 Configuracion de Modelos (2h)
- [ ] Agregar trait LogsActivity a User
- [ ] Configurar atributos a loguear
- [ ] Configurar log name por modelo

#### 7.3 Activity Log Controller (2h)
- [ ] Crear `app/Http/Controllers/Api/ActivityLogController.php`
- [ ] Implementar endpoint GET /activity-logs con filtros
- [ ] Paginacion y busqueda

#### 7.4 Integracion con Services (2h)
- [ ] Agregar logging manual en UserService
- [ ] Agregar logging en AuthService (login, logout)
- [ ] Agregar logging en operaciones criticas

#### 7.5 Tests (1h)
- [ ] Crear `tests/Feature/Api/ActivityLogTest.php`
- [ ] Test que cambios generan logs
- [ ] Test que admin puede ver logs

### Archivos Afectados

**Nuevos:**
```
app/Http/Controllers/Api/ActivityLogController.php
tests/Feature/Api/ActivityLogTest.php
```

**Modificados:**
```
app/Models/User.php
app/Services/User/UserService.php
app/Services/Auth/AuthService.php
routes/api.php
config/activitylog.php
```

### Criterios de Aceptacion
- [ ] Cambios en usuarios generan logs
- [ ] Login/logout se registran
- [ ] Admin puede ver historial de actividad
- [ ] Logs incluyen quien, que, cuando

### Dependencias para Siguiente Fase
- Activity logging funcionando

---

## FASE 8: Optimizacion y Documentacion

### Objetivo
Optimizar rendimiento, completar documentacion y preparar para produccion.

### Prerequisitos
- Fases 1-7 completadas
- Sistema funcionando end-to-end

### Tareas

#### 8.1 Query Optimization (3h)
- [ ] Agregar indices a tablas segun uso
- [ ] Implementar eager loading en queries
- [ ] Revisar N+1 queries
- [ ] Configurar query caching donde apropiado

#### 8.2 API Documentation (3h)
- [ ] Instalar `darkaonline/l5-swagger`
- [ ] Documentar endpoints de Auth
- [ ] Documentar endpoints de Users
- [ ] Documentar endpoints de Profile
- [ ] Generar Swagger UI

#### 8.3 Configuracion de Produccion (2h)
- [ ] Configurar `config/cache.php` para Redis (opcional)
- [ ] Configurar queue driver
- [ ] Revisar configuraciones de seguridad
- [ ] Crear comandos de deploy: `php artisan optimize`

#### 8.4 Code Quality (2h)
- [ ] Ejecutar Laravel Pint para formato
- [ ] Ejecutar `composer audit`
- [ ] Revisar y resolver warnings de PHPStan (opcional)
- [ ] Asegurar 70%+ code coverage

### Archivos Afectados

**Nuevos:**
```
storage/api-docs/api-docs.json
config/l5-swagger.php
```

**Modificados:**
```
Multiples archivos con anotaciones Swagger
database/migrations/* (indices)
config/cache.php
config/queue.php
```

### Criterios de Aceptacion
- [ ] 0 vulnerabilidades en `composer audit`
- [ ] Documentacion Swagger accesible en /api/documentation
- [ ] Query time < 100ms en endpoints principales
- [ ] Code coverage >= 70%

### Dependencias para Siguiente Fase
- Backend listo para produccion
- API documentada

---

## Diagrama de Dependencias entre Fases

```
FASE 1 (Seguridad + Testing)
    │
    ├──────────────────┐
    ▼                  ▼
FASE 2 (Roles)    FASE 3 (Email)
    │                  │
    └────────┬─────────┘
             ▼
        FASE 4 (Profile)
             │
             ▼
        FASE 5 (2FA)
             │
             ▼
        FASE 6 (Service Layer)
             │
             ▼
        FASE 7 (Activity Logs)
             │
             ▼
        FASE 8 (Optimizacion)
```

---

## Comandos Utiles por Fase

```bash
# Fase 1
./vendor/bin/phpunit --filter=Auth

# Fase 2
composer require spatie/laravel-permission
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder

# Fase 3
php artisan make:notification VerifyEmailNotification

# Fase 4
php artisan make:model UserProfile -m

# Fase 5
composer require pragmarx/google2fa-laravel

# Fase 6
php artisan make:provider RepositoryServiceProvider

# Fase 7
composer require spatie/laravel-activitylog

# Fase 8
composer require darkaonline/l5-swagger
./vendor/bin/pint
composer audit
```

---

**Fin del Documento de Fases Backend**

*Generado por Claude Code - Architect Agent*
*Fecha: 2026-01-20*
