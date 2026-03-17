# Spec 26 — Squashing de Migraciones

**Fecha:** 2026-03-17
**Estado:** ✅ IMPLEMENTADO — 2026-03-17
**Rama sugerida:** `squashing` (ya existe)
**Riesgo global:** 🔴 ALTO (mitigable)

---

## Resumen Ejecutivo

Consolidar las **50 migraciones activas** del proyecto en un conjunto limpio de migraciones atómicas (una por tabla), eliminando el historial de `renameColumn`, `dropColumn` y `change()`, y resolviendo dos duplicaciones críticas de tablas antes de ejecutar el squash.

---

## Contexto

El proyecto acumula migraciones desde el scaffolding inicial de Laravel (2014) hasta las más recientes (2026-03-17). Varias tablas tienen 2-4 migraciones que las modifican. Dos tablas tienen migraciones de creación duplicadas que **bloquean** cualquier intento de `migrate:fresh`.

---

## 1. Inventario de Migraciones por Tabla

| Tabla | Nº migraciones | Cambios acumulados | Riesgo |
|-------|---------------|--------------------|--------|
| `tenants` | 2 | Duplicación de `Schema::create` | 🔴 CRÍTICO |
| `users` | 4 | Duplicación de `Schema::create` + tenant_id + is_active | 🔴 CRÍTICO |
| `companions` | 4 | ENUM → VARCHAR(50) en `relationship` | 🟠 ALTO |
| `cases` | 4 | DROP de 4 columnas de fecha; ADD 7 campos operativos | 🟠 ALTO |
| `clients` | 2 | ADD 'prospect' a ENUM via `DB::statement()` | 🟡 MEDIO |
| `case_important_dates` | 1 | Sin cambios | 🟢 BAJO |
| `case_tasks` | 1 | Sin cambios | 🟢 BAJO |
| `case_invoices` | 1 | Sin cambios | 🟢 BAJO |
| `case_types` | 1 | Sin cambios (incluye seeding) | 🟢 BAJO |
| `todos` | 1 | Sin cambios | 🟢 BAJO |
| `events` | 2 | ADD assigned_to + client snapshot | 🟢 BAJO |
| `user_case_history` | 1 | Reciente, sin cambios | 🟢 BAJO |
| `scrum_columns`, `scrum_tasks` | 1 c/u | Sin cambios | 🟢 BAJO |
| Tablas Laravel base | Múltiples | sessions, cache, jobs, etc. | 🟢 BAJO |

---

## 2. Riesgos Identificados

### 🔴 CRÍTICO #1 — Duplicación tabla `users`

**Archivos conflictivos:**
- `2014_10_12_000000_create_users_table.php` — base Laravel (sin tenant_id)
- `2026_01_01_000002_create_users_table.php` — versión del proyecto (con tenant_id, is_active, soft_deletes)

**Síntoma:** `migrate:fresh` fallará con "table already exists".

**Resolución:** Eliminar `2014_10_12_000000`. La versión 2026 es el estado final correcto.

**Esquema final de `users`:**
```
id, name, email (unique), email_verified_at, password,
tenant_id (FK→tenants, nullable), is_active (default true),
remember_token, timestamps, soft_deletes
```

---

### 🔴 CRÍTICO #2 — Duplicación tabla `tenants`

**Archivos conflictivos:**
- `2026_01_01_000001_create_tenants_table.php`
- `2026_02_08_221200_create_tenants_table.php`

**Resolución:** Consolidar en una sola. Mantener `2026_02_08` (más completa: incluye `ms_client_id`, `ms_client_secret`, `google_client_id`, `google_client_secret`).

**Esquema final de `tenants`:**
```
id, name, slug (unique), settings (json, nullable),
ms_client_id, ms_client_secret, google_client_id, google_client_secret,
is_active (default true), timestamps
```

---

### 🟠 ALTO #3 — Cambio de tipo en `companions.relationship`

