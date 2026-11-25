---
tipo: spec
numero: 34
modulo: Autenticación y Seguridad
dominio: AUTH
estado: DONE
tags: [spec, auth, done]
relacionados:
  - "[[53_sesion_unica_por_usuario|Spec 53]]"
---
# [[34_security_hardening|Spec 34]]: Security Hardening — Remediación de Vulnerabilidades

## Información del Documento

- **Fecha:** 2026-03-31
- **Tipo:** Análisis de Impacto de Seguridad y Plan de Remediación
- **Scope:** Fortalecer autenticación, autorización, cifrado de datos sensibles, sanitización de inputs y headers de seguridad
- **Dependencias:** [[27_file_manager_system|Spec 27]] (File Manager), [[28_multi_tenant_branding_storage|Spec 28]] (Multi-Tenant), [[30_cloud_folder_sync_por_tenant|Spec 30]] (Cloud Folder Sync), [[33_sharepoint_storage_provider|Spec 33]] (SharePoint)
- **Estado:** Implementado
- **Metodología:** OWASP Top 10 2021, STRIDE, análisis estático de código fuente

---

## 1. Resumen Ejecutivo

Evaluación de seguridad integral de la aplicación VITE-IT Immigration que maneja datos altamente sensibles (pasaportes, documentos legales, transacciones financieras) en una arquitectura multi-tenant con integraciones cloud (OneDrive, Google Drive, SharePoint).

Se identificaron **12 hallazgos** calificados por severidad: 1 crítico, 3 altos, 7 medios y 1 bajo. La aplicación cuenta con una base de seguridad sólida ([[TenantScope]], cifrado de secrets, rate limiting, path traversal protection), pero requiere refuerzo en áreas específicas.

### Controles Positivos Existentes

| Control | Implementación | Archivo(s) |
|---------|----------------|------------|
| Tenant isolation (Global Scope) | `TenantScope` filtra queries por tenant_id automáticamente | `app/Models/Scopes/TenantScope.php` |
| Tenant isolation (Auto-assign) | `tenant_id` se asigna en evento `creating` | `app/Models/Traits/BelongsToTenant.php` |
| Secrets cifrados | `Crypt::encryptString` para ms_client_secret, google_client_secret | `app/Models/Tenant.php` |
| 2FA secret cifrado | Cast `encrypted` para two_factor_secret | `app/Models/User.php` |
| Rate limiting granular | 7 limiters (login, register, uploads, downloads, etc.) | `app/Providers/AppServiceProvider.php` |
| Path traversal protection | `validatePath()` con null byte check, traversal check, prefix check | `app/Services/Storage/LocalStorageProvider.php` |
| File type validation | Whitelist de MIME types | `app/Http/Requests/Document/StoreDocumentRequest.php` |
| Security headers | X-Content-Type-Options, X-Frame-Options, HSTS, Referrer-Policy | `app/Http/Middleware/SecurityHeaders.php` |
| OAuth state validation | State generado con `Str::random(40)`, validado via cache TTL 10min | `app/Http/Controllers/Api/OAuthFlowController.php` |
| Session management | Regenerate en login, invalidate en logout | `app/Services/Auth/AuthService.php` |
| Activity logging | Spatie Activitylog en User, Client, Case, Document | Múltiples modelos |
| Virus scanning | `ScanDocumentForVirus` job en uploads | `app/Services/Document/DocumentService.php` |
| Registration por invitation | Requiere invitation_code válido | `app/Services/Auth/AuthService.php` |

---

## 2. Hallazgos y Matriz de Riesgo

| ID | Título | Severidad | CVSS | OWASP |
|--------|--------------------------------------------------------------|-----------|------|-------|
| SEC-001 | OAuth tokens sin cifrado en reposo | Crítico | 8.5 | A02:2021 |
| SEC-002 | Endpoints de TenantOAuth/TenantController sin permisos de rol | Alto | 7.2 | A01:2021 |
| SEC-003 | ClientPolicy sin verificación de tenant_id (IDOR) | Alto | 7.5 | A01:2021 |
| SEC-004 | UserPolicy sin verificación de tenant_id (IDOR) | Alto | 7.5 | A01:2021 |
| SEC-005 | OAuth callback sin rate limiting | Medio | 5.3 | A07:2021 |
| SEC-006 | Content-Disposition header injection | Medio | 5.4 | CWE-113 |
| SEC-007 | v-html sin sanitización — XSS almacenado | Medio | 6.1 | A03:2021 |
| SEC-008 | `$request->all()` en updateTheme — mass assignment | Medio | 5.3 | A08:2021 |
| SEC-009 | Todo/Event/[[ScrumTask]] sin authorize() | Medio | 5.5 | A01:2021 |
| SEC-010 | bulkDestroy sin validación explícita de tenant | Medio | 5.5 | A01:2021 |
| SEC-011 | Ausencia de Content-Security-Policy (CSP) | Medio | 5.0 | A05:2021 |
| SEC-013 | passport_number en búsqueda LIKE — enumeración de PII | Bajo | 3.1 | A04:2021 |

---

## 3. Decisiones de Diseño

| # | Decisión | Justificación |
|---|----------|---------------|
| D1 | Usar `encrypted` cast en [[OauthToken]] en vez de cifrado manual | Consistente con el patrón de `two_factor_secret` en User.php. Laravel maneja cifrado/descifrado transparentemente |
| D2 | Middleware `role:admin` en rutas vs `authorize()` en controller | Middleware en rutas centraliza el control, es más visible y difícil de olvidar |
| D3 | DOMPurify para sanitización de v-html | Librería madura, 50M+ downloads/semana, estándar de la industria para XSS prevention en frontend |
| D4 | CSP sin `unsafe-eval` | Vue 3 no requiere `unsafe-eval` en producción (solo dev mode). Más seguro |
| D5 | Verificar tenant_id en Policies como defensa en profundidad | Aunque TenantScope global ya filtra, las Policies son la segunda barrera contra IDOR |

