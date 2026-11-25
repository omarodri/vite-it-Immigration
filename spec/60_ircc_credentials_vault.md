---
tipo: spec
numero: 60
modulo: Autenticación y Seguridad
dominio: AUTH
estado: DONE
tags: [spec, auth, done]
relacionados:
  - "[[07_epic_2.1_expedientes_core|Spec 07]]"
  - "[[08_permisos_clientes_acompanantes_roles|Spec 08]]"
  - "[[34_security_hardening|Spec 34]]"
  - "[[53_sesion_unica_por_usuario|Spec 53]]"
  - "[[58_cliente_vs_aplicante_principal|Spec 58]]"
---
# [[60_ircc_credentials_vault|Spec 60]] — Pestaña "Información IRCC" con Bóveda de Credenciales

**Dominio:** AUTH / CASES  
**Estado:** DONE  
**Relacionados:** 07 (Core Expedientes), 08 (Permisos), 34 (Security Hardening), 53 (Sesión Única), 58 (Cliente vs Aplicante)  
**Creado:** 2026-05-15  
**Autor:** Arquitectura (análisis de ciberseguridad)

---

## Resumen

Implementar una pestaña oculta "Información IRCC" en la vista de detalle de expedientes (`cases/show.vue`) que permita almacenar credenciales críticas de acceso al portal del IRCC (Immigration, Refugees and Citizenship Canada) y cuentas de correo electrónico vinculadas. Todos los campos sensibles se encriptan en reposo. El acceso está restringido a rol `consultor` con permiso específico y se activa mediante un mecanismo de UX no obvio.

---

## Datos a Almacenar

### Grupo: Credenciales IRCC
- `ircc_username` — Usuario del portal IRCC
- `ircc_password` — Contraseña del portal IRCC

### Grupo: Correo Electrónico
- `email_address` — Dirección de email vinculada
- `email_password` — Contraseña del email
- `application_number` — Número de solicitud
- `notes` — Notas adicionales

### Grupo: Preguntas de Seguridad
- 5 filas fijas: `pregunta` (texto libre) + `respuesta` (texto sensible)
- Almacenadas como JSON encriptado (`encrypted:array`)

### Metadatos
- `created_by` — User ID del creador (automático)
- `updated_by` — User ID del último editor (automático)
- Timestamps estándar

---

## Decisiones Arquitectónicas

### A1 — Encriptación: Laravel `encrypted` cast (AES-256-CBC)

**Decisión:** Usar el cast `encrypted` nativo de Laravel 12 sobre todos los campos sensibles.

**Alternativas descartadas:**
- **HashiCorp Vault:** Agrega un point-of-failure operacional (cluster HA, unseal keys) sin resolver el vector real. Si el servidor app está comprometido, el token del agente Vault también lo está.
- **AWS KMS:** Válido para fase futura (clientes enterprise con BYOK). Reevaluar cuando se firmen contratos con cláusulas KMS explícitas.

**Consecuencia crítica:** Si se pierde `APP_KEY`, los datos son irrecuperables. Ver sección "Riesgos" y runbook obligatorio antes de release.

### A2 — Tabla Separada `case_ircc_credentials` (1:1)

**Decisión:** Tabla separada, no columnas adicionales en `cases`.

**Justificación:** Si los campos van en `cases`, cualquier `CaseResource` existente (33 controllers) podría filtrarlos accidentalmente. La tabla separada elimina esa clase de bug por diseño. También facilita:
- Policy independiente
- Exclusión de backups selectivos
- Rotación de llaves limitada a una tabla pequeña
- Auditoría diferenciada

### A3 — Auditoría Diferenciada Lectura/Escritura

**Decisión:** Registrar accesos de lectura explícitamente desde el Controller (no Observer) usando `IrccAuditService`. Los logs de escritura van via Observer. Ningún log captura valores — solo nombres de campos y metadatos de sesión.

### A4 — Activación Frontend como Capa de UX Friction (no seguridad)

**Decisión:** Triple-clic en el campo de número de expediente del header del caso + verificación silenciosa de permiso al backend.

**Importante:** El frontend NO es la capa de seguridad. El backend rechaza cualquier request sin permiso `ircc_credentials.view`, independientemente de si la pestaña es visible o no. El mecanismo secreto solo evita exposición accidental.

---

