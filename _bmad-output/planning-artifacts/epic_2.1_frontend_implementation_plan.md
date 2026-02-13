# Plan de Implementacion Frontend: Epic 2.1 - Expedientes Core

## Metadata
- **Fecha:** 2026-02-10
- **Version:** 1.0
- **Autor:** Frontend Specialist Agent
- **Epic:** 2.1 - Expedientes Core (Fase Frontend)
- **Fases Backend Prerequisito:** Fases 1-4 del spec del arquitecto
- **Tiempo Estimado Total:** 22 horas

---

## Resumen de Subfases Frontend

| Subfase | Nombre | Tiempo | Prioridad | Dependencias |
|---------|--------|--------|-----------|--------------|
| 5.1 | Type Definitions | 2h | CRITICO | Backend APIs |
| 5.2 | API Service | 2h | CRITICO | 5.1 |
| 5.3 | Pinia Store | 2h | CRITICO | 5.1, 5.2 |
| 6.1 | Rutas y Sidebar | 1h | ALTO | 5.3 |
| 6.2 | Componentes Reutilizables | 4h | ALTO | 5.3 |
| 6.3 | Vista List | 4h | ALTO | 6.1, 6.2 |
| 6.4 | Vista Show | 4h | ALTO | 6.1, 6.2 |
| 6.5 | Vista Edit | 2h | ALTO | 6.1, 6.2 |
| 7.1 | Traducciones | 1h | MEDIO | 6.3, 6.4, 6.5 |

---

## SUBFASE 5.1: Type Definitions (2h)

### Objetivo
Crear interfaces TypeScript completas para el dominio de Expedientes.

### Archivo a Crear

```
resources/js/src/types/case.ts
```

### Interfaces Detalladas

#### Tipos Base (Enums Equivalentes)

```typescript
// Status del caso
export type CaseStatus = 'active' | 'inactive' | 'archived' | 'closed';

// Prioridad del caso
export type CasePriority = 'urgent' | 'high' | 'medium' | 'low';

// Categoria del tipo de caso
export type CaseTypeCategory = 'temporary_residence' | 'permanent_residence' | 'humanitarian';
```

#### Interface CaseType

```typescript
export interface CaseType {
    id: number;
    tenant_id: number | null;
    name: string;
    code: string;
    category: CaseTypeCategory;
    description: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}
```

#### Interface ImmigrationCase (Principal)

```typescript
export interface ImmigrationCase {
    id: number;
    case_number: string;
    tenant_id: number;
    client_id: number;
    case_type_id: number;
    assigned_to: number | null;

    // Status y Prioridad
    status: CaseStatus;
    status_label: string;  // Computado por backend
    priority: CasePriority;
    priority_label: string;  // Computado por backend

    // Progreso
    progress: number;  // 0-100
    progress_percentage: string;  // "45%"

    // Configuracion
    language: string;
    description: string | null;

    // Fechas importantes
    hearing_date: string | null;
    fda_deadline: string | null;
    brown_sheet_date: string | null;
    evidence_deadline: string | null;
    days_until_hearing: number | null;  // Computado

    // Archivado
    archive_box_number: string | null;
    closed_at: string | null;
    closure_notes: string | null;

    // Timestamps
    created_at: string;
    updated_at: string;
    deleted_at: string | null;

    // Relaciones (opcionales, incluidas con eager loading)
    client?: {
        id: number;
        first_name: string;
        last_name: string;
        full_name?: string;
        email: string | null;
        phone: string | null;
    };
    case_type?: CaseType;
    assigned_user?: {
        id: number;
        name: string;
        email: string;
    };
}
```

#### Interfaces de Datos para Crear/Actualizar

```typescript
export interface CreateCaseData {
    client_id: number;
    case_type_id: number;
    priority?: CasePriority;
    language?: string;
    description?: string;
    hearing_date?: string;
    fda_deadline?: string;
    brown_sheet_date?: string;
    evidence_deadline?: string;
}

export interface UpdateCaseData {
    client_id?: number;
    case_type_id?: number;
    status?: CaseStatus;
    priority?: CasePriority;
    progress?: number;
    language?: string;
    description?: string;
    hearing_date?: string;
    fda_deadline?: string;
    brown_sheet_date?: string;
    evidence_deadline?: string;
    archive_box_number?: string;
    closure_notes?: string;
    assigned_to?: number | null;
}

export interface AssignCaseData {
    assigned_to: number;
}
```

#### Interfaces de Filtros y Estadisticas

```typescript
export interface CaseFilters {
    search?: string;
    status?: CaseStatus;
    priority?: CasePriority;
    case_type_id?: number;
    assigned_to?: number;
    client_id?: number;
    date_from?: string;
    date_to?: string;
    hearing_date_from?: string;
    hearing_date_to?: string;
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
    per_page?: number;
    page?: number;
}

export interface CaseStatistics {
    total: number;
    by_status: {
        active: number;
        inactive: number;
        archived: number;
        closed: number;
    };
    by_priority: {
        urgent: number;
        high: number;
        medium: number;
        low: number;
    };
    upcoming_hearings: number;
    unassigned: number;
}
```

#### Interface ActivityLog (para Timeline)

```typescript
export interface CaseActivityLog {
    id: number;
    log_name: string;
    description: string;
    subject_type: string;
    subject_id: number;
    causer_type: string | null;
    causer_id: number | null;
    properties: {
        old?: Record<string, any>;
        attributes?: Record<string, any>;
    };
    created_at: string;
    causer?: {
        id: number;
        name: string;
    };
}
```

#### Constantes de Opciones

```typescript
// Status options para dropdowns
export const CASE_STATUS_OPTIONS: Array<{ value: CaseStatus; label: string; color: string }> = [
    { value: 'active', label: 'Active', color: 'success' },
    { value: 'inactive', label: 'Inactive', color: 'warning' },
    { value: 'archived', label: 'Archived', color: 'secondary' },
    { value: 'closed', label: 'Closed', color: 'dark' },
];

// Priority options para dropdowns
export const CASE_PRIORITY_OPTIONS: Array<{ value: CasePriority; label: string; color: string }> = [
    { value: 'urgent', label: 'Urgent', color: 'danger' },
    { value: 'high', label: 'High', color: 'warning' },
    { value: 'medium', label: 'Medium', color: 'info' },
    { value: 'low', label: 'Low', color: 'secondary' },
];

// Category options
export const CASE_TYPE_CATEGORY_OPTIONS: Array<{ value: CaseTypeCategory; label: string }> = [
    { value: 'temporary_residence', label: 'Temporary Residence' },
    { value: 'permanent_residence', label: 'Permanent Residence' },
    { value: 'humanitarian', label: 'Humanitarian' },
];

// Spanish translations for labels (backup)
export const CASE_STATUS_LABELS_ES: Record<CaseStatus, string> = {
    active: 'Activo',
    inactive: 'Inactivo',
    archived: 'Archivado',
    closed: 'Cerrado',
};

export const CASE_PRIORITY_LABELS_ES: Record<CasePriority, string> = {
    urgent: 'Urgente',
    high: 'Alta',
    medium: 'Media',
    low: 'Baja',
};
```

### Criterios de Aceptacion
- [ ] Todas las interfaces reflejan el schema del backend
- [ ] TypeScript compila sin errores
- [ ] Constantes incluyen colores para badges

---

## SUBFASE 5.2: API Service (2h)

