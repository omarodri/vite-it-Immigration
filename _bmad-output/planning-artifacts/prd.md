---
stepsCompleted: [step-01-init, step-02-discovery, step-03-success, step-04-journeys, step-05-domain, step-06-innovation-skipped, step-07-project-type, step-08-scoping, step-09-functional, step-10-nonfunctional, step-11-polish]
inputDocuments:
  - product-brief-vite-it-2026-02-07.md
  - prototype-analysis-vite-it.md
  - CLAUDE.md
  - spec/00_arquitectura_auth_sistema_completo.md
  - spec/01_architectural_analysis.md
  - spec/02_implementation_specs.md
  - spec/03_backend_implementation_phases.md
  - spec/04_frontend_implementation_phases.md
  - spec/05_socialite_analysis.md
  - spec/06_role_permission_management.md
  - spec/06a_backend_implementation_phases.md
  - spec/06b_frontend_implementation_phases.md
workflowType: 'prd'
projectType: 'brownfield'
documentCounts:
  briefs: 1
  prototypes: 1
  specs: 11
  projectDocs: 1
classification:
  projectType: saas_b2b
  domain: legaltech
  complexity: high
  projectContext: brownfield
---

# Product Requirements Document - VITE-IT Immigration

**Author:** Omar
**Date:** 2026-02-07
**Version:** 1.0

---

## Executive Summary

**VITE-IT Immigration** is a purpose-built CRM platform for Canadian immigration consultancies (RCICs). Unlike generic CRM solutions requiring months of customization, VITE-IT delivers a pre-configured, industry-tailored system that standardizes case management workflows, integrates with existing document tools (OneDrive/Google Drive), and provides end-to-end visibility across client cases, billing, and documentation.

### Vision

Become the operating system for Canadian immigration consultancies—from first client contact to case closure and beyond.

### Product Differentiator

| Differentiator | Value |
|----------------|-------|
| **Pre-Configured for Immigration** | Ready to deploy with immigration-specific workflows, stages, and terminology |
| **Cloud Document Integration** | OneDrive and Google Drive integration preserves existing document habits |
| **Calendar Integration** | Bidirectional sync with Outlook and Google Calendar prevents double-entry |
| **Bilingual/Trilingual Support** | Spanish, English, and French for diverse client populations |
| **Modern Technical Foundation** | Laravel 12 + Vue 3.5 SPA ensuring performance and security |

### Current State

**Brownfield Project** - Core infrastructure complete:
- ✅ Authentication (Sanctum SPA + 2FA)
- ✅ User Management
- ✅ Roles & Permissions (Spatie)
- ✅ Email Verification
- ✅ Activity Logging
- ✅ Clean Architecture (Service Layer + Repository Pattern)

---

## Success Criteria

### User Success

#### Consultant (RCIC) Success
| Criteria | Target | Measurement |
|----------|--------|-------------|
| Time saved on admin tasks | 50% reduction | Self-reported weekly hours |
| Zero missed deadlines | 100% on-time | Deadline completion rate |
| Case visibility | All cases in one place | % cases managed in VITE-IT |
| Team coordination | No "where is this?" questions | Task completion rates |

#### Support Staff Success
| Criteria | Target | Measurement |
|----------|--------|-------------|
| Clear daily priorities | 100% tasks with due dates | Task metadata completeness |
| Document tracking | No lost requests | Request → receipt cycle time |
| Time logging adoption | 90%+ billable time logged | Hours logged vs. capacity |

### Business Success

#### SaaS Growth (12-month)
| Metric | Target |
|--------|--------|
| Customer acquisition | 10 consultancies onboarded |
| Net Revenue Retention | >100% |
| No spreadsheet reversion | 0% user abandonment |

#### Technical Success
| Metric | Target |
|--------|--------|
| System uptime | 99.9% |
| Onboarding time | <1 week to productive use |
| Support burden | <2 tickets/user/month |
| Integration reliability | OneDrive/GDrive/Calendar sync stable |

