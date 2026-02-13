# Plan de Implementación: Epic 2.1 - Expedientes Core

## Metadata
- **Fecha:** 2026-02-10
- **Version:** 1.0
- **Arquitecto:** Winston (Architect Agent)
- **Epic:** 2.1 - Expedientes Core
- **PRD Coverage:** FR10-FR15, FR17-FR21
- **Story Points:** 34
- **Tiempo Total Estimado:** ~50 horas

---

## Resumen de Fases

| Fase | Nombre | Tiempo | Prioridad | Status |
|------|--------|--------|-----------|--------|
| 1 | Modelos y Repositorios | 8h | CRITICO | ⬜ PENDIENTE |
| 2 | Capa de Servicio y Políticas | 6h | CRITICO | ⬜ PENDIENTE |
| 3 | Controladores y API | 8h | CRITICO | ⬜ PENDIENTE |
| 4 | Tests de Backend | 6h | ALTO | ⬜ PENDIENTE |
| 5 | Frontend: Types, Service, Store | 6h | ALTO | ⬜ PENDIENTE |
| 6 | Frontend: Vistas y Componentes | 12h | ALTO | ⬜ PENDIENTE |
| 7 | Traducciones e Integración | 4h | MEDIO | ⬜ PENDIENTE |

---

## Estado Actual del Proyecto

### Lo que YA existe:
```
✅ database/migrations/2026_02_08_221203_create_case_types_table.php
   - Tabla creada con 15 tipos de caso sembrados
   - Categorías: temporary_residence, permanent_residence, humanitarian

✅ database/migrations/2026_02_08_221204_create_cases_table.php
   - Esquema completo definido
   - Foreign keys a: tenants, clients, case_types, users
   - Soft deletes habilitado
```

### Lo que FALTA crear:
```
❌ app/Models/ImmigrationCase.php
❌ app/Models/CaseType.php
❌ app/Http/Controllers/Api/CaseController.php
❌ app/Http/Controllers/Api/CaseTypeController.php
❌ app/Services/Case/CaseService.php
❌ app/Repositories/*/CaseRepository*.php
❌ app/Policies/CasePolicy.php
❌ app/Http/Requests/Case/*.php
❌ app/Http/Resources/Case*.php
❌ tests/Feature/CaseTest.php
❌ resources/js/src/types/case.ts
❌ resources/js/src/services/caseService.ts
❌ resources/js/src/stores/case.ts
❌ resources/js/src/views/cases/*.vue
```

---

## FASE 1: Modelos y Repositorios (8h) ⬜ PENDIENTE

### Objetivo
Crear los modelos Eloquent y la capa de repositorio siguiendo el patrón existente.

### Prerequisitos
- Migraciones de cases y case_types ejecutadas
- Epic 1.2 (Clients) completado

### Tareas

#### 1.1 Modelo CaseType (1h)
- [ ] Crear `app/Models/CaseType.php`
- [ ] Definir fillable: name, code, category, description, is_active
- [ ] Relación: `cases(): HasMany<ImmigrationCase>`
- [ ] Scopes: `scopeActive()`, `scopeByCategory()`, `scopeGlobalOrTenant()`
- [ ] Constantes de categorías con labels en español

#### 1.2 Modelo ImmigrationCase (3h)
- [ ] Crear `app/Models/ImmigrationCase.php` (nombrado así porque 'case' es palabra reservada)
- [ ] Configurar `protected $table = 'cases'`
- [ ] Traits: BelongsToTenant, HasFactory, LogsActivity, SoftDeletes
- [ ] Definir fillable (15+ campos)
- [ ] Casts para fechas: hearing_date, fda_deadline, etc.
- [ ] Relaciones:
  - `client(): BelongsTo<Client>`
  - `caseType(): BelongsTo<CaseType>`
  - `assignedTo(): BelongsTo<User>`
- [ ] Accessors:
  - `getStatusLabelAttribute(): string`
  - `getPriorityLabelAttribute(): string`
  - `getProgressPercentageAttribute(): string`
  - `getDaysUntilHearingAttribute(): ?int`
