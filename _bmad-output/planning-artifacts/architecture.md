# VITE-IT Immigration - Technical Architecture Document

**Version:** 1.0
**Date:** 2026-02-08
**Author:** Architecture Team
**Status:** Ready for Implementation

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [System Architecture Overview](#2-system-architecture-overview)
3. [Database Architecture](#3-database-architecture)
4. [Backend Architecture](#4-backend-architecture)
5. [Frontend Architecture](#5-frontend-architecture)
6. [Integration Architecture](#6-integration-architecture)
7. [Security Architecture](#7-security-architecture)
8. [Implementation Phases](#8-implementation-phases)
9. [Technical Decisions](#9-technical-decisions)

---

## 1. Executive Summary

### 1.1 Project Context

VITE-IT Immigration is a **brownfield** multi-tenant SaaS CRM for Canadian immigration consultancies. The existing foundation includes:

| Component | Status | Technology |
|-----------|--------|------------|
| Authentication | Complete | Laravel Sanctum + 2FA |
| User Management | Complete | CRUD + Roles |
| Roles & Permissions | Complete | Spatie Permission |
| Activity Logging | Complete | Spatie Activitylog |
| Clean Architecture | Complete | Service + Repository Pattern |
| Frontend SPA | Complete | Vue 3.5 + TypeScript + Pinia |

### 1.2 Architecture Principles

1. **Multi-Tenant Isolation**: All domain tables include `tenant_id` with enforced scoping
2. **Clean Architecture**: Controller -> Service -> Repository -> Model
3. **API-First**: RESTful JSON APIs consumed by Vue SPA
4. **Type Safety**: TypeScript on frontend, strict typing on backend
5. **Audit Everything**: Complete activity logging for compliance

### 1.3 MVP Scope

11 modules delivering core CRM functionality:

```
Clientes -> Acompanantes -> Expedientes -> Tareas -> Seguimiento
                                |
                          Documentos <-> OneDrive/Google Drive
                                |
                           Agenda <-> Outlook/Google Calendar
```

---

## 2. System Architecture Overview

### 2.1 High-Level Architecture Diagram

```
+------------------------------------------------------------------+
|                         CLIENT LAYER                              |
|  +------------------------------------------------------------+  |
|  |  Vue 3.5 SPA (TypeScript)                                  |  |
|  |  - Pinia Stores (State Management)                         |  |
|  |  - Vue Router (Client Routing)                             |  |
|  |  - Vue I18n (ES/EN/FR)                                     |  |
|  |  - Tailwind CSS (UI)                                       |  |
|  +------------------------------------------------------------+  |
+------------------------------------------------------------------+
                              |
                    HTTPS (REST JSON API)
                              |
+------------------------------------------------------------------+
|                      APPLICATION LAYER                            |
|  +------------------------------------------------------------+  |
|  |  Laravel 12 API                                            |  |
|  |  +------------------+  +------------------+                 |  |
|  |  | Auth Middleware  |  | Tenant Scope    |                 |  |
|  |  | (Sanctum)        |  | Middleware      |                 |  |
|  |  +------------------+  +------------------+                 |  |
|  |                                                            |  |
|  |  +------------------+  +------------------+                 |  |
|  |  | API Controllers  |  | Form Requests   |                 |  |
|  |  | (Resource-based) |  | (Validation)    |                 |  |
|  |  +------------------+  +------------------+                 |  |
|  |                                                            |  |
|  |  +------------------+  +------------------+                 |  |
|  |  | Application      |  | Domain          |                 |  |
|  |  | Services         |  | Policies        |                 |  |
|  |  +------------------+  +------------------+                 |  |
|  |                                                            |  |
|  |  +------------------+  +------------------+                 |  |
|  |  | Repositories     |  | Eloquent        |                 |  |
|  |  | (Contracts)      |  | Models          |                 |  |
|  |  +------------------+  +------------------+                 |  |
|  +------------------------------------------------------------+  |
+------------------------------------------------------------------+
                              |
+------------------------------------------------------------------+
|                      INFRASTRUCTURE LAYER                         |
|  +----------------+  +----------------+  +-------------------+   |
|  | MySQL 8.0      |  | Redis          |  | Queue (database)  |   |
|  | (Primary DB)   |  | (Cache/Session)|  | (Jobs)            |   |
|  +----------------+  +----------------+  +-------------------+   |
+------------------------------------------------------------------+
                              |
+------------------------------------------------------------------+
|                      EXTERNAL INTEGRATIONS                        |
|  +----------------+  +----------------+  +-------------------+   |
|  | Microsoft      |  | Google         |  | SMTP              |   |
|  | Graph API      |  | APIs           |  | (Notifications)   |   |
|  | - OneDrive     |  | - Drive        |  |                   |   |
|  | - Outlook Cal  |  | - Calendar     |  |                   |   |
|  +----------------+  +----------------+  +-------------------+   |
+------------------------------------------------------------------+
```

### 2.2 Request Flow

```
Browser Request
    |
    v
[Vue Router] -- Client-side navigation (no reload)
    |
    v
[Pinia Store] -- Dispatch action
    |
    v
[Service Layer] -- API call via Axios
    |
    v
[Laravel API] /api/*
    |
    v
[Sanctum Auth] -- Verify session/token
    |
    v
[Tenant Scope] -- Inject tenant_id filter
    |
    v
[Controller] -- Authorize via Policy
    |
    v
[Form Request] -- Validate input
    |
    v
[Application Service] -- Business logic
    |
    v
[Repository] -- Data access
    |
    v
[Eloquent Model] -- Query with tenant scope
    |
    v
[MySQL] -- Execute query
```

### 2.3 Multi-Tenant Strategy

**Approach**: Shared database with `tenant_id` column on all domain tables.

```php
// Global scope applied to all tenant-aware models
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check() && auth()->user()->tenant_id) {
            $builder->where('tenant_id', auth()->user()->tenant_id);
        }
    }
}

// Trait for tenant-aware models
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (auth()->check()) {
                $model->tenant_id = auth()->user()->tenant_id;
            }
        });
    }
}
```

**Tenant Identification**: Single domain with tenant context from authenticated user.

---

## 3. Database Architecture

### 3.1 Entity Relationship Diagram

```
+------------------+       +-------------------+       +------------------+
|     tenants      |       |      users        |       |      roles       |
+------------------+       +-------------------+       +------------------+
| id (PK)          |<---+  | id (PK)           |       | id (PK)          |
| name             |    |  | tenant_id (FK)    |------>| name             |
| slug             |    |  | name              |       | guard_name       |
| settings (JSON)  |    |  | email             |       +------------------+
| ms_client_id     |    |  | password          |
| ms_client_secret |    |  | email_verified_at |
| google_client_id |    |  | two_factor_*      |
| google_client_*  |    |  | created_at        |
| is_active        |    |  +-------------------+
| created_at       |    |           |
| updated_at       |    |           | (model_has_roles)
+------------------+    |           v
        |               |  +-------------------+
        |               |  |   user_profiles   |
        |               |  +-------------------+
        |               |  | id (PK)           |
        |               |  | user_id (FK)      |
        |               |  | phone, address... |
        |               |  +-------------------+
        |               |
        +---------------+------------------------------------------+
        |               |                                          |
        v               v                                          v
+------------------+   +-------------------+              +------------------+
|     clients      |   |    companions     |              |      cases       |
+------------------+   +-------------------+              +------------------+
| id (PK)          |   | id (PK)           |              | id (PK)          |
| tenant_id (FK)   |   | tenant_id (FK)    |              | tenant_id (FK)   |
| user_id (FK)     |   | client_id (FK)    |------------->| client_id (FK)   |
| first_name       |   | first_name        |              | case_number      |
| last_name        |   | last_name         |              | case_type_id(FK) |
| email            |   | relationship      |              | status           |
| phone            |   | date_of_birth     |              | priority         |
| nationality      |   | passport_number   |              | progress         |
| canada_status    |   | nationality       |              | assigned_to (FK) |
| status           |   | notes             |              | hearing_date     |
| description      |   | created_at        |              | fda_deadline     |
| is_primary_*     |   +-------------------+              | description      |
| created_at       |                                      | created_at       |
+------------------+                                      +------------------+
        |                                                         |
        |                                                         |
        +-------------------------+-------------------------------+
                                  |
        +-------------------------+-------------------------+
        |                         |                         |
        v                         v                         v
+------------------+   +-------------------+   +------------------+
|      tasks       |   |    follow_ups     |   |    documents     |
+------------------+   +-------------------+   +------------------+
| id (PK)          |   | id (PK)           |   | id (PK)          |
| tenant_id (FK)   |   | tenant_id (FK)    |   | tenant_id (FK)   |
| case_id (FK)     |   | client_id (FK)    |   | case_id (FK)     |
| requester_id(FK) |   | case_id (FK)      |   | folder_id (FK)   |
| assigned_to (FK) |   | user_id (FK)      |   | uploaded_by (FK) |
| subject          |   | channel           |   | name             |
| description      |   | type              |   | original_name    |
| type             |   | contact_date      |   | mime_type        |
| priority         |   | duration_hours    |   | size             |
| status           |   | notes             |   | category         |
| due_date         |   | category          |   | storage_type     |
| estimated_hours  |   | created_at        |   | storage_path     |
| actual_hours     |   +-------------------+   | external_id      |
| document_id (FK) |                           | external_url     |
| created_at       |                           | created_at       |
+------------------+                           +------------------+
        |                                               |
        v                                               v
+--------------------+                        +--------------------+
| task_time_entries  |                        |  document_folders  |
+--------------------+                        +--------------------+
| id (PK)            |                        | id (PK)            |
| tenant_id (FK)     |                        | tenant_id (FK)     |
| task_id (FK)       |                        | case_id (FK)       |
| user_id (FK)       |                        | parent_id (FK)     |
| hours              |                        | name               |
| work_date          |                        | sort_order         |
| description        |                        | created_at         |
| created_at         |                        +--------------------+
+--------------------+

+------------------+   +-------------------+   +--------------------+
|     events       |   |   oauth_tokens    |   | event_participants |
+------------------+   +-------------------+   +--------------------+
| id (PK)          |   | id (PK)           |   | id (PK)            |
| tenant_id (FK)   |   | tenant_id (FK)    |   | event_id (FK)      |
| created_by (FK)  |   | user_id (FK)      |   | user_id (FK)       |
| client_id (FK)   |   | provider          |   | confirmed          |
| case_id (FK)     |   | access_token      |   | created_at         |
| title            |   | refresh_token     |   +--------------------+
| description      |   | expires_at        |
| start_date       |   | scopes (JSON)     |
| end_date         |   | created_at        |
| all_day          |   +-------------------+
| location         |
| category         |   +-------------------+
| sync_source      |   |    case_types     |
| external_id      |   +-------------------+
| last_synced_at   |   | id (PK)           |
| created_at       |   | tenant_id (FK)    |
+------------------+   | name              |
                       | code              |
                       | category          |
                       | description       |
                       | is_active         |
                       | created_at        |
                       +-------------------+
```

### 3.2 Core Tables Schema

#### 3.2.1 Tenants Table

```sql
CREATE TABLE tenants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    settings JSON DEFAULT NULL,
    -- Tenant-level OAuth credentials (optional, encrypted)
    ms_client_id TEXT DEFAULT NULL,
    ms_client_secret TEXT DEFAULT NULL,
    google_client_id TEXT DEFAULT NULL,
    google_client_secret TEXT DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

#### 3.2.2 Users Table (Modified)

```sql
ALTER TABLE users
ADD COLUMN tenant_id BIGINT UNSIGNED NULL AFTER id,
ADD CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL;
```

#### 3.2.3 Clients Table

```sql
CREATE TABLE clients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,

    -- Personal Information
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    nationality VARCHAR(255) NULL,
    second_nationality VARCHAR(255) NULL,
    language VARCHAR(255) DEFAULT 'es',
    second_language VARCHAR(255) NULL,
    date_of_birth DATE NULL,
    gender ENUM('male', 'female', 'other') NULL,
    passport_number VARCHAR(255) NULL,
    passport_country VARCHAR(255) NULL,
    passport_expiry_date DATE NULL,
    marital_status ENUM('single', 'married', 'divorced', 'widowed', 'common_law', 'separated') NULL,
    profession VARCHAR(255) NULL,
    description TEXT NULL,

    -- Contact Information
    email VARCHAR(255) NULL,
    residential_address VARCHAR(255) NULL,
    mailing_address VARCHAR(255) NULL,
    city VARCHAR(255) NULL,
    province VARCHAR(255) NULL,
    postal_code VARCHAR(255) NULL,
    country VARCHAR(255) NULL,
    phone VARCHAR(255) NULL,
    secondary_phone VARCHAR(255) NULL,

    -- Legal Status in Canada
    canada_status ENUM('asylum_seeker', 'refugee', 'temporary_resident', 'permanent_resident', 'citizen', 'visitor', 'student', 'worker', 'other') NULL,
    status_date DATE NULL,
    arrival_date DATE NULL,
    entry_point ENUM('airport', 'land_border', 'green_path') NULL,
    iuc VARCHAR(255) NULL COMMENT 'Unique Client Identifier',
    work_permit_number VARCHAR(255) NULL,
    study_permit_number VARCHAR(255) NULL,
    permit_expiry_date DATE NULL,
    other_status_1 VARCHAR(255) NULL,
    other_status_2 VARCHAR(255) NULL,

    -- Status
    status ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    is_primary_applicant BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    CONSTRAINT fk_clients_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_clients_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_clients_tenant_status (tenant_id, status),
    INDEX idx_clients_tenant_name (tenant_id, last_name, first_name),
    INDEX idx_clients_tenant_email (tenant_id, email),
    INDEX idx_clients_tenant_passport (tenant_id, passport_number)
);
```

#### 3.2.4 Companions Table

```sql
CREATE TABLE companions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,

    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    relationship ENUM('spouse', 'child', 'parent', 'sibling', 'other') DEFAULT 'other',
    date_of_birth DATE NULL,
    passport_number VARCHAR(255) NULL,
    nationality VARCHAR(255) NULL,
    notes TEXT NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    CONSTRAINT fk_companions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_companions_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,

    INDEX idx_companions_tenant_client (tenant_id, client_id)
);
```

#### 3.2.5 Case Types Table

```sql
CREATE TABLE case_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(255) UNIQUE NOT NULL,
    category ENUM('temporary_residence', 'permanent_residence', 'humanitarian') NOT NULL,
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_case_types_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,

    INDEX idx_case_types_tenant_category (tenant_id, category)
);

-- Default seeded case types:
-- Temporary Residence: TOURIST, STUDENT, WORK, EMIT
-- Permanent Residence: EXPRESS_ENTRY, ARRIMA, PEQ, PILOT, SKILLED_WORKER
-- Humanitarian: ASYLUM, ASYLUM_CLAIM, APPEAL, FEDERAL_COURT, ERAR, SPONSORSHIP
```

#### 3.2.6 Cases Table

```sql
CREATE TABLE cases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    case_number VARCHAR(255) UNIQUE NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    case_type_id BIGINT UNSIGNED NOT NULL,
    assigned_to BIGINT UNSIGNED NULL,

    -- Status & Priority
    status ENUM('active', 'inactive', 'archived', 'closed') DEFAULT 'active',
    priority ENUM('urgent', 'high', 'medium', 'low') DEFAULT 'medium',
    progress TINYINT UNSIGNED DEFAULT 0 COMMENT '0-100 percentage',
    language VARCHAR(255) DEFAULT 'es',

    -- Description
    description TEXT NULL,

    -- Key Dates
    hearing_date DATE NULL COMMENT 'Hearing date',
    fda_deadline DATE NULL COMMENT 'FDA deposit deadline',
    brown_sheet_date DATE NULL COMMENT 'Brown sheet date',
    evidence_deadline DATE NULL COMMENT 'Evidence submission deadline',

    -- Archive Info
    archive_box_number VARCHAR(255) NULL COMMENT 'Archive box number',

    -- Closure
    closed_at DATE NULL,
    closure_notes TEXT NULL COMMENT 'Closure notes',

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    CONSTRAINT fk_cases_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_cases_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_cases_type FOREIGN KEY (case_type_id) REFERENCES case_types(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cases_assigned FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_cases_tenant_status (tenant_id, status),
    INDEX idx_cases_tenant_client (tenant_id, client_id),
    INDEX idx_cases_tenant_assigned (tenant_id, assigned_to),
    INDEX idx_cases_tenant_priority (tenant_id, priority),
    INDEX idx_cases_tenant_hearing (tenant_id, hearing_date)
);
```

#### 3.2.7 Tasks Table

```sql
CREATE TABLE tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    case_id BIGINT UNSIGNED NULL,
    requester_id BIGINT UNSIGNED NOT NULL,
    assigned_to BIGINT UNSIGNED NULL,

    subject VARCHAR(255) NOT NULL,
    description TEXT NULL,
    type ENUM('translation', 'case_creation', 'accounting', 'filing', 'document', 'other') DEFAULT 'other',
    priority ENUM('urgent', 'high', 'medium', 'low') DEFAULT 'medium',
    status ENUM('new', 'assigned', 'in_progress', 'rejected', 'resolved', 'closed') DEFAULT 'new',

    due_date DATETIME NULL,
    estimated_hours DECIMAL(5, 2) NULL,
    actual_hours DECIMAL(5, 2) NULL,

    -- Document attachment
    document_id BIGINT UNSIGNED NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    CONSTRAINT fk_tasks_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_tasks_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    CONSTRAINT fk_tasks_requester FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tasks_assigned FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_tasks_tenant_status (tenant_id, status),
    INDEX idx_tasks_tenant_assigned (tenant_id, assigned_to),
    INDEX idx_tasks_tenant_case (tenant_id, case_id),
    INDEX idx_tasks_tenant_due (tenant_id, due_date),
    INDEX idx_tasks_tenant_priority (tenant_id, priority)
);
```

#### 3.2.8 Task Time Entries Table

```sql
CREATE TABLE task_time_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    task_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,

    hours DECIMAL(5, 2) NOT NULL,
    work_date DATE NOT NULL,
    description TEXT NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_time_entries_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_time_entries_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_time_entries_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    INDEX idx_time_entries_tenant_task (tenant_id, task_id),
    INDEX idx_time_entries_tenant_user (tenant_id, user_id),
    INDEX idx_time_entries_tenant_date (tenant_id, work_date)
);
```

#### 3.2.9 Follow Ups Table

```sql
CREATE TABLE follow_ups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    case_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NOT NULL,

    channel ENUM('phone', 'email', 'meeting', 'video_call', 'other') DEFAULT 'phone',
    type ENUM('task', 'follow_up', 'note') DEFAULT 'follow_up',
    contact_date DATETIME NOT NULL,
    duration_hours DECIMAL(5, 2) NULL,
    notes TEXT NULL,
    category VARCHAR(255) NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    CONSTRAINT fk_follow_ups_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_follow_ups_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_follow_ups_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    CONSTRAINT fk_follow_ups_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    INDEX idx_follow_ups_tenant_client (tenant_id, client_id),
    INDEX idx_follow_ups_tenant_case (tenant_id, case_id),
    INDEX idx_follow_ups_tenant_user (tenant_id, user_id),
    INDEX idx_follow_ups_tenant_date (tenant_id, contact_date)
);
```

#### 3.2.10 Document Folders Table

```sql
CREATE TABLE document_folders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    case_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_folders_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_folders_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    CONSTRAINT fk_folders_parent FOREIGN KEY (parent_id) REFERENCES document_folders(id) ON DELETE CASCADE,

    INDEX idx_folders_tenant_case (tenant_id, case_id)
);
```

#### 3.2.11 Documents Table

```sql
CREATE TABLE documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    case_id BIGINT UNSIGNED NOT NULL,
    folder_id BIGINT UNSIGNED NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,

    name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(255) NULL,
    size BIGINT UNSIGNED NULL,
    category ENUM('admission', 'history', 'evidence', 'hearing', 'contract', 'other') DEFAULT 'other',
    storage_type ENUM('local', 'onedrive', 'google_drive') DEFAULT 'local',
    storage_path VARCHAR(255) NULL,
    external_id VARCHAR(255) NULL COMMENT 'ID in OneDrive/Google Drive',
    external_url VARCHAR(255) NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    CONSTRAINT fk_documents_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_documents_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    CONSTRAINT fk_documents_folder FOREIGN KEY (folder_id) REFERENCES document_folders(id) ON DELETE SET NULL,
    CONSTRAINT fk_documents_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,

    INDEX idx_documents_tenant_case (tenant_id, case_id),
    INDEX idx_documents_tenant_category (tenant_id, category),
    INDEX idx_documents_storage (storage_type, external_id)
);
```

#### 3.2.12 OAuth Tokens Table

```sql
CREATE TABLE oauth_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,

    provider ENUM('microsoft', 'google') NOT NULL,
    access_token TEXT NOT NULL,
    refresh_token TEXT NULL,
    expires_at DATETIME NOT NULL,
    scopes JSON NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_oauth_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_oauth_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    UNIQUE INDEX uq_oauth_user_provider (user_id, provider),
    INDEX idx_oauth_tenant_provider (tenant_id, provider)
);
```

#### 3.2.13 Events Table

```sql
CREATE TABLE events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NULL,
    case_id BIGINT UNSIGNED NULL,

    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    all_day BOOLEAN DEFAULT FALSE,
    location VARCHAR(255) NULL,
    category VARCHAR(255) NULL,

    -- External calendar sync
    sync_source ENUM('local', 'outlook', 'google') DEFAULT 'local',
    external_id VARCHAR(255) NULL,
    last_synced_at DATETIME NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    CONSTRAINT fk_events_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_events_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_events_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_events_case FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE SET NULL,

    INDEX idx_events_tenant_start (tenant_id, start_date),
    INDEX idx_events_tenant_creator (tenant_id, created_by),
    INDEX idx_events_sync (sync_source, external_id)
);
```

#### 3.2.14 Event Participants Table

```sql
CREATE TABLE event_participants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    confirmed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_participants_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_participants_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    UNIQUE INDEX uq_event_user (event_id, user_id)
);
```

### 3.3 Indexes Strategy

| Table | Index Purpose | Columns |
|-------|---------------|---------|
| All domain tables | Tenant isolation | `tenant_id` |
| clients | Search by name | `tenant_id, last_name, first_name` |
| clients | Email lookup | `tenant_id, email` |
| clients | Passport lookup | `tenant_id, passport_number` |
| cases | Status filtering | `tenant_id, status` |
| cases | Priority filtering | `tenant_id, priority` |
| cases | Hearing dates | `tenant_id, hearing_date` |
| tasks | Due date queries | `tenant_id, due_date` |
| tasks | Priority filtering | `tenant_id, priority` |
| tasks | Status filtering | `tenant_id, status` |
| follow_ups | Date queries | `tenant_id, contact_date` |
| events | Calendar range queries | `tenant_id, start_date` |
| documents | External sync | `storage_type, external_id` |

---

## 4. Backend Architecture

### 4.1 Directory Structure

```
app/
├── Console/
│   └── Commands/
│       ├── SyncCalendarEvents.php
│       └── RefreshOAuthTokens.php
│
├── Enums/
│   ├── CanalTipo.php
│   ├── ClienteEstado.php
│   ├── ExpedienteEstado.php
│   ├── Parentesco.php
│   ├── Prioridad.php
│   ├── Provider.php
│   └── TareaEstado.php
│
├── Events/
│   ├── ExpedienteCreated.php
│   ├── TareaCompleted.php
│   └── DocumentoLinked.php
│
├── Exceptions/
│   ├── Handler.php
│   ├── OAuthException.php
│   └── TenantException.php
│
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── AuthController.php          # Existing
│   │   │   ├── UserController.php          # Existing
│   │   │   ├── RoleController.php          # Existing
│   │   │   ├── ProfileController.php       # Existing
│   │   │   │
│   │   │   ├── ClienteController.php       # NEW
│   │   │   ├── AcompananteController.php   # NEW
│   │   │   ├── ExpedienteController.php    # NEW
│   │   │   ├── TareaController.php         # NEW
│   │   │   ├── SeguimientoController.php   # NEW
│   │   │   ├── DocumentoController.php     # NEW
│   │   │   ├── CarpetaController.php       # NEW
│   │   │   ├── EventoController.php        # NEW
│   │   │   │
│   │   │   ├── Integration/
│   │   │   │   ├── OneDriveController.php  # NEW
│   │   │   │   ├── GoogleDriveController.php # NEW
│   │   │   │   ├── OutlookCalendarController.php # NEW
│   │   │   │   ├── GoogleCalendarController.php  # NEW
│   │   │   │   └── OAuthController.php     # NEW
│   │   │   │
│   │   │   ├── Dashboard/
│   │   │   │   └── DashboardController.php # NEW
│   │   │   │
│   │   │   └── Admin/
│   │   │       └── TenantController.php    # NEW
│   │   │
│   │   └── Controller.php
│   │
│   ├── Middleware/
│   │   ├── Authenticate.php                # Existing
│   │   ├── TenantScope.php                 # NEW
│   │   └── EnsureIntegrationConnected.php  # NEW
│   │
│   └── Requests/
│       ├── Cliente/
│       │   ├── StoreClienteRequest.php
│       │   └── UpdateClienteRequest.php
│       ├── Acompanante/
│       ├── Expediente/
│       ├── Tarea/
│       ├── Seguimiento/
│       ├── Documento/
│       └── Evento/
│
├── Jobs/
│   ├── SyncCalendarEvent.php
│   ├── RefreshOAuthToken.php
│   └── CreateExpedienteFolders.php
│
├── Listeners/
│   ├── CreateExpedienteTasks.php
│   └── NotifyTaskAssignment.php
│
├── Models/
│   ├── User.php                    # Modified (add tenant_id)
│   ├── Tenant.php                  # NEW
│   ├── Cliente.php                 # NEW
│   ├── Acompanante.php             # NEW
│   ├── Expediente.php              # NEW
│   ├── Tarea.php                   # NEW
│   ├── TiempoTarea.php             # NEW
│   ├── Seguimiento.php             # NEW
│   ├── CarpetaDocumento.php        # NEW
│   ├── Documento.php               # NEW
│   ├── Evento.php                  # NEW
│   ├── OAuthToken.php              # NEW
│   ├── TipoCaso.php                # NEW
│   │
│   ├── Traits/
│   │   └── BelongsToTenant.php     # NEW
│   │
│   └── Scopes/
│       └── TenantScope.php         # NEW
│
├── Notifications/
│   ├── TareaAsignada.php           # NEW
│   ├── TareaVencimiento.php        # NEW
│   └── ExpedienteActualizado.php   # NEW
│
├── Policies/
│   ├── UserPolicy.php              # Existing
│   ├── RolePolicy.php              # Existing
│   ├── ClientePolicy.php           # NEW
│   ├── ExpedientePolicy.php        # NEW
│   ├── TareaPolicy.php             # NEW
│   └── DocumentoPolicy.php         # NEW
│
├── Providers/
│   ├── AppServiceProvider.php
│   ├── AuthServiceProvider.php
│   ├── RepositoryServiceProvider.php  # Existing - extend
│   └── IntegrationServiceProvider.php # NEW
│
├── Repositories/
│   ├── Contracts/
│   │   ├── UserRepositoryInterface.php      # Existing
│   │   ├── ClienteRepositoryInterface.php   # NEW
│   │   ├── ExpedienteRepositoryInterface.php # NEW
│   │   ├── TareaRepositoryInterface.php     # NEW
│   │   ├── SeguimientoRepositoryInterface.php # NEW
│   │   ├── DocumentoRepositoryInterface.php # NEW
│   │   └── EventoRepositoryInterface.php    # NEW
│   │
│   └── Eloquent/
│       ├── UserRepository.php               # Existing
│       ├── ClienteRepository.php            # NEW
│       ├── ExpedienteRepository.php         # NEW
│       ├── TareaRepository.php              # NEW
│       ├── SeguimientoRepository.php        # NEW
│       ├── DocumentoRepository.php          # NEW
│       └── EventoRepository.php             # NEW
│
└── Services/
    ├── Auth/
    │   └── AuthService.php                  # Existing
    │
    ├── User/
    │   └── UserService.php                  # Existing
    │
    ├── Cliente/
    │   └── ClienteService.php               # NEW
    │
    ├── Expediente/
    │   ├── ExpedienteService.php            # NEW
    │   └── ExpedienteWizardService.php      # NEW
    │
    ├── Tarea/
    │   ├── TareaService.php                 # NEW
    │   └── TiempoService.php                # NEW
    │
    ├── Seguimiento/
    │   └── SeguimientoService.php           # NEW
    │
    ├── Documento/
    │   ├── DocumentoService.php             # NEW
    │   └── CarpetaService.php               # NEW
    │
    ├── Evento/
    │   └── EventoService.php                # NEW
    │
    ├── Integration/
    │   ├── OAuthService.php                 # NEW
    │   ├── OneDriveService.php              # NEW
    │   ├── GoogleDriveService.php           # NEW
    │   ├── OutlookCalendarService.php       # NEW
    │   ├── GoogleCalendarService.php        # NEW
    │   │
    │   └── Contracts/
    │       ├── CloudStorageInterface.php    # NEW
    │       └── CalendarInterface.php        # NEW
    │
    └── Dashboard/
        └── DashboardService.php             # NEW
