export type TrashableType = 'clients' | 'cases' | 'companions' | 'documents';

export interface TrashItem {
    id: number;
    type: TrashableType;
    display_name: string;
    subtitle?: string;
    deleted_at: string;
    days_until_purge: number;
    parent_in_trash: boolean;
    parent?: {
        type: TrashableType;
        id: number;
        name: string;
    };
    can_restore: boolean;
}

export interface TrashSummary {
    counts: Record<TrashableType, number>;
    auto_purge_days: number;
}

export interface TrashMeta {
    current_page: number;
    total: number;
    last_page: number;
    per_page: number;
}

export interface TrashPaginatedResponse {
    data: TrashItem[];
    meta: TrashMeta;
}
