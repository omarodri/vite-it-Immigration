# Plan de Implementación: Ciclo de Vida / Checklist Dinámico por Expediente

## Metadata
- **Fecha:** 2026-03-13
- **Version:** 1.0
- **Arquitecto:** Claude (Architect Agent)
- **Epic:** 3.4 - Ciclo de Vida / Checklist Dinámico
- **Alcance:** Cada expediente tiene su propio checklist de tareas ordenables. Nuevo Paso 5 en el Wizard, nueva sección en edit.vue y nueva tab en show.vue. El campo `progress` se calcula automáticamente desde las tareas completadas.
- **Flujos cubiertos:** Wizard (nuevo Paso 5) + Edit (sección Ciclo de Vida) + Show (tab interactiva)
- **Tiempo Total Estimado:** 20–26 horas
- **Estado:** ✅ COMPLETADO

---

## Decisiones Arquitectónicas

| Dimensión | Decisión | Patrón de referencia |
|-----------|----------|----------------------|
| Modelo de datos | `case_tasks` con `is_custom`, sin `tenant_id`, sin soft delete | Sigue patrón de `case_important_dates` |
| Progreso | `CaseTaskService::recalculateProgress()` (method explícito) | Consistente con `CaseService` existente |
| i18n lista base | Frontend hardcoded con keys + locales JSON | Sigue patrón de traducciones existente |
| API: wizard | Embebido en `POST /api/cases` como `case_tasks: [...]` | Sigue patrón de `important_dates` y `companion_ids` |
| API: edición | `PUT /api/cases/{id}/tasks` con replace strategy (delete-and-insert) | Sigue patrón de `important_dates` en `updateCase()` |
| API: toggle | `PATCH /api/cases/{id}/tasks/{taskId}/toggle` | Operación atómica para UX fluida en show |
| API: listado | NO incluir tasks en eager loading del index | `progress` ya está denormalizado en `cases` |
| Wizard | 5 → 6 pasos, nuevo Paso 5 "Checklist" | Extend pattern existente |
| Slider progress | Condicional: readonly si hay tasks, editable si no hay | Preserva backward compatibility |
| Drag-and-drop | `vue-draggable-plus` v0.6.0 (ya instalado) | Ya declarado en `package.json` |
| Sort order | Reasignación secuencial 0..N al guardar | Simple, sin gaps |

---

## Esquema de Base de Datos

```sql
CREATE TABLE case_tasks (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id         BIGINT UNSIGNED NOT NULL,
    label           VARCHAR(150) NOT NULL,
    is_completed    BOOLEAN NOT NULL DEFAULT false,
    is_custom       BOOLEAN NOT NULL DEFAULT false,
    sort_order      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    completed_at    TIMESTAMP NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    CONSTRAINT fk_ct_case_id FOREIGN KEY (case_id)
        REFERENCES cases(id) ON DELETE CASCADE,

    INDEX idx_ct_case_id (case_id),
    INDEX idx_ct_case_completed (case_id, is_completed)
);
```

**Decisiones de campo:**
- `is_custom`: distingue tareas base vs personalizadas (reportes futuros + UX badge + reset behavior)
- Sin `tenant_id`: se infiere desde `case_id → cases.tenant_id` (igual que `case_important_dates`)
- Sin soft delete: tareas son datos operativos efímeros, no datos legales
- `sort_order TINYINT`: 0–255 es suficiente para este dominio (máx estimado: 20–30 tareas)
- `label VARCHAR(150)`: etiquetas largas en francés consumen ~45 chars; 150 da margen seguro

---

## Internacionalización — Lista Base de Tareas

**Estrategia: Frontend hardcoded con keys + locales JSON**

Las 10 tareas del proceso IRCC se definen como constantes en `types/case.ts` con una `key` estable. Las traducciones van en `locales/*.json` bajo `case_tasks.*`.

**Lo que se persiste en la DB:** El `label` traducido al idioma del expediente (`wizard.state.caseDetails.language`). No se guarda la key. Razón: cuando se consulta el caso meses después, la tarea debe mostrarse en el idioma original del expediente.

**Lista base (keys):**
```
contract_signature, document_reception, document_review,
application_preparation, application_submission, acknowledgment_receipt,
biometric_verification, interview, additional_info_request, final_decision
```

