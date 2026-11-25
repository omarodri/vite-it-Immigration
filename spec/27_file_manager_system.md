---
tipo: spec
numero: 27
modulo: Documentos y Storage
dominio: DOCS
estado: DONE
tags: [spec, documentos, done]
relacionados:
  - "[[28_multi_tenant_branding_storage|Spec 28]]"
  - "[[30_cloud_folder_sync_por_tenant|Spec 30]]"
  - "[[56_seleccion_dinamica_directorios_wizard|Spec 56]]"
---
# [[27_file_manager_system|Spec 27]]: Sistema de Gestion de Archivos (File Manager)

## Informacion del Documento

- **Fecha:** 2026-03-20
- **Tipo:** Analisis de Impacto Arquitectonico y de Seguridad
- **Scope:** Modulo de documentos dentro de la pestana "Documentos" de cada expediente
- **Estado:** En Progreso - Fases 1-5 implementadas (local + cloud storage + OAuth + frontend UI + hardening)

---

## 1. Resumen Ejecutivo

Este documento analiza la arquitectura, seguridad y estrategia de implementacion para un sistema de gestion de archivos integrado en la vista de detalle de expediente (`cases/show.vue`). El sistema debe soportar almacenamiento local, OneDrive (Microsoft Graph API) y Google Drive, con aislamiento por tenant y por caso.

### Estado Actual de la Base de Datos

Las tablas `documents` y `document_folders` ya existen en migracion `2026_02_08_221206`, asi como `oauth_tokens` en `2026_02_08_221209`. Sin embargo, **no existen modelos Eloquent** (`Document.php`, `DocumentFolder.php`), **ni controladores**, **ni servicios** para gestion de archivos. La tabla `tenants` ya almacena credenciales OAuth encriptadas (ms_client_id/secret, google_client_id/secret) y el servicio `OAuthCredentialService` ya resuelve credenciales con fallback tenant -> sistema.

El tab "Documents" en `cases/show.vue` (linea 334) es actualmente un placeholder con mensaje "coming soon".

---

## 2. Analisis de Almacenamiento: Flysystem vs SDKs Nativos

### 2.1 Evaluacion de Flysystem (League\Flysystem v3)

| Aspecto | Flysystem | SDKs Nativos (Graph SDK / Google Client) |
|---------|-----------|------------------------------------------|
| **Abstraccion** | Interfaz unificada `read/write/delete/list` | API completa con features avanzados |
| **Mover directorios entre nubes** | NO soportado (no hay `move` cross-disk) | Requiere download + re-upload manual igualmente |
| **Streaming** | Soporta streams para read/write | Soporta streams + resumable uploads |
| **Metadata** | Basica (size, mimetype, timestamp) | Completa (permisos, thumbnails, versiones) |
| **OAuth token refresh** | No maneja tokens; requiere wrapper | Integrado en SDKs |
| **Resumable/Chunked uploads** | No nativo | Soportado nativamente en ambos SDKs |
| **Webhooks/Change notifications** | No aplica | Microsoft: Subscriptions API. Google: Push Notifications |
| **URLs temporales** | Solo si el adapter lo implementa | Nativo: sharing links / presigned URLs |
| **Complejidad de integracion** | Baja (adapter pattern) | Media-Alta (cada SDK tiene su API) |
| **Paquetes Laravel** | `league/flysystem-aws-s3-v3` (no hay adapter oficial para OneDrive/GDrive en v3) | `microsoft/microsoft-graph`, `google/apiclient` |

### 2.2 Problema Critico con Flysystem

No existen adapters oficiales de Flysystem v3 para OneDrive ni Google Drive. Los paquetes comunitarios estan desactualizados o incompletos. Esto implica:

1. Escribir adapters custom para Flysystem v3 (alto costo de mantenimiento).
2. Perder features avanzados (resumable uploads, thumbnails, sharing links).
3. No poder gestionar el ciclo de vida de OAuth tokens dentro del adapter.

### 2.3 Recomendacion: Strategy Pattern con SDKs Nativos + Flysystem Solo para Local

```
DocumentStorageInterface (Contract)
    |
    +-- LocalStorageProvider    --> Flysystem (disco 'local' de Laravel)
    +-- OneDriveProvider        --> microsoft/microsoft-graph SDK
    +-- GoogleDriveProvider     --> google/apiclient SDK
```

**Justificacion:**
- Flysystem es idoneo para almacenamiento local (ya configurado en `config/filesystems.php`).
- Para nubes, los SDKs nativos ofrecen resumable uploads, token refresh automatico, y metadata enriquecida.
- El Strategy Pattern permite agregar providers futuros (Dropbox, S3) sin modificar el core.
- El tenant determina que provider usar segun sus credenciales configuradas.

