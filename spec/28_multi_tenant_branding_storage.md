---
tipo: spec
numero: 28
modulo: Documentos y Storage
dominio: DOCS
estado: DONE
tags: [spec, documentos, done]
relacionados:
  - "[[27_file_manager_system|Spec 27]]"
  - "[[30_cloud_folder_sync_por_tenant|Spec 30]]"
---
# [[28_multi_tenant_branding_storage|Spec 28]]: Multi-Tenant — Branding Dinamico y Configuracion por Tenant

## Informacion del Documento

- **Fecha:** 2026-03-21
- **Tipo:** Analisis de Impacto Tecnico y Plan de Implementacion
- **Scope:** Arquitectura Multi-tenant (Tenancy by Database) con branding dinamico, almacenamiento configurable y gestion de credenciales por tenant
- **Estado:** Completo - Fases 1-5 implementadas (20/20 tareas)

---

## 1. Resumen Ejecutivo

Este documento analiza el impacto tecnico de completar la implementacion multi-tenant del sistema. El proyecto ya posee una **base solida**: tabla `tenants`, trait `BelongsToTenant`, `TenantScope`, middleware, 10 modelos con aislamiento, OAuth credentials por tenant y `StorageProviderFactory` dinamico.

Sin embargo, existen gaps criticos que impiden que el multi-tenant sea **visible y funcional** para el usuario final: el frontend no carga datos del tenant tras autenticacion, el branding esta cableado pero dormido, no hay UI para gestionar settings/branding, y no existe upload de logo.

### Objetivo

El rol **Administrador** debe poder gestionar por cada Tenant:
- **Branding:** Logo (archivo fisico) y nombre de la compania
- **Almacenamiento:** Seleccion dinamica del tipo de storage (Local, OneDrive, Google Drive)
- **Credenciales API:** client_id/client_secret para Google y Microsoft por tenant
- **Ajustes:** Campo `settings` (JSON) para configuraciones miscelaneas
- **Theme Customizer:** Parametros graficos de la plantilla, con opcion de desactivar

---

## 2. Estado Actual del Multi-Tenant

### 2.1 Infraestructura Implementada

| Componente | Estado | Archivos |
|-----------|--------|----------|
| Tabla `tenants` (name, slug, settings, OAuth creds, storage_type, quota) | Completo | `migrations/2026_01_01_000001` + 2 additive |
| Modelo `Tenant` con secrets encriptados (`Crypt::encryptString`) | Completo | `app/Models/Tenant.php` |
| `users.tenant_id` FK nullable (super-admin sin tenant) | Completo | `migrations/2026_01_01_000002` |
| Trait `BelongsToTenant` + `TenantScope` global scope | Completo | `app/Models/Traits/BelongsToTenant.php`, `app/Models/Scopes/TenantScope.php` |
| `TenantMiddleware` (valida tenant activo) | Completo | `app/Http/Middleware/TenantMiddleware.php` |
| 10 modelos con trait (Client, Case, Document, etc.) | Completo | `app/Models/` |
| `OAuthCredentialService` (tenant → system fallback) | Completo | `app/Services/OAuthCredentialService.php` |
| `StorageProviderFactory` (resuelve por `tenant.storage_type`) | Completo | `app/Services/Storage/StorageProviderFactory.php` |
| `TenantController` (show, settings, branding) | Completo | `app/Http/Controllers/Api/TenantController.php` |
| `TenantOAuthController` (CRUD credenciales por tenant) | Completo | `app/Http/Controllers/Api/TenantOAuthController.php` |
| Pinia `useTenantStore` con TypeScript | Completo | `resources/js/src/stores/tenant.ts` |
| `TenantLogo.vue` (logo/nombre del store) | Completo | `resources/js/src/components/layout/TenantLogo.vue` |
| `OAuthSettings.vue` (admin credenciales) | Completo | `resources/js/src/views/admin/tenant/OAuthSettings.vue` |

### 2.2 Gaps Identificados

