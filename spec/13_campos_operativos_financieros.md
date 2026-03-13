# Plan de Implementación: Campos Operativos y Financieros en Casos

## Metadata
- **Fecha:** 2026-03-13
- **Version:** 1.0
- **Arquitecto:** Claude (Architect Agent)
- **Epic:** 3.3 - Expansión de Campos Operativos y Financieros
- **Alcance:** Incorporar seguimiento de etapa, estado IRCC, resultado final, código IRCC, contrato, tipo de servicio y honorarios. Agregar progress bar, column chooser y filtro por etapa en el datatable.
- **Flujos cubiertos:** Wizard + Edit + Show + List (datatable)
- **Tiempo Total Estimado:** ~6.5 días
- **Estado:** ✅ COMPLETADO (2026-03-13)
- **Tests:** 62 tests, 272 assertions — todos pasan ✅

---

## Decisiones de Diseño

### D1: `stage` e `ircc_status` — Constantes en el Modelo (no tablas maestras)

Siguen el patrón existente de `STATUS_*` y `PRIORITY_*`. Las tablas maestras se descartan porque:
- Los valores están dictados por el proceso IRCC institucional (baja frecuencia de cambio)
- El patrón de constantes es consistente con todo el proyecto
- Evita JOINs adicionales y complejidad de nuevas tablas/endpoints
- Si en el futuro un admin necesita gestionar valores, la migración es no-breaking (el campo es `string`)

### D2: `fees` — Permiso Spatie `cases.view-fees`

Más granular que filtrar por rol directamente. Permite asignar el permiso a roles específicos sin cambiar código. Se expone con `$this->when($request->user()?->can('cases.view-fees'), $this->fees)` en el Resource.

Si un usuario sin permiso envía `fees` en el request → se **ignora silenciosamente** (se elimina del input antes de llegar al Service). No 403, ya que la validación filtra inputs, no autoriza acciones.

### D3: Column Chooser — localStorage

**Key:** `vite-it:cases:columns`

Justificación vs base de datos:
- Latencia instantánea (sin HTTP)
- El wizard ya usa sessionStorage como precedente
- La sincronización multi-dispositivo no es un requerimiento explícito
- Cero complejidad backend

**Merge strategy al cargar:** Si el localStorage tiene columnas guardadas, se hace merge con los defaults del código (para manejar columnas nuevas en futuras versiones).

### D4: `progress` — Manual, no calculado por `stage`

`stage` refleja el proceso IRCC; `progress` refleja el avance interno del equipo. No mapean 1:1. Se mantiene el input `range` editable. Futuro: botón "Auto-calcular basado en stage".

### D5: `final_result` — Validación blanda frontend, sin validación cruzada backend

El campo solo se muestra en el frontend cuando `ircc_status` es `approved` o `refused`. Backend acepta cualquier combinación para evitar fricción operativa.

---

## Nuevos Campos

### Seguimiento Operativo
| Campo | Tipo DB | Default | Descripción |
|-------|---------|---------|-------------|
| `stage` | string(50) nullable | null | Etapa del expediente |
| `ircc_status` | string(50) nullable | null | Estado IRCC |
| `final_result` | string(20) nullable | null | `approved` / `denied` |
| `ircc_code` | string(50) nullable | null | Código de solicitud IRCC |

### Financiero/Admin
| Campo | Tipo DB | Default | Descripción |
|-------|---------|---------|-------------|
| `contract_number` | string(50) nullable | null | Número de contrato |
| `service_type` | string(20) | `fee_based` | `pro_bono` / `fee_based` |
| `fees` | decimal(10,2) nullable | null | Honorarios (permiso `cases.view-fees`) |

---

## Constantes del Modelo `ImmigrationCase`

### Stage

| Constante | Valor | Label ES |
|-----------|-------|----------|
| `STAGE_INITIAL_CONSULTATION` | `initial_consultation` | Consulta Inicial |
| `STAGE_DOCUMENT_COLLECTION` | `document_collection` | Recolección de Documentos |
| `STAGE_APPLICATION_PREPARATION` | `application_preparation` | Preparación de Solicitud |
| `STAGE_SUBMITTED` | `submitted` | Enviada |
| `STAGE_UNDER_REVIEW` | `under_review` | En Revisión IRCC |
| `STAGE_ADDITIONAL_INFO_REQUESTED` | `additional_info_requested` | Información Adicional Solicitada |
| `STAGE_DECISION_PENDING` | `decision_pending` | Decisión Pendiente |
| `STAGE_CLOSED` | `closed` | Cerrada |