## Schema de Base de Datos

```sql
CREATE TABLE case_ircc_credentials (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    case_id BIGINT UNSIGNED NOT NULL UNIQUE,
    ircc_username TEXT NULL,          -- encrypted (AES-256-CBC)
    ircc_password TEXT NULL,          -- encrypted
    email_address TEXT NULL,          -- encrypted
    email_password TEXT NULL,         -- encrypted
    application_number TEXT NULL,     -- encrypted
    notes TEXT NULL,                  -- encrypted
    security_questions JSON NULL,     -- encrypted:array [{pregunta, respuesta} x5]
    key_version TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tenant_case (tenant_id, case_id)
);
```

**Notas del schema:**
- `TEXT` no `VARCHAR` — payload AES + HMAC + base64 genera ~250-300 chars para strings cortos; `notes` puede ser mucho mayor.
- `key_version` — columna para rotación futura de llaves sin re-encriptar todo el dataset. Registros nuevos usan `v2` al rotar; los viejos se re-encriptan lazy en el siguiente read.
- `UNIQUE` en `case_id` — garantiza la cardinalidad 1:1 a nivel de DB.
- `ON DELETE CASCADE` — al borrar el expediente (soft o hard), las credenciales se eliminan.

---

## Permisos

| Permiso | Descripción | Roles |
|---------|-------------|-------|
| `ircc_credentials.view` | Ver credenciales IRCC de un expediente | `consultor`, `super-admin` |
| `ircc_credentials.edit` | Crear/modificar credenciales IRCC | `consultor`, `super-admin` |

Definir en migración dedicada con `Permission::firstOrCreate()` (ver Patrón D-03).

---

## Artefactos Backend

### Migraciones
1. `xxxx_create_case_ircc_credentials_table` — schema completo
2. `xxxx_create_ircc_credential_permissions` — permisos Spatie

### Modelo `CaseIrccCredential`
```php
use BelongsToTenant, HasFactory;

protected $casts = [
    'ircc_username'      => 'encrypted',
    'ircc_password'      => 'encrypted',
    'email_address'      => 'encrypted',
    'email_password'     => 'encrypted',
    'application_number' => 'encrypted',
    'notes'              => 'encrypted',
    'security_questions' => 'encrypted:array',
];

protected $hidden = ['security_questions'];  // forzar paso por Resource
// $fillable explícito — nunca $guarded = []
```

Relación en `ImmigrationCase`:
```php
public function irccCredentials(): HasOne
{
    return $this->hasOne(CaseIrccCredential::class, 'case_id');
}
```

### Policy `CaseIrccCredentialPolicy`
Triple gate en cada ability: **role consultor + permiso específico + tenant match + ownership del caso padre**.

```php
public function view(User $user, CaseIrccCredential $cred): bool
{
    return $user->hasRole(['consultor', 'super-admin'])
        && $user->hasPermissionTo('ircc_credentials.view')
        && $user->tenant_id === $cred->tenant_id;
}
```

### Controller `IrccCredentialController`
```
GET  /api/cases/{case}/ircc-credentials/availability  → {available: bool}
GET  /api/cases/{case}/ircc-credentials               → IrccCredentialResource + log de lectura
PUT  /api/cases/{case}/ircc-credentials               → upsert atómico → {success, updated_at}
```

- `show()`: autoriza → carga → `IrccAuditService::logRead()` → retorna Resource
- `update()`: autoriza → valida → upsert en transacción → retorna `{success, updated_at}` (NO el payload)
- `availability()`: retorna solo si el usuario tiene permiso (sin log de lectura — no hubo acceso a datos)

### Service `IrccCredentialService`
```php
// app/Services/Ircc/IrccCredentialService.php
public function upsert(ImmigrationCase $case, array $data, User $by): CaseIrccCredential;
public function getForCase(ImmigrationCase $case): ?CaseIrccCredential;
```

### Audit Service `IrccAuditService`
```php
// app/Services/Ircc/IrccAuditService.php
public function logRead(CaseIrccCredential $cred, User $user): void
{
    activity('ircc_access')
        ->causedBy($user)
        ->performedOn($cred)
        ->withProperties([
            'ip'        => request()->ip(),
            'user_agent'=> request()->userAgent(),
            'case_id'   => $cred->case_id,
            'tenant_id' => $cred->tenant_id,
        ])
        ->event('viewed')
        ->log('IRCC credentials viewed');
}
```