```

### 4.2 Service Layer Pattern

Following the existing pattern in `UserService.php`:

```php
<?php

namespace App\Services\Expediente;

use App\Models\Expediente;
use App\Models\TipoCaso;
use App\Repositories\Contracts\ExpedienteRepositoryInterface;
use App\Services\Tarea\TareaService;
use App\Services\Documento\CarpetaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpedienteService
{
    public function __construct(
        private ExpedienteRepositoryInterface $expedienteRepository,
        private TareaService $tareaService,
        private CarpetaService $carpetaService
    ) {}

    public function listExpedientes(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->expedienteRepository->paginate($filters, $perPage);
    }

    public function createExpediente(array $data): Expediente
    {
        return DB::transaction(function () use ($data) {
            // Generate unique code
            $data['codigo'] = $this->generateCodigo($data['tipo_caso']);
            $data['created_by'] = Auth::id();

            // Create expediente
            $expediente = $this->expedienteRepository->create($data);

            // Attach acompanantes if provided
            if (!empty($data['acompanantes'])) {
                $expediente->acompanantes()->attach($data['acompanantes']);
            }

            // Get tipo_caso configuration
            $tipoCaso = TipoCaso::where('codigo', $data['tipo_caso'])->first();

            if ($tipoCaso) {
                // Create default tasks from template
                if ($tipoCaso->plantilla_tareas) {
                    $this->tareaService->createFromTemplate(
                        $expediente,
                        $tipoCaso->plantilla_tareas
                    );
                }

                // Create folder structure from template
                if ($tipoCaso->plantilla_carpetas) {
                    $this->carpetaService->createFromTemplate(
                        $expediente,
                        $tipoCaso->plantilla_carpetas
                    );
                }
            }

            // Log activity
            activity()
                ->causedBy(Auth::user())
                ->performedOn($expediente)
                ->withProperties(['tipo_caso' => $data['tipo_caso']])
                ->log('Created expediente: ' . $expediente->codigo);

            return $expediente->load(['cliente', 'acompanantes', 'consultor']);
        });
    }

    private function generateCodigo(string $tipoCaso): string
    {
        $prefix = strtoupper(substr($tipoCaso, 0, 3));
        $year = date('Y');
        $sequence = $this->expedienteRepository->getNextSequence($tipoCaso, $year);

        return sprintf('%s-%d-%04d', $prefix, $year, $sequence);
    }
}
```

### 4.3 Repository Pattern

Following the existing pattern:

```php
<?php