---

## 3. Diseno de Componentes Backend

### 3.1 Diagrama de Componentes

```
[Vue FileManager.vue]
        |
        | REST API (JSON)
        v
[DocumentController]  ──>  [DocumentFolderController]
        |                            |
        v                            v
[DocumentService]            [FolderService]
        |
        | resolve provider by storage_type
        v
[DocumentStorageInterface]
    |         |              |
    v         v              v
[Local]   [OneDrive]    [GoogleDrive]
    |         |              |
    v         v              v
Flysystem  Graph SDK    Google Client
            |              |
            v              v
       [OAuthTokenService]
            |
            v
       oauth_tokens table
       tenants table (credentials)
```

### 3.2 Contratos (Interfaces)

```php
// app/Contracts/DocumentStorageInterface.php

interface DocumentStorageInterface
{
    /**
     * Upload a file.
     * Returns: ['external_id' => ?string, 'storage_path' => string, 'external_url' => ?string, 'size' => int]
     */
    public function upload(UploadedFile $file, string $destinationPath): array;

    /**
     * Download a file. Returns a StreamedResponse or temporary URL string.
     */
    public function download(Document $document): StreamedResponse|string;

    /**
     * Delete a file.
     */
    public function delete(Document $document): bool;

    /**
     * Move a file to a different path/folder.
     */
    public function move(Document $document, string $newPath): array;

    /**
     * Get a temporary preview URL (for images/PDFs in browser).
     */
    public function getPreviewUrl(Document $document, int $expiresInMinutes = 30): ?string;

    /**
     * Check if the provider is available/configured for the current tenant.
     */
    public function isAvailable(): bool;
}
```

### 3.3 Modelos Eloquent (Nuevos)

```php
// app/Models/Document.php
class Document extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'case_id', 'folder_id', 'uploaded_by',
        'name', 'original_name', 'mime_type', 'size',
        'category', 'storage_type', 'storage_path',
        'external_id', 'external_url',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    // Relationships: tenant, case, folder, uploader
    // Scopes: byCase, byFolder, byCategory, byStorageType
}
```

```php
// app/Models/DocumentFolder.php
class DocumentFolder extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'case_id', 'parent_id', 'name', 'sort_order',
    ];

    // Relationships: tenant, case, parent, children, documents
    // Scope: byCase, roots (parent_id IS NULL)
}
```

### 3.4 Servicio de Tokens OAuth

```php
// app/Services/OAuthTokenService.php
class OAuthTokenService
{
    /**
     * Get a valid access token for a provider/user, refreshing if expired.
     * Uses oauth_tokens table + tenant credentials from OAuthCredentialService.
     */
    public function getValidToken(User $user, string $provider): ?string;

    /**
     * Refresh an expired token using the refresh_token + tenant client_secret.
     */
    public function refreshToken(OauthToken $token): OauthToken;

    /**
     * Store/update tokens after OAuth callback.
     */
    public function storeToken(User $user, string $provider, array $tokenData): OauthToken;
}
```

**Flujo de refresh de tokens:**

```
getValidToken()
    |
    +-- Token exists and not expired? --> Return access_token
    |
    +-- Token expired but has refresh_token?
    |       |
    |       +-- Get client_secret from OAuthCredentialService (tenant -> system fallback)
    |       +-- POST to provider's token endpoint with refresh_token + client_secret
    |       +-- Update oauth_tokens row with new access_token, expires_at
    |       +-- Return new access_token
    |
    +-- No token at all? --> Return null (user must re-authenticate via OAuth flow)
```

**Riesgo: Token refresh durante cargas pesadas.** Si multiples requests intentan refrescar simultaneamente, se puede producir un race condition donde el primer refresh invalida el refresh_token para los demas.

**Mitigacion:** Usar cache lock (`Cache::lock("oauth_refresh_{$userId}_{$provider}", 10)`) para serializar refreshes. Los requests concurrentes esperan al lock y luego leen el token ya refrescado de la DB.

### 3.5 Estructura de Carpetas Automatica al Crear Expediente

Al crear un `ImmigrationCase`, un observer o listener debe crear la estructura base de carpetas:

```
[Caso: IMM-2026-0042]
    +-- Archivo/           (category: archive)
    +-- Cartas/            (category: letters)
    +-- Comunicaciones/    (category: communication)
    +-- Contrato/          (category: contract)
    +-- Contrato/          (category: accounting)
    +-- Documentos/        (category: documents)
    +-- Enlaces/           (category: links)
    +-- Questionarios/     (category: Questionary)
    +-- Formularios/       (category: forms)
    +-- Admision/          (category: admission)
    +-- Historial/         (category: history)
    +-- Evidencia/         (category: evidence)
    +-- Audiencias/        (category: hearing)
    +-- Otros/             (category: other)
```

