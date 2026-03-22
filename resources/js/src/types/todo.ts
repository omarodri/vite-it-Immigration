export interface Todo {
    id: number;
    title: string;
    description?: string | null;
    tag?: 'archivar' | 'documentos' | 'seguimiento' | 'ircc' | 'contabilidad' | null;
    priority: 'low' | 'medium' | 'high';
    status: 'pending' | 'complete' | 'important' | 'trash';
    due_date?: string | null;
    created_at: string;
    updated_at?: string;
    assigned_to?: { id: number; name: string; avatar_url: string | null } | null;
    case?: { id: number; case_number: string; client_name?: string } | null;
}

export interface CreateTodoData {
    title: string;
    description?: string;
    assigned_to_id?: number | null;
    case_id?: number | null;
    tag?: string;
    priority?: 'low' | 'medium' | 'high';
    status?: string;
    due_date?: string;
}

export interface UpdateTodoData {
    title?: string;
    description?: string;
    assigned_to_id?: number | null;
    case_id?: number | null;
    tag?: string;
    priority?: 'low' | 'medium' | 'high';
    due_date?: string;
}

export interface CaseOption {
    id: number;
    case_number: string;
    client_name?: string;
}