### Objetivo
Crear el servicio para comunicarse con los endpoints de la API de casos.

### Archivo a Crear

```
resources/js/src/services/caseService.ts
```

### Metodos del Service

```typescript
/**
 * Case Service
 * Handles all case-related API calls
 */

import api from './api';
import type {
    ImmigrationCase,
    CaseType,
    CreateCaseData,
    UpdateCaseData,
    AssignCaseData,
    CaseFilters,
    CaseStatistics,
    CaseActivityLog
} from '@/types/case';
import type { PaginatedResponse } from '@/types/pagination';

// Response interfaces
export interface CaseResponse {
    message: string;
    case: ImmigrationCase;
}

export interface CaseTypesResponse {
    data: CaseType[];
}

export interface TimelineResponse {
    data: CaseActivityLog[];
}

const caseService = {
    // ===============================
    // CASE TYPES
    // ===============================

    /**
     * Get all active case types
     */
    async getCaseTypes(): Promise<CaseType[]>;

    /**
     * Get case type by ID
     */
    async getCaseType(id: number): Promise<CaseType>;

    // ===============================
    // CASES - CRUD
    // ===============================

    /**
     * Get paginated list of cases with filters
     * @param filters - Optional filters for search, status, priority, etc.
     */
    async getCases(filters?: CaseFilters): Promise<PaginatedResponse<ImmigrationCase>>;

    /**
     * Get single case by ID with relations
     */
    async getCase(id: number): Promise<ImmigrationCase>;

    /**
     * Create a new case
     * @param data - Case data (client_id and case_type_id required)
     */
    async createCase(data: CreateCaseData): Promise<CaseResponse>;

    /**
     * Update an existing case
     * @param id - Case ID
     * @param data - Partial case data to update
     */
    async updateCase(id: number, data: UpdateCaseData): Promise<CaseResponse>;

    /**
     * Delete (soft delete) a case
     */
    async deleteCase(id: number): Promise<{ message: string }>;

    // ===============================
    // CASES - ACTIONS
    // ===============================

    /**
     * Assign case to a user
     * @param id - Case ID
     * @param userId - User ID to assign
     */
    async assignCase(id: number, userId: number): Promise<CaseResponse>;

    /**
     * Get case activity timeline
     */
    async getTimeline(id: number): Promise<CaseActivityLog[]>;

    /**
     * Get case statistics for dashboard
     */
    async getStatistics(): Promise<CaseStatistics>;
};

export default caseService;
```

### Implementacion de Filtros

El metodo `getCases` debe construir query params similar a `clientService`:

```typescript
async getCases(filters: CaseFilters = {}): Promise<PaginatedResponse<ImmigrationCase>> {
    const params = new URLSearchParams();

    if (filters.search) params.append('search', filters.search);
    if (filters.status) params.append('status', filters.status);
    if (filters.priority) params.append('priority', filters.priority);
    if (filters.case_type_id) params.append('case_type_id', filters.case_type_id.toString());
    if (filters.assigned_to) params.append('assigned_to', filters.assigned_to.toString());
    if (filters.client_id) params.append('client_id', filters.client_id.toString());
    if (filters.date_from) params.append('date_from', filters.date_from);
    if (filters.date_to) params.append('date_to', filters.date_to);
    if (filters.hearing_date_from) params.append('hearing_date_from', filters.hearing_date_from);
    if (filters.hearing_date_to) params.append('hearing_date_to', filters.hearing_date_to);
    if (filters.sort_by) params.append('sort_by', filters.sort_by);
    if (filters.sort_direction) params.append('sort_direction', filters.sort_direction);
    if (filters.per_page) params.append('per_page', filters.per_page.toString());
    if (filters.page) params.append('page', filters.page.toString());

    const response = await api.get<PaginatedResponse<ImmigrationCase>>(`/cases?${params.toString()}`);
    return response.data;
}
```

### Endpoints Mapeados

| Metodo Service | HTTP | Endpoint |
|----------------|------|----------|
| getCaseTypes() | GET | /api/case-types |
| getCaseType(id) | GET | /api/case-types/{id} |
| getCases(filters) | GET | /api/cases |
| getCase(id) | GET | /api/cases/{id} |
| createCase(data) | POST | /api/cases |
| updateCase(id, data) | PUT | /api/cases/{id} |
| deleteCase(id) | DELETE | /api/cases/{id} |
| assignCase(id, userId) | POST | /api/cases/{id}/assign |
| getTimeline(id) | GET | /api/cases/{id}/timeline |
| getStatistics() | GET | /api/cases/statistics |

### Criterios de Aceptacion
- [ ] Todos los metodos implementados
- [ ] Manejo correcto de query params
- [ ] Tipado correcto de respuestas

---

## SUBFASE 5.3: Pinia Store (2h)

### Objetivo
Crear el store de Pinia para gestionar el estado de casos.

### Archivo a Crear

```
resources/js/src/stores/case.ts
```

### Estructura del Store

#### State

```typescript
interface CaseState {
    // Data
    cases: ImmigrationCase[];
    currentCase: ImmigrationCase | null;
    caseTypes: CaseType[];
    statistics: CaseStatistics | null;
    timeline: CaseActivityLog[];

    // Pagination
    meta: PaginationMeta | null;
    links: PaginationLinks | null;

    // Filters (persistent)
    filters: CaseFilters;

    // UI State
    isLoading: boolean;
    isLoadingTimeline: boolean;
    error: string | null;
}
```

#### Initial State

```typescript
const initialFilters: CaseFilters = {
    search: '',
    status: undefined,
    priority: undefined,
    case_type_id: undefined,
    assigned_to: undefined,
    client_id: undefined,
    sort_by: 'created_at',
    sort_direction: 'desc',
    per_page: 15,
    page: 1,
};
```

#### Getters

```typescript
getters: {
    // Buscar caso por ID en cache
    getCaseById: (state) => (id: number): ImmigrationCase | undefined;

    // Total de casos (del meta de paginacion)
    totalCases: (state): number;

    // Pagina actual
    currentPage: (state): number;

    // Ultima pagina
    lastPage: (state): number;

    // Hay siguiente pagina?
    hasNextPage: (state): boolean;

    // Hay pagina anterior?
    hasPrevPage: (state): boolean;

    // Opciones de status para selects (traducidas)
    statusOptions: (): Array<{ value: CaseStatus | ''; label: string; color: string }>;

    // Opciones de prioridad para selects
    priorityOptions: (): Array<{ value: CasePriority | ''; label: string; color: string }>;

    // Conteo de casos urgentes
    urgentCount: (state): number;

    // Conteo de audiencias proximas (7 dias)
    upcomingHearingsCount: (state): number;

    // Casos sin asignar
    unassignedCount: (state): number;

    // Casos activos filtrados
    activeCases: (state): ImmigrationCase[];

    // Case types agrupados por categoria
    caseTypesByCategory: (state): Record<CaseTypeCategory, CaseType[]>;
}
```

#### Actions