Implementacion recomendada: `CaseObserver::created()` que invoca `FolderService::createDefaultStructure($case)`.

**Nota:** Los nombres de carpetas deben ser traducibles. Almacenar el `category` como clave, y el `name` como valor localizado en el idioma del caso o del tenant.
**Nota2:** El uso de los 3 modos de almacenamiento: 1.local, 2.OneDrive (Microsoft Graph API) y 3.Google Drive, es excluyente (sólo se puede utilizar uno modo). Esta elección estará configurada en un nuevo campo en la tabla tenant. 

---

## 4. Diseno de API REST

### 4.1 Endpoints de Carpetas

| Metodo | URI | Accion | Permiso |
|--------|-----|--------|---------|
| `GET` | `/api/cases/{case}/folders` | Listar arbol de carpetas del caso | `documents.view` |
| `POST` | `/api/cases/{case}/folders` | Crear subcarpeta | `documents.create` |
| `PATCH` | `/api/cases/{case}/folders/{folder}` | Renombrar carpeta | `documents.update` |
| `DELETE` | `/api/cases/{case}/folders/{folder}` | Eliminar carpeta (solo si vacia) | `documents.delete` |
| `PATCH` | `/api/cases/{case}/folders/reorder` | Reordenar carpetas | `documents.update` |

### 4.2 Endpoints de Documentos

| Metodo | URI | Accion | Permiso |
|--------|-----|--------|---------|
| `GET` | `/api/cases/{case}/documents` | Listar documentos (filtro por folder_id, category) | `documents.view` |
| `POST` | `/api/cases/{case}/documents` | Subir documento(s) | `documents.create` |
| `GET` | `/api/cases/{case}/documents/{document}` | Metadata del documento | `documents.view` |
| `GET` | `/api/cases/{case}/documents/{document}/download` | Descargar archivo | `documents.view` |
| `GET` | `/api/cases/{case}/documents/{document}/preview` | URL temporal de preview | `documents.view` |
| `PATCH` | `/api/cases/{case}/documents/{document}` | Actualizar metadata (nombre, categoria, carpeta) | `documents.update` |
| `POST` | `/api/cases/{case}/documents/{document}/replace` | Reemplazar archivo (nueva version) | `documents.update` |
| `DELETE` | `/api/cases/{case}/documents/{document}` | Soft delete | `documents.delete` |
| `POST` | `/api/cases/{case}/documents/{document}/move` | Mover a otra carpeta | `documents.update` |

### 4.3 Endpoints de OAuth (para conectar cuenta de usuario a cloud)

| Metodo | URI | Accion |
|--------|-----|--------|
| `GET` | `/api/oauth/{provider}/redirect` | Generar URL de autorizacion OAuth |
| `GET` | `/api/oauth/{provider}/callback` | Callback: almacenar tokens en oauth_tokens |
| `GET` | `/api/oauth/status` | Estado de conexion del usuario a cada provider |
| `DELETE` | `/api/oauth/{provider}/disconnect` | Revocar tokens y eliminar de oauth_tokens |

### 4.4 Estrategia de Descarga: Streaming vs URLs Temporales

| Escenario | Estrategia | Justificacion |
|-----------|------------|---------------|
| **Local storage** | StreamedResponse via Laravel | Archivo en disco del servidor, streaming directo |
| **OneDrive** | URL temporal (`@microsoft.graph.downloadUrl`) | Graph API retorna URL de descarga directa con 1h de validez. Evita proxy. |
| **Google Drive** | URL temporal (`webContentLink`) o proxy stream | Para archivos pequenos: URL directa. Para privados: proxy stream desde backend. |
| **Preview (imagenes/PDF)** | URL temporal desde cloud provider | No pasar bytes por el backend para previews. |

**Regla general:** Siempre preferir redirigir al cliente a una URL temporal del provider para evitar que el backend sea bottleneck de ancho de banda.

### 4.5 Chunked Uploads para Archivos Grandes

Para archivos > 4MB (limite recomendado que se debe guardar como configuración tabla tendant):

**OneDrive:** Upload session via `POST /drive/items/{parent-id}:/{filename}:/createUploadSession`, luego chunks de 5-10MB via `PUT` al uploadUrl retornado.

**Google Drive:** Resumable upload via `POST https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable`, luego chunks via `PUT` con header `Content-Range`.

