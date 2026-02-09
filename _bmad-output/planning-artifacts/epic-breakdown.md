# VITE-IT Immigration - Epic Breakdown

**Version:** 1.0
**Date:** 2026-02-08
**Author:** Architecture Team
**Status:** Ready for Implementation

---

## Overview

- **Total Epics:** 17
- **Total User Stories:** 82
- **Estimated Phases:** 6 (per architecture.md)
- **Estimated Duration:** 10-15 weeks
- **PRD Coverage:** 92 Functional Requirements

---

## Epic Summary Table

| Epic ID | Epic Name | Stories | Phase | Priority | Effort (pts) |
|---------|-----------|---------|-------|----------|--------------|
| 1.1 | Multi-Tenant Foundation | 5 | 1 | P0 | 21 |
| 1.2 | Clientes Management | 8 | 1 | P0 | 29 |
| 1.3 | Acompanantes Management | 5 | 1 | P0 | 13 |
| 2.1 | Expedientes Core | 7 | 2 | P0 | 34 |
| 2.2 | Case Wizard | 5 | 2 | P0 | 21 |
| 2.3 | Expediente Lifecycle | 4 | 2 | P1 | 13 |
| 3.1 | Tareas Core | 6 | 3 | P0 | 26 |
| 3.2 | Time Logging | 4 | 3 | P0 | 13 |
| 3.3 | Seguimiento Module | 5 | 3 | P0 | 18 |
| 4.1 | OAuth Infrastructure | 4 | 4 | P0 | 16 |
| 4.2 | OneDrive Integration | 5 | 4 | P0 | 18 |
| 4.3 | Google Drive Integration | 4 | 4 | P0 | 13 |
| 4.4 | Document Management | 5 | 4 | P0 | 18 |
| 5.1 | Agenda Core | 5 | 5 | P0 | 18 |
| 5.2 | Outlook Calendar Sync | 4 | 5 | P0 | 16 |
| 5.3 | Google Calendar Sync | 3 | 5 | P1 | 10 |
| 6.1 | Dashboard & Reporting | 8 | 6 | P0 | 29 |

**Total Story Points:** ~296

---

## Phase 1: Foundation (Weeks 1-3)

### Epic 1.1: Multi-Tenant Foundation

**Goal:** Establish tenant isolation infrastructure for all domain entities
**PRD Coverage:** FR72, FR73, FR73a-d
**Architecture Ref:** Section 2.3, 3.2.1

#### User Stories:

**US-1.1.1: Tenant Model & Migration**
- As a System Administrator, I can create tenant organizations so that each consultancy operates in isolation
- Acceptance Criteria:
  - [ ] Tenants table created with name, slug, settings JSON, OAuth credentials
  - [ ] Tenant model with relationships to users
  - [ ] Unique slug constraint enforced
  - [ ] is_active flag for tenant status
- PRD Refs: FR72, FR73
- UX Ref: N/A (Backend only)
- Estimate: 3 points
- Technical Notes:
  - Backend: Create migration, model, factory, seeder
  - Frontend: N/A

**US-1.1.2: User-Tenant Association**
- As a User, my data is isolated to my organization so that I only see my consultancy's data
- Acceptance Criteria:
  - [ ] tenant_id column added to users table
  - [ ] Foreign key constraint to tenants
  - [ ] Existing users assigned to default tenant
  - [ ] User registration includes tenant assignment
- PRD Refs: FR72, FR73
- UX Ref: N/A (Backend only)
- Estimate: 3 points
- Technical Notes:
  - Backend: Migration to add tenant_id, update User model
  - Frontend: N/A

**US-1.1.3: Tenant Scope Middleware**
- As a Developer, all queries are automatically scoped to current tenant so that data isolation is guaranteed
- Acceptance Criteria:
  - [ ] TenantScope global scope applies tenant_id filter
  - [ ] BelongsToTenant trait auto-assigns tenant_id on create
  - [ ] TenantScope middleware validates tenant context
  - [ ] Super admin can bypass tenant scope
- PRD Refs: FR72, FR73
- UX Ref: N/A (Backend only)
- Estimate: 5 points
- Technical Notes:
  - Backend: Create scope class, trait, middleware
  - Frontend: N/A

**US-1.1.4: Tenant Configuration**
- As an Admin, I can configure tenant branding and business information so that the system reflects our identity
- Acceptance Criteria:
  - [ ] Settings JSON stores logo URL, colors, company info
  - [ ] API endpoint to update tenant settings
  - [ ] Frontend displays tenant branding in header/sidebar
  - [ ] Changes reflect immediately without logout
- PRD Refs: FR73a, FR73b
- UX Ref: Section 2.1 (Sidebar logo area)
- Estimate: 5 points
- Technical Notes:
  - Backend: TenantController with update endpoint
  - Frontend: Inject tenant branding into layout components

**US-1.1.5: Tenant OAuth Credentials**
- As an Admin, I can configure our organization's OAuth credentials so that integrations use our apps
- Acceptance Criteria:
  - [ ] Encrypted storage for MS and Google OAuth credentials
  - [ ] Admin UI to input client ID and secret
  - [ ] Fallback to system-level credentials if tenant not configured
  - [ ] Credential validation on save
- PRD Refs: FR73c, FR73d
- UX Ref: Section 3.7 (Settings pattern)
- Estimate: 5 points
- Technical Notes:
  - Backend: Encrypt credentials with Laravel Crypt
  - Frontend: Settings form with secure input fields

---

### Epic 1.2: Clientes Management

**Goal:** Enable CRUD operations for client management with search and filtering
**PRD Coverage:** FR1-FR9
**Architecture Ref:** Section 3.2.3, 4.1

#### User Stories:

**US-1.2.1: Client List View**
- As a Consultor, I can view a paginated list of all my clients so that I can quickly find and access client records
- Acceptance Criteria:
  - [ ] Display client cards with avatar, name, nationality, contact info
  - [ ] Grid and list view toggle
  - [ ] Pagination with configurable page size
  - [ ] Total client count displayed
  - [ ] Empty state when no clients exist
- PRD Refs: FR2, FR3, FR5
- UX Ref: Section 3.2.1 Lista de Clientes (S3)
- Estimate: 5 points
- Technical Notes:
  - Backend: ClienteController@index with pagination
  - Frontend: ClienteCard.vue, grid layout, Pinia store

**US-1.2.2: Client Search**
- As a Consultor, I can search clients by name, email, phone, or nationality so that I can quickly locate specific clients
- Acceptance Criteria:
  - [ ] Search input with debounced API calls
  - [ ] Search across multiple fields (nombre, apellidos, email, telefono, nacionalidad)
  - [ ] Results update in real-time as user types
  - [ ] Clear search button resets results
- PRD Refs: FR2
- UX Ref: Section 4.1 Filter Bars
- Estimate: 3 points
- Technical Notes:
  - Backend: Search scope on Cliente model
  - Frontend: SearchInput.vue component, useDebounce composable

**US-1.2.3: Client Status Filter**
- As a Consultor, I can filter clients by status so that I can focus on active, prospect, or inactive clients
- Acceptance Criteria:
  - [ ] Dropdown filter for status (prospecto, activo, inactivo, archivado)
  - [ ] Filter badge shows active filter count
  - [ ] Multiple filters combine with AND logic
  - [ ] Clear filters button
- PRD Refs: FR3
- UX Ref: Section 4.1 Filter Bars
- Estimate: 2 points
- Technical Notes:
  - Backend: Filter parameter in repository
  - Frontend: Filter dropdown, useFilters composable

