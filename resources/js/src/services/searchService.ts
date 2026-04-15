import api from './api';
import type { SearchResponse } from '@/types/search';

const searchService = {
    async search(q: string, limit = 5): Promise<SearchResponse> {
        const { data } = await api.get('/search', { params: { q, limit } });
        // Backend returns results grouped by type { clients: [], cases: [], ... }
        // Flatten into a single array for the component
        const flat = Object.values(data.results as Record<string, unknown[]>).flat() as SearchResponse['results'];
        return {
            query: data.query,
            results: flat,
            totals: data.totals,
        };
    },
};

export default searchService;
