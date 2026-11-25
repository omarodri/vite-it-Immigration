---
tipo: spec
numero: 45
modulo: Calendario y Eventos
dominio: CAL
estado: PART
tags: [spec, calendario, parcial]
relacionados:
  - "[[22_calendario_legal_integrado|Spec 22]]"
  - "[[24_calendario_drag_drop_filtros_quicklinks|Spec 24]]"
  - "[[62_sincronizacion_calendarios_externos|Spec 62]]"
---
# Especificacion Tecnica: Sincronizacion Bidireccional de Calendarios Externos

## Metadata
- **Fecha:** 2026-04-15
- **Version:** 1.0
- **Arquitecto:** Claude Code (Architect Agent)
- **Estado:** Pendiente de implementacion
- **Dependencias:** [[22_calendario_legal_integrado|Spec 22]] (Calendario Legal), [[24_calendario_drag_drop_filtros_quicklinks|Spec 24]] (Calendario Drag & Drop), [[30_cloud_folder_sync_por_tenant|Spec 30]] (Cloud Folder Sync)

---

## 1. Resumen Ejecutivo

### Problema
Los usuarios del sistema mantienen sus agendas personales y profesionales en Google Calendar y/o Microsoft Outlook. Actualmente deben duplicar manualmente los eventos entre la plataforma VITE-IT y sus calendarios externos, lo que genera inconsistencias, olvidos y doble trabajo.

### Alcance del MVP
- Conexion OAuth a nivel de **usuario** (cada usuario conecta su propio calendario).
- Sincronizacion **bidireccional**: los eventos creados en VITE-IT se empujan al calendario externo y viceversa.
- Estrategia de sync: **polling cada 15 minutos** (sin webhooks).
- Proveedores soportados: **Google Calendar** y **Microsoft Outlook (Graph API)**.
- Diferenciacion visual en el calendario para identificar la fuente del evento.
- Interfaz de conexion/desconexion en el perfil del usuario.

### Fuera de alcance (MVP)
- Sincronizacion de asistentes/invitaciones.
- Creacion de calendarios secundarios (se usa el calendario principal del usuario).
- Webhooks / push notifications de los proveedores.
- Sincronizacion de recurrencias complejas (series).
- Merge automatico de conflictos (gana la ultima escritura).

---

## 2. Arquitectura

### 2.1 Diagrama de Componentes

```
Usuario (Browser)
    |
    v
[Vue SPA]
    |-- CalendarConnections.vue  (perfil: conectar/desconectar)
    |-- calendar.vue             (FullCalendar + badges de fuente)
    |-- calendarSyncService.ts   (API calls)
    |
    v (REST API)
[Laravel API Layer]
    |-- UserCalendarOAuthController  (flujo OAuth calendario)
    |-- EventController              (CRUD eventos -- ya existe)
    |
    v
[Application Services]
    |-- CalendarSyncService          (orquestador de sync)
    |-- OAuthTokenService            (tokens -- ya existe, se modifica)
    |
    v
[Provider Services]
    |-- GoogleCalendarService        (HTTP client -> Google Calendar API v3)
    |-- MicrosoftCalendarService     (HTTP client -> Microsoft Graph API)
    |
    v
[Domain / Infrastructure]
    |-- Event model                  (sync_source, external_id, last_synced_at)
    |-- OauthToken model             (+ purpose column)
    |-- EventSyncObserver            (dispara push al crear/actualizar/eliminar)
    |-- SyncEventToCalendarJob       (push async a proveedor)
    |-- PullCalendarEventsJob        (pull periodico de proveedores)
    |
    v
[External APIs]
    |-- Google Calendar API v3
    |-- Microsoft Graph API v1.0
```

### 2.2 Flujo OAuth de Calendario (User-Level)

```
1. Usuario hace clic en "Conectar Google Calendar" en su perfil
2. Frontend: GET /api/calendar-oauth/{provider}/redirect
3. Backend: genera state, lo guarda en Cache, retorna URL de autorizacion
4. Browser: redirige al proveedor OAuth (Google/Microsoft)
5. Usuario autoriza permisos de calendario
6. Proveedor redirige a: GET /api/calendar-oauth/{provider}/callback
7. Backend: valida state, intercambia code por tokens
8. Backend: guarda tokens en oauth_tokens con purpose='calendar'
9. Backend: redirige a /users/profile?calendar_connected={provider}
```

**Diferencia clave con el flujo de storage existente:**
- Storage tokens son **tenant-level** (un admin conecta para todo el tenant).
- Calendar tokens son **user-level** (cada usuario conecta su propia cuenta).
- Se diferencian por la columna `purpose` en `oauth_tokens`.

### 2.3 Flujo Push (Evento local -> Calendario externo)

```
1. Usuario crea/edita/elimina evento en VITE-IT
2. EventSyncObserver detecta el cambio (created/updated/deleted)
3. Si el evento es sync_source='local' Y el usuario tiene calendario conectado:
   a. Observer despacha SyncEventToCalendarJob (queued)
4. SyncEventToCalendarJob:
   a. Obtiene token valido via OAuthTokenService
   b. Llama a GoogleCalendarService o MicrosoftCalendarService
   c. Crea/actualiza/elimina evento en el proveedor
   d. Guarda external_id y last_synced_at en el Event
```

### 2.4 Flujo Pull (Calendario externo -> VITE-IT)

```
1. PullCalendarEventsJob corre cada 15 minutos via scheduler
2. Para cada usuario con calendario conectado:
   a. Obtiene token valido
   b. Lista eventos modificados desde last_synced_at (o ultimas 24h si primera vez)
   c. Para cada evento externo:
      - Si external_id existe en DB: actualiza campos (titulo, fechas, ubicacion)
      - Si external_id no existe: crea nuevo Event con sync_source='google'|'outlook'
   d. Para eventos locales con external_id que ya no existen en proveedor:
      - Soft-delete el evento local
   e. Actualiza last_synced_at del usuario (en tabla calendar_sync_status)
```

---

## 3. Cambios en Base de Datos

### 3.1 Migracion: Agregar `purpose` a `oauth_tokens`

**Archivo:** `database/migrations/2026_04_16_000002_add_purpose_to_oauth_tokens.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_tokens', function (Blueprint $table) {
            $table->string('purpose', 20)->default('storage')->after('provider');
        });

        // Actualizar unique constraint: ahora incluye purpose
        Schema::table('oauth_tokens', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'provider']);
            $table->unique(['user_id', 'provider', 'purpose']);
        });

        // Actualizar index de tenant
        Schema::table('oauth_tokens', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'provider']);
            $table->index(['tenant_id', 'provider', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::table('oauth_tokens', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'provider', 'purpose']);
            $table->unique(['user_id', 'provider']);
            $table->dropIndex(['tenant_id', 'provider', 'purpose']);
            $table->index(['tenant_id', 'provider']);
            $table->dropColumn('purpose');
        });
    }
};
```

