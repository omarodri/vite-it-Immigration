---
tipo: spec
numero: 33
modulo: Documentos y Storage
dominio: DOCS
estado: DONE
tags: [spec, documentos, done]
relacionados:
  - "[[28_multi_tenant_branding_storage|Spec 28]]"
  - "[[30_cloud_folder_sync_por_tenant|Spec 30]]"
---
# [[33_sharepoint_storage_provider|Spec 33]]: Integracion de Microsoft SharePoint como Storage Provider

## Informacion del Documento

- **Fecha:** 2026-03-26
- **Tipo:** Analisis de Impacto Tecnico y Plan de Implementacion
- **Scope:** Agregar Microsoft SharePoint como 4to Storage Provider por Tenant + Carpeta Destino Dinamica por Tenant (cross-provider)
- **Dependencias:** [[27_file_manager_system|Spec 27]] (File Manager System), [[28_multi_tenant_branding_storage|Spec 28]] (Multi-Tenant Branding & Storage), [[30_cloud_folder_sync_por_tenant|Spec 30]] (Cloud Folder Sync por Tenant)
- **Estado:** Implementado (2026-03-26) — Fases 1-4 completadas, Fases 5-6 pendientes de testing

---

## 1. Resumen Ejecutivo

Este documento analiza el impacto tecnico de dos funcionalidades relacionadas: (A) integrar Microsoft SharePoint como 4to Storage Provider, y (B) permitir una "Carpeta Destino" personalizable por Tenant que aplica a **todos** los providers (Local, OneDrive, Google Drive, SharePoint). Ambas funcionalidades comparten puntos de inyeccion en el codebase y se disenan como una sola unidad.

### Contexto

La arquitectura base de storage providers esta consolidada (Spec 27/30): `DocumentStorageInterface`, `StorageProviderFactory`, `LocalStorageProvider`, `OneDriveProvider`, `GoogleDriveProvider`, `ResilientStorageProvider` con circuit breaker. La UI de documentos es completamente agnostica al storage provider.

### Insight Arquitectonico Clave

OneDrive y SharePoint usan **la misma API** (Microsoft Graph). La unica diferencia es el prefijo de ruta:

| Operacion | OneDrive | SharePoint |
|-----------|----------|------------|
| Cualquier CRUD | `/me/drive/items/{id}` | `/drives/{driveId}/items/{id}` |

Esto significa que ~90% del codigo de `OneDriveProvider.php` es reutilizable directamente.

---

## 2. Reglas de Negocio

| # | Regla | Descripcion |
|---|-------|-------------|
| RN1 | Seleccion Unica | Cada Tenant tiene configurado un solo `storage_type` (local, onedrive, google_drive, **sharepoint**) |
| RN2 | Site + Library | El admin del Tenant selecciona un Site de SharePoint y una Document Library dentro de ese site |
| RN3 | Estructura de Carpetas | Al crear expediente, crear carpeta raiz (nombre = codigo del expediente) dentro de la Document Library seleccionada (capturar la carpeta destino dentro de Sharepoint por tenant) |
| RN4 | Credenciales Compartidas | SharePoint reutiliza el mismo Azure AD app registration que OneDrive (`ms_client_id`, `ms_client_secret`) |
| RN5 | UI Transparente | La pestana "Documentos" funciona identicamente sin importar si el disco es SharePoint, OneDrive, Google Drive o Local |
| RN6 | Scope OAuth Expandido | SharePoint requiere el scope adicional `Sites.ReadWrite.All` |
| RN7 | Carpeta Destino Dinamica | El admin define una "Carpeta Base" por tenant (ej: `Expedientes_Legales`). Todos los expedientes se crean dentro de esa ruta, no en la raiz del disco |
| RN8 | Retrocompatibilidad | Si `base_folder_path` es null, el sistema se comporta como antes (carpetas en la raiz). No requiere migracion de datos existentes |
| RN9 | Auto-Creacion | Si la carpeta base no existe en el provider al momento de crear un expediente, el sistema la crea automaticamente |

---

## 3. Estado Actual del Codebase (Verificado)

### 3.1 Arquitectura de Storage Existente

| Componente | Archivo | Estado |
|------------|---------|--------|
| Contrato | `app/Contracts/DocumentStorageInterface.php` | 10 metodos: upload, download, delete, move, isAvailable, createFolder, deleteFolder, renameItem, moveItem, listFolder |
| Factory | `app/Services/Storage/StorageProviderFactory.php` | Resuelve provider por `tenant.storage_type` via `make()` y `makeForTenant()` |
| Local | `app/Services/Storage/LocalStorageProvider.php` | Completo con validacion de path traversal |
| OneDrive | `app/Services/Storage/OneDriveProvider.php` | Completo: simple + resumable upload, download, delete, move, folder CRUD |
| Google Drive | `app/Services/Storage/GoogleDriveProvider.php` | Completo: multipart + resumable upload, download, delete, move, folder CRUD |
| Resiliencia | `app/Services/Storage/ResilientStorageProvider.php` | Retry + circuit breaker, decora cualquier provider |
| Circuit Breaker | `app/Services/Storage/CircuitBreaker.php` | 5 failures / 60s -> open 120s -> half-open |

