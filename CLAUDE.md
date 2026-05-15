# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**VITE-IT Immigration** is a **SaaS multi-tenant system** for immigration case management. It allows law firms to manage clients, companions, immigration cases, documents (with cloud storage), a shared event calendar (with bidirectional Google/Outlook sync), a Scrum board, to-do lists, and expiration alerts. It uses a **Single Page Application (SPA)** architecture: Laravel 12 serves the initial HTML and acts as an API server; Vue 3.5 handles all client-side routing and UI.

## Protocolo de Carga de Contexto

Antes de implementar cualquier spec o feature, leer en este orden:
1. `spec/INDEX.md` — identifica el spec y sus dependencias (columna "Relacionados")
2. `PATTERNS.md` — confirma los patrones técnicos aplicables
3. `MEMORY.md` — revisa decisiones recientes y deuda técnica activa
4. El spec específico completo: `spec/<NN>_<nombre>.md`

Al completar un feature: actualizar `MEMORY.md` (agregar entrada en "Implementaciones Recientes") y cambiar el estado en `spec/INDEX.md` (`PEND`/`PART` → `DONE`).

Atajo: `/load-context` ejecuta este protocolo automáticamente.

## Common Commands

```bash
# Development
npm run dev              # Start Vite dev server (hot reload)
npm run build            # Production build
php artisan serve        # Start Laravel dev server (if not using Herd)

# Queue & Scheduler (required for calendar sync, backups, folder sync)
php artisan queue:work   # Process queued jobs
php artisan schedule:run # Run scheduled tasks (cron: * * * * *)

# Database
php artisan migrate      # Run database migrations
php artisan migrate:fresh --seed  # Reset and seed database (dev only)

# Testing
./vendor/bin/phpunit                    # Run all PHP tests
./vendor/bin/phpunit tests/Unit         # Run unit tests only
./vendor/bin/phpunit tests/Feature      # Run feature tests only
./vendor/bin/phpunit --filter=TestName  # Run specific test

# Cache & Optimization
php artisan config:cache    # Cache configuration
php artisan route:cache     # Cache routes
php artisan optimize        # Optimize for production
```

## Architecture Overview

### Request Flow
```
Browser Request (any URL)
    │
    ▼
routes/web.php  →  AppController::index()  →  resources/views/app.blade.php
    │
    ▼
Vue 3 SPA mounts on #app  →  Vue Router handles all navigation

API calls:
Browser  →  /api/*  →  TenantMiddleware (resolves tenant)
                    →  auth:sanctum (Sanctum SPA cookie auth)
                    →  Controller  →  Service  →  Model (with TenantScope)
                                              →  Resource (JSON response)
```