### 3.2 Migracion: Crear tabla `calendar_sync_status`

**Archivo:** `database/migrations/2026_04_16_000003_create_calendar_sync_status_table.php`

Esta tabla rastrea el estado de sincronizacion por usuario y proveedor.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_sync_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('provider', ['google', 'microsoft']);
            $table->dateTime('last_pull_at')->nullable();
            $table->dateTime('last_push_at')->nullable();
            $table->string('sync_token')->nullable();       // Google sync token / Microsoft delta token
            $table->enum('status', ['active', 'error', 'paused'])->default('active');
            $table->text('last_error')->nullable();
            $table->unsignedInteger('error_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_sync_status');
    }
};
```

### 3.3 Nota sobre la tabla `events`

La tabla `events` **ya contiene** los campos necesarios para sync (definidos en la migracion original `0001_01_01_000006_create_business_tables.php`):

- `sync_source` -- `ENUM('local', 'outlook', 'google')` con default `'local'`
- `external_id` -- `VARCHAR(255)` nullable
- `last_synced_at` -- `DATETIME` nullable
- Indice: `['sync_source', 'external_id']`

**No se requiere migracion adicional para `events`.**

### 3.4 Migracion: Agregar `external_etag` a `events`

**Archivo:** `database/migrations/2026_04_16_000004_add_external_etag_to_events.php`

Para detectar cambios sin comparar todos los campos (optimizacion de conflictos):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('external_etag')->nullable()->after('last_synced_at');
            $table->foreignId('synced_by_user_id')
                  ->nullable()
                  ->after('external_etag')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['synced_by_user_id']);
            $table->dropColumn(['external_etag', 'synced_by_user_id']);
        });
    }
};
```

`synced_by_user_id` identifica que usuario trajo el evento externo (importante porque el pull corre per-user).

---

## 4. Backend -- Nuevos Archivos a Crear

### 4.1 `UserCalendarOAuthController`

**Archivo:** `app/Http/Controllers/Api/UserCalendarOAuthController.php`

**Responsabilidades:**
- Flujo OAuth separado del storage, con scopes de calendario.
- Genera URL de autorizacion con scopes de calendario.
- Maneja callback y guarda tokens con `purpose='calendar'`.
- Status y disconnect a nivel de usuario.
- Redirige al perfil del usuario (no a admin/tenant/oauth).

**Rutas:**
```
GET  /api/calendar-oauth/{provider}/redirect   -> redirect()
GET  /api/calendar-oauth/{provider}/callback    -> callback()
GET  /api/calendar-oauth/status                 -> status()
POST /api/calendar-oauth/{provider}/disconnect  -> disconnect()
```

**Scopes por proveedor:**

| Proveedor | Scopes |
|-----------|--------|
| Google | `https://www.googleapis.com/auth/calendar.events offline_access` |
| Microsoft | `Calendars.ReadWrite offline_access User.Read` |

**Ejemplo de implementacion (metodo redirect):**

```php
public function redirect(string $provider): JsonResponse
{
    $user = Auth::user();
    $tenant = $user->tenant;

    $credentials = match ($provider) {
        'microsoft' => $this->credentialService->getMicrosoftCredentials($tenant),
        'google' => $this->credentialService->getGoogleCredentials($tenant),
        default => null,
    };

    if (!$credentials) {
        return response()->json([
            'message' => "No {$provider} OAuth credentials configured.",
        ], 422);
    }

    $state = Str::random(40);
    Cache::put("calendar_oauth_state:{$state}", [
        'user_id' => $user->id,
        'provider' => $provider,
    ], now()->addMinutes(10));

    $authUrl = match ($provider) {
        'microsoft' => $this->buildMicrosoftCalendarAuthUrl($credentials, $state),
        'google' => $this->buildGoogleCalendarAuthUrl($credentials, $state),
    };

    return response()->json(['url' => $authUrl]);
}
```

**Ejemplo (callback -- guarda con purpose):**

```php
public function callback(Request $request, string $provider): RedirectResponse
{
    $frontendUrl = rtrim(config('app.url'), '/');
    $profilePage = "{$frontendUrl}/users/profile";

    // ... validar state, exchange code ...

    // Guardar token con purpose='calendar' a nivel de USUARIO
    $this->tokenService->storeUserCalendarToken(
        $user,
        $provider,
        $tokenData
    );

    // Crear registro de sync status
    CalendarSyncStatus::updateOrCreate(
        ['user_id' => $user->id, 'provider' => $provider],
        ['tenant_id' => $user->tenant_id, 'status' => 'active', 'error_count' => 0]
    );

    return redirect("{$profilePage}?calendar_connected={$provider}");
}
```

### 4.2 `CalendarSyncService`

**Archivo:** `app/Services/Calendar/CalendarSyncService.php`

**Responsabilidades:**
- Orquestar la sincronizacion push y pull.
- Seleccionar el proveedor correcto (Google/Microsoft).
- Manejar la logica de deduplicacion y resolucion de conflictos.
- Actualizar `calendar_sync_status`.

**Metodos principales:**

```php
class CalendarSyncService
{
    public function __construct(
        private readonly GoogleCalendarService $googleService,
        private readonly MicrosoftCalendarService $microsoftService,
        private readonly OAuthTokenService $tokenService,
    ) {}

    /**
     * Push un evento local al calendario externo del usuario.
     * Se llama desde SyncEventToCalendarJob.
     */
    public function pushEvent(Event $event, User $user): void
    {
        $provider = $this->getConnectedProvider($user);
        if (!$provider) return;

        $service = $this->getProviderService($provider);
        $token = $this->tokenService->getValidUserCalendarToken($user, $provider);
        if (!$token) return;

        if ($event->external_id) {
            // Actualizar evento existente
            $result = $service->updateEvent($token, $event->external_id, $event);
        } else {
            // Crear nuevo evento
            $result = $service->createEvent($token, $event);
        }

        if ($result) {
            $event->updateQuietly([
                'external_id' => $result['id'],
                'external_etag' => $result['etag'] ?? null,
                'last_synced_at' => now(),
                'synced_by_user_id' => $user->id,
            ]);
        }
    }

    /**
     * Eliminar un evento del calendario externo.
     */
    public function deleteExternalEvent(string $externalId, User $user): void;

    /**
     * Pull eventos del calendario externo de un usuario.
     * Se llama desde PullCalendarEventsJob.
     */
    public function pullEvents(User $user): void
    {
        $provider = $this->getConnectedProvider($user);
        if (!$provider) return;

        $service = $this->getProviderService($provider);
        $token = $this->tokenService->getValidUserCalendarToken($user, $provider);
        if (!$token) return;

        $syncStatus = CalendarSyncStatus::where('user_id', $user->id)
            ->where('provider', $provider)
            ->first();

        $since = $syncStatus?->last_pull_at ?? now()->subDay();

        try {
            $externalEvents = $service->listEvents($token, $since);
            $this->processExternalEvents($externalEvents, $user, $provider);

            $syncStatus->update([
                'last_pull_at' => now(),
                'status' => 'active',
                'error_count' => 0,
                'last_error' => null,
            ]);
        } catch (\Exception $e) {
            $syncStatus->increment('error_count');
            $syncStatus->update([
                'status' => $syncStatus->error_count >= 5 ? 'error' : 'active',
                'last_error' => Str::limit($e->getMessage(), 500),
            ]);
            Log::error('Calendar pull failed', [
                'user_id' => $user->id,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Procesar eventos externos: crear, actualizar o soft-delete.
     */
    private function processExternalEvents(array $events, User $user, string $provider): void;

    /**
     * Obtener el proveedor conectado del usuario (google o microsoft).
     * Retorna null si no tiene ninguno conectado.
     */
    private function getConnectedProvider(User $user): ?string;

    /**
     * Obtener la instancia del servicio del proveedor.
     */
    private function getProviderService(string $provider): CalendarProviderInterface;
}
```