**Flujo frontend:**
1. Frontend solicita upload session al backend: `POST /api/cases/{case}/documents/upload-session`
2. Backend crea session en el provider y retorna `uploadUrl` + `sessionId`
3. Frontend sube chunks directamente al provider (bypassing backend)
4. Al completar, frontend notifica al backend: `POST /api/cases/{case}/documents/upload-complete`
5. Backend crea el registro en tabla `documents` con el `external_id`

**Beneficio:** El backend no maneja los bytes del archivo grande, solo la coordinacion.

---

## 5. Diseno Frontend (Vue.js)

### 5.1 Arquitectura de Componentes

```
cases/show.vue (tab: documents)
    |
    +-- CaseDocumentsTab.vue (orchestrator)
            |
            +-- FolderTree.vue          (sidebar: arbol de carpetas, drag-drop)
            +-- DocumentBreadcrumb.vue  (navegacion de ruta actual)
            +-- DocumentToolbar.vue     (acciones: upload, crear carpeta, filtros)
            +-- DocumentGrid.vue        (lista/grid de documentos en carpeta actual)
            |       |
            |       +-- DocumentCard.vue    (preview thumbnail, nombre, acciones)
            |       +-- DocumentRow.vue     (variante lista)
            |
            +-- UploadDropzone.vue      (drag & drop zone, progress bars)
            +-- DocumentPreviewModal.vue (preview inline de imagenes/PDF)
            +-- ReplaceDocumentModal.vue (confirmar reemplazo de version)
            +-- MoveDocumentModal.vue   (seleccionar carpeta destino)
```

### 5.2 Estado (Pinia Store)

```typescript
// stores/document.ts

interface DocumentState {
    folders: DocumentFolder[];       // arbol completo del caso
    currentFolderId: number | null;  // carpeta actual
    documents: Document[];           // documentos en carpeta actual
    isLoading: boolean;
    uploadQueue: UploadItem[];       // archivos en cola de subida
    viewMode: 'grid' | 'list';
    selectedDocuments: number[];     // seleccion multiple
}

// Actions principales:
// fetchFolders(caseId)
// fetchDocuments(caseId, folderId?)
// uploadDocuments(caseId, folderId, files[])
// deleteDocument(caseId, documentId)
// moveDocument(caseId, documentId, targetFolderId)
// createFolder(caseId, parentId, name)
// deleteFolder(caseId, folderId) -- solo si vacia
// renameFolder(caseId, folderId, newName)
```

### 5.3 Validaciones Frontend Criticas

**Reemplazo de documentos:**
- Al subir un archivo con nombre identico a uno existente en la misma carpeta, mostrar modal de confirmacion con opciones: "Reemplazar", "Renombrar", "Cancelar".
- El reemplazo llama a `POST /documents/{id}/replace`, no crea un registro nuevo.
- Mostrar diff visual: nombre actual vs nombre nuevo, tamano, fecha.

**Eliminacion de carpetas:**
- Solo permitir eliminar carpetas vacias (sin documentos ni subcarpetas).
- Si la carpeta tiene contenido, mostrar mensaje: "Mueva o elimine los {n} archivos y {m} subcarpetas antes de eliminar esta carpeta."
- Las carpetas por defecto (creadas automaticamente) NO se pueden eliminar, solo renombrar.

**Prevencion de exposicion de IDs sensibles:**
- No exponer `external_id` (ID de OneDrive/Google) en el DOM ni en respuestas JSON al frontend.
- Las URLs temporales de preview/descarga deben generarse en el backend y tener TTL corto (15-30 min).
- Los `document.id` internos son aceptables en la UI (son auto-increment del sistema, no sensibles).
- No incluir `storage_path` en respuestas JSON.

### 5.4 Drag & Drop

Dos contextos de drag & drop:

1. **Upload:** Arrastrar archivos desde el OS al `UploadDropzone.vue`. Usar la Web API nativa `dragenter/dragover/drop` con `DataTransfer`.
2. **Mover:** Arrastrar un `DocumentCard` a un nodo del `FolderTree` para moverlo de carpeta. Usar `vue-draggable-plus` (ya en el proyecto).

---

## 6. Seguridad y Aislamiento

### 6.1 Aislamiento por Tenant y por Caso

**Regla fundamental:** Todo acceso a documentos debe validar la cadena completa: `User.tenant_id == Case.tenant_id == Document.tenant_id`.