### Backend Layer Architecture
```
HTTP Layer
  Controllers (app/Http/Controllers/Api/) — 33 controllers
      ↕ Resources (app/Http/Resources/) — 20 resources, map models to JSON
  Middleware:
      TenantMiddleware    — resolves active tenant from request
      ValidateBackupApiKey — protects external backup endpoint
      SecurityHeaders     — CSP / security response headers
      EnsureSingleSession — validates current session is the authorised one (single-session enforcement)

Domain / Service Layer (app/Services/)
  Auth/     — AuthService, PasswordResetService
  Case/     — CaseService, CaseTaskService, CaseInvoiceService
  Client/   — ClientService
  Companion/ — CompanionService
  Timesheet/ — TimeLogService (startTimer, stopTimer, logManual, getLogsForCase, getActiveTimerForUser)
  Document/ — DocumentService, FolderService (createSelectedStructure — Spec 56),
               FolderNameValidator (Spec 56 — validates folder names: forbidden chars, reserved Windows names),
               CloudDocumentSyncService, CaseFolderSyncService, DocumentAuditService, StorageQuotaService
  Storage/  — StorageProviderFactory, LocalStorageProvider, OneDriveProvider,
               GoogleDriveProvider, SharePointProvider, MicrosoftGraphBaseProvider,
               ResilientStorageProvider (circuit breaker), CircuitBreaker
  Calendar/ — CalendarSyncService, GoogleCalendarService, MicrosoftCalendarService,
               CalendarProviderInterface
  Backup/   — TenantBackupService
  Search/   — GlobalSearchService
  Trash/    — TrashService
  User/     — UserService
  Root:     — TenantService, OAuthTokenService, OAuthCredentialService,
               LegalDocumentService, TwoFactorService

Data Layer (app/Models/) — 25 models
  Core:    User, Tenant, UserProfile
  Domain:  Client, Companion, ImmigrationCase, CaseTask, CaseInvoice,
           CaseType, CaseImportantDate, Document, DocumentFolder,
           LegalDocument, Event, CalendarSyncStatus
  System:  OauthToken, Activity, BackupLog, LoginAttempt, InvitationCode,
           SessionRevocation (security infra — no BelongsToTenant)
  Kanban:  ScrumColumn, ScrumTask, Todo
  Timesheet: TimeLog

Cross-cutting
  Events (app/Events/):
      SessionRevoked — fired by establishSingleSession() when a prior session is displaced;
                       carries victim User, revokingIp, revokingUserAgent, stopReason
  Listeners (app/Listeners/):
      StopActiveTimerOnSessionRevoked — sync; stops active TimeLog for the victim
      LogSessionRevocation            — async (queue: default); writes to session_revocations + ActivityLog
      DetectKickingAbuse              — async (queue: high); locks account (security_locked_until)
                                        after ≥5 kicks in 10 min from same victim
  Observers (app/Observers/) — 6 observers
      ClientObserver, CompanionObserver, CaseObserver — cascade soft-delete
      ImmigrationCaseObserver — cascade soft-delete for case children
      EventSyncObserver       — triggers SyncEventToCalendarJob on save/delete
      TimeLogObserver         — updates total_time_spent_seconds cache on cases (atomic delta UPDATE)
  Jobs (app/Jobs/) — 6 jobs
      SyncEventToCalendarJob      — push local event to Google/Outlook
      PullCalendarEventsJob       — pull events from all connected calendars (every 15 min)
      SyncCaseFolderStructure     — sync folder tree to cloud storage
      GenerateTenantBackupJob     — backup tenant data
      ScanDocumentForVirus        — antivirus scan on upload
      AutoStopExpiredTimersJob    — auto-stop orphaned timers per tenant (every 1 min, ShouldBeUnique)
  Scheduler (routes/console.php):
      trash:purge --days=30       → daily at 03:00
      PullCalendarEventsJob       → every 15 minutes
      AutoStopExpiredTimersJob    → every 1 minute (stops timers > tenant.settings.max_timer_duration)
```

### Frontend Layer Architecture
```
State (resources/js/src/stores/) — 18 Pinia stores
  useAppStore       — theme, locale, layout, sidebar, RTL
  useAuthStore      — login/logout flow
  useUserStore      — authenticated user + loaded permissions
  usePermissionStore — flat map { 'cases.create': true, ... }
  useCaseStore, useClientStore, useCompanionStore
  useDocumentStore, useDashboardStore, useTenantStore
  useRoleStore, useProfileStore, useScrumStore
  useTodoStore, useTrashStore, useListFilters
  useTimesheetStore — active timer, case log cache, CRUD for time_logs; BroadcastChannel('timesheet') for multi-tab sync
  useSessionStore   — kicked-out banner state (session_revoked) + account_locked state (account_security_locked);
                      sessionStorage persistence for both; BroadcastChannel('session') for multi-tab propagation
  useImportantDatesStore — Spec 57; 60-day milestone radar, filters, optimistic calendar linking

Services (resources/js/src/services/) — 23 services
  api.ts              — axios instance; 401 → logout+clear state;
                        419 (CSRF expired) → auto-refresh + retry
  authService, twoFactorService, userService, profileService
  clientService, companionService, caseService
  documentService, legalDocumentService
  tenantService, roleService, oauthService
  scrumService, todoService, trashService
  searchService, dashboardService, backupService
  timesheetService         — startTimer, stopTimer, createManualLog, getCaseLogs, getActiveTimer
  calendarSyncService      — getStatus, getRedirectUrl, disconnect
  caseFolderTemplateService — getDefaults() with SPA-session cache; feeds the Spec 56 wizard step
  importantDateAlertService — Spec 57; list(filters, page), linkEvent(dateId, eventId)

Routing (resources/js/src/router/index.ts) — 60+ routes
  Route meta guards: requiresAuth, requiresVerified, permission, role, guest
  Layouts: app-layout (sidebar+header) | auth-layout (minimal)

Composables (resources/js/src/composables/)
  usePermissions() — powers v-can / v-role directives
  useMeta()        — sets <title> and meta tags per page
  useDebounce()    — debounced search inputs

i18n (resources/js/src/locales/) — 16 language FLAT JSON files
  CRITICAL: keys are FLAT strings — "calendar_sync.title": "..."
            NOT nested objects — {"calendar_sync": {"title": ...}}
  Arabic (ae) automatically enables RTL via useAppStore
```

