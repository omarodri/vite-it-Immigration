# Epic 1.3: Acompañantes Management - Technical Architecture

> **Document Version:** 1.0
> **Date:** 2026-02-10
> **Status:** ✅ IMPLEMENTED
> **Author:** Winston (System Architect)

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Scope & Requirements](#2-scope--requirements)
3. [Database Design](#3-database-design)
4. [Backend Architecture](#4-backend-architecture)
5. [API Contract](#5-api-contract)
6. [Frontend Architecture](#6-frontend-architecture)
7. [Security & Authorization](#7-security--authorization)
8. [Test Coverage](#8-test-coverage)
9. [File Inventory](#9-file-inventory)
10. [Integration Points](#10-integration-points)

---

## 1. Executive Summary

### Overview

Epic 1.3 implements the **Companions (Acompañantes) Management** module, enabling users to manage family members and dependents linked to immigration clients. This module follows Clean Architecture principles and integrates seamlessly with the existing Client Management (Epic 1.2) infrastructure.

### Key Metrics

| Metric | Value |
|--------|-------|
| **Story Points** | 13 |
| **User Stories** | 5 |
| **PRD Coverage** | FR6, FR7 |
| **Test Cases** | 27 |
| **Backend Files** | 14 |
| **Frontend Files** | 5 |
| **Implementation Status** | 100% Complete |

### Technology Stack

| Layer | Technology |
|-------|------------|
| Database | MySQL 8.0 |
| Backend | Laravel 12, PHP 8.2+ |
| API | REST, Laravel Sanctum |
| Frontend | Vue 3.5, TypeScript, Pinia |
| UI | Tailwind CSS, HeadlessUI |

---

## 2. Scope & Requirements

### User Stories Implemented

| ID | Story | Points | Status |
|----|-------|--------|--------|
| US-1.3.1 | List Client Companions | 3 | ✅ |
| US-1.3.2 | Add Companion | 3 | ✅ |
| US-1.3.3 | Edit Companion | 2 | ✅ |
| US-1.3.4 | Delete Companion | 2 | ✅ |
| US-1.3.5 | Companion Relationship Types | 3 | ✅ |

### Functional Requirements

- **FR6:** Gestión de acompañantes vinculados a clientes
- **FR7:** Tipos de relación con opción personalizada ("Otro")

### Business Rules

1. Companions are always linked to exactly one Client
2. Companions inherit tenant isolation from their parent Client
3. Relationship types: `spouse`, `child`, `parent`, `sibling`, `other`
4. When relationship is `other`, a custom description is required
5. Soft delete is used to preserve audit trail
6. All CRUD operations are logged via Activity Log

---

## 3. Database Design

### 3.1 Entity Relationship Diagram

```
┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│   tenants   │       │   clients   │       │ companions  │
├─────────────┤       ├─────────────┤       ├─────────────┤
│ PK id       │◄──────│ FK tenant_id│◄──────│ FK tenant_id│
│    name     │       │ PK id       │◄──────│ FK client_id│
│    ...      │       │    ...      │       │ PK id       │
└─────────────┘       └─────────────┘       │    ...      │
                                            └─────────────┘
```

### 3.2 Table Schema: `companions`

```sql
CREATE TABLE companions (
    -- Primary Key
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Foreign Keys (Tenant Isolation)
    tenant_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,

    -- Personal Information
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    relationship ENUM('spouse', 'child', 'parent', 'sibling', 'other') NOT NULL DEFAULT 'other',
    relationship_other VARCHAR(255) NULL,
    date_of_birth DATE NULL,
    gender ENUM('male', 'female', 'other') NULL,

    -- Passport Information
    passport_number VARCHAR(255) NULL,
    passport_country VARCHAR(255) NULL,
    passport_expiry_date DATE NULL,
    nationality VARCHAR(255) NULL,

    -- Additional
    notes TEXT NULL,

    -- Timestamps
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    -- Constraints
    CONSTRAINT fk_companions_tenant
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_companions_client
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_companions_tenant_client (tenant_id, client_id),
    INDEX idx_companions_relationship (relationship)
);
```

### 3.3 Migrations

| Migration File | Purpose |
|----------------|---------|
| `2026_02_08_221202_create_companions_table.php` | Creates base table structure |
| `2026_02_09_100000_add_relationship_other_to_companions.php` | Adds `relationship_other`, `passport_country`, `passport_expiry_date`, `gender` |

---

## 4. Backend Architecture

### 4.1 Layer Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                        PRESENTATION LAYER                            │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │ CompanionController                                          │    │
│  │ ├── index(Client): AnonymousResourceCollection               │    │
│  │ ├── store(StoreCompanionRequest, Client): JsonResponse      │    │
│  │ ├── show(Client, Companion): CompanionResource               │    │
│  │ ├── update(UpdateCompanionRequest, Client, Companion)        │    │
│  │ └── destroy(Client, Companion): JsonResponse                 │    │
│  └─────────────────────────────────────────────────────────────┘    │
│  ┌─────────────────────┐  ┌─────────────────────────────────────┐  │
│  │ StoreCompanionRequest│  │ UpdateCompanionRequest              │  │
│  │ Validation Rules:   │  │ Validation Rules:                   │  │
│  │ • first_name: req   │  │ • first_name: sometimes             │  │
│  │ • last_name: req    │  │ • last_name: sometimes              │  │
│  │ • relationship: req │  │ • relationship: sometimes           │  │
│  │ • relationship_other│  │ • (partial updates allowed)         │  │
│  │   required_if:other │  │                                     │  │
│  └─────────────────────┘  └─────────────────────────────────────┘  │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │ CompanionResource                                            │    │
│  │ Transforms: id, client_id, first_name, last_name, full_name, │    │
│  │ relationship, relationship_other, relationship_label,        │    │
│  │ date_of_birth, age, gender, passport_*, nationality, notes,  │    │
│  │ created_at, updated_at                                       │    │
│  └─────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        APPLICATION LAYER                             │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │ CompanionService                                             │    │
│  │                                                              │    │
│  │ Dependencies:                                                │    │
│  │ • CompanionRepositoryInterface (injected via DI)             │    │
│  │                                                              │    │
│  │ Methods:                                                     │    │
│  │ • listCompanions(Client): Collection                         │    │
│  │ • getCompanion(Companion): Companion (with eager loading)    │    │
│  │ • createCompanion(Client, array): Companion                  │    │
│  │ • updateCompanion(Companion, array): Companion               │    │
│  │ • deleteCompanion(Companion): void                           │    │
│  │ • getCompanionCount(Client): int                             │    │
│  │                                                              │    │
│  │ Cross-Cutting Concerns:                                      │    │
│  │ • DB::transaction() for write operations                     │    │
│  │ • activity() logging for audit trail                         │    │
│  └─────────────────────────────────────────────────────────────┘    │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │ CompanionPolicy                                              │    │
│  │                                                              │    │
│  │ Authorization Matrix:                                        │    │
│  │ • viewAny(User, Client) → clients.view + tenant match        │    │
│  │ • view(User, Companion) → clients.view + tenant match        │    │
│  │ • create(User, Client) → clients.update + tenant match       │    │
│  │ • update(User, Companion) → clients.update + tenant match    │    │
│  │ • delete(User, Companion) → clients.delete + tenant match    │    │
│  └─────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         DOMAIN LAYER                                 │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │ Companion (Eloquent Model)                                   │    │
│  │                                                              │    │
│  │ Traits:                                                      │    │
│  │ • BelongsToTenant (tenant isolation)                         │    │
│  │ • HasFactory (testing support)                               │    │
│  │ • LogsActivity (audit logging)                               │    │
│  │ • SoftDeletes (data retention)                               │    │
│  │                                                              │    │
│  │ Relationships:                                               │    │
│  │ • client(): BelongsTo<Client>                                │    │
│  │                                                              │    │
│  │ Accessors:                                                   │    │
│  │ • getFullNameAttribute(): string                             │    │
│  │ • getInitialsAttribute(): string                             │    │
│  │ • getRelationshipLabelAttribute(): string                    │    │
│  │ • getAgeAttribute(): ?int                                    │    │
│  │                                                              │    │
│  │ Constants:                                                   │    │
│  │ • RELATIONSHIP_TYPES: array (with Spanish labels)            │    │
│  │                                                              │    │
│  │ Casts:                                                       │    │
│  │ • date_of_birth → date                                       │    │
│  │ • passport_expiry_date → date                                │    │
│  └─────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      INFRASTRUCTURE LAYER                            │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │ CompanionRepositoryInterface (Contract)                      │    │
│  │                                                              │    │
│  │ • findById(int $id): ?Companion                              │    │
│  │ • getByClient(Client $client): Collection                    │    │
│  │ • create(array $data): Companion                             │    │
│  │ • update(Companion $companion, array $data): Companion       │    │
│  │ • delete(Companion $companion): bool                         │    │
│  │ • countByClient(Client $client): int                         │    │
│  └─────────────────────────────────────────────────────────────┘    │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │ CompanionRepository (Eloquent Implementation)                │    │
│  │                                                              │    │
│  │ Implementation Details:                                      │    │
│  │ • Injects tenant_id from Auth::user()->tenant_id             │    │
│  │ • Orders results by relationship, then first_name            │    │
│  │ • Uses Eloquent for all database operations                  │    │
│  └─────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────┘
```

### 4.2 Service Provider Registration

**RepositoryServiceProvider.php:**
```php
$this->app->bind(
    CompanionRepositoryInterface::class,
    CompanionRepository::class
);
```

**AuthServiceProvider.php:**
```php
protected $policies = [
    // ...
    Companion::class => CompanionPolicy::class,
];
```

---

## 5. API Contract

### 5.1 Endpoints

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| `GET` | `/api/clients/{client}/companions` | List all companions for a client | ✅ |
| `POST` | `/api/clients/{client}/companions` | Create a new companion | ✅ |
| `GET` | `/api/clients/{client}/companions/{companion}` | Get companion details | ✅ |
| `PUT` | `/api/clients/{client}/companions/{companion}` | Update companion | ✅ |
| `DELETE` | `/api/clients/{client}/companions/{companion}` | Delete companion (soft) | ✅ |

### 5.2 Request/Response Schemas

#### Create Companion Request
```typescript
interface CreateCompanionData {
    first_name: string;          // required, max:255
    last_name: string;           // required, max:255
    relationship: RelationshipType; // required, enum
    relationship_other?: string; // required if relationship='other', max:255
    date_of_birth?: string;      // date, before:today
    gender?: 'male' | 'female' | 'other';
    passport_number?: string;    // max:50
    passport_country?: string;   // max:100
    passport_expiry_date?: string; // date
    nationality?: string;        // max:100
    notes?: string;              // max:1000
}
```

#### Update Companion Request
```typescript
interface UpdateCompanionData {
    first_name?: string;
    last_name?: string;
    relationship?: RelationshipType;
    relationship_other?: string;
    date_of_birth?: string;
    gender?: 'male' | 'female' | 'other';
    passport_number?: string;
    passport_country?: string;
    passport_expiry_date?: string;
    nationality?: string;
    notes?: string;
}
```

#### Companion Resource Response
```typescript
interface CompanionResource {
    id: number;
    client_id: number;
    first_name: string;
    last_name: string;
    full_name: string;           // computed
    relationship: RelationshipType;
    relationship_other: string | null;
    relationship_label: string;  // computed (Spanish label)
    date_of_birth: string | null; // 'YYYY-MM-DD'
    age: number | null;          // computed from date_of_birth
    gender: 'male' | 'female' | 'other' | null;
    passport_number: string | null;
    passport_country: string | null;
    passport_expiry_date: string | null;
    nationality: string | null;
    notes: string | null;
    created_at: string;          // ISO 8601
    updated_at: string;          // ISO 8601
}
```

### 5.3 Error Responses

| Status | Description | Example |
|--------|-------------|---------|
| 401 | Unauthenticated | Missing or invalid token |
| 403 | Forbidden | Insufficient permissions or tenant mismatch |
| 404 | Not Found | Client or Companion not found |
| 422 | Validation Error | Invalid request data |

---

## 6. Frontend Architecture

### 6.1 Component Structure

```
┌─────────────────────────────────────────────────────────────────────┐
│                         VIEW LAYER                                   │
│  ┌─────────────────────────────────────────────────────────────┐    │
│  │ views/clients/show.vue                                       │    │
│  │                                                              │    │
│  │ Template Structure:                                          │    │
│  │ ├── Tab Navigation                                           │    │
│  │ │   └── [Acompañantes] tab with badge counter                │    │
│  │ ├── Tab Content: Companions                                  │    │
│  │ │   ├── Header with "Add Companion" button                   │    │
│  │ │   ├── Loading state (spinner)                              │    │
│  │ │   ├── Empty state (icon + message)                         │    │
│  │ │   └── Companions Grid (responsive 1-2 columns)             │    │
│  │ │       └── CompanionCard                                    │    │
│  │ │           ├── Avatar (initials)                            │    │
│  │ │           ├── Name, relationship, age                      │    │
│  │ │           ├── Edit/Delete buttons                          │    │
│  │ │           └── Passport info (conditional)                  │    │
│  │ └── Modal: Companion Form (HeadlessUI Dialog)                │    │
│  │     ├── Form fields (2-column grid)                          │    │
│  │     ├── Validation error display                             │    │
│  │     └── Save/Cancel buttons                                  │    │
│  └─────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────┘
```

### 6.2 State Management (Pinia Store)

```typescript
// stores/companion.ts
interface CompanionState {
    companions: Companion[];
    currentCompanion: Companion | null;
    clientId: number | null;
    isLoading: boolean;
    error: string | null;
}

// Getters
- getCompanionById(id: number): Companion | undefined
- companionCount: number
- spouses: Companion[]
- children: Companion[]
- parents: Companion[]
- siblings: Companion[]
- others: Companion[]
- relationshipOptions: Array<{value, label}>

// Actions
- fetchCompanions(clientId: number): Promise<void>
- fetchCompanion(clientId: number, companionId: number): Promise<Companion>
- createCompanion(clientId: number, data: CreateCompanionData): Promise<Companion>
- updateCompanion(clientId: number, companionId: number, data: UpdateCompanionData): Promise<Companion>
- deleteCompanion(clientId: number, companionId: number): Promise<void>
- clearCompanions(): void
- clearCurrentCompanion(): void
- clearError(): void
```

### 6.3 API Service Layer

```typescript
// services/companionService.ts
const companionService = {
    getCompanions(clientId: number): Promise<Companion[]>,
    getCompanion(clientId: number, companionId: number): Promise<Companion>,
    createCompanion(clientId: number, data: CreateCompanionData): Promise<Companion>,
    updateCompanion(clientId: number, companionId: number, data: UpdateCompanionData): Promise<Companion>,
    deleteCompanion(clientId: number, companionId: number): Promise<{message: string}>
}
```

### 6.4 Type Definitions

```typescript
// types/companion.ts
type RelationshipType = 'spouse' | 'child' | 'parent' | 'sibling' | 'other';

interface Companion {
    id: number;
    client_id: number;
    tenant_id: number;
    first_name: string;
    last_name: string;
    full_name?: string;
    relationship: RelationshipType;
    relationship_other: string | null;
    relationship_label?: string;
    date_of_birth: string | null;
    age?: number | null;
    gender: Gender | null;
    passport_number: string | null;
    passport_country: string | null;
    passport_expiry_date: string | null;
    nationality: string | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

const RELATIONSHIP_TYPE_OPTIONS: Array<{value: RelationshipType; label: string}>;
const RELATIONSHIP_TYPE_LABELS_ES: Record<RelationshipType, string>;
```

---

## 7. Security & Authorization

### 7.1 Authentication

- **Method:** Laravel Sanctum (token-based)
- **Middleware:** `auth:sanctum`
- **Token Location:** Bearer token in Authorization header

### 7.2 Authorization Matrix

| Action | Required Permission | Tenant Check | Additional Validation |
|--------|---------------------|--------------|----------------------|
| List companions | `clients.view` | ✅ | Client belongs to user's tenant |
| View companion | `clients.view` | ✅ | Companion belongs to user's tenant |
| Create companion | `clients.update` | ✅ | Client belongs to user's tenant |
| Update companion | `clients.update` | ✅ | Companion belongs to client |
| Delete companion | `clients.delete` | ✅ | Companion belongs to client |

### 7.3 Tenant Isolation

```php
// BelongsToTenant trait applies global scope
protected static function booted(): void
{
    static::addGlobalScope('tenant', function (Builder $builder) {
        if (auth()->check()) {
            $builder->where('tenant_id', auth()->user()->tenant_id);
        }
    });
}
```

### 7.4 Frontend Permission Checks

```vue
<button v-can="'clients.update'" @click="openCompanionModal()">
    Add Companion
</button>
```

---

## 8. Test Coverage

### 8.1 Test Categories

| Category | Test Count | Description |
|----------|------------|-------------|
| List Operations | 3 | Index, filtering, tenant isolation |
| Create Operations | 10 | Validation, success cases, relationship types |
| Read Operations | 3 | Show, not found, tenant isolation |
| Update Operations | 3 | Partial update, relationship change, auth |
| Delete Operations | 2 | Soft delete, auth check |
| Tenant Isolation | 1 | Auto-assignment of tenant_id |
| Activity Logging | 1 | Audit trail creation |
| Relationship Types | 4 | Spouse, child, parent, sibling |
| **Total** | **27** | All passing ✅ |

### 8.2 Test File Location

```
tests/Feature/CompanionTest.php
```

### 8.3 Running Tests

```bash
./vendor/bin/phpunit tests/Feature/CompanionTest.php
```

---

## 9. File Inventory

### 9.1 Backend Files (14)

```
app/
├── Http/
│   ├── Controllers/Api/
│   │   └── CompanionController.php
│   ├── Requests/Companion/
│   │   ├── StoreCompanionRequest.php
│   │   └── UpdateCompanionRequest.php
│   └── Resources/
│       └── CompanionResource.php
├── Models/
│   └── Companion.php
├── Policies/
│   └── CompanionPolicy.php
├── Repositories/
│   ├── Contracts/
│   │   └── CompanionRepositoryInterface.php
│   └── Eloquent/
│       └── CompanionRepository.php
└── Services/
    └── Companion/
        └── CompanionService.php

database/
├── factories/
│   └── CompanionFactory.php
└── migrations/
    ├── 2026_02_08_221202_create_companions_table.php
    └── 2026_02_09_100000_add_relationship_other_to_companions.php

routes/
└── api.php (modified)

tests/
└── Feature/
    └── CompanionTest.php
```

### 9.2 Frontend Files (5)

```
resources/js/src/
├── types/
│   └── companion.ts
├── services/
│   └── companionService.ts
├── stores/
│   └── companion.ts
├── views/
│   └── clients/
│       └── show.vue (modified)
└── locales/
    ├── en.json (modified - 41 keys)
    └── es.json (modified - 41 keys)
```

### 9.3 Modified Provider Files

```
app/Providers/
├── RepositoryServiceProvider.php (binding added)
└── AuthServiceProvider.php (policy registered)
```

---

## 10. Integration Points

### 10.1 Upstream Dependencies

| Dependency | Epic | Status |
|------------|------|--------|
| Tenants | 1.1 | ✅ Complete |
| Clients | 1.2 | ✅ Complete |
| User Authentication | 1.1 | ✅ Complete |
| Permissions System | 1.1 | ✅ Complete |

### 10.2 Downstream Consumers

| Consumer | Epic | Status |
|----------|------|--------|
| Expedientes (Cases) | 2.1 | 🔜 Pending |
| Document Management | 4.4 | 🔜 Pending |

### 10.3 Activity Log Integration

All companion operations are logged to the `activity_log` table:

```php
activity()
    ->causedBy(Auth::user())
    ->performedOn($companion)
    ->withProperties(['client' => $client->full_name])
    ->log('Added companion: ' . $companion->full_name);
```

---

## Appendix A: Relationship Type Constants

```php
public const RELATIONSHIP_TYPES = [
    'spouse'  => 'Cónyuge',
    'child'   => 'Hijo/a',
    'parent'  => 'Padre/Madre',
    'sibling' => 'Hermano/a',
    'other'   => 'Otro',
];
```

---

## Appendix B: Validation Rules Summary

### Store Request

| Field | Rules |
|-------|-------|
| first_name | required, string, max:255 |
| last_name | required, string, max:255 |
| relationship | required, in:spouse,child,parent,sibling,other |
| relationship_other | nullable, string, max:255, required_if:relationship,other |
| date_of_birth | nullable, date, before:today |
| gender | nullable, in:male,female,other |
| passport_number | nullable, string, max:50 |
| passport_country | nullable, string, max:100 |
| passport_expiry_date | nullable, date |
| nationality | nullable, string, max:100 |
| notes | nullable, string, max:1000 |

### Update Request

All fields use `sometimes` instead of `required` to allow partial updates.

---

**Document End**