### MVP Validation Gates

1. Pilot consultancy using system daily
2. 10+ cases managed end-to-end
3. 80%+ documents via cloud integration
4. Calendar events syncing bidirectionally
5. Staff using VITE-IT exclusively (no spreadsheet fallback)

---

## User Journeys

### Journey 1: María Creates a New Case (Consultant - Success Path)

**Opening Scene:**
María arrives at the office Monday morning with three new client consultations scheduled. Her inbox has 47 unread emails, and her phone shows 12 WhatsApp messages from existing clients asking about their case status. She used to dread this chaos—hunting through spreadsheets, searching email threads, wondering what fell through the cracks over the weekend.

**Rising Action:**
She opens VITE-IT and sees her personalized dashboard. A notification shows: "3 tasks due today, 2 cases need attention." She clicks "New Case" and the wizard guides her through:
1. Selects case type: "Express Entry"
2. Searches for existing client or creates new: "Hassan, Ahmed"
3. Adds spouse as acompañante
4. System auto-connects to her OneDrive, showing the client's folder structure
5. Links relevant documents already uploaded

**Climax:**
The case is created in under 5 minutes. VITE-IT automatically generates a task list based on the case type, assigns initial tasks to Carlos, and shows key milestone dates. María sees exactly what needs to happen and who's responsible.

**Resolution:**
By 9:30 AM, María has responded to client inquiries via the Seguimiento module (all history in one place), created two new cases, and knows exactly what her team is working on. She hasn't touched a spreadsheet. The anxiety of "what am I forgetting?" is gone.

---

### Journey 2: María Finds a Missing Document (Consultant - Edge Case)

**Opening Scene:**
María is preparing for an IRCC submission due tomorrow. She searches for the client's police certificate but can't find it in the case documents. Panic sets in—did they ever receive it?

**Rising Action:**
She opens the case's Seguimiento history and searches "police certificate." She finds a note from two weeks ago: "Client said certificate is being processed, expected in 10 days." She sees Carlos was assigned a follow-up task that's now overdue.

**Climax:**
María assigns a high-priority task to Carlos: "Call client immediately re: police certificate." Carlos receives the notification, contacts the client, and uploads the document to Google Drive within the hour. The document syncs automatically to the case.

**Resolution:**
The submission goes out on time. María realizes she would have missed this deadline with her old system. She adds a note in Seguimiento documenting the close call for future reference.

---

### Journey 3: Carlos Starts His Day (Support Staff - Daily Workflow)

**Opening Scene:**
Carlos arrives at 8 AM, coffee in hand. Previously, he'd spend the first hour asking María and the other consultant what to work on. Now he opens VITE-IT.

**Rising Action:**
His task dashboard shows:
- 🔴 3 high-priority tasks due today
- 🟡 5 medium-priority tasks this week
- 🟢 12 tasks in backlog

He clicks the first task: "Follow up with Hassan family re: biometrics appointment." The case opens, he sees the complete history, and knows exactly what to say.

**Climax:**
Carlos completes 8 tasks before lunch, logging time on each. He marks tasks complete, adds follow-up notes in Seguimiento, and links a new document the client sent via email to their OneDrive folder.

**Resolution:**
By day's end, Carlos has logged 6.5 billable hours with detailed case-by-case tracking. He knows tomorrow's priorities without asking anyone. María can see his productivity without interrupting him.

---

### Journey 4: Admin Sets Up a New Staff Member (Admin - Configuration)

**Opening Scene:**
The consultancy is growing. A new support staff member, Ana, is starting next week. The admin needs to set up her access.

**Rising Action:**
Admin opens User Management:
1. Creates user: "Ana Rodríguez"
2. Assigns role: "Case Support Staff" (pre-configured permissions)
3. Links her to consultants María and Jorge
4. Sends welcome email with login credentials and 2FA setup instructions

**Climax:**
Ana logs in on her first day. She sees a welcome dashboard with training links and her assigned tasks. Her OneDrive and Google Calendar are already connected via OAuth.