**Cambio de idioma en Paso 4:**
- Tareas base (`is_custom: false`): se retraducen automáticamente usando la `key`
- Tareas personalizadas (`is_custom: true`): se preservan intactas (sin `key`, escritas por el usuario)
- El orden (`sort_order`) se preserva siempre

---

## Lógica de Progreso

**Fórmula:** `progress = total_tasks > 0 ? round((completed / total) * 100) : 0`

**Método:** `CaseTaskService::recalculateProgress(ImmigrationCase $case): void`
- Cuenta `case_tasks` donde `case_id = $case->id`
- Calcula el porcentaje
- Actualiza `$case->progress` + `$case->save()`

**Se invoca en:** `syncTasks()`, `toggleTask()` (todos los métodos de `CaseTaskService`)

**Slider condicional en `edit.vue`:**
- Si `form.tasks.length > 0` → slider se reemplaza por barra readonly "(Calculado automáticamente)"
- Si `form.tasks.length === 0` → slider permanece editable (backward compatibility)

---

## API Contract

### POST `/api/cases` — Wizard (case_tasks embebido)

```json
{
  "client_id": 42,
  "case_type_id": 3,
  "language": "fr",
  "case_tasks": [
    { "label": "Signature du contrat",   "is_custom": false, "sort_order": 0 },
    { "label": "Réception des documents","is_custom": false, "sort_order": 1 },
    { "label": "Tâche spéciale",         "is_custom": true,  "sort_order": 2 }
  ]
}
```

Si `case_tasks` ausente o vacío → caso se crea sin tareas (`progress = 0`).

### PUT `/api/cases/{id}/tasks` — Bulk update (Edit)

```json
{
  "tasks": [
    { "label": "Signature du contrat", "is_completed": true,  "is_custom": false, "sort_order": 0 },
    { "label": "Réception des documents","is_completed": false,"is_custom": false,"sort_order": 1 },
    { "label": "Nueva tarea custom",   "is_completed": false, "is_custom": true,  "sort_order": 2 }
  ]
}
```

**Response:**
```json
{
  "data": {
    "id": 123,
    "progress": 33,
    "tasks": [
      { "id": 15, "label": "Signature du contrat", "is_completed": true,
        "is_custom": false, "sort_order": 0, "completed_at": "2026-03-13T10:30:00Z" },
      ...
    ]
  },
  "message": "Tasks updated successfully."
}
```

### PATCH `/api/cases/{caseId}/tasks/{taskId}/toggle`

Sin body (toggle automático).

**Response:**
```json
{
  "data": {
    "id": 5,
    "label": "Signature du contrat",
    "is_completed": true,
    "is_custom": false,
    "sort_order": 0,
    "completed_at": "2026-03-13T10:30:00Z"
  },
  "meta": { "case_progress": 33 },
  "message": "Task toggled successfully."
}
```

### GET `/api/cases/{id}` — Incluye tasks condicionalmente

- `findById()` en el repository → sí incluye `tasks` en `with()`
- `paginate()` → NO incluye `tasks` (solo `progress` denormalizado)

---

## Wizard — Paso 5 "Checklist"

### Cambio: 5 → 6 pasos

```
Paso 1: Case Type    → sin cambio
Paso 2: Client       → sin cambio
Paso 3: Companions   → sin cambio
Paso 4: Details      → sin cambio
Paso 5: Checklist    → NUEVO
Paso 6: Summary      → antes era Paso 5
```

### Estado en `WizardState`

```typescript
export interface WizardTaskItem {
    key: string | null;   // null para custom; key estable para base
    label: string;        // texto traducido según idioma del paso 4
    is_custom: boolean;
    sort_order: number;
}
// Se agrega: selectedTasks: WizardTaskItem[]
```

### Estructura del componente `StepChecklist.vue`

- **Columna izquierda:** 10 checkboxes con la lista base (idioma del paso 4). Al marcar → mueve a la derecha.
- **Columna derecha:** Tareas seleccionadas con drag-and-drop (`<VueDraggable>`). Cada item tiene botón "×" para deseleccionar.
- **Abajo:** Campo texto + botón "Agregar tarea personalizada" (is_custom: true).
- El orden final se refleja en `sort_order` (0..N).

---

## Edit.vue — Sección "Ciclo de Vida"

Nueva sección después de "Companions" y antes del botón "Guardar". Contiene:
1. Header con contador `(completadas/total)` + barra de progreso calculada (reemplaza el slider)
2. `LifecycleChecklist.vue` con drag-and-drop + checkboxes + eliminar
3. Campo "Agregar tarea personalizada"
4. Las tareas se envían como `case_tasks` en el mismo PUT del formulario