### 4.3 `CalendarProviderInterface`

**Archivo:** `app/Services/Calendar/CalendarProviderInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Event;

interface CalendarProviderInterface
{
    /**
     * Crear un evento en el calendario externo.
     * Retorna ['id' => string, 'etag' => ?string] o null si falla.
     */
    public function createEvent(string $accessToken, Event $event): ?array;

    /**
     * Actualizar un evento existente en el calendario externo.
     */
    public function updateEvent(string $accessToken, string $externalId, Event $event): ?array;

    /**
     * Eliminar un evento del calendario externo.
     */
    public function deleteEvent(string $accessToken, string $externalId): bool;

    /**
     * Listar eventos modificados desde una fecha dada.
     * Retorna array de arrays con formato normalizado.
     */
    public function listEvents(string $accessToken, \DateTimeInterface $since): array;
}
```

### 4.4 `GoogleCalendarService`

**Archivo:** `app/Services/Calendar/GoogleCalendarService.php`

**Responsabilidades:**
- Comunicacion con Google Calendar API v3 via `Http::` client de Laravel.
- Conversion de formato Event -> Google Event y viceversa.
- Manejo de paginacion (`pageToken`).

**Endpoints de Google Calendar API v3:**

| Operacion | Metodo | URL |
|-----------|--------|-----|
| Listar | GET | `https://www.googleapis.com/calendar/v3/calendars/primary/events` |
| Crear | POST | `https://www.googleapis.com/calendar/v3/calendars/primary/events` |
| Actualizar | PUT | `https://www.googleapis.com/calendar/v3/calendars/primary/events/{eventId}` |
| Eliminar | DELETE | `https://www.googleapis.com/calendar/v3/calendars/primary/events/{eventId}` |

**Formato de evento Google (request body):**

```json
{
    "summary": "Titulo del evento",
    "description": "Descripcion",
    "location": "Direccion",
    "start": {
        "dateTime": "2026-04-15T10:00:00-05:00",
        "timeZone": "America/Toronto"
    },
    "end": {
        "dateTime": "2026-04-15T11:00:00-05:00",
        "timeZone": "America/Toronto"
    }
}
```

**Para eventos all-day, usar `date` en vez de `dateTime`:**

```json
{
    "start": { "date": "2026-04-15" },
    "end": { "date": "2026-04-16" }
}
```

**Ejemplo de implementacion (listEvents con updatedMin):**

```php
public function listEvents(string $accessToken, \DateTimeInterface $since): array
{
    $events = [];
    $pageToken = null;

    do {
        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->get('https://www.googleapis.com/calendar/v3/calendars/primary/events', [
                'updatedMin' => $since->format(\DateTimeInterface::RFC3339),
                'singleEvents' => 'true',
                'maxResults' => 250,
                'pageToken' => $pageToken,
                'showDeleted' => 'true', // Para detectar eliminaciones
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Google Calendar API error: {$response->status()}");
        }

        $data = $response->json();

        foreach ($data['items'] ?? [] as $item) {
            $events[] = $this->normalizeEvent($item);
        }

        $pageToken = $data['nextPageToken'] ?? null;
    } while ($pageToken);

    return $events;
}

/**
 * Normalizar evento de Google al formato interno.
 */
private function normalizeEvent(array $googleEvent): array
{
    $isAllDay = isset($googleEvent['start']['date']);

    return [
        'external_id' => $googleEvent['id'],
        'title' => $googleEvent['summary'] ?? '(Sin titulo)',
        'description' => $googleEvent['description'] ?? null,
        'location' => $googleEvent['location'] ?? null,
        'start_date' => $isAllDay
            ? Carbon::parse($googleEvent['start']['date'])->startOfDay()
            : Carbon::parse($googleEvent['start']['dateTime']),
        'end_date' => $isAllDay
            ? Carbon::parse($googleEvent['end']['date'])->startOfDay()
            : Carbon::parse($googleEvent['end']['dateTime']),
        'all_day' => $isAllDay,
        'etag' => $googleEvent['etag'] ?? null,
        'status' => $googleEvent['status'] ?? 'confirmed', // 'cancelled' = eliminado
        'sync_source' => 'google',
    ];
}
```

### 4.5 `MicrosoftCalendarService`

**Archivo:** `app/Services/Calendar/MicrosoftCalendarService.php`

**Responsabilidades:**
- Comunicacion con Microsoft Graph API v1.0.
- Conversion de formato Event -> Outlook Event y viceversa.
- Manejo de `$skipToken` para paginacion y `$deltaToken` para cambios incrementales.

**Endpoints de Microsoft Graph API:**

| Operacion | Metodo | URL |
|-----------|--------|-----|
| Listar | GET | `https://graph.microsoft.com/v1.0/me/events` |
| Crear | POST | `https://graph.microsoft.com/v1.0/me/events` |
| Actualizar | PATCH | `https://graph.microsoft.com/v1.0/me/events/{eventId}` |
| Eliminar | DELETE | `https://graph.microsoft.com/v1.0/me/events/{eventId}` |
| Delta | GET | `https://graph.microsoft.com/v1.0/me/calendarView/delta` |

**Formato de evento Microsoft (request body):**

```json
{
    "subject": "Titulo del evento",
    "body": {
        "contentType": "text",
        "content": "Descripcion"
    },
    "location": {
        "displayName": "Direccion"
    },
    "start": {
        "dateTime": "2026-04-15T10:00:00",
        "timeZone": "America/Toronto"
    },
    "end": {
        "dateTime": "2026-04-15T11:00:00",
        "timeZone": "America/Toronto"
    },
    "isAllDay": false
}
```