```php
// En DocumentController, antes de cualquier operacion:

// 1. El middleware 'tenant' ya valida que el user tiene tenant activo
// 2. Route model binding de Case con scope:
Route::apiResource('cases.documents', DocumentController::class)
    ->scoped(['document' => 'case_id']); // Laravel auto-valida que document.case_id == case.id

// 3. Policy que valida tenant ownership:
class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return $user->tenant_id === $document->tenant_id
            && $user->can('documents.view');
    }
    // ... create, update, delete, download
}
```

**Almacenamiento local - path traversal protection:**
```php
// Estructura de paths: tenants/{tenant_id}/cases/{case_id}/documents/{uuid}.{ext}
// NUNCA usar el nombre original del archivo en el path
// Validar que el path resuelto esta dentro del directorio esperado:
$resolvedPath = realpath($storagePath);
$basePath = realpath(storage_path("app/tenants/{$tenantId}"));
if (!str_starts_with($resolvedPath, $basePath)) {
    abort(403);
}
```

### 6.2 Permisos por Rol

| Permiso | admin | consultor | apoyo | contador | cliente |
|---------|-------|-----------|-------|----------|---------|
| `documents.view` | Si | Si (sus casos) | Si (sus casos) | Si (solo facturas/contratos) | Si (solo su caso) |
| `documents.create` | Si | Si | Si | No | No |
| `documents.update` | Si | Si | No | No | No |
| `documents.delete` | Si | No | No | No | No |
| `documents.download` | Si | Si | Si | Si (solo facturas) | Si (su caso) |

**Nota para `cliente`:** El rol cliente solo ve documentos de los expedientes donde el es el `client_id`. Ademas, se puede configurar por carpeta/categoria cuales son visibles para el cliente.

### 6.3 Proteccion contra Abuso

**Rate limiting (en `RouteServiceProvider` o middleware):**

```php
// Uploads: max 20 archivos por minuto por usuario
Route::middleware('throttle:uploads')->group(function () {
    Route::post('/cases/{case}/documents', [DocumentController::class, 'store']);
});

// Downloads: max 60 por minuto por usuario
Route::middleware('throttle:downloads')->group(function () {
    Route::get('/cases/{case}/documents/{document}/download', ...);
});
```

**Validacion de archivos en upload:**
- Tamano maximo: configurable por tenant (default 50MB, max 200MB).
- Tipos MIME permitidos (whitelist): `pdf, doc, docx, xls, xlsx, jpg, jpeg, png, gif, txt, csv, zip`.
- Validar MIME type real (no solo extension): usar `finfo_file()` o `$file->getMimeType()`.
- Escaneo antivirus: recomendado para produccion (ClamAV via queue job).
- Limite de espacio por tenant: campo `storage_quota_mb` en tabla `tenants` (futuro).

### 6.4 Manejo de Errores ante Caidas de APIs Externas

```
Escenario                    | Comportamiento
-----------------------------|-----------------------------------------------
Provider API no responde     | Timeout 10s, retry 1x, luego error al usuario
                             | con mensaje: "El servicio de almacenamiento no
                             | esta disponible. Intente de nuevo."
Token refresh falla          | Marcar token como 'needs_reauth', notificar al
                             | usuario que debe reconectar su cuenta.
Upload parcial falla         | Para chunked: reanudar desde el ultimo chunk
                             | exitoso. Para simple: reintentar completo.
Archivo no encontrado en     | Log warning, marcar documento como 'orphaned'
cloud (external_id invalido) | en DB, mostrar mensaje "Archivo no disponible".
Rate limit del provider      | Respetar Retry-After header, encolar para
                             | reintento via job.
```

**Circuit Breaker Pattern (opcional pero recomendado):**
Si un provider falla 5+ veces en 1 minuto, activar circuit breaker por 2 minutos. Durante ese periodo, mostrar mensaje "Almacenamiento en nube temporalmente no disponible" sin intentar llamadas.

---

## 7. Webhooks para Cambios Externos (Fase Futura)

### 7.1 Microsoft Graph Subscriptions

```
POST https://graph.microsoft.com/v1.0/subscriptions
{
    "changeType": "updated",
    "notificationUrl": "https://app.example.com/api/webhooks/onedrive",
    "resource": "/drives/{drive-id}/root",
    "expirationDateTime": "2026-03-27T00:00:00Z"
}
```

- Las suscripciones expiran (max 30 dias para OneDrive). Requiere job para renovarlas.
- El webhook recibe notificacion de cambio, luego el backend debe hacer `delta query` para obtener los cambios reales.

### 7.2 Google Drive Push Notifications

```
POST https://www.googleapis.com/drive/v3/changes/watch
{
    "id": "unique-channel-id",
    "type": "web_hook",
    "address": "https://app.example.com/api/webhooks/google-drive"
}
```

### 7.3 Recomendacion