**Migración problemática:** `2026_03_14_204906_change_relationship_column_to_string_in_companions_table.php`
Cambia la columna de `ENUM('spouse','child','parent','sibling','other')` a `VARCHAR(50)`.

**El squash debe crear la columna directamente como `string`** — nunca como ENUM.

**Discrepancia activa (preexistente al squash):**

| Capa | Tipos definidos |
|------|----------------|
| `app/Models/Companion.php::RELATIONSHIP_TYPES` | 5 tipos |
| `resources/js/src/types/companion.ts::RelationshipType` | 14 tipos |
| `database/factories/CompanionFactory.php` | 5 tipos |

La BD acepta cualquier string (VARCHAR), así que es **funcional pero inconsistente**.
Esta discrepancia debe resolverse independientemente del squash (ver Sección 6).

---

### 🟠 ALTO #4 — Columnas eliminadas de `cases`

**Migración:** `2026_03_13_041302_drop_date_columns_from_cases_table.php`
Elimina: `hearing_date`, `fda_deadline`, `brown_sheet_date`, `evidence_deadline`.
Fueron reemplazadas por la tabla `case_important_dates`.

**Validación de impacto:**

| Capa | Referencias a columnas eliminadas |
|------|----------------------------------|
| `ImmigrationCase` model | ✅ Ninguna |
| `CaseResource` | ✅ Ninguna |
| `types/case.ts` | ✅ Ninguna |
| Seeders / Factories | ✅ Ninguna |

**Impacto del squash:** Mínimo — el squash simplemente no incluye estas columnas desde el inicio.

---

### 🟡 MEDIO #5 — `clients.status` ENUM modificado con SQL raw

**Migración:** `2026_02_09_000001_add_prospect_status_and_unique_constraints_to_clients.php`
Usa `DB::statement("ALTER TABLE clients MODIFY COLUMN status ENUM(...)")` para agregar `'prospect'`.

**El squash debe crear el ENUM final directamente:**
```php
$table->enum('status', ['prospect', 'active', 'inactive', 'archived'])->default('prospect');
```

**Validación:** `types/client.ts` define `ClientStatus = 'prospect' | 'active' | 'inactive' | 'archived'` ✅

---

### 🟡 MEDIO #6 — Foreign Key implícita en `case_companions`

Tabla junction de la relación `BelongsToMany` entre `ImmigrationCase` y `Companion`.
La migración debe verificarse — confirmar que tiene `cascadeOnDelete` en ambas FKs.

---

## 3. Análisis de Impacto por Capa

### Backend — Modelos

| Modelo | Campos críticos | Estado post-squash |
|--------|----------------|-------------------|
| `ImmigrationCase` | `$table = 'cases'`; scopes `active()`, `byAssignee()` | ✅ Sin cambios |
| `Client` | `status` enum; `$casts` para fechas | ✅ Sin cambios |
| `Companion` | `relationship` como string; `relationship_other` | ✅ Sin cambios |
| `CaseType` | `category` enum con 4 valores | ✅ Sin cambios |
| `Event` | `getCategoryHex()`, `getCategoryColor()` | ✅ Sin cambios |

### Backend — API Resources

| Resource | Referencias a columnas | Estado |
|----------|----------------------|--------|
| `CaseResource` | `stage`, `ircc_status`, `final_result`, `ircc_code`, `contract_number`, `service_type`, `fees` | ✅ Todos en esquema final |
| `DashboardCaseResource` | `stage`, `priority_label` | ✅ Sin cambios |
| `CompanionResource` | `relationship_other`, `passport_expiry_date`, `passport_country` | ✅ Sin cambios |
| `EventResource` | `all_day`, `start_date`, `end_date` | ✅ Sin cambios |
| `DashboardEventResource` | `getCategoryHex()` | ✅ Sin cambios |

### Backend — Controllers

