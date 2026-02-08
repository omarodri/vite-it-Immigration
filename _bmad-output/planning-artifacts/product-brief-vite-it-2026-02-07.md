---
stepsCompleted: [1, 2, 3, 4, 5]
inputDocuments:
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
  - Project+SCOPE_v1.doc (external)
date: 2026-02-07
author: Omar
project_name: vite-it
newRequirements:
  - Microsoft OneDrive integration for expediente document management
  - Google Drive integration for expediente document management
---

# Product Brief: VITE-IT Immigration

## Executive Summary

**VITE-IT Immigration** is a purpose-built CRM platform designed specifically for Canadian immigration consultancies. Unlike generic CRM solutions, VITE-IT delivers a pre-configured, industry-tailored system that standardizes case management workflows, integrates with existing document management tools (OneDrive/Google Drive), and provides end-to-end visibility across client cases, billing, and documentation.

The platform addresses the critical operational gaps faced by immigration consultants who currently struggle with fragmented workflows, inconsistent case tracking, and dispersed document management. By combining a comprehensive Staff Portal with a Client Portal, VITE-IT creates a unified ecosystem where consultants, support staff, and clients collaborate seamlessly throughout the immigration journey.

**Current Implementation Status:** Core infrastructure complete including authentication, user management, roles/permissions system, and foundational architecture (Laravel 12 + Vue 3.5 SPA).

---

## Core Vision

### Problem Statement

Canadian immigration consultants operate in a high-stakes environment where missed deadlines can derail client cases, lost documents can delay applications, and poor communication can damage client relationships. Despite these critical requirements, most consultancies rely on:

- Generic CRM tools not designed for immigration workflows
- Scattered document storage across personal drives and email
- Manual tracking of IRCC/CISR deadlines and case milestones
- Ad-hoc communication with clients lacking centralized history
- No standardized processes for case intake, progression, and closure

### Problem Impact

**For Consultants:**
- Hours lost searching for documents and case information
- Risk of missing critical regulatory deadlines
- Difficulty scaling practice due to operational overhead
- No visibility into team workload and case distribution

**For Clients:**
- Uncertainty about case status and next steps
- Confusion about required documents and deadlines
- Limited self-service options for routine tasks
- Fragmented communication history

### Why Existing Solutions Fall Short

| Solution Type | Limitation |
|--------------|------------|
| Generic CRMs (Salesforce, HubSpot) | Not configured for immigration case structures, stages, or document requirements |
| Legal Practice Management | Designed for litigation, not regulatory immigration processes |
| Spreadsheets/Manual Tracking | No automation, prone to errors, doesn't scale |
| Custom Development | Expensive, long development cycles, maintenance burden |

### Proposed Solution

**VITE-IT Immigration** delivers a turnkey CRM platform pre-configured for Canadian immigration consultancies:

**Staff Portal Capabilities:**
- **Case Management (Expedientes):** Structured case lifecycle with configurable stages, priorities, and status tracking
- **Client Management:** Complete client profiles with companion (acompañante) support for family-based applications
- **Document Management:** Native OneDrive and Google Drive integration maintaining existing consultant workflows
- **Deadline Calculator:** Automated IRCC/CISR deadline computation based on case type and key dates
- **Task Management:** Assignment, tracking, and time logging per case
- **Follow-up Tracking (Seguimiento):** Centralized communication history across all channels
- **Billing Integration:** Case-linked payment tracking and invoicing
- **Agenda/Calendar:** Personal and case-linked event management
- **Time Reporting:** Detailed time investment analysis per case and team member

**Client Portal Capabilities:**
- Case status visibility (consultant-controlled)
- Document upload and task completion
- Secure messaging with case team
- Form submission and tracking

### Key Differentiators

1. **Pre-Configured for Canadian Immigration:** Not a blank CRM requiring months of customization—ready to deploy with immigration-specific workflows, stages, and terminology

2. **Consultant-Centric Design:** Built around how immigration consultants actually work, with deadline calculations, case structures, and document workflows native to the platform

3. **Cloud Document Integration:** OneDrive and Google Drive integration preserves existing document management habits while adding case-level organization and tracking

