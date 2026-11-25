---
tipo: spec
numero: 30
modulo: Documentos y Storage
dominio: DOCS
estado: DONE
tags: [spec, documentos, done]
relacionados:
  - "[[27_file_manager_system|Spec 27]]"
  - "[[28_multi_tenant_branding_storage|Spec 28]]"
  - "[[33_sharepoint_storage_provider|Spec 33]]"
  - "[[56_seleccion_dinamica_directorios_wizard|Spec 56]]"
---
# [[30_cloud_folder_sync_por_tenant|Spec 30]]: Sincronizacion de Carpetas Cloud por Tenant (Storage Provider Unico)

## Informacion del Documento

- **Fecha:** 2026-03-21
- **Tipo:** Analisis de Impacto Tecnico
- **Scope:** Gestion de expedientes con un unico Storage Provider activo por tenant
- **Dependencias:** [[27_file_manager_system|Spec 27]] (File Manager System), [[28_multi_tenant_branding_storage|Spec 28]] (Multi-Tenant Branding & Storage)
- **Estado:** Completo - Fases 1-6 implementadas (36/36 tareas, 35 tests, 217 assertions)

---

## 1. Resumen Ejecutivo

Este documento analiza el impacto tecnico de implementar la gestion completa de expedientes con un unico Storage Provider activo por tenant. Cada tenant puede elegir guardar archivos en OneDrive, Google Drive o Local. Al crear un expediente, el sistema debe crear automaticamente la estructura de carpetas en la plataforma seleccionada y mantener consistencia en todas las operaciones de documentos.

### Contexto

La arquitectura base de storage providers ya existe (Spec 27): `DocumentStorageInterface`, `StorageProviderFactory`, `LocalStorageProvider`, `OneDriveProvider`, `GoogleDriveProvider`, `ResilientStorageProvider` con circuit breaker. Sin embargo, **no existe sincronizacion de carpetas con plataformas cloud** ni creacion automatica de estructura al instanciar expedientes.

---

## 2. Reglas de Negocio

| # | Regla | Descripcion |
|---|---|---|
| RN1 | Seleccion Unica | Cada Tenant tiene configurado un solo `storage_type` (local, onedrive, google_drive) |
| RN2 | Estructura de Carpetas | Al crear expediente, crear carpeta raiz (nombre = codigo del expediente, ej: `26-PQ-VEGA-0003`) y subdirectorios en la plataforma seleccionada |
| RN3 | Creacion Automatica | Al crear un case, generar automaticamente las estructuras de directorios |
| RN4 | Consistencia de Operaciones | Carga, eliminacion y visualizacion deben ejecutarse sobre el disco configurado |
| RN5 | Recursividad | Subdirectorios dentro del expediente deben replicarse en la plataforma cloud |
| RN6 | Prohibicion de Renombrado | Los archivos mantienen su nombre original tras la subida |
| RN7 | Visibilidad en Tiempo Real | La pestana "Documentos" debe reflejar los archivos existentes en los repositorios cloud |

---

## 3. Estado Actual del Codebase (Verificado)

### 3.1 Arquitectura de Storage Existente

| Componente | Archivo | Estado |
|---|---|---|
| Contrato | `app/Contracts/DocumentStorageInterface.php` | Completo: upload, download, delete, move, isAvailable |
| Factory | `app/Services/Storage/StorageProviderFactory.php` | Completo: resuelve provider por `tenant.storage_type` |
| Local | `app/Services/Storage/LocalStorageProvider.php` | Completo con validacion de path traversal |
| OneDrive | `app/Services/Storage/OneDriveProvider.php` | Completo: simple + resumable upload, download, delete, move |
| Google Drive | `app/Services/Storage/GoogleDriveProvider.php` | Completo: multipart + resumable upload, download, delete, move |
| Resiliencia | `app/Services/Storage/ResilientStorageProvider.php` | Completo: retry (1 intento) + circuit breaker |
| Circuit Breaker | `app/Services/Storage/CircuitBreaker.php` | Completo: 5 failures / 60s -> open 120s -> half-open |

### 3.2 Modelo de Datos Actual

**Tabla `tenants`**: Ya tiene `storage_type` (default 'local'), `ms_client_id`, `ms_client_secret`, `google_client_id`, `google_client_secret`, `storage_quota_mb`, `storage_used_bytes`.

**Tabla `documents`**: Ya tiene `storage_type`, `storage_path`, `external_id`, `external_url`, `version`, `checksum`.

**Tabla `document_folders`**: Tiene `tenant_id`, `case_id`, `parent_id`, `name`, `sort_order`, `is_default`, `category`. **NO tiene** `external_id` ni `external_folder_id`.