- [ ] Scopes:
  - `scopeActive()`, `scopeByStatus()`, `scopeByPriority()`
  - `scopeByAssignee()`, `scopeSearch()`, `scopeUpcoming()`
- [ ] Constantes STATUS_LABELS y PRIORITY_LABELS

#### 1.3 Repositorio CaseType (1h)
- [ ] Crear `app/Repositories/Contracts/CaseTypeRepositoryInterface.php`
- [ ] Crear `app/Repositories/Eloquent/CaseTypeRepository.php`
- [ ] Métodos: getActive(), getByCategory(), findByCode()

#### 1.4 Repositorio Case (3h)
- [ ] Crear `app/Repositories/Contracts/CaseRepositoryInterface.php`
- [ ] Métodos del contrato:
  - `findById(int): ?ImmigrationCase`
  - `paginate(array $filters, int $perPage): LengthAwarePaginator`
  - `create(array): ImmigrationCase`
  - `update(ImmigrationCase, array): ImmigrationCase`
  - `delete(ImmigrationCase): bool`
  - `getStatistics(): array`
  - `getNextSequence(CaseType): int`
- [ ] Crear `app/Repositories/Eloquent/CaseRepository.php`
- [ ] Implementar filtros: status, priority, case_type_id, assigned_to, client_id, search
- [ ] Implementar ordenamiento configurable
- [ ] Eager loading de relaciones

### Archivos a Crear

```
app/Models/CaseType.php
app/Models/ImmigrationCase.php
app/Repositories/Contracts/CaseTypeRepositoryInterface.php
app/Repositories/Contracts/CaseRepositoryInterface.php
app/Repositories/Eloquent/CaseTypeRepository.php
app/Repositories/Eloquent/CaseRepository.php
```

### Criterios de Aceptación
- [ ] Modelos tienen todas las relaciones definidas
- [ ] Accessors calculan valores correctamente
- [ ] Scopes filtran datos apropiadamente
- [ ] Repositorios implementan todos los métodos del contrato
- [ ] Tenant isolation funciona correctamente

---

## FASE 2: Capa de Servicio y Políticas (6h) ⬜ PENDIENTE

### Objetivo
Implementar la lógica de negocio y autorización.

### Prerequisitos
- Fase 1 completada
- Modelos y repositorios funcionando

### Tareas

#### 2.1 CaseService (4h)
- [ ] Crear `app/Services/Case/CaseService.php`
- [ ] Inyectar CaseRepositoryInterface y CaseTypeRepositoryInterface
- [ ] Métodos:
  - `listCases(array $filters, int $perPage): LengthAwarePaginator`
  - `getCase(ImmigrationCase): ImmigrationCase`
  - `createCase(array $data): ImmigrationCase`
  - `updateCase(ImmigrationCase, array $data): ImmigrationCase`
  - `deleteCase(ImmigrationCase): void`
  - `assignCase(ImmigrationCase, int $userId): ImmigrationCase`
  - `getTimeline(ImmigrationCase): Collection`
  - `getStatistics(): array`
- [ ] Método privado: `generateCaseNumber(CaseType): string`
  - Formato: `{YEAR}-{TYPE_CODE}-{SEQUENCE}`
  - Ejemplo: `2026-ASYLUM-00042`
- [ ] Activity logging en create, update, delete, assign
- [ ] Transacciones de base de datos para operaciones de escritura

#### 2.2 CasePolicy (2h)
- [ ] Crear `app/Policies/CasePolicy.php`
- [ ] Métodos de autorización:
  - `viewAny(User): bool` → `cases.view`
  - `view(User, ImmigrationCase): bool` → `cases.view` + tenant match
  - `create(User): bool` → `cases.create`
  - `update(User, ImmigrationCase): bool` → `cases.update` + tenant match
  - `delete(User, ImmigrationCase): bool` → `cases.delete` + tenant match
  - `assign(User, ImmigrationCase): bool` → `cases.assign` + tenant match