---

## Multi-Tenancy

Every business model uses `BelongsToTenant` trait, which registers a global Eloquent scope (`TenantScope`) that automatically filters all queries by the active tenant.

**Rule: Every new business model must:**
1. Use `use BelongsToTenant;`
2. Have a `tenant_id` FK in its migration
3. Set `tenant_id` from the active tenant in `store()` before saving

`TenantMiddleware` resolves the tenant from the request (subdomain or header) and binds it to the container. The `tenant` middleware alias is applied to all authenticated API routes.

**Why Global Scope instead of RLS or separate DBs:** MySQL doesn't support RLS natively. Separate DBs would make migrations, backups, and connection pooling complex at this scale. Eloquent global scope gives sufficient isolation without infrastructure changes.

**Exception — `CaseImportantDate` (Spec 57):** This model intentionally lacks `BelongsToTenant` and `tenant_id`. Multi-tenant isolation is enforced via an explicit JOIN on `cases.tenant_id` in every query. Never use `CaseImportantDate::find($id)` directly in a controller — always go through `ImportantDateAlertController` (alerts) or the case detail flow which scopes via the parent `ImmigrationCase`. A `CaseImportantDatePolicy` enforces cross-tenant ownership checks at the policy layer.

---

## Authentication & Permissions

### Auth: Laravel Sanctum SPA (cookie-based)
- Auth via HttpOnly cookies + CSRF token. **Not Bearer tokens.**
- `api.ts` calls `/sanctum/csrf-cookie` before login.
- On 419 (CSRF expired): interceptor auto-refreshes cookie and retries the request.
- On 401 `reason: 'session_revoked'`: session was kicked by a new login on another device — sets `useSessionStore.kickedOut`, shows banner on login page, clears all stores.
- On 401 (generic): clears auth state and redirects to login.
- **Never store auth tokens in Pinia or localStorage.**

### Single Active Session per User (Spec 53)
- `SESSION_DRIVER=database` — `sessions` table stores all active sessions.
- `users.current_session_id` column — points to the one valid session. Set via `AuthService::establishSingleSession()` on every successful login (after `session()->regenerate()`).
- `EnsureSingleSession` middleware — applied to ALL authenticated route groups; responds 401 + `reason: 'session_revoked'` if the session ID doesn't match `current_session_id`.
- **2FA timing:** `establishSingleSession()` is called AFTER 2FA verification, never before. An attacker with only credentials (no TOTP code) cannot kick a legitimate user.
- **Timer safety:** `stopActiveTimerForRevokedSession()` auto-stops any running `TimeLog` before invalidating the session. `AutoStopExpiredTimersJob` is the fallback (runs every minute).
- **Multi-tab:** `BroadcastChannel('session')` propagates the kick to all tabs of the same browser instantly.
- `users.current_session_id` is in `$hidden` — never exposed in API responses.
- `users.security_locked_until` — set by `DetectKickingAbuse` listener when ≥5 kicks detected in 10 min; `EnsureSingleSession` enforces it with 401 + `reason: 'account_security_locked'`.
- Password change invalidates all other sessions but keeps the current one active.
- **Event-driven side effects (Spec 54):** `establishSingleSession()` fires `SessionRevoked` event → `StopActiveTimerOnSessionRevoked` (sync), `LogSessionRevocation` (async, queue: default), `DetectKickingAbuse` (async, queue: high).
- `session_revocations` table stores each kick event for abuse detection queries (no BelongsToTenant).

### Permissions: Spatie Laravel-Permission
- Format: `resource.action` (e.g. `cases.create`, `documents.delete`)
- Roles: `super-admin`, `admin`, `consultor`, `apoyo`, `contador`, `cliente`, `user`
- Permissions are seeded via dedicated migrations (not just seeders) so they survive `migrate:fresh` in production.
- On login, permissions are loaded and cached in `usePermissionStore` (flat boolean map).
- `v-can="'cases.create'"` and `v-role="'admin'"` directives: **UX only — hide buttons, not security.**
- **Real authorization:** `$this->authorize()` or `Gate::allows()` in controllers.

### Adding a new permission
1. Create a migration that calls `Permission::firstOrCreate(['name' => 'resource.action'])`
2. Assign to appropriate roles in the same migration
3. Frontend: add guard in route meta `permission: 'resource.action'` and use `v-can` in template

---

## Storage Strategy Pattern

