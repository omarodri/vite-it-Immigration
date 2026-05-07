/**
 * Workflow Types
 * Dynamic case lifecycle: case_types -> workflow_stages -> task_templates
 */

export type Locale = 'es' | 'en' | 'fr';
export type TranslationsByField = Record<string, Partial<Record<Locale, string>>>;

export type TaskType = 'translation' | 'case_creation' | 'accounting' | 'filing' | 'document' | 'other';
export type TaskPriority = 'urgent' | 'high' | 'medium' | 'low';
export type TaskStatus = 'new' | 'assigned' | 'in_progress' | 'rejected' | 'resolved' | 'closed';

export interface TaskTemplate {
    id: number;
    tenant_id?: number | null;
    workflow_stage_id: number;
    code: string;
    sort_order: number;
    is_required: boolean;
    blocks_stage_completion: boolean;
    default_type: TaskType;
    default_priority: TaskPriority;
    due_offset_days: number | null;
    is_active: boolean;
    translations: TranslationsByField;
    created_at?: string;
    updated_at?: string;
}

export interface WorkflowStage {
    id: number;
    tenant_id?: number | null;
    case_type_id: number;
    code: string;
    sort_order: number;
    is_active: boolean;
    is_terminal: boolean;
    color: string;
    translations: TranslationsByField;
    task_templates_count?: number;
    task_templates?: TaskTemplate[];
    created_at?: string;
    updated_at?: string;
}

export interface WorkflowSnapshotStage {
    id: number;
    code: string;
    sort_order: number;
    is_terminal: boolean;
    color: string;
    name: string | null;
    description: string | null;
}

export interface WorkflowSnapshot {
    locale: Locale | string;
    captured_at: string;
    stages: WorkflowSnapshotStage[];
}

export interface CaseStage {
    id: number;
    case_id: number;
    workflow_stage_id: number | null;
    is_ad_hoc: boolean;
    code: string;
    sort_order: number;
    is_terminal: boolean;
    color: string;
    name: Record<string, string>;
    description: Record<string, string>;
    name_resolved: string;
}

export interface CreateCaseStagePayload {
    name: Record<string, string>;
    description?: Record<string, string>;
    color: string;
    is_terminal?: boolean;
}

export interface UpdateCaseStagePayload {
    name?: Record<string, string>;
    description?: Record<string, string>;
    color?: string;
    is_terminal?: boolean;
}

export type DeleteStagePolicy = 'move' | 'trash';

export interface DeleteCaseStageBody {
    policy: DeleteStagePolicy;
    move_to_stage_id?: number;
}

export interface WorkflowTask {
    id: number;
    tenant_id?: number | null;
    case_id: number;
    workflow_stage_id: number | null;
    case_stage_id: number | null;
    task_template_id: number | null;
    cloned_from_task_id: number | null;
    requester_id: number | null;
    assigned_to: number | null;
    subject: string;
    description: string | null;
    type: TaskType;
    priority: TaskPriority;
    status: TaskStatus;
    sort_order: number;
    due_date: string | null;
    estimated_hours: number | null;
    actual_hours: number | null;
    document_id: number | null;
    is_adhoc: boolean;
    template?: TaskTemplate;
    workflow_stage?: WorkflowStage;
    assignee?: { id: number; name: string };
    created_at?: string;
    updated_at?: string;
}

export const SUPPORTED_LOCALES: Locale[] = ['es', 'en', 'fr'];

export const TASK_TYPE_OPTIONS: Array<{ value: TaskType; label: string }> = [
    { value: 'translation',   label: 'tasks.types.translation' },
    { value: 'case_creation', label: 'tasks.types.case_creation' },
    { value: 'accounting',    label: 'tasks.types.accounting' },
    { value: 'filing',        label: 'tasks.types.filing' },
    { value: 'document',      label: 'tasks.types.document' },
    { value: 'other',         label: 'tasks.types.other' },
];

export const TASK_PRIORITY_OPTIONS: Array<{ value: TaskPriority; label: string; color: string }> = [
    { value: 'urgent', label: 'tasks.priority.urgent', color: 'danger' },
    { value: 'high',   label: 'tasks.priority.high',   color: 'warning' },
    { value: 'medium', label: 'tasks.priority.medium', color: 'info' },
    { value: 'low',    label: 'tasks.priority.low',    color: 'secondary' },
];

export const STAGE_COLOR_OPTIONS = [
    'primary', 'secondary', 'success', 'warning', 'danger', 'info', 'dark',
] as const;

export interface UpdateWorkflowTaskCorePayload {
    subject: string;
    description?: string | null;
    type?: TaskType;
    priority?: 'low' | 'medium' | 'high' | 'urgent';
    assigned_to?: number | null;
    due_date?: string | null;
}