- [ ] Registrar en AuthServiceProvider

### Archivos a Crear

```
app/Services/Case/CaseService.php
app/Policies/CasePolicy.php
```

### Archivos a Modificar

```
app/Providers/RepositoryServiceProvider.php  # Agregar bindings
app/Providers/AuthServiceProvider.php        # Registrar policy
database/seeders/RolePermissionSeeder.php    # Agregar permisos
```

### Permisos a Agregar

```php
// En RolePermissionSeeder
'cases.view',
'cases.create',
'cases.update',
'cases.delete',
'cases.assign',
```

### Criterios de Aceptación
- [ ] Case number se genera correctamente y es único
- [ ] Activity log registra todas las operaciones
- [ ] Políticas verifican permisos Y tenant match
- [ ] Transacciones previenen datos inconsistentes

---

## FASE 3: Controladores y API (8h) ⬜ PENDIENTE

### Objetivo
Exponer la funcionalidad a través de endpoints REST.

### Prerequisitos
- Fase 2 completada
- Servicio y políticas funcionando

### Tareas

#### 3.1 Form Requests (2h)
- [ ] Crear `app/Http/Requests/Case/StoreCaseRequest.php`
  - client_id: required, exists:clients,id
  - case_type_id: required, exists:case_types,id
  - priority: sometimes, in:urgent,high,medium,low
  - description: nullable, string, max:5000
  - hearing_date: nullable, date
  - fda_deadline: nullable, date
  - brown_sheet_date: nullable, date
  - evidence_deadline: nullable, date
- [ ] Crear `app/Http/Requests/Case/UpdateCaseRequest.php`
  - Todos los campos con 'sometimes' para updates parciales
  - status: sometimes, in:active,inactive,archived,closed
  - assigned_to: sometimes, exists:users,id
- [ ] Crear `app/Http/Requests/Case/AssignCaseRequest.php`
  - assigned_to: required, exists:users,id

#### 3.2 Resources (2h)
- [ ] Crear `app/Http/Resources/CaseResource.php`
  - Todos los campos del modelo
  - Campos computados: status_label, priority_label, progress_percentage, days_until_hearing
  - Relaciones condicionales: client, case_type, assigned_to
- [ ] Crear `app/Http/Resources/CaseTypeResource.php`
  - id, name, code, category, description, is_active

#### 3.3 CaseController (3h)
- [ ] Crear `app/Http/Controllers/Api/CaseController.php`
- [ ] Métodos:
  - `index(Request)` - Lista paginada con filtros
  - `store(StoreCaseRequest)` - Crear caso
  - `show(ImmigrationCase)` - Detalle con relaciones
  - `update(UpdateCaseRequest, ImmigrationCase)` - Actualizar
  - `destroy(ImmigrationCase)` - Soft delete
  - `assign(AssignCaseRequest, ImmigrationCase)` - Asignar usuario
  - `timeline(ImmigrationCase)` - Activity log del caso
  - `statistics()` - Estadísticas del dashboard

#### 3.4 CaseTypeController (1h)
- [ ] Crear `app/Http/Controllers/Api/CaseTypeController.php`
- [ ] Métodos:
  - `index()` - Lista de tipos activos
  - `show(CaseType)` - Detalle de tipo

#### 3.5 Rutas API
- [ ] Agregar rutas en `routes/api.php`:

```php
// Case Types
Route::get('/case-types', [CaseTypeController::class, 'index']);
Route::get('/case-types/{caseType}', [CaseTypeController::class, 'show']);

// Cases
Route::get('/cases/statistics', [CaseController::class, 'statistics']);
Route::apiResource('cases', CaseController::class);
Route::post('/cases/{case}/assign', [CaseController::class, 'assign']);
Route::get('/cases/{case}/timeline', [CaseController::class, 'timeline']);
```

### Archivos a Crear