namespace App\Repositories\Contracts;

use App\Models\Expediente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ExpedienteRepositoryInterface
{
    public function findById(int $id): ?Expediente;
    public function findByCodigo(string $codigo): ?Expediente;
    public function create(array $data): Expediente;
    public function update(Expediente $expediente, array $data): Expediente;
    public function delete(Expediente $expediente): bool;
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getNextSequence(string $tipoCaso, int $year): int;
    public function getByCliente(int $clienteId): array;
    public function getByConsultor(int $consultorId, array $filters = []): LengthAwarePaginator;
}
```

### 4.4 API Routes Structure

```php
<?php
// routes/api.php

use Illuminate\Support\Facades\Route;

// Existing routes...

// Protected routes with tenant scope
Route::middleware(['auth:sanctum', 'throttle:api', 'tenant.scope'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Clientes
    Route::apiResource('clientes', ClienteController::class);
    Route::post('/clientes/{cliente}/convert', [ClienteController::class, 'convertToActive']);
    Route::get('/clientes/{cliente}/expedientes', [ClienteController::class, 'expedientes']);

    // Acompanantes
    Route::apiResource('clientes.acompanantes', AcompananteController::class)->shallow();

    // Expedientes
    Route::apiResource('expedientes', ExpedienteController::class);
    Route::get('/expedientes/{expediente}/timeline', [ExpedienteController::class, 'timeline']);
    Route::patch('/expedientes/{expediente}/etapa', [ExpedienteController::class, 'updateEtapa']);
    Route::patch('/expedientes/{expediente}/asignar', [ExpedienteController::class, 'asignar']);

    // Tareas
    Route::apiResource('tareas', TareaController::class);
    Route::get('/expedientes/{expediente}/tareas', [TareaController::class, 'byExpediente']);
    Route::patch('/tareas/{tarea}/complete', [TareaController::class, 'complete']);
    Route::post('/tareas/{tarea}/tiempo', [TareaController::class, 'logTime']);
    Route::get('/tareas/dashboard', [TareaController::class, 'dashboard']);

    // Seguimientos
    Route::apiResource('expedientes.seguimientos', SeguimientoController::class)->shallow();
    Route::get('/seguimientos/search', [SeguimientoController::class, 'search']);

    // Documentos & Carpetas
    Route::apiResource('expedientes.carpetas', CarpetaController::class)->shallow();
    Route::apiResource('expedientes.documentos', DocumentoController::class)->shallow();
    Route::post('/documentos/{documento}/unlink', [DocumentoController::class, 'unlink']);

    // Eventos (Agenda)
    Route::apiResource('eventos', EventoController::class);
    Route::get('/eventos/calendar', [EventoController::class, 'calendar']);

    // Integrations - OAuth
    Route::prefix('integrations')->group(function () {
        Route::get('/status', [OAuthController::class, 'status']);

        // Microsoft
        Route::get('/microsoft/connect', [OAuthController::class, 'microsoftRedirect']);
        Route::get('/microsoft/callback', [OAuthController::class, 'microsoftCallback']);
        Route::delete('/microsoft/disconnect', [OAuthController::class, 'microsoftDisconnect']);

        // Google
        Route::get('/google/connect', [OAuthController::class, 'googleRedirect']);
        Route::get('/google/callback', [OAuthController::class, 'googleCallback']);
        Route::delete('/google/disconnect', [OAuthController::class, 'googleDisconnect']);

        // OneDrive
        Route::get('/onedrive/browse', [OneDriveController::class, 'browse']);
        Route::post('/onedrive/link', [OneDriveController::class, 'linkFile']);

        // Google Drive
        Route::get('/google-drive/browse', [GoogleDriveController::class, 'browse']);
        Route::post('/google-drive/link', [GoogleDriveController::class, 'linkFile']);

        // Outlook Calendar
        Route::get('/outlook/calendars', [OutlookCalendarController::class, 'listCalendars']);
        Route::post('/outlook/sync', [OutlookCalendarController::class, 'sync']);

        // Google Calendar
        Route::get('/google-calendar/calendars', [GoogleCalendarController::class, 'listCalendars']);
        Route::post('/google-calendar/sync', [GoogleCalendarController::class, 'sync']);
    });

    // Configuration
    Route::prefix('config')->group(function () {
        Route::get('/tipos-caso', [ConfigController::class, 'tiposCaso']);
        Route::get('/etapas/{tipoCaso}', [ConfigController::class, 'etapas']);
    });
});