**US-1.2.4: Create Client**
- As a Consultor, I can add a new client with complete profile so that I can track their immigration case
- Acceptance Criteria:
  - [ ] Form with personal info fields (nombre, apellidos, fecha_nacimiento, genero, nacionalidad)
  - [ ] Contact information (email, telefono, telefono_alternativo)
  - [ ] Canada status fields (estatus_canada, fecha_llegada, idioma_preferido)
  - [ ] Validation for required fields with error messages
  - [ ] Success notification on save
- PRD Refs: FR1, FR4
- UX Ref: Section 3.2.3 Formulario Cliente (S3.3)
- Estimate: 5 points
- Technical Notes:
  - Backend: StoreClienteRequest validation, ClienteService@create
  - Frontend: ClienteForm.vue with validation

**US-1.2.5: View Client Profile**
- As a Consultor, I can view complete client profile with all associated data so that I have full context
- Acceptance Criteria:
  - [ ] Header with avatar, name, nationality, status badge
  - [ ] Tabs: Informacion, Acompanantes, Expedientes, Timeline
  - [ ] Display all client fields organized in sections
  - [ ] Show count of expedientes
  - [ ] Activity timeline of recent actions
- PRD Refs: FR5
- UX Ref: Section 3.2.2 Perfil Cliente (S3.1)
- Estimate: 5 points
- Technical Notes:
  - Backend: ClienteController@show with eager loading
  - Frontend: Tabbed view using HeadlessUI

**US-1.2.6: Edit Client**
- As a Consultor, I can update client information so that records stay current
- Acceptance Criteria:
  - [ ] Pre-populated form with current values
  - [ ] Same validation as create
  - [ ] Audit log entry on update
  - [ ] Success notification on save
- PRD Refs: FR8
- UX Ref: Section 3.2.3 Formulario Cliente (S3.2)
- Estimate: 3 points
- Technical Notes:
  - Backend: UpdateClienteRequest, ClienteService@update
  - Frontend: Reuse ClienteForm.vue with edit mode

**US-1.2.7: Duplicate Prevention**
- As a System, I prevent duplicate client records so that data integrity is maintained
- Acceptance Criteria:
  - [ ] Check email uniqueness within tenant on create
  - [ ] Check phone uniqueness within tenant on create
  - [ ] Display warning with existing client link if duplicate found
  - [ ] Allow override with confirmation for edge cases
- PRD Refs: FR9
- UX Ref: SweetAlert2 warning pattern
- Estimate: 3 points
- Technical Notes:
  - Backend: Custom validation rule for tenant-scoped uniqueness
  - Frontend: Warning modal with options

**US-1.2.8: Prospect Conversion**
- As a Consultor, I can convert a prospect to active client so that I can track their progression
- Acceptance Criteria:
  - [ ] Convert button visible only for prospects
  - [ ] Confirmation dialog before conversion
  - [ ] Status updates to 'activo' with timestamp
  - [ ] Activity log entry created
- PRD Refs: FR4
- UX Ref: Button in client profile header
- Estimate: 3 points
- Technical Notes:
  - Backend: ClienteController@convertToActive
  - Frontend: Action button with confirmation

---

### Epic 1.3: Acompanantes Management

**Goal:** Manage client companions/dependents linked to cases
**PRD Coverage:** FR6, FR7
**Architecture Ref:** Section 3.2.4

#### User Stories:

**US-1.3.1: List Client Companions**
- As a Consultor, I can view all companions linked to a client so that I see the full family unit
- Acceptance Criteria:
  - [ ] Table view within client profile Acompanantes tab
  - [ ] Columns: nombre, relacion, fecha_nacimiento, nacionalidad
  - [ ] Actions: edit, delete
  - [ ] Empty state when no companions
- PRD Refs: FR6
- UX Ref: Section 3.2.2 Tab: Acompanantes
- Estimate: 3 points
- Technical Notes:
  - Backend: Eager load acompanantes on cliente
  - Frontend: Table component within profile tab

**US-1.3.2: Add Companion**
- As a Consultor, I can add a companion to a client so that family members are tracked
- Acceptance Criteria:
  - [ ] Modal form with companion fields
  - [ ] Relationship type dropdown (conyuge, hijo, padre, madre, hermano, otro)
  - [ ] "Otro" shows custom text field
  - [ ] Passport info optional fields
  - [ ] Success notification on save
- PRD Refs: FR6, FR7
- UX Ref: Section 3.2.2 [+ Agregar Acompanante] button
- Estimate: 3 points
- Technical Notes:
  - Backend: AcompananteController@store nested under cliente
  - Frontend: AcompananteForm.vue modal

**US-1.3.3: Edit Companion**
- As a Consultor, I can update companion information so that records stay current
- Acceptance Criteria:
  - [ ] Edit action opens pre-populated modal
  - [ ] Same validation as create
  - [ ] Audit log entry on update
- PRD Refs: FR7
- UX Ref: Edit icon in table row
- Estimate: 2 points
- Technical Notes:
  - Backend: AcompananteController@update
  - Frontend: Reuse AcompananteForm.vue in edit mode

**US-1.3.4: Delete Companion**
- As a Consultor, I can remove a companion so that incorrect entries are corrected
- Acceptance Criteria:
  - [ ] Delete confirmation dialog
  - [ ] Soft delete with deleted_at timestamp
  - [ ] Warning if companion linked to active expedientes
  - [ ] Success notification on delete
- PRD Refs: FR7
- UX Ref: Delete icon with SweetAlert confirmation
- Estimate: 2 points
- Technical Notes:
  - Backend: Soft delete, check expediente links
  - Frontend: Confirmation modal

**US-1.3.5: Companion Relationship Types**
- As a Consultor, I can specify the exact relationship type so that applications are accurate
- Acceptance Criteria:
  - [ ] Predefined types: conyuge, hijo, padre, madre, hermano
  - [ ] "Otro" option with custom text field
  - [ ] Display relationship label in Spanish
- PRD Refs: FR7
- UX Ref: Dropdown in companion form
- Estimate: 3 points
- Technical Notes:
  - Backend: Enum for parentesco, parentesco_otro field
  - Frontend: Select with conditional text input

---

## Phase 2: Case Management (Weeks 3-5)

### Epic 2.1: Expedientes Core

**Goal:** Implement core case management with CRUD operations
**PRD Coverage:** FR10-FR15, FR17-FR21
**Architecture Ref:** Section 3.2.5, 4.2

#### User Stories:

**US-2.1.1: Expediente List View**
- As a Consultor, I can view all my cases in a list so that I can manage my caseload
- Acceptance Criteria:
  - [ ] Card list with codigo, tipo_caso, cliente, estado, prioridad
  - [ ] Progress bar showing case completion percentage
  - [ ] Key dates (audiencia, objetivo) displayed
  - [ ] Responsable avatar and name
  - [ ] Pagination with filters
- PRD Refs: FR18, FR19
- UX Ref: Section 3.3.1 Lista de Expedientes (S4)
- Estimate: 5 points
- Technical Notes:
  - Backend: ExpedienteController@index with filters
  - Frontend: ExpedienteCard.vue, grid layout

**US-2.1.2: Expediente Filters**
- As a Consultor, I can filter cases by multiple criteria so that I focus on relevant cases
- Acceptance Criteria:
  - [ ] Filter by estado (borrador, activo, pausado, completado, cancelado)
  - [ ] Filter by tipo_caso
  - [ ] Filter by prioridad (baja, media, alta, urgente)
  - [ ] Filter by responsable (consultor/asignado)
  - [ ] Active filter chips with remove option
- PRD Refs: FR18
- UX Ref: Section 4.1 Filter Bars
- Estimate: 3 points
- Technical Notes:
  - Backend: Multiple filter scopes on repository
  - Frontend: FilterBar component