```
app/Http/Controllers/Api/CaseController.php
app/Http/Controllers/Api/CaseTypeController.php
app/Http/Requests/Case/StoreCaseRequest.php
app/Http/Requests/Case/UpdateCaseRequest.php
app/Http/Requests/Case/AssignCaseRequest.php
app/Http/Resources/CaseResource.php
app/Http/Resources/CaseTypeResource.php
```

### Archivos a Modificar

```
routes/api.php
```

### API Endpoints Resultantes

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | /api/case-types | Lista tipos de caso |
| GET | /api/case-types/{id} | Detalle tipo de caso |
| GET | /api/cases | Lista casos (paginado + filtros) |
| POST | /api/cases | Crear caso |
| GET | /api/cases/{id} | Detalle caso |
| PUT | /api/cases/{id} | Actualizar caso |
| DELETE | /api/cases/{id} | Eliminar caso (soft) |
| POST | /api/cases/{id}/assign | Asignar caso |
| GET | /api/cases/{id}/timeline | Timeline del caso |
| GET | /api/cases/statistics | Estadísticas |

### Criterios de Aceptación
- [ ] Todos los endpoints responden con JSON correcto
- [ ] Validaciones rechazan datos inválidos con 422
- [ ] Autorización retorna 403 cuando no tiene permiso
- [ ] Tenant isolation funciona (404 para casos de otro tenant)

---

## FASE 4: Tests de Backend (6h) ⬜ PENDIENTE

### Objetivo
Garantizar la calidad del código con tests automatizados.

### Prerequisitos
- Fases 1-3 completadas
- API funcionando

### Tareas

#### 4.1 Factories (1h)
- [ ] Crear `database/factories/CaseTypeFactory.php`
- [ ] Crear `database/factories/ImmigrationCaseFactory.php`
  - Estados: active(), closed(), urgent()
  - Relaciones: withClient(), withAssignee()

#### 4.2 Feature Tests (5h)
- [ ] Crear `tests/Feature/CaseTest.php`
- [ ] Tests de listado:
  - [ ] test_admin_can_list_cases
  - [ ] test_cases_are_paginated
  - [ ] test_can_filter_by_status
  - [ ] test_can_filter_by_priority
  - [ ] test_can_filter_by_case_type
  - [ ] test_can_filter_by_assignee
  - [ ] test_can_search_cases
  - [ ] test_unauthorized_user_cannot_list_cases
- [ ] Tests de creación:
  - [ ] test_admin_can_create_case
  - [ ] test_case_number_is_generated_automatically
  - [ ] test_create_case_requires_client_id
  - [ ] test_create_case_requires_case_type_id
  - [ ] test_unauthorized_user_cannot_create_case
- [ ] Tests de lectura:
  - [ ] test_admin_can_view_case
  - [ ] test_cannot_view_case_from_another_tenant
- [ ] Tests de actualización:
  - [ ] test_admin_can_update_case
  - [ ] test_can_update_case_status
  - [ ] test_unauthorized_user_cannot_update_case
- [ ] Tests de eliminación:
  - [ ] test_admin_can_delete_case
  - [ ] test_case_is_soft_deleted
  - [ ] test_unauthorized_user_cannot_delete_case
- [ ] Tests de asignación:
  - [ ] test_admin_can_assign_case
  - [ ] test_cannot_assign_to_user_from_another_tenant
- [ ] Tests de timeline:
  - [ ] test_can_get_case_timeline
- [ ] Tests de estadísticas:
  - [ ] test_can_get_case_statistics
- [ ] Tests de tenant isolation:
  - [ ] test_case_auto_assigned_to_user_tenant
  - [ ] test_user_only_sees_own_tenant_cases
- [ ] Tests de activity logging:
  - [ ] test_creating_case_logs_activity
  - [ ] test_updating_case_logs_activity

### Archivos a Crear

```
database/factories/CaseTypeFactory.php
database/factories/ImmigrationCaseFactory.php
tests/Feature/CaseTest.php
```

