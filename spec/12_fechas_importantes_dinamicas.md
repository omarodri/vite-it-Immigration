# Plan de Implementación: Fechas Importantes Dinámicas (`case_important_dates`)

## Metadata
- **Fecha:** 2026-03-13
- **Version:** 1.0
- **Arquitecto:** Claude (Architect Agent)
- **Epic:** 3.2 - Fechas Importantes Dinámicas
- **Alcance:** Transformar los 4 campos fijos de fecha en `cases` a una entidad relacionada dinámica `case_important_dates`, con CRUD en wizard, edición y consulta
- **Flujos cubiertos:** Wizard (StepDetails.vue) + Edición (edit.vue) + Vista detalle (show.vue) + Listado (list.vue)
- **Tiempo Total Estimado:** 11–17 horas
- **Estado:** ✅ COMPLETADO (2026-03-13)
- **Tests:** 62 tests, 272 assertions — todos pasan ✅

---

## Diagnóstico del Estado Actual

Los 4 campos de fecha (`hearing_date`, `fda_deadline`, `brown_sheet_date`, `evidence_deadline`) están embebidos como columnas `datetime` en la tabla `cases`. Se propagan a través de **21 archivos** del proyecto.

### Diagrama de Dependencia Actual

```
cases (table)
  hearing_date, fda_deadline, brown_sheet_date, evidence_deadline
    |
    +---> ImmigrationCase.php ($fillable, $casts, getDaysUntilHearingAttribute, scopeUpcoming, getActivitylogOptions)
    +---> CaseResource.php (formatea las 4 fechas + days_until_hearing)
    +---> CaseService.php (getUpcomingHearings)
    +---> CaseRepository.php (paginate: filtros hearing_from/hearing_to, getUpcomingHearings, allowedSortColumns)
    +---> StoreCaseRequest.php (validacion de hearing_date con after_or_equal:today)
    +---> UpdateCaseRequest.php (validacion de las 4 fechas)
    +---> ImmigrationCaseFactory.php (fake data para las 4 fechas)
    +---> CaseTest.php (factory usage)
    |
    +---> case.ts (ImmigrationCase, CreateCaseData, UpdateCaseData, CaseFilters)
    +---> wizard.ts (CaseDetailsForm, DEFAULT_CASE_DETAILS)
    +---> useCaseWizard.ts (createDefaultState, submit payload)
    +---> StepDetails.vue (4 flat-pickr inputs)
    +---> StepSummary.vue (muestra hearing_date en resumen)
    +---> edit.vue (4 flat-pickr inputs, form reactive)
    +---> show.vue (muestra las 4 fechas con semaforización para hearing_date)
    +---> list.vue (columna hearing_date en datatable + card mobile)
    +---> en.json, es.json (claves de traducción)
```

---

## Decisiones de Diseño

### D1: `tenant_id` NO en `case_important_dates`
El `tenant_id` se infiere desde `case_id → cases.tenant_id`. Agregarlo sería redundante (viola 3NF) y crearía riesgo de inconsistencia. El aislamiento de tenant lo garantiza el global scope `BelongsToTenant` en `ImmigrationCase`.

### D2: Estrategia de sincronización — Delete-and-Insert
El frontend envía el array completo en cada request. Delete-and-insert dentro de una transacción es atómico, sencillo y elimina orphan rows. Upsert descartado porque requiere que el frontend maneje IDs existentes vs nuevos simultáneamente.

### D3: Fechas predeterminadas en el Service Layer
La lógica de crear los 4 defaults vive en `CaseService::createCase()`. Si el payload incluye `important_dates`, se usan esas en lugar de los defaults. Lógica de negocio de aplicación — no va en el Model (crea acoplamiento oculto) ni en el Controller (rompe separación de responsabilidades).

### D4: Hard Delete para `case_important_dates`
Las fechas son datos auxiliares. Los cambios quedan en el activity log del caso padre. Soft-delete agregaría complejidad innecesaria.

### D5: Máximo 20 fechas por caso
Prevenir abuso sin ser restrictivo. Validado en FormRequest.

### D6: `due_date` como `DATE` (no `DATETIME`)
Elimina ambigüedad de timezone. Solo se necesita la parte de fecha. Si en el futuro se requiere hora, la migración es trivial.