| # | Gap | Severidad | Categoria |
|---|-----|-----------|-----------|
| G1 | `fetchTenant()` nunca se invoca — branding dormido | Critico | Frontend |
| G2 | CSS variables `--tenant-primary` no conectadas a Tailwind | Alto | Frontend |
| G3 | No hay vista de Settings/Branding del tenant | Alto | Frontend |
| G4 | No hay endpoint de upload de logo | Alto | Backend/API |
| G5 | Registro auto-asigna primer tenant activo | Alto | Backend |
| G6 | No hay CRUD de tenants para super-admin | Alto | Backend/API |
| G7 | `CaseType` no usa `BelongsToTenant` | Alto | Backend |
| G8 | ThemeCustomizer no conectado a tenant settings | Medio | Frontend |
| G9 | `Gate::before` no incluye `super-admin` | Medio | Backend |
| G10 | Activity logs no registran `tenant_id` | Medio | Backend |
| G11 | No hay opcion para desactivar ThemeCustomizer | Bajo | Frontend |
| G12 | Policies sin validacion explicita de `tenant_id` | Bajo | Backend |

---

## 3. Analisis por Componente

### 3.1 Backend (Laravel)

#### 3.1.1 Base de Datos

**Tabla `tenants` — ya existe con este esquema:**

```
id, name, slug (unique), settings (JSON nullable),
ms_client_id, ms_client_secret, google_client_id, google_client_secret,
storage_type (default 'local'), storage_quota_mb (default 5000),
storage_used_bytes (default 0), is_active, timestamps
```

**Migracion adicional requerida:**

```php
// add_logo_and_theme_to_tenants_table
Schema::table('tenants', function (Blueprint $table) {
    $table->string('logo_path')->nullable()->after('slug');
});
```

**Justificacion:** El logo actualmente se guarda como URL en `settings.logo_url` (JSON). Moverlo a una columna dedicada simplifica queries y permite validacion a nivel DB. Alternativamente, mantenerlo en `settings` es viable si se prefiere no migrar.

**Campo `settings` — estructura JSON recomendada:**

```json
{
    "company_name": "Firma Legal ABC",
    "timezone": "America/New_York",
    "date_format": "MM/DD/YYYY",
    "language": "es",
    "branding": {
        "primary_color": "#4361ee",
        "secondary_color": "#805dca",
        "logo_url": "/storage/tenants/firma-legal/logo.png"
    },
    "theme": {
        "mode": "light",
        "menu": "vertical",
        "layout": "full",
        "rtl_class": "ltr",
        "animation": "animate__fadeIn",
        "navbar": "navbar-sticky",
        "semidark": false,
        "show_customizer": true
    },
    "features": {
        "documents_enabled": true,
        "scrum_enabled": true,
        "calendar_enabled": true
    }
}
```

#### 3.1.2 Modelos

**Relacion User ↔ Tenant ↔ Role:**

```
Tenant (1) ──→ (N) User
User (N) ──→ (N) Role (via Spatie model_has_roles)
Tenant (1) ──→ (N) Client, Case, Document, Event, Todo, etc.
```

**Modelo `CaseType` — decision requerida:**

Actualmente tiene `tenant_id` en fillable y migracion pero NO usa `BelongsToTenant`. Opciones:

| Opcion | Pros | Contras |
|--------|------|---------|
| A. Agregar trait → types por tenant | Aislamiento total | Cada tenant debe crear sus propios types |
| B. Mantener sin trait → types globales | Un solo catalogo compartido | No se pueden personalizar por tenant |
| C. Hibrido: `tenant_id` nullable, scope condicional | Flexibilidad maxima | Mayor complejidad en queries |

**Recomendacion:** Opcion C — case types con `tenant_id = null` son globales (sembrados por seeder), con `tenant_id` son personalizados por tenant. Query: `where tenant_id = {current} OR tenant_id IS NULL`.

#### 3.1.3 Service Providers — Runtime Config

**Estado actual:** `AppServiceProvider` no tiene logica de tenant. La resolucion es lazy (cada servicio lee `Auth::user()->tenant` cuando lo necesita).

**Se requiere para S3 futuro:**

```php
// AppServiceProvider::boot()
// Solo si se agrega soporte S3 por tenant:
$this->app->resolving('filesystem', function ($factory) {
    $tenant = Auth::user()?->tenant;
    if ($tenant && $tenant->storage_type === 's3') {
        config([
            'filesystems.disks.s3.key' => $tenant->getSetting('s3_access_key'),
            'filesystems.disks.s3.secret' => $tenant->getSetting('s3_secret_key'),
            'filesystems.disks.s3.region' => $tenant->getSetting('s3_region'),
            'filesystems.disks.s3.bucket' => $tenant->getSetting('s3_bucket'),
        ]);
    }
});
```

