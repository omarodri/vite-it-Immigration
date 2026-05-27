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

### 2026-05-27 — Spec 67: RBAC Granular Fase 2 — builder + labels i18n en panel de roles (COMPLETADO)

**4 archivos modificados, build ✓ (9.61s):**

- `admin/workflows/builder.vue` — botones del panel de task templates cambiados a `workflow_tasks.*`: "Add template" → `workflow_tasks.create`; drag handle → `v-can="'workflow_tasks.update'"`; edit → `workflow_tasks.update`; delete → `workflow_tasks.delete`. Los botones de etapas (Stage) conservan `workflows.*` (D-01 del spec).
- `admin/roles/show.vue` — `useI18n` importado; `groupedPermissions` usa `t('permissions.modules.{name}')` con fallback al capitalize raw; `formatPermissionName` usa `t('permissions.labels.{group}.{action}')` con fallback al capitalize del action.
- `admin/roles/edit.vue` — mismo patrón: `useI18n` + computed `translatedGroups` que envuelve `roleStore.permissionGroups` con display_name traducido; template usa `translatedGroups`; `formatPermissionName` con i18n lookup.
- `UpdateCaseCodePatternRequest.php` — comentario stale actualizado: `settings.case_code.manage` → `case_code.update` (Spec 66 ya migró la ruta).

**Patrón de fallback:** `t(key) === key` detecta clave ausente → muestra capitalize del raw. Grupos sin clave i18n nunca rompen la UI.

### 2026-05-27 — Spec 69: Companions Jerárquicos para Clientes Empresa (COMPLETADO)

**Arquitectura:** Self-reference `companions.parent_companion_id` (nullable FK, `ON DELETE CASCADE`, CHECK constraint MySQL 8 `chk_companions_family_not_beneficiary`). Jerarquía de profundidad máxima 1: `beneficiary` (empleado) → N familiares. No se creó tabla pivote — relación 1-a-N estricta.

**Backend:**
- Migraciones `2026_05_27_000001` (columna + índice + CHECK) y `2026_05_27_000002` (permiso `companions.manage_family` → super-admin, admin, consultor, apoyo)
- `Companion`: relaciones `parent()`/`familyMembers()`, scopes `employees`/`familyOf`/`roots`, helpers `canHaveFamily()`/`isFamilyMember()`
- `CompanionObserver`: cascade soft-delete/restore a `familyMembers` cuando `canHaveFamily()`
- `CompanionService`: `createFamilyMember()` (abort_if profundidad > 1), `listFamilyMembers()`, `listCompanions($params)` con `tier/parent_id/with_family/with_family_count`
- `StoreCompanionRequest`: regla `parent_companion_id` scoped a tenant + `withValidator()` anti-abuelo
- `UpdateCompanionRequest`: `passedValidation()` hace `parent_companion_id` inmutable
- `CompanionResource`: 5 campos nuevos; endpoint `GET /api/companions/{companion}/family`

**Frontend:**
- `types/companion.ts`: `parent_companion_id`, `is_employee`, `is_family_member`, `family_count`, `family_members`, `CompanionListParams`
- `companionService.ts`: `list(params)` + `getFamilyMembers(employeeId)`
- `CompanionFormModal`: prop `parentCompanionId` → incluido en payload POST
- `clients/show.vue`: accordion por empleado (empresa) / render plano preservado (persona); 2 instancias de `CompanionFormModal` (empleado + familiar)
- `StepCompanions.vue`: `displayableCompanions` computed + watcher limpia selección al cambiar empleado + empty state
- `cases/show.vue`: header empleado + familiares incluidos para company cases
- `cases/edit.vue`: `loadAddableCompanions()` filtrada por `getFamilyMembers(employeeId)`
- 18 claves i18n FLAT en es/en/fr — Build Vite ✓ 9.89s

**Pendiente residual (DT-15):** tests Feature PHP (`CompanionHierarchyTest`, cascade soft-delete) y smoke test E2E manual.

### 2026-05-26 — Spec 66: RBAC Ultra-Granular workflow_tasks + case_code (COMPLETADO)

