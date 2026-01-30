/**
 * Pagination Types
 * Interfaces for paginated API responses (Laravel style)
 */

export interface PaginationParams {
    page?: number;
    per_page?: number;
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
    search?: string;
    filters?: Record<string, string | number | boolean>;
}

export interface PaginationMeta {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
    path: string;
}

export interface PaginationLinks {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
}

export interface PaginatedResponse<T> {
    data: T[];
    meta: PaginationMeta;
    links: PaginationLinks;
}

export interface SimplePaginatedResponse<T> {
    data: T[];
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
}

export interface PaginationState {
    currentPage: number;
    perPage: number;
    total: number;
    lastPage: number;
}

export const DEFAULT_PAGINATION: PaginationParams = {
    page: 1,
    per_page: 15,
    sort_direction: 'desc',
};