Posponer webhooks a una fase posterior. Para la v1, usar "pull on demand": sincronizar metadata del archivo cuando el usuario abre la carpeta. Esto simplifica enormemente la implementacion inicial y evita la complejidad de mantener suscripciones activas.

---

## 8. Migraciones de Base de Datos Adicionales

Las tablas `documents` y `document_folders` ya existen. Se requieren los siguientes cambios incrementales:

### 8.1 Nueva Tabla: `oauth_tokens` (ya existe)

La migracion `2026_02_08_221209` ya cubre esta tabla. No se requieren cambios.

### 8.2 Cambios Sugeridos a Tablas Existentes

```php
// Migracion: add_fields_to_documents_table

Schema::table('documents', function (Blueprint $table) {
    // Version tracking para reemplazos
    $table->unsignedInteger('version')->default(1)->after('external_url');
    $table->foreignId('replaced_by')->nullable()->constrained('documents')->nullOnDelete()->after('version');

    // Checksum para validacion de integridad
    $table->string('checksum', 64)->nullable()->after('size')->comment('SHA-256 hash');
});
```

```php
// Migracion: add_is_default_to_document_folders

Schema::table('document_folders', function (Blueprint $table) {
    // Carpetas por defecto no se pueden eliminar
    $table->boolean('is_default')->default(false)->after('sort_order');
    // Categoria asociada (para carpetas por defecto)
    $table->string('category')->nullable()->after('is_default');
});
```

### 8.3 Modelo [[OauthToken]] (Nuevo)

```php
// app/Models/OauthToken.php
class OauthToken extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'provider',
        'access_token', 'refresh_token', 'expires_at', 'scopes',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'scopes' => 'array',
    ];

    protected $hidden = [
        'access_token', 'refresh_token',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isExpiringSoon(int $bufferSeconds = 300): bool
    {
        return $this->expires_at->subSeconds($bufferSeconds)->isPast();
    }
}
```

---

## 9. Dependencias de Paquetes

### 9.1 Composer (Backend)

```json
{
    "require": {
        "microsoft/microsoft-graph": "^2.0",
        "google/apiclient": "^2.16"
    }
}
```

**Justificacion:**
- `microsoft/microsoft-graph`: SDK oficial para Microsoft Graph API. Soporta upload sessions, delta queries, file operations.
- `google/apiclient`: SDK oficial de Google. Soporta Drive API v3, resumable uploads, OAuth2.

**NO se requiere:**
- `league/flysystem-*` adicionales (ya incluido con Laravel para local).

### 9.2 NPM (Frontend)

No se requieren dependencias adicionales. Se usaran:
- `vue-draggable-plus` (ya instalado) para drag & drop de documentos entre carpetas.
- Web API nativa `DataTransfer` para drag & drop de archivos desde el OS.

---

## 10. Plan de Implementacion

### Fase 1: Fundamentos (Backend Core)

| # | Tarea | Archivos |
|---|-------|----------|
| 1.1 | ~~Crear modelos `Document`, `DocumentFolder`, `OauthToken`~~ | `app/Models/` | [x] |
| 1.2 | ~~Crear migraciones incrementales (version, checksum, is_default, category)~~ | `database/migrations/` | [x] |
| 1.3 | ~~Crear `DocumentStorageInterface` (contrato)~~ | `app/Contracts/` | [x] |
| 1.4 | ~~Implementar `LocalStorageProvider`~~ | `app/Services/Storage/` | [x] |
| 1.5 | ~~Crear `DocumentService` y `FolderService`~~ | `app/Services/Document/` | [x] |
| 1.6 | ~~Crear `OAuthTokenService`~~ | `app/Services/` | [x] |
| 1.7 | ~~Crear `CaseObserver` para estructura de carpetas por defecto~~ | `app/Observers/` | [x] |
| 1.8 | ~~Registrar permisos de documentos en seeder~~ | `database/seeders/` | [x] 5 permisos × 5 roles según matriz spec |

### Fase 2: API REST

| # | Tarea | Archivos |
|---|-------|----------|
| 2.1 | ~~Crear `DocumentController` con CRUD~~ | `app/Http/Controllers/Api/` | [x] |
| 2.2 | ~~Crear `DocumentFolderController`~~ | `app/Http/Controllers/Api/` | [x] |
| 2.3 | ~~Crear `DocumentPolicy` y `DocumentFolderPolicy`~~ | `app/Policies/` | [x] |
| 2.4 | ~~Crear Form Requests de validacion~~ | `app/Http/Requests/Document/` | [x] |
| 2.5 | ~~Registrar rutas en `routes/api.php`~~ | `routes/api.php` | [x] |
| 2.6 | ~~Configurar rate limiting para uploads/downloads~~ | `app/Providers/` | [x] uploads:20/min, downloads:60/min |
| 2.7 | Tests Feature para endpoints | `tests/Feature/` | [ ] (pendiente) |