`StorageService` is a façade that delegates to a provider based on tenant configuration:

```
StorageService
  → StorageProviderFactory → selects: LocalStorageProvider
                                    | OneDriveProvider
                                    | GoogleDriveProvider
                                    | SharePointProvider
                             (all extend MicrosoftGraphBaseProvider for MS providers)
  → ResilientStorageProvider wraps any provider (circuit breaker)
  → CircuitBreaker: tracks failures; opens after threshold, resets after timeout
```

- Tenant-level OAuth tokens: `OauthToken` with `purpose='storage'`
- `OAuthTokenService` handles token refresh; scope depends on `purpose` field
- `OAuthCredentialService` manages tenant-level client_id/client_secret for each provider
- Tenant chooses storage type in Settings → Storage; DocumentService transparently uses the active provider

---

## Calendar Sync (Bidirectional)

Two separate OAuth token types for the same providers (Google, Microsoft):
- `purpose='storage'` → tenant-level, files
- `purpose='calendar'` → user-level, calendar events

### Push (local → external)
`EventSyncObserver` (registered in `AppServiceProvider`) intercepts Event created/updated/deleted.
→ Dispatches `SyncEventToCalendarJob` (queue, 3 retries with backoff).
→ Job calls `CalendarSyncService::pushEvent()` for each connected user.

### Pull (external → local)
`PullCalendarEventsJob` runs every 15 minutes via scheduler.
→ Iterates all `CalendarSyncStatus` records with `status='active'`.
→ Calls `CalendarSyncService::pullEvents()` → `GoogleCalendarService` or `MicrosoftCalendarService`.
→ Creates/updates events with `sync_source='google'|'outlook'`, soft-deletes cancelled ones.
→ Uses `updateQuietly()` to avoid re-triggering the observer.

### Anti-loop guard
`CalendarSyncService::$isPulling = true` is set during pull operations.
`EventSyncObserver::shouldSync()` returns false when this flag is set.
**Why static:** The flag lives in the job worker process. `PullCalendarEventsJob` is `ShouldBeUnique`, so only one pull runs per process at a time.

### Circuit breaker
After 5 consecutive errors, `calendar_sync_status.status` is set to `'error'`.
The pull job skips records in `'error'` state (user must reconnect to reset).

### OAuth flow
`UserCalendarOAuthController`: redirect → callback → creates `CalendarSyncStatus` record.
State key: `calendar_oauth_state:{state}` (distinct from storage: `oauth_state:{state}`).
On success: redirects to `/users/profile?calendar_connected={provider}`.

---

## Known Gotchas & Anti-patterns

### G1. New model without BelongsToTenant
All tenants will see the model's records. Every business model needs the trait + `tenant_id` FK.

### G2. MySQL FK-backed index cannot be dropped directly
`SQLSTATE[HY000]: 1553 Cannot drop index: needed in a foreign key constraint`
Pattern: `dropForeign(['col'])` → `dropIndex/dropUnique(['col', ...])` → create new index → `$table->foreign('col')->...`
See migration `2026_04_16_000002_add_purpose_to_oauth_tokens.php` for the full pattern.

### G3. 401 handler must clear stores before router.push
If `router.push('/auth/login')` runs before `userStore.clearUser()` + `permissionStore.clear()`, route guards may loop-redirect. Order matters.

### G4. Permissions must be in migrations, not only in seeders
Seeders are for dev data. If a permission only exists in a seeder, `migrate:fresh` in staging/prod destroys it. Use `Permission::firstOrCreate()` in a dedicated migration.

### G5. v-can / v-role are UX — not security
The directives hide UI. Backend authorization (`$this->authorize()`) is the real gate.

### G6. i18n keys must be FLAT strings
`"module.key": "value"` — never nested objects. Vue I18n supports both, but this project uses flat throughout. Python `json.load/dump` can silently flatten/nest; always verify.

### G7. Observers registered once in AppServiceProvider
Never call `Model::observe()` inside a Job, Controller, or Service. All observer registrations live in `AppServiceProvider::boot()`.

### G8. CalendarSyncService::$isPulling is process-scoped
If pull jobs ever run on multiple workers concurrently (parallel queue), the static flag won't prevent cross-process loops. Would need a Redis cache key instead.

### G9. Cloud storage operations may fail silently without ResilientStorageProvider
Always use `StorageService` (which wraps providers in `ResilientStorageProvider`) rather than calling provider classes directly.

