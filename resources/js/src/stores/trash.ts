import { defineStore } from 'pinia';
import trashService from '@/services/trashService';
import type { TrashableType, TrashItem, TrashSummary, TrashMeta } from '@/types/trash';

interface TrashState {
    summary: TrashSummary | null;
    items: TrashItem[];
    meta: TrashMeta | null;
    activeTab: TrashableType;
    isLoading: boolean;
    isSummaryLoading: boolean;
    error: string | null;
}

export const useTrashStore = defineStore('trash', {
    state: (): TrashState => ({
        summary: null,
        items: [],
        meta: null,
        activeTab: 'clients',
        isLoading: false,
        isSummaryLoading: false,
        error: null,
    }),

    getters: {
        totalCount: (state): number =>
            state.summary
                ? Object.values(state.summary.counts).reduce((a, b) => a + b, 0)
                : 0,
    },

    actions: {
        async fetchSummary() {
            this.isSummaryLoading = true;
            try {
                this.summary = await trashService.summary();
            } finally {
                this.isSummaryLoading = false;
            }
        },

        async fetchItems(type: TrashableType, page = 1, search?: string) {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await trashService.list(type, { page, search, per_page: 15 });
                this.items = response.data;
                this.meta = response.meta;
            } catch (err: any) {
                this.error = err.response?.data?.message || 'Error loading trash';
            } finally {
                this.isLoading = false;
            }
        },

        async restoreItem(type: TrashableType, id: number) {
            await trashService.restore(type, id);
            await this.fetchSummary();
            await this.fetchItems(type, this.meta?.current_page ?? 1);
        },

        async restoreWithParents(type: TrashableType, id: number) {
            await trashService.restoreWithParents(type, id);
            await this.fetchSummary();
            await this.fetchItems(type, this.meta?.current_page ?? 1);
        },

        async forceDeleteItem(type: TrashableType, id: number) {
            await trashService.forceDelete(type, id);
            await this.fetchSummary();
            await this.fetchItems(type, this.meta?.current_page ?? 1);
        },

        async purgeAll(olderThanDays = 30) {
            await trashService.purge(olderThanDays);
            await this.fetchSummary();
            this.items = [];
            this.meta = null;
        },
    },
});
