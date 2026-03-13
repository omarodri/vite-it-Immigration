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

export type CaseStage =
    | 'initial_consultation'
    | 'document_collection'
    | 'application_preparation'
    | 'submitted'
    | 'under_review'
    | 'additional_info_requested'
    | 'decision_pending'
    | 'closed';

export type IrccStatus =
    | 'not_submitted'
    | 'received'
    | 'in_process'
    | 'approved'
    | 'refused'
    | 'withdrawn'
    | 'cancelled';

export type FinalResult = 'approved' | 'denied';
export type ServiceType = 'pro_bono' | 'fee_based';

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

export interface ImportantDate {
    id?: number;
    label: string;
    due_date: string | null;
    sort_order: number;
}

// =============================================
// Case Task Types (Lifecycle Checklist)
// =============================================

export interface CaseTask {
    id?: number;
    label: string;
    is_completed: boolean;
    is_custom: boolean;
    sort_order: number;
    completed_at?: string | null;
}

export interface DefaultCaseTask {
    key: string;
    sort_order: number;
}

export const DEFAULT_CASE_TASKS: DefaultCaseTask[] = [
    { key: 'contract_signature',      sort_order: 0 },
    { key: 'document_reception',      sort_order: 1 },
    { key: 'document_review',         sort_order: 2 },
    { key: 'application_preparation', sort_order: 3 },
    { key: 'application_submission',  sort_order: 4 },
    { key: 'acknowledgment_receipt',  sort_order: 5 },
    { key: 'biometric_verification',  sort_order: 6 },
    { key: 'interview',               sort_order: 7 },
    { key: 'additional_info_request', sort_order: 8 },
    { key: 'final_decision',          sort_order: 9 },
];

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
    important_dates?: ImportantDate[];

    // Lifecycle Tasks
    tasks?: CaseTask[];

    // Operational
    stage: CaseStage | null;
    stage_label: string | null;
    ircc_status: IrccStatus | null;
    ircc_status_label: string | null;
    final_result: FinalResult | null;
    final_result_label: string | null;
    ircc_code: string | null;

    // Financial
    contract_number: string | null;
    service_type: ServiceType;
    service_type_label: string;
    fees?: number | null;

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
    important_dates?: Omit<ImportantDate, 'id'>[];
    case_tasks?: Omit<CaseTask, 'id' | 'completed_at'>[];
    service_type?: ServiceType;
    contract_number?: string | null;
    fees?: number | null;
}

export interface UpdateCaseData {
    client_id?: number;
    case_type_id?: number;
    status?: CaseStatus;
    priority?: CasePriority;
    progress?: number;
    language?: string;
    description?: string;
    important_dates?: Omit<ImportantDate, 'id'>[];
    case_tasks?: Omit<CaseTask, 'id' | 'completed_at'>[];
    archive_box_number?: string | null;
    closure_notes?: string;
    assigned_to?: number | null;
    companion_ids?: number[];
    stage?: CaseStage | null;
    ircc_status?: IrccStatus | null;
    final_result?: FinalResult | null;
    ircc_code?: string | null;
    contract_number?: string | null;
    service_type?: ServiceType;
    fees?: number | null;
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
    date_from?: string;
    date_to?: string;
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
    per_page?: number;
    page?: number;
    stage?: CaseStage;
    ircc_status?: IrccStatus;
    service_type?: ServiceType;
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

export const CASE_STAGE_OPTIONS: Array<{ value: CaseStage; label: string; color: string }> = [
    { value: 'initial_consultation',      label: 'Consulta Inicial',                   color: 'secondary' },
    { value: 'document_collection',       label: 'Recolección de Documentos',          color: 'info' },
    { value: 'application_preparation',   label: 'Preparación de Solicitud',           color: 'info' },
    { value: 'submitted',                 label: 'Enviada',                            color: 'primary' },
    { value: 'under_review',              label: 'En Revisión IRCC',                   color: 'warning' },
    { value: 'additional_info_requested', label: 'Información Adicional Solicitada',   color: 'danger' },
    { value: 'decision_pending',          label: 'Decisión Pendiente',                 color: 'warning' },
    { value: 'closed',                    label: 'Cerrada',                            color: 'dark' },
];

export const IRCC_STATUS_OPTIONS: Array<{ value: IrccStatus; label: string; color: string }> = [
    { value: 'not_submitted', label: 'No Enviada',  color: 'dark' },
    { value: 'received',      label: 'Recibida',    color: 'info' },
    { value: 'in_process',    label: 'En Proceso',  color: 'primary' },
    { value: 'approved',      label: 'Aprobada',    color: 'success' },
    { value: 'refused',       label: 'Rechazada',   color: 'danger' },
    { value: 'withdrawn',     label: 'Retirada',    color: 'warning' },
    { value: 'cancelled',     label: 'Cancelada',   color: 'dark' },
];

export const SERVICE_TYPE_OPTIONS: Array<{ value: ServiceType; label: string }> = [
    { value: 'pro_bono',   label: 'Pro Bono' },
    { value: 'fee_based',  label: 'Con Honorarios' },
];

export const FINAL_RESULT_OPTIONS: Array<{ value: FinalResult; label: string; color: string }> = [
    { value: 'approved', label: 'Aprobado', color: 'success' },
    { value: 'denied',   label: 'Denegado', color: 'danger' },
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
