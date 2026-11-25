---
tipo: spec
numero: 38
modulo: Sistema e Infraestructura
dominio: SYS
estado: DONE
tags: [spec, sistema, done]
---
# [[38_backup_total_base_de_datos|Spec 38]]: Respaldo Total de Base de Datos por Tenant

## Información del Documento

- **Fecha:** 2026-04-05
- **Tipo:** Análisis de Impacto de Infraestructura y Seguridad + Plan de Implementación
- **Scope:** Generación de archivos `.sql.gz` por tenant, endpoint seguro para automatización vía CRON/Job externo, panel de administración, auditoría y gestión del ciclo de vida de los backups
- **Dependencias:** [[28_multi_tenant_branding_storage|Spec 28]] (Multi-tenant), arquitectura admin existente (`/api/admin/tenants/*`)
- **Estado:** Implementado

---

## 1. Resumen Ejecutivo

El sistema actual no tiene mecanismo de respaldo de datos por tenant. Dado que la arquitectura es **single-database multi-tenant** (todas las tablas comparten una BD con `tenant_id`), el backup completo de la BD incluiría datos de todos los tenants — inaceptable en un contexto SaaS.

La solución propuesta genera **exports SQL por tenant**: extrae únicamente las filas donde `tenant_id = X` de cada tabla relevante, produce un archivo `.sql.gz` autocontenido que permite restauración aislada, y lo expone mediante un endpoint protegido consumible por Jobs externos o desde el panel de administración.

### Hallazgos clave del análisis

| ID | Hallazgo | Impacto |
|----|----------|---------|
| BK-001 | Base de datos compartida — `mysqldump` full no es viable para backup por tenant | Crítico |
| BK-002 | `mysqldump` nativo con `--where` no funciona con JOINs complejos (tablas sin `tenant_id` directo) | Alto |
| BK-003 | PHP tiene `max_execution_time` de 30s por defecto — insuficiente para backups grandes | Alto |
| BK-004 | Archivos en `storage/app` son accesibles si el symlink de `public/storage` está mal configurado | Medio |
| BK-005 | No existe auditoría de operaciones de backup/restauración | Medio |
| BK-006 | `mysqldump` disponible en `/opt/homebrew/opt/mysql-client/bin/mysqldump` (Herd/local) | Info |

---

## 2. Análisis de Impacto

### 2.1 Estrategia de Dump: `mysqldump` vs PHP nativo

| Criterio | `mysqldump` vía Symfony Process | PHP nativo (PDO loop) |
|----------|--------------------------------|----------------------|
| Estabilidad con BD grande | ✅ Excelente — stream directo a disco | ⚠️ Riesgo de OOM con millones de filas |
| Filtrado por `tenant_id` | ⚠️ Limitado — `--where` solo para tablas simples | ✅ Total control con WHERE por query |
| Velocidad | ✅ Muy rápida (C nativo) | ❌ Lenta para datos grandes |
| Portabilidad del output | ✅ SQL estándar compatible con MySQL restore | ✅ SQL generado manualmente |
| Compresión integrada | ✅ pipe a `gzip` en el mismo proceso | Manual |
| **Veredicto** | **Recomendado para BD completa** | **Recomendado para export por tenant** |

**Decisión: PHP nativo con PDO** para este caso. La razón: la arquitectura single-DB con `tenant_id` requiere filtrar fila por fila en cada tabla. `mysqldump --where` no soporta subqueries ni JOINs, lo que imposibilita exportar tablas como `case_companions` (sin `tenant_id` directo).

### 2.2 Gestión de Memoria y Tiempo de Ejecución

```php
// Al inicio del comando Artisan
ini_set('max_execution_time', 0);     // Sin límite en CLI
ini_set('memory_limit', '512M');      // Suficiente para datasets medianos
```