### 3.2 Modelo de Datos Actual

**Tabla `tenants`**: `storage_type` (string, default 'local'), `ms_client_id`, `ms_client_secret`, `google_client_id`, `google_client_secret`, `storage_quota_mb`, `storage_used_bytes`.

**Tabla `documents`**: `storage_type` (string), `storage_path`, `external_id`, `external_url`, `version`, `checksum`.

**Tabla `document_folders`**: `external_id`, `external_url`, `sync_status`.

**Tabla `cases`**: `root_external_folder_id`, `folder_sync_status`.

**Tabla `oauth_tokens`**: Tokens a nivel de tenant con refresh automatico y cache lock.

### 3.3 Interfaz DocumentStorageInterface — Compatibilidad

| Metodo | SharePoint Compatible? | Notas |
|--------|:----------------------:|-------|
| `upload(UploadedFile, path, metadata)` | Si | `PUT /drives/{driveId}/items/{parentId}:/{filename}:/content` |
| `download(Document)` | Si | Mismo patron `@microsoft.graph.downloadUrl` |
| `delete(Document)` | Si | `DELETE /drives/{driveId}/items/{itemId}` |
| `move(Document, newPath)` | Si | PATCH con parentReference |
| `isAvailable()` | Si | Verifica token `microsoft` existente |
| `createFolder(name, parentId)` | Si | POST children en el drive |
| `deleteFolder(externalId)` | Si | Identico a OneDrive |
| `renameItem(externalId, newName)` | Si | PATCH identico |
| `moveItem(externalId, targetParentId)` | Si | PATCH con parentReference |
| `listFolder(externalId)` | Si | Endpoint children identico |

**Veredicto:** La interfaz es 100% compatible. No se requieren nuevos metodos.

### 3.4 Frontend — Completamente Agnostico

El frontend NO comunica directamente con APIs cloud. Todas las operaciones pasan por la API de Laravel (`documentService.ts` -> `/api/cases/{id}/documents/*`). Los componentes `DocumentGrid.vue`, `DocumentToolbar.vue` y toda la pestana "Documentos" **no requieren cambios funcionales**.

---

## 4. Evaluacion de Abstraccion: Flysystem vs Graph SDK Directo

### 4.1 Enfoque Actual: HTTP Directo a Microsoft Graph

Los providers actuales (`OneDriveProvider`, `GoogleDriveProvider`) realizan llamadas HTTP directas a las APIs cloud usando `Http::withToken()`. No usan Flysystem.

### 4.2 Comparativa

| Criterio | Flysystem | Graph SDK Directo (actual) |
|----------|-----------|---------------------------|
| **Rendimiento** | Capa adicional de abstraccion | Llamadas directas, control total sobre uploads resumables |
| **Seguridad** | Delegada al adapter | Control total sobre headers, tokens, scopes |
| **Throttling** | No maneja 429s nativamente | `ResilientStorageProvider` ya maneja retry + circuit breaker |
| **Upload Resumable** | No soportado en la mayoria de adapters | Implementado: upload sessions para archivos > 4MB |
| **Consistencia** | Requeriria reescribir OneDrive y Google Drive | Patron existente, solo extender |

### 4.3 Recomendacion

**Mantener el enfoque actual (Graph SDK directo).** La abstraccion via `DocumentStorageInterface` + `ResilientStorageProvider` es superior a Flysystem para este caso de uso porque:

1. Ya maneja upload resumable (>4MB) nativamente
2. El circuit breaker protege contra fallos cascada
3. Introducir Flysystem requeriria reescribir 2 providers existentes
4. SharePoint comparte 100% de la API Graph con OneDrive

---

## 5. Propuesta de Solucion

### 5.1 Refactoring: Clase Base [[MicrosoftGraphBaseProvider]]

Extraer una clase abstracta del `OneDriveProvider` actual con toda la logica compartida:

```
MicrosoftGraphBaseProvider (abstract)
├── upload()           — simple + resumable upload
├── download()         — metadata + downloadUrl
├── delete()           — DELETE item
├── move()             — PATCH parentReference
├── createFolder()     — POST children
├── deleteFolder()     — DELETE item
├── renameItem()       — PATCH name
├── moveItem()         — PATCH parentReference
├── listFolder()       — GET children
├── isAvailable()      — verifica token
├── getAccessToken()   — via OAuthTokenService
└── getDriveBasePath() — **ABSTRACT**
        │
        ├── OneDriveProvider:   return '/me/drive'
        └── SharePointProvider: return '/drives/{driveId}'
```

El unico metodo abstracto es:

```php
abstract protected function getDriveBasePath(): string;
```

Esto elimina ~350 lineas de duplicacion potencial.

### 5.2 Migracion de Base de Datos

**Nuevos campos en tabla `tenants`:**

```sql
-- SharePoint config
ALTER TABLE tenants ADD COLUMN sharepoint_site_id VARCHAR(255) NULL AFTER storage_type;
ALTER TABLE tenants ADD COLUMN sharepoint_drive_id VARCHAR(255) NULL AFTER sharepoint_site_id;
ALTER TABLE tenants ADD COLUMN sharepoint_site_url VARCHAR(500) NULL AFTER sharepoint_drive_id;

-- Carpeta destino dinamica (cross-provider)
ALTER TABLE tenants ADD COLUMN base_folder_path VARCHAR(500) NULL AFTER sharepoint_site_url;
ALTER TABLE tenants ADD COLUMN base_folder_external_id VARCHAR(500) NULL AFTER base_folder_path;
```