```typescript
actions: {
    // ===============================
    // FETCH OPERATIONS
    // ===============================

    /**
     * Fetch paginated cases with current filters
     */
    async fetchCases(filters?: Partial<CaseFilters>): Promise<void>;

    /**
     * Fetch single case by ID
     */
    async fetchCase(id: number): Promise<ImmigrationCase>;

    /**
     * Fetch case types (cached)
     */
    async fetchCaseTypes(): Promise<void>;

    /**
     * Fetch statistics for dashboard
     */
    async fetchStatistics(): Promise<void>;

    /**
     * Fetch timeline for current case
     */
    async fetchTimeline(caseId: number): Promise<void>;

    // ===============================
    // CRUD OPERATIONS
    // ===============================

    /**
     * Create new case
     */
    async createCase(data: CreateCaseData): Promise<ImmigrationCase>;

    /**
     * Update existing case
     */
    async updateCase(id: number, data: UpdateCaseData): Promise<ImmigrationCase>;

    /**
     * Delete case (soft delete)
     */
    async deleteCase(id: number): Promise<void>;

    /**
     * Assign case to user
     */
    async assignCase(id: number, userId: number): Promise<ImmigrationCase>;

    // ===============================
    // FILTER OPERATIONS
    // ===============================

    /**
     * Set search filter
     */
    setSearch(search: string): void;

    /**
     * Set status filter
     */
    setStatusFilter(status: CaseStatus | undefined): void;

    /**
     * Set priority filter
     */
    setPriorityFilter(priority: CasePriority | undefined): void;

    /**
     * Set case type filter
     */
    setCaseTypeFilter(caseTypeId: number | undefined): void;

    /**
     * Set assigned user filter
     */
    setAssignedToFilter(userId: number | undefined): void;

    /**
     * Set sorting
     */
    setSort(sortBy: string, direction: 'asc' | 'desc'): void;

    /**
     * Set current page
     */
    setPage(page: number): void;

    /**
     * Set items per page
     */
    setPerPage(perPage: number): void;

    /**
     * Reset all filters to defaults
     */
    resetFilters(): void;

    // ===============================
    // CLEAR OPERATIONS
    // ===============================

    /**
     * Clear current case
     */
    clearCurrentCase(): void;

    /**
     * Clear timeline
     */
    clearTimeline(): void;

    /**
     * Clear error
     */
    clearError(): void;
}
```

### Patron de Manejo de Errores

Seguir el patron de `clientStore`:

```typescript
async fetchCases(filters?: Partial<CaseFilters>) {
    this.isLoading = true;
    this.error = null;

    if (filters) {
        this.filters = { ...this.filters, ...filters };
    }

    try {
        const response = await caseService.getCases(this.filters);
        this.cases = response.data;
        this.meta = response.meta;
        this.links = response.links;
    } catch (error: any) {
        this.error = error.response?.data?.message || 'Failed to fetch cases';
        throw error;
    } finally {
        this.isLoading = false;
    }
}
```

### Criterios de Aceptacion
- [ ] State inicial correctamente definido
- [ ] Todos los getters implementados
- [ ] Todas las actions implementadas
- [ ] Manejo de errores consistente
- [ ] Integracion con paginacion de Laravel

---

## SUBFASE 6.1: Rutas y Sidebar (1h)

### Objetivo
Configurar las rutas de Vue Router y agregar el item de menu en el Sidebar.

### Archivos a Modificar

```
resources/js/src/router/index.ts
resources/js/src/components/layout/Sidebar.vue
```

### Rutas a Agregar en router/index.ts

```typescript
// Case Management
{
    path: '/cases',
    name: 'cases',
    component: () => import('../views/cases/list.vue'),
    meta: { permission: 'cases.view' },
},
{
    path: '/cases/create',
    name: 'cases-create',
    component: () => import('../views/cases/create.vue'),
    meta: { permission: 'cases.create' },
},
{
    path: '/cases/:id',
    name: 'cases-show',
    component: () => import('../views/cases/show.vue'),
    meta: { permission: 'cases.view' },
},
{
    path: '/cases/:id/edit',
    name: 'cases-edit',
    component: () => import('../views/cases/edit.vue'),
    meta: { permission: 'cases.update' },
},
```

**Ubicacion:** Despues de las rutas de Client Management (linea ~571)

### Menu Item en Sidebar.vue

Agregar despues de la seccion de Apps (similar a como se agregaria Clients):

```vue
<!-- Expedientes/Cases -->
<li v-if="canViewCases" class="nav-item">
    <router-link to="/cases" class="group" @click="toggleMobileMenu">
        <div class="flex items-center">
            <icon-folder class="group-hover:!text-primary shrink-0" />
            <span class="ltr:pl-3 rtl:pr-3 text-black dark:text-[#506690] dark:group-hover:text-white-dark">
                {{ $t('sidebar.cases') }}
            </span>
        </div>
    </router-link>
</li>
```

Agregar computed para permisos:

```typescript
const canViewCases = computed(() => authStore.hasPermission('cases.view'));
```

Agregar import del icono:

```typescript
import IconFolder from '@/components/icon/icon-folder.vue';
```

### Criterios de Aceptacion
- [ ] Rutas accesibles con lazy loading
- [ ] Permisos aplicados via meta
- [ ] Menu visible solo con permiso
- [ ] Navegacion funciona correctamente

---

## SUBFASE 6.2: Componentes Reutilizables (4h)

### Objetivo
Crear componentes reutilizables especificos para el modulo de casos.

### Archivos a Crear

```
resources/js/src/views/cases/components/CaseCard.vue
resources/js/src/views/cases/components/CaseFilters.vue
resources/js/src/views/cases/components/CaseHeader.vue
resources/js/src/views/cases/components/CaseTabs.vue
resources/js/src/views/cases/components/CaseTimeline.vue
resources/js/src/views/cases/components/CasePipeline.vue
resources/js/src/views/cases/components/CaseAssign.vue
```

---

### 6.2.1 CaseCard.vue

**Proposito:** Tarjeta que muestra informacion resumida de un caso.

**Props:**
```typescript
interface Props {
    case: ImmigrationCase;
    showClient?: boolean;  // default: true
    showActions?: boolean;  // default: true
    compact?: boolean;  // default: false
}
```

**Eventos:**
```typescript
interface Emits {
    (e: 'view', caseId: number): void;
    (e: 'edit', caseId: number): void;
    (e: 'delete', caseItem: ImmigrationCase): void;
    (e: 'assign', caseItem: ImmigrationCase): void;
}
```

**Contenido:**
- Case number (enlace a detalle)
- Status badge con color
- Priority badge con color
- Tipo de caso
- Nombre del cliente (si showClient)
- Fecha de audiencia (si existe, con dias restantes)
- Barra de progreso
- Usuario asignado (avatar o "Sin asignar")
- Botones de acciones (view, edit, delete, assign)

---

### 6.2.2 CaseFilters.vue

**Proposito:** Barra de filtros para la lista de casos.

**Props:**
```typescript
interface Props {
    modelValue: CaseFilters;
    caseTypes: CaseType[];
    users?: Array<{ id: number; name: string }>;  // Para filtro de asignado
    showClientFilter?: boolean;  // default: false
    isLoading?: boolean;
}
```

**Eventos:**
```typescript
interface Emits {
    (e: 'update:modelValue', filters: CaseFilters): void;
    (e: 'search'): void;
    (e: 'reset'): void;
}
```

**Contenido:**
- Input de busqueda (debounced)
- Select de status
- Select de prioridad
- Select de tipo de caso
- Select de usuario asignado (opcional)
- Boton de limpiar filtros
- Indicador de filtros activos

---

### 6.2.3 CaseHeader.vue

**Proposito:** Header de la vista de detalle con informacion principal.