**Tabla `cases`**: **NO tiene** campo para almacenar el ID de carpeta raiz en la plataforma cloud.

**Tabla `oauth_tokens`**: Tokens a nivel de tenant con refresh automatico y cache lock.

### 3.3 Flujo de Creacion de Caso Actual

En `CaseService::createCase()`, la transaccion crea: caso -> companions -> important_dates -> tasks. **NO invoca** `FolderService::createDefaultStructure()`. Las carpetas por defecto se crean en la BD local pero **no se sincronizan** con ningun provider cloud.

### 3.4 Flujo de Upload Actual

`DocumentService::uploadDocument()` ya:
1. Verifica quota via `StorageQuotaService`
2. Obtiene provider via `StorageProviderFactory::make()`
3. Sube archivo con path `tenants/{tenant_id}/cases/{case_id}/{uuid}.ext`
4. Guarda registro con `storage_type`, `external_id`, `external_url`

**Problema clave**: Los cloud providers suben archivos a una ruta plana. No crean carpetas previamente. OneDrive crea carpetas implicitas al subir con path, Google Drive requiere `parentId` (actualmente no se pasa).

### 3.5 Frontend Actual

El frontend es completamente agnostico al storage provider. Todas las operaciones pasan por la API de Laravel (`documentService.ts` llama endpoints `/api/cases/{id}/documents/*` y `/api/cases/{id}/folders/*`). No hay comunicacion directa con APIs cloud. **Esto es correcto y no debe cambiar.**

---

## 4. GAPs Criticos Identificados

| # | GAP | Severidad | Descripcion |
|---|---|---|---|
| G1 | Carpetas sin external_id | **CRITICA** | `document_folders` no almacena IDs de carpetas en OneDrive/Google Drive |
| G2 | Caso sin root_folder_id cloud | **CRITICA** | `cases` no guarda referencia a la carpeta raiz en la plataforma |
| G3 | createCase no crea folders | **ALTA** | `CaseService::createCase()` no invoca `createDefaultStructure()` |
| G4 | Google Drive sin parentId | **ALTA** | `GoogleDriveProvider::upload()` no recibe ni usa folder ID de destino |
| G5 | DocumentStorageInterface sin createFolder | **CRITICA** | El contrato no define operaciones de carpeta |
| G6 | No hay Job de creacion de carpetas | **ALTA** | No existe mecanismo async para crear estructura en cloud |
| G7 | Providers dependen de Auth::user() | **ALTA** | No funcionan en Jobs (queue workers sin sesion) |

### Matriz de Impacto por Componente

```
                          CREAR    SUBIR    DESCARGAR   ELIMINAR   MOVER   LISTAR
                          CASO     DOC      DOC         DOC        DOC     DOCS
==========================================================================================
StorageInterface           +++      +        -           -          +       ++
StorageProviderFactory      -       -        -           -          -        -
OneDriveProvider           +++      ++       -           -          +       ++
GoogleDriveProvider        +++      ++       -           -          +       ++
LocalStorageProvider        -       -        -           -          -        -
DocumentService             +       ++       -           +          ++       -
FolderService              +++      -        -           -          -       ++
CaseService                +++      -        -           -          -        -
DocumentController          -       +        -           -          -        +
DocumentFolderController    +       -        -           -          -       ++

+++ = Cambio mayor    ++ = Cambio moderado    + = Cambio menor    - = Sin cambio
```

---

## 5. Propuesta de Solucion

### 5.1 Migraciones de Base de Datos

**Migracion 1 — Agregar external_id a document_folders:**

```sql
ALTER TABLE document_folders ADD COLUMN external_id VARCHAR(255) NULL;
ALTER TABLE document_folders ADD COLUMN external_url VARCHAR(512) NULL;
ALTER TABLE document_folders ADD COLUMN sync_status ENUM('pending','synced','failed') DEFAULT 'pending';
ALTER TABLE document_folders ADD COLUMN synced_at TIMESTAMP NULL;

-- Indice para busquedas rapidas
CREATE INDEX idx_folders_external ON document_folders(external_id, tenant_id);
```

**Migracion 2 — Agregar root_external_folder_id a cases:**

```sql
ALTER TABLE cases ADD COLUMN root_external_folder_id VARCHAR(255) NULL;
ALTER TABLE cases ADD COLUMN folder_sync_status ENUM('pending','synced','failed','not_applicable') DEFAULT 'not_applicable';
ALTER TABLE cases ADD COLUMN folder_synced_at TIMESTAMP NULL;
```