---

## Esquema de Base de Datos

### Nueva tabla `case_important_dates`

```sql
CREATE TABLE case_important_dates (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id         BIGINT UNSIGNED NOT NULL,
    label           VARCHAR(100) NOT NULL,
    due_date        DATE NULL,
    sort_order      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    CONSTRAINT fk_cid_case_id FOREIGN KEY (case_id)
        REFERENCES cases(id) ON DELETE CASCADE,

    INDEX idx_cid_case_id (case_id),
    INDEX idx_cid_due_date (due_date)
);
```

### Columnas a eliminar de `cases`
- `hearing_date`
- `fda_deadline`
- `brown_sheet_date`
- `evidence_deadline`

**No hay migración de datos** (los valores existentes se descartan).

### Relación

```
+------------------+         +-------------------------+
| cases            |         | case_important_dates    |
|------------------|         |-------------------------|
| id (PK)          |<---+    | id (PK)                 |
| tenant_id (FK)   |    +----| case_id (FK → CASCADE)  |
| ...              |         | label VARCHAR(100)       |
+------------------+         | due_date DATE NULL       |
      1 : N (HasMany)        | sort_order TINYINT       |
                             | created_at               |
                             | updated_at               |
                             +-------------------------+
```

### Fechas predeterminadas al crear un caso

| sort_order | label               | due_date |
|------------|---------------------|----------|
| 0          | Fecha de inicio     | `today`  |
| 1          | Fecha límite legal  | `null`   |
| 2          | Fecha de envío IRCC | `null`   |
| 3          | Fecha de decisión   | `null`   |

---

## API Contract

### GET `/api/cases/{id}` — Response

```json
{
  "data": {
    "id": 1,
    "case_number": "2026-ASYLUM-00001",
    "status": "active",
    "priority": "high",
    "important_dates": [
      { "id": 1, "label": "Fecha de inicio",     "due_date": "2026-03-13", "sort_order": 0 },
      { "id": 2, "label": "Fecha límite legal",  "due_date": null,         "sort_order": 1 },
      { "id": 3, "label": "Fecha de envío IRCC", "due_date": "2026-04-15", "sort_order": 2 },
      { "id": 4, "label": "Fecha de decisión",   "due_date": null,         "sort_order": 3 }
    ]
  }
}
```

**Eliminados del nivel raíz:** `hearing_date`, `fda_deadline`, `brown_sheet_date`, `evidence_deadline`, `days_until_hearing`.

### POST `/api/cases` — Request

```json
{
  "client_id": 5,
  "case_type_id": 3,
  "priority": "high",
  "language": "es",
  "important_dates": [
    { "label": "Fecha de inicio",     "due_date": "2026-03-13", "sort_order": 0 },
    { "label": "Fecha límite legal",  "due_date": null,         "sort_order": 1 }
  ]
}
```

Si `important_dates` está ausente o vacío → el Service crea los 4 defaults.

### PUT `/api/cases/{id}` — Request

```json
{
  "priority": "urgent",
  "important_dates": [
    { "label": "Fecha de inicio",     "due_date": "2026-03-13", "sort_order": 0 },
    { "label": "Fecha límite legal",  "due_date": "2026-05-01", "sort_order": 1 },
    { "label": "Cita en embajada",    "due_date": "2026-07-20", "sort_order": 2 }
  ]
}
```

Si `important_dates` **NO** está en el payload → las fechas existentes no se tocan.
Si `important_dates` **SÍ** está → delete-and-insert completo.

### Reglas de Validación

```php
'important_dates'              => ['sometimes', 'array', 'max:20'],
'important_dates.*.label'      => ['required', 'string', 'max:100'],
'important_dates.*.due_date'   => ['nullable', 'date'],
'important_dates.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:255'],
```

---

## Componente `DateManager.vue`

### Props y Emits

```typescript
interface Props {
    modelValue: ImportantDate[];  // v-model binding
    readonly?: boolean;           // true en show.vue
    maxDates?: number;            // default 20
}
// Emits: 'update:modelValue'
```

### Lógica de Semaforización