**Permisos creados (migración `2026_05_27_000001`):**
- `workflow_tasks.{view, create, clone, update, delete}` — catálogo maestro de tareas
- `case_code.{view, update}` — fragmentación del legacy `settings.case_code.manage` (legacy mantenido 1 release; migración de transición copia a quienes ya lo tenían)
- Asignación: `super-admin/admin` → todos; `consultor` → 4 sin delete; `apoyo` → solo view

**Backend:**
- `TaskTemplatePolicy` — namespace refactorizado `workflows.*` → `workflow_tasks.*`; método `clone()` agregado con validación cross-tenant `$template->tenant_id === $user->tenant_id`
- `TaskTemplateService::clone()` — copia fillable + traducciones al stage destino; sort_order = MAX+1; DB::transaction
- `TaskTemplateController::clone()` — nuevo endpoint; `reorder()` corregido de `authorize('create')` → `authorize('update')`
- `CloneTaskTemplateRequest` — valida `target_stage_id` con `Rule::exists` scoped a `tenant_id` del tenant activo
- `routes/api.php` — middleware `case-code-pattern` cambiado a `permission:case_code.update`; ruta clone agregada al grupo admin/workflow
- `Gate::before` en `AuthServiceProvider` ya cubría super-admin Y admin — sin cambio necesario

**Frontend:**
- `auth.ts` — bug corregido: `hasPermission`, `hasAnyPermission`, `hasAllPermissions`, `isAdmin` ahora incluyen `super-admin` en bypass (alineados con backend `Gate::before`)
- `Sidebar.vue` — `canSeeWorkflowTasks` (OR sobre 5 permisos) y `canSeeCaseCode` (OR sobre 2) reemplazan los legacy `canViewWorkflows` + `canManageCaseCode`; patrón limpio sin mezcla role+permiso
- `router/index.ts` — `admin-workflows`, `admin-workflows-builder`, `admin-case-code-settings` usan `meta.permission: [array]`; OR lógico via `beforeEach` existente
- 15 keys i18n FLAT por idioma (es/en/fr): `permissions.modules.*`, `permissions.labels.*`, `errors.permission_denied.*`
- Build Vite: ✓ sin errores (9.58 s)

**Pendiente residual (Spec 67 + DT-12):** botones `v-can` en vistas admin workflows, `groupPermissions()` en panel de roles, tests Feature/Unit; deprecación `settings.case_code.manage` en Release N+1 (DT-10)

### 2026-05-26 — Bugs Spec 64: Relación "Empleado Beneficiario" en clientes empresa (2 fixes)

**Fix 1 — `formatRelationship` no mostraba "Empleado Beneficiario" en la pestaña de companions**
- `resources/js/src/views/clients/show.vue` — agregado `beneficiary: 'Empleado Beneficiario'` al mapa de labels en `formatRelationship()`. Antes caía al fallback mostrando el string crudo `"beneficiary"`.

**Fix 2 — Al editar un beneficiario, el campo relación era modificable (debía estar bloqueado)**
Tres archivos afectados:
- `resources/js/src/components/companions/CompanionFormModal.vue` — `lockedRelationship` computed: eliminado `!props.companion` del guard. Ahora bloquea el dropdown tanto en creación como en edición cuando `presetRelationship` está definido.
- `resources/js/src/views/clients/show.vue` · `openCompanionModal()` — extendida la condición del preset: antes solo se activaba al crear (`!companion && isCompany`), ahora también al editar un companion con `relationship === 'beneficiary'`.
- `resources/js/src/views/clients/show.vue` · `openCompanionModalFromView()` — función que bypaseaba `openCompanionModal` completamente. Agregada la misma lógica de preset para edición desde la vista de detalle del companion.
- Build Vite: ✓ sin errores (9.87 s)

**Patrón consolidado:** `presetRelationship` en `CompanionFormModal` bloquea el campo en create **y** edit cuando se pasa. El label bloqueado usa `t('companions.beneficiary')` → "Empleado Beneficiario" (clave ya existente en es/en/fr).

### 2026-05-25 — Spec 65: Panel Configuración Código de Expediente (COMPLETADO)