Justificacion: `not_applicable` es el default porque casos existentes con storage local no necesitan sincronizacion.

### 5.2 Ampliacion del Contrato DocumentStorageInterface

Archivo: `app/Contracts/DocumentStorageInterface.php`

Agregar 3 metodos nuevos:

```php
/**
 * Create a folder in the storage provider.
 * @return array{external_id: string, external_url: string}
 */
public function createFolder(string $folderName, ?string $parentExternalId = null): array;

/**
 * Delete a folder from the storage provider.
 */
public function deleteFolder(string $externalId): bool;

/**
 * List contents of a folder (for real-time visibility).
 * @return array<int, array{name: string, type: 'file'|'folder', external_id: string, size: int|null, modified_at: string|null}>
 */
public function listFolder(string $externalId): array;
```

Modificar `upload()` para aceptar metadata opcional:

```php
public function upload(UploadedFile $file, string $destinationPath, array $metadata = []): array;
// $metadata puede incluir ['parent_external_id' => '...'] para Google Drive
```

### 5.3 Implementacion por Provider

| Operacion | Local | OneDrive | Google Drive |
|---|---|---|---|
| `createFolder` | `Storage::makeDirectory()` | `POST /drive/root:/{path}:/children` con `{"name":"...","folder":{}}` | `POST /drive/v3/files` con `mimeType: application/vnd.google-apps.folder` y `parents: [parentId]` |
| `deleteFolder` | `Storage::deleteDirectory()` | `DELETE /drive/items/{id}` | `DELETE /drive/v3/files/{id}` |
| `listFolder` | `Storage::files()` + `directories()` | `GET /drive/items/{id}/children` | `GET /drive/v3/files?q='{parentId}' in parents` |
| `upload` con parent | Path-based (sin cambio) | Path-based (implicito, OneDrive crea dirs) | Pasar `parentId` en metadata |

**[[LocalStorageProvider]]**: Los `external_id` para local seran el path relativo (ej: `tenants/1/cases/5/Archivo`).

**[[OneDriveProvider]]**: Usa Microsoft Graph API. `createFolder` llama `POST /me/drive/root:/{path}:/children`. `listFolder` llama `GET /me/drive/items/{id}/children`.

**[[GoogleDriveProvider]]**: Usa Google Drive API v3. `createFolder` llama `POST /drive/v3/files` con `mimeType: 'application/vnd.google-apps.folder'` y `parents: [parentId]`. `listFolder` usa query `'{parentId}' in parents`.

### 5.4 Desacoplamiento de Auth::user() en Providers

Los providers actuales dependen de `Auth::user()` para obtener el `tenant_id` y acceder a tokens OAuth. Esto **no funciona en Jobs** (queue workers sin sesion de usuario).

**Solucion:**

1. Agregar `StorageProviderFactory::makeForTenant(Tenant $tenant)`:

```php
public function makeForTenant(Tenant $tenant): DocumentStorageInterface
{
    $provider = match ($tenant->storage_type) {
        'onedrive' => new OneDriveProvider($this->tokenService, $tenant->id),
        'google_drive' => new GoogleDriveProvider($this->tokenService, $tenant->id),
        default => new LocalStorageProvider(),
    };
    return new ResilientStorageProvider($provider, $tenant->storage_type);
}
```

2. Modificar providers para recibir `tenantId` en constructor en vez de depender de `Auth::user()`:

```php
// OneDriveProvider
public function __construct(
    private OAuthTokenService $tokenService,
    private ?int $tenantId = null  // null = usar Auth::user()
)

private function getAccessToken(): string
{
    $tenantId = $this->tenantId ?? Auth::user()?->tenant_id;
    if (!$tenantId) {
        throw new \RuntimeException('No tenant context available.');
    }
    return $this->tokenService->getValidTenantToken($tenantId, 'microsoft');
}
```

### 5.5 Nuevo Job: [[SyncCaseFolderStructure]]

Archivo nuevo: `app/Jobs/SyncCaseFolderStructure.php`

```
Flujo:
1. Recibe case_id
2. Carga caso -> tenant, verifica storage_type != 'local'
3. Si local: marca todas las folders como synced, retorna
4. Si cloud:
   a. Obtiene provider via StorageProviderFactory::makeForTenant($tenant)
   b. Crea carpeta raiz con nombre = case_number (ej: "26-PQ-VEGA-0003")
   c. Guarda root_external_folder_id en cases
   d. Itera document_folders del caso
   e. Crea cada subcarpeta como hija de la raiz
   f. Guarda external_id en cada document_folder
   g. Marca sync_status = 'synced' o 'failed'

Retry: 3 intentos, backoff exponencial (10s, 30s, 90s)
Idempotencia: Verifica si carpeta ya existe (por external_id) antes de crearla
Fallback: Si falla despues de 3 intentos, marca folder_sync_status = 'failed'
```