| Controller | Uso crítico | Estado |
|------------|------------|--------|
| `CaseController` | Filtros por `stage`, `ircc_status`; `DB::table('user_case_history')` upsert | ✅ Sin cambios |
| `ClientController` | Filtros por `status`, `canada_status` | ✅ Sin cambios |
| `DashboardController` | Queries a `events`, `todos`, `cases`, `user_case_history` | ✅ Sin cambios |

### Frontend — TypeScript Types

| Archivo | Campos de BD | Estado |
|---------|-------------|--------|
| `types/case.ts` | `stage`, `ircc_status`, `final_result`, `progress`, `fees` | ✅ Todos presentes en esquema final |
| `types/client.ts` | `status: 'prospect'\|'active'\|'inactive'\|'archived'` | ✅ Coincide con ENUM final |
| `types/companion.ts` | `RelationshipType` (14 valores) | ⚠️ Discrepancia preexistente con modelo PHP (5 valores) |
| `types/dashboard.ts` | Campos de múltiples tablas | ✅ Sin cambios |

### Frontend — Componentes Vue

**Ningún componente `.vue` quedará roto por el squash.** Los cambios de esquema históricos (columnas eliminadas, tipos cambiados) ya están absorbidos en el código actual.

---

## 4. Orden de Dependencias para Migraciones Squashed

El siguiente orden garantiza que las Foreign Keys se cumplan:

```
1.  tenants
2.  users                    (FK → tenants)
3.  password_reset_tokens
4.  personal_access_tokens   (FK → users)
5.  sessions
6.  cache / cache_locks
7.  jobs / job_batches / failed_jobs
8.  roles, permissions       (Spatie)
9.  model_has_roles, model_has_permissions, role_has_permissions
10. clients                  (FK → tenants, users)
11. case_types               (FK → tenants nullable) + seeding
12. cases                    (FK → tenants, clients, users, case_types)
13. case_important_dates     (FK → cases)
14. case_tasks               (FK → cases)
15. case_invoices            (FK → cases)
16. companions               (FK → tenants, clients)
17. case_companions          (FK → cases, companions)
18. todos                    (FK → tenants, users, cases)
19. events                   (FK → tenants, users, cases, clients)
20. event_participants       (FK → events, users)
21. scrum_columns            (FK → tenants)
22. scrum_tasks              (FK → scrum_columns, users, cases)
23. user_case_history        (FK → users, cases, tenants)
24. activity_log             (si existe)
```

---

## 5. Checklist de Implementación

### Pre-squash (Bloqueadores — ejecutar en orden)

- [x] **1.** Verificar migración `case_companions` — confirmar que existe y tiene `cascadeOnDelete`
- [x] **2.** Ejecutar `php artisan migrate:fresh --seed` en estado actual — debe pasar sin errores
- [x] **3.** Crear backup: `cp -r database/migrations /tmp/migrations_backup_$(date +%Y%m%d)`
- [x] **4.** Documentar schema actual: `php artisan migrate:status` → guardar output

### Squash

- [x] **5.** Crear directorio temporal con migraciones squashed (una por tabla, esquema final)
- [x] **6.** Eliminar `2014_10_12_000000_create_users_table.php` (reemplazada por 2026)
- [x] **7.** Consolidar duplicación de `tenants` en un único archivo
- [x] **8.** Nombrar archivos con timestamp `2026_01_01_000001_create_X_table.php` (orden numérico)
- [x] **9.** Usar `string` (VARCHAR) en `companions.relationship` — nunca ENUM
- [x] **10.** Usar ENUM final en `clients.status` (incluye 'prospect' desde el inicio)
- [x] **11.** Incluir `DB::insert()` del seeding de `case_types` en su migración
- [x] **12.** Eliminar todas las migraciones antiguas del directorio

### Validación

- [x] **13.** Ejecutar `php artisan migrate:fresh --seed` con nuevas migraciones — 0 errores
- [x] **14.** Verificar que los 21 `case_types` globales existen en BD (21 registros confirmados)
- [x] **15.** Verificar que `php artisan migrate:status` muestra todas las tablas como "Ran"
- [x] **16.** Ejecutar `npm run build` — 0 errores TypeScript
- [ ] **17.** Probar login, CRUD de expedientes, clientes y tareas manualmente