**Regla absoluta:** `logRead` y `logWrite` NUNCA incluyen valores de campos sensibles en `withProperties()` — solo nombres de campos modificados y metadatos de sesión.

### Resource `IrccCredentialResource`
- Mapeo campo por campo explícito — nunca `$this->resource->toArray()`
- En responses de escritura (`PUT`): no devolver valores, solo `{success, updated_at}`

### Form Request `StoreIrccCredentialRequest`
```php
'ircc_username'             => 'nullable|string|max:255',
'ircc_password'             => 'nullable|string|max:255',
'email_address'             => 'nullable|email|max:255',
'email_password'            => 'nullable|string|max:255',
'application_number'        => 'nullable|string|max:50',
'notes'                     => 'nullable|string|max:5000',
'security_questions'        => 'nullable|array|size:5',
'security_questions.*.pregunta'  => 'nullable|string|max:500',
'security_questions.*.respuesta' => 'nullable|string|max:500',
```

### Handler.php — `$dontFlash`
Agregar a la lista existente:
```php
'ircc_password', 'email_password', 'ircc_username',
'application_number', 'security_questions',
```

---

## Artefactos Frontend

### Store `useIrccStore`
```ts
// resources/js/src/stores/useIrccStore.ts
state: {
  credentials: IrccCredentials | null,
  loadedAt: number,
  caseId: number | null,
}

// clear() sobrescribe valores antes de null (memory hygiene)
clear(): void {
  if (this.credentials) {
    for (const k of Object.keys(this.credentials)) {
      (this.credentials as any)[k] = '\0'.repeat(64);
    }
  }
  this.$reset();
}
```

Triggers de limpieza:
1. `onUnmounted` del componente `IrccCredentialsTab.vue`
2. `beforeRouteLeave` en `cases/show.vue`
3. TTL de 5 minutos de inactividad
4. `visibilitychange` — si la pestaña estuvo inactiva >2 min, forzar re-load

### Composable `useIrccActivation`
```ts
// resources/js/src/composables/useIrccActivation.ts
// Nombre neutral en código — no usar strings 'IRCC' ni 'secret'
export function useExtendedTab(caseId: Ref<number>) {
  const visible = ref(false);
  const clickCount = ref(0);
  const lastClickAt = ref(0);

  function onIdentifierClick() {
    const now = Date.now();
    if (now - lastClickAt.value > 800) clickCount.value = 0;
    clickCount.value++;
    lastClickAt.value = now;
    if (clickCount.value >= 3) {
      clickCount.value = 0;
      checkAvailability();
    }
  }

  async function checkAvailability() {
    const { available } = await irccCredentialsService.getAvailability(caseId.value);
    if (available) visible.value = true;
  }

  function deactivate() {
    visible.value = false;
    useIrccStore().clear();
  }

  return { visible, onIdentifierClick, deactivate };
}
```

### Service `irccCredentialsService.ts`
```ts
// resources/js/src/services/irccCredentialsService.ts
getAvailability(caseId: number): Promise<{ available: boolean }>
get(caseId: number): Promise<IrccCredentials>
save(caseId: number, payload: IrccCredentialsPayload): Promise<{ success: boolean; updated_at: string }>
```

### Componente `IrccCredentialsTab.vue`
- Sección 1: Credenciales IRCC (2 inputs con reveal)
- Sección 2: Email (4 inputs con reveal en passwords)
- Sección 3: Tabla fija de 5 filas — `v-for="i in 5"` con `pregunta` (text) + `respuesta` (password + reveal)
- Sección 4: Metadatos read-only (`created_by`, `updated_at`)
- Botón "Guardar" con estado `saving`, toast propio, error handling propio
- Botón "Cerrar" que ejecuta `deactivate()`
- `onUnmounted(() => irccStore.clear())`

**Password masking estándar:**
```vue
<input
  :type="revealed[field] ? 'text' : 'password'"
  :readonly="!editing"
  autocomplete="off"
  data-lpignore="true"
  data-form-type="other"
  spellcheck="false"
/>
```

### Integración en `cases/show.vue`
```vue
<Tab v-if="irccActivation.visible.value" name="ircc">
  <IrccCredentialsTab :case-id="caseId" />
</Tab>
```