**Props:**
```typescript
interface Props {
    case: ImmigrationCase;
    showActions?: boolean;  // default: true
}
```

**Eventos:**
```typescript
interface Emits {
    (e: 'edit'): void;
    (e: 'delete'): void;
    (e: 'assign'): void;
    (e: 'close'): void;  // Cerrar caso
}
```

**Contenido:**
- Case number prominente
- Status badge grande
- Priority badge
- Tipo de caso
- Usuario asignado con avatar
- Botones: Editar, Asignar, Cerrar Caso, Eliminar
- Breadcrumb

---

### 6.2.4 CaseTabs.vue

**Proposito:** Navegacion de tabs para la vista de detalle.

**Props:**
```typescript
interface Props {
    activeTab: string;
    tabs: Array<{
        id: string;
        label: string;
        icon?: string;
        badge?: number;
        disabled?: boolean;
    }>;
}
```

**Eventos:**
```typescript
interface Emits {
    (e: 'update:activeTab', tabId: string): void;
}
```

**Tabs Definidos:**
```typescript
const defaultTabs = [
    { id: 'info', label: 'cases.tab_information', icon: 'icon-info-circle' },
    { id: 'timeline', label: 'cases.tab_timeline', icon: 'icon-clock' },
    { id: 'documents', label: 'cases.tab_documents', icon: 'icon-folder', disabled: true },  // Futuro
    { id: 'tasks', label: 'cases.tab_tasks', icon: 'icon-clipboard', disabled: true },  // Futuro
];
```

---

### 6.2.5 CaseTimeline.vue

**Proposito:** Mostrar el historial de actividades del caso.

**Props:**
```typescript
interface Props {
    activities: CaseActivityLog[];
    isLoading?: boolean;
}
```

**Contenido:**
- Lista vertical con linea de tiempo
- Icono segun tipo de actividad
- Usuario que realizo la accion
- Descripcion de la actividad
- Fecha/hora formateada
- Cambios de propiedades (old/new values)
- Estado vacio si no hay actividades

---

### 6.2.6 CasePipeline.vue

**Proposito:** Visualizacion del progreso del caso en formato pipeline.

**Props:**
```typescript
interface Props {
    progress: number;  // 0-100
    status: CaseStatus;
}
```

**Contenido:**
- Barra de progreso visual
- Pasos del pipeline (segun progress)
- Indicador de estado actual
- Porcentaje visible

---

### 6.2.7 CaseAssign.vue

**Proposito:** Modal para asignar un caso a un usuario.

**Props:**
```typescript
interface Props {
    isOpen: boolean;
    case: ImmigrationCase | null;
    users: Array<{ id: number; name: string; email: string }>;
}
```

**Eventos:**
```typescript
interface Emits {
    (e: 'close'): void;
    (e: 'assign', userId: number): void;
}
```

**Contenido:**
- Modal con HeadlessUI
- Info del caso actual
- Lista de usuarios disponibles
- Usuario actualmente asignado resaltado
- Botones: Cancelar, Asignar

---

### Criterios de Aceptacion Generales
- [ ] Componentes siguen Composition API con script setup
- [ ] Props y eventos correctamente tipados
- [ ] Estilos usando Tailwind CSS
- [ ] Soporte para dark mode
- [ ] Responsive design
- [ ] Accesibilidad basica (aria labels)

---

## SUBFASE 6.3: Vista List (4h)

### Objetivo
Crear la vista de listado de casos con filtros, paginacion y acciones.

### Archivo a Crear

```
resources/js/src/views/cases/list.vue
```

### Estructura del Template

```
- Breadcrumb
- Statistics Cards (4 tarjetas: por status)
- Panel principal
  - Header (titulo + boton "Nuevo Caso")
  - CaseFilters component
  - Loading skeleton (mientras carga)
  - Desktop: DataTable con vue3-datatable
  - Mobile: Lista de CaseCard
  - Empty state (sin resultados / sin datos)
  - Paginacion mobile
```

### Columnas del DataTable

```typescript
const columns = [
    { field: 'case_number', title: 'Case #', width: '120px', isUnique: true },
    { field: 'client', title: 'Client', minWidth: '200px' },
    { field: 'case_type', title: 'Type', width: '150px' },
    { field: 'status', title: 'Status', width: '100px' },
    { field: 'priority', title: 'Priority', width: '100px' },
    { field: 'hearing_date', title: 'Hearing', width: '120px' },
    { field: 'assigned_to', title: 'Assigned', width: '150px' },
    { field: 'actions', title: 'Actions', width: '150px', sort: false },
];
```

### Funcionalidades

1. **Filtros:**
   - Busqueda por case_number, cliente
   - Filtro por status
   - Filtro por prioridad
   - Filtro por tipo de caso

2. **Ordenamiento:**
   - Por case_number, created_at, hearing_date, priority

3. **Acciones por fila:**
   - Ver detalle
   - Editar
   - Asignar
   - Eliminar (con confirmacion)

4. **Acciones masivas:**
   - Seleccionar multiples
   - Eliminar seleccionados

### Criterios de Aceptacion
- [ ] Paginacion funcional
- [ ] Filtros persisten en URL query params
- [ ] Responsive (tabla en desktop, cards en mobile)
- [ ] Loading states
- [ ] Empty states

---

## SUBFASE 6.4: Vista Show (4h)

### Objetivo
Crear la vista de detalle del caso con tabs.

### Archivo a Crear

```
resources/js/src/views/cases/show.vue
```

### Estructura del Template

```
- Breadcrumb
- Loading state
- CaseHeader component
- Tabs navigation (CaseTabs)
- Tab content:
  - Tab Info: Informacion detallada del caso
  - Tab Timeline: CaseTimeline component
  - Tab Documents: Placeholder (futuro)
  - Tab Tasks: Placeholder (futuro)
- CaseAssign modal
- Delete confirmation dialog
```

### Tab Info - Secciones

1. **Informacion General:**
   - Case number
   - Tipo de caso
   - Status con selector para cambiar
   - Prioridad con selector para cambiar
   - Progreso (CasePipeline)
   - Descripcion

2. **Cliente Vinculado:**
   - Nombre completo (link a cliente)
   - Email
   - Telefono
   - Nacionalidad

3. **Fechas Importantes:**
   - Fecha de audiencia (con dias restantes)
   - FDA Deadline
   - Brown Sheet Date
   - Evidence Deadline

4. **Asignacion:**
   - Usuario asignado
   - Boton para cambiar asignacion

5. **Metadata:**
   - Creado: fecha
   - Actualizado: fecha
   - ID del caso

### Criterios de Aceptacion
- [ ] Tabs funcionales
- [ ] Timeline carga correctamente
- [ ] Edicion inline de status/priority
- [ ] Modal de asignacion funcional
- [ ] Navegacion al cliente

---

## SUBFASE 6.5: Vista Edit (2h)

### Objetivo
Crear la vista/formulario de edicion de casos.

### Archivos a Crear

```
resources/js/src/views/cases/edit.vue
resources/js/src/views/cases/create.vue  (reutiliza edit con modo create)
```

### Estructura del Formulario

```
- Breadcrumb
- Panel
  - Header: "Editar Expediente" / "Nuevo Expediente"
  - Form sections:
    - Seccion 1: Informacion Principal
    - Seccion 2: Fechas
    - Seccion 3: Notas y Archivo
  - Buttons: Guardar, Cancelar
```

### Campos del Formulario