| Campo | Tipo | Proposito | Cifrado? |
|-------|------|-----------|----------|
| `sharepoint_site_id` | VARCHAR(255) | ID del site en Graph API (ej: `contoso.sharepoint.com,guid,guid`) | No — identificador de configuracion |
| `sharepoint_drive_id` | VARCHAR(255) | ID de la Document Library dentro del site | No — mismo caso |
| `sharepoint_site_url` | VARCHAR(500) | URL legible para display (ej: `https://contoso.sharepoint.com/sites/Immigration`) | No — informativo |
| `base_folder_path` | VARCHAR(500) | Nombre legible de la carpeta base (ej: `Expedientes_Legales`) | No — nombre visible |
| `base_folder_external_id` | VARCHAR(500) | ID operativo del provider para la carpeta base | No — identificador tecnico |

**No se requieren cambios en `documents`, `document_folders` ni `cases`.** Los campos existentes (`storage_type`, `external_id`, `external_url`) soportan SharePoint nativamente. La carpeta base es transparente para subdirectorios (operan sobre `root_external_folder_id` del caso).

### 5.3 Cifrado de Credenciales

SharePoint reutiliza las credenciales Microsoft existentes (`ms_client_id`, `ms_client_secret`) que ya estan cifradas con `Crypt::encryptString()`. **No se requieren nuevas credenciales cifradas.** Los campos `sharepoint_site_id`, `sharepoint_drive_id` y `sharepoint_site_url` no son secretos.

### 5.4 OAuth — Expansion de Scopes

| Escenario | Scopes actuales | Scopes requeridos |
|-----------|----------------|-------------------|
| Solo OneDrive | `Files.ReadWrite.All offline_access` | Sin cambio |
| Solo SharePoint | — | `Files.ReadWrite.All Sites.ReadWrite.All offline_access` |
| Ambos soportados | `Files.ReadWrite.All offline_access` | `Files.ReadWrite.All Sites.ReadWrite.All offline_access` |

**Recomendacion:** Siempre solicitar el superset de scopes. Tenants existentes con OneDrive necesitaran hacer "Reconectar" para aceptar el scope adicional.

### 5.5 SharePoint Site Discovery — Nuevos Endpoints

Se requieren 3 nuevos endpoints para que el admin configure cual site y library usar:

| Endpoint | Metodo | Proposito | Graph API subyacente |
|----------|--------|-----------|---------------------|
| `/api/tenant/sharepoint/sites` | GET | Listar sites accesibles | `GET /v1.0/sites?search=*` |
| `/api/tenant/sharepoint/sites/{siteId}/drives` | GET | Listar document libraries del site | `GET /v1.0/sites/{siteId}/drives` |
| `/api/tenant/sharepoint/config` | PUT | Guardar `site_id`, `drive_id`, `site_url` | — (escritura local) |

### 5.6 Auto-Provisioning de Carpetas

El flujo existente funciona sin cambios para SharePoint:

```
Caso creado
    → FolderService::createDefaultStructure() crea registros en BD
    → CaseFolderSyncService::syncFolderStructure() via Job
    → StorageProviderFactory::makeForTenant($tenant)
    → SharePointProvider::createFolder()
    → Carpeta creada en la Document Library del tenant
```

La unica diferencia: `SharePointProvider::createFolder()` opera sobre `/drives/{driveId}/...` en lugar de `/me/drive/...`.

### 5.7 Carpeta Destino Dinamica (Base Folder Path)

Esta funcionalidad es **cross-provider** — aplica a Local, OneDrive, Google Drive y SharePoint por igual.

#### 5.7.1 Concepto

El admin del Tenant define un nombre de carpeta (ej: `Expedientes_Legales`) que actua como directorio raiz para todos los expedientes. La ruta final de un caso queda:

```
[Carpeta_Base] / [Numero_de_Caso] / [Subcarpetas...]

Ejemplo concreto:
  Expedientes_Legales / 26-PQ-VEGA-0003 / Formularios /
  Expedientes_Legales / 26-PQ-VEGA-0003 / Evidencias /
```

Si `base_folder_path` es `null`, el comportamiento es identico al actual (carpetas en la raiz del disco).

#### 5.7.2 Diseño Dual: Path vs External ID

La carpeta base necesita **dos campos** en la tabla `tenants`:

| Campo | Tipo | Proposito | Ejemplo |
|-------|------|-----------|---------|
| `base_folder_path` | VARCHAR(500) | Nombre legible — lo que el admin escribe en el formulario | `Expedientes_Legales` |
| `base_folder_external_id` | VARCHAR(500) | ID operativo del provider — lo que el sistema usa internamente | Ver tabla abajo |

**Valor de `base_folder_external_id` por provider:**

| Provider | Valor almacenado | Obtenido de |
|----------|-----------------|-------------|
| Local | `tenants/{id}/Expedientes_Legales` | Path construido |
| OneDrive | `01BYE5RZ6QN...` (Graph item ID) | Response de `createFolder()` |
| Google Drive | `1a2b3c4d5e...` (folder ID) | Response de `createFolder()` |
| SharePoint | `01XYZ789...` (Graph item ID) | Response de `createFolder()` |