**Ejemplo de implementacion (listEvents con filter):**

```php
public function listEvents(string $accessToken, \DateTimeInterface $since): array
{
    $events = [];
    $url = 'https://graph.microsoft.com/v1.0/me/events';

    $params = [
        '$filter' => "lastModifiedDateTime ge {$since->format('Y-m-d\TH:i:s\Z')}",
        '$top' => 50,
        '$select' => 'id,subject,body,location,start,end,isAllDay,lastModifiedDateTime,isCancelled',
        '$orderby' => 'lastModifiedDateTime desc',
    ];

    do {
        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->get($url, $params);

        if (!$response->successful()) {
            throw new \RuntimeException("Microsoft Graph API error: {$response->status()}");
        }

        $data = $response->json();

        foreach ($data['value'] ?? [] as $item) {
            $events[] = $this->normalizeEvent($item);
        }

        $url = $data['@odata.nextLink'] ?? null;
        $params = []; // nextLink ya incluye los params
    } while ($url);

    return $events;
}

private function normalizeEvent(array $msEvent): array
{
    return [
        'external_id' => $msEvent['id'],
        'title' => $msEvent['subject'] ?? '(Sin titulo)',
        'description' => $msEvent['body']['content'] ?? null,
        'location' => $msEvent['location']['displayName'] ?? null,
        'start_date' => Carbon::parse($msEvent['start']['dateTime'], $msEvent['start']['timeZone']),
        'end_date' => Carbon::parse($msEvent['end']['dateTime'], $msEvent['end']['timeZone']),
        'all_day' => $msEvent['isAllDay'] ?? false,
        'etag' => $msEvent['@odata.etag'] ?? null,
        'status' => ($msEvent['isCancelled'] ?? false) ? 'cancelled' : 'confirmed',
        'sync_source' => 'outlook',
    ];
}
```

### 4.6 `EventSyncObserver`

**Archivo:** `app/Observers/EventSyncObserver.php`

**Responsabilidades:**
- Observar cambios en el modelo `Event` (created, updated, deleted).
- Despachar `SyncEventToCalendarJob` cuando corresponda.
- Evitar loops infinitos: no despachar si el cambio viene del pull (verificar via flag).

```php
<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\SyncEventToCalendarJob;
use App\Models\Event;

class EventSyncObserver
{
    /**
     * Despachar push solo si:
     * 1. El evento es de origen local (no importado)
     * 2. No estamos en un pull (flag estático para evitar loops)
     * 3. El creador/editor tiene calendario conectado
     */
    public function created(Event $event): void
    {
        if ($this->shouldSync($event)) {
            SyncEventToCalendarJob::dispatch($event, 'create');
        }
    }

    public function updated(Event $event): void
    {
        if ($this->shouldSync($event)) {
            SyncEventToCalendarJob::dispatch($event, 'update');
        }
    }

    public function deleted(Event $event): void
    {
        if ($event->external_id && $this->shouldSync($event)) {
            SyncEventToCalendarJob::dispatch($event, 'delete');
        }
    }

    private function shouldSync(Event $event): bool
    {
        // No sincronizar si estamos en un pull (evita loop infinito)
        if (CalendarSyncService::$isPulling) {
            return false;
        }

        // Solo sincronizar eventos locales o que ya tienen external_id
        if ($event->sync_source !== 'local' && !$event->external_id) {
            return false;
        }

        return true;
    }
}
```

**Registro del observer en `EventServiceProvider` o `AppServiceProvider`:**

```php
// En boot()
Event::observe(EventSyncObserver::class);
```

### 4.7 `SyncEventToCalendarJob`

**Archivo:** `app/Jobs/SyncEventToCalendarJob.php`