### Criterios de Aceptación
- [ ] Mínimo 25 tests
- [ ] Cobertura de todos los endpoints
- [ ] Tests pasan con `./vendor/bin/phpunit tests/Feature/CaseTest.php`

---

## FASE 5: Frontend - Types, Service, Store (6h) ⬜ PENDIENTE

### Objetivo
Crear la infraestructura frontend para consumir la API.

### Prerequisitos
- Backend completado y testeado (Fases 1-4)

### Tareas

#### 5.1 Type Definitions (2h)
- [ ] Crear `resources/js/src/types/case.ts`

```typescript
export type CaseStatus = 'active' | 'inactive' | 'archived' | 'closed';
export type CasePriority = 'urgent' | 'high' | 'medium' | 'low';
export type CaseTypeCategory = 'temporary_residence' | 'permanent_residence' | 'humanitarian';

export interface CaseType {
    id: number;
    tenant_id: number | null;
    name: string;
    code: string;
    category: CaseTypeCategory;
    description: string | null;
    is_active: boolean;
}

export interface ImmigrationCase {
    id: number;
    case_number: string;
    tenant_id: number;
    client_id: number;
    case_type_id: number;
    assigned_to: number | null;
    status: CaseStatus;
    status_label: string;
    priority: CasePriority;
    priority_label: string;
    progress: number;
    progress_percentage: string;
    language: string;
    description: string | null;
    hearing_date: string | null;
    fda_deadline: string | null;
    brown_sheet_date: string | null;
    evidence_deadline: string | null;
    days_until_hearing: number | null;
    archive_box_number: string | null;
    closed_at: string | null;
    closure_notes: string | null;
    created_at: string;
    updated_at: string;
    // Relations
    client?: Client;
    case_type?: CaseType;
    assigned_user?: User;
}

export interface CreateCaseData { ... }
export interface UpdateCaseData { ... }
export interface CaseFilters { ... }
export interface CaseStatistics { ... }

// Constants
export const CASE_STATUS_OPTIONS: Array<{value, label, color}>;
export const CASE_PRIORITY_OPTIONS: Array<{value, label, color}>;
export const CASE_TYPE_CATEGORY_OPTIONS: Array<{value, label}>;
```

#### 5.2 API Service (2h)
- [ ] Crear `resources/js/src/services/caseService.ts`
- [ ] Métodos:
  - getCases(filters): Promise<PaginatedResponse>
  - getCase(id): Promise<ImmigrationCase>
  - createCase(data): Promise<ImmigrationCase>
  - updateCase(id, data): Promise<ImmigrationCase>
  - deleteCase(id): Promise<{message}>
  - assignCase(id, userId): Promise<ImmigrationCase>
  - getTimeline(id): Promise<ActivityLog[]>
  - getStatistics(): Promise<CaseStatistics>
  - getCaseTypes(): Promise<CaseType[]>

#### 5.3 Pinia Store (2h)
- [ ] Crear `resources/js/src/stores/case.ts`
- [ ] State:
  - cases: ImmigrationCase[]
  - currentCase: ImmigrationCase | null
  - caseTypes: CaseType[]
  - statistics: CaseStatistics | null
  - timeline: ActivityLog[]
  - meta: PaginationMeta | null
  - filters: CaseFilters
  - isLoading: boolean
  - error: string | null
- [ ] Getters:
  - getCaseById, totalCases, currentPage, statusOptions, priorityOptions
  - urgentCount, upcomingHearingsCount
- [ ] Actions:
  - fetchCases, fetchCase, createCase, updateCase, deleteCase
  - assignCase, fetchTimeline, fetchStatistics, fetchCaseTypes
  - setFilters, resetFilters, clearCurrentCase

### Archivos a Crear

```
resources/js/src/types/case.ts
resources/js/src/services/caseService.ts
resources/js/src/stores/case.ts
```

### Criterios de Aceptación
- [ ] TypeScript compila sin errores
- [ ] Service cubre todos los endpoints
- [ ] Store maneja estado correctamente