**BD:** `case_number_seq INT UNSIGNED` + UNIQUE `(tenant_id, seq)` en tabla `cases` (G11). Backfill con `ROW_NUMBER()` — 47 casos migrados, 0 nulls. Permiso `settings.case_code.manage` creado y asignado a super-admin/admin.

**Backend — Strategy Pattern:**
- 10 archivos nuevos en `app/Services/Case/`: `CaseCodeGeneratorService`, `CaseCodePatternResolver` (Cache 1h key `tenant:{id}:case_code_pattern`), `CaseCodePattern` (value object), `GenerationContext` (DTO readonly), 5 BlockResolvers (`YY`,`TT`,`AAAA`,`NNNN`,`NOMBRE`)
- `CaseService::generateCaseNumber()` eliminado — reemplazado por `$this->caseCodeGenerator->generate($ctx)`; `case_number_seq` persistido en `createCase()`
- `CaseRepository::getNextSequence()` usa `MAX(case_number_seq)` (no parsea string)
- Configuración guardada en `tenant.settings` JSON: `case_code_blocks[]`, `case_code_separator`, `case_code_include_name`
- `PUT /api/tenant/case-code-pattern` con `permission:settings.case_code.manage`; `TenantResource` extendido con bloque `case_code`

**Frontend:**
- `CaseCodeBuilder.vue` — DnD con `vue-draggable-plus`, selector separador, toggle NOMBRE, preview síncrono (`computed` puro, sin debounce), modal SweetAlert2 con preview en el body
- `CaseCodeSettings.vue` — vista con breadcrumb + spinner carga + panel
- Ruta `/admin/case-code-settings` + sidebar entry con `v-can="'settings.case_code.manage'"`
- Banner dismissable (sessionStorage) en `cases/list.vue` cuando `case_code_updated_at` existe
- 30 keys FLAT `settings.case_code.*` en es/en/fr

**Smoke test:** default `26-TO-RODR-0048`; custom `TO_26_RODR_0048_Omar-Andres-Rodriguez-Perez`. Build ✓ 9.18s.
**Gotcha añadida a CLAUDE.md:** G24 — `case_number_seq` es la fuente canónica del consecutivo, nunca parsear `case_number` string.
**Deuda técnica:** tests PHP (Unit + Feature) pendientes.

### 2026-05-25 — Spec 64: Bugs post-implementación (3 fixes)

**Fix 1 — `StoreCompanionRequest` / `UpdateCompanionRequest` rechazaban `relationship='beneficiary'` (422)**
- `app/Http/Requests/Companion/StoreCompanionRequest.php`: añadido `'beneficiary'` al `Rule::in([...])` del campo `relationship`
- `app/Http/Requests/Companion/UpdateCompanionRequest.php`: misma corrección
- Causa: el valor `'beneficiary'` fue agregado al modelo PHP y al tipo TS pero no al whitelist de validación HTTP

**Fix 2 — Botón "Promover a cliente" no aparecía para companions de 18+ años**
- `resources/js/src/views/clients/show.vue` · `computeLocalEligibility()` línea ~741: threshold cambiado de `< 22` a `< 18`
- Causa: typo simple; el backend siempre validó `< 18` correctamente

**Fix 3 — Wizard Step 3 (empresa): no había forma de seleccionar solicitante principal si el cliente ya tenía companions**
- `StepCompanions.vue` — el dropdown de solicitante principal estaba oculto para empresas (`v-if="!isCompanyCase"`); ahora se muestra para todos con lógica adaptada:
  - Para empresa: usa `primaryApplicantCompanions = companions.value` (todos, no solo seleccionados); sin opción "cliente"
  - Al seleccionar companion del dropdown en empresa → auto-agrega a `selectedCompanionIds` via `toggleCompanion()`
  - Sección "Beneficiario" cambia a borde neutral (antes rojo) cuando ya hay `primaryApplicantCompanionId` set; mensaje pasa de error a nota informativa
  - Computed `primaryApplicantCompanions` agregado al script
  - `primaryApplicantSelection` setter ampliado con auto-include para empresa