// Admin routes (super admin only)
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::apiResource('tenants', TenantController::class);
});
```

---

## 5. Frontend Architecture

### 5.1 Directory Structure

```
resources/js/src/
├── assets/
│   └── css/
│
├── components/
│   ├── layout/                    # Existing
│   │   ├── Header.vue
│   │   ├── Sidebar.vue
│   │   └── Footer.vue
│   │
│   ├── common/                    # NEW - Reusable components
│   │   ├── DataTable.vue
│   │   ├── SearchInput.vue
│   │   ├── StatusBadge.vue
│   │   ├── PriorityBadge.vue
│   │   ├── EmptyState.vue
│   │   ├── LoadingSpinner.vue
│   │   ├── ConfirmModal.vue
│   │   └── Pagination.vue
│   │
│   ├── clientes/                  # NEW
│   │   ├── ClienteCard.vue
│   │   ├── ClienteForm.vue
│   │   ├── ClienteSearch.vue
│   │   └── AcompananteForm.vue
│   │
│   ├── expedientes/               # NEW
│   │   ├── ExpedienteCard.vue
│   │   ├── ExpedienteWizard/
│   │   │   ├── Step1TipoCaso.vue
│   │   │   ├── Step2Cliente.vue
│   │   │   ├── Step3Acompanantes.vue
│   │   │   ├── Step4Detalles.vue
│   │   │   └── Step5Resumen.vue
│   │   ├── ExpedienteTimeline.vue
│   │   ├── EtapaPipeline.vue
│   │   └── ExpedienteDetailTabs.vue
│   │
│   ├── tareas/                    # NEW
│   │   ├── TareaCard.vue
│   │   ├── TareaForm.vue
│   │   ├── TareaKanban.vue
│   │   ├── TareaList.vue
│   │   └── TimeLogModal.vue
│   │
│   ├── seguimientos/              # NEW
│   │   ├── SeguimientoEntry.vue
│   │   ├── SeguimientoForm.vue
│   │   └── SeguimientoTimeline.vue
│   │
│   ├── documentos/                # NEW
│   │   ├── DocumentoCard.vue
│   │   ├── CarpetaTree.vue
│   │   ├── FileBrowser.vue
│   │   └── LinkDocumentModal.vue
│   │
│   ├── agenda/                    # NEW
│   │   ├── CalendarView.vue
│   │   ├── EventoForm.vue
│   │   └── EventoDetail.vue
│   │
│   └── integrations/              # NEW
│       ├── IntegrationStatus.vue
│       ├── OneDriveBrowser.vue
│       ├── GoogleDriveBrowser.vue
│       └── CalendarSyncSettings.vue
│
├── composables/                   # Existing + NEW
│   ├── use-meta.ts                # Existing
│   ├── useNotification.ts         # Existing
│   ├── usePagination.ts           # NEW
│   ├── useFilters.ts              # NEW
│   ├── useDebounce.ts             # NEW
│   └── useIntegration.ts          # NEW
│
├── layouts/
│   ├── app-layout.vue             # Existing
│   └── auth-layout.vue            # Existing
│
├── locales/
│   ├── en.json                    # Extend with CRM terms
│   ├── es.json                    # Extend with CRM terms
│   └── fr.json                    # Extend with CRM terms
│
├── router/
│   └── index.ts                   # Extend with new routes
│
├── services/                      # Existing + NEW
│   ├── api.ts                     # Existing
│   ├── authService.ts             # Existing
│   ├── userService.ts             # Existing
│   ├── clienteService.ts          # NEW
│   ├── expedienteService.ts       # NEW
│   ├── tareaService.ts            # NEW
│   ├── seguimientoService.ts      # NEW
│   ├── documentoService.ts        # NEW
│   ├── eventoService.ts           # NEW
│   ├── integrationService.ts      # NEW
│   └── dashboardService.ts        # NEW
│
├── stores/                        # Existing + NEW
│   ├── index.ts                   # Existing (app settings)
│   ├── auth.ts                    # Existing
│   ├── user.ts                    # Existing
│   ├── cliente.ts                 # NEW
│   ├── expediente.ts              # NEW
│   ├── tarea.ts                   # NEW
│   ├── seguimiento.ts             # NEW
│   ├── documento.ts               # NEW
│   ├── evento.ts                  # NEW
│   ├── integration.ts             # NEW
│   └── dashboard.ts               # NEW
│
├── types/                         # Existing + NEW
│   ├── user.ts                    # Existing
│   ├── pagination.ts              # Existing
│   ├── cliente.ts                 # NEW
│   ├── acompanante.ts             # NEW
│   ├── expediente.ts              # NEW
│   ├── tarea.ts                   # NEW
│   ├── seguimiento.ts             # NEW
│   ├── documento.ts               # NEW
│   ├── evento.ts                  # NEW
│   └── integration.ts             # NEW
│
└── views/
    ├── index.vue                  # Modify - CRM Dashboard
    │
    ├── clientes/                  # NEW
    │   ├── index.vue              # Client list
    │   ├── show.vue               # Client detail
    │   └── create.vue             # Create client
    │
    ├── expedientes/               # NEW
    │   ├── index.vue              # Case list
    │   ├── show.vue               # Case detail (tabbed)
    │   └── create.vue             # Case wizard
    │
    ├── tareas/                    # NEW
    │   ├── index.vue              # My tasks / All tasks
    │   └── kanban.vue             # Kanban view
    │
    ├── agenda/                    # NEW
    │   └── index.vue              # Calendar view
    │
    └── settings/                  # NEW
        └── integrations.vue       # Manage integrations