---

## FASE 6: Frontend - Vistas y Componentes (12h) ⬜ PENDIENTE

### Objetivo
Crear las interfaces de usuario para gestión de casos.

### Prerequisitos
- Fase 5 completada
- Store y service funcionando

### Tareas

#### 6.1 Rutas (0.5h)
- [ ] Agregar rutas en `router/index.ts`:

```typescript
{
    path: '/cases',
    name: 'cases.list',
    component: () => import('@/views/cases/list.vue'),
    meta: { permission: 'cases.view' }
},
{
    path: '/cases/:id',
    name: 'cases.show',
    component: () => import('@/views/cases/show.vue'),
    meta: { permission: 'cases.view' }
},
{
    path: '/cases/:id/edit',
    name: 'cases.edit',
    component: () => import('@/views/cases/edit.vue'),
    meta: { permission: 'cases.update' }
}
```

#### 6.2 Sidebar Menu (0.5h)
- [ ] Agregar item de menú "Expedientes" en Sidebar.vue

#### 6.3 List View (4h) - US-2.1.1, US-2.1.2
- [ ] Crear `views/cases/list.vue`
- [ ] Header con título y botón "Nuevo Caso"
- [ ] Barra de filtros: search, status, priority, case_type, assigned_to
- [ ] Grid de CaseCards
- [ ] Paginación
- [ ] Estados vacíos
- [ ] Componentes:
  - [ ] `components/CaseCard.vue` - Tarjeta con info resumida
  - [ ] `components/CaseFilters.vue` - Barra de filtros

#### 6.4 Show View (4h) - US-2.1.3, US-2.1.4, US-2.1.6, US-2.1.7
- [ ] Crear `views/cases/show.vue`
- [ ] Header con case_number, status, priority badges
- [ ] Información del cliente vinculado
- [ ] Tabs: Información, Timeline, Documentos (futuro), Tareas (futuro)
- [ ] Progress pipeline
- [ ] Componentes:
  - [ ] `components/CaseHeader.vue` - Header con badges
  - [ ] `components/CaseTabs.vue` - Navegación de tabs
  - [ ] `components/CaseTimeline.vue` - Activity log
  - [ ] `components/CasePipeline.vue` - Barra de progreso
  - [ ] `components/CaseAssign.vue` - Modal de asignación

#### 6.5 Edit View (3h) - US-2.1.5
- [ ] Crear `views/cases/edit.vue`
- [ ] Formulario con campos editables
- [ ] Validación frontend
- [ ] Botones guardar/cancelar

### Archivos a Crear

```
resources/js/src/views/cases/list.vue
resources/js/src/views/cases/show.vue
resources/js/src/views/cases/edit.vue
resources/js/src/views/cases/components/CaseCard.vue
resources/js/src/views/cases/components/CaseFilters.vue
resources/js/src/views/cases/components/CaseHeader.vue
resources/js/src/views/cases/components/CaseTabs.vue
resources/js/src/views/cases/components/CaseTimeline.vue
resources/js/src/views/cases/components/CasePipeline.vue
resources/js/src/views/cases/components/CaseAssign.vue
```

### Archivos a Modificar

```
resources/js/src/router/index.ts
resources/js/src/components/layout/Sidebar.vue
```

### Criterios de Aceptación
- [ ] Lista muestra casos con filtros funcionales
- [ ] Detalle muestra toda la información
- [ ] Timeline carga activity log
- [ ] Edición guarda cambios correctamente
- [ ] Asignación funciona desde modal

---

## FASE 7: Traducciones e Integración (4h) ⬜ PENDIENTE

### Objetivo
Completar traducciones y verificar integración end-to-end.

### Prerequisitos
- Fases 5-6 completadas

### Tareas

#### 7.1 Traducciones (2h)
- [ ] Agregar ~60 keys a `locales/en.json`
- [ ] Agregar ~60 keys a `locales/es.json`
- [ ] Keys para:
  - Menú y navegación
  - List view (filtros, estados vacíos, acciones)
  - Show view (tabs, campos, acciones)
  - Edit view (labels, validaciones, botones)
  - Mensajes de éxito/error