- 5 keys i18n nuevas en es/en/fr: `primary_applicant_company_title`, `primary_applicant_company_desc`, `beneficiary_optional_note`
- Build Vite: ✓ sin errores (9.31s)

### 2026-05-25 — Spec 64 DT-09: Wizard + Búsqueda Global para Empresas (COMPLETADO)
- `Companion::RELATIONSHIP_BENEFICIARY = 'beneficiary'` + `scopeBeneficiaries()` + entrada en `RELATIONSHIP_TYPES`
- `GlobalSearchService` — búsqueda de clientes por `display_name LIKE` + `tax_id LIKE` (reemplaza `first_name/last_name LIKE`); SELECT ampliado con `display_name`, `type`, `company_name`, `trade_name`; misma corrección en sección trash
- `FolderService` — sin cambios requeridos: rutas usan `case_number`, no nombre de cliente
- `StepCompanions.vue` — sección "Empleado Beneficiario" + `StepSummary.vue` jerarquía empresa/empleado/familia; `isCompanyCase` + `familyMembers` computeds agregados
- `useCaseWizard.ts` — validación Step 3 empresa: `primaryApplicantType='companion'` + `primaryApplicantCompanionId !== null`
- 11 keys i18n FLAT en es/en/fr: `wizard.step_companions.*` + `wizard.step_summary.*`
- Build Vite: ✓ sin errores (9.57s)
- **Pendiente residual (baja prioridad):** `StepClient.vue` nota informativa para empresa, `StoreCaseRequest` validación server-side que companion sea beneficiario, tests PHP

### 2026-05-24 — Spec 64: Clientes Empresa — Patrocinador + Beneficiario (PART)
- STI en tabla `clients`: columna `type ENUM('person','company') DEFAULT 'person'` + 7 campos empresa nullable (`company_name`, `trade_name`, `tax_id`, `industry`, `website`, `legal_rep_name`, `legal_rep_title`)
- `display_name` columna virtual GENERATED ALWAYS AS STORED + FULLTEXT index — unifica búsqueda persona/empresa sin lógica duplicada
- `Client::TYPE_PERSON/TYPE_COMPANY` constantes; scopes `ofType()`, `persons()`, `companies()`; accessors `full_name`/`sort_name`/`initials` null-safe para empresa
- 4 FormRequests nuevos + 2 dispatchers refactorizados; campos opuestos marcados `prohibited`
- `ClientService::createClient()` null-out campos contrarios; `updateClient()` bloquea cambio de `type`
- Frontend: `list.vue` (filtro segmentado), `create.vue` (selector tipo), `edit.vue` (bloqueo tipo), `show.vue` (secciones condicionales)
- Build Vite: ✓ sin errores; 3 migraciones aplicadas en producción

### 2026-05-15 — Spec 63: Widget "Próximos Hitos Legales" en Dashboard
- `UpcomingMilestonesService` (app/Services/Dashboard/) — `getUpcoming(User)` con ventana ±30 días, JOIN explícito `cases` (D-07), `whereNull('cases.deleted_at')` (soft-delete en JOINs crudos), `whereIn('cases.status', ['active','inactive'])`, LIMIT 10, orden `due_date/sort_order/id`
- Serialización inline: `days_diff` via `Carbon::diffInDays($date, false)` (negativo=pasado), `urgency_bucket` calculado en PHP — mismo patrón que Spec 57 (no hay utilidad frontend para esto)
- `DashboardController::index()` — nueva llamada gateada por `cases.view`, campo `upcoming_milestones` en response consolidado
- No se creó migración — índice `due_date` ya existía en migración original de `case_important_dates`
- Frontend: `DashboardMilestone` type + getter `upcomingMilestones` en `useDashboardStore`
- `UpcomingMilestonesWidget.vue` — reutiliza `UrgencyBadge.vue` existente; posición en sidebar derecho entre Upcoming Events y Expiring Documents
- 3 keys i18n FLAT en es/en/fr: `dashboard.upcoming_milestones`, `dashboard.no_upcoming_milestones`, `dashboard.view_all_milestones`
- Build Vite: ✓ sin errores (8.90s)

