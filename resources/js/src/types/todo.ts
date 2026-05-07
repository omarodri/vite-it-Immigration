export type TodoStatus = 'pending' | 'complete' | 'important' | 'trash';
export type TodoPriority = 'low' | 'medium' | 'high';
export type TodoSource = 'manual' | 'wizard' | 'workflow_add' | 'workflow_sync';

export interface Todo {
    id: number;
    tenant_id: number;
    title: string;
    description: string | null;
    assigned_to_id: number | null;
    case_id: number | null;
    task_id: number | null;
    task_template_id: number | null;
    is_core: boolean;
    source: TodoSource;
    tag: string | null;
    priority: TodoPriority;
    status: TodoStatus;
    due_date: string | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    assigned_to?: { id: number; name: string; avatar_url: string | null } | null;
    case?: { id: number; case_number: string; client_name?: string } | null;
}

export interface CreateTodoData {
    title: string;
    description?: string;
    assigned_to_id?: number | null;
    case_id?: number | null;
    tag?: string;
    priority?: TodoPriority;
    status?: string;
    due_date?: string;
}

export interface UpdateTodoData {
    title?: string;
    description?: string;
    assigned_to_id?: number | null;
    case_id?: number | null;
    tag?: string;
    priority?: TodoPriority;
    due_date?: string;
}

export interface CaseOption {
    id: number;
    case_number: string;
    client_name?: string;
}

export interface TodoCoreTaskSummary {
    id?: number;
    subject: string;
    assignedToName?: string;
    dueDate?: string;
}

export interface TodoCoreConfirmPayload {
    scenario: 'wizard_create' | 'add_to_existing' | 'remove_from_existing';
    tasksAffected: TodoCoreTaskSummary[];
    consultantName?: string;
    cascadeStrategy?: 'soft_delete' | 'keep_orphan';
}