**Resolution:**
Ana is productive by day two. No IT support tickets, no permission issues, no "I can't see that case" problems.

---

### Journey 5: Contador Reviews Monthly Billing (Accountant - Financial Oversight)

**Opening Scene:**
End of month. The external accountant needs to generate invoices for all active cases and reconcile payments.

**Rising Action:**
She logs into VITE-IT with her limited "Accountant" role. She can only see:
- Estado de Cuenta per case
- Time logged per case
- Payment history

She exports a report: "Time by Case - October 2026"

**Climax:**
The report shows:
- Case Hassan: 12.5 hours logged
- Case García: 8.0 hours logged
- Case Singh: 15.25 hours logged

She cross-references with payments received and identifies outstanding balances.

**Resolution:**
Invoices are generated in her accounting software using exported data. No back-and-forth with María asking "how much time did you spend on this client?"

---

### Journey Requirements Summary

| Journey | Capabilities Revealed |
|---------|----------------------|
| María Creates Case | Case wizard, client search/create, acompañantes, OneDrive integration, auto-task generation |
| María Missing Document | Seguimiento search, task assignment, document sync, notification system |
| Carlos Daily Work | Task dashboard, priority sorting, time logging, case context access, document linking |
| Admin Setup User | User CRUD, role assignment, consultant linking, OAuth onboarding |
| Accountant Billing | Role-based access, time reports, case billing, data export |

---

## Domain-Specific Requirements

### Regulatory & Compliance

#### RCIC Professional Standards
| Requirement | Description |
|-------------|-------------|
| **CICC Regulations** | Support compliance with College of Immigration and Citizenship Consultants standards |
| **Client Confidentiality** | All client data treated as confidential professional information |
| **Record Retention** | Retain case records for minimum period per CICC requirements (typically 6+ years) |
| **Conflict of Interest** | Flag potential conflicts when creating new cases |
| **Professional Conduct** | Audit trail for all case actions to support professional accountability |

#### Data Privacy
| Requirement | Description |
|-------------|-------------|
| **PIPEDA Compliance** | Canadian Personal Information Protection (applies to commercial activities) |
| **Immigration Data Sensitivity** | Passport numbers, biometrics references, immigration status - all highly sensitive |
| **Cross-Border Data** | Cloud integrations must respect data residency if required |
| **Client Consent** | Document client consent for data collection and processing |

### Document Security
| Requirement | Specification |
|-------------|---------------|
| **Document Access Control** | Only authorized users can view case documents |
| **Download Tracking** | Log all document downloads and access |
| **Secure Deletion** | Proper data destruction procedures when required |
| **Cloud Provider Security** | OAuth tokens stored securely, refreshed appropriately |

### Risk Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| **Data Breach** | Client immigration status exposed | Encryption, access controls, audit logs, incident response plan |
| **Missed Deadline** | Case rejection, client harm | Task notifications, calendar integration, dashboard alerts |
| **Cloud Provider Outage** | Document access blocked | Cache critical metadata locally; graceful degradation |
| **Account Compromise** | Unauthorized case access | 2FA enforced, session monitoring, login alerts |
| **Data Loss** | Case history destroyed | Regular backups, cloud provider redundancy |
| **Regulatory Audit** | CICC investigation | Complete audit trail, exportable records, retention compliance |

---

## SaaS B2B Architecture

### Multi-Tenant Model

VITE-IT is a **multi-tenant SaaS platform** serving Canadian immigration consultancies. Each consultancy operates as an isolated tenant with their own users, clients, cases, and data.

| Aspect | Specification |
|--------|---------------|
| **Tenant Isolation** | Database-level isolation via tenant_id on all tables |
| **Tenant Identification** | Subdomain-based (acme.vite-it.com) or single domain with tenant context |
| **Data Segregation** | Strict - users can only access their organization's data |
| **Cross-Tenant Features** | None in MVP; future: RCIC referral network |
| **Tenant Provisioning** | Admin-driven initially; self-service signup in future |

