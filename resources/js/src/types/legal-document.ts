export type AlertStatus = 'overdue' | 'critical' | 'warning' | 'ok' | 'none';

export interface LegalDocument {
    id?: number;
    document_type: string;
    document_name: string | null;
    display_name?: string;
    document_number: string | null;
    issuing_country?: string | null;
    issue_date: string | null;
    expiry_date: string | null;
    alert_days_before: number;
    alert_active: boolean;
    notes: string | null;
    sort_order: number;
    days_remaining?: number;
    alert_status?: AlertStatus;
    entity_name?: string;
    entity_type?: 'client' | 'companion';
    entity_id?: number;
    client_id?: number;
}

export const DOCUMENT_TYPES = [
    { value: 'passport', labelKey: 'documents.type_passport' },
    { value: 'work_permit', labelKey: 'documents.type_work_permit' },
    { value: 'study_permit', labelKey: 'documents.type_study_permit' },
    { value: 'visitor_record', labelKey: 'documents.type_visitor_record' },
    { value: 'pr_card', labelKey: 'documents.type_pr_card' },
    { value: 'caq', labelKey: 'documents.type_caq' },
    { value: 'open_work_permit', labelKey: 'documents.type_open_work_permit' },
    { value: 'eta', labelKey: 'documents.type_eta' },
    { value: 'other', labelKey: 'documents.type_other' },
] as const;