**Para OneDrive/Google Drive:** No se necesita runtime config — `StorageProviderFactory` ya resuelve el provider correcto usando los tokens OAuth del usuario autenticado.

#### 3.1.4 Seguridad — Cifrado de Secrets

**Ya implementado.** El modelo `Tenant` usa mutators con `Crypt::encryptString()`:

```php
// SET: encripta antes de guardar
public function setMsClientSecretAttribute(?string $value): void
{
    $this->attributes['ms_client_secret'] = $value ? Crypt::encryptString($value) : null;
}

// GET: desencripta al leer
public function getMsClientSecretAttribute(?string $value): ?string
{
    return $value ? Crypt::decryptString($value) : null;
}
```

Ademas, `$hidden = ['ms_client_secret', 'google_client_secret']` previene serializacion accidental en API responses.

---

### 3.2 Frontend (Vue.js)

#### 3.2.1 Estado Global — Activar Tenant Store

**Problema critico:** `fetchTenant()` existe en `stores/tenant.ts` pero nunca se invoca.

**Solucion:** Invocar en el flujo de autenticacion:

```typescript
// stores/auth.ts — despues de fetchUser() exitoso
async function fetchUser() {
    const response = await axios.get('/api/user');
    user.value = response.data;

    // Cargar datos del tenant
    const tenantStore = useTenantStore();
    await tenantStore.fetchTenant();
}
```

**Impacto:** Al invocar `fetchTenant()`, automaticamente:
- `TenantLogo.vue` mostrara el logo y nombre reales
- `applyBranding()` seteara `--tenant-primary` y `--tenant-secondary` como CSS custom properties

#### 3.2.2 Conectar CSS Variables con Tailwind

**Archivo:** `tailwind.config.cjs`

```javascript
// Antes (hardcoded):
primary: '#4361ee',

// Despues (dinamico con fallback):
primary: 'var(--tenant-primary, #4361ee)',
secondary: 'var(--tenant-secondary, #805dca)',
```

**Riesgo:** Tailwind genera clases en build time. Si `var()` no es soportado en algun contexto (opacity modifiers como `bg-primary/50`), se necesita una estrategia alternativa con `rgb()`:

```javascript
primary: {
    DEFAULT: 'rgb(var(--tenant-primary-rgb, 67 97 238) / <alpha-value>)',
}
```

Esto requiere que `applyBranding()` setee los valores como RGB sin el `#`:
```typescript
document.documentElement.style.setProperty('--tenant-primary-rgb', '67 97 238');
```

#### 3.2.3 Vista de Configuracion del Tenant

**Crear 2 nuevas vistas:**

**A. `TenantSettings.vue`** — Configuracion general

| Campo | Tipo | Mapeo |
|-------|------|-------|
| Nombre de compania | text input | `settings.company_name` |
| Timezone | select | `settings.timezone` |
| Formato de fecha | select | `settings.date_format` |
| Idioma por defecto | select | `settings.language` |
| Mostrar Theme Customizer | toggle | `settings.theme.show_customizer` |

**B. `TenantBranding.vue`** — Branding visual

| Campo | Tipo | Mapeo |
|-------|------|-------|
| Logo | file upload (image) | `POST /api/tenant/branding/logo` |
| Color primario | color picker | `settings.branding.primary_color` |
| Color secundario | color picker | `settings.branding.secondary_color` |
| Preview en vivo | render components | Aplica colores en tiempo real |

#### 3.2.4 ThemeCustomizer — Conectar a Tenant

**Archivo:** `resources/js/src/components/ThemeCustomizer.vue`

**Estado actual:** Lee/escribe en localStorage via `stores/index.ts`. Los parametros son: `theme` (light/dark/system), `menu`, `layout`, `rtlClass`, `animation`, `navbar`, `semidark`.

**Cambio requerido:**

1. Al guardar una preferencia en el ThemeCustomizer, ademas de localStorage, guardar en `tenant.settings.theme` via API
2. Al cargar la app, priorizar `tenant.settings.theme` sobre localStorage
3. Si `settings.theme.show_customizer === false`, ocultar el boton del customizer

**Flujo de prioridad de tema:**

```
tenant.settings.theme (API) → localStorage (override local) → defaults
```

#### 3.2.5 Desactivar ThemeCustomizer

**Archivo:** `resources/js/src/App.vue`