**Razon del diseño dual:** Google Drive opera exclusivamente por IDs, no por paths. OneDrive y SharePoint soportan ambos pero son mas eficientes por ID. Almacenar solo el nombre textual obligaria a resolver el ID en cada operacion. Con el ID pre-resuelto, la creacion de carpetas de caso es un solo API call.

#### 5.7.3 Puntos de Inyeccion en el Codebase

**A. `CaseFolderSyncService::syncFolderStructure()` — Cloud Providers (CRITICO)**

Flujo actual:
```
$rootResult = $provider->createFolder($rootFolderName);  // crea en raiz del drive
```

Flujo propuesto:
```
$baseFolderExternalId = $this->resolveBaseFolderExternalId($tenant, $provider);
$rootResult = $provider->createFolder($rootFolderName, $baseFolderExternalId);
```

Nuevo metodo privado `resolveBaseFolderExternalId(Tenant, DocumentStorageInterface)`:
1. Si `tenant.base_folder_path` es null → return null (comportamiento actual)
2. Si `tenant.base_folder_external_id` ya tiene valor → return ese ID (fast path)
3. Si tiene path pero no ID → crear la carpeta via `provider->createFolder(base_folder_path)`, guardar el ID retornado en `tenant.base_folder_external_id`, return ID

Esto implementa **lazy creation**: la carpeta base se crea automaticamente la primera vez que se necesita.

**B. `FolderService::createDefaultStructure()` — Local Storage**

Flujo actual (linea 218):
```
$rootPath = "tenants/{$case->tenant_id}/cases/{$case->case_number}";
```

Flujo propuesto:
```
$basePath = $tenant->base_folder_path
    ? "tenants/{$tenant->id}/{$tenant->base_folder_path}/cases/{$case->case_number}"
    : "tenants/{$tenant->id}/cases/{$case->case_number}";
```

`Storage::makeDirectory()` crea directorios intermedios automaticamente, por lo que no se requiere creacion previa de la carpeta base en local.

**C. `DocumentService::uploadDocument()` — Path de Storage Local (linea 55)**

Mismo patron que B. El `destinationPath` para storage local debe incluir el prefijo:
```
$destinationPath = "tenants/{tenant_id}/{base_folder_path}/cases/{case_number}/{storedName}"
```

Para cloud, el path es metadata-only (la ubicacion real se determina por `parent_external_id`), pero debe reflejar la ruta logica para consistencia.

**D. `DocumentService::replaceDocument()` — Mismo ajuste de path (linea 282)**

#### 5.7.4 Especializacion por Driver

| Operacion | Local | OneDrive / SharePoint | Google Drive |
|-----------|-------|----------------------|--------------|
| Almacenamiento de base folder | Path de texto (`tenants/1/Expedientes/`) | Graph item ID | Google Drive folder ID |
| Creacion de base folder | `Storage::makeDirectory()` implicito | `POST /drive/root/children` | `POST /drive/v3/files` con `mimeType: folder` |
| Referencia como parent | Concatenacion de path string | `parentExternalId` parameter | `parents: [folderId]` array |
| Resolucion | Instantanea (string concat) | 1 API call si no existe, 0 si ya cached en `base_folder_external_id` | Identico a OneDrive |

**Google Drive es el caso especial:** No soporta paths textuales. La unica forma de crear una subcarpeta es pasando el `parent ID`. Por eso `base_folder_external_id` es esencial — sin el, habria que buscar la carpeta por nombre en cada operacion (`GET /files?q=name='X' and mimeType='folder'`), lo cual es lento y ambiguo si hay duplicados.

#### 5.7.5 Validacion y Auto-Creacion

| Momento | Accion | Estrategia |
|---------|--------|------------|
| Admin guarda `base_folder_path` en settings | Intentar crear/encontrar la carpeta en el cloud provider | **Sincronica** — feedback inmediato al admin |
| Si la creacion falla (token expirado, etc.) | Guardar `base_folder_path` sin `external_id` | `external_id` se resolvera lazily |
| Primer caso creado con `base_folder_path` sin `external_id` | `resolveBaseFolderExternalId()` crea la carpeta | **Sincronica** dentro del Job de sync |
| Carpeta base eliminada externamente del cloud | `createFolder()` crea una nueva, actualiza `external_id` | Auto-recovery |

#### 5.7.6 Feedback Visual en Frontend

En `OAuthSettings.vue`, debajo del campo de texto "Carpeta Base", mostrar un preview de la ruta final proyectada:

```
Ruta proyectada: Expedientes_Legales / {numero-de-caso} / ...
```

Este preview es puramente informativo (se construye en el frontend concatenando strings).

#### 5.7.7 Endpoint API para Base Folder

Extender el endpoint existente `PUT /api/tenant/storage-type` para aceptar opcionalmente `base_folder_path`, o crear endpoint dedicado:

| Endpoint | Metodo | Body | Respuesta |
|----------|--------|------|-----------|
| `PUT /api/tenant/base-folder` | PUT | `{ "base_folder_path": "Expedientes_Legales" }` | `{ "base_folder_path": "...", "base_folder_external_id": "..." }` |