**US-2.1.3: View Expediente Detail**
- As a Consultor, I can view complete case details so that I have full case context
- Acceptance Criteria:
  - [ ] Header with codigo, tipo, cliente, estado, prioridad badges
  - [ ] Tabs: Estructura, Historia, Documentos, Tareas, Estado de Cuenta, IRCC Info
  - [ ] Progress bar with current etapa highlighted
  - [ ] Responsable information
- PRD Refs: FR18
- UX Ref: Section 3.3.3 Detalle de Expediente (S4.3)
- Estimate: 5 points
- Technical Notes:
  - Backend: ExpedienteController@show with eager loading
  - Frontend: ExpedienteDetailTabs.vue

**US-2.1.4: Case Timeline**
- As a Consultor, I can view chronological case history so that I understand case progression
- Acceptance Criteria:
  - [ ] Timeline showing all case actions
  - [ ] Entry shows: date, user, action description
  - [ ] Filter by action type
  - [ ] Automatic entries from system events
- PRD Refs: FR18, FR21
- UX Ref: Section 3.3.3 Tab: Historia
- Estimate: 5 points
- Technical Notes:
  - Backend: Activity log integration, dedicated timeline endpoint
  - Frontend: ExpedienteTimeline.vue component

**US-2.1.5: Edit Expediente**
- As a Consultor, I can update case information so that case details stay current
- Acceptance Criteria:
  - [ ] Editable fields: tipo_caso, prioridad, notas, fecha_objetivo
  - [ ] IRCC reference numbers editable
  - [ ] Validation for required fields
  - [ ] Audit log entry on update
- PRD Refs: FR17
- UX Ref: Section 3.3 edit button
- Estimate: 3 points
- Technical Notes:
  - Backend: ExpedienteController@update
  - Frontend: ExpedienteForm.vue

**US-2.1.6: Expediente Stage Pipeline**
- As a Consultor, I can view case progress through stages so that I track advancement
- Acceptance Criteria:
  - [ ] Visual pipeline showing all etapas for caso type
  - [ ] Current etapa highlighted
  - [ ] Completed stages marked with checkmark
  - [ ] Click to view stage details
- PRD Refs: FR15
- UX Ref: Section 3.3.3 progress visualization
- Estimate: 5 points
- Technical Notes:
  - Backend: Load tipos_caso configuration
  - Frontend: EtapaPipeline.vue component

**US-2.1.7: Assign Case**
- As a Consultor, I can assign a case to team members so that work is distributed
- Acceptance Criteria:
  - [ ] Assign to consultor (primary owner)
  - [ ] Assign to asignado (support staff)
  - [ ] Only show users within same tenant
  - [ ] Notification sent to assigned user
- PRD Refs: FR14
- UX Ref: Assign dropdown in expediente detail
- Estimate: 3 points
- Technical Notes:
  - Backend: ExpedienteController@asignar
  - Frontend: User select dropdown

**US-2.1.8: Auto-Logged Modifications**
- As a System, all case modifications are automatically logged so that audit trail exists
- Acceptance Criteria:
  - [ ] Every update creates activity log entry
  - [ ] Log includes: user, timestamp, old values, new values
  - [ ] Log visible in Historia tab
  - [ ] Cannot be deleted or modified
- PRD Refs: FR21
- UX Ref: Timeline in Historia tab
- Estimate: 5 points
- Technical Notes:
  - Backend: Spatie Activitylog on Expediente model
  - Frontend: Timeline display

---

### Epic 2.2: Case Wizard

**Goal:** Implement guided case creation wizard
**PRD Coverage:** FR10-FR13, FR15a-c
**Architecture Ref:** Section 4.2 (ExpedienteWizardService)

#### User Stories:

**US-2.2.1: Wizard Step 1 - Case Type**
- As a Consultor, I can select the case type so that appropriate workflow is applied
- Acceptance Criteria:
  - [ ] Visual grid of case type cards by category
  - [ ] Categories: Residencia Temporal, Residencia Permanente, Humanitario
  - [ ] Selected type highlighted
  - [ ] Tipo description shown on selection
- PRD Refs: FR10, FR11
- UX Ref: Section 3.3.2 PASO 1 (S4.2)
- Estimate: 3 points
- Technical Notes:
  - Backend: tipos_caso configuration endpoint
  - Frontend: Step1TipoCaso.vue

**US-2.2.2: Wizard Step 2 - Client Selection**
- As a Consultor, I can select or create the client for this case so that case is linked properly
- Acceptance Criteria:
  - [ ] Search existing clients with autocomplete
  - [ ] Option to create new client inline
  - [ ] Selected client shows summary card
  - [ ] Validation that client is selected
- PRD Refs: FR12
- UX Ref: Section 3.3.2 PASO 4
- Estimate: 5 points
- Technical Notes:
  - Backend: Client search endpoint
  - Frontend: Step2Cliente.vue with ClienteSearch.vue

**US-2.2.3: Wizard Step 3 - Companions**
- As a Consultor, I can select companions to include in case so that family applications are complete
- Acceptance Criteria:
  - [ ] List client's existing companions with checkboxes
  - [ ] Option to add new companion inline
  - [ ] Show companion role in case
  - [ ] Can proceed without companions
- PRD Refs: FR13
- UX Ref: Section 3.3.2 companion selection
- Estimate: 3 points
- Technical Notes:
  - Backend: Load client companions
  - Frontend: Step3Acompanantes.vue

**US-2.2.4: Wizard Step 4 - Case Details**
- As a Consultor, I can configure case settings so that workflow is customized
- Acceptance Criteria:
  - [ ] Assign consultor (default: current user)
  - [ ] Assign asignado (support staff)
  - [ ] Set prioridad
  - [ ] Set fecha_inicio and fecha_objetivo
  - [ ] Add initial notas
- PRD Refs: FR14, FR16
- UX Ref: Section 3.3.2 PASO 5
- Estimate: 3 points
- Technical Notes:
  - Backend: Validation rules
  - Frontend: Step4Detalles.vue

**US-2.2.5: Wizard Step 5 - Summary & Create**
- As a Consultor, I can review and confirm case creation so that I catch any errors
- Acceptance Criteria:
  - [ ] Summary of all selections
  - [ ] Edit links to go back to specific steps
  - [ ] Create button triggers case creation
  - [ ] Auto-generated tasks shown (from template)
  - [ ] Auto-created folder structure shown
  - [ ] Success redirects to expediente detail
- PRD Refs: FR15a, FR15b, FR15c
- UX Ref: Section 3.3.2 PASO 6
- Estimate: 7 points
- Technical Notes:
  - Backend: ExpedienteWizardService handles transaction
  - Frontend: Step5Resumen.vue

---

### Epic 2.3: Expediente Lifecycle

**Goal:** Implement case stage management and status transitions
**PRD Coverage:** FR15, FR15a-c, FR20
**Architecture Ref:** Section 3.2.5

#### User Stories:

**US-2.3.1: Update Case Stage**
- As a Consultor, I can advance case to next stage so that progress is tracked
- Acceptance Criteria:
  - [ ] Click on next stage in pipeline to advance
  - [ ] Confirmation dialog with stage description
  - [ ] Validation that prerequisites are met
  - [ ] Activity log entry created
- PRD Refs: FR15
- UX Ref: EtapaPipeline click interaction
- Estimate: 3 points
- Technical Notes:
  - Backend: ExpedienteController@updateEtapa
  - Frontend: Stage click handler with modal

**US-2.3.2: Case Status Transitions**
- As a Consultor, I can change case status so that case lifecycle is managed
- Acceptance Criteria:
  - [ ] Status dropdown in expediente detail
  - [ ] Valid transitions enforced (e.g., borrador->activo)
  - [ ] Completion requires all stages complete
  - [ ] Cancelled requires reason note
- PRD Refs: FR15, FR20
- UX Ref: Status badge dropdown
- Estimate: 3 points
- Technical Notes:
  - Backend: State machine validation
  - Frontend: Status select with confirmation