```vue
<!-- Antes -->
<ThemeCustomizer />

<!-- Despues -->
<ThemeCustomizer v-if="tenantStore.branding?.show_customizer !== false" />
```

---

### 3.3 API & Almacenamiento

#### 3.3.1 Cambio Dinamico de Disk

**Ya resuelto.** `StorageProviderFactory` lee `tenant.storage_type` y retorna el provider correcto:

```php
return match ($type) {
    'onedrive' => app(OneDriveProvider::class),   // usa OAuth tokens del user
    'google_drive' => app(GoogleDriveProvider::class), // usa OAuth tokens del user
    default => app(LocalStorageProvider::class),   // usa Storage::disk('local')
};
```

**Endpoint faltante para cambiar storage_type:**

```
PUT /api/tenant/storage
Body: { "storage_type": "onedrive" }
Validacion: Solo admin. Verificar que existen credenciales OAuth para el provider seleccionado.
```

#### 3.3.2 Callbacks OAuth Multi-Tenant

**Ya resuelto.** `OAuthFlowController` usa `state` parameter con session binding:

```php
// redirect(): genera URL con state = random token
session(['oauth_state' => $state, 'oauth_user_id' => $user->id]);

// callback(): valida state, recupera user_id de session
// El user ya tiene tenant_id, so los tokens se guardan con tenant scope
```

No se necesitan rutas de callback por tenant — una sola ruta `/api/oauth/{provider}/callback` maneja todos los tenants.

---

### 3.4 Cambio de Logotipo

#### 3.4.1 Estrategia de Almacenamiento

| Aspecto | Decision | Justificacion |
|---------|----------|---------------|
| **Ubicacion** | `storage/app/public/tenants/{slug}/logo.{ext}` | Accesible via URL publica con symlink `storage:link` |
| **URL publica** | `/storage/tenants/{slug}/logo.png` | Necesario para login page (pre-auth) |
| **Formatos** | JPG, PNG, SVG, WebP | Max 2MB |
| **Dimensiones** | Max 500x500px | Resize en backend con Intervention Image (si disponible) o validacion |
| **Cache-busting** | Append `?v={timestamp}` a la URL | Forzar recarga al actualizar |

#### 3.4.2 Endpoint de Upload

```
POST /api/tenant/branding/logo
Content-Type: multipart/form-data
Body: { logo: File }
Response: { logo_url: "/storage/tenants/firma-legal/logo.png?v=1711036800" }
```

**Logica del controller:**

```php
public function uploadLogo(Request $request)
{
    $request->validate(['logo' => 'required|image|mimes:jpg,jpeg,png,svg,webp|max:2048']);

    $tenant = auth()->user()->tenant;
    $path = $request->file('logo')->storeAs(
        "tenants/{$tenant->slug}",
        'logo.' . $request->file('logo')->extension(),
        'public'
    );

    $logoUrl = Storage::disk('public')->url($path) . '?v=' . time();
    $tenant->setSetting('branding.logo_url', $logoUrl)->save();

    return response()->json(['logo_url' => $logoUrl]);
}
```

#### 3.4.3 Impacto en Cache del Navegador

- `TenantLogo.vue` ya lee `logoUrl` del store
- Al actualizar, el store se refresca con la nueva URL que incluye `?v=timestamp`
- El navegador trata `logo.png?v=1` y `logo.png?v=2` como recursos distintos → cache invalidado

---

## 4. Archivos a Modificar / Crear

### 4.1 Archivos Nuevos

| Archivo | Proposito |
|---------|-----------|
| `database/migrations/2026_03_21_000004_add_logo_path_to_tenants.php` | Columna logo_path (opcional si se usa settings) |
| `resources/js/src/views/admin/tenant/TenantSettings.vue` | Vista settings generales |
| `resources/js/src/views/admin/tenant/TenantBranding.vue` | Vista branding (logo, colores) |
| `resources/js/src/services/tenantService.ts` | Servicio API centralizado para tenant |

### 4.2 Archivos a Modificar