### RBAC Matrix (Role-Based Access Control)

**Implementation:** Spatie Laravel-Permission (already implemented)

| Role | Clientes | Expedientes | Tareas | Documentos | Agenda | Users | Reports |
|------|----------|-------------|--------|------------|--------|-------|---------|
| **Admin** | Full | Full | Full | Full | Full | Full | Full |
| **Consultor** | Full | Full | Full | Full | Own + Assigned | View Team | Own + Team |
| **Apoyo Gestión** | View + Edit | View + Edit Assigned | Full Assigned | Upload + Link | Own | None | Own |
| **Contador** | View Financial | View Financial | None | None | None | None | Financial |
| **Cliente** (Phase 2) | Own Profile | Own Cases (Limited) | Own Tasks | Own Docs | None | None | None |

### Integration Architecture

| Integration | Type | Status | Authentication |
|-------------|------|--------|----------------|
| **Microsoft OneDrive** | Document Storage | MVP | OAuth 2.0 (Microsoft Graph) |
| **Google Drive** | Document Storage | MVP | OAuth 2.0 (Google APIs) |
| **Microsoft Outlook** | Calendar | MVP | OAuth 2.0 (Microsoft Graph) |
| **Google Calendar** | Calendar | MVP | OAuth 2.0 (Google APIs) |
| **Email (SMTP)** | Notifications | MVP | Server-side config |
| **Stripe/Payment** | Billing | Phase 2 | API Keys |
| **WhatsApp Business** | Communication | Phase 3 | Meta Business API |

### OAuth Token Management

| Concern | Solution |
|---------|----------|
| Token Storage | Encrypted in database, per-user |
| Token Refresh | Background job before expiry |
| Scope Escalation | Re-consent flow if new scopes needed |
| Revocation | User can disconnect integrations |

---

## Project Scoping & Phased Development

### MVP Strategy

**Approach:** Problem-Solving MVP
- Focus on solving the core workflow chaos problem for consultants
- Cloud integrations are differentiators, not "nice-to-haves"
- Staff Portal first; Client Portal after staff adoption validated
- Multi-language support (ES/FR/EN) essential for diverse client populations

**Validation Hypothesis:**
"If we give RCICs a pre-configured case management system with native cloud document and calendar integration, they will standardize their workflows and stop using spreadsheets."

### MVP Feature Set (Phase 1)

**Staff Portal with 11 Core Modules:**

| Module | Description | Justification |
|--------|-------------|---------------|
| **Clientes** | Client CRUD, search, filters, prospect conversion | Foundation - cases need clients |
| **Acompañantes** | Companion management linked to cases | Required for family applications |
| **Expedientes** | Case wizard, lifecycle, stages, status, assignment | Core product value |
| **Tareas** | Task assignment, priorities, due dates, time logging | Workflow standardization |
| **Seguimiento** | Communication history, channel tracking, notes | Case context continuity |
| **Documentos** | Document linking, folder structure per case | Document organization |
| **OneDrive Integration** | Browse, link, sync from Microsoft OneDrive | Differentiator - essential |
| **Google Drive Integration** | Browse, link, sync from Google Drive | Differentiator - essential |
| **Agenda** | Calendar view, events, appointments per case | Consultant daily workflow |
| **Outlook Sync** | Bidirectional calendar sync | Prevents double-entry |
| **Google Calendar Sync** | Bidirectional calendar sync | Prevents double-entry |

**Language Support (MVP):**

| Language | Code | Status |
|----------|------|--------|
| **Spanish** | es | Primary - LATAM consultant base |
| **English** | en | Required - Canadian official language |
| **French** | fr | Required - Canadian official language |

*Note: Vue I18n infrastructure already supports 16 languages; MVP activates ES/EN/FR with complete translations*

### Growth Features (Phase 2)