### 2026-05-15 — Spec 62: Sincronización Calendarios Externos — Completar Gaps
- `CalendarSyncService::getConnectedProviders()` (plural) — elimina supuesto "un proveedor por usuario"; `getConnectedProvider()` marcado `@deprecated`
- `pullEvents($user, $provider = null)` refactorizado: acepta proveedor explícito, ventana de backfill desde `tenant.settings.calendar_backfill_months` (default 6) + `calendar_lookahead_months` (default 12)
- `handlePullError()` privado en `CalendarSyncService` — circuit breaker: tras 5 errores `status='error'`
- `Tenant::calendarBackfillMonths()` + `calendarLookaheadMonths()` — accessors con clamp [1,24]
- `UpdateTenantSettingsRequest` + validación para los dos nuevos settings
- `EventSyncObserver::shouldSync()` — ahora bloquea re-push de eventos con `sync_source IN ['google','outlook']` (fix anti-loop multi-provider R7)
- `PullCalendarEventsJob::handle()` refactorizado: itera por `(user, provider)` explícito + filtro `last_pull_at < now()-15min` (previene doble-pull tras on-demand) + try/catch por fila
- `UserCalendarOAuthController::retry()` — reset de circuit breaker + pull sincrono; ruta `POST /api/calendar-oauth/{provider}/retry` con `throttle:5,1`
- `UserCalendarOAuthController::pull()` — amplía filtro a `['active', 'paused']`
- `UserCalendarOAuthController::callback()` — dispara backfill inicial no-bloqueante tras crear `CalendarSyncStatus`
- `CalendarProviderInterface` + `GoogleCalendarService` + `MicrosoftCalendarService` — firma `listEvents($token, $since, $until = null)` actualizada
- Frontend: `useCalendarSyncStore` (nuevo), `calendarSyncService.retry()` + `.pullOnDemand()`, botón Retry en `CalendarConnections.vue` (estado error), barra de estado en `apps/calendar.vue` (lastPullAt + Actualizar ahora)
- 18 keys i18n FLAT nuevas en es/en/fr (`calendar_sync.retry`, `calendar_sync.refresh_now`, `calendar_sync.status_error`, etc.)
- Build Vite: ✓ sin errores (9.15s)
- DT-08 agregado: `OAuthTokenService::refresh()` propagation + `activity_log` on circuit breaker open

### 2026-05-15 — Spec 61: Paginación y Ordenamiento Widget Tareas Dashboard
- `AssignedTasksService` (app/Services/Dashboard/) — `paginate(User, page, perPage)` con orden `due_date IS NULL ASC, due_date ASC, id ASC` (NULLs al final)
- Índice compuesto `idx_todos_dashboard` en `todos(tenant_id, assigned_to_id, status, due_date)` — migración `2026_05_15_000010`
- `DashboardController::assignedTasks()` — nuevo endpoint paginado `GET /api/dashboard/assigned-tasks?page=N&per_page=M` (max 50)
- `DashboardController::index()` — primera página embebida (10 items) + campo `assigned_tasks_meta` ({total, per_page, last_page})
- Frontend: `assignedTasks` pasó de getter (array) a sub-state en `useDashboardStore` ({items, page, perPage, total, lastPage, isLoading, error})
- Nuevas acciones: `_syncAssignedTasksFromDashboard()`, `fetchAssignedTasksPage(page)`, `invalidateAssignedTasks()`
- `index.vue`: skeleton loader, footer de paginación con Anterior/Siguiente, badge de total real (no conteo visible)
- `TaskRow.vue`: prop `urgencyBadge` — badge rojo (vencida) o naranja (próxima) adyacente a la fecha
- 6 keys i18n FLAT en es/en/fr: `common.prev`, `common.next`, `dashboard.pagination_summary`, `dashboard.tasks_load_error`, `dashboard.urgency_overdue`, `dashboard.urgency_due_soon`
- Gotcha G17 documentado en CLAUDE.md: widget usa `Todo`, no `CaseTask` ni `Task`
- Build Vite: ✓ sin errores (1226 módulos transformados)
- Cambio de ordenamiento: `FIELD(priority,...)` eliminado → orden cronológico por urgencia de fecha