```

### 5.2 State Management (Pinia Stores)

Following the existing `user.ts` pattern:

```typescript
// stores/expediente.ts
import { defineStore } from 'pinia';
import expedienteService, { type ExpedienteFilters } from '@/services/expedienteService';
import type { Expediente, CreateExpedienteData, UpdateExpedienteData } from '@/types/expediente';
import type { PaginationMeta, PaginationLinks } from '@/types/pagination';

interface ExpedienteState {
    expedientes: Expediente[];
    currentExpediente: Expediente | null;
    meta: PaginationMeta | null;
    links: PaginationLinks | null;
    filters: ExpedienteFilters;
    isLoading: boolean;
    error: string | null;
}

export const useExpedienteStore = defineStore('expediente', {
    state: (): ExpedienteState => ({
        expedientes: [],
        currentExpediente: null,
        meta: null,
        links: null,
        filters: {
            search: '',
            estado: '',
            tipo_caso: '',
            consultor_id: null,
            sort_by: 'created_at',
            sort_direction: 'desc',
            per_page: 15,
            page: 1,
        },
        isLoading: false,
        error: null,
    }),

    getters: {
        getById: (state) => (id: number): Expediente | undefined => {
            return state.expedientes.find((exp) => exp.id === id);
        },

        totalExpedientes: (state): number => state.meta?.total ?? 0,

        expedientesByEstado: (state) => (estado: string): Expediente[] => {
            return state.expedientes.filter((exp) => exp.estado === estado);
        },
    },

    actions: {
        async fetchExpedientes(filters?: Partial<ExpedienteFilters>) {
            this.isLoading = true;
            this.error = null;

            if (filters) {
                this.filters = { ...this.filters, ...filters };
            }

            try {
                const response = await expedienteService.getExpedientes(this.filters);
                this.expedientes = response.data;
                this.meta = response.meta;
                this.links = response.links;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to fetch expedientes';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async fetchExpediente(id: number) {
            this.isLoading = true;
            this.error = null;

            try {
                this.currentExpediente = await expedienteService.getExpediente(id);
                return this.currentExpediente;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to fetch expediente';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async createExpediente(data: CreateExpedienteData) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await expedienteService.createExpediente(data);
                await this.fetchExpedientes();
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to create expediente';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async updateExpediente(id: number, data: UpdateExpedienteData) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await expedienteService.updateExpediente(id, data);
                const index = this.expedientes.findIndex((e) => e.id === id);
                if (index !== -1) {
                    this.expedientes[index] = response.expediente;
                }
                if (this.currentExpediente?.id === id) {
                    this.currentExpediente = response.expediente;
                }
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to update expediente';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async updateEtapa(id: number, etapa: string) {
            try {
                const response = await expedienteService.updateEtapa(id, etapa);
                // Update local state
                const exp = this.expedientes.find((e) => e.id === id);
                if (exp) exp.etapa = etapa;
                if (this.currentExpediente?.id === id) {
                    this.currentExpediente.etapa = etapa;
                }
                return response;
            } catch (error: any) {
                throw error;
            }
        },

        setFilters(filters: Partial<ExpedienteFilters>) {
            this.filters = { ...this.filters, ...filters, page: 1 };
        },

        resetFilters() {
            this.filters = {
                search: '',
                estado: '',
                tipo_caso: '',
                consultor_id: null,
                sort_by: 'created_at',
                sort_direction: 'desc',
                per_page: 15,
                page: 1,
            };
        },

        clearCurrentExpediente() {
            this.currentExpediente = null;
        },
    },
});
```

### 5.3 TypeScript Interfaces

```typescript
// types/expediente.ts
import type { Cliente } from './cliente';
import type { Acompanante } from './acompanante';
import type { User } from './user';

export type ExpedienteEstado = 'borrador' | 'activo' | 'pausado' | 'completado' | 'cancelado' | 'archivado';
export type Prioridad = 'baja' | 'media' | 'alta' | 'urgente';

export interface Expediente {
    id: number;
    codigo: string;
    tipo_caso: string;
    subtipo_caso: string | null;
    numero_referencia_ircc: string | null;
    numero_referencia_cisr: string | null;
    etapa: string;
    estado: ExpedienteEstado;
    prioridad: Prioridad;
    fecha_inicio: string | null;
    fecha_objetivo: string | null;
    fecha_cierre: string | null;
    notas: string | null;
    cliente_id: number;
    consultor_id: number;
    asignado_id: number | null;
    created_at: string;
    updated_at: string;

    // Relations (when loaded)
    cliente?: Cliente;
    consultor?: User;
    asignado?: User;
    acompanantes?: Acompanante[];
    tareas_count?: number;
    tareas_pendientes_count?: number;
    documentos_count?: number;
}

export interface CreateExpedienteData {
    cliente_id: number;
    tipo_caso: string;
    subtipo_caso?: string;
    prioridad?: Prioridad;
    consultor_id?: number;
    asignado_id?: number;
    fecha_inicio?: string;
    fecha_objetivo?: string;
    notas?: string;
    acompanantes?: number[];
}

export interface UpdateExpedienteData {
    tipo_caso?: string;
    subtipo_caso?: string;
    numero_referencia_ircc?: string;
    numero_referencia_cisr?: string;
    etapa?: string;
    estado?: ExpedienteEstado;
    prioridad?: Prioridad;
    asignado_id?: number;
    fecha_objetivo?: string;
    notas?: string;
}

export interface ExpedienteFilters {
    search: string;
    estado: string;
    tipo_caso: string;
    consultor_id: number | null;
    sort_by: string;
    sort_direction: 'asc' | 'desc';
    per_page: number;
    page: number;
}
```

### 5.4 Vue Router Configuration

```typescript
// router/index.ts (additions)
const crmRoutes = [
    // Dashboard (replace existing index)
    {
        path: '/',
        name: 'dashboard',
        component: () => import('@/views/index.vue'),
        meta: { requiresAuth: true }
    },

    // Clientes
    {
        path: '/clientes',
        name: 'clientes',
        component: () => import('@/views/clientes/index.vue'),
        meta: { requiresAuth: true, permission: 'clientes.view' }
    },
    {
        path: '/clientes/create',
        name: 'clientes-create',
        component: () => import('@/views/clientes/create.vue'),
        meta: { requiresAuth: true, permission: 'clientes.create' }
    },
    {
        path: '/clientes/:id',
        name: 'clientes-show',
        component: () => import('@/views/clientes/show.vue'),
        meta: { requiresAuth: true, permission: 'clientes.view' }
    },

    // Expedientes
    {
        path: '/expedientes',
        name: 'expedientes',
        component: () => import('@/views/expedientes/index.vue'),
        meta: { requiresAuth: true, permission: 'expedientes.view' }
    },
    {
        path: '/expedientes/create',
        name: 'expedientes-create',
        component: () => import('@/views/expedientes/create.vue'),
        meta: { requiresAuth: true, permission: 'expedientes.create' }
    },
    {
        path: '/expedientes/:id',
        name: 'expedientes-show',
        component: () => import('@/views/expedientes/show.vue'),
        meta: { requiresAuth: true, permission: 'expedientes.view' }
    },

    // Tareas
    {
        path: '/tareas',
        name: 'tareas',
        component: () => import('@/views/tareas/index.vue'),
        meta: { requiresAuth: true, permission: 'tareas.view' }
    },
    {
        path: '/tareas/kanban',
        name: 'tareas-kanban',
        component: () => import('@/views/tareas/kanban.vue'),
        meta: { requiresAuth: true, permission: 'tareas.view' }
    },

    // Agenda
    {
        path: '/agenda',
        name: 'agenda',
        component: () => import('@/views/agenda/index.vue'),
        meta: { requiresAuth: true, permission: 'eventos.view' }
    },

    // Settings - Integrations
    {
        path: '/settings/integrations',
        name: 'settings-integrations',
        component: () => import('@/views/settings/integrations.vue'),
        meta: { requiresAuth: true }
    },
];
```

---

## 6. Integration Architecture

### 6.1 OAuth Flow Architecture

```
+----------------+     +----------------+     +-------------------+
|   Vue SPA      |     |  Laravel API   |     |  OAuth Provider   |
|                |     |                |     |  (MS/Google)      |
+----------------+     +----------------+     +-------------------+
        |                      |                       |
        | 1. Click Connect     |                       |
        |--------------------->|                       |
        |                      |                       |
        |  2. Return auth URL  |                       |
        |<---------------------|                       |
        |                      |                       |
        | 3. Redirect to OAuth |                       |
        |------------------------------------------>---|
        |                      |                       |
        |                      |    4. User consents   |
        |                      |<----------------------|
        |                      |                       |
        | 5. Callback with code|                       |
        |--------------------->|                       |
        |                      | 6. Exchange code      |
        |                      |---------------------->|
        |                      |                       |
        |                      | 7. Return tokens      |
        |                      |<----------------------|
        |                      |                       |
        |                      | 8. Store encrypted    |
        |                      |    tokens in DB       |
        |                      |                       |
        | 9. Success response  |                       |
        |<---------------------|                       |
        |                      |                       |
```

### 6.2 OAuth Service Implementation

```php
<?php

namespace App\Services\Integration;

use App\Models\OAuthToken;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class OAuthService
{
    public function getMicrosoftAuthUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => config('services.microsoft.client_id'),
            'redirect_uri' => config('services.microsoft.redirect'),
            'response_type' => 'code',
            'scope' => implode(' ', [
                'openid',
                'profile',
                'email',
                'offline_access',
                'Files.ReadWrite.All',
                'Calendars.ReadWrite',
            ]),
            'state' => $state,
            'prompt' => 'consent',
        ]);

        return 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . $params;
    }

    public function handleMicrosoftCallback(User $user, string $code): OAuthToken
    {
        $response = Http::asForm()->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
            'client_id' => config('services.microsoft.client_id'),
            'client_secret' => config('services.microsoft.client_secret'),
            'code' => $code,
            'redirect_uri' => config('services.microsoft.redirect'),
            'grant_type' => 'authorization_code',
        ]);

        if ($response->failed()) {
            throw new OAuthException('Failed to exchange code for tokens');
        }

        $data = $response->json();

        return $this->storeToken($user, 'microsoft', $data);
    }

    public function refreshMicrosoftToken(OAuthToken $token): OAuthToken
    {
        $response = Http::asForm()->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
            'client_id' => config('services.microsoft.client_id'),
            'client_secret' => config('services.microsoft.client_secret'),
            'refresh_token' => Crypt::decryptString($token->refresh_token),
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            $token->delete();
            throw new OAuthException('Token refresh failed - reconnection required');
        }

        $data = $response->json();

        return $this->updateToken($token, $data);
    }

    public function getValidToken(User $user, string $provider): ?string
    {
        $token = OAuthToken::where('user_id', $user->id)
            ->where('provider', $provider)
            ->first();

        if (!$token) {
            return null;
        }

        // Refresh if expires within 5 minutes
        if (Carbon::parse($token->expires_at)->subMinutes(5)->isPast()) {
            $token = $provider === 'microsoft'
                ? $this->refreshMicrosoftToken($token)
                : $this->refreshGoogleToken($token);
        }

        return Crypt::decryptString($token->access_token);
    }

    private function storeToken(User $user, string $provider, array $data): OAuthToken
    {
        return OAuthToken::updateOrCreate(
            ['user_id' => $user->id, 'provider' => $provider],
            [
                'access_token' => Crypt::encryptString($data['access_token']),
                'refresh_token' => isset($data['refresh_token'])
                    ? Crypt::encryptString($data['refresh_token'])
                    : null,
                'token_type' => $data['token_type'] ?? 'Bearer',
                'expires_at' => Carbon::now()->addSeconds($data['expires_in']),
                'scopes' => $data['scope'] ?? null,
            ]
        );
    }
}
```

### 6.3 Cloud Storage Interface

```php
<?php