#### 7.2 Testing de Integración (2h)
- [ ] Verificar flujo completo: list → create → show → edit → delete
- [ ] Verificar filtros funcionan correctamente
- [ ] Verificar asignación de casos
- [ ] Verificar timeline muestra actividades
- [ ] Verificar tenant isolation en frontend
- [ ] Build de producción: `npm run build`

### Archivos a Modificar

```
resources/js/src/locales/en.json
resources/js/src/locales/es.json
```

### Criterios de Aceptación
- [ ] Todas las cadenas están traducidas
- [ ] Build de producción exitoso
- [ ] Flujo completo funciona sin errores

---

## Resumen de Archivos

### Backend: 15 archivos nuevos

```
app/
├── Http/
│   ├── Controllers/Api/
│   │   ├── CaseController.php
│   │   └── CaseTypeController.php
│   ├── Requests/Case/
│   │   ├── StoreCaseRequest.php
│   │   ├── UpdateCaseRequest.php
│   │   └── AssignCaseRequest.php
│   └── Resources/
│       ├── CaseResource.php
│       └── CaseTypeResource.php
├── Models/
│   ├── ImmigrationCase.php
│   └── CaseType.php
├── Policies/
│   └── CasePolicy.php
├── Repositories/
│   ├── Contracts/
│   │   ├── CaseRepositoryInterface.php
│   │   └── CaseTypeRepositoryInterface.php
│   └── Eloquent/
│       ├── CaseRepository.php
│       └── CaseTypeRepository.php
└── Services/Case/
    └── CaseService.php

database/factories/
├── CaseTypeFactory.php
└── ImmigrationCaseFactory.php

tests/Feature/
└── CaseTest.php
```

### Frontend: 12 archivos nuevos

```
resources/js/src/
├── types/case.ts
├── services/caseService.ts
├── stores/case.ts
└── views/cases/
    ├── list.vue
    ├── show.vue
    ├── edit.vue
    └── components/
        ├── CaseCard.vue
        ├── CaseFilters.vue
        ├── CaseHeader.vue
        ├── CaseTabs.vue
        ├── CaseTimeline.vue
        ├── CasePipeline.vue
        └── CaseAssign.vue
```

### Archivos a Modificar: 6

```
routes/api.php
app/Providers/RepositoryServiceProvider.php
app/Providers/AuthServiceProvider.php
database/seeders/RolePermissionSeeder.php
resources/js/src/router/index.ts
resources/js/src/components/layout/Sidebar.vue
resources/js/src/locales/en.json
resources/js/src/locales/es.json
```

---

## Dependencias para Siguiente Epic

- Epic 2.1 es prerequisito para:
  - **Epic 2.2**: Case Wizard (creación guiada de casos)
  - **Epic 3.1**: Tareas (tasks vinculadas a casos)
  - **Epic 4.4**: Documentos (documentos vinculados a casos)

---

## Notas Técnicas Importantes

### 1. Naming del Modelo
El modelo se llama `ImmigrationCase` (no `Case`) porque `case` es palabra reservada en PHP.

```php
class ImmigrationCase extends Model
{
    protected $table = 'cases';
}
```

### 2. Generación de Case Number
Formato: `{YEAR}-{TYPE_CODE}-{SEQUENCE}`
Ejemplo: `2026-ASYLUM-00042`

### 3. Status Transitions
```
active → inactive | archived | closed
inactive → active | closed
closed es estado final (pero puede reabrirse a active si necesario)
```

### 4. Permisos Requeridos
```php
'cases.view'   // Ver lista y detalle
'cases.create' // Crear nuevos casos
'cases.update' // Editar casos
'cases.delete' // Eliminar casos
'cases.assign' // Asignar casos a usuarios
```

---

**Documento generado por Winston (Architect Agent)**
**Fecha: 2026-02-10**