**US-2.3.3: Support Staff Case Access**
- As Support Staff, I can view and update assigned cases so that I can do my work
- Acceptance Criteria:
  - [ ] My Cases view shows only assigned expedientes
  - [ ] Can update notes, upload documents
  - [ ] Cannot change consultor assignment
  - [ ] Cannot delete cases
- PRD Refs: FR19, FR20
- UX Ref: Role-based view filtering
- Estimate: 3 points
- Technical Notes:
  - Backend: ExpedientePolicy for role-based access
  - Frontend: Conditional UI elements

**US-2.3.4: Auto-Generated Tasks**
- As a System, task templates are applied when case is created so that workflows are standardized
- Acceptance Criteria:
  - [ ] Tasks created from tipo_caso template
  - [ ] Tasks unassigned (for delegation)
  - [ ] Task priorities set from template
  - [ ] Link to expediente established
- PRD Refs: FR15a, FR15c
- UX Ref: Tasks tab in expediente detail
- Estimate: 4 points
- Technical Notes:
  - Backend: TareaService@createFromTemplate
  - Frontend: Display in wizard summary

---

## Phase 3: Tasks & Follow-up (Weeks 5-7)

### Epic 3.1: Tareas Core

**Goal:** Implement task management with priorities and assignments
**PRD Coverage:** FR22-FR31
**Architecture Ref:** Section 3.2.7, 4.1

#### User Stories:

**US-3.1.1: Task List View**
- As a User, I can view tasks in a list with filters so that I can manage my work
- Acceptance Criteria:
  - [ ] Tabs: Mis Tareas, Todas (if permission), Asignadas por mi
  - [ ] Table/card view with checkbox, titulo, expediente, responsable, estado
  - [ ] Priority badge with color coding
  - [ ] Due date with overdue highlighting
- PRD Refs: FR25, FR26
- UX Ref: Section 3.4 Tareas Module (S7)
- Estimate: 5 points
- Technical Notes:
  - Backend: TareaController@index with role-based filtering
  - Frontend: TareaList.vue with tabs

**US-3.1.2: Task Filters**
- As a User, I can filter tasks by multiple criteria so that I focus on relevant work
- Acceptance Criteria:
  - [ ] Filter by estado (pendiente, en_progreso, completada, cancelada)
  - [ ] Filter by prioridad
  - [ ] Filter by fecha_vencimiento (vencidas, hoy, esta semana)
  - [ ] Filter by expediente
  - [ ] Filter by asignado/consultor
  - [ ] Filter by creation date range
- PRD Refs: FR29, FR30, FR30a-d
- UX Ref: Section 4.1 Filter Bars
- Estimate: 3 points
- Technical Notes:
  - Backend: Multiple filter scopes
  - Frontend: FilterBar with date range picker

**US-3.1.3: Create Task**
- As a Consultor, I can create tasks linked to cases so that work is tracked
- Acceptance Criteria:
  - [ ] Form with titulo, descripcion
  - [ ] Link to expediente (optional for personal tasks)
  - [ ] Assign to user
  - [ ] Set prioridad and fecha_vencimiento
  - [ ] Success notification
- PRD Refs: FR22, FR23, FR24
- UX Ref: [+ Nueva Tarea] button
- Estimate: 3 points
- Technical Notes:
  - Backend: TareaController@store
  - Frontend: TareaForm.vue modal

**US-3.1.4: Edit Task**
- As a User, I can edit task details so that requirements stay current
- Acceptance Criteria:
  - [ ] Edit all fields except created_by
  - [ ] Support staff can edit assigned tasks (FR24a)
  - [ ] Audit log entry
  - [ ] Validation for required fields
- PRD Refs: FR24a
- UX Ref: Edit icon in task row
- Estimate: 2 points
- Technical Notes:
  - Backend: TareaPolicy for edit permissions
  - Frontend: Reuse TareaForm.vue

**US-3.1.5: Complete Task**
- As a User, I can mark tasks complete so that progress is visible
- Acceptance Criteria:
  - [ ] Checkbox to mark complete
  - [ ] fecha_completada timestamp set
  - [ ] Task moves to completed filter
  - [ ] Visual strikethrough on title
- PRD Refs: FR27
- UX Ref: Checkbox in task card
- Estimate: 2 points
- Technical Notes:
  - Backend: TareaController@complete
  - Frontend: Checkbox handler

**US-3.1.6: Overdue Task Alerts**
- As a User, I see overdue tasks highlighted so that I prioritize urgent work
- Acceptance Criteria:
  - [ ] Overdue tasks highlighted in red
  - [ ] Dashboard widget shows overdue count
  - [ ] Filter for "vencidas" tasks
  - [ ] Sort places overdue tasks first
- PRD Refs: FR31
- UX Ref: Section 3.1 Dashboard (S2)
- Estimate: 3 points
- Technical Notes:
  - Backend: Overdue scope on Tarea model
  - Frontend: Red highlighting, dashboard integration

**US-3.1.7: Task by Client Filter**
- As a User, I can filter tasks by client so that I focus on specific client work
- Acceptance Criteria:
  - [ ] Client dropdown in filter bar
  - [ ] Shows all tasks for all expedientes of selected client
  - [ ] Combines with other filters
- PRD Refs: FR30b
- UX Ref: Filter dropdown
- Estimate: 2 points
- Technical Notes:
  - Backend: Join through expediente to cliente
  - Frontend: Client select in filters

**US-3.1.8: Task Kanban View**
- As a User, I can view tasks in Kanban board so that I visualize workflow
- Acceptance Criteria:
  - [ ] Columns: Pendiente, En Progreso, Completada
  - [ ] Drag-and-drop between columns
  - [ ] Task cards with key info
  - [ ] Filter bar applies to Kanban
- PRD Refs: FR25
- UX Ref: Section 3.4 Kanban variant
- Estimate: 6 points
- Technical Notes:
  - Backend: Same API with different grouping
  - Frontend: TareaKanban.vue with vue-draggable-plus

---

### Epic 3.2: Time Logging

**Goal:** Enable time tracking on tasks for billing
**PRD Coverage:** FR28, FR79
**Architecture Ref:** Section 3.2.8

#### User Stories:

**US-3.2.1: Log Time on Task**
- As a User, I can log time spent on a task so that work is tracked for billing
- Acceptance Criteria:
  - [ ] Time log button on task card/row
  - [ ] Modal with: fecha, minutos, descripcion
  - [ ] Running total updates on task
  - [ ] Success notification
- PRD Refs: FR28
- UX Ref: Section 3.4 Time Logging Modal
- Estimate: 3 points
- Technical Notes:
  - Backend: TareaController@logTime, TiempoTarea model
  - Frontend: TimeLogModal.vue

**US-3.2.2: View Task Time Log**
- As a User, I can view all time entries on a task so that I see full effort
- Acceptance Criteria:
  - [ ] Time log list in task detail
  - [ ] Shows: date, user, minutes, description
  - [ ] Total time displayed prominently
  - [ ] Edit/delete own entries
- PRD Refs: FR28
- UX Ref: Expandable section in task detail
- Estimate: 3 points
- Technical Notes:
  - Backend: Eager load tiempo_tareas
  - Frontend: TimeLog list component

**US-3.2.3: Time Summary per Task**
- As a Consultor, I can see estimated vs actual time so that I manage scope
- Acceptance Criteria:
  - [ ] tiempo_estimado_minutos field on task
  - [ ] tiempo_registrado_minutos calculated total
  - [ ] Visual indicator if over estimate
  - [ ] Display as hours:minutes format
- PRD Refs: FR28
- UX Ref: Time display in task card
- Estimate: 2 points
- Technical Notes:
  - Backend: Accessor for formatted time
  - Frontend: Time display component