```typescript
function getDateStatus(dueDate: string | null): 'overdue' | 'upcoming' | 'future' | 'none' {
    if (!dueDate) return 'none';
    const now = new Date();
    const date = new Date(dueDate);
    const diffDays = Math.floor((date.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
    if (diffDays < 0)  return 'overdue';   // Rojo
    if (diffDays <= 7) return 'upcoming';  // Amarillo
    return 'future';                        // Verde
}
```

| Estado   | Badge class             | Ring/Border class   |
|----------|-------------------------|---------------------|
| overdue  | `badge-outline-danger`  | `ring-danger`       |
| upcoming | `badge-outline-warning` | `ring-warning`      |
| future   | `badge-outline-success` | `ring-success`      |
| none     | `badge-outline-dark`    | (sin ring)          |

### Integración con Flatpickr
- Usar `:key="date.sort_order"` en el `v-for` — no usar el índice del array.
- Configuración `dateConfig` como `ref` compartido (no reactivo por fila).
- Al agregar nueva fila, push `{ label: '', due_date: null, sort_order: dates.length }`.
- Al eliminar, splice por índice y recalcular sort_order.

---

## Lista Completa de Archivos Impactados

### Backend — Archivos NUEVOS

| # | Archivo | Descripción |
|---|---------|-------------|
| 1 | `database/migrations/YYYY_create_case_important_dates_table.php` | Crea la tabla |
| 2 | `database/migrations/YYYY_drop_date_columns_from_cases_table.php` | Elimina las 4 columnas |
| 3 | `app/Models/CaseImportantDate.php` | Modelo Eloquent |
| 4 | `app/Http/Resources/CaseImportantDateResource.php` | API Resource |
| 5 | `database/factories/CaseImportantDateFactory.php` | Factory para tests |

### Backend — Archivos MODIFICADOS

| # | Archivo | Cambios |
|---|---------|---------|
| 6 | `app/Models/ImmigrationCase.php` | Eliminar fillable/casts/accessor de las 4 fechas; refactorizar `scopeUpcoming()`; agregar `importantDates(): HasMany`; actualizar `getActivitylogOptions()` |
| 7 | `app/Http/Resources/CaseResource.php` | Eliminar 4 fechas + `days_until_hearing`; agregar `important_dates` con `whenLoaded` |
| 8 | `app/Services/Case/CaseService.php` | `createCase()`: crear defaults si no hay `important_dates`; `updateCase()`: delete-and-insert si `important_dates` en payload; refactorizar `getUpcomingHearings()` → `getUpcomingDeadlines()` |
| 9 | `app/Repositories/Eloquent/CaseRepository.php` | Eager load `importantDates`; reemplazar filtros `hearing_from/hearing_to` con `whereHas`; actualizar estadísticas; remover `hearing_date` de `allowedSortColumns` |
| 10 | `app/Http/Requests/Case/StoreCaseRequest.php` | Eliminar reglas de las 4 fechas; agregar reglas `important_dates.*` |
| 11 | `app/Http/Requests/Case/UpdateCaseRequest.php` | Mismo cambio |
| 12 | `database/factories/ImmigrationCaseFactory.php` | Eliminar fake data de las 4 columnas; agregar `afterCreating` para `CaseImportantDate` |
| 13 | `database/seeders/DatabaseSeeder.php` | Actualizar si genera fechas fijas |

### Frontend — Archivos NUEVOS

| # | Archivo | Descripción |
|---|---------|-------------|
| 14 | `resources/js/src/components/DateManager.vue` | Componente CRUD reutilizable de fechas |

### Frontend — Archivos MODIFICADOS