### IRCC Status

| Constante | Valor | Label ES |
|-----------|-------|----------|
| `IRCC_NOT_SUBMITTED` | `not_submitted` | No Enviada |
| `IRCC_RECEIVED` | `received` | Recibida |
| `IRCC_IN_PROCESS` | `in_process` | En Proceso |
| `IRCC_APPROVED` | `approved` | Aprobada |
| `IRCC_REFUSED` | `refused` | Rechazada |
| `IRCC_WITHDRAWN` | `withdrawn` | Retirada |
| `IRCC_CANCELLED` | `cancelled` | Cancelada |

### Final Result / Service Type

| Constante | Valor |
|-----------|-------|
| `FINAL_RESULT_APPROVED` | `approved` |
| `FINAL_RESULT_DENIED` | `denied` |
| `SERVICE_TYPE_PRO_BONO` | `pro_bono` |
| `SERVICE_TYPE_FEE_BASED` | `fee_based` |

---

## Esquema de Migración

```sql
ALTER TABLE cases ADD COLUMN stage VARCHAR(50) NULL DEFAULT NULL AFTER language;
ALTER TABLE cases ADD COLUMN ircc_status VARCHAR(50) NULL DEFAULT NULL AFTER stage;
ALTER TABLE cases ADD COLUMN final_result VARCHAR(20) NULL DEFAULT NULL AFTER ircc_status;
ALTER TABLE cases ADD COLUMN ircc_code VARCHAR(50) NULL DEFAULT NULL AFTER final_result;
ALTER TABLE cases ADD COLUMN contract_number VARCHAR(50) NULL DEFAULT NULL AFTER archive_box_number;
ALTER TABLE cases ADD COLUMN service_type VARCHAR(20) NOT NULL DEFAULT 'fee_based' AFTER contract_number;
ALTER TABLE cases ADD COLUMN fees DECIMAL(10,2) NULL DEFAULT NULL AFTER service_type;

CREATE INDEX idx_cases_tenant_stage ON cases (tenant_id, stage);
CREATE INDEX idx_cases_tenant_ircc_status ON cases (tenant_id, ircc_status);
CREATE INDEX idx_cases_tenant_service_type ON cases (tenant_id, service_type);
```

Todos los campos son `nullable` o tienen defaults seguros → los casos existentes no necesitan migración de datos.

---

## API Contract

### GET `/api/cases/{id}` — Response (nuevos campos)

```json
{
  "data": {
    "id": 1,
    "case_number": "2026-ASYLUM-00001",
    "status": "active",
    "status_label": "Activo",
    "priority": "high",
    "priority_label": "Alta",
    "progress": 45,
    "stage": "document_collection",
    "stage_label": "Recolección de Documentos",
    "ircc_status": "not_submitted",
    "ircc_status_label": "No Enviada",
    "final_result": null,
    "final_result_label": null,
    "ircc_code": null,
    "contract_number": "CTR-2026-001",
    "service_type": "fee_based",
    "service_type_label": "Con Honorarios",
    "fees": 2500.00
  }
}
```

`fees` **solo aparece** si `auth()->user()->can('cases.view-fees')`. Si el usuario no tiene permiso, la key queda completamente omitida del JSON.

### POST `/api/cases` — Nuevos campos aceptados

```json
{
  "service_type": "fee_based",
  "contract_number": "CTR-2026-001",
  "fees": 2500.00
}
```

`stage` e `ircc_status` NO se envían en creación — el Service los setea como defaults `null` (o `initial_consultation` / `not_submitted` si se decide pre-poblar).

### PUT `/api/cases/{id}` — Nuevos campos aceptados

```json
{
  "stage": "document_collection",
  "ircc_status": "received",
  "final_result": "approved",
  "ircc_code": "B000123456",
  "contract_number": "CTR-2026-001",
  "service_type": "fee_based",
  "fees": 3000.00
}
```

---

## Reglas de Validación

### StoreCaseRequest (nuevas reglas)

```php
'service_type'    => ['sometimes', Rule::in([...SERVICE_TYPE constants...])],
'contract_number' => ['nullable', 'string', 'max:50'],
'fees'            => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
```