**Seccion 1 - Informacion Principal:**
- Client (select2 con busqueda) - Solo en create
- Case Type (select) - Solo en create
- Status (select) - Solo en edit
- Priority (select)
- Language (select: EN/ES/FR)
- Assigned To (select usuarios)
- Progress (slider 0-100) - Solo en edit

**Seccion 2 - Fechas:**
- Hearing Date (datepicker)
- FDA Deadline (datepicker)
- Brown Sheet Date (datepicker)
- Evidence Deadline (datepicker)

**Seccion 3 - Notas:**
- Description (textarea)
- Archive Box Number (text) - Solo en edit si archived
- Closure Notes (textarea) - Solo en edit si closed

### Validaciones Frontend

```typescript
const rules = {
    client_id: [{ required: true, message: 'Client is required' }],
    case_type_id: [{ required: true, message: 'Case type is required' }],
    priority: [{ required: true, message: 'Priority is required' }],
    hearing_date: [{ type: 'date', message: 'Invalid date format' }],
};
```

### Criterios de Aceptacion
- [ ] Formulario valida antes de submit
- [ ] Errores del backend mostrados correctamente
- [ ] Redirect a show despues de guardar
- [ ] Confirmacion antes de salir con cambios sin guardar

---

## SUBFASE 7.1: Traducciones (1h)

### Objetivo
Agregar todas las traducciones necesarias para el modulo de casos.

### Archivos a Modificar

```
resources/js/src/locales/en.json
resources/js/src/locales/es.json
```

### Keys de Traduccion Requeridas

#### Sidebar y Navegacion

```json
"sidebar.cases": "Cases" / "Expedientes"
```

#### Vista List

```json
"cases.cases": "Cases" / "Expedientes",
"cases.list": "Case List" / "Lista de Expedientes",
"cases.case_management": "Case Management" / "Gestion de Expedientes",
"cases.add_case": "New Case" / "Nuevo Expediente",
"cases.search_placeholder": "Search by case number or client..." / "Buscar por numero o cliente...",
"cases.all_statuses": "All Statuses" / "Todos los estados",
"cases.all_priorities": "All Priorities" / "Todas las prioridades",
"cases.all_types": "All Types" / "Todos los tipos",
"cases.per_page": "per page" / "por pagina",
"cases.no_cases_yet": "No cases yet" / "Sin expedientes",
"cases.get_started_by_adding": "Get started by adding your first case" / "Comienza agregando tu primer expediente",
"cases.add_first_case": "Add First Case" / "Agregar Primer Expediente",
"cases.no_results_found": "No results found" / "Sin resultados",
"cases.no_cases_match_criteria": "No cases match your search criteria" / "Ningun expediente coincide con tu busqueda",
"cases.clear_filters": "Clear Filters" / "Limpiar Filtros",
"cases.page": "Page" / "Pagina",
"cases.of": "of" / "de",
"cases.previous": "Previous" / "Anterior",
"cases.next": "Next" / "Siguiente"
```

#### Status y Priority

```json
"cases.active": "Active" / "Activo",
"cases.inactive": "Inactive" / "Inactivo",
"cases.archived": "Archived" / "Archivado",
"cases.closed": "Closed" / "Cerrado",
"cases.urgent": "Urgent" / "Urgente",
"cases.high": "High" / "Alta",
"cases.medium": "Medium" / "Media",
"cases.low": "Low" / "Baja"
```

#### Vista Show

```json
"cases.case_details": "Case Details" / "Detalles del Expediente",
"cases.tab_information": "Information" / "Informacion",
"cases.tab_timeline": "Timeline" / "Historial",
"cases.tab_documents": "Documents" / "Documentos",
"cases.tab_tasks": "Tasks" / "Tareas",
"cases.general_info": "General Information" / "Informacion General",
"cases.case_number": "Case Number" / "Numero de Expediente",
"cases.case_type": "Case Type" / "Tipo de Caso",
"cases.status": "Status" / "Estado",
"cases.priority": "Priority" / "Prioridad",
"cases.progress": "Progress" / "Progreso",
"cases.description": "Description" / "Descripcion",
"cases.client_info": "Client Information" / "Informacion del Cliente",
"cases.view_client": "View Client" / "Ver Cliente",
"cases.important_dates": "Important Dates" / "Fechas Importantes",
"cases.hearing_date": "Hearing Date" / "Fecha de Audiencia",
"cases.fda_deadline": "FDA Deadline" / "Fecha Limite FDA",
"cases.brown_sheet_date": "Brown Sheet Date" / "Fecha Brown Sheet",
"cases.evidence_deadline": "Evidence Deadline" / "Fecha Limite Evidencia",
"cases.days_until_hearing": "{days} days until hearing" / "{days} dias para audiencia",
"cases.past_due": "Past due" / "Vencido",
"cases.assignment": "Assignment" / "Asignacion",
"cases.assigned_to": "Assigned To" / "Asignado a",
"cases.unassigned": "Unassigned" / "Sin asignar",
"cases.metadata": "Metadata" / "Metadata",
"cases.created": "Created" / "Creado",
"cases.updated": "Updated" / "Actualizado"
```

#### Vista Edit/Create

```json
"cases.create_case": "New Case" / "Nuevo Expediente",
"cases.edit_case": "Edit Case" / "Editar Expediente",
"cases.main_information": "Main Information" / "Informacion Principal",
"cases.select_client": "Select Client" / "Seleccionar Cliente",
"cases.select_type": "Select Case Type" / "Seleccionar Tipo",
"cases.select_status": "Select Status" / "Seleccionar Estado",
"cases.select_priority": "Select Priority" / "Seleccionar Prioridad",
"cases.select_user": "Select User" / "Seleccionar Usuario",
"cases.language": "Language" / "Idioma",
"cases.dates_section": "Important Dates" / "Fechas Importantes",
"cases.notes_section": "Notes & Archive" / "Notas y Archivo",
"cases.archive_box_number": "Archive Box Number" / "Numero de Caja",
"cases.closure_notes": "Closure Notes" / "Notas de Cierre",
"cases.save": "Save" / "Guardar",
"cases.saving": "Saving..." / "Guardando...",
"cases.cancel": "Cancel" / "Cancelar",
"cases.back": "Back" / "Volver",
"cases.client_required": "Client is required" / "Cliente es requerido",
"cases.type_required": "Case type is required" / "Tipo de caso es requerido"
```

#### Acciones y Confirmaciones

```json
"cases.view": "View" / "Ver",
"cases.edit": "Edit" / "Editar",
"cases.delete": "Delete" / "Eliminar",
"cases.assign": "Assign" / "Asignar",
"cases.close_case": "Close Case" / "Cerrar Caso",
"cases.reopen_case": "Reopen Case" / "Reabrir Caso",
"cases.confirm_delete": "Delete case {number}?" / "Eliminar expediente {number}?",
"cases.delete_warning": "This action cannot be undone." / "Esta accion no se puede deshacer.",
"cases.yes_delete": "Yes, delete" / "Si, eliminar",
"cases.deleted_successfully": "Case deleted successfully" / "Expediente eliminado",
"cases.created_successfully": "Case created successfully" / "Expediente creado",
"cases.updated_successfully": "Case updated successfully" / "Expediente actualizado",
"cases.assigned_successfully": "Case assigned successfully" / "Expediente asignado",
"cases.failed_to_load": "Failed to load cases" / "Error al cargar expedientes",
"cases.not_found": "Case not found" / "Expediente no encontrado",
"cases.back_to_list": "Back to Cases" / "Volver a Expedientes"
```