**US-3.2.4: Time per Case Report**
- As a Consultor, I can view total time logged per case so that I track case effort
- Acceptance Criteria:
  - [ ] Time summary in expediente detail
  - [ ] Breakdown by user
  - [ ] Breakdown by task
  - [ ] Export capability
- PRD Refs: FR79
- UX Ref: Estado de Cuenta tab
- Estimate: 5 points
- Technical Notes:
  - Backend: Aggregate query for time summary
  - Frontend: Time report component

---

### Epic 3.3: Seguimiento Module

**Goal:** Track all client communications and follow-ups
**PRD Coverage:** FR32-FR37
**Architecture Ref:** Section 3.2.9

#### User Stories:

**US-3.3.1: Seguimiento Timeline**
- As a Consultor, I can view communication history for a case so that I have full context
- Acceptance Criteria:
  - [ ] Chronological timeline grouped by date
  - [ ] Entry shows: time, channel icon, user, summary
  - [ ] Direction badge (entrante/saliente)
  - [ ] Expandable to see full content
- PRD Refs: FR32, FR36, FR37
- UX Ref: Section 3.5 Seguimiento Module (S6)
- Estimate: 5 points
- Technical Notes:
  - Backend: SeguimientoController with ordering
  - Frontend: SeguimientoTimeline.vue

**US-3.3.2: Add Seguimiento Entry**
- As a User, I can add a follow-up entry so that communications are documented
- Acceptance Criteria:
  - [ ] Form with: tipo_canal, direccion, resumen, contenido
  - [ ] Channel options: llamada, email, whatsapp, presencial, otro
  - [ ] Optional duration_minutos field
  - [ ] Link to expediente
  - [ ] Auto-timestamp on creation
- PRD Refs: FR32, FR33, FR36
- UX Ref: [+ Nuevo Seguimiento] button
- Estimate: 3 points
- Technical Notes:
  - Backend: SeguimientoController@store
  - Frontend: SeguimientoForm.vue

**US-3.3.3: Channel Categorization**
- As a User, I can categorize follow-up by communication channel so that patterns are visible
- Acceptance Criteria:
  - [ ] Dropdown with channel options
  - [ ] Channel icon displayed in timeline
  - [ ] Filter by channel type
  - [ ] Color coding per channel
- PRD Refs: FR33
- UX Ref: Channel badges in timeline
- Estimate: 2 points
- Technical Notes:
  - Backend: Enum for tipo_canal
  - Frontend: Channel badge component

**US-3.3.4: Search Within Case**
- As a User, I can search follow-up history within a case so that I find specific notes
- Acceptance Criteria:
  - [ ] Search input above timeline
  - [ ] Searches resumen and contenido
  - [ ] Highlights matching text
  - [ ] Results update in real-time
- PRD Refs: FR34
- UX Ref: Search in seguimiento section
- Estimate: 3 points
- Technical Notes:
  - Backend: FULLTEXT search on seguimientos
  - Frontend: Search input with highlighting

**US-3.3.5: Search Across Cases**
- As a Consultor, I can search follow-ups across all cases so that I find past communications
- Acceptance Criteria:
  - [ ] Global seguimiento search page
  - [ ] Results show expediente link
  - [ ] Filter by date range
  - [ ] Filter by channel type
- PRD Refs: FR35
- UX Ref: Seguimiento global view
- Estimate: 5 points
- Technical Notes:
  - Backend: SeguimientoController@search with pagination
  - Frontend: Global search view

---

## Phase 4: Documents & Integration (Weeks 7-10)

### Epic 4.1: OAuth Infrastructure

**Goal:** Implement OAuth authentication for cloud integrations
**PRD Coverage:** FR47-FR52
**Architecture Ref:** Section 6.1, 6.2

#### User Stories:

**US-4.1.1: Microsoft OAuth Connection**
- As a User, I can connect my Microsoft account so that I access OneDrive and Outlook
- Acceptance Criteria:
  - [ ] "Connect Microsoft" button in settings
  - [ ] OAuth flow redirects to Microsoft login
  - [ ] Consent screen shows required scopes
  - [ ] Success returns to app with confirmation
  - [ ] Account email displayed after connection
- PRD Refs: FR47
- UX Ref: Section 3.7 Integration Settings
- Estimate: 5 points
- Technical Notes:
  - Backend: OAuthController@microsoftRedirect/Callback
  - Frontend: Integration settings page

**US-4.1.2: Google OAuth Connection**
- As a User, I can connect my Google account so that I access Google Drive and Calendar
- Acceptance Criteria:
  - [ ] "Connect Google" button in settings
  - [ ] OAuth flow redirects to Google login
  - [ ] Consent screen shows required scopes
  - [ ] Success returns to app with confirmation
  - [ ] Account email displayed after connection
- PRD Refs: FR48
- UX Ref: Section 3.7 Integration Settings
- Estimate: 5 points
- Technical Notes:
  - Backend: OAuthController@googleRedirect/Callback
  - Frontend: Integration settings page

**US-4.1.3: Disconnect Integration**
- As a User, I can disconnect cloud accounts so that I revoke access
- Acceptance Criteria:
  - [ ] Disconnect button for each connected account
  - [ ] Confirmation dialog before disconnect
  - [ ] Tokens deleted from database
  - [ ] Linked documents remain but show "disconnected" status
- PRD Refs: FR49
- UX Ref: Disconnect button in settings
- Estimate: 3 points
- Technical Notes:
  - Backend: OAuthController@disconnect
  - Frontend: Disconnect confirmation

**US-4.1.4: Token Management**
- As a System, OAuth tokens are securely stored and refreshed so that integrations stay connected
- Acceptance Criteria:
  - [ ] Tokens encrypted with AES-256
  - [ ] Automatic refresh before expiry
  - [ ] Failed refresh triggers reconnection prompt
  - [ ] Refresh handled in background job
- PRD Refs: FR50, FR51, FR52
- UX Ref: N/A (System behavior)
- Estimate: 3 points
- Technical Notes:
  - Backend: OAuthService with Crypt, RefreshOAuthToken job
  - Frontend: Connection status indicator

---

### Epic 4.2: OneDrive Integration

**Goal:** Enable browsing and linking files from OneDrive
**PRD Coverage:** FR39, FR41, FR44
**Architecture Ref:** Section 6.3, 6.4

#### User Stories:

**US-4.2.1: Browse OneDrive Files**
- As a User, I can browse my OneDrive files so that I find documents to link
- Acceptance Criteria:
  - [ ] File browser modal opens from document section
  - [ ] Folder navigation with breadcrumbs
  - [ ] Files show: name, type icon, size, modified date
  - [ ] Loading state while fetching
- PRD Refs: FR41
- UX Ref: Section 3.7 File Browser Component
- Estimate: 5 points
- Technical Notes:
  - Backend: OneDriveController@browse using Graph API
  - Frontend: OneDriveBrowser.vue

**US-4.2.2: Navigate Folders**
- As a User, I can navigate through OneDrive folders so that I find nested files
- Acceptance Criteria:
  - [ ] Click folder to navigate into
  - [ ] Breadcrumb trail shows current path
  - [ ] Back button returns to parent
  - [ ] Root folder accessible via breadcrumb
- PRD Refs: FR41
- UX Ref: File browser navigation
- Estimate: 3 points
- Technical Notes:
  - Backend: Pass folder ID to list items
  - Frontend: Breadcrumb navigation state

**US-4.2.3: Link OneDrive File to Case**
- As a User, I can link a OneDrive file to a case so that it appears in case documents
- Acceptance Criteria:
  - [ ] Select file(s) in browser
  - [ ] Link button creates documento record
  - [ ] File metadata stored (name, size, type, web_url)
  - [ ] File appears in case Documentos tab
  - [ ] Success notification
