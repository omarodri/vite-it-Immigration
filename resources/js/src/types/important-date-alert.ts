export type UrgencyBucket =
    | 'overdue_critical'
    | 'overdue_recent'
    | 'today'
    | 'upcoming_week'
    | 'upcoming_month';

export interface ImportantDateAlert {
    id: number;
    case_id: number;
    case_number: string;
    case_url: string;
    client_id: number;
    client_name: string | null;
    case_type: { id: number; name: string } | null;
    consultant: { id: number; name: string } | null;
    label: string;
    due_date: string;
    days_diff: number;
    urgency_level: 1 | 2 | 3 | 4 | 5;
    urgency_bucket: UrgencyBucket;
    has_calendar_event: boolean;
    calendar_event_id: number | null;
}

export interface ImportantDateAlertMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    window: { from: string; to: string; today: string };
    counts_by_urgency: Record<UrgencyBucket, number>;
}

export interface ImportantDateFilters {
    consultant_id: number | null;
    case_type_id: number | null;
    urgency: 'overdue' | 'today' | 'this_week' | 'upcoming' | 'all';
    search: string;
    sort_by: 'due_date' | 'urgency' | 'case_number';
    sort_dir: 'asc' | 'desc';
    per_page: number;
}