- Los comandos Artisan no tienen límite de tiempo por defecto (`max_execution_time = 0` en CLI)
- El endpoint HTTP **debe** ejecutar el backup de forma **asíncrona** (queue job) para evitar timeouts
- Para tenants con >100k filas, usar `chunk(1000)` en los queries de exportación

### 2.3 Multi-tenancy: Tablas con y sin `tenant_id`

**Tablas con `tenant_id` directo** (backup filtrado por tenant):
`clients`, `companions`, `cases`, `documents`, `document_folders`, `tasks`, `events`, `oauth_tokens`, `invitation_codes`, `case_types`, `task_time_entries`, `follow_ups`, `legal_documents`, `users`

**Tablas relacionales sin `tenant_id`** (requieren JOIN para filtrar):
- `case_companions` → JOIN con `cases` WHERE `cases.tenant_id = X`
- `case_check_items` → JOIN con `cases`
- `case_important_dates` → JOIN con `cases`
- `activity_log` → WHERE `subject_type` IN [...] (filtrado por IDs del tenant)

**Tablas globales** (no incluir en backup por tenant):
`tenants`, `migrations`, `personal_access_tokens`, `password_reset_tokens`, `failed_jobs`

---

## 3. Arquitectura de Seguridad

### 3.1 Autenticación del Endpoint

Se proponen **dos modalidades de acceso**:

#### A) Acceso desde Panel de Administración (UI)
- Ruta: `POST /api/admin/tenants/{tenant}/backup`
- Protección: Sanctum (`auth:sanctum`) + Spatie role `super-admin`
- Respuesta: `202 Accepted` + `job_id` (asíncrono)

#### B) Acceso desde Job externo / CRON
- Ruta: `POST /api/admin/backup/run`
- Protección: **API Key estática** almacenada en `.env` como `BACKUP_API_KEY`
- Header requerido: `X-Backup-Key: {BACKUP_API_KEY}`
- Middleware dedicado: `ValidateBackupApiKey`
- La key debe ser un UUID v4 de 36+ chars generado en setup

```php
// Ejemplo de validación en middleware
if (! hash_equals(config('backup.api_key'), $request->header('X-Backup-Key', ''))) {
    abort(401, 'Invalid backup key');
}
```

> **¿Por qué API Key y no Signed URL?** Las Signed URLs de Laravel expiran (by design), lo que requiere regenerarlas antes de cada ejecución del CRON — añade complejidad operacional. Una API Key fija en `.env` es más simple y segura para Jobs automatizados siempre que el canal sea HTTPS.

### 3.2 Protección del Archivo Generado

- Almacenamiento: `storage/app/backups/{tenant_slug}/` — fuera del webroot, no expuesto públicamente
- El disco `local` de Laravel apunta a `storage/app` — **nunca** symlinkear a `public/`
- Descarga solo mediante **Signed URL temporal** (`Storage::temporaryUrl()` o respuesta `StreamedResponse`)
- TTL de descarga: 15 minutos
- Limpieza automática: archivos >7 días se eliminan via `backup:cleanup` command

```php
// Descarga segura
return response()->streamDownload(function () use ($path) {
    $stream = Storage::disk('backups')->readStream($path);
    fpassthru($stream);
}, $filename, ['Content-Type' => 'application/gzip']);
```

---

## 4. Diseño del Sistema

### 4.1 Flujo Completo

```
[CRON / UI Admin]
    │
    ▼
POST /api/admin/tenants/{tenant}/backup
    │ auth: sanctum + role:super-admin  ← UI
    │ auth: X-Backup-Key header         ← CRON
    │
    ▼
BackupController::run()
    │ Dispatch → GenerateTenantBackupJob (queue)
    │ Returns 202 + { job_id, message }
    │
    ▼
GenerateTenantBackupJob::handle()
    │ TenantBackupService::generate($tenant)
    │   ├── SET max_execution_time = 0
    │   ├── Por cada tabla: SELECT WHERE tenant_id / JOIN
    │   ├── Generar SQL: CREATE TABLE IF NOT EXISTS + INSERT
    │   ├── Comprimir con gzencode()
    │   ├── Guardar en storage/app/backups/{slug}/
    │   └── Registrar en tabla backup_logs
    │
    ▼
Notificación (si falla → Alert por email/log)
```