- PRD Refs: FR39
- UX Ref: [Vincular] button in file browser
- Estimate: 3 points
- Technical Notes:
  - Backend: OneDriveController@linkFile, DocumentoService
  - Frontend: Multi-select with link action

**US-4.2.4: Open in OneDrive**
- As a User, I can open a linked file in OneDrive so that I view/edit in native app
- Acceptance Criteria:
  - [ ] "Open in OneDrive" button on linked documents
  - [ ] Opens provider_web_url in new tab
  - [ ] Works for all linked file types
- PRD Refs: FR44
- UX Ref: Action button on document card
- Estimate: 2 points
- Technical Notes:
  - Backend: Store web_url on link
  - Frontend: External link button

**US-4.2.5: Handle Disconnection**
- As a User, I see graceful error when OneDrive is disconnected so that I know to reconnect
- Acceptance Criteria:
  - [ ] Error message if not connected
  - [ ] Link to integration settings
  - [ ] Previously linked files show disconnected status
  - [ ] Retry after reconnection
- PRD Refs: FR52
- UX Ref: Error state in file browser
- Estimate: 5 points
- Technical Notes:
  - Backend: Handle API errors gracefully
  - Frontend: Error states, reconnection prompt

---

### Epic 4.3: Google Drive Integration

**Goal:** Enable browsing and linking files from Google Drive
**PRD Coverage:** FR40, FR42, FR44
**Architecture Ref:** Section 6.3

#### User Stories:

**US-4.3.1: Browse Google Drive Files**
- As a User, I can browse my Google Drive files so that I find documents to link
- Acceptance Criteria:
  - [ ] File browser modal with Google Drive tab
  - [ ] Folder navigation with breadcrumbs
  - [ ] Files show: name, type icon, size, modified date
  - [ ] Loading state while fetching
- PRD Refs: FR42
- UX Ref: Section 3.7 File Browser Component
- Estimate: 5 points
- Technical Notes:
  - Backend: GoogleDriveController@browse using Drive API
  - Frontend: GoogleDriveBrowser.vue

**US-4.3.2: Link Google Drive File to Case**
- As a User, I can link a Google Drive file to a case so that it appears in case documents
- Acceptance Criteria:
  - [ ] Select file(s) in browser
  - [ ] Link button creates documento record
  - [ ] File metadata stored (name, size, type, web_url)
  - [ ] Provider set to 'google_drive'
- PRD Refs: FR40
- UX Ref: [Vincular] button in file browser
- Estimate: 3 points
- Technical Notes:
  - Backend: GoogleDriveController@linkFile
  - Frontend: Same pattern as OneDrive

**US-4.3.3: Open in Google Drive**
- As a User, I can open a linked file in Google Drive so that I view/edit in native app
- Acceptance Criteria:
  - [ ] "Open in Google Drive" button on linked documents
  - [ ] Opens web_url in new tab
  - [ ] Works for all linked file types
- PRD Refs: FR44
- UX Ref: Action button on document card
- Estimate: 2 points
- Technical Notes:
  - Backend: Store webViewLink on link
  - Frontend: External link button

**US-4.3.4: Provider Indicator**
- As a User, I can see which cloud provider each document is from so that I understand storage location
- Acceptance Criteria:
  - [ ] Provider icon (OneDrive/GDrive) on document cards
  - [ ] Filter by provider
  - [ ] Clear visual distinction
- PRD Refs: FR43, FR45
- UX Ref: Document card with provider badge
- Estimate: 3 points
- Technical Notes:
  - Backend: provider field on documento
  - Frontend: Provider icon component

---

### Epic 4.4: Document Management

**Goal:** Organize case documents in folder structures
**PRD Coverage:** FR38, FR43, FR45, FR46
**Architecture Ref:** Section 3.2.10, 3.2.11

#### User Stories:

**US-4.4.1: Case Folder Structure**
- As a Consultor, I can create folders within a case so that documents are organized
- Acceptance Criteria:
  - [ ] Folder tree view in Estructura tab
  - [ ] Expandable folders showing contents
  - [ ] Document count per folder
  - [ ] Empty state for new cases
- PRD Refs: FR38
- UX Ref: Section 3.3.3 Tab: Estructura
- Estimate: 5 points
- Technical Notes:
  - Backend: CarpetaController with nested structure
  - Frontend: CarpetaTree.vue component

**US-4.4.2: Create Folder**
- As a User, I can create a new folder so that I organize documents
- Acceptance Criteria:
  - [ ] "+ Nueva Carpeta" button
  - [ ] Name input with validation
  - [ ] Create under current folder (or root)
  - [ ] Folder appears in tree
- PRD Refs: FR38
- UX Ref: Button in folder tree
- Estimate: 3 points
- Technical Notes:
  - Backend: CarpetaController@store
  - Frontend: Folder creation modal

**US-4.4.3: Document List View**
- As a User, I can view all documents linked to a case so that I access needed files
- Acceptance Criteria:
  - [ ] Grid of document cards
  - [ ] Shows: name, type icon, size, provider, date
  - [ ] Filter by folder
  - [ ] Sort options
- PRD Refs: FR43, FR45
- UX Ref: Section 3.3.3 Tab: Documentos
- Estimate: 3 points
- Technical Notes:
  - Backend: DocumentoController@index with filtering
  - Frontend: DocumentoCard.vue grid

**US-4.4.4: Unlink Document**
- As a User, I can unlink a document from a case so that incorrect links are removed
- Acceptance Criteria:
  - [ ] Unlink action on document card
  - [ ] Confirmation dialog
  - [ ] Removes link only (file remains in cloud)
  - [ ] Activity log entry
- PRD Refs: FR46
- UX Ref: Unlink action
- Estimate: 2 points
- Technical Notes:
  - Backend: DocumentoController@unlink (soft delete)
  - Frontend: Confirmation modal

**US-4.4.5: Auto-Create Folders from Template**
- As a System, folder templates are applied when case is created so that structure is consistent
- Acceptance Criteria:
  - [ ] plantilla_carpetas defined in tipos_caso
  - [ ] Folders created automatically on expediente create
  - [ ] Template configurable per case type
  - [ ] Nested folder templates supported
- PRD Refs: FR15b
- UX Ref: Visible in wizard summary
- Estimate: 5 points
- Technical Notes:
  - Backend: CarpetaService@createFromTemplate
  - Frontend: Display in wizard

---

## Phase 5: Calendar & Events (Weeks 10-12)

### Epic 5.1: Agenda Core

**Goal:** Implement in-app calendar with event management
**PRD Coverage:** FR53-FR55, FR60
**Architecture Ref:** Section 3.2.13

#### User Stories:

**US-5.1.1: Calendar View**
- As a User, I can view my calendar in day/week/month view so that I see my schedule
- Acceptance Criteria:
  - [ ] FullCalendar integration
  - [ ] View toggle: month, week, day
  - [ ] Navigation arrows for date range
  - [ ] Today button
  - [ ] Events displayed with color coding
- PRD Refs: FR53
- UX Ref: Section 3.6 Agenda Module (S5)
- Estimate: 5 points
- Technical Notes:
  - Backend: EventoController@calendar with date range
  - Frontend: CalendarView.vue with @fullcalendar/vue3

**US-5.1.2: Create Event**
- As a User, I can create calendar events so that I schedule appointments
- Acceptance Criteria:
  - [ ] Click on date opens event form
  - [ ] Form: titulo, fecha_inicio, fecha_fin, descripcion
  - [ ] Optional link to expediente
  - [ ] Category selection with colors
  - [ ] Reminder minutes option
- PRD Refs: FR54, FR55
- UX Ref: Section 3.6 Event Modal
- Estimate: 3 points
- Technical Notes:
  - Backend: EventoController@store
  - Frontend: EventoForm.vue modal