`stage`, `ircc_status`, `final_result`, `ircc_code` NO se validan en Store.

### UpdateCaseRequest (nuevas reglas)

```php
'stage'           => ['sometimes', 'nullable', Rule::in([...STAGE constants...])],
'ircc_status'     => ['sometimes', 'nullable', Rule::in([...IRCC constants...])],
'final_result'    => ['nullable', Rule::in([FINAL_RESULT_APPROVED, FINAL_RESULT_DENIED])],
'ircc_code'       => ['nullable', 'string', 'max:50'],
'contract_number' => ['nullable', 'string', 'max:50'],
'service_type'    => ['sometimes', Rule::in([...SERVICE_TYPE constants...])],
'fees'            => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
```

**Filtrado de `fees` sin permiso** (en ambos requests, dentro de `withValidator()`):

```php
if ($this->has('fees') && !$this->user()->can('cases.view-fees')) {
    $this->request->remove('fees');
}
```

---

## Mapa de Campos por Vista

### Wizard de Creación (`StepDetails.vue`)

| Campo | Incluir | Justificación |
|-------|---------|---------------|
| `service_type` | ✅ SÍ | Dato de configuración inicial |
| `contract_number` | ✅ SÍ, opcional | Se puede conocer al crear |
| `fees` | ✅ SÍ, `v-if="canViewFees"` | Se puede establecer al crear |
| `stage` | ❌ NO | El caso siempre inicia en `initial_consultation` |
| `ircc_status` | ❌ NO | Siempre inicia en `not_submitted` |
| `final_result` | ❌ NO | Solo tiene sentido en casos avanzados |
| `ircc_code` | ❌ NO | Se asigna cuando IRCC responde |

Ubicación: Nueva sección "Financiero / Administrativo" debajo de los campos actuales.

### Formulario de Edición (`edit.vue`)

**Sección "Seguimiento Operativo"** (después de `status`):
- `stage` (dropdown)
- `ircc_status` (dropdown)
- `final_result` (dropdown, solo visible si `ircc_status` es `approved` o `refused`)
- `ircc_code` (text input)

**Nueva sección "Información Financiera":**
- `service_type` (dropdown)
- `contract_number` (text input)
- `fees` (number input, `v-if="canViewFees"`)

### Vista de Detalle (`show.vue`)

- `stage` + badge coloreado → sección "General Information"
- `ircc_status` + badge coloreado → sección "General Information"
- `final_result` → solo si no es null
- `ircc_code` → solo si no es null
- `contract_number`, `service_type`, `fees` → nueva subsección "Financiero"

### Datatable (`list.vue`)

| Columna | Visible por default | Oculable | Permiso requerido |
|---------|---------------------|----------|-------------------|
| `case_number` | ✅ | ❌ (fija) | — |
| `client` | ✅ | ❌ (fija) | — |
| `case_type` | ✅ | ✅ | — |
| `status` | ✅ | ❌ (fija) | — |
| `priority` | ✅ | ✅ | — |
| `stage` | ✅ NUEVO | ✅ | — |
| `progress` | ❌ oculta | ✅ | — |
| `ircc_status` | ❌ oculta | ✅ | — |
| `service_type` | ❌ oculta | ✅ | — |
| `fees` | ❌ oculta | ✅ | `cases.view-fees` |
| `nearest_date` | ✅ | ✅ | — |
| `assigned_to` | ✅ | ✅ | — |
| `actions` | ✅ | ❌ (fija) | — |

---

## Diseño del Column Chooser

### Estructura de datos (`useCaseColumnChooser.ts`)

```typescript
interface ColumnConfig {
    field: string;
    title: string;           // i18n key
    visible: boolean;
    locked: boolean;         // true = no se puede ocultar
    width?: string;
    requiresPermission?: string;
}

const STORAGE_KEY = 'vite-it:cases:columns';
```

### Lógica de persistencia

1. Al montar `list.vue`: leer `localStorage.getItem(STORAGE_KEY)`
2. Si existe: parsear y hacer **merge** con defaults (para columnas nuevas en futuras versiones)
3. Si no existe: usar defaults
4. Al cambiar visibilidad: guardar `[{ field, visible }]` en localStorage
5. Botón "Restablecer": limpiar localStorage + restaurar defaults
6. Columnas con `requiresPermission` se filtran si el usuario no tiene el permiso (no aparecen en el dropdown)

