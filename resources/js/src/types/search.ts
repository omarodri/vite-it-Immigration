export interface SearchResult {
    id: number;
    type: 'client' | 'case' | 'companion' | 'event' | 'todo' | 'trash';
    label: string;
    description: string;
    route: string;
}

export interface SearchResponse {
    query: string;
    results: SearchResult[];
    totals: Record<string, number>;
}