---

## 4. Plan de Implementación

### Fase 1: Cifrado de Tokens OAuth (Crítico) — SEC-001

| # | Tarea | Estado |
|---|-------|--------|
| 1.1 | Agregar cast `encrypted` a `access_token` y `refresh_token` en `OauthToken.php` | Hecho |
| 1.2 | Crear comando artisan para migrar tokens existentes en texto plano a cifrados | Hecho |
| 1.3 | Ejecutar migración de tokens (`php artisan tokens:encrypt`) | Pendiente (deploy) |

**Archivos a modificar:**
- `app/Models/OauthToken.php`

---

### Fase 2: Control de Acceso en Endpoints Sensibles (Alto) — SEC-002

| # | Tarea | Estado |
|---|-------|--------|
| 2.1 | Agrupar endpoints sensibles bajo middleware `role:admin` en `routes/api.php` | Hecho |
| 2.2 | Verificar que endpoints afectados retornan 403 para usuarios no-admin | Hecho |

**Archivos a modificar:**
- `routes/api.php`

**Endpoints a proteger:**
- `PUT /tenant/oauth/microsoft`
- `DELETE /tenant/oauth/microsoft`
- `PUT /tenant/oauth/google`
- `DELETE /tenant/oauth/google`
- `PUT /tenant/storage-type`
- `PUT /tenant/sharepoint/config`
- `PUT /tenant/base-folder`
- `PUT /tenant/theme`
- `GET /tenant/sharepoint/sites`
- `GET /tenant/sharepoint/drives`

---

### Fase 3: IDOR Protection en Policies (Alto) — SEC-003, SEC-004

| # | Tarea | Estado |
|---|-------|--------|
| 3.1 | Agregar verificación `$model->tenant_id === $user->tenant_id` en ClientPolicy | Hecho |
| 3.2 | Agregar verificación `$model->tenant_id === $user->tenant_id` en UserPolicy | Hecho |

**Archivos a modificar:**
- `app/Policies/ClientPolicy.php`
- `app/Policies/UserPolicy.php`

---

### Fase 4: Sanitización XSS en Frontend (Medio) — SEC-007

| # | Tarea | Estado |
|---|-------|--------|
| 4.1 | Instalar DOMPurify (`npm install dompurify @types/dompurify`) | Hecho |
| 4.2 | Crear utility `sanitizeHtml()` en `resources/js/src/utils/sanitize.ts` | Hecho |
| 4.3 | Reemplazar los 8 usos de `v-html` con versión sanitizada | Hecho |

**Archivos a modificar:**
- `resources/js/src/views/cases/components/CaseTodoTab.vue`
- `resources/js/src/views/apps/todolist.vue`
- `resources/js/src/components/layout/Header.vue`
- `resources/js/src/views/apps/mailbox.vue`

---

### Fase 5: Hardening de API (Medio) — SEC-005, SEC-006, SEC-008

| # | Tarea | Estado |
|---|-------|--------|
| 5.1 | Agregar throttle al endpoint de OAuth callback | Hecho |
| 5.2 | Sanitizar Content-Disposition headers con `HeaderUtils::makeDisposition()` | Hecho |
| 5.3 | Reemplazar `$request->all()` por `$request->validated()` en `updateTheme` | Hecho |

**Archivos a modificar:**
- `routes/api.php`
- `app/Services/Storage/MicrosoftGraphBaseProvider.php`
- `app/Services/Document/DocumentService.php`
- `app/Http/Controllers/Api/TenantController.php`

---

### Fase 6: Content-Security-Policy (Medio) — SEC-011

| # | Tarea | Estado |
|---|-------|--------|
| 6.1 | Agregar header CSP al middleware [[SecurityHeaders]] | Hecho |
| 6.2 | Configurar CSP para permitir CDN de fonts y conexiones a Graph API / Google API | Hecho |

**Archivos a modificar:**
- `app/Http/Middleware/SecurityHeaders.php`

---

### Fase 7: Policies para Todo, Event, ScrumTask (Medio) — SEC-009, SEC-010

| # | Tarea | Estado |
|---|-------|--------|
| 7.1 | Crear `TodoPolicy` con verificación de permisos y tenant_id | Hecho |
| 7.2 | Crear `EventPolicy` con verificación de permisos y tenant_id | Hecho |
| 7.3 | Integrar `authorize()` en `TodoController` | Hecho |
| 7.4 | Integrar `authorize()` en `EventController` | Hecho |

**Archivos a crear:**
- `app/Policies/TodoPolicy.php`
- `app/Policies/EventPolicy.php`

**Archivos a modificar:**
- `app/Http/Controllers/Api/TodoController.php`
- `app/Http/Controllers/Api/EventController.php`

---

### Fase 8: Mejora Continua (Bajo) — SEC-013

| # | Tarea | Estado |
|---|-------|--------|
| 8.1 | Remover `passport_number` del scope de búsqueda general | Hecho |

---

## 5. Estimación de Impacto

| Dimensión | Valor |
|-----------|-------|
| Archivos backend a modificar | ~12 |
| Archivos backend a crear | 2 (Policies) |
| Archivos frontend a modificar | 4 |
| Archivos frontend a crear | 1 (sanitize utility) |
| Dependencias nuevas | 1 (dompurify) |
| Migraciones | 0 (cambio en cast, no en schema) |
| Riesgo de regresión | Bajo — cambios son aditivos (middleware, policies, sanitización) |
| Impacto en usuarios | Ninguno visible — mejoras internas de seguridad |