#### Asignacion Modal

```json
"cases.assign_case": "Assign Case" / "Asignar Expediente",
"cases.select_assignee": "Select a user to assign this case" / "Selecciona un usuario para asignar",
"cases.current_assignee": "Currently assigned" / "Actualmente asignado",
"cases.no_users_available": "No users available" / "Sin usuarios disponibles",
"cases.confirm_assign": "Assign" / "Asignar"
```

#### Timeline

```json
"cases.timeline_empty": "No activity recorded" / "Sin actividad registrada",
"cases.activity_created": "Case created" / "Expediente creado",
"cases.activity_updated": "Case updated" / "Expediente actualizado",
"cases.activity_assigned": "Case assigned" / "Expediente asignado",
"cases.activity_status_changed": "Status changed" / "Estado cambiado",
"cases.field_changed": "{field} changed from {old} to {new}" / "{field} cambio de {old} a {new}"
```

#### Statistics

```json
"cases.statistics": "Statistics" / "Estadisticas",
"cases.total_cases": "Total Cases" / "Total Expedientes",
"cases.active_cases": "Active" / "Activos",
"cases.urgent_cases": "Urgent" / "Urgentes",
"cases.upcoming_hearings": "Upcoming Hearings" / "Audiencias Proximas"
```

### Criterios de Aceptacion
- [ ] ~80 keys agregadas a en.json
- [ ] ~80 keys agregadas a es.json
- [ ] Todas las keys usadas en componentes existen
- [ ] Formato correcto de variables {variable}

---

## Resumen de Archivos

### Archivos Nuevos: 12

```
resources/js/src/
├── types/
│   └── case.ts                              # Type definitions
├── services/
│   └── caseService.ts                       # API service
├── stores/
│   └── case.ts                              # Pinia store
└── views/cases/
    ├── list.vue                             # List view
    ├── show.vue                             # Show view
    ├── edit.vue                             # Edit view
    ├── create.vue                           # Create view (puede ser wrapper de edit)
    └── components/
        ├── CaseCard.vue                     # Card component
        ├── CaseFilters.vue                  # Filters component
        ├── CaseHeader.vue                   # Header component
        ├── CaseTabs.vue                     # Tabs navigation
        ├── CaseTimeline.vue                 # Timeline component
        ├── CasePipeline.vue                 # Progress pipeline
        └── CaseAssign.vue                   # Assign modal
```

### Archivos a Modificar: 4

```
resources/js/src/router/index.ts             # Agregar rutas
resources/js/src/components/layout/Sidebar.vue  # Agregar menu item
resources/js/src/locales/en.json             # Traducciones EN
resources/js/src/locales/es.json             # Traducciones ES
```

---

## Orden de Implementacion Recomendado

1. **types/case.ts** - Base para todo lo demas
2. **services/caseService.ts** - Comunicacion con API
3. **stores/case.ts** - Gestion de estado
4. **router/index.ts** - Rutas disponibles
5. **Sidebar.vue** - Menu visible
6. **CaseCard.vue, CaseFilters.vue** - Componentes base
7. **list.vue** - Primera vista funcional
8. **CaseHeader.vue, CaseTabs.vue, CaseTimeline.vue, CasePipeline.vue** - Componentes detalle
9. **show.vue** - Vista detalle
10. **CaseAssign.vue** - Modal asignacion
11. **edit.vue, create.vue** - Formularios
12. **en.json, es.json** - Traducciones

---

## Dependencias Externas

No se requieren nuevas dependencias npm. Se utilizan las existentes:
- `@headlessui/vue` - Para modales y transiciones
- `vue3-datatable` - Para tabla con paginacion
- `flatpickr` - Para datepickers
- `tippy.js` - Para tooltips

---

## Notas de Implementacion

1. **Patron de Componentes:** Seguir el patron de `clients/show.vue` para la vista de detalle con tabs.

2. **Patron de Service:** Seguir `companionService.ts` como referencia para metodos simples.

3. **Patron de Store:** Seguir `clientStore.ts` para manejo de paginacion y filtros.

4. **Permisos:** Usar la directiva `v-can` para mostrar/ocultar elementos segun permisos.

5. **Iconos:** Usar los iconos existentes en `components/icon/`. Si se necesita uno nuevo (icon-folder ya existe), verificar primero.

6. **Formateo de Fechas:** Usar el helper `formatDate` de `@/utils/formatters`.

---

## APENDICE A: Estructuras de Template Detalladas

### A.1 Template de list.vue

```vue
<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <a href="javascript:;" class="text-primary hover:underline">{{ $t('sidebar.cases') }}</a>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('cases.list') }}</span>
            </li>
        </ul>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
            <!-- Active Cases -->
            <div class="panel bg-gradient-to-r from-green-500 to-green-400">
                <div class="flex justify-between">
                    <div class="text-white">
                        <p class="text-lg font-semibold">{{ caseStore.statistics?.by_status.active ?? 0 }}</p>
                        <p class="text-sm opacity-80">{{ $t('cases.active_cases') }}</p>
                    </div>
                    <icon-folder class="w-10 h-10 text-white opacity-50" />
                </div>
            </div>
            <!-- Urgent Cases -->
            <div class="panel bg-gradient-to-r from-red-500 to-red-400">
                <div class="flex justify-between">
                    <div class="text-white">
                        <p class="text-lg font-semibold">{{ caseStore.statistics?.by_priority.urgent ?? 0 }}</p>
                        <p class="text-sm opacity-80">{{ $t('cases.urgent_cases') }}</p>
                    </div>
                    <icon-bell class="w-10 h-10 text-white opacity-50" />
                </div>
            </div>
            <!-- Upcoming Hearings -->
            <div class="panel bg-gradient-to-r from-blue-500 to-blue-400">
                <div class="flex justify-between">
                    <div class="text-white">
                        <p class="text-lg font-semibold">{{ caseStore.statistics?.upcoming_hearings ?? 0 }}</p>
                        <p class="text-sm opacity-80">{{ $t('cases.upcoming_hearings') }}</p>
                    </div>
                    <icon-calendar class="w-10 h-10 text-white opacity-50" />
                </div>
            </div>
            <!-- Total Cases -->
            <div class="panel bg-gradient-to-r from-gray-500 to-gray-400">
                <div class="flex justify-between">
                    <div class="text-white">
                        <p class="text-lg font-semibold">{{ caseStore.statistics?.total ?? 0 }}</p>
                        <p class="text-sm opacity-80">{{ $t('cases.total_cases') }}</p>
                    </div>
                    <icon-archive class="w-10 h-10 text-white opacity-50" />
                </div>
            </div>
        </div>

        <!-- Main Panel -->
        <div class="panel">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
                <h5 class="font-semibold text-lg dark:text-white-light">
                    {{ $t('cases.case_management') }}
                </h5>
                <router-link
                    v-can="'cases.create'"
                    to="/cases/create"
                    class="btn btn-primary gap-2"
                >
                    <icon-plus class="w-5 h-5" />
                    {{ $t('cases.add_case') }}
                </router-link>
            </div>

            <!-- Filters Component -->
            <CaseFilters
                v-model="filters"
                :case-types="caseStore.caseTypes"
                :is-loading="caseStore.isLoading"
                @search="handleSearch"
                @reset="handleResetFilters"
            />

            <!-- Content Area -->
            <!-- ... DataTable / Cards / Empty States ... -->
        </div>
    </div>
</template>
```