namespace App\Services\Integration\Contracts;

interface CloudStorageInterface
{
    /**
     * List files and folders in a directory
     */
    public function listItems(string $folderId = 'root'): array;

    /**
     * Get file metadata
     */
    public function getFile(string $fileId): array;

    /**
     * Get web URL for a file
     */
    public function getWebUrl(string $fileId): string;

    /**
     * Create a folder
     */
    public function createFolder(string $name, string $parentId = 'root'): array;

    /**
     * Check if connection is valid
     */
    public function isConnected(): bool;
}
```

### 6.4 OneDrive Service Implementation

```php
<?php

namespace App\Services\Integration;

use App\Services\Integration\Contracts\CloudStorageInterface;
use Illuminate\Support\Facades\Http;

class OneDriveService implements CloudStorageInterface
{
    private const GRAPH_URL = 'https://graph.microsoft.com/v1.0';

    public function __construct(
        private OAuthService $oAuthService
    ) {}

    public function listItems(string $folderId = 'root'): array
    {
        $token = $this->getToken();

        $endpoint = $folderId === 'root'
            ? '/me/drive/root/children'
            : "/me/drive/items/{$folderId}/children";

        $response = Http::withToken($token)
            ->get(self::GRAPH_URL . $endpoint, [
                '$select' => 'id,name,size,lastModifiedDateTime,file,folder,webUrl',
                '$orderby' => 'name',
            ]);

        if ($response->failed()) {
            throw new \Exception('Failed to list OneDrive items');
        }

        return $this->transformItems($response->json()['value'] ?? []);
    }