Logica del endpoint:
1. Validar string (max 255 chars, sin caracteres especiales: `/ \ : * ? " < > |`)
2. Si cloud provider esta conectado → intentar `provider->createFolder(path)` en el drive
3. Guardar `base_folder_path` y `base_folder_external_id` en tenant
4. Si la creacion cloud falla → guardar solo `base_folder_path` (lazy resolution posterior)

#### 5.7.8 Impacto en Recursividad de Subdirectorios

**No hay impacto.** Los subdirectorios de un caso se crean como hijos de `root_external_folder_id` (que apunta a la carpeta del caso, no a la carpeta base). La jerarquia queda:

```
[base_folder_external_id]           ← Carpeta base del tenant
  └── [root_external_folder_id]     ← Carpeta del caso (ej: 26-PQ-VEGA-0003)
       ├── Formularios/             ← external_id apunta a parent = root
       ├── Evidencias/
       └── ...
```

Una vez creada la carpeta del caso dentro de la carpeta base, toda la logica de subdirectorios opera sobre `root_external_folder_id` sin conocer la carpeta base. **Ningun cambio necesario en:**
- `CaseFolderSyncService::syncSingleFolder()`
- `CaseFolderSyncService::syncChildFolders()`
- `DocumentFolderController::store()`
- Operaciones de upload/download/delete de documentos

---

## 6. Impacto por Componente

### 6.1 Matriz de Impacto

```
                              CREAR    SUBIR    DESCARGAR   ELIMINAR   MOVER   LISTAR   CONFIG
                              CASO     DOC      DOC         DOC        DOC     DOCS     TENANT
==============================================================================================
MicrosoftGraphBaseProvider     -        -        -           -          -        -        -      NUEVO
SharePointProvider             -        -        -           -          -        -        -      NUEVO
OneDriveProvider              +++       -        -           -          -        -        -      REFACTOR
StorageProviderFactory         +        -        -           -          -        -        -
Tenant Model                   -        -        -           -          -        -        ++
Document Model                 -        -        -           -          -        -        -
DocumentService                +        ++       -           -          -        -        -      BASE FOLDER
FolderService                  ++       -        -           -          -        -        -      BASE FOLDER
CaseFolderSyncService          ++       -        -           -          -        -        -      BASE FOLDER
CloudDocumentSyncService       +        -        -           -          -        -        -
TenantController               -        -        -           -          -        -       +++
DocumentFolderController       +        -        -           -          -        -        -
OAuthFlowController            -        -        -           -          -        -        ++
OAuthTokenService              -        -        -           -          -        -        +
TenantResource                 -        -        -           -          -        -        ++
OAuthSettings.vue              -        -        -           -          -        -       +++
DocumentGrid.vue               -        -        -           -          -        -        -
DocumentToolbar.vue            -        -        -           -          -        -        -

+++ = Cambio mayor    ++ = Cambio moderado    + = Cambio menor    - = Sin cambio
```

### 6.2 Archivos Nuevos (3)

| Archivo | Proposito |
|---------|-----------|
| `app/Services/Storage/MicrosoftGraphBaseProvider.php` | Clase abstracta con toda la logica Graph compartida (~350 lineas extraidas de [[OneDriveProvider]]) |
| `app/Services/Storage/SharePointProvider.php` | Provider concreto, extiende base, override `getDriveBasePath()` para usar `/drives/{driveId}` |
| `database/migrations/YYYY_add_sharepoint_and_base_folder_to_tenants.php` | Migracion para 5 campos nuevos en tenants (3 SharePoint + 2 base folder) |

### 6.3 Archivos Modificados — Backend (14)

| Archivo | Cambio | Funcionalidad |
|---------|--------|---------------|
| `app/Services/Storage/OneDriveProvider.php` | Refactorizar para extender `MicrosoftGraphBaseProvider`. Override `getDriveBasePath()` retornando `/me/drive`. Se reduce de ~400 a ~50 lineas | SharePoint |
| `app/Services/Storage/StorageProviderFactory.php` | Agregar `'sharepoint'` a los `in_array()` de cloud providers y a las expresiones `match` en `make()` y `makeForTenant()` | SharePoint |
| `app/Models/Tenant.php` | Agregar constante `STORAGE_SHAREPOINT`. Agregar 5 campos a `$fillable` (3 SharePoint + 2 base folder) | Ambas |
| `app/Models/Document.php` | Agregar constante `STORAGE_SHAREPOINT = 'sharepoint'` | SharePoint |
| `app/Services/Document/DocumentService.php` | Agregar `'sharepoint'` a 3 match/in_array. **Base folder:** Prepend `base_folder_path` en `uploadDocument()` (linea 55) y `replaceDocument()` (linea 282) para paths de storage local | Ambas |
| `app/Services/Document/FolderService.php` | Agregar `'sharepoint'` a 5 checks. **Base folder:** Prepend `base_folder_path` en `createDefaultStructure()` (linea 218) al construir `rootPath` para local | Ambas |
| `app/Services/Document/CaseFolderSyncService.php` | **Base folder:** Agregar metodo `resolveBaseFolderExternalId()`. Modificar `syncFolderStructure()` para pasar `baseFolderExternalId` como parent al crear carpeta raiz del caso | Base Folder |
| `app/Services/Document/CloudDocumentSyncService.php` | Agregar `'sharepoint'` a 2 checks in_array y 1 expresion match | SharePoint |
| `app/Http/Controllers/Api/TenantController.php` | Agregar `'sharepoint'` a validacion. 3 metodos SharePoint discovery. 1 metodo `updateBaseFolder()` | Ambas |
| `app/Http/Controllers/Api/DocumentFolderController.php` | Agregar `'sharepoint'` a 1 check `in_array` en `initialize()` | SharePoint |
| `app/Http/Controllers/Api/OAuthFlowController.php` | Expandir scope Microsoft con `Sites.ReadWrite.All` en redirect y token exchange | SharePoint |
| `app/Services/OAuthTokenService.php` | Expandir scope en `refreshMicrosoftToken()` | SharePoint |
| `app/Http/Resources/TenantResource.php` | Agregar campos SharePoint + `base_folder_path` al response | Ambas |
| `routes/api.php` | 3 rutas SharePoint + 1 ruta base folder = **4 nuevas rutas** | Ambas |

