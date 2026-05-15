# MEMORY.md — Decisiones Técnicas e Historial de Implementaciones

> Log de decisiones arquitectónicas y features completados.
> Máximo 15 entradas en "Implementaciones Recientes" — mover las más antiguas a MEMORY_ARCHIVE.md.
> Al completar un feature: agregar entrada al inicio de la sección correspondiente.

---

## Decisiones Arquitectónicas (estables — no eliminar sin consenso)

### D-01 — Multi-tenancy via Eloquent Global Scope (no RLS ni DBs separadas)
**Decisión:** `BelongsToTenant` trait + `TenantScope` global en cada modelo de negocio.
**Por qué:** MySQL no soporta RLS nativo. DBs separadas complican migraciones, backups y connection pooling.
**Consecuencia:** Todo modelo nuevo DEBE usar el trait + `tenant_id` FK — sin excepciones salvo D-07.

### D-02 — Auth Sanctum SPA (cookies HttpOnly, sin Bearer tokens)
**Decisión:** HttpOnly cookies + CSRF. Sin tokens en localStorage ni Pinia.
**Por qué:** Mitiga XSS (tokens inaccesibles desde JS). Patrón oficial Laravel para SPAs del mismo dominio.
**Consecuencia:** Interceptor 419 auto-refresh CSRF. Interceptor 401 limpia stores antes de redirect.

### D-03 — Permisos en migraciones, no solo en seeders
**Decisión:** `Permission::firstOrCreate(['name' => 'resource.action'])` en migración dedicada.
**Por qué:** Seeders no corren en `migrate:fresh` de producción. Los permisos son esquema, no datos de prueba.
**Consecuencia:** Nunca agregar permisos solo a un seeder.

### D-04 — ImmigrationCase mapea a tabla `cases`
**Decisión:** `protected $table = 'cases'` en el modelo `ImmigrationCase`.
**Por qué:** Nombre heredado del schema inicial. Cambiar requeriría migración en prod con datos vivos.
**Gotcha crítico:** Todo JOIN raw o `DB::table()` debe usar `'cases'`, no `'immigration_cases'`.

### D-05 — Storage: Strategy Pattern + Circuit Breaker
**Decisión:** `StorageProviderFactory` + `ResilientStorageProvider` (circuit breaker) como wrapper.
**Por qué:** 4 providers con APIs distintas (Local, OneDrive, Google Drive, SharePoint). Circuit breaker evita cascadas.
**Consecuencia:** Nunca llamar providers directamente. Siempre via `StorageService`.

### D-06 — Calendar sync anti-loop: flag estático (deuda técnica conocida)
**Decisión:** `CalendarSyncService::$isPulling = true` previene re-push durante pull.
**Por qué:** `PullCalendarEventsJob` es `ShouldBeUnique` — solo una instancia por proceso.
**Deuda:** Con multi-worker paralelo el flag estático no funciona entre procesos. Requeriría Redis cache key.

### D-07 — CaseImportantDate sin BelongsToTenant (excepción documentada)
**Decisión:** Aislamiento multi-tenant via JOIN explícito en `cases.tenant_id` en cada query.
**Por qué:** El tenant se deriva siempre del caso padre. `tenant_id` directo sería redundancia + riesgo de inconsistencia.
**Consecuencia:** NUNCA `CaseImportantDate::find($id)` directo en controller. Siempre via `ImportantDateAlertController` o scope de caso padre. `CaseImportantDatePolicy` enforces ownership.

---

## Implementaciones Recientes