### G10. `unplugin-vue-i18n` rejects angle brackets `<>` as HTML in locale messages
If an i18n JSON value contains `<` or `>`, the Vite build fails with "Detected HTML in message". Escape them using Vue I18n literal interpolation: `{'<'}` and `{'>'}`. Example in `wizard.step_folders.invalid_chars`. Never use `&lt;`/`&gt;` (would show literally) or raw `<`/`>`.

### G11. `ImmigrationCase` uses `protected $table = 'cases'` — not `immigration_cases`
The Eloquent model `ImmigrationCase` maps to the physical table `cases` in MySQL. Any raw SQL, `DB::table()`, or hardcoded JOIN string must use `'cases'`, not `'immigration_cases'`. Eloquent relations resolve correctly via the model, but string-based JOINs in query builders will fail with "Table not found" if the wrong name is used. (Discovered in Spec 57 `ImportantDateAlertController`.)

### G12. Case Creation Wizard has 7 steps (Spec 56)
Steps: 1=CaseType, 2=Client, 3=Companions, 4=Details, **5=Folders** (new — dynamic folder selection), 6=Checklist, 7=Summary. `isLastStep === 7`, `canGoNext: step < 7`. Wizard state includes `folders.selected: CaseFolderInput[]` persisted in sessionStorage. `FolderService::createDefaultStructure()` is a backward-compatible alias for `createSelectedStructure($case, null)` — existing callers unchanged.

### G13. `encrypted:array` cast only accepts flat serializable structures (Spec 60)
The `encrypted:array` cast on `CaseIrccCredential::$security_questions` serializes and encrypts the full JSON in one operation. The array must contain only plain PHP types (strings, ints, null) — no Carbon instances, no Eloquent models. Validate in `StoreIrccCredentialRequest` that each element is `{pregunta: string, respuesta: string}`. Also, `encrypted:array` returns `null` (not `[]`) for a NULL DB column — always coalesce to a 5-element default array before returning from the Resource.

### G14. `encrypted` cast breaks `where` queries (Spec 60)
Fields cast as `encrypted` in `CaseIrccCredential` (ircc_username, ircc_password, etc.) cannot be searched via Eloquent `where`. The ciphertext is non-deterministic (random IV per encryption). If future search over these fields is needed, a separate HMAC-SHA256 column is required. Do not attempt `whereIrccUsername()` or similar.

### G15. IRCC cross-tenant must return 404, not 403 (Spec 60)
The `ircc_credentials.view` Gate and the route model binding for `ImmigrationCase` must return 404 (not 403) for resources belonging to another tenant. Returning 403 leaks the existence of the resource. Route model binding scoped to the active tenant already handles this via `TenantScope`.

### G16. IRCC secret tab trigger is UX friction — NOT security (Spec 60)
The triple-click mechanism on `case_number` in `cases/show.vue` that activates the IRCC tab is a UX layer to avoid accidental exposure. It is **not** a security control. The backend `Gate::define('ircc_credentials.view')` is the real authorization gate and validates on every request. Never conflate the frontend trigger with actual access control. An attacker with valid session cookies can call the API directly regardless of frontend state.

---

## Project Structure

```
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/    # 32 controllers (+ Admin/ subdirectory)
│   │   ├── Middleware/         # TenantMiddleware, ValidateBackupApiKey, SecurityHeaders, ...
│   │   └── Resources/          # 19 API resources
│   ├── Jobs/                   # 5 async jobs
│   ├── Models/                 # 23 Eloquent models (all BelongsToTenant)
│   ├── Observers/              # 5 observers
│   ├── Providers/              # AppServiceProvider (observer registration)
│   └── Services/               # Service layer (Auth/, Case/, Calendar/, Storage/, ...)
│
├── database/
│   ├── migrations/             # 25+ migrations (includes permission seeds)
│   ├── seeders/                # Dev data seeders only
│   └── factories/              # Model factories for testing
│
├── routes/
│   ├── api.php                 # REST API routes (auth:sanctum + tenant middleware)
│   ├── web.php                 # Catch-all → Vue SPA
│   └── console.php             # Scheduler definitions
│
├── spec/                       # Feature specs (45+ files, Markdown)
│
└── resources/js/src/           # Vue 3 frontend
    ├── main.ts                 # Entry point
    ├── App.vue                 # Root (layout switching)
    ├── router/index.ts         # 60+ routes with permission/role guards
    ├── stores/                 # 18 Pinia stores
    ├── services/               # 23 API service modules
    ├── composables/            # usePermissions, useMeta, useDebounce, ...
    ├── views/                  # Page components
    │   ├── apps/calendar.vue   # FullCalendar with Google/Outlook source badges
    │   ├── users/profile.vue   # Profile + CalendarConnections
    │   ├── clients/            # Client list + detail + companions
    │   ├── cases/              # Case list + detail + documents + tasks
    │   ├── auth/               # Login, register, 2FA, password reset
    │   └── admin/              # Tenant management (super-admin)
    ├── components/             # Shared components (EventFormModal, icon/*, layout/*)
    ├── locales/                # 16 FLAT JSON i18n files (en, es, fr, ...)
    └── assets/css/             # Tailwind + component CSS
```