### 4.2 Formato del Archivo SQL

```sql
-- VITE-IT Immigration Backup
-- Tenant: Onis Immigration (onis-immigration)
-- Generated: 2026-04-05 14:30:00 UTC
-- Tables: 18 | Rows: 4,521

SET FOREIGN_KEY_CHECKS=0;
SET NAMES utf8mb4;

-- Table: clients
CREATE TABLE IF NOT EXISTS `clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  ...
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `clients` (`id`,`tenant_id`,`first_name`,...) VALUES
(9,35,'César','Mora',...),
(10,35,'Andrea','Salgado',...);

-- Table: companions
...

SET FOREIGN_KEY_CHECKS=1;
```

### 4.3 Tabla `backup_logs`

```sql
CREATE TABLE backup_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    triggered_by    BIGINT UNSIGNED NULL,       -- user_id (null = CRON)
    trigger_source  ENUM('ui','cron','api') NOT NULL,
    status          ENUM('pending','running','completed','failed') DEFAULT 'pending',
    filename        VARCHAR(255) NULL,
    file_size_bytes BIGINT UNSIGNED NULL,
    row_count       INT UNSIGNED NULL,
    error_message   TEXT NULL,
    started_at      TIMESTAMP NULL,
    completed_at    TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_status (status)
);
```

---

## 5. Validación de Integridad

```php
// Al finalizar la generación
$checksum = hash_file('sha256', $localPath);
// Guardar checksum en backup_logs.checksum