**US-5.1.3: Edit Event**
- As a User, I can edit events so that schedule changes are reflected
- Acceptance Criteria:
  - [ ] Click event opens edit form
  - [ ] Drag-and-drop to reschedule
  - [ ] Resize to change duration
  - [ ] All fields editable
- PRD Refs: FR60
- UX Ref: Event click handler
- Estimate: 3 points
- Technical Notes:
  - Backend: EventoController@update
  - Frontend: FullCalendar event handlers

**US-5.1.4: Delete Event**
- As a User, I can delete events so that cancelled appointments are removed
- Acceptance Criteria:
  - [ ] Delete button in event modal
  - [ ] Confirmation dialog
  - [ ] Soft delete with deleted_at
  - [ ] Removed from calendar view
- PRD Refs: FR60
- UX Ref: Delete button in event detail
- Estimate: 2 points
- Technical Notes:
  - Backend: EventoController@destroy
  - Frontend: Delete confirmation

**US-5.1.5: Event Categories**
- As a User, I can categorize events by type so that calendar is organized
- Acceptance Criteria:
  - [ ] Categories: Audiencia, Cita Cliente, Reunion Interna, Personal, Plazo
  - [ ] Color coding per category
  - [ ] Filter calendar by category
  - [ ] Category legend displayed
- PRD Refs: FR55
- UX Ref: Section 3.6 Event Categories table
- Estimate: 5 points
- Technical Notes:
  - Backend: Categoria enum or config
  - Frontend: Color legend, filter checkboxes

---

### Epic 5.2: Outlook Calendar Sync

**Goal:** Bidirectional sync with Outlook/Microsoft Calendar
**PRD Coverage:** FR56, FR58, FR59, FR60
**Architecture Ref:** Section 6.5

#### User Stories:

**US-5.2.1: List Outlook Calendars**
- As a User, I can see my Outlook calendars so that I choose which to sync
- Acceptance Criteria:
  - [ ] List of calendars from Outlook
  - [ ] Checkbox to enable sync
  - [ ] Default calendar pre-selected
  - [ ] Save sync preferences
- PRD Refs: FR58
- UX Ref: Integration settings
- Estimate: 3 points
- Technical Notes:
  - Backend: OutlookCalendarController@listCalendars
  - Frontend: Calendar selection in settings

**US-5.2.2: Sync Events from Outlook**
- As a User, synced Outlook events appear in my VITE-IT calendar so that I have unified view
- Acceptance Criteria:
  - [ ] Events pulled from selected Outlook calendar
  - [ ] Events marked with Outlook provider
  - [ ] Display in calendar with distinct icon
  - [ ] Sync runs on schedule
- PRD Refs: FR56, FR59
- UX Ref: Synced events in calendar
- Estimate: 5 points
- Technical Notes:
  - Backend: OutlookCalendarService, SyncCalendarEvent job
  - Frontend: Provider badge on events

**US-5.2.3: Push Events to Outlook**
- As a User, events I create in VITE-IT sync to Outlook so that my calendars match
- Acceptance Criteria:
  - [ ] Option to sync event to Outlook on create
  - [ ] Event created in connected Outlook calendar
  - [ ] provider_event_id stored for updates
  - [ ] Edits sync bidirectionally
- PRD Refs: FR56, FR60
- UX Ref: Sync toggle on event form
- Estimate: 5 points
- Technical Notes:
  - Backend: Create event via Graph API
  - Frontend: Sync checkbox in event form

**US-5.2.4: Conflict Resolution**
- As a System, sync conflicts are resolved predictably so that data is consistent
- Acceptance Criteria:
  - [ ] Last-modified wins for updates
  - [ ] Deleted in either system removes from both
  - [ ] Conflict log for admin review
  - [ ] Manual conflict resolution option
- PRD Refs: NFR-I5
- UX Ref: N/A (System behavior)
- Estimate: 3 points
- Technical Notes:
  - Backend: Conflict detection in sync job
  - Frontend: Conflict notification

---

### Epic 5.3: Google Calendar Sync

**Goal:** Bidirectional sync with Google Calendar
**PRD Coverage:** FR57, FR58, FR59, FR60
**Architecture Ref:** Section 6.5

#### User Stories:

**US-5.3.1: List Google Calendars**
- As a User, I can see my Google calendars so that I choose which to sync
- Acceptance Criteria:
  - [ ] List of calendars from Google
  - [ ] Checkbox to enable sync
  - [ ] Primary calendar pre-selected
- PRD Refs: FR58
- UX Ref: Integration settings
- Estimate: 3 points
- Technical Notes:
  - Backend: GoogleCalendarController@listCalendars
  - Frontend: Calendar selection

**US-5.3.2: Sync Events from Google**
- As a User, synced Google Calendar events appear in VITE-IT so that I have unified view
- Acceptance Criteria:
  - [ ] Events pulled from selected Google calendar
  - [ ] Events marked with Google provider
  - [ ] Sync runs on schedule
- PRD Refs: FR57, FR59
- UX Ref: Synced events in calendar
- Estimate: 4 points
- Technical Notes:
  - Backend: GoogleCalendarService
  - Frontend: Provider badge

**US-5.3.3: Push Events to Google**
- As a User, events I create in VITE-IT sync to Google Calendar so that calendars match
- Acceptance Criteria:
  - [ ] Option to sync event to Google on create
  - [ ] Event created in connected Google calendar
  - [ ] provider_event_id stored
  - [ ] Edits sync bidirectionally
- PRD Refs: FR57, FR60
- UX Ref: Sync checkbox on event form
- Estimate: 3 points
- Technical Notes:
  - Backend: Create event via Google Calendar API
  - Frontend: Sync checkbox

---

## Phase 6: Dashboard & Polish (Weeks 12-15)

### Epic 6.1: Dashboard & Reporting

**Goal:** Implement personalized dashboard and basic reporting
**PRD Coverage:** FR61-FR67, FR74-FR81
**Architecture Ref:** Section 4.1 (DashboardController)

#### User Stories:

**US-6.1.1: Dashboard Stats Cards**
- As a User, I see key metrics on dashboard so that I understand my workload at a glance
- Acceptance Criteria:
  - [ ] Tasks due today count
  - [ ] Overdue tasks count (red highlight)
  - [ ] Active expedientes count
  - [ ] Total clients count
  - [ ] Cards with appropriate icons
- PRD Refs: FR61, FR62, FR63
- UX Ref: Section 3.1 Dashboard Stats Cards
- Estimate: 5 points
- Technical Notes:
  - Backend: DashboardController@stats
  - Frontend: Stats card components

**US-6.1.2: Recent Cases Widget**
- As a User, I see my 5 most recent cases so that I can quickly access active work
- Acceptance Criteria:
  - [ ] Last 5 cases accessed or modified
  - [ ] Mini card with codigo, cliente, status
  - [ ] Click navigates to expediente detail
  - [ ] Refresh on page load
- PRD Refs: FR64, FR65
- UX Ref: Section 3.1 CASOS RECIENTES panel
- Estimate: 3 points
- Technical Notes:
  - Backend: Recent expedientes query
  - Frontend: Recent cases widget

**US-6.1.3: Urgent Tasks Widget**
- As a User, I see urgent/overdue tasks so that I prioritize my day
- Acceptance Criteria:
  - [ ] Top 5 tasks sorted by priority and due date
  - [ ] Checkbox to mark complete inline
  - [ ] Priority badge
  - [ ] Due date with overdue highlighting
- PRD Refs: FR62, FR63
- UX Ref: Section 3.1 TAREAS URGENTES panel
- Estimate: 3 points
- Technical Notes:
  - Backend: Filtered tasks query
  - Frontend: Task list widget