### 2026-05-15 — Spec 59: Promoción Companion → Cliente Independiente
- `ClientPromotionService` (promote + checkEligibility), `ClientPromotionController` (store + eligibility)
- Excepciones tipadas: `CompanionNotEligibleForPromotionException` (`$errorCode`, no `$code` — colisión PHP), `ClientPromotionConflictException`
- `clients.promoted_from_companion_id` (FK nullable, UNIQUE) — trazabilidad + idempotencia de DB
- `Companion::promotedClient()` HasOne · `Client::originatedFromCompanion()` BelongsTo
- `CompanionResource` expone `already_promoted` + `promoted_to_client_id`; `CaseService::getCase()` eager-carga `companions.promotedClient`
- Frontend: `companionPromotionService.ts`, 3 estados de ícono en `cases/show.vue` (verde/gris/info), 27 i18n keys por idioma
- Descubrimiento clave: roles reales del tenant son `consultor`, `apoyo`, `contador`, `cliente`, `user` — NO `attorney`/`paralegal` como indicaba CLAUDE.md (ya corregido)
- Ruta Vue Router del detalle de cliente: `clients-show` (no `clients-detail`)
- Tests unitarios y feature tests pendientes (DT-05)

### 2026-05-14 — Spec 57: Radar Fechas Importantes (Módulo Alertas)
- `CaseImportantDate` model (sin BelongsToTenant — ver D-07)
- `ImportantDateAlertController`, `CaseImportantDatePolicy`
- `importantDateAlertService.ts`, `useImportantDatesStore`
- Filtros de 60 días, linkeo a eventos de calendario
- Problema encontrado: JOIN raw usaba `immigration_cases` → corregido a `cases` (D-04)

### 2026-05-14 — Spec 56: Directorios Dinámicos en Wizard (Step 5)
- Wizard ampliado de 5 a 7 steps. Step 5 nuevo: selección dinámica de carpetas
- `FolderService::createSelectedStructure()` + `FolderNameValidator`
- `caseFolderTemplateService.ts` con cache de sesión SPA
- `FolderService::createDefaultStructure()` alias backward-compatible para callers existentes
- `isLastStep === 7`, `canGoNext: step < 7`

### ~2026-04-28 — Specs 51-52: Timesheet + Gobernanza de Cronómetros
- `TimeLog` model + `TimeLogService` (startTimer, stopTimer, logManual)
- `useTimesheetStore` + `timesheetService.ts`
- `AutoStopExpiredTimersJob` (ShouldBeUnique, corre cada 1 min)
- `TimeLogObserver` actualiza cache `total_time_spent_seconds` en cases (delta UPDATE atómico)
- `BroadcastChannel('timesheet')` para sync multi-tab

### ~2026-04-28 — Specs 53-54: Sesión Única (Single Active Session)
- `sessions` tabla (SESSION_DRIVER=database) + `users.current_session_id`
- `EnsureSingleSession` middleware en todos los grupos autenticados
- `SessionRevoked` event → 3 listeners: `StopActiveTimerOnSessionRevoked` (sync), `LogSessionRevocation` (async queue:default), `DetectKickingAbuse` (async queue:high)
- `users.security_locked_until` — bloqueo por abuso (≥5 kicks en 10 min)
- `BroadcastChannel('session')` propagación multi-tab
- `session_revocations` tabla sin BelongsToTenant (igual que D-07)

### ~2026-04-20 — Specs 46-50: Motor de Workflows Dinámicos
- Tablas: `workflow_stages`, `workflow_tasks`, `case_stage_overrides`, `case_task_instances`
- Motor de etapas dinámicas por tipo de expediente (`CaseType`)
- Tablero interactivo de tareas en vista de expediente (Spec 47)
- Etapas personalizadas por expediente (Spec 48, Phase 9 deferida)
- Todos generados desde workflow (Spec 49), edición/clonación (Spec 50)
- Fases 5 y 9 de Spec 46 omitidas intencionalmente

---

## Deuda Técnica Activa

| ID | Descripción | Impacto | Spec |
|----|-------------|---------|------|
| DT-01 | Calendar sync flag estático no funciona en multi-worker | BAJO (ShouldBeUnique mitiga) | 45 |
| DT-02 | Wizard Fase 4 avatares pendiente | BAJO | 29 |
| DT-03 | Spec 48 Fase 9 (etapas personalizadas avanzadas) deferida | BAJO | 48 |
| DT-04 | Specs 49/50 smoke tests Phase 10 pendientes | BAJO | 49,50 |
| DT-05 | Spec 59 tests unitarios y feature tests pendientes | BAJO | 59 |