// Al descargar
$downloadChecksum = hash('sha256', Storage::disk('backups')->get($path));
if (! hash_equals($storedChecksum, $downloadChecksum)) {
    abort(500, 'Backup file integrity check failed');
}
```

**Sobre restauración vía web:** Se desaconseja implementar restauración automática vía UI por las siguientes razones:
- Riesgo de sobreescritura accidental de datos en producción
- El proceso puede fallar a mitad dejando datos en estado inconsistente
- Requiere DROP + recrear tablas con data live de otros tenants en la misma BD

**Recomendación:** La restauración debe hacerse manualmente por consola con el comando `php artisan tenant:restore {tenant} {filename}` en un ambiente controlado.

---

## 6. Plan de Implementación

### Fase 1: Infraestructura base

- [x] Crear migración `create_backup_logs_table`
- [x] Crear modelo `BackupLog` (sin [[BelongsToTenant]] — acceso super-admin directo)
- [x] Crear disco `backups` en `config/filesystems.php` → `storage/app/backups`
- [x] Agregar `BACKUP_API_KEY` a `.env` y `config/backup.php`

### Fase 2: Servicio de generación

- [x] Crear `app/Services/Backup/TenantBackupService.php`
  - Método `generate(Tenant $tenant): BackupLog`
  - Introspección de tablas con `tenant_id`
  - Manejo de tablas relacionales sin `tenant_id` (case_companions, etc.)
  - Generación de SQL con `CREATE TABLE IF NOT EXISTS` + chunked `INSERT`
  - Compresión con `gzencode()`
  - Guardado en disco `backups`
  - Validación de integridad (sha256)
- [x] Crear `app/Jobs/GenerateTenantBackupJob.php` (queue: `backups`)

### Fase 3: Comando Artisan

- [x] Crear `app/Console/Commands/BackupTenant.php`
  - Firma: `tenant:backup {tenant : ID or slug}`
  - Llama a `TenantBackupService`
  - Output de progreso con `$this->info()` / `$this->withProgressBar()`
- [x] Crear `app/Console/Commands/BackupCleanup.php`
  - Firma: `backup:cleanup {--days=7}`
  - Elimina archivos y registros de backup_logs con `completed_at < now() - days`

### Fase 4: API y Middleware

- [x] Crear `app/Http/Middleware/ValidateBackupApiKey.php`
- [x] Crear `app/Http/Controllers/Api/Admin/BackupController.php`
  - `run(Tenant $tenant)` → dispatch job → 202
  - `index(Tenant $tenant)` → listar backup_logs
  - `download(BackupLog $log)` → StreamedResponse con checksum
  - `destroy(BackupLog $log)` → eliminar archivo + registro
- [x] Registrar rutas en `routes/api.php` bajo grupo `admin`

### Fase 5: Frontend (Panel Admin)

- [x] Agregar sección "Backups" en nueva vista `admin/tenant/TenantBackups.vue`
  - Botón "Generar Backup" → POST + polling de estado
  - Tabla de backups con: fecha, tamaño, estado, botón descarga
  - Badge de estado: pending / running / completed / failed

### Fase 6: Notificaciones y Auditoría

- [ ] Notificación por email a super-admin si backup falla
- [ ] Registro en `activity_log` de cada operación (generación, descarga, eliminación)
- [x] Agregar claves i18n `backup.*` en en/es/fr

---

## 7. Archivos a Crear/Modificar

### Backend
| Archivo | Acción |
|---------|--------|
| `database/migrations/YYYY_add_backup_logs_table.php` | Crear |
| `app/Models/BackupLog.php` | Crear |
| `config/backup.php` | Crear |
| `config/filesystems.php` | Modificar — disco `backups` |
| `app/Services/Backup/TenantBackupService.php` | Crear |
| `app/Jobs/GenerateTenantBackupJob.php` | Crear |
| `app/Console/Commands/BackupTenant.php` | Crear |
| `app/Console/Commands/BackupCleanup.php` | Crear |
| `app/Http/Middleware/ValidateBackupApiKey.php` | Crear |
| `app/Http/Controllers/Api/Admin/BackupController.php` | Crear |
| `routes/api.php` | Modificar — rutas backup |

### Frontend
| Archivo | Acción |
|---------|--------|
| `resources/js/src/views/admin/tenant/TenantBackups.vue` | Crear |
| `resources/js/src/services/backupService.ts` | Crear |
| `resources/js/src/types/backup.ts` | Crear |
| `resources/js/src/locales/en.json` | Modificar |
| `resources/js/src/locales/es.json` | Modificar |
| `resources/js/src/locales/fr.json` | Modificar |

---

## 8. Criterios de Aceptación

1. `php artisan tenant:backup {slug}` genera un `.sql.gz` válido restaurable con `mysql < dump.sql`
2. `POST /api/admin/tenants/{id}/backup` con rol `super-admin` despacha el job y retorna 202
3. `POST /api/admin/backup/run` sin `X-Backup-Key` retorna 401
4. El archivo generado **no** es accesible via URL pública directa
5. La descarga solo funciona con el endpoint autenticado (`/api/admin/backups/{log}/download`)
6. Backup de tenant con 5,000 filas completa en <30s
7. Backups >7 días se eliminan automáticamente
8. Fallo de backup genera log en `backup_logs` con `status = failed` y `error_message`

---

## 9. Consideraciones de Riesgo

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|-------------|---------|------------|
| OOM en tenant con >500k filas | Baja | Alto | `chunk(1000)` en todos los queries |
| Archivo corrupto por fallo a mitad | Baja | Alto | Escribir a temp file, mover solo si completa sin error |
| Colisión de nombres de archivo | Muy baja | Medio | Timestamp en nombre: `slug_YYYY-MM-DD_HHmm.sql.gz` |
| Exposición accidental del archivo | Baja | Crítico | Disco `backups` aislado + validación en descarga |
| BACKUP_API_KEY filtrado en logs | Baja | Alto | Nunca loguear headers de autenticación |
| Tablas relacionales sin `tenant_id` olvidadas | Media | Alto | Lista exhaustiva hardcodeada en el servicio + test |