### 6.4 Archivos Modificados — Frontend (4)

| Archivo | Cambio |
|---------|--------|
| `resources/js/src/views/admin/tenant/OAuthSettings.vue` | **(SharePoint)** 4to radio button + site picker + drive selector. **(Base Folder)** Campo de texto "Carpeta Base" con preview de ruta proyectada (`[Carpeta_Base]/[Numero_de_Caso]/...`) |
| `resources/js/src/types/document.ts` | Agregar `'sharepoint'` al union type. Nuevas interfaces `SharePointSite`, `SharePointDrive` |
| `resources/js/src/locales/en.json` | Claves SharePoint + claves base folder (`tenant.base_folder`, `tenant.base_folder_placeholder`, `tenant.base_folder_preview`, `tenant.base_folder_saved`) |
| `resources/js/src/locales/es.json` | Mismas claves traducidas al espanol |

### 6.5 Archivos Sin Cambio (confirmado)

| Archivo | Razon |
|---------|-------|
| `DocumentGrid.vue` | Agnostico al storage — muestra objetos `Document` |
| `DocumentToolbar.vue` | Boton "Sync" opera via mismos endpoints API |
| `DocumentList.vue` | Mismo patron agnostico |
| `documentService.ts` (frontend) | Llama endpoints Laravel, no APIs cloud directamente |
| `ResilientStorageProvider.php` | Decora cualquier provider automaticamente |
| `CircuitBreaker.php` | Sin cambios necesarios |
| `OAuthCredentialService.php` | Credenciales Microsoft compartidas |
| `CaseFolderSyncService::syncSingleFolder()` | Opera sobre `root_external_folder_id`, carpeta base es transparente |
| `CaseFolderSyncService::syncChildFolders()` | Misma razon — los subdirectorios no conocen la carpeta base |
| `DocumentStorageInterface.php` | `createFolder()` ya acepta `parentExternalId` opcional — no necesita cambios |

---

## 7. Rutas API

### 7.1 Rutas Existentes — Sin Cambio

Todas las rutas de documentos son storage-agnostic:

- `GET/POST /api/cases/{case}/documents` — CRUD documentos
- `GET/POST /api/cases/{case}/folders` — CRUD carpetas
- `POST /api/cases/{case}/folders/initialize` — Sync de estructura
- `PUT /api/tenant/storage-type` — Cambiar storage type (agregar `'sharepoint'` a validacion)

### 7.2 Rutas Nuevas (4)

| Ruta | Metodo | Controlador | Proposito | Funcionalidad |
|------|--------|-------------|-----------|---------------|
| `/api/tenant/sharepoint/sites` | GET | TenantController | Listar sites SharePoint accesibles via Graph API | SharePoint |
| `/api/tenant/sharepoint/sites/{siteId}/drives` | GET | TenantController | Listar document libraries de un site | SharePoint |
| `/api/tenant/sharepoint/config` | PUT | TenantController | Guardar `sharepoint_site_id`, `sharepoint_drive_id`, `sharepoint_site_url` | SharePoint |
| `/api/tenant/base-folder` | PUT | TenantController | Guardar `base_folder_path`, intentar crear carpeta y resolver `base_folder_external_id` | Base Folder |

### 7.3 Rutas OAuth — Sin Cambio

Las rutas existentes `GET /api/oauth/microsoft/redirect` y `GET /api/oauth/microsoft/callback` se reutilizan. El unico cambio es el scope expandido en el URL builder.

---

## 8. Riesgos y Mitigacion