### Fase 3: Frontend - File Manager UI

| # | Tarea | Archivos |
|---|-------|----------|
| 3.1 | ~~Crear `documentStore` (Pinia)~~ | `resources/js/src/stores/documentStore.ts` | [x] State, getters, actions integrados en CaseDocumentsTab |
| 3.2 | ~~Crear `CaseDocumentsTab.vue` (orchestrator)~~ | `resources/js/src/views/cases/components/` | [x] |
| 3.3 | ~~Crear `FolderTree.vue`~~ | `resources/js/src/views/cases/components/documents/` | [x] |
| 3.4 | ~~Crear `DocumentGrid.vue`~~ | `resources/js/src/views/cases/components/documents/` | [x] |
| 3.5 | ~~Crear `UploadDropzone.vue` con progress~~ | `resources/js/src/views/cases/components/documents/` | [x] |
| 3.6 | ~~Crear `DocumentToolbar.vue`~~ | `resources/js/src/views/cases/components/documents/` | [x] |
| 3.7 | ~~Crear modales (preview, replace, move)~~ | `resources/js/src/views/cases/components/documents/` | [x] Preview (inline), ReplaceDocumentModal, MoveDocumentModal |
| 3.8 | ~~Integrar tab en `cases/show.vue`~~ | `resources/js/src/views/cases/show.vue` | [x] |
| 3.9 | ~~Agregar traducciones i18n~~ | `resources/js/src/locales/` | [x] |

### Fase 4: Integracion Cloud

| # | Tarea | Archivos |
|---|-------|----------|
| 4.1 | ~~Implementar `OneDriveProvider`~~ | `app/Services/Storage/` | [x] |
| 4.2 | ~~Implementar `GoogleDriveProvider`~~ | `app/Services/Storage/` | [x] |
| 4.3 | ~~Crear `OAuthFlowController` (redirect/callback/status/disconnect)~~ | `app/Http/Controllers/Api/` | [x] |
| 4.4 | ~~UI para conectar/desconectar cuentas cloud~~ | `resources/js/src/views/cases/components/documents/` | [x] |
| 4.5 | Implementar chunked uploads (frontend + backend) | Varios | [ ] (pendiente — uploads >4MB) |
| 4.6 | Tests de integracion con mocks de APIs | `tests/Feature/` | [ ] (pendiente) |

### Fase 5: Hardening (Seguridad y Performance)

| # | Tarea | Estado |
|---|-------|--------|
| 5.1 | ~~Implementar circuit breaker para providers cloud~~ | [x] [[CircuitBreaker]] + [[ResilientStorageProvider]] |
| 5.2 | ~~Agregar escaneo antivirus via queue~~ | [x] [[ScanDocumentForVirus]] job (ClamAV) |
| 5.3 | ~~Configurar storage quota por tenant~~ | [x] [[StorageQuotaService]] + migration |
| 5.4 | ~~Agregar logging de auditoria (document access log)~~ | [x] [[DocumentAuditService]] |
| 5.5 | ~~Tests de seguridad (path traversal, tenant isolation, permission bypass)~~ | [x] Path traversal protection + rate limiting + 16 unit tests |
| 5.6 | Performance testing con archivos grandes | [ ] (documentacion — no requiere codigo) |

---

## 11. Riesgos y Mitigaciones

| # | Riesgo | Probabilidad | Impacto | Mitigacion |
|---|--------|-------------|---------|------------|
| R1 | Race condition en token refresh concurrente | Media | Alto | Cache lock por user+provider. Retry con backoff. |
| R2 | Path traversal en almacenamiento local | Baja (si se implementa bien) | Critico | Usar UUID para nombres en disco. Validar realpath. Nunca usar input del usuario en paths. |
| R3 | Tenant A accede a documentos de Tenant B | Baja | Critico | Triple validacion: middleware tenant + policy + query scope. Tests automatizados de aislamiento. |
| R4 | Caida prolongada de OneDrive/Google | Media | Medio | Circuit breaker. Fallback a upload local con sincronizacion posterior (feature opcional). |
| R5 | Archivos maliciosos (malware) | Media | Alto | Validacion estricta de MIME type + extension. Antivirus en queue. No ejecutar archivos del lado servidor. |
| R6 | Exceso de almacenamiento por tenant | Media | Medio | Quota configurable. Alerta al 80%. Bloqueo de uploads al 100%. |
| R7 | Tokens OAuth revocados externamente | Baja | Medio | Detectar 401 de provider, marcar token como revocado, solicitar re-autenticacion. |
| R8 | Latencia en listado de carpetas con muchos archivos | Media | Bajo | Paginacion server-side. Cache de arbol de carpetas (invalidar en cambio). Lazy loading de subcarpetas. |
| R9 | Perdida de archivo en cloud sin replica local | Baja | Alto | Checksum para validar integridad. Considerar backup periodico a local para archivos criticos (fase futura). |