---

## Show.vue — Nueva Tab "Ciclo de Vida"

Nueva tab en el array `tabs`. Contenido:
- Barra de progreso (readonly)
- Lista de tareas con **checkboxes interactivos** (llama a `PATCH .../toggle`)
- Tareas completadas muestran `completed_at`
- Sin opción de agregar/eliminar/reordenar (eso es de edit.vue)

---

## Casos de Borde

| Caso | Comportamiento |
|------|----------------|
| Eliminar tarea completada | Recalcular: `completadas / total`. Ej: 5/10 → eliminar completada → 4/9 = 44% |
| Caso sin tareas | `progress = 0` (división protegida) |
| Cambio idioma Paso 4 | Retraducir base, preservar custom, mantener orden |
| Slider vs automático | Condicional: con tasks → readonly; sin tasks → editable |
| 0 tareas en wizard | Permitido (campo opcional) |
| Label vacío | Validado en frontend y backend (`required`, `max:150`) |
| Sort_order duplicado | Frontend normaliza a 0..N antes de enviar |

---

## Lista Completa de Archivos Impactados

### Backend — Nuevos

| Archivo | Descripción |
|---------|-------------|
| `database/migrations/YYYY_create_case_tasks_table.php` | Migración |
| `app/Models/CaseTask.php` | Modelo Eloquent |
| `app/Http/Resources/CaseTaskResource.php` | API Resource |
| `app/Services/Case/CaseTaskService.php` | syncTasks, toggleTask, recalculateProgress |
| `app/Http/Controllers/Api/CaseTaskController.php` | bulkUpdate, toggle |
| `app/Http/Requests/Case/BulkUpdateCaseTasksRequest.php` | Validación bulk |
| `database/factories/CaseTaskFactory.php` | Factory para tests |
| `tests/Feature/CaseTaskApiTest.php` | Tests de API |

### Backend — Modificados

| Archivo | Cambio |
|---------|--------|
| `app/Models/ImmigrationCase.php` | Relación `tasks(): HasMany` |
| `app/Services/Case/CaseService.php` | Extraer/persistir `case_tasks` en create/update; invocar recalculateProgress |
| `app/Http/Resources/CaseResource.php` | Agregar `tasks` con `whenLoaded` |
| `app/Http/Requests/Case/StoreCaseRequest.php` | Reglas para `case_tasks.*` |
| `app/Http/Requests/Case/UpdateCaseRequest.php` | Mismas reglas (sometimes) |
| `app/Repositories/Eloquent/CaseRepository.php` | Eager load `tasks` en `findById()` solamente |
| `routes/api.php` | Rutas `PUT /cases/{case}/tasks` y `PATCH /cases/{case}/tasks/{task}/toggle` |

### Frontend — Nuevos

| Archivo | Descripción |
|---------|-------------|
| `resources/js/src/views/cases/wizard/steps/StepChecklist.vue` | Nuevo Paso 5 del wizard |
| `resources/js/src/components/LifecycleChecklist.vue` | Componente reutilizable (edit + show) |

### Frontend — Modificados

| Archivo | Cambio |
|---------|--------|
| `resources/js/src/types/case.ts` | CaseTask, DefaultCaseTask, DEFAULT_CASE_TASKS; `tasks?` en ImmigrationCase; `case_tasks?` en CreateCaseData/UpdateCaseData |
| `resources/js/src/types/wizard.ts` | WizardTaskItem; WIZARD_STEPS a 6; `selectedTasks` en WizardState |
| `resources/js/src/composables/useCaseWizard.ts` | Estado selectedTasks; canGoNext < 6; isLastStep === 6; sessionStorage; payload submit |
| `resources/js/src/views/cases/wizard/CaseWizard.vue` | Importar StepChecklist; case 5 nuevo; case 6 = summary |
| `resources/js/src/views/cases/wizard/steps/StepSummary.vue` | Mostrar tareas seleccionadas |
| `resources/js/src/views/cases/edit.vue` | Sección Ciclo de Vida; slider condicional; case_tasks en payload |
| `resources/js/src/views/cases/show.vue` | Tab lifecycle; toggle interactivo |
| `resources/js/src/services/caseService.ts` | bulkUpdateTasks(), toggleTask() |
| `resources/js/src/stores/case.ts` | Actions para toggle y bulk; actualizar progress |
| `resources/js/src/locales/en.json` | case_tasks.* (10 labels) + cases.tab_lifecycle + cases.lifecycle_* |
| `resources/js/src/locales/es.json` | Ídem |
| `resources/js/src/locales/fr.json` | Ídem |