| Archivo | Cambio |
|---------|--------|
| `app/Http/Controllers/Api/TenantController.php` | Agregar `uploadLogo()`, `updateStorageType()` |
| `app/Models/CaseType.php` | Decidir e implementar estrategia de tenant scoping |
| `app/Providers/AuthServiceProvider.php` | Agregar `super-admin` a `Gate::before` |
| `app/Services/Auth/AuthService.php` | Corregir registro (no auto-asignar primer tenant) |
| `resources/js/src/stores/auth.ts` | Invocar `fetchTenant()` tras autenticacion |
| `resources/js/src/stores/tenant.ts` | Agregar action para upload logo y update storage type |
| `resources/js/src/components/ThemeCustomizer.vue` | Persistir cambios en tenant settings |
| `resources/js/src/App.vue` | Condicionar render del ThemeCustomizer |
| `resources/js/src/app-setting.ts` | Cargar tema desde tenant settings con fallback a localStorage |
| `tailwind.config.cjs` | CSS variables para primary/secondary con fallback |
| `resources/js/src/router/index.ts` | Agregar rutas para nuevas vistas admin |
| `resources/js/src/components/layout/Sidebar.vue` | Agregar menu items para tenant admin |
| `resources/js/src/locales/en.json` | Traducciones tenant settings/branding |
| `resources/js/src/locales/es.json` | Traducciones tenant settings/branding |
| `resources/js/src/locales/fr.json` | Traducciones tenant settings/branding |

### 4.3 Posibles Breaking Changes

| Cambio | Riesgo | Mitigacion |
|--------|--------|------------|
| Agregar `BelongsToTenant` a `CaseType` | Case types existentes sin `tenant_id` se vuelven invisibles | Migrar datos: asignar `tenant_id` a existentes o mantener `null` como global |
| Conectar CSS variables a Tailwind | Opacity modifiers (`bg-primary/50`) pueden fallar con `var()` | Usar formato RGB con `<alpha-value>` |
| Corregir flujo de registro | Registro abierto deja de funcionar | Implementar invitation codes o seleccion de tenant |
| Priorizar tema de tenant sobre localStorage | Usuarios pierden personalizaciones locales | Migrar localStorage a tenant settings en primera carga |

---

## 5. Plan de Implementacion

### Fase 1: Activacion del Branding (Frontend Critical Path)

| # | Tarea | Archivos | Estado |
|---|-------|----------|--------|
| 1.1 | ~~Invocar `fetchTenant()` en flujo de auth~~ | `stores/auth.ts` | [x] login, fetchUser, verifyTwoFactor, logout |
| 1.2 | ~~Conectar CSS variables con Tailwind (formato RGB)~~ | `tailwind.config.cjs`, `stores/tenant.ts` | [x] RGB `<alpha-value>` pattern |
| 1.3 | ~~Crear `tenantService.ts`~~ | `services/tenantService.ts` | [x] 7 metodos API |
| 1.4 | ~~Agregar traducciones i18n para tenant admin~~ | `locales/en.json`, `es.json`, `fr.json` | [x] 27+ keys en 3 idiomas |

### Fase 2: Vistas de Administracion

| # | Tarea | Archivos | Estado |
|---|-------|----------|--------|
| 2.1 | ~~Crear `TenantSettings.vue`~~ | `views/admin/tenant/TenantSettings.vue` | [x] company name, timezone, date format, language, customizer toggle |
| 2.2 | ~~Crear `TenantBranding.vue`~~ | `views/admin/tenant/TenantBranding.vue` | [x] logo upload/delete, color pickers, storage type |
| 2.3 | ~~Endpoint `POST /api/tenant/branding/logo`~~ | `TenantController.php` | [x] upload + delete + cache-busting |
| 2.4 | ~~Endpoint `PUT /api/tenant/storage-type`~~ | `TenantController.php` | [x] validacion in:local,onedrive,google_drive |
| 2.5 | ~~Registrar rutas y menu sidebar para admin tenant~~ | `router/index.ts`, `Sidebar.vue` | [x] 3 rutas + submenu con permiso |

### Fase 3: ThemeCustomizer Conectado a Tenant

| # | Tarea | Archivos | Estado |
|---|-------|----------|--------|
| 3.1 | ~~Persistir preferencias de tema en `tenant.settings.theme`~~ | `ThemeCustomizer.vue`, `stores/tenant.ts` | [x] debounced save 1s |
| 3.2 | ~~Cargar tema desde tenant settings al iniciar~~ | `app-setting.ts` | [x] localStorage → tenant → themeConfig |
| 3.3 | ~~Toggle para desactivar ThemeCustomizer~~ | `app-layout.vue`, `TenantSettings.vue` | [x] v-if showCustomizer |