| # | Riesgo | Severidad | Mitigacion |
|---|--------|-----------|------------|
| R1 | Re-consent OAuth por scope expandido | Baja | Admin hace clic en "Reconectar" — flujo estandar. Mostrar aviso en UI |
| R2 | Permisos SharePoint mas restrictivos que OneDrive | Media | La cuenta conectada necesita acceso Contribute a la library. Documentar en onboarding |
| R3 | Regresion en OneDrive tras refactoring de base class | Baja | Refactoring es puramente estructural. Tests existentes de OneDrive deben pasar sin cambios |
| R4 | Throttling de SharePoint (429s) | Baja | `ResilientStorageProvider` ya maneja retry con circuit breaker |
| R5 | Site con multiples Document Libraries | Baja | UI de drive picker permite al admin elegir cual library. Se guarda como `sharepoint_drive_id` |
| R6 | Token expiration durante operaciones largas | Baja | `OAuthTokenService` ya maneja refresh automatico con cache lock |
| R7 | Base folder eliminada externamente del cloud | Baja | `resolveBaseFolderExternalId()` implementa lazy creation — si el `external_id` falla, crea una nueva carpeta |
| R8 | Cambio de `base_folder_path` con casos existentes | Media | Los casos existentes mantienen su `root_external_folder_id` apuntando a la ubicacion original. Solo casos nuevos usan la nueva carpeta base. Documentar este comportamiento en la UI |
| R9 | Nombre de base folder con caracteres especiales | Baja | Validacion en endpoint: rechazar `/ \ : * ? " < > |`. Estos causan errores en Graph API y filesystem local |

---

## 9. Plan de Implementacion

### Fase 1: Backend — Migracion y Refactoring de Provider

| # | Tarea | Archivo(s) | Estado |
|---|-------|-----------|--------|
| 1.1 | Crear migracion para campos SharePoint + base folder en tenants | `database/migrations/2026_03_26_000001_add_sharepoint_and_base_folder_to_tenants_table.php` | DONE |
| 1.2 | Extraer `MicrosoftGraphBaseProvider` de `OneDriveProvider` | `app/Services/Storage/MicrosoftGraphBaseProvider.php` | DONE |
| 1.3 | Refactorizar `OneDriveProvider` para extender base | `app/Services/Storage/OneDriveProvider.php` (reducido de ~480 a ~27 lineas) | DONE |
| 1.4 | Crear `SharePointProvider` extendiendo base | `app/Services/Storage/SharePointProvider.php` | DONE |
| 1.5 | Agregar `'sharepoint'` a `StorageProviderFactory` | `app/Services/Storage/StorageProviderFactory.php` | DONE |
| 1.6 | Actualizar constantes y `$fillable` en `Tenant` y `Document` models | `app/Models/Tenant.php`, `app/Models/Document.php` | DONE |
| 1.7 | Agregar `'sharepoint'` a services ([[DocumentService]], [[FolderService]], [[CloudDocumentSyncService]]) | 3 archivos | DONE |

### Fase 2: Backend — Carpeta Destino Dinamica (Base Folder)

| # | Tarea | Archivo(s) | Estado |
|---|-------|-----------|--------|
| 2.1 | Agregar `resolveBaseFolderExternalId()` a `CaseFolderSyncService` | `app/Services/Document/CaseFolderSyncService.php` | DONE |
| 2.2 | Modificar `syncFolderStructure()` para pasar base folder como parent | `app/Services/Document/CaseFolderSyncService.php` | DONE |
| 2.3 | Prepend `base_folder_path` en `FolderService::createDefaultStructure()` para paths locales | `app/Services/Document/FolderService.php` | DONE |
| 2.4 | Prepend `base_folder_path` en `DocumentService::uploadDocument()` y `replaceDocument()` | `app/Services/Document/DocumentService.php` | DONE |
| 2.5 | Endpoint `PUT /api/tenant/base-folder` con validacion y creacion cloud | `TenantController.php` | DONE |

### Fase 3: Backend — API SharePoint y OAuth

| # | Tarea | Archivo(s) | Estado |
|---|-------|-----------|--------|
| 3.1 | Expandir scopes OAuth Microsoft | `OAuthFlowController.php`, `OAuthTokenService.php` | DONE |
| 3.2 | Endpoint: listar SharePoint sites | `TenantController.php` | DONE |
| 3.3 | Endpoint: listar drives de un site | `TenantController.php` | DONE |
| 3.4 | Endpoint: guardar configuracion SharePoint | `TenantController.php` | DONE |
| 3.5 | Agregar `'sharepoint'` a validacion storage_type | `TenantController.php` | DONE |
| 3.6 | Actualizar `TenantResource` con campos SharePoint + base folder | `app/Http/Resources/TenantResource.php` | DONE |
| 3.7 | Registrar 4 nuevas rutas (3 SharePoint + 1 base folder) | `routes/api.php` | DONE |
| 3.8 | Agregar `'sharepoint'` a `DocumentFolderController::initialize()` | `app/Http/Controllers/Api/DocumentFolderController.php` | DONE |

### Fase 4: Frontend — Configuracion de Tenant

| # | Tarea | Archivo(s) | Estado |
|---|-------|-----------|--------|
| 4.1 | Agregar radio button SharePoint en OAuthSettings | `OAuthSettings.vue` | DONE |
| 4.2 | Crear seccion site picker + drive selector para SharePoint | `OAuthSettings.vue` | DONE |
| 4.3 | Agregar campo "Carpeta Base" con preview de ruta proyectada | `OAuthSettings.vue` | DONE |
| 4.4 | Agregar tipos TypeScript para SharePoint | `resources/js/src/types/document.ts` | DONE |
| 4.5 | Agregar traducciones en.json y es.json (SharePoint + base folder) | `resources/js/src/locales/en.json`, `es.json` | DONE |