### Post-squash

- [x] **18.** Resolver discrepancia `Companion::RELATIONSHIP_TYPES` (expandido a 15 tipos con frontend)
- [ ] **19.** Commit en rama `squashing` → PR a `main`

---

## 6. Tarea Independiente: Alinear `companion.relationship` types

Esta discrepancia existe **antes y después** del squash, pero el squash es buen momento para resolverla.

**Opción A — Expandir modelo PHP (recomendada):**
Actualizar `Companion::RELATIONSHIP_TYPES` con los 14 tipos del frontend:
```php
public const RELATIONSHIP_TYPES = [
    'spouse'          => 'Cónyuge',
    'common-law partner' => 'Pareja de hecho',
    'dependent child' => 'Hijo/a dependiente',
    'grandchild'      => 'Nieto/a',
    'parent'          => 'Padre/Madre',
    'grandparent'     => 'Abuelo/a',
    'sibling'         => 'Hermano/a',
    'half-sibling'    => 'Medio hermano/a',
    'step-sibling'    => 'Hermanastro/a',
    'aunt / uncle'    => 'Tío/a',
    'niece / nephew'  => 'Sobrino/a',
    'cousin'          => 'Primo/a',
    'child-in-law'    => 'Yerno/Nuera',
    'parent-in-law'   => 'Suegro/a',
    'other'           => 'Otro',
];
```

**Opción B — Reducir frontend a 5 tipos:** Menos recomendable — pierde granularidad de datos.

---

## 7. Archivos Clave de Referencia

### Migraciones críticas a leer antes de squashear
```
database/migrations/2026_01_01_000001_create_tenants_table.php
database/migrations/2026_02_08_221200_create_tenants_table.php         ← duplicado
database/migrations/2014_10_12_000000_create_users_table.php
database/migrations/2026_01_01_000002_create_users_table.php           ← duplicado
database/migrations/2026_03_14_204906_change_relationship_column_*.php ← tipo cambiado
database/migrations/2026_03_13_041302_drop_date_columns_from_cases_*.php ← columnas eliminadas
database/migrations/2026_02_09_000001_add_prospect_status_*.php        ← enum raw SQL
```

### Modelos a verificar post-squash
```
app/Models/ImmigrationCase.php   → $table = 'cases', $fillable, $casts
app/Models/Companion.php         → RELATIONSHIP_TYPES, $casts
app/Models/Client.php            → status enum, $casts fechas
app/Models/CaseType.php          → CATEGORY_REFUGEE (actualizado en spec anterior)
```

### Seeders a validar
```
database/seeders/DatabaseSeeder.php
database/seeders/CaseTypeSeeder.php   → 21 tipos, usa updateOrInsert por 'code'
database/seeders/RolePermissionSeeder.php
database/seeders/TenantSeeder.php
```

---

## 8. Decisiones de Diseño del Squash

| Decisión | Opción elegida | Justificación |
|----------|---------------|---------------|
| Método | Reescritura manual | `schema:dump` genera SQL que mezcla migraciones PHP y SQL, ensucio el repositorio |
| Columnas eliminadas | NO incluir | Estado final de `cases` no las tiene; `case_important_dates` las reemplaza |
| `relationship` tipo | `string` (VARCHAR) | Estado final es VARCHAR, no ENUM |
| Seeding de `case_types` | Dentro de la migración | Es parte del estado base del sistema, no datos de usuario |
| Timestamp de archivos | `2026_01_01_000XXX` | Orden claro, todos antes de cualquier dato de negocio |
| Migraciones Spatie | Mantener originales | Son generadas por el package, no deben modificarse |
| Migraciones Laravel base | Mantener originales (sessions, jobs, cache) | Son estándar de framework |