4. **Bilingual/Trilingual Support:** Full internationalization supporting Spanish, English, and French—essential for serving diverse client populations

5. **Modern Technical Foundation:** Laravel 12 + Vue 3.5 SPA architecture ensuring performance, security, and maintainability

6. **Client Self-Service Portal:** Reduces consultant administrative burden while improving client experience and transparency

---

## Target Users

### Primary Users

#### 1. Consultor (RCIC - Regulated Canadian Immigration Consultant)

**Persona: María González, RCIC**
- **Context:** Licensed immigration consultant with 8 years of experience, managing 40-60 active cases
- **Environment:** Works from office with 2-3 support staff, serves clients primarily from Latin America
- **Languages:** Spanish (native), English (professional), French (basic)

**Current Pain Points:**
- Spends 2+ hours daily searching for documents across email, OneDrive, and WhatsApp
- Manually tracks IRCC deadlines in spreadsheets, risking missed dates
- No visibility into support staff workload or case progress
- Client communication scattered across multiple channels

**Goals:**
- Standardize case workflow to reduce errors and scale practice
- Delegate administrative tasks confidently with full visibility
- Never miss an IRCC/CISR deadline
- Professional client experience that builds referrals

**Success Vision:** "I open VITE-IT and see exactly where every case stands, what's due this week, and what my team is working on—all in one place."

---

#### 2. Apoyo a Gestión de Expedientes (Case Support Staff)

**Persona: Carlos Mendoza, Case Administrator**
- **Context:** Full-time employee supporting 2 consultants, handles document collection and client communication
- **Environment:** Office-based, manages day-to-day case administration
- **Languages:** Spanish, English, French

**Current Pain Points:**
- Unclear task priorities—constantly asking consultant what to do next
- Chasing clients for documents via WhatsApp with no tracking
- No system for logging time spent on each case
- Duplicate work due to lack of case history visibility

**Goals:**
- Clear daily task list with priorities
- Easy document request and tracking with clients
- Log work time for billing purposes
- Access case history without bothering the consultant

**Success Vision:** "I start my day knowing exactly what needs to be done, and I can handle client requests without constantly interrupting María."

---

### Secondary Users

#### 3. Cliente (Individual Applicant)

**Persona: Ahmed Hassan, Express Entry Applicant**
- **Context:** Skilled worker applying for Canadian PR, works full-time while managing application
- **Environment:** Mobile-first, limited availability during business hours
- **Languages:** English, Arabic

**Needs:**
- Know case status without calling the consultancy
- Clear list of required documents with deadlines
- Easy document upload from phone
- Secure messaging with case team

**Success Vision:** "I can check my case status and upload documents from my phone during lunch break."

---

#### 4. Administrador (System Administrator)

**Persona: Tech-savvy consultant or office manager**
- **Context:** Configures system during initial setup, occasional maintenance
- **Responsibilities:**
  - User account management (create, deactivate, assign roles)
  - Configure case types, stages, and priorities
  - Manage email templates and quick responses
  - Set up deadline calculation rules

**Success Vision:** "I set up the system once, and it just works. When we need changes, they're intuitive."

---

#### 5. Contador (Accountant)

**Persona: External or part-time accountant**
- **Context:** Reviews billing, generates invoices, tracks payments
- **Access:** Limited to financial modules (Estado de Cuenta, Time Reports)

**Needs:**
- View case-linked payments and pending balances
- Generate time reports for billing
- Export data for accounting software

**Success Vision:** "I can pull billing reports and see exactly what's been paid and what's outstanding per case."

---

### User Journey

#### Consultant (María) Journey

| Stage | Experience |
|-------|------------|
| **Discovery** | Referred by colleague or finds via RCIC community |
| **Onboarding** | Pre-configured system, imports existing client list, connects OneDrive |
| **First Week** | Creates first case using wizard, assigns tasks to Carlos |
| **Aha! Moment** | Deadline calculator shows all IRCC dates automatically |
| **Daily Use** | Dashboard shows case priorities, tasks, and team activity |
| **Long-term** | Scales practice to 100+ cases with same overhead |

#### Client (Ahmed) Journey