### Fase 4: Correccion de Gaps Backend

| # | Tarea | Archivos | Estado |
|---|-------|----------|--------|
| 4.1 | ~~Estrategia [[CaseType]] hibrido~~ | `CaseType.php` | [x] global scope tenant_or_global + auto-assign |
| 4.2 | ~~Agregar `super-admin` a `Gate::before`~~ | `AuthServiceProvider.php` | [x] |
| 4.3 | ~~Corregir registro: requerir invitation code~~ | `AuthService.php`, `InvitationCode.php`, migration | [x] invitation_codes table, model con isValid/redeem, RegisterRequest requiere code |
| 4.4 | ~~CRUD de tenants para super-admin~~ | `Admin\TenantController.php`, `TenantPolicy.php`, routes | [x] 7 endpoints: index, store, show, update, destroy, activate, stats |
| 4.5 | ~~Activity logs con `tenant_id`~~ | `Activity.php`, migration, config | [x] custom Activity model, auto-populate tenant_id, scoping en controller |
| 4.6 | ~~Endpoint `PUT /api/tenant/theme`~~ | `TenantController.php`, `TenantService.php` | [x] 8 parametros validados |
| 4.7 | ~~Theme en TenantResource~~ | `TenantResource.php` | [x] con defaults |

### Fase 5: Testing y Hardening

| # | Tarea | Estado |
|---|-------|--------|
| 5.1 | ~~Tests de aislamiento: tenant A no ve datos de tenant B~~ | [x] TenantIsolationTest (4 tests) + AdminTenantTest (9 tests) |
| 5.2 | ~~Tests de branding: CSS variables y colores~~ | [x] TenantBrandingTest (8 tests) |
| 5.3 | ~~Tests de logo upload: formatos, tamano, cache-busting~~ | [x] incluido en TenantBrandingTest |
| 5.4 | ~~Tests de ThemeCustomizer: persistencia en tenant settings~~ | [x] incluido en TenantBrandingTest |
| 5.5 | ~~Test de registro con invitation code~~ | [x] InvitationCodeRegistrationTest (8 tests) |

---

## 6. Dependencias y Prerequisitos

### Paquetes Existentes (no se requieren nuevos)

| Paquete | Uso | Estado |
|---------|-----|--------|
| `spatie/laravel-permission` | Roles y permisos por tenant | Ya instalado |
| `laravel/sanctum` | Auth tokens | Ya instalado |
| `spatie/laravel-activitylog` | Audit logs | Ya instalado |

### Paquetes Opcionales

| Paquete | Uso | Necesario |
|---------|-----|-----------|
| `intervention/image` | Resize de logo en upload | Recomendado pero no critico |

---

## 7. Riesgos y Mitigaciones

| # | Riesgo | Probabilidad | Impacto | Mitigacion |
|---|--------|-------------|---------|------------|
| R1 | CSS variables con Tailwind opacity modifiers fallan | Media | Alto | Usar formato RGB con `<alpha-value>` |
| R2 | `fetchTenant()` en cada page load agrega latencia | Baja | Bajo | Cache en store, solo fetch si no hay datos |
| R3 | Registro abierto permite crear usuarios sin tenant valido | Alta | Alto | Requerir invitation code o deshabilitar registro publico |
| R4 | Logo upload expone path traversal | Baja | Critico | Usar `storeAs()` con slug sanitizado, no input de usuario |
| R5 | Cambiar storage_type con documentos existentes en otro provider | Media | Alto | Advertencia en UI, no migrar archivos automaticamente |
| R6 | ThemeCustomizer overwrite: tenant admin cambia tema y afecta todos los usuarios | Media | Medio | Separar preferencias de usuario (localStorage) vs tenant defaults |
| R7 | CaseType hibrido: queries con `OR tenant_id IS NULL` afectan performance | Baja | Bajo | Index compuesto en `(tenant_id, id)` |

---

## 8. Estimacion de Complejidad

| Fase | Componentes | Complejidad |
|------|------------|-------------|
| Fase 1: Activacion branding | 4 archivos | Baja |
| Fase 2: Vistas admin | 5 archivos nuevos + 4 modificados | Media |
| Fase 3: ThemeCustomizer | 3 archivos | Media |
| Fase 4: Gaps backend | 5 archivos | Media-Alta |
| Fase 5: Testing | Tests nuevos | Media |