    public function getFile(string $fileId): array
    {
        $token = $this->getToken();

        $response = Http::withToken($token)
            ->get(self::GRAPH_URL . "/me/drive/items/{$fileId}");

        if ($response->failed()) {
            throw new \Exception('Failed to get file');
        }

        return $this->transformItem($response->json());
    }

    public function getWebUrl(string $fileId): string
    {
        $file = $this->getFile($fileId);
        return $file['web_url'];
    }

    public function createFolder(string $name, string $parentId = 'root'): array
    {
        $token = $this->getToken();

        $endpoint = $parentId === 'root'
            ? '/me/drive/root/children'
            : "/me/drive/items/{$parentId}/children";

        $response = Http::withToken($token)
            ->post(self::GRAPH_URL . $endpoint, [
                'name' => $name,
                'folder' => new \stdClass(),
                '@microsoft.graph.conflictBehavior' => 'rename',
            ]);

        if ($response->failed()) {
            throw new \Exception('Failed to create folder');
        }

        return $this->transformItem($response->json());
    }

    public function isConnected(): bool
    {
        try {
            $token = $this->oAuthService->getValidToken(auth()->user(), 'microsoft');
            return $token !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getToken(): string
    {
        $token = $this->oAuthService->getValidToken(auth()->user(), 'microsoft');

        if (!$token) {
            throw new \Exception('Microsoft account not connected');
        }

        return $token;
    }

    private function transformItems(array $items): array
    {
        return array_map([$this, 'transformItem'], $items);
    }

    private function transformItem(array $item): array
    {
        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'type' => isset($item['folder']) ? 'folder' : 'file',
            'size' => $item['size'] ?? null,
            'mime_type' => $item['file']['mimeType'] ?? null,
            'modified_at' => $item['lastModifiedDateTime'] ?? null,
            'web_url' => $item['webUrl'] ?? null,
        ];
    }
}
```

### 6.5 Calendar Sync Architecture

```php
<?php

namespace App\Services\Integration\Contracts;

interface CalendarInterface
{
    /**
     * List user's calendars
     */
    public function listCalendars(): array;

    /**
     * Get events in a date range
     */
    public function getEvents(string $calendarId, \DateTime $start, \DateTime $end): array;

    /**
     * Create an event
     */
    public function createEvent(string $calendarId, array $eventData): array;

    /**
     * Update an event
     */
    public function updateEvent(string $calendarId, string $eventId, array $eventData): array;

    /**
     * Delete an event
     */
    public function deleteEvent(string $calendarId, string $eventId): bool;

    /**
     * Check if connection is valid
     */
    public function isConnected(): bool;
}
```

### 6.6 Integration Status API

```php
<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Services\Integration\OAuthService;
use Illuminate\Http\JsonResponse;

class OAuthController extends Controller
{
    public function __construct(
        private OAuthService $oAuthService
    ) {}

    public function status(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'microsoft' => [
                'connected' => $this->oAuthService->isConnected($user, 'microsoft'),
                'account_email' => $this->oAuthService->getAccountEmail($user, 'microsoft'),
                'scopes' => [
                    'onedrive' => true,
                    'calendar' => true,
                ],
            ],
            'google' => [
                'connected' => $this->oAuthService->isConnected($user, 'google'),
                'account_email' => $this->oAuthService->getAccountEmail($user, 'google'),
                'scopes' => [
                    'drive' => true,
                    'calendar' => true,
                ],
            ],
        ]);
    }
}
```

---

## 7. Security Architecture

### 7.1 Authentication & Authorization Layers

```
Request
   |
   v
[Rate Limiting] -- Throttle by IP/user
   |
   v
[Sanctum Auth] -- Validate session/token
   |
   v
[Tenant Scope] -- Inject tenant_id filter
   |
   v
[Policy Check] -- Authorize action (Spatie)
   |
   v
[Controller]
```

### 7.2 Permission Matrix

```php
// Permissions seeder
$permissions = [
    // Clientes
    'clientes.view',
    'clientes.create',
    'clientes.update',
    'clientes.delete',

    // Expedientes
    'expedientes.view',
    'expedientes.view_all',  // See all, not just assigned
    'expedientes.create',
    'expedientes.update',
    'expedientes.delete',
    'expedientes.assign',

    // Tareas
    'tareas.view',
    'tareas.view_all',
    'tareas.create',
    'tareas.update',
    'tareas.delete',
    'tareas.assign',

    // Seguimientos
    'seguimientos.view',
    'seguimientos.create',

    // Documentos
    'documentos.view',
    'documentos.upload',
    'documentos.delete',

    // Eventos
    'eventos.view',
    'eventos.create',
    'eventos.update',
    'eventos.delete',

    // Reports
    'reports.view',
    'reports.export',

    // Admin
    'users.manage',
    'roles.manage',
    'tenant.settings',
];

// Role assignments
$roles = [
    'admin' => [...$permissions],

    'consultor' => [
        'clientes.*',
        'expedientes.*',
        'tareas.*',
        'seguimientos.*',
        'documentos.*',
        'eventos.*',
        'reports.view',
    ],

    'apoyo' => [
        'clientes.view',
        'clientes.create',
        'clientes.update',
        'expedientes.view',
        'expedientes.update',
        'tareas.view',
        'tareas.update',
        'seguimientos.*',
        'documentos.view',
        'documentos.upload',
        'eventos.view',
        'eventos.create',
        'eventos.update',
    ],

    'contador' => [
        'clientes.view',
        'expedientes.view',
        'reports.view',
        'reports.export',
    ],
];
```

### 7.3 Data Encryption

| Data Type | Encryption Method |
|-----------|-------------------|
| OAuth Access Tokens | AES-256-GCM (Laravel Crypt) |
| OAuth Refresh Tokens | AES-256-GCM (Laravel Crypt) |
| Tenant OAuth Secrets | AES-256-GCM |
| User Passwords | Bcrypt (Laravel default) |
| 2FA Secrets | AES-256-GCM (existing) |

### 7.4 Audit Logging

All domain actions logged via Spatie Activitylog:

```php
// Example in ExpedienteService
activity()
    ->causedBy(auth()->user())
    ->performedOn($expediente)
    ->withProperties([
        'old_etapa' => $oldEtapa,
        'new_etapa' => $newEtapa,
    ])
    ->log('Updated expediente stage');