Triple-click handler en el elemento que muestra el número/código del expediente en el header.

### i18n — Claves FLAT (agregar en es.json, en.json, fr.json)

```json
"ircc.tab_title": "Información IRCC",
"ircc.section_credentials": "Credenciales IRCC",
"ircc.section_email": "Correo Electrónico",
"ircc.section_security_questions": "Preguntas de Seguridad",
"ircc.field_username": "Usuario IRCC",
"ircc.field_password": "Contraseña",
"ircc.field_email": "Email",
"ircc.field_email_password": "Contraseña Email",
"ircc.field_application_number": "Número de Solicitud",
"ircc.field_notes": "Notas",
"ircc.field_question": "Pregunta",
"ircc.field_answer": "Respuesta",
"ircc.field_created_by": "Creado por",
"ircc.save": "Guardar",
"ircc.saved_successfully": "Credenciales guardadas correctamente",
"ircc.save_failed": "Error al guardar las credenciales",
"ircc.close_tab": "Cerrar",
"ircc.no_access": "No tienes permiso para ver esta información",
"ircc.reveal": "Mostrar",
"ircc.hide": "Ocultar",
"ircc.edit_mode": "Editar",
"ircc.cancel_edit": "Cancelar"
```

---

## Riesgos y Mitigaciones

### R1 — Pérdida de APP_KEY (CRÍTICO — bloqueante para release)

**Consecuencia:** Todos los datos encriptados son irrecuperables sin posibilidad de recovery.

**Mitigaciones obligatorias antes de desplegar a producción:**
1. Backup de `APP_KEY` en gestor de secretos externo (1Password Business, AWS Secrets Manager) — mínimo 3 copias geográficamente distribuidas.
2. Documentar en runbook y deploy pipeline que `php artisan key:generate` está **prohibido** en producción.
3. Usar `APP_PREVIOUS_KEYS` (Laravel 12) para rotación sin downtime.
4. Test de catástrofe trimestral: restaurar backup de DB con `APP_KEY` actual en staging, verificar decrypt exitoso.
5. Comando artisan `ircc:verify-integrity` en CI tras cada deploy.

**Proceso de rotación de llaves:**
```
1. Generar nueva APP_KEY
2. Mover la actual a APP_PREVIOUS_KEYS
3. Job de migración re-encripta registros en background
4. Verificar con ircc:verify-integrity
5. Remover APP_PREVIOUS_KEYS cuando migración complete
```

### R2 — Fuga en Logs

- `$dontFlash` en `Handler.php` incluye todos los campos IRCC
- Sentry/Bugsnag: `before_send` sanitiza `request.data.ircc_*`, `request.data.security_questions`
- MySQL slow query log: mostrará texto cifrado (aceptable — sin `APP_KEY` es ruido)
- El cast `encrypted` encripta antes del SQL bind — el plaintext nunca toca MySQL

### R3 — IDOR Cross-Tenant

- Ruta scoped: `cases/{case}/ircc-credentials` (no `ircc-credentials/{id}`)
- Policy verifica `$user->tenant_id === $cred->tenant_id`
- Retornar **404 (no 403)** para recursos de otro tenant — no filtrar existencia

### R4 — Filtrado accidental en CaseResource (riesgo post-release)

**El bug más probable en mantenimiento:** Alguien agrega `'ircc_credentials' => $this->irccCredentials` en `CaseResource` "por conveniencia" y expone todo el dataset desencriptado en el listado general de casos.

**Mitigación:** Code review checklist explícito para cualquier cambio a `CaseResource`.

---

## Gotchas Específicos de este Spec

### G13 — `encrypted:array` solo acepta estructuras planas
El JSON de `security_questions` no puede contener objetos no serializables (Carbon, modelos Eloquent). Validar en `StoreIrccCredentialRequest` que sea un array de `{pregunta: string, respuesta: string}`.

### G14 — Cast `encrypted` rompe queries `where`
No se puede `whereIrccUsername()` sobre campos encriptados. No hay búsqueda en este módulo — aceptable. Si el negocio lo requiere en el futuro, necesita columna hash HMAC separada.

### G15 — Cross-tenant: 404 no 403
Para no filtrar existencia del recurso. Implementar en Policy con `before()` o en el Controller con lookup scoped por tenant.