**Infraestructura de queues**: Ya existe migracion `create_jobs_table.php` y `create_job_batches_table.php`. Queue driver configurado como `database` en `.env`.

### 5.6 Decision Arquitectural: Sincronico vs Asincronico

| Operacion | Estrategia | Razon |
|---|---|---|
| Carpeta raiz del caso | **Sincronica** | 1 API call (< 2s), feedback inmediato al usuario |
| Subcarpetas default (14) | **Asincronica** (Job) | 14 calls potencialmente lentas, no bloquear creacion |
| Carpetas manuales (usuario) | **Sincronica** | 1 call individual, usuario espera feedback |
| Upload de documentos | **Sincronica** | Ya funciona asi, sin cambio necesario |

### 5.7 Nuevo Service: [[CaseFolderSyncService]]

Archivo nuevo: `app/Services/Document/CaseFolderSyncService.php`

Responsabilidades:

```php
class CaseFolderSyncService
{
    /**
     * Crea toda la estructura de carpetas en la plataforma cloud.
     * Llamado desde el Job SyncCaseFolderStructure.
     */
    public function syncFolderStructure(ImmigrationCase $case): void;

    /**
     * Sincroniza una carpeta individual con cloud.
     * Llamado al crear carpetas manualmente.
     */
    public function syncSingleFolder(DocumentFolder $folder): void;

    /**
     * Compara BD vs cloud y reconcilia diferencias.
     * Llamado desde endpoint de sincronizacion manual.
     */
    public function reconcileFolders(ImmigrationCase $case): array;

    /**
     * Retorna estado de sincronizacion del caso.
     */
    public function getSyncStatus(ImmigrationCase $case): array;
}
```

### 5.8 Modificacion de [[CaseService]]::createCase()

Archivo: `app/Services/Case/CaseService.php`

```php
// Dentro del DB::transaction, al final (despues de tasks):
$this->folderService->createDefaultStructure($case);

// FUERA de la transaccion (afterCommit para garantizar que el caso existe):
SyncCaseFolderStructure::dispatch($case->id)->afterCommit();
```

Dependencia nueva: Inyectar `FolderService` en `CaseService`.

### 5.9 Modificacion de Upload con Folder Targeting

Archivo: `app/Services/Document/DocumentService.php`

En `uploadDocument()`, si `folder_id` esta presente y el folder tiene `external_id`:
- Para OneDrive: subir a la ruta `{root_folder_path}/{subfolder_name}/{filename}`
- Para Google Drive: pasar `parentId = folder.external_id` en metadata

```php
$metadata = [];
if ($folder && $folder->external_id) {
    $metadata['parent_external_id'] = $folder->external_id;
}
$result = $provider->upload($file, $destinationPath, $metadata);
```

### 5.10 Modificacion de [[FolderService]]

Archivo: `app/Services/Document/FolderService.php`

- **createFolder()**: Despues de crear en BD, si tenant es cloud, llamar `CaseFolderSyncService::syncSingleFolder()` sincronicamente. Guardar `external_id` retornado.
- **deleteFolder()**: Si la carpeta tiene `external_id`, eliminar tambien en el provider cloud via `provider->deleteFolder()`.
- **getFolderTree()**: Approach hibrido para visibilidad:
  - Default: servir desde BD (rapido)
  - Accion "Sincronizar": endpoint que llama `listFolder()` del cloud y reconcilia con BD
  - Indicador visual de `sync_status` en el frontend

---

## 6. API Endpoints

### 6.1 Endpoints Nuevos

| Metodo | Ruta | Descripcion | Auth |
|---|---|---|---|
| `POST` | `/api/cases/{case}/folders/sync` | Forzar sincronizacion de carpetas con cloud | `auth:sanctum` |
| `GET` | `/api/cases/{case}/folders/sync-status` | Obtener estado de sincronizacion | `auth:sanctum` |
| `GET` | `/api/tenant/storage-info` | Info del storage actual (tipo, disponibilidad, quota) | `auth:sanctum` |

### 6.2 Endpoints Modificados

| Ruta | Cambio |
|---|---|
| `POST /api/cases/{case}/folders` | Crear carpeta tambien en cloud si aplica |
| `DELETE /api/cases/{case}/folders/{folder}` | Eliminar carpeta tambien en cloud si aplica |
| `POST /api/cases/{case}/documents` | Enviar `parent_external_id` al provider en metadata |

