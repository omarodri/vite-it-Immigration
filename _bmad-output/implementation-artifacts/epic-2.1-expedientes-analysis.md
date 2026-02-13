# Epic 2.1: Expedientes Core - Technical Analysis & Implementation Plan

> **Document Version:** 1.0
> **Date:** 2026-02-10
> **Status:** 📋 PLANNING
> **Author:** Winston (System Architect)
> **Phase:** 2 - Case Management

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Current State Analysis](#2-current-state-analysis)
3. [Scope & Requirements](#3-scope--requirements)
4. [Database Design](#4-database-design)
5. [Backend Architecture](#5-backend-architecture)
6. [API Contract](#6-api-contract)
7. [Frontend Architecture](#7-frontend-architecture)
8. [Implementation Plan](#8-implementation-plan)
9. [File Inventory](#9-file-inventory)
10. [Risk Assessment](#10-risk-assessment)

---

## 1. Executive Summary

### Overview

Epic 2.1 implements the **Expedientes (Cases) Core Management** module, the central entity of the immigration case management system. This module enables consultors to create, view, edit, and manage immigration cases linked to clients.

### Key Metrics

| Metric | Value |
|--------|-------|
| **Story Points** | 34 |
| **User Stories** | 8 |
| **PRD Coverage** | FR10-FR15, FR17-FR21 |
| **Estimated Duration** | 1-2 weeks |
| **Complexity** | High |

### Dependencies

| Dependency | Status | Required For |
|------------|--------|--------------|
| Epic 1.1 (Multi-tenant) | ✅ Complete | Tenant isolation |
| Epic 1.2 (Clients) | ✅ Complete | Client linking |
| Epic 1.3 (Companions) | ✅ Complete | Family members on cases |

---

## 2. Current State Analysis

### 2.1 What Exists

| Component | Status | Notes |
|-----------|--------|-------|
| **Migration: case_types** | ✅ Exists | With 15 seeded types |
| **Migration: cases** | ✅ Exists | Full schema defined |
| **Models** | ❌ Missing | Case, CaseType |
| **Controllers** | ❌ Missing | CaseController |
| **Services** | ❌ Missing | CaseService |
| **Repositories** | ❌ Missing | CaseRepository |
| **Policies** | ❌ Missing | CasePolicy |
| **Form Requests** | ❌ Missing | Store/Update |
| **Resources** | ❌ Missing | CaseResource |
| **Frontend** | ❌ Missing | All components |

### 2.2 Database Status

```bash
# Tables already created by migrations:
✅ case_types (with 15 seeded types)
✅ cases (empty, ready for use)
```

### 2.3 Seeded Case Types

| Category | Types |
|----------|-------|
| **Temporary Residence** | TOURIST, STUDENT, WORK, EMIT |
| **Permanent Residence** | EXPRESS_ENTRY, ARRIMA, PEQ, PILOT, SKILLED_WORKER |
| **Humanitarian** | ASYLUM, ASYLUM_CLAIM, APPEAL, FEDERAL_COURT, ERAR, SPONSORSHIP |

---

## 3. Scope & Requirements

### 3.1 User Stories

| ID | Story | Points | Priority |
|----|-------|--------|----------|
| US-2.1.1 | Expediente List View | 5 | 🔴 High |
| US-2.1.2 | Expediente Filters | 3 | 🔴 High |
| US-2.1.3 | View Expediente Detail | 5 | 🔴 High |
| US-2.1.4 | Case Timeline | 5 | 🟡 Medium |
| US-2.1.5 | Edit Expediente | 3 | 🔴 High |
| US-2.1.6 | Expediente Stage Pipeline | 5 | 🟡 Medium |
| US-2.1.7 | Assign Case | 3 | 🟡 Medium |
| US-2.1.8 | Auto-Logged Modifications | 5 | 🔴 High |

### 3.2 Functional Requirements Mapping

| PRD Ref | Description | Story |
|---------|-------------|-------|
| FR10 | Case creation | Epic 2.2 (Wizard) |
| FR11 | Case type selection | Epic 2.2 |
| FR12 | Client linking | Epic 2.2 |
| FR13 | Family member linking | Epic 2.2 |
| FR14 | Case assignment | US-2.1.7 |
| FR15 | Stage management | US-2.1.6 |
| FR17 | Case editing | US-2.1.5 |
| FR18 | Case viewing | US-2.1.1, US-2.1.3 |
| FR19 | Case filtering | US-2.1.2 |
| FR21 | Audit logging | US-2.1.8 |

### 3.3 Business Rules

1. **Case Number Generation**: Auto-generated unique format `{YEAR}-{TYPE_CODE}-{SEQUENCE}`
2. **Tenant Isolation**: Cases belong to one tenant only
3. **Client Linking**: Every case must have exactly one primary client
4. **Status Workflow**: `active` → `inactive` / `archived` / `closed`
5. **Priority Levels**: `urgent`, `high`, `medium`, `low`
6. **Assignment**: Cases can be assigned to users within same tenant
7. **Soft Delete**: Cases are soft-deleted to preserve audit trail

---

## 4. Database Design

### 4.1 Entity Relationship Diagram

```
┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│   tenants   │       │ case_types  │       │    users    │
├─────────────┤       ├─────────────┤       ├─────────────┤
│ PK id       │       │ PK id       │       │ PK id       │
└──────┬──────┘       │ FK tenant_id│       └──────┬──────┘
       │              │    name     │              │
       │              │    code     │              │
       │              │    category │              │
       │              └──────┬──────┘              │
       │                     │                     │
       ▼                     ▼                     ▼
┌──────────────────────────────────────────────────────────┐
│                         cases                             │
├──────────────────────────────────────────────────────────┤
│ PK id                                                     │
│ FK tenant_id ─────────────────────────────────────────────┤
│ FK client_id ─────────────────────────┐                   │
│ FK case_type_id ──────────────────────┼───────────────────┤
│ FK assigned_to ───────────────────────┼───────────────────┤
│    case_number (unique)               │                   │
│    status                             │                   │
│    priority                           ▼                   │
│    progress                    ┌─────────────┐           │
│    description                 │   clients   │           │
│    hearing_date                ├─────────────┤           │
│    fda_deadline                │ PK id       │           │
│    ...                         │ FK tenant_id│           │
└──────────────────────────────────────────────────────────┘
       │
       │ Future Epics
       ▼
┌──────┴──────┐   ┌─────────────┐   ┌─────────────┐
│    tasks    │   │ follow_ups  │   │  documents  │
└─────────────┘   └─────────────┘   └─────────────┘
```

### 4.2 Cases Table Schema (Already Migrated)

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| tenant_id | BIGINT UNSIGNED | FK → tenants, CASCADE |
| case_number | VARCHAR(255) | UNIQUE, NOT NULL |
| client_id | BIGINT UNSIGNED | FK → clients, CASCADE |
| case_type_id | BIGINT UNSIGNED | FK → case_types, RESTRICT |
| assigned_to | BIGINT UNSIGNED | FK → users, SET NULL |
| status | ENUM | active, inactive, archived, closed |
| priority | ENUM | urgent, high, medium, low |
| progress | TINYINT UNSIGNED | 0-100 |
| language | VARCHAR(255) | Default: 'es' |
| description | TEXT | NULL |
| hearing_date | DATE | NULL |
| fda_deadline | DATE | NULL |
| brown_sheet_date | DATE | NULL |
| evidence_deadline | DATE | NULL |
| archive_box_number | VARCHAR(255) | NULL |
| closed_at | DATE | NULL |
| closure_notes | TEXT | NULL |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |
| deleted_at | TIMESTAMP | Soft delete |

### 4.3 Case Types Table (Already Migrated & Seeded)

| Column | Type | Constraints |
|--------|------|-------------|
| id | BIGINT UNSIGNED | PK |
| tenant_id | BIGINT UNSIGNED | FK, NULL (global types) |
| name | VARCHAR(255) | NOT NULL |
| code | VARCHAR(255) | UNIQUE |
| category | ENUM | temporary_residence, permanent_residence, humanitarian |
| description | TEXT | NULL |
| is_active | BOOLEAN | Default: true |
| created_at | TIMESTAMP | |
| updated_at | TIMESTAMP | |

---

## 5. Backend Architecture

### 5.1 Layer Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                        PRESENTATION LAYER                            │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │ CaseController                                               │    │
│  │ ├── index(Request): JsonResponse         [List + Filters]   │    │
│  │ ├── store(StoreCaseRequest): JsonResponse [Create]          │    │
│  │ ├── show(Case): CaseResource              [Detail View]     │    │
│  │ ├── update(UpdateCaseRequest): JsonResponse [Edit]          │    │
│  │ ├── destroy(Case): JsonResponse           [Soft Delete]     │    │
│  │ ├── assign(AssignCaseRequest): JsonResponse [Assign User]   │    │
│  │ ├── timeline(Case): JsonResponse          [Activity Log]    │    │
│  │ └── statistics(): JsonResponse            [Dashboard Stats] │    │
│  └─────────────────────────────────────────────────────────────┘    │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │ CaseTypeController                                           │    │
│  │ ├── index(): CaseTypeResource::collection [List Types]      │    │
│  │ └── show(CaseType): CaseTypeResource      [Type Detail]     │    │
│  └─────────────────────────────────────────────────────────────┘    │
│  ┌───────────────────────┐  ┌───────────────────────────────────┐  │
│  │ StoreCaseRequest      │  │ UpdateCaseRequest                 │  │
│  │ • client_id: required │  │ • All fields optional             │  │
│  │ • case_type_id: req   │  │ • status: enum validation         │  │
│  │ • priority: enum      │  │ • priority: enum validation       │  │
│  │ • description: opt    │  │ • assigned_to: exists:users       │  │
│  └───────────────────────┘  └───────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        APPLICATION LAYER                             │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │ CaseService                                                  │    │
│  │                                                              │    │
│  │ Core Methods:                                                │    │
│  │ • listCases(filters[], perPage): LengthAwarePaginator        │    │
│  │ • getCase(Case): Case (with eager loading)                   │    │
│  │ • createCase(array): Case                                    │    │
│  │ • updateCase(Case, array): Case                              │    │
│  │ • deleteCase(Case): void                                     │    │
│  │ • assignCase(Case, userId): Case                             │    │
│  │ • getTimeline(Case): Collection                              │    │
│  │ • getStatistics(): array                                     │    │
│  │                                                              │    │
│  │ Business Logic:                                              │    │
│  │ • generateCaseNumber(CaseType): string                       │    │
│  │ • calculateProgress(Case): int                               │    │
│  │ • validateStatusTransition(current, new): bool               │    │
│  └─────────────────────────────────────────────────────────────┘    │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │ CasePolicy                                                   │    │
│  │ • viewAny(User) → cases.view                                 │    │
│  │ • view(User, Case) → cases.view + tenant match               │    │
│  │ • create(User) → cases.create                                │    │
│  │ • update(User, Case) → cases.update + tenant match           │    │
│  │ • delete(User, Case) → cases.delete + tenant match           │    │
│  │ • assign(User, Case) → cases.assign + tenant match           │    │
│  └─────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         DOMAIN LAYER                                 │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │ Case (Model)                                                 │    │
│  │                                                              │    │
│  │ Traits: BelongsToTenant, HasFactory, LogsActivity, SoftDeletes│   │
│  │                                                              │    │
│  │ Relationships:                                               │    │
│  │ • client(): BelongsTo<Client>                                │    │
│  │ • caseType(): BelongsTo<CaseType>                            │    │
│  │ • assignedTo(): BelongsTo<User>                              │    │
│  │ • tasks(): HasMany<Task> (future)                            │    │
│  │ • documents(): HasMany<Document> (future)                    │    │
│  │ • followUps(): HasMany<FollowUp> (future)                    │    │
│  │                                                              │    │
│  │ Accessors:                                                   │    │
│  │ • getStatusLabelAttribute(): string                          │    │
│  │ • getPriorityLabelAttribute(): string                        │    │
│  │ • getProgressPercentageAttribute(): string                   │    │
│  │ • getDaysUntilHearingAttribute(): ?int                       │    │
│  │                                                              │    │
│  │ Scopes:                                                      │    │
│  │ • scopeActive($query)                                        │    │
│  │ • scopeByStatus($query, $status)                             │    │
│  │ • scopeByPriority($query, $priority)                         │    │
│  │ • scopeByAssignee($query, $userId)                           │    │
│  │ • scopeSearch($query, $term)                                 │    │
│  │ • scopeUpcoming($query) // upcoming hearings                 │    │
│  └─────────────────────────────────────────────────────────────┘    │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │ CaseType (Model)                                             │    │
│  │                                                              │    │
│  │ Relationships:                                               │    │
│  │ • cases(): HasMany<Case>                                     │    │
│  │                                                              │    │
│  │ Scopes:                                                      │    │
│  │ • scopeActive($query)                                        │    │
│  │ • scopeByCategory($query, $category)                         │    │
│  │ • scopeGlobalOrTenant($query, $tenantId)                     │    │
│  └─────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      INFRASTRUCTURE LAYER                            │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │ CaseRepositoryInterface                                      │    │
│  │ • findById(int): ?Case                                       │    │
│  │ • paginate(filters[], perPage): LengthAwarePaginator         │    │
│  │ • create(array): Case                                        │    │
│  │ • update(Case, array): Case                                  │    │
│  │ • delete(Case): bool                                         │    │
│  │ • getStatistics(): array                                     │    │
│  │ • getNextSequence(CaseType): int                             │    │
│  └─────────────────────────────────────────────────────────────┘    │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │ CaseRepository (Eloquent)                                    │    │
│  │ • Implements all interface methods                           │    │
│  │ • Applies tenant scope automatically                         │    │
│  │ • Eager loads relationships                                  │    │
│  └─────────────────────────────────────────────────────────────┘    │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │ CaseTypeRepository                                           │    │
│  │ • getActive(): Collection                                    │    │
│  │ • getByCategory(category): Collection                        │    │
│  └─────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────┘
```

### 5.2 Case Number Generation Algorithm

```php
// Format: {YEAR}-{TYPE_CODE}-{SEQUENCE}
// Example: 2026-ASYLUM-00042

public function generateCaseNumber(CaseType $caseType): string
{
    $year = now()->year;
    $code = $caseType->code;
    $sequence = $this->repository->getNextSequence($caseType);

    return sprintf('%d-%s-%05d', $year, $code, $sequence);
}
```

### 5.3 Status Transitions

```
                    ┌─────────┐
                    │ active  │
                    └────┬────┘
                         │
         ┌───────────────┼───────────────┐
         ▼               ▼               ▼
    ┌─────────┐    ┌──────────┐    ┌─────────┐
    │inactive │    │ archived │    │ closed  │
    └────┬────┘    └──────────┘    └─────────┘
         │                               ▲
         └───────────────────────────────┘
                (can be closed later)
```

---

## 6. API Contract

### 6.1 Endpoints

| Method | Endpoint | Description | Permission |
|--------|----------|-------------|------------|
| `GET` | `/api/cases` | List cases (paginated + filters) | cases.view |
| `POST` | `/api/cases` | Create new case | cases.create |
| `GET` | `/api/cases/{case}` | Get case details | cases.view |
| `PUT` | `/api/cases/{case}` | Update case | cases.update |
| `DELETE` | `/api/cases/{case}` | Soft delete case | cases.delete |
| `POST` | `/api/cases/{case}/assign` | Assign case to user | cases.assign |
| `GET` | `/api/cases/{case}/timeline` | Get case activity log | cases.view |
| `GET` | `/api/cases/statistics` | Get dashboard stats | cases.view |
| `GET` | `/api/case-types` | List available case types | cases.view |
| `GET` | `/api/case-types/{caseType}` | Get case type details | cases.view |

### 6.2 Filter Parameters (GET /api/cases)

| Parameter | Type | Description |
|-----------|------|-------------|
| search | string | Search by case_number, client name |
| status | string | Filter by status |
| priority | string | Filter by priority |
| case_type_id | integer | Filter by case type |
| assigned_to | integer | Filter by assignee |
| client_id | integer | Filter by client |
| hearing_from | date | Hearing date from |
| hearing_to | date | Hearing date to |
| sort_by | string | Sort field (default: created_at) |
| sort_direction | string | asc/desc |
| per_page | integer | Items per page (default: 15) |

### 6.3 Request/Response Schemas

#### Create Case Request
```typescript
interface CreateCaseData {
    client_id: number;           // required
    case_type_id: number;        // required
    priority?: 'urgent' | 'high' | 'medium' | 'low';
    description?: string;
    hearing_date?: string;       // YYYY-MM-DD
    fda_deadline?: string;
    brown_sheet_date?: string;
    evidence_deadline?: string;
}
```

#### Case Resource Response
```typescript
interface CaseResource {
    id: number;
    case_number: string;
    tenant_id: number;
    status: 'active' | 'inactive' | 'archived' | 'closed';
    status_label: string;
    priority: 'urgent' | 'high' | 'medium' | 'low';
    priority_label: string;
    progress: number;
    progress_percentage: string;
    description: string | null;
    language: string;

    // Dates
    hearing_date: string | null;
    fda_deadline: string | null;
    brown_sheet_date: string | null;
    evidence_deadline: string | null;
    days_until_hearing: number | null;
    closed_at: string | null;
    closure_notes: string | null;
    archive_box_number: string | null;

    // Timestamps
    created_at: string;
    updated_at: string;

    // Relations (when loaded)
    client?: ClientResource;
    case_type?: CaseTypeResource;
    assigned_to?: UserResource;
}
```

#### Statistics Response
```typescript
interface CaseStatistics {
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
    upcoming_hearings: number;  // next 30 days
    overdue_deadlines: number;
}
```

---

## 7. Frontend Architecture

### 7.1 Component Structure

```
views/
└── cases/
    ├── list.vue              # Case list with filters (US-2.1.1, US-2.1.2)
    ├── show.vue              # Case detail with tabs (US-2.1.3)
    ├── edit.vue              # Edit case form (US-2.1.5)
    └── components/
        ├── CaseCard.vue      # Card for list view
        ├── CaseFilters.vue   # Filter bar component
        ├── CaseTabs.vue      # Tab navigation
        ├── CaseTimeline.vue  # Activity timeline (US-2.1.4)
        ├── CasePipeline.vue  # Stage pipeline (US-2.1.6)
        ├── CaseAssign.vue    # Assignment modal (US-2.1.7)
        └── CaseHeader.vue    # Header with status/priority
```

### 7.2 State Management (Pinia)

```typescript
// stores/case.ts
interface CaseState {
    cases: Case[];
    currentCase: Case | null;
    caseTypes: CaseType[];
    statistics: CaseStatistics | null;
    meta: PaginationMeta | null;
    filters: CaseFilters;
    isLoading: boolean;
    error: string | null;
}

interface CaseFilters {
    search?: string;
    status?: CaseStatus;
    priority?: CasePriority;
    case_type_id?: number;
    assigned_to?: number;
    client_id?: number;
    hearing_from?: string;
    hearing_to?: string;
    sort_by: string;
    sort_direction: 'asc' | 'desc';
    per_page: number;
    page: number;
}
```

### 7.3 Routes

```typescript
// router/index.ts
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

---

## 8. Implementation Plan

### 8.1 Implementation Order

| Phase | Task | Story | Effort | Dependencies |
|-------|------|-------|--------|--------------|
| **1** | Create CaseType model | - | 1h | None |
| **2** | Create Case model + accessors | - | 2h | CaseType |
| **3** | Create CaseRepository | - | 2h | Case |
| **4** | Create CaseService | - | 3h | Repository |
| **5** | Create CasePolicy | - | 1h | None |
| **6** | Create CaseController | US-2.1.1,3,5 | 4h | All above |
| **7** | Create Form Requests | - | 1h | None |
| **8** | Create Resources | - | 1h | Models |
| **9** | Register providers | - | 0.5h | All above |
| **10** | Create API routes | - | 0.5h | Controller |
| **11** | Create CaseFactory | - | 1h | Models |
| **12** | Write Feature Tests | - | 4h | All backend |
| **13** | Frontend: Types | - | 1h | API defined |
| **14** | Frontend: Service | - | 1h | Types |
| **15** | Frontend: Store | - | 2h | Service |
| **16** | Frontend: List View | US-2.1.1,2 | 4h | Store |
| **17** | Frontend: Show View | US-2.1.3 | 4h | Store |
| **18** | Frontend: Edit View | US-2.1.5 | 3h | Store |
| **19** | Frontend: Timeline | US-2.1.4 | 3h | Show view |
| **20** | Frontend: Pipeline | US-2.1.6 | 3h | Show view |
| **21** | Frontend: Assign | US-2.1.7 | 2h | Show view |
| **22** | Translations | - | 1h | Views |
| **23** | Integration Testing | - | 2h | All |

### 8.2 Permissions to Add

```php
// Add to RolePermissionSeeder
'cases.view',
'cases.create',
'cases.update',
'cases.delete',
'cases.assign',
```

### 8.3 Estimated Total Effort

| Category | Hours |
|----------|-------|
| Backend | 20h |
| Frontend | 24h |
| Testing | 6h |
| **Total** | **50h** |

---

## 9. File Inventory

### 9.1 Backend Files to Create (15)

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
│   ├── ImmigrationCase.php  (named to avoid 'case' reserved word)
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
└── Services/
    └── Case/
        └── CaseService.php

database/factories/
├── ImmigrationCaseFactory.php
└── CaseTypeFactory.php

tests/Feature/
└── CaseTest.php
```

### 9.2 Frontend Files to Create (12)

```
resources/js/src/
├── types/
│   └── case.ts
├── services/
│   └── caseService.ts
├── stores/
│   └── case.ts
├── views/
│   └── cases/
│       ├── list.vue
│       ├── show.vue
│       ├── edit.vue
│       └── components/
│           ├── CaseCard.vue
│           ├── CaseFilters.vue
│           ├── CaseTabs.vue
│           ├── CaseTimeline.vue
│           ├── CasePipeline.vue
│           ├── CaseAssign.vue
│           └── CaseHeader.vue
└── locales/
    ├── en.json (add ~60 keys)
    └── es.json (add ~60 keys)
```

### 9.3 Files to Modify (5)

```
routes/api.php                              # Add case routes
app/Providers/RepositoryServiceProvider.php # Add bindings
app/Providers/AuthServiceProvider.php       # Add policy
database/seeders/RolePermissionSeeder.php   # Add permissions
resources/js/src/router/index.ts            # Add routes
```

---

## 10. Risk Assessment

### 10.1 Technical Risks

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Reserved word 'case' in PHP | High | Medium | Use `ImmigrationCase` as model name |
| Complex filtering logic | Medium | Medium | Build incrementally, test each filter |
| Case number uniqueness | Medium | High | Use DB transaction + unique constraint |
| Timeline performance | Medium | Medium | Paginate activity log queries |

### 10.2 Model Naming Strategy

Since `case` is a reserved word in PHP, the model will be named `ImmigrationCase`:

```php
class ImmigrationCase extends Model
{
    protected $table = 'cases';
    // ...
}
```

### 10.3 Testing Strategy

1. **Unit Tests**: Case number generation, status transitions
2. **Feature Tests**: CRUD operations, filters, assignment
3. **Integration Tests**: Timeline with activity log
4. **Frontend Tests**: Component interactions (future)

---

## Appendix A: Status & Priority Labels

### Spanish Labels
```php
public const STATUS_LABELS = [
    'active' => 'Activo',
    'inactive' => 'Inactivo',
    'archived' => 'Archivado',
    'closed' => 'Cerrado',
];

public const PRIORITY_LABELS = [
    'urgent' => 'Urgente',
    'high' => 'Alta',
    'medium' => 'Media',
    'low' => 'Baja',
];
```

### Priority Colors
```typescript
const PRIORITY_COLORS = {
    urgent: 'danger',
    high: 'warning',
    medium: 'info',
    low: 'secondary',
};
```

---

## Appendix B: Case Type Categories

| Category | Spanish | Color |
|----------|---------|-------|
| temporary_residence | Residencia Temporal | Blue |
| permanent_residence | Residencia Permanente | Green |
| humanitarian | Humanitario | Orange |

---

**Document End**

---

## Next Steps

After Omar approves this analysis:
1. Begin implementation following the phased approach
2. Start with backend models and work towards frontend
3. Implement one user story at a time
4. Write tests alongside implementation