---

## Checklist de Implementación

### Fase 1: Backend Core ✅
- [x] Crear migración `create_case_tasks_table`
- [x] Crear modelo `CaseTask`
- [x] Crear `CaseTaskResource`
- [x] Relación `tasks()` en `ImmigrationCase`
- [x] Ejecutar migración
- [x] Crear factory `CaseTaskFactory`

### Fase 2: Backend Service + API ✅
- [x] Crear `CaseTaskService` (syncTasks, toggleTask, recalculateProgress)
- [x] Crear `BulkUpdateCaseTasksRequest`
- [x] Crear `CaseTaskController` (bulkUpdate, toggle)
- [x] Modificar `StoreCaseRequest`: reglas `case_tasks.*`
- [x] Modificar `CaseService::createCase()`: persistir tareas
- [x] Modificar `CaseService::updateCase()`: replace strategy para tareas
- [x] Modificar `CaseResource`: `whenLoaded('tasks')`
- [x] Modificar `CaseRepository::findById()`: eager load tasks
- [x] Agregar rutas en `api.php`
- [x] Escribir tests

### Fase 3: Frontend Types + i18n ✅
- [x] Tipos `CaseTask`, `DefaultCaseTask`, `WizardTaskItem` en types/
- [x] Constante `DEFAULT_CASE_TASKS` en `types/case.ts`
- [x] Traducciones en en/es/fr.json (case_tasks.* + cases.lifecycle_*)
- [x] `WIZARD_STEPS` actualizado a 6 pasos

### Fase 4: Frontend Wizard ✅
- [x] Crear `StepChecklist.vue`
- [x] Modificar `useCaseWizard.ts`
- [x] Modificar `CaseWizard.vue`
- [x] Modificar `StepSummary.vue`
- [x] Lógica de retraducción al cambiar idioma en Paso 4

### Fase 5: Frontend Edit + Show ✅
- [x] Crear `LifecycleChecklist.vue`
- [x] Modificar `edit.vue` (sección + slider condicional)
- [x] Modificar `show.vue` (tab + toggle interactivo)
- [x] Agregar métodos en `caseService.ts`
- [x] Agregar actions en `stores/case.ts`

### Fase 6: Testing + QA ✅
- [x] Test: crear caso con tareas desde wizard
- [x] Test: crear caso SIN tareas (legacy)
- [x] Test: toggle individual desde show
- [x] Test: bulk update (agregar, eliminar, reordenar)
- [x] Test: recálculo de progreso (0 tareas, todas completadas, ninguna)
- [x] Test: cambio de idioma en Paso 4
- [x] `./vendor/bin/phpunit` sin nuevas regresiones

---

## Verificación Final

**Fecha:** 2026-03-13

**Tests:** 58 tests, 258 assertions — ✅ OK (1 PHPUnit deprecation pre-existente, no relacionada con este epic)

**Frontend build:** `npm run build` — ✅ sin errores TypeScript ni de compilación

**Archivos nuevos creados:**
- `database/migrations/2026_03_13_164223_create_case_tasks_table.php`
- `app/Models/CaseTask.php`
- `app/Http/Resources/CaseTaskResource.php`
- `app/Services/Case/CaseTaskService.php`
- `app/Http/Controllers/Api/CaseTaskController.php`
- `app/Http/Requests/Case/BulkUpdateCaseTasksRequest.php`
- `database/factories/CaseTaskFactory.php`
- `resources/js/src/views/cases/wizard/steps/StepChecklist.vue`
- `resources/js/src/components/LifecycleChecklist.vue`

**Archivos modificados (backend):** `ImmigrationCase`, `CaseService`, `CaseResource`, `StoreCaseRequest`, `UpdateCaseRequest`, `CaseRepository`, `routes/api.php`

**Archivos modificados (frontend):** `types/case.ts`, `types/wizard.ts`, `useCaseWizard.ts`, `CaseWizard.vue`, `StepSummary.vue`, `edit.vue`, `show.vue`, `caseService.ts`, `stores/case.ts`, `locales/en.json`, `locales/es.json`, `locales/fr.json`