---

## 12. Campos Ocultos del JSON Response

Para prevenir fuga de informacion, los siguientes campos **nunca deben incluirse** en las respuestas JSON al frontend:

```php
// En DocumentResource.php (API Resource)
public function toArray($request): array
{
    return [
        'id'            => $this->id,
        'name'          => $this->name,
        'original_name' => $this->original_name,
        'mime_type'     => $this->mime_type,
        'size'          => $this->size,
        'category'      => $this->category,
        'storage_type'  => $this->storage_type,  // OK: indica si es local/cloud
        'version'       => $this->version,
        'folder_id'     => $this->folder_id,
        'uploaded_by'   => $this->whenLoaded('uploader', fn() => [
            'id'   => $this->uploader->id,
            'name' => $this->uploader->name,
        ]),
        'created_at'    => $this->created_at,
        'updated_at'    => $this->updated_at,
        // EXCLUIDOS: storage_path, external_id, external_url, tenant_id, checksum
    ];
}
```

---

## 13. Recomendacion Final

### Enfoque Recomendado: Implementacion Incremental con Local-First

1. **Fase 1-3 primero:** Construir el File Manager completo con almacenamiento local. Esto entrega valor inmediato al usuario y permite iterar sobre la UX del file manager sin la complejidad de APIs externas.

2. **Fase 4 despues:** Agregar providers de nube como enhancement. La interfaz `DocumentStorageInterface` permite esta adicion sin modificar el frontend ni los controllers.

3. **Fase 5 en paralelo:** Ir implementando medidas de seguridad progresivamente conforme se agregan providers.

### Justificacion de la Arquitectura

- **Strategy Pattern** para providers: Desacoplamiento total entre la logica de negocio y el medio de almacenamiento.
- **SDKs nativos** sobre Flysystem para cloud: Features completos (resumable uploads, presigned URLs) sin adapters custom.
- **Flysystem solo para local:** Aprovechar la integracion nativa de Laravel sin agregar complejidad.
- **Chunked uploads directo al provider:** El backend no es bottleneck de ancho de banda para archivos grandes.
- **Soft deletes en documents:** Permite recuperacion y auditoria. Las carpetas no usan soft delete (se valida que estan vacias antes de eliminar).
- **Observer para carpetas por defecto:** Garantiza consistencia estructural en todos los expedientes.

### Estimacion de Esfuerzo

| Fase | Estimacion |
|------|------------|
| Fase 1: Backend Core | 3-4 dias |
| Fase 2: API REST | 2-3 dias |
| Fase 3: Frontend UI | 4-5 dias |
| Fase 4: Cloud Integration | 5-7 dias |
| Fase 5: Hardening | 3-4 dias |
| **Total** | **17-23 dias** |

---

## Apendice A: Estructura de Archivos Resultante

```
app/
    Contracts/
        DocumentStorageInterface.php
    Http/
        Controllers/Api/
            DocumentController.php
            DocumentFolderController.php
            OAuthFlowController.php
        Requests/Document/
            StoreDocumentRequest.php
            UpdateDocumentRequest.php
            StoreFolderRequest.php
            ReplaceDocumentRequest.php
    Models/
        Document.php
        DocumentFolder.php
        OauthToken.php
    Observers/
        CaseObserver.php (agregar metodo created para default folders)
    Policies/
        DocumentPolicy.php
        DocumentFolderPolicy.php
    Services/
        Document/
            DocumentService.php
            FolderService.php
        Storage/
            LocalStorageProvider.php
            OneDriveProvider.php
            GoogleDriveProvider.php
        OAuthTokenService.php

resources/js/src/
    stores/
        document.ts
    views/cases/components/
        CaseDocumentsTab.vue
    components/documents/
        FolderTree.vue
        DocumentGrid.vue
        DocumentCard.vue
        DocumentRow.vue
        DocumentToolbar.vue
        DocumentBreadcrumb.vue
        UploadDropzone.vue
        DocumentPreviewModal.vue
        ReplaceDocumentModal.vue
        MoveDocumentModal.vue
    types/
        document.ts
```