| # | Archivo | Cambios |
|---|---------|---------|
| 15 | `resources/js/src/types/case.ts` | Agregar `ImportantDate` interface; eliminar las 4 props de fecha en `ImmigrationCase`, `CreateCaseData`, `UpdateCaseData`; agregar `important_dates?` |
| 16 | `resources/js/src/types/wizard.ts` | En `CaseDetailsForm`: eliminar 4 props, agregar `important_dates: ImportantDate[]`; actualizar `DEFAULT_CASE_DETAILS` |
| 17 | `resources/js/src/composables/useCaseWizard.ts` | `createDefaultState()`: incluir 4 fechas defaults; `submit()`: mapear `important_dates` al payload |
| 18 | `resources/js/src/views/cases/wizard/steps/StepDetails.vue` | Eliminar 4 flat-pickr individuales; agregar `<DateManager v-model="...important_dates" />` |
| 19 | `resources/js/src/views/cases/wizard/steps/StepSummary.vue` | Eliminar `hearing_date`; iterar sobre `important_dates` |
| 20 | `resources/js/src/views/cases/edit.vue` | Eliminar 4 flat-pickr; agregar `<DateManager v-model="form.important_dates" />`; popular desde `currentCase.important_dates` |
| 21 | `resources/js/src/views/cases/show.vue` | Eliminar bloque estático; iterar `important_dates` con semaforización |
| 22 | `resources/js/src/views/cases/list.vue` | Reemplazar columna `hearing_date` por `nearestDueDate` computed |
| 23 | `resources/js/src/locales/en.json` | Agregar claves `cases.add_date`, `cases.remove_date`, `cases.date_label`, `cases.no_dates` |
| 24 | `resources/js/src/locales/es.json` | Mismo |
| 25 | `resources/js/src/locales/fr.json` | Mismo |

### Tests

| # | Archivo | Cambios |
|---|---------|---------|
| 26 | `tests/Feature/CaseTest.php` | Actualizar factory usage; actualizar test de estadísticas (`upcoming_hearings` → `upcoming_deadlines`); agregar 8 nuevos tests |
| 27 | `tests/Feature/Api/UserStaffTest.php` | Sin cambios (no referencia fechas) |

---

## Impacto en Tests Existentes

### Tests que se ROMPERÁN

1. **`ImmigrationCaseFactory`** genera valores para las 4 columnas eliminadas → "Column not found". **Acción:** Actualizar la factory antes de correr las migraciones (Fase 1).
2. **`test_can_get_case_statistics`** verifica clave `upcoming_hearings`. Si se renombra → falla. **Acción:** Actualizar el test en Fase 3.

### Tests NUEVOS recomendados (Fase 3)

| Test |
|------|
| `test_creating_case_creates_default_important_dates` |
| `test_creating_case_with_custom_important_dates` |
| `test_updating_case_replaces_important_dates` |
| `test_updating_case_without_dates_preserves_existing` |
| `test_important_dates_max_limit` |
| `test_important_dates_validation` |
| `test_case_show_includes_important_dates` |
| `test_deleting_case_cascades_important_dates` |

---

## Riesgos y Mitigaciones

| # | Riesgo | Impacto | Mitigación |
|---|--------|---------|------------|
| 1 | Pérdida de datos existentes al eliminar columnas | Alto | Documentado: no hay migración de datos. En producción con datos reales, agregar migración de copia previa |
| 2 | Regresión en el listado — columna `hearing_date` deja de funcionar | Medio | Implementar `nearestDueDate` computed antes de desplegar |
| 3 | Inconsistencia de labels entre usuarios | Bajo | Aceptable en MVP. Futuro: catálogo de labels predefinidos |
| 4 | Performance en listados con eager loading | Bajo | 1 query extra para hasta ~300 filas. Negligible |
| 5 | Flatpickr memory leak al agregar/eliminar filas | Bajo | Usar `:key` basado en `sort_order` — vue-flatpickr-component maneja unmount |
| 6 | Race condition en delete-and-insert simultáneo | Bajo | Transacción DB garantiza atomicidad |

---

## Diagrama de Dependencias entre Fases

```
Fase 1: Backend DB + Modelo
    │
    └──► Fase 2: Backend Lógica + API
              │
              └──► Fase 3: Backend Tests
              │
              └──► Fase 4: Frontend Types
                        │
                        └──► Fase 5: DateManager.vue
                                  │
                                  └──► Fase 6: Integración en Vistas
                                            │
                                            └──► Fase 7: QA
```

Las Fases 3 y 4 pueden ejecutarse en paralelo.

---

## Orden de Implementación y Tiempos