| Stage | Experience |
|-------|------------|
| **Onboarding** | Receives portal credentials via email after signing contract |
| **First Task** | Sees clear document checklist with upload buttons |
| **Engagement** | Uploads documents, marks tasks complete, checks status |
| **Aha! Moment** | Gets notification when case advances to new stage |
| **Long-term** | Refers friends based on professional experience |

---

## Success Metrics

### User Success Metrics

#### Consultant (RCIC) Success
| Metric | Target | Measurement |
|--------|--------|-------------|
| **Time saved on admin tasks** | 50% reduction vs. baseline | Self-reported weekly hours before/after |
| **Zero missed IRCC/CISR deadlines** | 100% on-time | Deadline completion rate in system |
| **Case visibility** | All cases trackable in one place | % of active cases managed in VITE-IT |
| **Team coordination** | No "where is this?" questions | Task assignment and completion rates |

#### Support Staff Success
| Metric | Target | Measurement |
|--------|--------|-------------|
| **Clear daily priorities** | 100% tasks have due dates/priority | Task metadata completeness |
| **Document tracking** | No lost document requests | Document request → receipt cycle time |
| **Time logging adoption** | 90%+ of billable time logged | Hours logged vs. expected capacity |

#### Client Success
| Metric | Target | Measurement |
|--------|--------|-------------|
| **Portal adoption** | 80%+ clients use portal | Active logins per client per month |
| **Self-service document upload** | 70%+ documents via portal | Portal uploads vs. email/WhatsApp |
| **Status visibility** | Zero "what's my status?" calls | Support ticket reduction |

---

### Business Objectives

#### SaaS Growth (12-month targets)
| Objective | Target | Measurement |
|-----------|--------|-------------|
| **Customer acquisition** | 10 consultancies onboarded | Signed contracts |
| **Monthly Recurring Revenue** | Target MRR | Subscription revenue |
| **Net Revenue Retention** | >100% | Expansion vs. churn |

#### Adoption & Retention
| Objective | Target | Measurement |
|-----------|--------|-------------|
| **No spreadsheet reversion** | 0% users abandon system | Monthly active users / total users |
| **Feature adoption** | Core features used by 80%+ users | Feature usage analytics |
| **Client portal activation** | 90%+ clients with portal access | Activated accounts / total clients |

#### Operational Excellence
| Objective | Target | Measurement |
|-----------|--------|-------------|
| **Onboarding time** | <1 week to productive use | Days from signup to first case created |
| **Support burden** | <2 tickets/user/month | Help desk metrics |
| **System uptime** | 99.9% | Infrastructure monitoring |

---

### Key Performance Indicators (KPIs)

#### Leading Indicators (Predict Success)
1. **Weekly Active Users (WAU)** - Are users coming back daily/weekly?
2. **Cases created per week** - Is the system becoming the primary tool?
3. **Tasks completed per user** - Is workflow standardization happening?
4. **Documents linked per case** - Is OneDrive/Google Drive integration being used?

#### Lagging Indicators (Confirm Success)
1. **Customer retention rate** - Are consultancies renewing?
2. **Deadline compliance rate** - Zero missed deadlines = product working
3. **Time-to-value** - How fast do new users see benefits?
4. **Net Promoter Score (NPS)** - Would users recommend to colleagues?

#### Anti-Metrics (Warning Signs)
1. **Spreadsheet usage detected** - Staff maintaining parallel systems = failure
2. **Client portal dormancy** - <1 login/month per client = not adopted
3. **Incomplete case data** - Cases created but not maintained = abandonment
4. **Support escalations increasing** - System friction too high

---

## MVP Scope

### Core Features (Phase 1 - Staff Portal)

#### Already Implemented ✅
| Module | Status |
|--------|--------|
| Authentication (Sanctum SPA) | Complete |
| User Management | Complete |
| Roles & Permissions (Spatie) | Complete |
| Email Verification | Complete |
| Profile Management | Complete |
| Two-Factor Authentication | Complete |
| Activity Logs | Complete |