| Feature | Rationale for Deferral |
|---------|------------------------|
| Client Portal | Staff must adopt first; adds complexity |
| Calculadora de Plazos | Consultants can track manually initially |
| Reporte de Tiempos | Time data collecting in MVP; reports later |
| Estado de Cuenta | Basic tracking in MVP; full invoicing later |
| Email Templates | Convenience; consultants have email already |
| Respuestas Rápidas | Convenience; can copy-paste initially |
| Additional Languages | Arabic, Portuguese, etc. based on demand |

### Vision Features (Phase 3+)

| Feature | Strategic Value |
|---------|-----------------|
| Mobile App (iOS/Android) | On-the-go case access |
| Advanced Analytics | Business intelligence for consultancies |
| WhatsApp Integration | Direct client communication |
| AI Document Classification | Reduce manual organization |
| Multi-Consultancy Network | Referrals between RCICs |

### Scope Boundaries (Explicit Exclusions)

**NOT in MVP:**
- ❌ Mobile app
- ❌ Client self-service portal
- ❌ Automated deadline calculations
- ❌ WhatsApp/SMS integration
- ❌ Advanced reporting/analytics
- ❌ Payment processing
- ❌ Document OCR/AI features

---

## Functional Requirements

### Client Management

| ID | Requirement |
|----|-------------|
| FR1 | Consultants can create new client records with contact information |
| FR2 | Consultants can search clients by name, email, phone, or nationality |
| FR2a | Support staff can create new client records as prospects |
| FR3 | Consultants can filter client lists by status (prospect, active, inactive) |
| FR4 | Consultants can convert prospects to active clients |
| FR5 | Consultants can view complete client profile with all associated cases |
| FR6 | Consultants can add companions (acompañantes) linked to a client |
| FR7 | Consultants can specify companion relationship type (spouse, child, parent, etc.) |
| FR8 | Support staff can edit client contact information |
| FR9 | System prevents duplicate client records based on email/phone |

### Case Management (Expedientes)

| ID | Requirement |
|----|-------------|
| FR10 | Consultants can create new cases using a guided wizard |
| FR11 | Consultants can select case type from configurable list (Express Entry, Asylum, Study Permit, etc.) |
| FR12 | Consultants can associate a case with an existing client |
| FR13 | Consultants can add companions to a case from client's companion list |
| FR14 | Consultants can assign cases to other consultants or support staff |
| FR15 | Consultants can track case status through configurable stages |
| FR15a | System automatically generates task checklist based on case type at creation |
| FR15b | System automatically creates folder structure in connected cloud storage at case creation |
| FR15c | Generated tasks are initially unassigned for consultant to delegate |
| FR16 | Consultants can set case priority (high, medium, low) |
| FR17 | Consultants can add IRCC/CISR reference numbers to cases |
| FR18 | Consultants can view chronological case history (all actions taken) |
| FR19 | Support staff can view cases assigned to them |
| FR20 | Support staff can update case status within their assigned cases |
| FR21 | System automatically logs all case modifications with timestamp and user |

### Task Management (Tareas)

| ID | Requirement |
|----|-------------|
| FR22 | Consultants can create tasks linked to specific cases |
| FR23 | Consultants can assign tasks to themselves or support staff |
| FR24 | Consultants can set task priority and due date |
| FR24a | Support staff can edit tasks assigned to other users within their scope |
| FR25 | Consultants can view all tasks across all cases in a unified list |
| FR26 | Support staff can view their assigned tasks in priority order |
| FR27 | Support staff can mark tasks as complete |
| FR28 | Support staff can log time spent on each task |
| FR29 | Users can filter tasks by status (pending, in progress, completed) |
| FR30 | Users can filter tasks by due date (overdue, today, this week) |
| FR30a | Users can filter tasks by creation date range |
| FR30b | Users can filter tasks by client |
| FR30c | Users can filter tasks by assigned consultant |
| FR30d | Users can filter tasks by case type |
| FR31 | System alerts users of overdue tasks on dashboard |

### Communication Tracking (Seguimiento)