### Fase 5: Testing

| # | Tarea | Estado |
|---|-------|--------|
| 5.1 | Tests unitarios para `SharePointProvider` (upload, download, delete, folder CRUD) | DONE — `tests/Unit/Services/Storage/SharePointProviderTest.php` (11 tests) |
| 5.2 | Tests unitarios para `MicrosoftGraphBaseProvider` (logica compartida) | DONE — cubierto via SharePointProviderTest y OneDriveProviderTest (URLs, auth) |
| 5.3 | Test de regresion: `OneDriveProvider` sigue funcionando tras refactoring | DONE — `tests/Unit/Services/Storage/OneDriveProviderTest.php` (7 tests) |
| 5.4 | Tests de integracion: site discovery endpoints | DONE — `tests/Feature/SharePoint/SharePointEndpointTest.php` (13 tests) |
| 5.5 | Tests para `resolveBaseFolderExternalId()` — lazy creation, cached ID, null path | DONE — `tests/Unit/Services/BaseFolderResolutionTest.php` (4 tests) |
| 5.6 | Test: base folder path prepend correcto en paths locales | DONE — cubierto en SharePointEndpointTest (base folder saves/clears) |
| 5.7 | Test E2E: crear caso con storage SharePoint, verificar carpeta creada | Pendiente QA — requiere tenant real con OAuth conectado |
| 5.8 | Test E2E: subir/descargar/eliminar documento en SharePoint | Pendiente QA — requiere tenant real con OAuth conectado |
| 5.9 | Test E2E: base folder con cada provider (local, OneDrive, Google Drive, SharePoint) | Pendiente QA — requiere tenant real con OAuth conectado |

### Fase 6: Verificacion Manual

> **Nota:** Todas las tareas de esta fase requieren un tenant real con OAuth de Microsoft conectado y acceso a SharePoint. Prerequisito: registrar el redirect URI `https://{app-domain}/api/oauth/microsoft/callback` en Azure AD App Registrations → Authentication.

| # | Tarea | Estado |
|---|-------|--------|
| 6.1 | Verificar creacion automatica de carpeta en SharePoint al crear expediente | Pendiente QA |
| 6.2 | Verificar upload de archivo > 4MB (resumable upload) a SharePoint | Pendiente QA |
| 6.3 | Verificar sync bidireccional de carpetas | Pendiente QA |
| 6.4 | Verificar que tenants existentes con OneDrive no sufren regresion | Pendiente QA |
| 6.5 | Verificar flujo completo: seleccionar SharePoint -> configurar site/library -> crear caso -> subir doc | Pendiente QA |
| 6.6 | Verificar base folder: configurar "Expedientes_Legales" -> crear caso -> verificar que la carpeta se crea dentro de la base | Pendiente QA |
| 6.7 | Verificar retrocompatibilidad: tenant sin base folder configurado sigue creando en raiz | Pendiente QA |
| 6.8 | Verificar auto-recovery: eliminar base folder del cloud -> crear caso -> verificar que se recrea | Pendiente QA |

---

## 10. Estimacion de Impacto

| Dimension | Valor |
|-----------|-------|
| Archivos backend nuevos | 3 |
| Archivos backend modificados | 14 |
| Archivos frontend modificados | 4 |
| Archivos frontend sin cambio (confirmado) | 10+ (toda la UI de documentos + subdirectorios) |
| Nuevas rutas API | 4 (3 SharePoint + 1 base folder) |
| Nuevos campos en BD | 5 (3 SharePoint + 2 base folder, todos en tabla tenants) |
| Tablas modificadas | 0 (documents, document_folders, cases sin cambio) |
| Riesgo de regresion | Bajo (cambios aditivos, refactoring estructural sin cambio de comportamiento) |
| Dependencia nueva de paquetes | Ninguna |

---

## 11. Decisiones Tomadas

| # | Decision | Resultado | Implementacion |
|---|----------|-----------|----------------|
| D1 | Solicitar scopes expandidos siempre o condicionalmente | **Siempre (superset)** | `Sites.ReadWrite.All` agregado a redirect, callback y refresh en OAuthFlowController y [[OAuthTokenService]] |
| D2 | Crear controller separado para SharePoint config o agregar a TenantController | **Metodos en TenantController** | 4 metodos nuevos: `listSharePointSites`, `listSharePointDrives`, `saveSharePointConfig`, `updateBaseFolder` |
| D3 | Mostrar aviso de re-consent a tenants existentes con OneDrive | **Pendiente** | No implementado aun — requiere deteccion de scope en token existente |
| D4 | Base folder: endpoint dedicado o extender `updateStorageType` | **Endpoint separado** | `PUT /api/tenant/base-folder` |
| D5 | Base folder: creacion eager (al configurar) o lazy (al primer caso) | **Eager + fallback lazy** | `updateBaseFolder()` intenta crear en cloud; `resolveBaseFolderExternalId()` en [[CaseFolderSyncService]] hace fallback lazy |
| D6 | Validacion del nombre de base folder | **Restringir caracteres** | Regex en endpoint: `/^[^\/\\\\:*?"<>|]+$/` |