**Responsabilidades:**
- Job queued que ejecuta el push de un evento al calendario externo.
- Maneja reintentos y backoff exponencial.
- Registra errores en `calendar_sync_status`.

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Event;
use App\Models\User;
use App\Services\Calendar\CalendarSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncEventToCalendarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly Event $event,
        public readonly string $action, // 'create' | 'update' | 'delete'
    ) {}

    public function handle(CalendarSyncService $syncService): void
    {
        // Determinar el usuario relevante (creador o asignado)
        $user = $this->event->assignedTo ?? User::find($this->event->created_by);
        if (!$user) return;

        match ($this->action) {
            'create', 'update' => $syncService->pushEvent($this->event, $user),
            'delete' => $syncService->deleteExternalEvent(
                $this->event->external_id,
                $user
            ),
        };
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error('SyncEventToCalendarJob failed permanently', [
            'event_id' => $this->event->id,
            'action' => $this->action,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

### 4.8 `PullCalendarEventsJob`

**Archivo:** `app/Jobs/PullCalendarEventsJob.php`

**Responsabilidades:**
- Job scheduled que corre cada 15 minutos.
- Itera sobre todos los usuarios con calendario conectado.
- Llama a `CalendarSyncService::pullEvents()` para cada uno.
- Respeta el status de sync (no procesa usuarios con status='error').

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CalendarSyncStatus;
use App\Models\User;
use App\Services\Calendar\CalendarSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PullCalendarEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutos max
    public bool $shouldBeUnique = true;

    public function handle(CalendarSyncService $syncService): void
    {
        $activeConnections = CalendarSyncStatus::where('status', 'active')
            ->with('user')
            ->get();

        foreach ($activeConnections as $syncStatus) {
            try {
                $syncService->pullEvents($syncStatus->user);
            } catch (\Exception $e) {
                Log::error('Pull failed for user', [
                    'user_id' => $syncStatus->user_id,
                    'provider' => $syncStatus->provider,
                    'error' => $e->getMessage(),
                ]);
                // No lanzar: continuar con el siguiente usuario
            }
        }
    }
}
```

### 4.9 `CalendarSyncStatus` Model

**Archivo:** `app/Models/CalendarSyncStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarSyncStatus extends Model
{
    use BelongsToTenant;

    protected $table = 'calendar_sync_status';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'provider',
        'last_pull_at',
        'last_push_at',
        'sync_token',
        'status',
        'last_error',
        'error_count',
    ];

    protected $casts = [
        'last_pull_at' => 'datetime',
        'last_push_at' => 'datetime',
        'error_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

---

## 5. Backend -- Archivos a Modificar

### 5.1 `OAuthTokenService` -- Agregar metodos de calendario user-level

**Archivo:** `app/Services/OAuthTokenService.php`

**Cambios:**

1. **Agregar metodo `storeUserCalendarToken()`** que guarda con `purpose='calendar'`:

```php
/**
 * Store or update OAuth calendar tokens at user level.
 */
public function storeUserCalendarToken(User $user, string $provider, array $tokenData): OauthToken
{
    return OauthToken::updateOrCreate(
        [
            'user_id' => $user->id,
            'provider' => $provider,
            'purpose' => 'calendar',
        ],
        [
            'tenant_id' => $user->tenant_id,
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds((int) ($tokenData['expires_in'] ?? 3600)),
            'scopes' => $tokenData['scopes'] ?? null,
        ]
    );
}
```

2. **Agregar metodo `getValidUserCalendarToken()`**:

```php
/**
 * Get a valid calendar token for a user.
 */
public function getValidUserCalendarToken(User $user, string $provider): ?string
{
    $token = OauthToken::where('user_id', $user->id)
        ->where('provider', $provider)
        ->where('purpose', 'calendar')
        ->first();

    if (!$token) return null;

    if ($token->isExpiringSoon()) {
        $token = $this->refreshToken($token);
        if (!$token) return null;
    }

    return $token->access_token;
}
```

3. **Agregar metodo `revokeUserCalendarToken()`**:

```php
public function revokeUserCalendarToken(User $user, string $provider): bool
{
    return OauthToken::where('user_id', $user->id)
        ->where('provider', $provider)
        ->where('purpose', 'calendar')
        ->delete() > 0;
}
```

4. **Agregar metodo `hasUserCalendarToken()`**:

```php
public function hasUserCalendarToken(User $user, string $provider): bool
{
    $token = OauthToken::where('user_id', $user->id)
        ->where('provider', $provider)
        ->where('purpose', 'calendar')
        ->first();

    return $token !== null && !$token->isExpired();
}
```

5. **Modificar `refreshMicrosoftToken()`** para usar scopes correctos segun `purpose`:

```php
private function refreshMicrosoftToken(OauthToken $token, array $credentials): ?array
{
    $scope = match ($token->purpose ?? 'storage') {
        'calendar' => 'Calendars.ReadWrite offline_access User.Read',
        default => 'Files.ReadWrite.All Sites.ReadWrite.All offline_access',
    };

    $response = Http::timeout(30)->asForm()->post(
        'https://login.microsoftonline.com/common/oauth2/v2.0/token',
        [
            'grant_type' => 'refresh_token',
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'refresh_token' => $token->refresh_token,
            'scope' => $scope,
        ]
    );
    // ... rest unchanged
}
```

### 5.2 `OauthToken` Model -- Agregar `purpose` a fillable

**Archivo:** `app/Models/OauthToken.php`

```php
protected $fillable = [
    'tenant_id',
    'user_id',
    'provider',
    'purpose',       // <-- NUEVO
    'access_token',
    'refresh_token',
    'expires_at',
    'scopes',
];
```

### 5.3 `Event` Model -- Agregar nuevos campos a fillable y casts

**Archivo:** `app/Models/Event.php`

Agregar a `$fillable`:
```php
'external_etag',
'synced_by_user_id',
```

Agregar relacion:
```php
public function syncedByUser(): BelongsTo
{
    return $this->belongsTo(User::class, 'synced_by_user_id');
}
```

Agregar metodo helper:
```php
public function isExternal(): bool
{
    return $this->sync_source !== 'local';
}

public function getSyncSourceLabel(): string
{
    return match ($this->sync_source) {
        'google' => 'Google Calendar',
        'outlook' => 'Microsoft Outlook',
        default => 'VITE-IT',
    };
}
```

### 5.4 `routes/api.php` -- Agregar rutas de calendar OAuth

Agregar dentro del grupo autenticado:

```php
// Calendar OAuth (user-level)
Route::prefix('calendar-oauth')->group(function () {
    Route::get('/status', [UserCalendarOAuthController::class, 'status']);
    Route::get('/{provider}/redirect', [UserCalendarOAuthController::class, 'redirect'])
        ->whereIn('provider', ['google', 'microsoft']);
    Route::post('/{provider}/disconnect', [UserCalendarOAuthController::class, 'disconnect'])
        ->whereIn('provider', ['google', 'microsoft']);
});
```

Agregar **fuera** del grupo autenticado (el callback viene del proveedor OAuth, sin token Sanctum):

```php
// Calendar OAuth callbacks (no auth -- viene del proveedor)
Route::get('/calendar-oauth/{provider}/callback', [UserCalendarOAuthController::class, 'callback'])
    ->whereIn('provider', ['google', 'microsoft']);
```

### 5.5 `routes/console.php` -- Agregar schedule del pull job

```php
Schedule::job(new \App\Jobs\PullCalendarEventsJob)->everyFifteenMinutes();
```

### 5.6 `User` Model -- Agregar relacion

**Archivo:** `app/Models/User.php`

```php
public function calendarSyncStatus(): HasMany
{
    return $this->hasMany(CalendarSyncStatus::class);
}

public function hasCalendarConnected(string $provider): bool
{
    return $this->calendarSyncStatus()
        ->where('provider', $provider)
        ->where('status', '!=', 'error')
        ->exists();
}
```

---

## 6. Frontend -- Nuevos Archivos a Crear

### 6.1 `CalendarConnections.vue`

**Archivo:** `resources/js/src/views/users/profile/CalendarConnections.vue`

**Responsabilidades:**
- Mostrar estado de conexion de Google Calendar y Microsoft Outlook.
- Botones de conectar/desconectar.
- Mostrar ultimo sync y errores.
- Manejar query param `?calendar_connected=` despues del callback OAuth.

**Estructura UI:**

```
+---------------------------------------------------+
| Calendarios Conectados                             |
+---------------------------------------------------+
|                                                    |
| [Google icon]  Google Calendar                     |
|   Estado: Conectado                                |
|   Ultimo sync: hace 5 minutos                     |
|   [Desconectar]                                    |
|                                                    |
| [Outlook icon]  Microsoft Outlook                  |
|   Estado: No conectado                             |
|   [Conectar]                                       |
|                                                    |
+---------------------------------------------------+
```

**Ejemplo de implementacion:**

```vue
<script lang="ts" setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import Swal from 'sweetalert2';
import { calendarSyncService } from '@/services/calendarSyncService';

const { t } = useI18n();
const route = useRoute();

interface CalendarStatus {
    google: {
        connected: boolean;
        last_sync: string | null;
        status: 'active' | 'error' | 'paused' | null;
        last_error: string | null;
    };
    microsoft: {
        connected: boolean;
        last_sync: string | null;
        status: 'active' | 'error' | 'paused' | null;
        last_error: string | null;
    };
}

const status = ref<CalendarStatus | null>(null);
const loading = ref(false);

onMounted(async () => {
    await fetchStatus();

    // Manejar redirect despues de OAuth callback
    const connected = route.query.calendar_connected as string;
    if (connected) {
        Swal.fire({
            icon: 'success',
            title: t('calendar_sync.connected_success', { provider: connected }),
            timer: 3000,
        });
    }
});

async function fetchStatus() {
    loading.value = true;
    try {
        status.value = await calendarSyncService.getStatus();
    } finally {
        loading.value = false;
    }
}

async function connect(provider: 'google' | 'microsoft') {
    loading.value = true;
    try {
        const { url } = await calendarSyncService.getRedirectUrl(provider);
        window.location.href = url;
    } catch {
        Swal.fire({ icon: 'error', title: t('calendar_sync.connect_error') });
        loading.value = false;
    }
}

async function disconnect(provider: 'google' | 'microsoft') {
    const result = await Swal.fire({
        title: t('calendar_sync.confirm_disconnect'),
        text: t('calendar_sync.disconnect_warning'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: t('common.confirm'),
        cancelButtonText: t('common.cancel'),
    });

    if (!result.isConfirmed) return;

    loading.value = true;
    try {
        await calendarSyncService.disconnect(provider);
        await fetchStatus();
        Swal.fire({ icon: 'success', title: t('calendar_sync.disconnected'), timer: 2000 });
    } finally {
        loading.value = false;
    }
}
</script>
```

### 6.2 `calendarSyncService.ts`

**Archivo:** `resources/js/src/services/calendarSyncService.ts`

```typescript
import api from './api';

export const calendarSyncService = {
    /**
     * Obtener estado de conexion de calendarios del usuario actual.
     */
    async getStatus() {
        const { data } = await api.get('/calendar-oauth/status');
        return data;
    },

    /**
     * Obtener URL de redireccion OAuth para conectar calendario.
     */
    async getRedirectUrl(provider: 'google' | 'microsoft') {
        const { data } = await api.get(`/calendar-oauth/${provider}/redirect`);
        return data;
    },

    /**
     * Desconectar un proveedor de calendario.
     */
    async disconnect(provider: 'google' | 'microsoft') {
        const { data } = await api.post(`/calendar-oauth/${provider}/disconnect`);
        return data;
    },
};
```

---

## 7. Frontend -- Archivos a Modificar

### 7.1 `calendar.vue` -- Diferenciacion visual por fuente

**Archivo:** `resources/js/src/views/apps/calendar.vue`

**Cambios:**

1. **Agregar badges de fuente en los eventos del calendario:**

En el template slot `eventContent`, agregar un icono pequeno que indique la fuente:

```vue
<template v-slot:eventContent="arg">
    <div class="fc-event-main-frame flex items-center px-1 py-0.5 text-white">
        <!-- Badge de fuente -->
        <span
            v-if="arg.event.extendedProps.sync_source === 'google'"
            class="mr-1 text-xs opacity-75"
            title="Google Calendar"
        >G</span>
        <span
            v-if="arg.event.extendedProps.sync_source === 'outlook'"
            class="mr-1 text-xs opacity-75"
            title="Microsoft Outlook"
        >O</span>
        <div class="fc-event-time font-semibold px-0.5">{{ arg.timeText }}</div>
        <div class="fc-event-title-container">
            <div class="fc-event-title fc-sticky">{{ arg.event.title }}</div>
        </div>
    </div>
</template>
```

2. **Agregar leyenda de fuentes junto a la leyenda de categorias:**

```vue
<!-- Leyenda de fuentes -->
<div class="flex items-center mt-1 flex-wrap gap-3 text-xs text-gray-500">
    <span>{{ $t('calendar.source') }}:</span>
    <span class="flex items-center gap-1">
        <span class="font-bold">V</span> VITE-IT
    </span>
    <span class="flex items-center gap-1">
        <span class="font-bold">G</span> Google
    </span>
    <span class="flex items-center gap-1">
        <span class="font-bold">O</span> Outlook
    </span>
</div>
```

3. **Pasar `sync_source` como `extendedProps` al mapear eventos desde la API:**

En la funcion que transforma los eventos de la API a objetos FullCalendar, agregar:

```typescript
extendedProps: {
    ...event.extendedProps,
    sync_source: event.sync_source ?? 'local',
    external_id: event.external_id,
},
```

4. **Deshabilitar edicion (drag/resize) de eventos externos:**

En las opciones de FullCalendar, agregar:

```typescript
eventAllow: (dropInfo, draggedEvent) => {
    // Solo permitir drag de eventos locales
    return draggedEvent.extendedProps.sync_source === 'local';
},
```

Y en el mapeo de eventos:

```typescript
editable: event.sync_source === 'local',
```

### 7.2 `profile.vue` -- Agregar seccion de calendarios

**Archivo:** `resources/js/src/views/users/profile.vue`

Importar y montar el componente `CalendarConnections` en la vista de perfil. Si la vista de perfil usa tabs, agregar un nuevo tab "Calendarios". Si no, agregar como seccion debajo de la informacion de perfil.

```vue
<script setup>
import CalendarConnections from './profile/CalendarConnections.vue';
</script>

<!-- En el template, agregar la seccion -->
<CalendarConnections />
```

### 7.3 `EventResource` -- Incluir campos de sync en la respuesta API

**Archivo:** `app/Http/Resources/EventResource.php`

Agregar al array de retorno:

```php
'sync_source' => $this->sync_source,
'external_id' => $this->external_id,
'is_external' => $this->isExternal(),
```

---

## 8. Traducciones

### Claves nuevas para `es.json`, `en.json`, `fr.json`

**Seccion `calendar_sync`:**

```json
{
    "calendar_sync": {
        "title": "Calendarios Conectados",
        "connect": "Conectar",
        "disconnect": "Desconectar",
        "connected": "Conectado",
        "not_connected": "No conectado",
        "last_sync": "Ultimo sync",
        "status_active": "Activo",
        "status_error": "Error",
        "status_paused": "Pausado",
        "connected_success": "{provider} conectado exitosamente",
        "disconnected": "Calendario desconectado",
        "connect_error": "Error al conectar calendario",
        "confirm_disconnect": "Desconectar calendario?",
        "disconnect_warning": "Los eventos sincronizados se mantendran pero no se actualizaran mas.",
        "google_calendar": "Google Calendar",
        "microsoft_outlook": "Microsoft Outlook",
        "sync_every_15_min": "Sincronizacion automatica cada 15 minutos",
        "error_details": "Detalles del error"
    }
}
```

**Seccion `calendar` (agregar a las claves existentes):**

```json
{
    "calendar": {
        "source": "Fuente",
        "source_local": "VITE-IT",
        "source_google": "Google Calendar",
        "source_outlook": "Microsoft Outlook",
        "external_event_readonly": "Este evento proviene de {source} y no se puede editar aqui."
    }
}
```

**Equivalente en `en.json`:**

```json
{
    "calendar_sync": {
        "title": "Connected Calendars",
        "connect": "Connect",
        "disconnect": "Disconnect",
        "connected": "Connected",
        "not_connected": "Not connected",
        "last_sync": "Last sync",
        "status_active": "Active",
        "status_error": "Error",
        "status_paused": "Paused",
        "connected_success": "{provider} connected successfully",
        "disconnected": "Calendar disconnected",
        "connect_error": "Error connecting calendar",
        "confirm_disconnect": "Disconnect calendar?",
        "disconnect_warning": "Synced events will remain but will no longer be updated.",
        "google_calendar": "Google Calendar",
        "microsoft_outlook": "Microsoft Outlook",
        "sync_every_15_min": "Automatic sync every 15 minutes",
        "error_details": "Error details"
    },
    "calendar": {
        "source": "Source",
        "source_local": "VITE-IT",
        "source_google": "Google Calendar",
        "source_outlook": "Microsoft Outlook",
        "external_event_readonly": "This event comes from {source} and cannot be edited here."
    }
}
```

**Equivalente en `fr.json`:**

```json
{
    "calendar_sync": {
        "title": "Calendriers connectes",
        "connect": "Connecter",
        "disconnect": "Deconnecter",
        "connected": "Connecte",
        "not_connected": "Non connecte",
        "last_sync": "Derniere sync",
        "status_active": "Actif",
        "status_error": "Erreur",
        "status_paused": "En pause",
        "connected_success": "{provider} connecte avec succes",
        "disconnected": "Calendrier deconnecte",
        "connect_error": "Erreur de connexion au calendrier",
        "confirm_disconnect": "Deconnecter le calendrier?",
        "disconnect_warning": "Les evenements synchronises resteront mais ne seront plus mis a jour.",
        "google_calendar": "Google Calendar",
        "microsoft_outlook": "Microsoft Outlook",
        "sync_every_15_min": "Synchronisation automatique toutes les 15 minutes",
        "error_details": "Details de l'erreur"
    },
    "calendar": {
        "source": "Source",
        "source_local": "VITE-IT",
        "source_google": "Google Calendar",
        "source_outlook": "Microsoft Outlook",
        "external_event_readonly": "Cet evenement provient de {source} et ne peut pas etre modifie ici."
    }
}
```

---

## 9. Configuracion y Setup Externo

### 9.1 Google Cloud Console

1. Ir a [Google Cloud Console](https://console.cloud.google.com/).
2. Seleccionar el proyecto existente (el mismo usado para Google Drive storage).
3. Ir a **APIs & Services > Library**.
4. Habilitar **Google Calendar API**.
5. Ir a **APIs & Services > Credentials**.
6. El OAuth 2.0 Client ID existente se puede reutilizar, pero se deben agregar los **redirect URIs** del calendario:
   - `https://{APP_URL}/api/calendar-oauth/google/callback`
7. Ir a **OAuth consent screen** y verificar que los scopes incluyan:
   - `https://www.googleapis.com/auth/calendar.events`
8. Si la app esta en modo "Testing", agregar los emails de los usuarios que probaran.

**IMPORTANTE:** Las credenciales OAuth de Google (client_id, client_secret) son las mismas que ya se configuran por tenant via `OAuthCredentialService`. No se necesitan variables de entorno adicionales para las credenciales en si. Solo se necesita habilitar la Calendar API en el proyecto de Google Cloud.

### 9.2 Azure Portal (Microsoft)

1. Ir a [Azure Portal > App Registrations](https://portal.azure.com/#blade/Microsoft_AAD_IAM/ActiveDirectoryMenuBlade/RegisteredApps).
2. Seleccionar la app registration existente (la misma usada para OneDrive/SharePoint).
3. Ir a **API Permissions > Add a permission > Microsoft Graph > Delegated permissions**.
4. Agregar:
   - `Calendars.ReadWrite`
   - `User.Read` (probablemente ya esta)
5. Click en **Grant admin consent** (si es requerido por la politica del tenant Azure AD).
6. Ir a **Authentication > Redirect URIs**.
7. Agregar:
   - `https://{APP_URL}/api/calendar-oauth/microsoft/callback`

**IMPORTANTE:** Al igual que con Google, las credenciales de Microsoft son las mismas que ya gestiona `OAuthCredentialService` por tenant. La diferencia son los scopes solicitados y la redirect URI.

### 9.3 Variables de Entorno

No se necesitan variables de entorno nuevas. Las credenciales OAuth se manejan por tenant via `OAuthCredentialService` (almacenadas en la tabla `tenant_oauth_credentials`, encriptadas).

Las redirect URIs se construyen dinamicamente en `UserCalendarOAuthController` usando `config('app.url')`.

### 9.4 Queue Configuration

El sistema ya debe tener un queue worker corriendo para procesar los jobs:

```bash
php artisan queue:work --queue=default --tries=3 --backoff=30
```

Si se quiere separar la cola de sync para no bloquear otros jobs:

```bash
# En SyncEventToCalendarJob y PullCalendarEventsJob:
public $queue = 'calendar-sync';

# Worker dedicado:
php artisan queue:work --queue=calendar-sync --tries=3 --backoff=30
```

---

## 10. Consideraciones de Seguridad y Privacidad

### 10.1 Tokens OAuth
- Los tokens de acceso y refresh se almacenan **encriptados** en la base de datos (cast `encrypted` en el modelo `OauthToken`).
- Los tokens nunca se exponen en respuestas API al frontend (campo `hidden` en el modelo).
- El refresh usa **Cache lock** para evitar race conditions.

### 10.2 Separacion de Permisos
- Los tokens de calendario son **user-level**: un usuario solo puede acceder a sus propios eventos externos.
- La columna `purpose` previene que un token de storage se use para calendario y viceversa.
- El `state` parameter en el flujo OAuth previene ataques CSRF.

### 10.3 Scopes Minimos
- Google: solo `calendar.events` (no `calendar` completo). Esto limita el acceso a eventos, no a la configuracion del calendario.
- Microsoft: solo `Calendars.ReadWrite` (no `Mail.ReadWrite` ni otros).

### 10.4 Rate Limiting
- Google Calendar API: 1,000,000 queries/dia (amplio para MVP).
- Microsoft Graph: 10,000 requests/10 minutos por app. Con polling cada 15 min, ~4 requests/usuario/hora. Para 100 usuarios = 400 requests/hora (bien dentro del limite).

### 10.5 Datos de Eventos Externos
- Los eventos importados se almacenan con `sync_source` para identificar su origen.
- `synced_by_user_id` establece trazabilidad de quien importo cada evento.
- Los eventos externos no tienen `client_id` ni `case_id` (a menos que el usuario los vincule manualmente despues).

### 10.6 Desconexion y Limpieza
- Al desconectar, los tokens se eliminan pero los eventos importados se **mantienen** (con `sync_source` intacto).
- Los eventos importados dejan de actualizarse pero no se eliminan automaticamente.
- Se informa al usuario de este comportamiento antes de confirmar la desconexion.

### 10.7 Manejo de Errores y Circuit Breaker
- Despues de 5 errores consecutivos en el pull, el `status` cambia a `'error'` y se detiene la sincronizacion para ese usuario.
- El usuario puede ver el error en su perfil y re-conectar si es necesario.
- Esto previene que un token revocado/expirado genere miles de requests fallidos.

---

## 11. Checklist de Implementacion

### Fase 1: Infraestructura de Base de Datos y Modelo

- [x] Crear migracion `2026_04_16_000002_add_purpose_to_oauth_tokens.php`
- [x] Crear migracion `2026_04_16_000003_create_calendar_sync_status_table.php`
- [x] Crear migracion `2026_04_16_000004_add_external_etag_to_events.php`
- [ ] Ejecutar migraciones y verificar esquema
- [x] Agregar `purpose` a `OauthToken` model (`$fillable`)
- [x] Crear modelo `CalendarSyncStatus`
- [x] Agregar `external_etag`, `synced_by_user_id` a `Event` model (`$fillable`)
- [x] Agregar relaciones al modelo `User` (`calendarSyncStatus`, `hasCalendarConnected`)
- [x] Agregar metodos helper al modelo `Event` (`isExternal`, `getSyncSourceLabel`, `syncedByUser`)

### Fase 2: OAuth Flow de Calendario

- [x] Crear `UserCalendarOAuthController` con metodos `redirect`, `callback`, `status`, `disconnect`
- [x] Agregar metodos a `OAuthTokenService`: `storeUserCalendarToken`, `getValidUserCalendarToken`, `revokeUserCalendarToken`, `hasUserCalendarToken`
- [x] Modificar `refreshMicrosoftToken` para soportar scopes de calendario segun `purpose`
- [x] Registrar rutas en `routes/api.php` (autenticadas y callback publico)
- [ ] Probar flujo OAuth completo con Google Calendar
- [ ] Probar flujo OAuth completo con Microsoft Outlook

### Fase 3: Servicios de Proveedores

- [x] Crear `CalendarProviderInterface`
- [x] Crear `GoogleCalendarService` (implements `CalendarProviderInterface`)
  - [x] `createEvent()` con formato correcto (dateTime vs date para all-day)
  - [x] `updateEvent()` con PUT
  - [x] `deleteEvent()` con DELETE
  - [x] `listEvents()` con `updatedMin` y paginacion
  - [x] `normalizeEvent()` para convertir formato Google a interno
- [x] Crear `MicrosoftCalendarService` (implements `CalendarProviderInterface`)
  - [x] `createEvent()` con formato Graph API
  - [x] `updateEvent()` con PATCH
  - [x] `deleteEvent()` con DELETE
  - [x] `listEvents()` con `$filter` y paginacion
  - [x] `normalizeEvent()` para convertir formato Microsoft a interno
- [ ] Tests unitarios para normalizacion de eventos (ambos proveedores)

### Fase 4: Sincronizacion Push

- [x] Crear `CalendarSyncService` con metodo `pushEvent`
- [x] Crear `SyncEventToCalendarJob` (queued, con retry y backoff)
- [x] Crear `EventSyncObserver` (created, updated, deleted)
- [x] Registrar observer en `AppServiceProvider`
- [x] Agregar flag estatico `CalendarSyncService::$isPulling` para evitar loops
- [ ] Probar: crear evento local -> aparece en Google Calendar
- [ ] Probar: editar evento local -> se actualiza en Google Calendar
- [ ] Probar: eliminar evento local -> se elimina de Google Calendar
- [ ] Repetir pruebas con Microsoft Outlook

### Fase 5: Sincronizacion Pull

- [x] Agregar metodo `pullEvents` a `CalendarSyncService`
- [x] Implementar `processExternalEvents` (crear, actualizar, soft-delete)
- [x] Crear `PullCalendarEventsJob` (scheduled, unique)
- [x] Registrar job en `routes/console.php` scheduler (cada 15 minutos)
- [x] Implementar circuit breaker (5 errores -> status='error')
- [ ] Probar: crear evento en Google Calendar -> aparece en VITE-IT en el proximo pull
- [ ] Probar: editar evento en Google Calendar -> se actualiza en VITE-IT
- [ ] Probar: eliminar evento en Google Calendar -> soft-delete en VITE-IT
- [ ] Repetir pruebas con Microsoft Outlook

### Fase 6: Frontend -- Conexion de Calendario

- [x] Crear `calendarSyncService.ts`
- [x] Crear `CalendarConnections.vue`
  - [x] UI de estado de conexion (Google + Microsoft)
  - [x] Boton conectar -> redirige a OAuth
  - [x] Boton desconectar -> confirmacion + API call
  - [x] Mostrar ultimo sync y errores
  - [x] Manejar query param `?calendar_connected=` post-callback
- [x] Integrar `CalendarConnections.vue` en `profile.vue`

### Fase 7: Frontend -- Calendario Visual

- [x] Modificar `EventResource.php` para incluir `sync_source`, `external_id`, `is_external`
- [x] Modificar `calendar.vue`:
  - [x] Agregar badge de fuente (G/O) en el template de eventos
  - [x] Agregar leyenda de fuentes
  - [x] Pasar `sync_source` como `extendedProps` (via EventResource)
  - [x] Deshabilitar drag/resize para eventos externos
  - [ ] Mostrar tooltip/modal con info de read-only para eventos externos

### Fase 8: Traducciones y Polish

- [x] Agregar claves `calendar_sync.*` a `es.json`
- [x] Agregar claves `calendar_sync.*` a `en.json`
- [x] Agregar claves `calendar_sync.*` a `fr.json`
- [x] Agregar claves `calendar.source*` y `calendar.external_event_readonly` a los 3 idiomas
- [x] Revisar todos los textos en los 3 idiomas

### Fase 9: Configuracion de Proveedores

- [ ] Habilitar Google Calendar API en Google Cloud Console
- [ ] Agregar redirect URI de calendario en Google Cloud Console
- [ ] Agregar scope `calendar.events` en OAuth consent screen de Google
- [ ] Agregar permiso `Calendars.ReadWrite` en Azure Portal
- [ ] Agregar redirect URI de calendario en Azure Portal
- [ ] Verificar que queue worker esta corriendo

### Fase 10: Testing y QA

- [ ] Test: flujo OAuth Google completo (conectar -> sync -> desconectar)
- [ ] Test: flujo OAuth Microsoft completo
- [ ] Test: push de evento nuevo, editado y eliminado (ambos proveedores)
- [ ] Test: pull de evento nuevo, editado y eliminado (ambos proveedores)
- [ ] Test: circuit breaker se activa despues de 5 errores
- [ ] Test: eventos externos son read-only en el calendario
- [ ] Test: desconexion mantiene eventos pero detiene sync
- [ ] Test: reconexion reanuda sync
- [ ] Test: multiples usuarios con distintos proveedores
- [ ] Test: token refresh funciona correctamente para tokens de calendario