### 6.3 Contrato de Respuesta: Sync Status

```json
{
    "case_id": 5,
    "storage_type": "onedrive",
    "folder_sync_status": "synced",
    "folder_synced_at": "2026-03-21T15:30:00Z",
    "root_external_folder_id": "01ABCDEF...",
    "folders": [
        {
            "id": 1,
            "name": "Documentos Personales",
            "sync_status": "synced",
            "external_id": "01ABCDEF...",
            "synced_at": "2026-03-21T15:30:01Z"
        },
        {
            "id": 2,
            "name": "Formularios",
            "sync_status": "pending",
            "external_id": null,
            "synced_at": null
        }
    ]
}
```

---

## 7. Impacto en Frontend

### 7.1 Principio Clave: Laravel como Proxy

El frontend **nunca** comunica directamente con APIs cloud. Todo pasa por la API de Laravel. Esto ya esta implementado correctamente y **no debe cambiar**.

### 7.2 Cambios en Tipos TypeScript

Archivo: `resources/js/src/types/document.ts`

```typescript
export interface DocumentFolder {
    // ... campos existentes ...
    sync_status: 'pending' | 'synced' | 'failed';
    synced_at: string | null;
    external_id: string | null;
}
```

### 7.3 Cambios en Stores

**Tenant Store** (`resources/js/src/stores/tenant.ts`):
- Exponer `storageType` getter
- Exponer `isCloudStorage` computed

**Document Store** (`resources/js/src/stores/documentStore.ts`):
- Agregar `syncStatus: 'idle' | 'syncing' | 'synced' | 'error'` al state
- Action `syncFolders(caseId)` que llama al nuevo endpoint
- Action `fetchSyncStatus(caseId)`

### 7.4 Cambios en Componentes

| Componente | Cambio |
|---|---|
| `FolderTree.vue` | Icono de sync_status por carpeta (check verde = synced, reloj = pending, X roja = failed) |
| `DocumentToolbar.vue` | Boton "Sincronizar con Cloud" visible solo cuando `tenant.storage_type != 'local'`. Badge con provider actual |
| `CaseDocumentsTab.vue` | Banner de sync status cuando hay carpetas pendientes. Verificar `sync_status` al montar |
| `UploadDropzone.vue` | Sin cambios significativos. Manejo de error especifico cuando cloud provider no disponible |

### 7.5 Flujo de UI Completo

```
Usuario crea caso via CaseWizard
    |
    v
API crea caso + folders en BD (sincrono, respuesta inmediata)
    |
    v
Job SyncCaseFolderStructure dispatched (asincrono, background)
    |
    v
Frontend muestra caso con tab Documentos
    +-- Folders visibles inmediatamente (desde BD local)
    +-- Badge "Sincronizando..." si sync_status = pending
    +-- Badge "Sincronizado" cuando Job completa
    |
    v
Usuario sube documento
    +-- Upload va a API -> API usa provider del tenant
    +-- Si provider es cloud, usa external_id de la folder como parent
    +-- Documento aparece en grid inmediatamente
    +-- Si sync aun no completo, upload funciona con fallback path-based
```

### 7.6 Estados de Carga (UI Feedback)

```
Estado         | Icono      | Color    | Tooltip
==============================================
pending        | reloj      | warning  | "Sincronizando con {provider}..."
synced         | checkmark  | success  | "Sincronizado"
failed         | X          | danger   | "Error de sincronizacion. Click para reintentar"
not_applicable | (ninguno)  | -        | Almacenamiento local
```

---

## 8. Seguridad y Aislamiento

### 8.1 Aislamiento Multi-Tenant

Ya implementado correctamente:
- Trait `BelongsToTenant` en todos los modelos relevantes
- Validacion `validateCaseTenant()` en controllers
- Tokens OAuth scoped por `tenant_id`
- `StorageProviderFactory` resuelve provider por tenant del usuario autenticado

**Verificacion adicional necesaria**: En `SyncCaseFolderStructure` (Job), el contexto de `Auth` no esta disponible. El Job debe cargar el tenant explicitamente desde el case y usar `StorageProviderFactory::makeForTenant()`.

### 8.2 Gestion de Credenciales

Ya implementado:
- Secrets encriptados con `Crypt::encryptString()` en el modelo Tenant
- Fallback system -> tenant en `OAuthCredentialService`
- Token refresh con cache lock en `OAuthTokenService`

**Requisito adicional**: Solicitar seleccionar una plataforma de almacenamiento antes de crear el primer expediente. Implementar validacion en `CaseService::createCase()`:

```php
if ($tenant->storage_type !== 'local') {
    $token = OauthToken::where('tenant_id', $tenant->id)
        ->where('provider', $tenant->storage_type === 'onedrive' ? 'microsoft' : 'google')
        ->first();
    if (!$token) {
        throw new \RuntimeException('Cloud storage is configured but not connected. Connect in Admin > OAuth Settings.');
    }
}
```

### 8.3 Webhooks (Microsoft Graph / Google Drive)

**No recomendado en esta fase.**

Razon: Los webhooks requieren infraestructura adicional significativa:
- Endpoint publico con SSL
- Verificacion de firma
- Renovacion de subscripciones cada 3 dias (Microsoft) / 7 dias (Google)
- Manejo de duplicados y ordenamiento

VITE-IT es el unico escritor de archivos, por lo que el beneficio (notificacion push de cambios externos) no justifica la complejidad.

**Alternativa recomendada**: Endpoint de reconciliacion manual (`POST /cases/{case}/folders/sync`) + indicador de ultima sincronizacion en la UI.

---

## 9. Riesgos y Mitigaciones

| # | Riesgo | Probabilidad | Impacto | Mitigacion |
|---|---|---|---|---|
| R1 | APIs cloud lentas (14 carpetas x 5s = 70s) | Alta | Medio | Job asincrono. Crear carpetas en paralelo (3 concurrentes) |
| R2 | Token expirado durante Job largo | Media | Alto | Verificar/refresh token antes de cada batch de operaciones |
| R3 | Rate limiting Microsoft Graph (10K req/10min) | Baja | Alto | 15 requests por caso, muy por debajo del limite |
| R4 | Rate limiting Google Drive (300 writes/min) | Baja | Alto | 15 requests por caso, muy por debajo del limite |
| R5 | Job falla parcialmente (5 de 14 carpetas creadas) | Media | Medio | Idempotencia: verificar si carpeta existe antes de crear. Reanudar desde ultimo punto |
| R6 | Upload antes de sync completado | Media | Medio | Fallback: OneDrive crea dirs implicitos por path. Google Drive usa root si no hay parentId |
| R7 | Tenant cambia de provider con datos existentes | Baja | Alto | Validacion: no permitir cambio si hay documentos en cloud. Mostrar warning en UI |

---

## 10. Plan de Implementacion

### Fase 1: Base de Datos y Contrato - COMPLETADA

| # | Tarea | Archivo | Estado |
|---|---|---|---|
| 1.1 | Migracion: external_id + sync_status a document_folders | `database/migrations/2026_03_22_000001_add_external_fields_to_document_folders.php` | ✅ |
| 1.2 | Migracion: root_external_folder_id a cases | `database/migrations/2026_03_22_000002_add_folder_sync_to_cases.php` | ✅ |
| 1.3 | Ampliar DocumentStorageInterface con createFolder, deleteFolder, listFolder | `app/Contracts/DocumentStorageInterface.php` | ✅ |
| 1.4 | Actualizar modelo [[DocumentFolder]] con nuevos campos fillable | `app/Models/DocumentFolder.php` | ✅ |
| 1.5 | Actualizar modelo [[ImmigrationCase]] con nuevos campos fillable | `app/Models/ImmigrationCase.php` | ✅ |
| 1.6 | Ampliar upload() con parametro metadata opcional | `app/Contracts/DocumentStorageInterface.php` | ✅ |

### Fase 2: Providers - COMPLETADA

| # | Tarea | Archivo | Estado |
|---|---|---|---|
| 2.1 | Implementar createFolder/deleteFolder/listFolder en LocalStorageProvider | `app/Services/Storage/LocalStorageProvider.php` | ✅ |
| 2.2 | Implementar createFolder/deleteFolder/listFolder en OneDriveProvider | `app/Services/Storage/OneDriveProvider.php` | ✅ |
| 2.3 | Implementar createFolder/deleteFolder/listFolder en GoogleDriveProvider | `app/Services/Storage/GoogleDriveProvider.php` | ✅ |
| 2.4 | Delegar nuevos metodos en [[ResilientStorageProvider]] | `app/Services/Storage/ResilientStorageProvider.php` | ✅ |
| 2.5 | Agregar makeForTenant() a [[StorageProviderFactory]] | `app/Services/Storage/StorageProviderFactory.php` | ✅ |
| 2.6 | Modificar upload() para aceptar metadata (parent_external_id) | Todos los providers | ✅ |
| 2.7 | Desacoplar Auth::user() en providers para uso en Jobs | `OneDriveProvider.php`, `GoogleDriveProvider.php` | ✅ |

### Fase 3: Servicios y Jobs - COMPLETADA