---

## Diseño de la Progress Bar (Datatable)

```html
<!-- Slot de columna progress en vue3-datatable -->
<template #progress="data">
  <div class="flex items-center gap-2">
    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 min-w-[60px]">
      <div
        class="h-2 rounded-full transition-all"
        :class="getProgressBarClass(data.value.progress)"
        :style="{ width: `${data.value.progress}%` }"
      ></div>
    </div>
    <span class="text-xs text-gray-500 w-8 text-right">{{ data.value.progress }}%</span>
  </div>
</template>
```

**Colores** (función `getProgressBarClass` ya existe en `list.vue` y `show.vue`):

| Rango | Clase | Color |
|-------|-------|-------|
| ≥ 75% | `bg-success` | Verde |
| ≥ 50% | `bg-info` | Azul |
| ≥ 25% | `bg-warning` | Naranja |
| < 25% | `bg-danger` | Rojo |

---

## Permiso Spatie — Cambios en el Seeder

Agregar permiso: `cases.view-fees`

| Rol | Recibe `cases.view-fees` |
|-----|--------------------------|
| `admin` | ✅ |
| `contador` | ✅ |
| `consultor` | ❌ |
| `apoyo` | ❌ |

---

## Casos de Borde

| Caso | Comportamiento |
|------|----------------|
| `final_result` con `ircc_status` no terminal | Frontend oculta el campo; backend acepta cualquier combinación (no validación cruzada) |
| `fees = 0` con `service_type = pro_bono` | Permitido — no hay validación cruzada entre estos dos campos |
| Usuario sin permiso envía `fees` | El campo se elimina silenciosamente del input en el FormRequest (no 403) |
| Casos existentes después de migración | `stage = null`, `ircc_status = null`, `service_type = 'fee_based'` (default), `fees = null` |
| Column chooser con columna nueva en código | Merge strategy: las nuevas columnas toman su `visible` default, las guardadas mantienen su valor |

---

## Lista Completa de Archivos Impactados

### Backend — Nuevos

| Archivo | Descripción |
|---------|-------------|
| `database/migrations/YYYY_add_operational_and_financial_fields_to_cases_table.php` | 7 nuevos campos + índices |

### Backend — Modificados

| Archivo | Cambios |
|---------|---------|
| `app/Models/ImmigrationCase.php` | Constantes, `$fillable`, `$casts`, accessors de label, 3 nuevos scopes, activity log |
| `app/Http/Resources/CaseResource.php` | 8 nuevos campos + `fees` condicional con `$this->when()` |
| `app/Http/Requests/Case/StoreCaseRequest.php` | Nuevas reglas + filtrado `fees` sin permiso |
| `app/Http/Requests/Case/UpdateCaseRequest.php` | Nuevas reglas + filtrado `fees` sin permiso |
| `app/Repositories/Eloquent/CaseRepository.php` | 3 nuevos filtros, `allowedSortColumns`, estadísticas por stage |
| `app/Services/Case/CaseService.php` | Defaults en `createCase()` para `stage`/`ircc_status` si se decide pre-poblar |
| `app/Http/Controllers/Api/CaseController.php` | Nuevos filtros en `$request->only()` |
| `database/factories/ImmigrationCaseFactory.php` | Nuevos campos con valores fake |
| `database/seeders/RolePermissionSeeder.php` | Agregar `cases.view-fees` + asignación a roles |
| `tests/Feature/CaseTest.php` | Tests para nuevos campos, validaciones, permiso `fees` |

### Frontend — Nuevos

| Archivo | Descripción |
|---------|-------------|
| `resources/js/src/composables/useCaseColumnChooser.ts` | Lógica de column chooser con localStorage |

### Frontend — Modificados