```

---

## 8. Implementation Phases

### Phase 1: Foundation (2-3 weeks)

**Objective**: Establish multi-tenancy and core entities

| Task | Effort | Priority |
|------|--------|----------|
| Create Tenant model and migration | 2d | P0 |
| Add tenant_id to users table | 1d | P0 |
| Implement TenantScope middleware | 2d | P0 |
| Create BelongsToTenant trait | 1d | P0 |
| Clientes: Model, Migration, Repository, Service | 3d | P0 |
| Clientes: Controller, Requests, Policy | 2d | P0 |
| Clientes: Frontend (Store, Service, Views) | 3d | P0 |
| Acompanantes: Full stack | 2d | P0 |
| Seed tipos_caso configuration | 1d | P0 |

**Deliverables**:
- Multi-tenant infrastructure working
- Clientes CRUD with search/filter
- Acompanantes management

### Phase 2: Case Management (2-3 weeks)

**Objective**: Implement Expedientes with wizard and lifecycle

| Task | Effort | Priority |
|------|--------|----------|
| Expedientes: Model, Migration, Repository | 2d | P0 |
| Expedientes: Service with wizard logic | 3d | P0 |
| Expedientes: Controller, Requests, Policy | 2d | P0 |
| Expedientes: Frontend wizard component | 4d | P0 |
| Expedientes: List/detail views | 3d | P0 |
| Expedientes: Timeline component | 2d | P1 |
| Expediente-Acompanante pivot | 1d | P0 |

**Deliverables**:
- Case creation wizard
- Case listing with filters
- Case detail view with tabs
- Stage pipeline visualization

### Phase 3: Tasks & Communication (2 weeks)

**Objective**: Implement Tareas and Seguimiento

| Task | Effort | Priority |
|------|--------|----------|
| Tareas: Full backend stack | 3d | P0 |
| Tareas: Time logging | 2d | P0 |
| Tareas: Dashboard view | 2d | P0 |
| Tareas: Kanban view | 2d | P1 |
| Seguimientos: Full backend stack | 2d | P0 |
| Seguimientos: Timeline component | 2d | P0 |
| Seguimientos: Search across cases | 1d | P1 |

**Deliverables**:
- Task management with priorities
- Time logging per task
- Communication history per case
- Task dashboard

### Phase 4: Document Integration (2-3 weeks)

**Objective**: Implement cloud storage integrations

| Task | Effort | Priority |
|------|--------|----------|
| OAuth infrastructure | 3d | P0 |
| Microsoft OAuth flow | 2d | P0 |
| Google OAuth flow | 2d | P0 |
| OneDriveService implementation | 3d | P0 |
| GoogleDriveService implementation | 3d | P0 |
| Documentos: Full stack | 3d | P0 |
| Carpetas: Full stack | 2d | P0 |
| File browser components | 3d | P0 |
| Document linking flow | 2d | P0 |

**Deliverables**:
- OAuth connection management
- Browse OneDrive/Google Drive
- Link documents to cases
- Folder structure per case

### Phase 5: Calendar Integration (1-2 weeks)

**Objective**: Implement calendar and sync

| Task | Effort | Priority |
|------|--------|----------|
| Eventos: Full backend stack | 2d | P0 |
| Calendar view (FullCalendar) | 2d | P0 |
| OutlookCalendarService | 3d | P0 |
| GoogleCalendarService | 3d | P0 |
| Bidirectional sync jobs | 3d | P0 |
| Conflict resolution | 1d | P1 |

**Deliverables**:
- In-app calendar view
- Event CRUD
- Outlook/Google Calendar sync
- Link events to cases

### Phase 6: Dashboard & Polish (1-2 weeks)

**Objective**: CRM dashboard and refinements

| Task | Effort | Priority |
|------|--------|----------|
| Dashboard: Stats API | 2d | P0 |
| Dashboard: Widget components | 3d | P0 |
| Dashboard: Recent cases | 1d | P0 |
| Dashboard: Tasks due today | 1d | P0 |
| i18n: Complete ES/EN/FR translations | 2d | P0 |
| Performance optimization | 2d | P1 |
| E2E testing | 3d | P1 |

**Deliverables**:
- Personalized dashboard
- Complete translations
- Performance within NFR targets

### Phase Summary

| Phase | Duration | Key Deliverable |
|-------|----------|-----------------|
| 1. Foundation | 2-3 weeks | Multi-tenancy + Clients |
| 2. Case Management | 2-3 weeks | Expediente wizard + lifecycle |
| 3. Tasks & Communication | 2 weeks | Task management + Seguimiento |
| 4. Document Integration | 2-3 weeks | Cloud storage integration |
| 5. Calendar Integration | 1-2 weeks | Calendar sync |
| 6. Dashboard & Polish | 1-2 weeks | Dashboard + i18n |
| **Total** | **10-15 weeks** | **MVP Complete** |

---

## 9. Technical Decisions

### 9.1 Key Architecture Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Multi-tenant approach | Shared DB + tenant_id | Simpler ops, sufficient isolation, existing pattern works |
| OAuth token storage | Encrypted in DB | Per-user tokens, easy refresh management |
| Calendar sync | Job-based | Async, handles rate limits, retry logic |
| Document storage | Link-only (not copy) | Respects existing file organization, no storage costs |
| State management | Pinia per-module stores | Follows existing pattern, clear separation |
| API style | RESTful JSON | Existing pattern, simple, well-understood |

### 9.2 Libraries & Packages

**Backend (Composer)**:
```json
{
    "require": {
        "league/oauth2-client": "^2.7",
        "microsoft/microsoft-graph": "^2.0",
        "google/apiclient": "^2.15"
    }
}
```

**Frontend (npm)**:
```json
{
    "dependencies": {
        "@fullcalendar/vue3": "^6.1",
        "@fullcalendar/daygrid": "^6.1",
        "@fullcalendar/timegrid": "^6.1",
        "@fullcalendar/interaction": "^6.1"
    }
}
```

### 9.3 Performance Considerations

| Concern | Solution |
|---------|----------|
| Large client lists | Paginated API, virtualized lists |
| Case search | Database indexes, consider Elasticsearch for Phase 2 |
| Cloud API rate limits | Queue-based sync, exponential backoff |
| Dashboard load time | Cached stats, lazy-load widgets |
| Document metadata | Cache file metadata locally |

### 9.4 Scalability Path

| Milestone | Optimization |
|-----------|--------------|
| 10 tenants | Current architecture sufficient |
| 50 tenants | Add Redis caching for hot data |
| 100+ tenants | Consider read replicas, tenant sharding |
| High sync volume | Dedicated queue workers per integration |

---

## Appendix A: Configuration Examples

### A.1 Tipo Caso Configuration (JSON)

```json
{
    "codigo": "express_entry",
    "nombre": "Express Entry",
    "etapas": [
        {"codigo": "inicial", "nombre": "Evaluacion Inicial", "orden": 1},
        {"codigo": "documentacion", "nombre": "Recopilacion Documentos", "orden": 2},
        {"codigo": "perfil", "nombre": "Creacion Perfil EE", "orden": 3},
        {"codigo": "ita", "nombre": "Espera ITA", "orden": 4},
        {"codigo": "solicitud", "nombre": "Solicitud PR", "orden": 5},
        {"codigo": "procesamiento", "nombre": "En Procesamiento", "orden": 6},
        {"codigo": "decision", "nombre": "Decision", "orden": 7}
    ],
    "plantilla_tareas": [
        {"titulo": "Obtener pasaportes vigentes", "prioridad": "alta"},
        {"titulo": "Solicitar ECA", "prioridad": "alta"},
        {"titulo": "Programar IELTS", "prioridad": "alta"},
        {"titulo": "Obtener cartas laborales", "prioridad": "media"},
        {"titulo": "Obtener certificados policiales", "prioridad": "media"}
    ],
    "plantilla_carpetas": [
        {"nombre": "Documentos Personales"},
        {"nombre": "Educacion"},
        {"nombre": "Experiencia Laboral"},
        {"nombre": "Idiomas"},
        {"nombre": "Formularios IRCC"}
    ]
}
```

### A.2 Environment Variables

```env
# Microsoft OAuth
MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=
MICROSOFT_REDIRECT_URI=

# Google OAuth
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=

# Calendar Sync
CALENDAR_SYNC_ENABLED=true
CALENDAR_SYNC_INTERVAL_MINUTES=15
```

---

**Document Status**: Ready for Review
**Next Steps**:
1. Review with development team
2. Create detailed sprint backlogs per phase
3. Set up feature branches for parallel development