| Fase | Descripción | Tiempo est. | Paralelo con | Estado |
|------|-------------|-------------|--------------|--------|
| 1 | Backend: DB + Modelo + Factories | 2–3 h | — | ✅ COMPLETADO |
| 2 | Backend: Service + Repository + Requests + Resources | 2–3 h | — | ✅ COMPLETADO |
| 3 | Backend: Tests | 1–2 h | Fase 4 | ✅ COMPLETADO |
| 4 | Frontend: Types (`case.ts`, `wizard.ts`) | 30 min | Fase 3 | ✅ COMPLETADO |
| 5 | Frontend: Componente `DateManager.vue` | 2–3 h | — | ✅ COMPLETADO |
| 6 | Frontend: Integración en Vistas (wizard, edit, show, list) | 2–3 h | — | ✅ COMPLETADO |
| 7 | QA: Tests manuales + build + phpunit | 1–2 h | — | ✅ COMPLETADO |
| **Total** | | **11–17 h** | | ✅ |

## Verificación Final Post-Implementación ✅ COMPLETADO

1. `php artisan migrate` ejecuta sin errores ✅
2. Tabla `case_important_dates` creada con FK cascade, índices en `case_id` y `due_date` ✅
3. Columnas `hearing_date`, `fda_deadline`, `brown_sheet_date`, `evidence_deadline` eliminadas de `cases` ✅
4. `POST /api/cases` crea caso con 4 fechas predeterminadas si no se envía `important_dates` ✅
5. `PUT /api/cases/{id}` con `important_dates` → delete-and-insert; sin el campo → fechas no tocadas ✅
6. `GET /api/cases/{id}` incluye `important_dates` en la respuesta ✅
7. Wizard: `DateManager.vue` con fechas predeterminadas cargadas ✅
8. Edición: `DateManager.vue` con datos del caso precargados ✅
9. Vista detalle: `DateManager` en modo readonly con semaforización ✅
10. Listado: columna muestra la fecha más próxima de `important_dates` ✅
11. `./vendor/bin/phpunit --filter="CaseTest|UserStaffTest"` → **62 tests, 272 assertions, 0 failures** ✅

## Archivos Creados/Modificados

### Backend
| Archivo | Tipo |
|---------|------|
| `database/migrations/2026_03_13_041259_create_case_important_dates_table.php` | Nuevo |
| `database/migrations/2026_03_13_041302_drop_date_columns_from_cases_table.php` | Nuevo |
| `app/Models/CaseImportantDate.php` | Nuevo |
| `app/Http/Resources/CaseImportantDateResource.php` | Nuevo |
| `database/factories/CaseImportantDateFactory.php` | Nuevo |
| `app/Models/ImmigrationCase.php` | Modificado |
| `app/Http/Resources/CaseResource.php` | Modificado |
| `app/Services/Case/CaseService.php` | Modificado |
| `app/Repositories/Eloquent/CaseRepository.php` | Modificado |
| `app/Repositories/Contracts/CaseRepositoryInterface.php` | Modificado |
| `app/Http/Requests/Case/StoreCaseRequest.php` | Modificado |
| `app/Http/Requests/Case/UpdateCaseRequest.php` | Modificado |
| `database/factories/ImmigrationCaseFactory.php` | Modificado |
| `tests/Feature/CaseTest.php` | Modificado |

### Frontend
| Archivo | Tipo |
|---------|------|
| `resources/js/src/components/DateManager.vue` | Nuevo |
| `resources/js/src/types/case.ts` | Modificado |
| `resources/js/src/types/wizard.ts` | Modificado |
| `resources/js/src/composables/useCaseWizard.ts` | Modificado |
| `resources/js/src/views/cases/wizard/steps/StepDetails.vue` | Modificado |
| `resources/js/src/views/cases/wizard/steps/StepSummary.vue` | Modificado |
| `resources/js/src/views/cases/edit.vue` | Modificado |
| `resources/js/src/views/cases/show.vue` | Modificado |
| `resources/js/src/views/cases/list.vue` | Modificado |
| `resources/js/src/views/cases/create.vue` | Modificado |
| `resources/js/src/services/caseService.ts` | Modificado |
| `resources/js/src/locales/en.json` | Modificado |
| `resources/js/src/locales/es.json` | Modificado |
| `resources/js/src/locales/fr.json` | Modificado |
