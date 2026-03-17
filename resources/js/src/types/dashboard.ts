export interface DashboardMetrics {
    active_cases_assigned_to_me: number;
    today_events: number;
    pending_todos: number;
}

export interface DashboardTask {
    id: number;
    title: string;
    description: string | null;
    tag: string | null;
    priority: 'low' | 'medium' | 'high';
    status: 'pending' | 'important';
    due_date: string | null;
    case: { id: number; case_number: string } | null;
}

export interface DashboardEvent {
    id: number;
    title: string;
    start_date: string;
    end_date: string;
    all_day: boolean;
    category: string;
    hex_color: string;
    location: string | null;
    client_name: string | null;
}

export interface DashboardCase {
    id: number;
    case_number: string;
    status: string;
    priority: string;
    priority_label: string;
    progress: number;
    stage: string | null;
    stage_label: string | null;
    client: { id: number; full_name: string } | null;
    case_type: { id: number; name: string; code: string } | null;
    next_deadline: string | null;
}

export interface DashboardData {
    metrics: DashboardMetrics;
    assigned_tasks: DashboardTask[];
    upcoming_events: DashboardEvent[];
    recent_cases: DashboardCase[];
}
