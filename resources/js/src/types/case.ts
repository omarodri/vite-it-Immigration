/**
 * Case Types
 * Interfaces for immigration case-related data structures
 */

// =============================================
// Base Types (Enums)
// =============================================

export type CaseStatus = 'active' | 'inactive' | 'archived' | 'closed';
export type CasePriority = 'urgent' | 'high' | 'medium' | 'low';
export type CaseTypeCategory = 'category.temporary_residence' | 'category.permanent_residence' | 'category.refugee' | 'category.citizenship';

// =============================================
// Main Interfaces
// =============================================

export interface CaseType {
    id: number;
    tenant_id: number | null;
    name: string;
    code: string;
    category: CaseTypeCategory;
    category_label: string;
    description: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface ImmigrationCase {
    id: number;
    case_number: string;
    tenant_id: number;
    client_id: number;
    case_type_id: number;
    assigned_to: number | null;

    // Status & Priority
    status: CaseStatus;
    status_label: string;
    priority: CasePriority;
    priority_label: string;

    // Progress
    progress: number;
    progress_percentage: string;

    // Configuration
    language: string;
    description: string | null;

    // Important Dates
    hearing_date: string | null;
    fda_deadline: string | null;
    brown_sheet_date: string | null;
    evidence_deadline: string | null;
    days_until_hearing: number | null;

    // Archive
    archive_box_number: string | null;

    // Closure
    closed_at: string | null;
    closure_notes: string | null;

    // Timestamps
    created_at: string;
    updated_at: string;

    // Conditional Relations
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
    companions?: Array<{
        id: number;
        first_name: string;
        last_name: string;
        full_name?: string;
        initials?: string;
        relationship: string;
        relationship_label?: string;
        age?: number;
        nationality?: string;
        date_of_birth?: string;
        gender?: string;
    }>;
}

// =============================================
// Data Transfer Objects
// =============================================

export interface CreateCaseData {
    client_id: number;
    case_type_id: number;
    assigned_to?: number;
    companion_ids?: number[];
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
    hearing_date?: string | null;
    fda_deadline?: string | null;
    brown_sheet_date?: string | null;
    evidence_deadline?: string | null;
    archive_box_number?: string | null;
    closure_notes?: string;
    assigned_to?: number | null;
    companion_ids?: number[];
}

export interface AssignCaseData {
    assigned_to: number;
}

// =============================================
// Filters and Statistics
// =============================================

export interface CaseFilters {
    search?: string;
    status?: CaseStatus;
    priority?: CasePriority;
    case_type_id?: number;
    assigned_to?: number;
    client_id?: number;
    hearing_from?: string;
    hearing_to?: string;
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

// =============================================
// Activity Log (Timeline)
// =============================================

export interface CaseActivityLog {
    id: number;
    log_name: string;
    description: string;
    subject_type: string;
    subject_id: number;
    causer_type: string | null;
    causer_id: number | null;
    properties: {
        old?: Record<string, unknown>;
        attributes?: Record<string, unknown>;
    };
    created_at: string;
    causer?: {
        id: number;
        name: string;
    };
}

// =============================================
// API Response Types
// =============================================

export interface CaseResponse {
    message: string;
    data: ImmigrationCase;
}

export interface CaseDeleteResponse {
    message: string;
}

// =============================================
// Option Constants
// =============================================

export const CASE_STATUS_OPTIONS: Array<{ value: CaseStatus; label: string; color: string }> = [
    { value: 'active', label: 'Active', color: 'success' },
    { value: 'inactive', label: 'Inactive', color: 'warning' },
    { value: 'archived', label: 'Archived', color: 'secondary' },
    { value: 'closed', label: 'Closed', color: 'dark' },
];

export const CASE_PRIORITY_OPTIONS: Array<{ value: CasePriority; label: string; color: string }> = [
    { value: 'urgent', label: 'Urgent', color: 'danger' },
    { value: 'high', label: 'High', color: 'warning' },
    { value: 'medium', label: 'Medium', color: 'info' },
    { value: 'low', label: 'Low', color: 'secondary' },
];

export const CASE_TYPE_CATEGORY_OPTIONS: Array<{ value: CaseTypeCategory; label: string }> = [
    { value: 'category.temporary_residence', label: 'Temporary Residence' },
    { value: 'category.permanent_residence', label: 'Permanent Residence' },
    { value: 'category.refugee', label: 'Refugee' },
    { value: 'category.citizenship', label: 'Citizenship' },
];

export const LANGUAGE_OPTIONS: Array<{ value: string; label: string }> = [
    { value: 'es', label: 'Spanish' },
    { value: 'en', label: 'English' },
    { value: 'fr', label: 'French' },
];

// Spanish labels (for reference/backup)
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

export const CASE_TYPE_CATEGORY_LABELS_ES: Record<CaseTypeCategory, string> = {
    'category.temporary_residence': 'Residencia Temporal',
    'category.permanent_residence': 'Residencia Permanente',
    'category.refugee': 'Refugio/Asilo',
    'category.citizenship': 'Ciudadanía',
};
