import api from '@/services/api';
import type { ImportantDateAlert, ImportantDateAlertMeta, ImportantDateFilters } from '@/types/important-date-alert';

export interface ImportantDateAlertResponse {
    data: ImportantDateAlert[];
    links: Record<string, string | null>;
    meta: ImportantDateAlertMeta;
}

const importantDateAlertService = {
    async list(
        filters: Partial<ImportantDateFilters>,
        page = 1
    ): Promise<ImportantDateAlertResponse> {
        const params: Record<string, string | number> = { page };
        if (filters.consultant_id) params.consultant_id = filters.consultant_id;
        if (filters.case_type_id) params.case_type_id = filters.case_type_id;
        if (filters.urgency && filters.urgency !== 'all') params.urgency = filters.urgency;
        if (filters.search) params.search = filters.search;
        if (filters.sort_by) params.sort_by = filters.sort_by;
        if (filters.sort_dir) params.sort_dir = filters.sort_dir;
        if (filters.per_page) params.per_page = filters.per_page;
        const { data } = await api.get('/important-date-alerts', { params });
        return data;
    },

    async linkEvent(dateId: number, eventId: number): Promise<ImportantDateAlert> {
        const { data } = await api.post(`/important-date-alerts/${dateId}/link-event`, {
            event_id: eventId,
        });
        return data.data;
    },
};

export default importantDateAlertService;