### 2026-05-15 — Spec 60: Bóveda de Credenciales IRCC
- `CaseIrccCredential` model con `BelongsToTenant`, casts `encrypted` en todos los campos sensibles, `encrypted:array` en `security_questions` (5 preguntas fijas)
- `case_ircc_credentials` tabla separada 1:1 con `cases` (TEXT para campos encriptados, `key_version TINYINT DEFAULT 1` para rotación futura)
- Autorización via `Gate::define('ircc_credentials.view|update')` en `AuthServiceProvider` (no Policy mapeada a modelo — colisionaría con `CasePolicy`)
- `IrccCredentialService` (upsert + refresh) · `IrccAuditService` (logRead + logWrite — sin valores sensibles en propiedades del log)
- `IrccCredentialController` (availability + show + update) · `IrccCredentialResource` (nullable-safe, emptyQuestions() fallback)
- Permisos `ircc_credentials.view` + `ircc_credentials.edit` → `super-admin` y `consultor`
- Frontend: `useIrccStore` (clear() con sobrescritura `\0`), `useExtendedTab` composable (triple-click + check backend silencioso), `IrccCredentialsTab.vue` (3 secciones + tabla 5 filas + reveal por campo)
- `cases/show.vue`: `tabs` computed condicional, triple-click en `<h2>` del case_number, `onBeforeRouteLeave` limpia store
- **⚠️ Bloqueante de release:** Backup de `APP_KEY` + runbook de rotación OBLIGATORIO antes de producción (datos irrecuperables sin la key)
- Gotchas G13-G16 documentados en CLAUDE.md

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
| DT-06 | Spec 60 Fase 3 (runbook APP_KEY + backup + ircc:verify-integrity) y Fase 4 (tests) pendientes | **ALTO** (bloqueante para prod) | 60 |
| DT-07 | Spec 61 tests Feature/Unit pendientes; permiso `tasks.view` solo en seeder (no en migración — viola D-03) | BAJO | 61 |
| DT-08 | Spec 62: `OAuthTokenService::refresh()` propagation si refresh falla irrecuperablemente; `activity_log` entry cuando `CalendarSyncStatus→status='error'`; tests Feature/Unit de CalendarSync multi-provider; eliminar wrapper `getConnectedProvider()` tras confirmar 0 callers | BAJO | 62 |
| DT-09 | Spec 64 pendientes: (a) Wizard StepCompanions sección Beneficiario obligatoria para empresa + StepSummary jerarquía; (b) `Companion::RELATIONSHIP_BENEFICIARY` constante + scope; (c) `FolderService::buildCaseFolderName()` via `primaryApplicant()`; (d) `GlobalSearchService` busca por `display_name`; (e) tests PHP | MEDIO | 64 |
| DT-10 | Eliminar permiso legacy `settings.case_code.manage` en Release N+1 — verificar con `grep -r 'settings.case_code.manage' app/ resources/` → debe ser 0 | BAJA | 66 |
| DT-11 | Evaluar si `workflow_tasks.update_core` puede unificarse con `workflow_tasks.update` — requiere análisis de integración con Todos (G17) | BAJA | 66 |
| DT-12 | Spec 66 tests pendientes: `TaskTemplateAuthorizationTest`, `CaseCodePatternAuthorizationTest`, `TaskTemplatePolicyTest` (clone cross-tenant) | MEDIA | 66 |
| DT-13 | Spec 67 — botones `v-can` en vistas admin/workflows, `groupPermissions()` en panel de roles, deprecar `settings.case_code.manage` | ALTA | 67 |
| DT-14 | Spec 68 — aplicar patrón RBAC granular (sidebar OR lógico + router arrays) a módulos: cases, documents, calendar, clients, dashboard | MEDIA | 68 |
| DT-15 | Spec 69 — tests Feature PHP (`CompanionHierarchyTest`: jerarquía, profundidad, cross-tenant, inmutabilidad; cascade soft-delete/restore) y smoke test E2E manual | BAJA | 69 |