---

## Key Technologies

### Backend
| Technology | Purpose |
|------------|---------|
| Laravel 12 / PHP 8.2+ | Framework |
| MySQL 8.0 | Database |
| Laravel Sanctum (SPA) | Cookie-based authentication |
| Spatie Laravel-Permission | RBAC roles & permissions |
| Laravel Queues (DB driver) | Async jobs |
| Laravel Scheduler | Cron tasks |

### Frontend
| Technology | Purpose |
|------------|---------|
| Vue 3.5 (Composition API) | UI Framework |
| TypeScript 5.7 | Type safety |
| Vue Router 4.5 | Client-side routing |
| Pinia 2.3 | State management |
| Tailwind CSS 3.4 | Utility-first CSS |
| Vue I18n 11 | Internationalization (16 languages) |
| Vite 6 | Build tool |
| FullCalendar | Calendar view |
| SweetAlert2 | Modals and alerts |
| vue3-datatable | Data tables |
| vue-draggable-plus | Drag & drop (Scrum board) |

---

## Important Patterns

### Import Alias
Use `@/` for imports from `resources/js/src/`:
```typescript
import { useAppStore } from '@/stores/index';
import api from '@/services/api';
```

### Vue Component Style
```vue
<script lang="ts" setup>
import { ref, computed } from 'vue';
import { useMeta } from '@/composables/use-meta';
useMeta({ title: 'Page Title' });
</script>
```

### Adding a new API endpoint
1. `php artisan make:controller Api/MyController`
2. Add service in `app/Services/`
3. Add route in `routes/api.php` (inside the `auth:sanctum + tenant` group)
4. Add resource in `app/Http/Resources/` if needed
5. Add TypeScript service in `resources/js/src/services/`
6. Add permissions in a new migration

### Adding a new page
1. Create view in `resources/js/src/views/`
2. Add route in `resources/js/src/router/index.ts` with `permission` meta if needed
3. Add sidebar entry in `resources/js/src/components/layout/Sidebar.vue`
4. Add i18n keys (FLAT) in all locale files

### Scheduled tasks
Define in `routes/console.php` using `Schedule::`. Queue worker must be running for jobs dispatched from the scheduler.

---

## Database

- **Connection:** MySQL 8.0
- **Multi-tenant:** all business tables have `tenant_id FK → tenants.id`
- **Soft deletes:** Client, Companion, ImmigrationCase, Document (cascade via observers)
- **Key tables:** `tenants`, `users`, `clients`, `companions`, `immigration_cases`, `case_tasks`, `case_invoices`, `case_types`, `documents`, `document_folders`, `legal_documents`, `events`, `calendar_sync_status`, `oauth_tokens` (purpose: storage|calendar), `activity_log`, `backup_logs`, `roles`, `permissions`, `model_has_roles`, `model_has_permissions`

---

## Internationalization

3 languages: English (en), Spanish (es), French (fr)
Translation files: `resources/js/src/locales/*.json` — **FLAT keys only.**

---

## Tailwind CSS Colors

```javascript
primary: '#4361ee'   // Indigo blue
secondary: '#805dca' // Purple
success: '#00ab55'   // Green
danger: '#e7515a'    // Red
warning: '#e2a03f'   // Orange
info: '#2196f3'      // Blue
dark: '#3b3f5c'
```

Dark mode: use `dark:` prefix.

---

## Development Notes

- FullCalendar alias: `@fullcalendar/core/vdom` → `@fullcalendar/core` (vite.config.ts)
- Swiper modules: import from `swiper/modules`
- Perfect Scrollbar: named export `{ PerfectScrollbarPlugin }`
- `@unhead/vue` client import: `@unhead/vue/client`
- Queue driver: `database` (see `.env` QUEUE_CONNECTION). Always run `queue:work` in dev when testing calendar sync, backup, or document scanning features.
