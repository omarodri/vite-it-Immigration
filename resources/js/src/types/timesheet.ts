export interface TimeLog {
    id: number;
    case_id: number;
    todo_id: number | null;
    user: {
        id: number;
        name: string;
    };
    work_date: string;
    duration_seconds: number;
    duration_formatted: string;
    description: string | null;
    started_at: string | null;
    ended_at: string | null;
    elapsed_seconds: number;
    is_running: boolean;
    source: 'manual' | 'timer';
    todo?: { id: number; title: string } | null;
    created_at: string;
}

export interface ManualLogPayload {
    case_id: number;
    duration_seconds: number;
    work_date?: string;
    description?: string;
    todo_id?: number | null;
}

export interface StartTimerPayload {
    todo_id?: number | null;
}

export interface TimeLogFilters {
    user_id?: number;
    date_from?: string;
    date_to?: string;
    per_page?: number;
    page?: number;
}

export interface TimeLogPaginationMeta {
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
    from?: number;
    to?: number;
    [key: string]: unknown;
}
