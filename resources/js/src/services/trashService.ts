import api from './api';
import type { TrashableType, TrashSummary, TrashPaginatedResponse } from '@/types/trash';

const trashService = {
    async summary(): Promise<TrashSummary> {
        const { data } = await api.get('/trash');
        return data;
    },

    async list(
        type: TrashableType,
        params?: { page?: number; search?: string; per_page?: number }
    ): Promise<TrashPaginatedResponse> {
        const { data } = await api.get(`/trash/${type}`, { params });
        return data;
    },

    async restore(type: TrashableType, id: number): Promise<void> {
        await api.patch(`/trash/${type}/${id}/restore`);
    },

    async restoreWithParents(type: TrashableType, id: number): Promise<void> {
        await api.patch(`/trash/${type}/${id}/restore-with-parents`);
    },

    async forceDelete(type: TrashableType, id: number): Promise<void> {
        await api.delete(`/trash/${type}/${id}`);
    },

    async purge(olderThanDays: number = 30): Promise<void> {
        await api.delete('/trash/purge', { data: { older_than_days: olderThanDays } });
    },
};

export default trashService;