#### To Build - MVP Core
| Module | Description | Priority |
|--------|-------------|----------|
| **Clientes** | Client CRUD, search, filters, prospect-to-client conversion | P0 - Foundation |
| **Acompañantes** | Companion management linked to cases | P0 - Foundation |
| **Expedientes** | Case wizard, lifecycle, stages, status, assignment | P0 - Core |
| **Tareas** | Task assignment, priorities, due dates, time logging | P0 - Core |
| **Seguimiento** | Communication history, channel tracking, notes | P0 - Core |
| **Documentos** | Document linking, folder structure per case | P0 - Core |
| **OneDrive Integration** | Browse, link, sync documents from OneDrive | P0 - Differentiator |
| **Google Drive Integration** | Browse, link, sync documents from Google Drive | P0 - Differentiator |
| **Agenda** | Calendar view, events, appointments per case | P0 - Core |
| **Outlook Integration** | Sync events with Microsoft Outlook calendar | P0 - Differentiator |
| **Google Calendar Integration** | Sync events with Google Calendar | P0 - Differentiator |

#### MVP Integration Requirements
| Integration | Scope | Authentication |
|-------------|-------|----------------|
| Microsoft Graph API | OneDrive + Outlook Calendar | OAuth 2.0 (existing Socialite analysis) |
| Google APIs | Google Drive + Google Calendar | OAuth 2.0 (existing Socialite analysis) |

---

### Out of Scope for MVP

#### Phase 2 - Client Portal & Advanced Features
| Feature | Rationale for Deferral |
|---------|----------------------|
| **Client Portal** | Staff Portal must be stable before client-facing features |
| **Calculadora de Plazos** | Consultants can manually track deadlines initially |
| **Reporte de Tiempos** | Time logging in MVP; reports can come later |
| **Estado de Cuenta** | Billing tracking in MVP; invoicing features later |
| **Email Templates** | Quick responses useful but not blocking |
| **Respuestas Rápidas** | Convenience feature for Phase 2 |

#### Phase 3 - Scale & Optimize
| Feature | Vision |
|---------|--------|
| **Mobile App** | Native iOS/Android for on-the-go access |
| **Advanced Analytics** | Dashboard with case trends, team performance |
| **WhatsApp Integration** | Direct messaging from Seguimiento |
| **IRCC API Integration** | Automated status checks (if available) |
| **Multi-tenancy Enhancements** | White-labeling, custom domains |

---

### MVP Success Criteria

#### Go/No-Go Gates
| Criteria | Target | Decision Point |
|----------|--------|----------------|
| **Pilot consultancy onboarded** | 1 RCIC using system daily | Proceed to Phase 2 |
| **Cases managed end-to-end** | 10+ cases from creation to close | Product validates problem |
| **Cloud document adoption** | 80%+ documents via OneDrive/GDrive | Integration working |
| **Calendar sync working** | Events syncing both directions | Integration stable |
| **No spreadsheet fallback** | Staff using VITE-IT exclusively | Core value delivered |

#### Validation Evidence
- Consultant reports time savings on administrative tasks
- Support staff has clear daily task priorities
- All active cases visible in single dashboard
- Documents accessible without leaving the platform
- Calendar events sync without manual duplication

---

### Future Vision

#### 2-Year Product Vision
**VITE-IT becomes the operating system for Canadian immigration consultancies:**

1. **Complete Practice Management** - From first client contact to case closure and beyond
2. **Client Self-Service** - Portal reduces administrative burden by 70%
3. **Intelligent Automation** - Deadline predictions, document checklists, workflow suggestions
4. **RCIC Community Platform** - Referral network, case collaboration, knowledge sharing
5. **Regulatory Compliance** - Built-in IRCC/CISR compliance tracking and reporting

#### Expansion Opportunities
| Opportunity | Timeline |
|-------------|----------|
| Additional consultancies (SaaS growth) | Post-MVP |
| Immigration lawyers (adjacent market) | Year 2 |
| US immigration (H1B, Green Card) | Year 2-3 |
| Other regulated professional services | Year 3+ |

#### Technology Roadmap
| Capability | Phase |
|------------|-------|
| AI-powered document classification | Phase 3 |
| Predictive deadline alerts | Phase 3 |
| Case outcome analytics | Phase 3 |
| Voice-to-text case notes | Future |