**US-6.1.4: Today's Events Widget**
- As a User, I see today's calendar events so that I plan my day
- Acceptance Criteria:
  - [ ] List of events for current day
  - [ ] Shows time, title, expediente link
  - [ ] Click to view event detail
  - [ ] Empty state if no events
- PRD Refs: FR61
- UX Ref: Section 3.1 EVENTOS HOY panel
- Estimate: 3 points
- Technical Notes:
  - Backend: Today's events query
  - Frontend: Events widget

**US-6.1.5: Role-Based Navigation**
- As a User, I see navigation items based on my permissions so that I access allowed features
- Acceptance Criteria:
  - [ ] Sidebar items filtered by permissions
  - [ ] Admin sees all items
  - [ ] Consultor sees client/case management
  - [ ] Support staff sees assigned work
  - [ ] Contador sees financial reports
- PRD Refs: FR66, FR67
- UX Ref: Section 2.1 Sidebar based on role
- Estimate: 3 points
- Technical Notes:
  - Backend: Permission check API
  - Frontend: v-if on sidebar items

**US-6.1.6: Language Switcher**
- As a User, I can switch interface language so that I work in my preferred language
- Acceptance Criteria:
  - [ ] Language selector in header
  - [ ] Options: Espanol, English, Francais
  - [ ] Immediate UI update on change
  - [ ] Preference persisted to user profile
- PRD Refs: FR74, FR75, FR76, FR77
- UX Ref: Header language selector
- Estimate: 3 points
- Technical Notes:
  - Backend: Store preference in user settings
  - Frontend: Vue I18n locale switching

**US-6.1.7: Complete Translations**
- As a User, all UI text is in my selected language so that I understand the interface
- Acceptance Criteria:
  - [ ] All new modules have ES/EN/FR translations
  - [ ] Clientes, Expedientes, Tareas module translations
  - [ ] Error messages translated
  - [ ] Form labels and placeholders translated
- PRD Refs: FR78
- UX Ref: N/A (i18n content)
- Estimate: 5 points
- Technical Notes:
  - Backend: N/A
  - Frontend: Update locales/*.json files

**US-6.1.8: Time Reports by Case**
- As an Accountant, I can view time reports across cases so that I prepare invoices
- Acceptance Criteria:
  - [ ] Report page with case filter
  - [ ] Shows: case, total hours, by user breakdown
  - [ ] Date range filter
  - [ ] Export to CSV
- PRD Refs: FR79, FR80, FR81
- UX Ref: Section 3.4 time reporting
- Estimate: 4 points
- Technical Notes:
  - Backend: Report endpoint with aggregation
  - Frontend: Report page with export

---

## Dependency Graph

```
Phase 1: Foundation
+------------------+     +------------------+     +------------------+
|  Epic 1.1        |---->|  Epic 1.2        |---->|  Epic 1.3        |
|  Multi-Tenant    |     |  Clientes        |     |  Acompanantes    |
+------------------+     +------------------+     +------------------+
                                   |
                                   v
Phase 2: Case Management
+------------------+     +------------------+     +------------------+
|  Epic 2.1        |<----|  Epic 2.2        |---->|  Epic 2.3        |
|  Expedientes     |     |  Case Wizard     |     |  Lifecycle       |
+------------------+     +------------------+     +------------------+
        |
        v
Phase 3: Tasks & Follow-up
+------------------+     +------------------+     +------------------+
|  Epic 3.1        |---->|  Epic 3.2        |     |  Epic 3.3        |
|  Tareas          |     |  Time Logging    |     |  Seguimiento     |
+------------------+     +------------------+     +------------------+
        |                                                  |
        v                                                  v
Phase 4: Documents & Integration
+------------------+     +------------------+     +------------------+
|  Epic 4.1        |---->|  Epic 4.2        |     |  Epic 4.3        |
|  OAuth           |     |  OneDrive        |     |  Google Drive    |
+------------------+     +------------------+     +------------------+
        |                        |                        |
        |                        v                        |
        |                +------------------+             |
        +--------------->|  Epic 4.4        |<------------+
                         |  Documents       |
                         +------------------+
                                   |
                                   v
Phase 5: Calendar & Events
+------------------+     +------------------+     +------------------+
|  Epic 5.1        |---->|  Epic 5.2        |     |  Epic 5.3        |
|  Agenda          |     |  Outlook Sync    |     |  Google Sync     |
+------------------+     +------------------+     +------------------+
        |
        v
Phase 6: Dashboard & Polish
+------------------+
|  Epic 6.1        |
|  Dashboard       |
+------------------+
```

---

## Technical Notes Summary

### Backend Work Per Phase

| Phase | Key Backend Components |
|-------|----------------------|
| 1 | Tenant model/scope/middleware, Cliente model/controller/service/repository, Acompanante model/controller |
| 2 | Expediente model/controller/service/repository, ExpedienteWizardService, TipoCaso configuration |
| 3 | Tarea model/controller/service, TiempoTarea model, Seguimiento model/controller/service |
| 4 | OAuthService, OAuthToken model, OneDriveService, GoogleDriveService, Documento/Carpeta models |
| 5 | Evento model/controller/service, OutlookCalendarService, GoogleCalendarService, sync jobs |
| 6 | DashboardController, report aggregation queries, i18n API preferences |

### Frontend Work Per Phase

| Phase | Key Frontend Components |
|-------|------------------------|
| 1 | ClienteCard, ClienteForm, ClienteSearch, AcompananteForm, Pinia stores |
| 2 | ExpedienteCard, ExpedienteWizard (5 steps), EtapaPipeline, ExpedienteDetailTabs |
| 3 | TareaCard, TareaForm, TareaKanban, TimeLogModal, SeguimientoTimeline |
| 4 | IntegrationStatus, OneDriveBrowser, GoogleDriveBrowser, CarpetaTree, DocumentoCard |
| 5 | CalendarView (FullCalendar), EventoForm, CalendarSyncSettings |
| 6 | Dashboard widgets, language switcher, report views |

---

## Story Point Legend

| Points | Effort | Example |
|--------|--------|---------|
| 1 | 2-4 hours | Simple UI tweak, minor fix |
| 2 | 0.5-1 day | Simple form, basic CRUD operation |
| 3 | 1-2 days | Complex form, multi-step operation |
| 5 | 2-4 days | New module, integration feature |
| 8 | 4-7 days | Complex integration, major feature |

---

## PRD Traceability Matrix

| PRD Requirement | Epic | User Stories |
|-----------------|------|--------------|
| FR1-FR9 | 1.2, 1.3 | US-1.2.1 through US-1.3.5 |
| FR10-FR21 | 2.1, 2.2, 2.3 | US-2.1.1 through US-2.3.4 |
| FR22-FR31 | 3.1, 3.2 | US-3.1.1 through US-3.2.4 |
| FR32-FR37 | 3.3 | US-3.3.1 through US-3.3.5 |
| FR38-FR46 | 4.2, 4.3, 4.4 | US-4.2.1 through US-4.4.5 |
| FR47-FR52 | 4.1 | US-4.1.1 through US-4.1.4 |
| FR53-FR60 | 5.1, 5.2, 5.3 | US-5.1.1 through US-5.3.3 |
| FR61-FR67 | 6.1 | US-6.1.1 through US-6.1.5 |
| FR68-FR73 | 1.1 | US-1.1.1 through US-1.1.5 |
| FR74-FR81 | 6.1 | US-6.1.6 through US-6.1.8 |

---

**Document Status:** Complete - Ready for Sprint Planning
**Next Steps:**
1. Review with development team
2. Prioritize stories within each epic
3. Create sprint backlog starting with Phase 1
4. Assign stories to team members
5. Begin implementation with Epic 1.1