| ID | Requirement |
|----|-------------|
| FR32 | Users can add follow-up entries to case communication history |
| FR33 | Users can categorize follow-up by channel (call, email, WhatsApp, in-person) |
| FR34 | Users can search follow-up history within a case |
| FR35 | Users can search follow-up history across all cases |
| FR36 | System timestamps all follow-up entries automatically |
| FR37 | Users can view complete chronological seguimiento for any case |

### Document Management

| ID | Requirement |
|----|-------------|
| FR38 | Users can create folder structure within a case |
| FR39 | Users can link documents from OneDrive to case folders |
| FR40 | Users can link documents from Google Drive to case folders |
| FR41 | Users can browse their connected OneDrive files within VITE-IT |
| FR42 | Users can browse their connected Google Drive files within VITE-IT |
| FR43 | Users can view document metadata (name, type, last modified) |
| FR44 | Users can navigate to linked documents in their original cloud location |
| FR45 | System tracks which documents are linked to which cases |
| FR46 | Users can unlink documents from cases |

### Cloud Integration

| ID | Requirement |
|----|-------------|
| FR47 | Users can connect their Microsoft account via OAuth |
| FR48 | Users can connect their Google account via OAuth |
| FR49 | Users can disconnect cloud accounts at any time |
| FR50 | System securely stores OAuth tokens per user |
| FR51 | System refreshes tokens automatically before expiry |
| FR52 | System handles cloud provider unavailability gracefully |

### Calendar & Scheduling (Agenda)

| ID | Requirement |
|----|-------------|
| FR53 | Users can view their calendar in day, week, or month view |
| FR54 | Users can create events linked to specific cases |
| FR55 | Users can create personal events not linked to cases |
| FR56 | Users can sync events bidirectionally with Outlook calendar |
| FR57 | Users can sync events bidirectionally with Google Calendar |
| FR58 | Users can choose which calendar provider to sync with |
| FR59 | System displays synced events from connected calendars |
| FR60 | Users can edit events and have changes sync to cloud calendar |

### Dashboard & Navigation

| ID | Requirement |
|----|-------------|
| FR61 | Users see personalized dashboard upon login |
| FR62 | Dashboard displays count of tasks due today |
| FR63 | Dashboard displays count of overdue tasks |
| FR64 | Dashboard displays cases requiring attention |
| FR65 | Dashboard displays 5 most recent cases for one-click navigation |
| FR66 | Users can access all modules from main navigation |
| FR67 | Navigation reflects user's role-based permissions |

### User & Tenant Management

| ID | Requirement |
|----|-------------|
| FR68 | Admins can create new user accounts within their tenant |
| FR69 | Admins can assign roles to users (Consultor, Apoyo, Contador) |
| FR70 | Admins can deactivate user accounts |
| FR71 | Admins can link support staff to specific consultants |
| FR72 | System isolates data between tenants completely |
| FR73 | Users can only access data within their tenant |
| FR73a | Admins can configure tenant branding (logo, UI colors) |
| FR73b | Admins can configure tenant name and business information |
| FR73c | Admins can configure Microsoft OAuth client credentials for tenant |
| FR73d | Admins can configure Google OAuth client credentials for tenant |

### Localization

| ID | Requirement |
|----|-------------|
| FR74 | Users can switch interface language to Spanish |
| FR75 | Users can switch interface language to English |
| FR76 | Users can switch interface language to French |
| FR77 | System remembers user's interface preferences (language, theme, layout) |
| FR78 | All UI text, labels, and messages support configured languages |

### Reporting & Export

| ID | Requirement |
|----|-------------|
| FR79 | Users can view time logged per case |
| FR80 | Accountants can view time reports across cases |
| FR81 | Users can export data in standard formats (CSV, PDF) |

---

**Total: 92 Functional Requirements across 11 capability areas**

---

## Non-Functional Requirements

### Performance