| # | Tarea | Archivo | Estado |
|---|---|---|---|
| 3.1 | Crear CaseFolderSyncService | `app/Services/Document/CaseFolderSyncService.php` | ✅ |
| 3.2 | Crear Job SyncCaseFolderStructure | `app/Jobs/SyncCaseFolderStructure.php` | ✅ |
| 3.3 | Modificar CaseService::createCase() para crear folders + despachar sync | `app/Services/Case/CaseService.php` | ✅ |
| 3.4 | Modificar FolderService::createFolder() para sync cloud | `app/Services/Document/FolderService.php` | ✅ |
| 3.5 | Modificar FolderService::deleteFolder() para delete en cloud | `app/Services/Document/FolderService.php` | ✅ |
| 3.6 | Modificar [[DocumentService]]::uploadDocument() para usar parent_external_id | `app/Services/Document/DocumentService.php` | ✅ |
| 3.7 | Actualizar DocumentFolderResource con sync_status | `app/Http/Resources/DocumentFolderResource.php` | ✅ |
| 3.8 | Validacion en createCase: verificar cloud conectado antes de crear | `app/Services/Case/CaseService.php` | ✅ |

### Fase 4: Controllers y API - COMPLETADA

| # | Tarea | Archivo | Estado |
|---|---|---|---|
| 4.1 | Agregar endpoint POST /cases/{case}/folders/sync | `app/Http/Controllers/Api/DocumentFolderController.php` | ✅ |
| 4.2 | Agregar endpoint GET /cases/{case}/folders/sync-status | `app/Http/Controllers/Api/DocumentFolderController.php` | ✅ |
| 4.3 | Registrar nuevas rutas | `routes/api.php` | ✅ |

### Fase 5: Frontend - COMPLETADA

| # | Tarea | Archivo | Estado |
|---|---|---|---|
| 5.1 | Actualizar tipos TypeScript con sync_status y external_id | `resources/js/src/types/document.ts` | ✅ |
| 5.2 | Agregar storageType getter al tenant store | `resources/js/src/stores/tenant.ts` | ✅ |
| 5.3 | Agregar sync actions al document store | `resources/js/src/stores/documentStore.ts` | ✅ |
| 5.4 | Agregar endpoints sync al document service | `resources/js/src/services/documentService.ts` | ✅ |
| 5.5 | Indicadores de sync en FolderTree + FolderTreeItem | `FolderTree.vue`, `FolderTreeItem.vue` | ✅ |
| 5.6 | Boton sync y badge de provider en DocumentToolbar | `DocumentToolbar.vue` | ✅ |
| 5.7 | Banner de sync status en CaseDocumentsTab | `CaseDocumentsTab.vue` | ✅ |

### Fase 6: Testing - COMPLETADA (35 tests, 217 assertions)

| # | Tarea | Archivo | Estado |
|---|---|---|---|
| 6.1 | Unit tests LocalStorageProvider folder ops (11 tests) | `tests/Unit/Storage/LocalStorageProviderFolderTest.php` | ✅ |
| 6.2 | Unit tests CaseFolderSyncService (5 tests) | `tests/Unit/Services/CaseFolderSyncServiceTest.php` | ✅ |
| 6.3 | Feature test creacion de caso con sync dispatch (5 tests) | `tests/Feature/Api/CaseFolderSyncTest.php` | ✅ |
| 6.4 | Feature test upload con folder metadata (3 tests) | `tests/Feature/Api/DocumentUploadSyncTest.php` | ✅ |
| 6.5 | Feature test endpoints de sync manual (7 tests) | `tests/Feature/Api/FolderSyncEndpointTest.php` | ✅ |
| 6.6 | Feature test aislamiento multi-tenant (4 tests) | `tests/Feature/Api/FolderSyncEndpointTest.php` | ✅ |

---

## 11. Inventario Completo de Archivos

### Archivos Existentes a Modificar (22)