| Archivo | Cambios |
|---------|---------|
| `resources/js/src/types/case.ts` | Tipos `CaseStage`, `IrccStatus`, `FinalResult`, `ServiceType`; constantes de opciones; nuevas props en interfaces |
| `resources/js/src/types/wizard.ts` | `CaseDetailsForm` + `DEFAULT_CASE_DETAILS` con campos financieros |
| `resources/js/src/composables/useCaseWizard.ts` | Payload con campos financieros |
| `resources/js/src/views/cases/wizard/steps/StepDetails.vue` | Sección "Financiero" con `service_type`, `contract_number`, `fees` |
| `resources/js/src/views/cases/edit.vue` | Sección operativa + sección financiera |
| `resources/js/src/views/cases/show.vue` | Nuevos campos con badges coloreados + subsección financiera |
| `resources/js/src/views/cases/list.vue` | Column chooser + nuevas columnas + progress bar + filtro stage |
| `resources/js/src/locales/en.json` | ~30 nuevas keys `cases.*` |
| `resources/js/src/locales/es.json` | Mismo |
| `resources/js/src/locales/fr.json` | Mismo |

---

## Diagrama de Dependencias entre Fases

```
Fase 1: Backend Foundation (migración + modelo + permisos)
    │
    └──► Fase 2: Backend API (requests + resource + repository + service + controller)
              │
              └──► Fase 3: Backend Tests
              │
              └──► Fase 4: Frontend Types & State (types + wizard + composable columnChooser)
                        │
                        └──► Fase 5: Frontend Formularios (StepDetails + edit + locales)
                        │
                        └──► Fase 6: Frontend Datatable & Show (list + show)
                                  │
                                  └──► Fase 7: QA
```

Las Fases 3 y 4 pueden ejecutarse en paralelo.

---

## Checklist de Implementación

### Fase 1: Backend Foundation ✅
- [x] Crear migración con 7 campos e índices
- [x] Actualizar `ImmigrationCase.php`: constantes, fillable, casts, accessors, scopes, activity log
- [x] Actualizar `RolePermissionSeeder.php`: agregar `cases.view-fees`
- [x] Actualizar `ImmigrationCaseFactory.php`: nuevos campos
- [x] Ejecutar migración + re-seed de permisos

### Fase 2: Backend API ✅
- [x] Actualizar `StoreCaseRequest.php`: nuevas reglas + filtrado `fees`
- [x] Actualizar `UpdateCaseRequest.php`: nuevas reglas + filtrado `fees`
- [x] Actualizar `CaseResource.php`: 8 nuevos campos + `fees` condicional
- [x] Actualizar `CaseRepository.php`: filtros, sort columns, estadísticas
- [x] Actualizar `CaseController.php`: nuevos filtros

### Fase 3: Backend Tests ✅
- [x] 62 tests, 272 assertions — 0 failures

### Fase 4: Frontend Types & State ✅
- [x] Actualizar `types/case.ts`: tipos, interfaces, constantes de opciones
- [x] Actualizar `types/wizard.ts`: `CaseDetailsForm` y defaults
- [x] Actualizar `composables/useCaseWizard.ts`: payload
- [x] Crear `composables/useCaseColumnChooser.ts`

### Fase 5: Frontend Formularios ✅
- [x] Actualizar `StepDetails.vue`: sección financiera
- [x] Actualizar `StepSummary.vue`: mostrar campos financieros en resumen
- [x] Actualizar `edit.vue`: sección operativa + sección financiera + lógica condicional `final_result`
- [x] Actualizar locales `en.json`, `es.json`, `fr.json` (30 keys cada uno)

### Fase 6: Frontend Datatable & Show ✅
- [x] Actualizar `list.vue`: column chooser + columna `stage` + progress bar + filtro stage
- [x] Actualizar `show.vue`: nuevos campos con badges + subsección financiera

### Fase 7: QA ✅
- [x] `./vendor/bin/phpunit --filter="CaseTest|UserStaffTest"` → 62 tests, 272 assertions, 0 failures

---

## Keys de Traducción Necesarias (~30 nuevas)

```
cases.stage                        cases.ircc_status
cases.final_result                 cases.ircc_code
cases.contract_number              cases.service_type
cases.fees                         cases.financial_info
cases.operational_info             cases.pro_bono
cases.fee_based                    cases.initial_consultation
cases.document_collection          cases.application_preparation
cases.submitted                    cases.under_review
cases.additional_info_requested    cases.decision_pending
cases.not_submitted                cases.received
cases.in_process                   cases.approved
cases.refused                      cases.withdrawn
cases.cancelled                    cases.denied
cases.all_stages                   cases.columns
cases.reset_columns                cases.no_stage
cases.no_ircc_status               cases.progress_bar
```