### A.2 Template de show.vue

```vue
<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/cases" class="text-primary hover:underline">
                    {{ $t('sidebar.cases') }}
                </router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ currentCase?.case_number }}</span>
            </li>
        </ul>

        <!-- Loading State -->
        <div v-if="isLoading" class="panel">
            <div class="animate-pulse space-y-4">
                <!-- Skeleton -->
            </div>
        </div>

        <!-- Case Profile -->
        <div v-else-if="currentCase" class="space-y-5">
            <!-- Header Card -->
            <CaseHeader
                :case="currentCase"
                @edit="navigateToEdit"
                @delete="confirmDelete"
                @assign="openAssignModal"
            />

            <!-- Tabs Panel -->
            <div class="panel p-0">
                <CaseTabs
                    v-model:active-tab="activeTab"
                    :tabs="tabs"
                />

                <!-- Tab Content -->
                <div class="p-5">
                    <!-- Information Tab -->
                    <div v-if="activeTab === 'info'">
                        <!-- Sections: General, Client, Dates, etc. -->
                    </div>

                    <!-- Timeline Tab -->
                    <div v-else-if="activeTab === 'timeline'">
                        <CaseTimeline
                            :activities="timeline"
                            :is-loading="isLoadingTimeline"
                        />
                    </div>

                    <!-- Documents Tab (Future) -->
                    <div v-else-if="activeTab === 'documents'">
                        <div class="text-center py-10 text-gray-500">
                            {{ $t('cases.documents_coming_soon') }}
                        </div>
                    </div>

                    <!-- Tasks Tab (Future) -->
                    <div v-else-if="activeTab === 'tasks'">
                        <div class="text-center py-10 text-gray-500">
                            {{ $t('cases.tasks_coming_soon') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Not Found -->
        <div v-else class="panel text-center py-10">
            <icon-folder class="w-16 h-16 mx-auto text-gray-300 mb-4" />
            <h3 class="text-lg font-semibold text-gray-600 mb-2">
                {{ $t('cases.not_found') }}
            </h3>
            <router-link to="/cases" class="btn btn-primary mt-4">
                {{ $t('cases.back_to_list') }}
            </router-link>
        </div>

        <!-- Assign Modal -->
        <CaseAssign
            :is-open="showAssignModal"
            :case="currentCase"
            :users="availableUsers"
            @close="closeAssignModal"
            @assign="handleAssign"
        />
    </div>
</template>
```

### A.3 Template de CaseCard.vue

```vue
<template>
    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow">
        <!-- Header: Case Number + Badges -->
        <div class="flex items-start justify-between mb-3">
            <div>
                <router-link
                    :to="`/cases/${caseItem.id}`"
                    class="text-primary font-semibold hover:underline"
                >
                    {{ caseItem.case_number }}
                </router-link>
                <p class="text-sm text-gray-500">{{ caseItem.case_type?.name }}</p>
            </div>
            <div class="flex gap-1">
                <span class="badge" :class="statusBadgeClass">
                    {{ $t(`cases.${caseItem.status}`) }}
                </span>
                <span class="badge" :class="priorityBadgeClass">
                    {{ $t(`cases.${caseItem.priority}`) }}
                </span>
            </div>
        </div>

        <!-- Client Info -->
        <div v-if="showClient && caseItem.client" class="mb-3">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center">
                    <span class="text-xs font-semibold text-primary">
                        {{ getInitials(caseItem.client.first_name, caseItem.client.last_name) }}
                    </span>
                </div>
                <div>
                    <p class="font-medium text-sm">{{ caseItem.client.full_name }}</p>
                    <p class="text-xs text-gray-500">{{ caseItem.client.email }}</p>
                </div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="mb-3">
            <div class="flex justify-between text-xs mb-1">
                <span>{{ $t('cases.progress') }}</span>
                <span>{{ caseItem.progress }}%</span>
            </div>
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div
                    class="h-2 rounded-full transition-all"
                    :class="progressBarClass"
                    :style="{ width: `${caseItem.progress}%` }"
                ></div>
            </div>
        </div>

        <!-- Hearing Date -->
        <div v-if="caseItem.hearing_date" class="mb-3 text-sm">
            <div class="flex items-center gap-2">
                <icon-calendar class="w-4 h-4 text-gray-400" />
                <span>{{ formatDate(caseItem.hearing_date) }}</span>
                <span
                    v-if="caseItem.days_until_hearing !== null"
                    class="text-xs"
                    :class="daysUntilClass"
                >
                    ({{ daysUntilText }})
                </span>
            </div>
        </div>

        <!-- Assigned User -->
        <div class="mb-3 text-sm">
            <div class="flex items-center gap-2">
                <icon-user class="w-4 h-4 text-gray-400" />
                <span v-if="caseItem.assigned_user">
                    {{ caseItem.assigned_user.name }}
                </span>
                <span v-else class="text-gray-400 italic">
                    {{ $t('cases.unassigned') }}
                </span>
            </div>
        </div>

        <!-- Actions -->
        <div v-if="showActions" class="flex items-center gap-2 pt-3 border-t border-gray-100 dark:border-gray-700">
            <tippy content="View">
                <button
                    class="btn btn-sm btn-outline-info p-1.5"
                    @click="$emit('view', caseItem.id)"
                >
                    <icon-eye class="w-4 h-4" />
                </button>
            </tippy>
            <tippy v-can="'cases.update'" content="Edit">
                <button
                    class="btn btn-sm btn-outline-primary p-1.5"
                    @click="$emit('edit', caseItem.id)"
                >
                    <icon-pencil class="w-4 h-4" />
                </button>
            </tippy>
            <tippy v-can="'cases.assign'" content="Assign">
                <button
                    class="btn btn-sm btn-outline-warning p-1.5"
                    @click="$emit('assign', caseItem)"
                >
                    <icon-user-plus class="w-4 h-4" />
                </button>
            </tippy>
            <tippy v-can="'cases.delete'" content="Delete">
                <button
                    class="btn btn-sm btn-outline-danger p-1.5"
                    @click="$emit('delete', caseItem)"
                >
                    <icon-trash class="w-4 h-4" />
                </button>
            </tippy>
        </div>
    </div>
</template>
```

---

## APENDICE B: Helpers y Utilidades

### B.1 Funciones Helper para Cases

Agregar a `/resources/js/src/utils/caseHelpers.ts`:

```typescript
import type { CaseStatus, CasePriority } from '@/types/case';

/**
 * Get badge class for case status
 */
export function getStatusBadgeClass(status: CaseStatus): string {
    const classes: Record<CaseStatus, string> = {
        active: 'badge-outline-success',
        inactive: 'badge-outline-warning',
        archived: 'badge-outline-secondary',
        closed: 'badge-outline-dark',
    };
    return classes[status] || 'badge-outline-primary';
}

/**
 * Get badge class for case priority
 */
export function getPriorityBadgeClass(priority: CasePriority): string {
    const classes: Record<CasePriority, string> = {
        urgent: 'badge-outline-danger',
        high: 'badge-outline-warning',
        medium: 'badge-outline-info',
        low: 'badge-outline-secondary',
    };
    return classes[priority] || 'badge-outline-primary';
}

/**
 * Get progress bar color class based on percentage
 */
export function getProgressBarClass(progress: number): string {
    if (progress >= 75) return 'bg-success';
    if (progress >= 50) return 'bg-info';
    if (progress >= 25) return 'bg-warning';
    return 'bg-danger';
}

/**
 * Format days until hearing
 */
export function formatDaysUntil(days: number | null): { text: string; class: string } {
    if (days === null) return { text: '', class: '' };

    if (days < 0) {
        return {
            text: `${Math.abs(days)} days overdue`,
            class: 'text-danger',
        };
    }
    if (days === 0) {
        return {
            text: 'Today',
            class: 'text-danger font-bold',
        };
    }
    if (days <= 7) {
        return {
            text: `${days} days`,
            class: 'text-warning',
        };
    }
    if (days <= 30) {
        return {
            text: `${days} days`,
            class: 'text-info',
        };
    }
    return {
        text: `${days} days`,
        class: 'text-gray-500',
    };
}

/**
 * Get initials from name
 */
export function getInitials(firstName: string, lastName: string): string {
    return ((firstName?.[0] || '') + (lastName?.[0] || '')).toUpperCase();
}
```

---

## APENDICE C: Composables Especificos

### C.1 useCaseFilters Composable

Crear `/resources/js/src/composables/useCaseFilters.ts`:

```typescript
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import type { CaseFilters, CaseStatus, CasePriority } from '@/types/case';

export function useCaseFilters() {
    const route = useRoute();
    const router = useRouter();

    // Initialize filters from URL query params
    const filters = ref<CaseFilters>({
        search: (route.query.search as string) || '',
        status: (route.query.status as CaseStatus) || undefined,
        priority: (route.query.priority as CasePriority) || undefined,
        case_type_id: route.query.case_type_id
            ? parseInt(route.query.case_type_id as string)
            : undefined,
        assigned_to: route.query.assigned_to
            ? parseInt(route.query.assigned_to as string)
            : undefined,
        sort_by: (route.query.sort_by as string) || 'created_at',
        sort_direction: (route.query.sort_direction as 'asc' | 'desc') || 'desc',
        per_page: route.query.per_page
            ? parseInt(route.query.per_page as string)
            : 15,
        page: route.query.page
            ? parseInt(route.query.page as string)
            : 1,
    });

    // Check if any filter is active
    const hasActiveFilters = computed(() => {
        return !!(
            filters.value.search ||
            filters.value.status ||
            filters.value.priority ||
            filters.value.case_type_id ||
            filters.value.assigned_to
        );
    });

    // Sync filters to URL
    const syncToUrl = () => {
        const query: Record<string, string> = {};

        if (filters.value.search) query.search = filters.value.search;
        if (filters.value.status) query.status = filters.value.status;
        if (filters.value.priority) query.priority = filters.value.priority;
        if (filters.value.case_type_id) query.case_type_id = filters.value.case_type_id.toString();
        if (filters.value.assigned_to) query.assigned_to = filters.value.assigned_to.toString();
        if (filters.value.sort_by !== 'created_at') query.sort_by = filters.value.sort_by;
        if (filters.value.sort_direction !== 'desc') query.sort_direction = filters.value.sort_direction;
        if (filters.value.per_page !== 15) query.per_page = filters.value.per_page.toString();
        if (filters.value.page !== 1) query.page = filters.value.page.toString();

        router.replace({ query });
    };

    // Reset filters
    const resetFilters = () => {
        filters.value = {
            search: '',
            status: undefined,
            priority: undefined,
            case_type_id: undefined,
            assigned_to: undefined,
            sort_by: 'created_at',
            sort_direction: 'desc',
            per_page: 15,
            page: 1,
        };
        syncToUrl();
    };

    return {
        filters,
        hasActiveFilters,
        syncToUrl,
        resetFilters,
    };
}
```

---

## APENDICE D: Diagrama de Flujo de Datos

```
+------------------+     +------------------+     +------------------+
|                  |     |                  |     |                  |
|   Vue Component  |---->|   Pinia Store    |---->|   API Service    |
|   (list.vue)     |     |   (case.ts)      |     |   (caseService)  |
|                  |<----|                  |<----|                  |
+------------------+     +------------------+     +------------------+
        |                        |                        |
        |                        |                        |
        v                        v                        v
+------------------+     +------------------+     +------------------+
|                  |     |                  |     |                  |
|   CaseFilters    |     |   State:         |     |   GET /api/cases |
|   CaseCard       |     |   - cases[]      |     |   POST /api/cases|
|   CaseTabs       |     |   - currentCase  |     |   PUT /api/cases |
|   CaseTimeline   |     |   - filters      |     |   DELETE /api/...|
|                  |     |   - statistics   |     |                  |
+------------------+     +------------------+     +------------------+
```

---

## APENDICE E: Checklist de Implementacion

### Pre-implementacion
- [ ] Backend APIs completadas y testeadas
- [ ] Permisos `cases.*` sembrados en la base de datos
- [ ] Migraciones de `cases` y `case_types` ejecutadas

### Fase 5: Infraestructura Frontend
- [ ] 5.1 Crear `types/case.ts` con todas las interfaces
- [ ] 5.2 Crear `services/caseService.ts` con todos los metodos
- [ ] 5.3 Crear `stores/case.ts` con state, getters y actions

### Fase 6: Vistas y Componentes
- [ ] 6.1.1 Agregar rutas en `router/index.ts`
- [ ] 6.1.2 Agregar menu en `Sidebar.vue`
- [ ] 6.2.1 Crear `CaseCard.vue`
- [ ] 6.2.2 Crear `CaseFilters.vue`
- [ ] 6.2.3 Crear `CaseHeader.vue`
- [ ] 6.2.4 Crear `CaseTabs.vue`
- [ ] 6.2.5 Crear `CaseTimeline.vue`
- [ ] 6.2.6 Crear `CasePipeline.vue`
- [ ] 6.2.7 Crear `CaseAssign.vue`
- [ ] 6.3 Crear `list.vue` con DataTable y filtros
- [ ] 6.4 Crear `show.vue` con tabs
- [ ] 6.5 Crear `edit.vue` y `create.vue`

### Fase 7: Traducciones
- [ ] 7.1.1 Agregar keys a `en.json`
- [ ] 7.1.2 Agregar keys a `es.json`

### Post-implementacion
- [ ] Verificar flujo completo: list -> create -> show -> edit -> delete
- [ ] Verificar filtros y paginacion
- [ ] Verificar responsive design (mobile)
- [ ] Verificar dark mode
- [ ] Build de produccion exitoso (`npm run build`)

---

## APENDICE F: Consideraciones de UX

### F.1 Estados de Carga
- Skeleton loaders para carga inicial
- Spinner inline para actualizaciones
- Deshabilitar botones durante submit

### F.2 Feedback al Usuario
- Toast de exito/error usando `useNotification`
- Confirmacion antes de eliminar
- Indicador de cambios sin guardar

### F.3 Navegacion
- Breadcrumbs en todas las vistas
- Boton "Volver" visible
- Deep linking con query params en filtros

### F.4 Accesibilidad
- Labels para todos los inputs
- Aria-labels en botones de accion
- Focus management en modales
- Soporte para navegacion con teclado

---

**Documento generado por Frontend Specialist Agent**
**Fecha: 2026-02-10**
**Version: 1.1 (con apendices)**