| ID | Requirement | Measurement |
|----|-------------|-------------|
| NFR-P1 | Dashboard loads within 2 seconds | Time from navigation to fully rendered |
| NFR-P2 | Task list filtering responds within 500ms | Time from filter change to results |
| NFR-P3 | Case search returns results within 1 second | Time from search submit to results |
| NFR-P4 | Cloud file browser loads folder contents within 3 seconds | Including OneDrive/GDrive API call |
| NFR-P5 | Calendar view renders within 2 seconds | Including synced cloud events |
| NFR-P6 | System supports 50 concurrent users per tenant | Without performance degradation |

### Security

| ID | Requirement | Measurement |
|----|-------------|-------------|
| NFR-S1 | All data encrypted in transit using TLS 1.2+ | SSL certificate validation |
| NFR-S2 | All data encrypted at rest | Database and file storage encryption |
| NFR-S3 | OAuth tokens stored encrypted | AES-256 or equivalent |
| NFR-S4 | Session timeout after 30 minutes of inactivity | Automatic logout |
| NFR-S5 | Failed login attempts limited to 5 before lockout | Brute force protection |
| NFR-S6 | Complete audit trail for all data access | Activity log includes user, action, timestamp |
| NFR-S7 | Tenant data isolation enforced at database level | No cross-tenant data leakage possible |
| NFR-S8 | PIPEDA compliance for Canadian personal data | Data handling procedures documented |
| NFR-S9 | 2FA available for all users | TOTP-based authentication option |
| NFR-S10 | Passwords meet complexity requirements | Min 8 chars, mixed case, numbers |

### Scalability

| ID | Requirement | Measurement |
|----|-------------|-------------|
| NFR-SC1 | System supports 50+ tenants | Without architectural changes |
| NFR-SC2 | System supports 500+ total users | Across all tenants |
| NFR-SC3 | System handles 10,000+ cases per tenant | Without performance degradation |
| NFR-SC4 | Database queries optimized for growth | Indexed, paginated, no N+1 |
| NFR-SC5 | Cloud API calls rate-limited and queued | Prevent hitting provider limits |

### Integration

| ID | Requirement | Measurement |
|----|-------------|-------------|
| NFR-I1 | OAuth flow completes within 30 seconds | User authorization experience |
| NFR-I2 | Cloud provider outages handled gracefully | Error message, cached data shown |
| NFR-I3 | Token refresh happens automatically | No user intervention required |
| NFR-I4 | API failures logged with context | Debugging information captured |
| NFR-I5 | Calendar sync conflicts resolved predictably | Clear rules for conflict resolution |
| NFR-I6 | Integration status visible to users | Connected/disconnected indicator |

### Reliability

| ID | Requirement | Measurement |
|----|-------------|-------------|
| NFR-R1 | System uptime 99.5% monthly | Excluding scheduled maintenance |
| NFR-R2 | Scheduled maintenance windows announced 24h in advance | User notification |
| NFR-R3 | Database backups every 6 hours | Point-in-time recovery capability |
| NFR-R4 | Recovery Time Objective (RTO) < 4 hours | Time to restore service |
| NFR-R5 | Recovery Point Objective (RPO) < 1 hour | Maximum data loss window |
| NFR-R6 | Error logging captures stack traces | Debugging capability |

### Accessibility

| ID | Requirement | Measurement |
|----|-------------|-------------|
| NFR-A1 | Keyboard navigation for core workflows | Tab through forms, enter to submit |
| NFR-A2 | Color contrast meets WCAG AA | 4.5:1 for normal text |
| NFR-A3 | Form fields have visible labels | Screen reader compatible |
| NFR-A4 | Error messages clearly identify issue | Specific, actionable feedback |

---

**Total: 32 Non-Functional Requirements across 6 categories**

---

## Document Summary

| Section | Count |
|---------|-------|
| User Journeys | 5 |
| Functional Requirements | 92 |
| Non-Functional Requirements | 32 |
| MVP Modules | 11 |
| Supported Languages | 3 |
| Cloud Integrations | 4 |

**PRD Status:** Complete - Ready for Architecture Planning
