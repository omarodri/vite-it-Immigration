export interface ScrumColumn {
    id: number;
    title: string;
    order_index: number;
    tasks: ScrumTask[];
    tasks_count: number;
}

export interface ScrumTask {
    id: number;
    scrum_column_id: number;
    title: string;
    description?: string | null;
    description_preview?: string | null;
    tags: string[];
    category?: string | null;
    due_date?: string | null;
    order_index: number;
    is_completed?: boolean;
    created_at: string;
    updated_at?: string;
    assigned_to?: { id: number; name: string; email?: string } | null;
    case?: { id: number; case_number: string; client_name?: string } | null;
}

export interface CreateScrumTaskData {
    scrum_column_id: number;
    title: string;
    description?: string;
    tags?: string[];
    category?: string;
    due_date?: string;
    assigned_to_id?: number | null;
    case_id?: number | null;
}

export interface UpdateScrumTaskData {
    title?: string;
    description?: string;
    tags?: string[];
    category?: string;
    due_date?: string;
    assigned_to_id?: number | null;
    case_id?: number | null;
}

export interface MoveScrumTaskData {
    scrum_column_id: number;
    position: number;
}

export interface Assignee {
    id: number;
    name: string;
    email: string;
}

export interface ScrumBoardResponse {
    data: ScrumColumn[];
}