| # | Archivo | Tipo de Cambio |
|---|---|---|
| 1 | `app/Contracts/DocumentStorageInterface.php` | +3 metodos + metadata param en upload |
| 2 | `app/Models/DocumentFolder.php` | Agregar fillable (external_id, sync_status, etc.) |
| 3 | `app/Models/ImmigrationCase.php` | Agregar fillable (root_external_folder_id, etc.) |
| 4 | `app/Services/Storage/StorageProviderFactory.php` | Agregar makeForTenant() |
| 5 | `app/Services/Storage/LocalStorageProvider.php` | Implementar createFolder/deleteFolder/listFolder |
| 6 | `app/Services/Storage/OneDriveProvider.php` | Implementar 3 metodos + desacoplar Auth |
| 7 | `app/Services/Storage/GoogleDriveProvider.php` | Implementar 3 metodos + desacoplar Auth + parentId |
| 8 | `app/Services/Storage/ResilientStorageProvider.php` | Delegar 3 metodos nuevos |
| 9 | `app/Services/Document/DocumentService.php` | Pasar parent_external_id al upload |
| 10 | `app/Services/Document/FolderService.php` | Sync con cloud en create/delete |
| 11 | `app/Services/Case/CaseService.php` | createDefaultStructure + dispatch Job + validacion cloud |
| 12 | `app/Http/Controllers/Api/DocumentFolderController.php` | Endpoints sync y sync-status |
| 13 | `app/Http/Resources/DocumentFolderResource.php` | Agregar sync_status, external_id, synced_at |
| 14 | `routes/api.php` | Nuevas rutas de sync |
| 15 | `resources/js/src/types/document.ts` | Nuevos campos en DocumentFolder |
| 16 | `resources/js/src/stores/tenant.ts` | Exponer storageType getter |
| 17 | `resources/js/src/stores/documentStore.ts` | Sync actions y syncStatus state |
| 18 | `resources/js/src/services/documentService.ts` | Nuevos endpoints syncFolders, fetchSyncStatus |
| 19 | `resources/js/src/views/cases/components/documents/FolderTree.vue` | Indicadores de sync |
| 20 | `resources/js/src/views/cases/components/documents/DocumentToolbar.vue` | Boton sync + badge provider |
| 21 | `resources/js/src/views/cases/components/CaseDocumentsTab.vue` | Banner sync status |
| 22 | `resources/js/src/locales/en.json` y `es.json` | Nuevas claves i18n para sync |

### Archivos Nuevos a Crear (4)

| # | Archivo | Descripcion |
|---|---|---|
| 1 | `database/migrations/..._add_external_fields_to_document_folders.php` | Migracion: external_id, sync_status a document_folders |
| 2 | `database/migrations/..._add_folder_sync_to_cases.php` | Migracion: root_external_folder_id a cases |
| 3 | `app/Services/Document/CaseFolderSyncService.php` | Servicio de sincronizacion de carpetas con cloud |
| 4 | `app/Jobs/SyncCaseFolderStructure.php` | Job asincrono para crear estructura en cloud |

---

## 12. Diagrama de Flujo: Creacion de Caso con Sync

```
[Usuario: Crear Expediente]
        |
        v
[CaseService::createCase()]
        |
        +-- DB::transaction
        |       |
        |       +-- Crear caso en BD
        |       +-- Crear companions
        |       +-- Crear important_dates
        |       +-- Crear tasks
        |       +-- FolderService::createDefaultStructure()
        |       |       |
        |       |       +-- Crear 14 folders en BD (document_folders)
        |       |       +-- Cada folder con sync_status = 'pending'
        |       |
        |       +-- Commit
        |
        +-- afterCommit:
                |
                +-- [storage_type == 'local']?
                |       |
                |       YES: Marcar todas folders como synced, fin
                |       NO: continuar
                |
                +-- SyncCaseFolderStructure::dispatch(case_id)
                        |
                        v
                [Queue Worker procesa Job]
                        |
                        +-- Cargar caso + tenant
                        +-- StorageProviderFactory::makeForTenant($tenant)
                        +-- createFolder("26-PQ-VEGA-0003") -> root_external_id
                        +-- Guardar root_external_folder_id en case
                        +-- Para cada document_folder:
                        |       +-- createFolder(folder.name, root_external_id)
                        |       +-- Guardar external_id en folder
                        |       +-- Marcar sync_status = 'synced'
                        |
                        +-- Marcar case.folder_sync_status = 'synced'
                        +-- Marcar case.folder_synced_at = now()
```

---

## 13. Consideraciones Finales

### Orden de Dependencias

```
Fase 1 (BD + Contrato)
    |
    v
Fase 2 (Providers) -----> Fase 3 (Services + Jobs)
                                  |
                                  v
                           Fase 4 (Controllers + API)
                                  |
                                  v
                           Fase 5 (Frontend)
                                  |
                                  v
                           Fase 6 (Testing)
```

### Prerequisitos

1. El flujo OAuth debe estar funcional (Spec 28) - **en progreso, pendiente validar token exchange**
2. Las credenciales de Microsoft deben usar el **Secret Value** (no el Secret ID) - ver seccion de configuracion Azure
3. El queue worker debe estar corriendo para procesar Jobs: `php artisan queue:work`
4. Para testing, usar mocks de las APIs de Microsoft Graph y Google Drive (no llamadas reales)