### G16 — El mecanismo secreto NO es seguridad
El triple-clic es solo UX friction. El backend valida permisos en cada request. Si el atacante tiene las cookies de sesión de un consultor, puede hacer el request directo. Comunicar al equipo para evitar falsas sensaciones de seguridad.

---

## Checklist de Implementación

### Fase 1: Backend ✅ Completada (2026-05-15)
- [x] Migración `case_ircc_credentials` table
- [x] Migración de permisos `ircc_credentials.view` + `ircc_credentials.edit`
- [x] Modelo `CaseIrccCredential` con casts, `BelongsToTenant`, `$fillable`
- [x] Relación `hasOne` en `ImmigrationCase`
- [x] `CaseIrccCredentialPolicy` + Gates en `AuthServiceProvider` (via `Gate::define`, no Policy mapeada — colisión con CasePolicy)
- [x] `IrccCredentialService` (upsert + get)
- [x] `IrccAuditService` (logRead + logWrite — sin valores)
- [x] `StoreIrccCredentialRequest`
- [x] `IrccCredentialController` (availability + show + update)
- [x] `IrccCredentialResource`
- [x] Rutas en `routes/api.php`
- [x] `$dontFlash` actualizado en `Handler.php`

### Fase 2: Frontend ✅ Completada (2026-05-15)
- [x] `useIrccStore` con `clear()` de sobrescritura
- [x] `useIrccActivation` composable (triple-click + check backend)
- [x] `irccCredentialsService.ts`
- [x] `IrccCredentialsTab.vue` (form completo + tabla 5 filas + reveals)
- [x] Integración en `cases/show.vue`
- [x] i18n claves en `es.json`, `en.json`, `fr.json`
- [x] `CLAUDE.md` actualizado con G13, G14, G15, G16

### Fase 3: Operaciones ⚠️ PENDIENTE — Bloqueante para release en producción
- [ ] Runbook `docs/runbooks/app-key-management.md`
- [ ] Backup de `APP_KEY` en gestor de secretos externo verificado
- [ ] Comando artisan `ircc:verify-integrity`
- [ ] Test de catástrofe en staging

### Fase 4: Tests — Pendiente (DT-06)
- [ ] Feature: GET sin permiso → 403/404
- [ ] Feature: GET con permiso → log de lectura creado en `activity_log`
- [ ] Feature: PUT upsert idempotente → segunda llamada no crea duplicado
- [ ] Feature: cross-tenant access → 404
- [ ] Feature: `storage/logs/laravel.log` no contiene valores tras exception en el endpoint
- [ ] Unit: round-trip encrypt/decrypt preserva data incluyendo caracteres especiales
- [ ] Unit: `security_questions` array de 5 exactamente (ni más ni menos)

---

## Archivos a Crear/Modificar

### Crear
- `database/migrations/xxxx_create_case_ircc_credentials_table.php`
- `database/migrations/xxxx_create_ircc_credential_permissions.php`
- `app/Models/CaseIrccCredential.php`
- `app/Policies/CaseIrccCredentialPolicy.php`
- `app/Services/Ircc/IrccCredentialService.php`
- `app/Services/Ircc/IrccAuditService.php`
- `app/Http/Controllers/Api/IrccCredentialController.php`
- `app/Http/Requests/StoreIrccCredentialRequest.php`
- `app/Http/Resources/IrccCredentialResource.php`
- `resources/js/src/stores/useIrccStore.ts`
- `resources/js/src/composables/useIrccActivation.ts`
- `resources/js/src/services/irccCredentialsService.ts`
- `resources/js/src/views/cases/IrccCredentialsTab.vue`
- `docs/runbooks/app-key-management.md`

### Modificar
- `app/Models/ImmigrationCase.php` — agregar relación `hasOne`
- `app/Providers/AuthServiceProvider.php` — registrar Policy
- `app/Exceptions/Handler.php` — ampliar `$dontFlash`
- `routes/api.php` — agregar grupo de rutas
- `resources/js/src/views/cases/show.vue` — tab condicional + triple-click handler
- `resources/js/src/locales/es.json`, `en.json`, `fr.json` — claves FLAT `ircc.*`
- `CLAUDE.md` — Gotchas G13-G16
- `spec/INDEX.md` — registrar Spec 60
- `MEMORY.md` — entrada al completar
