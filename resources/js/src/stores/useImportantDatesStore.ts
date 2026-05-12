import { defineStore } from 'pinia';
import { ref, reactive } from 'vue';
import importantDateAlertService from '@/services/importantDateAlertService';
import type { ImportantDateAlert, ImportantDateAlertMeta, ImportantDateFilters } from '@/types/important-date-alert';

export const useImportantDatesStore = defineStore('importantDates', () => {
    const items = ref<ImportantDateAlert[]>([]);
    const meta = ref<ImportantDateAlertMeta | null>(null);
    const isLoading = ref(false);

    const filters = reactive<ImportantDateFilters>({
        consultant_id: null,
        case_type_id: null,
        urgency: 'all',
        search: '',
        sort_by: 'urgency',
        sort_dir: 'asc',
        per_page: 25,
    });

    async function fetch(page = 1): Promise<void> {
        isLoading.value = true;
        try {
            const response = await importantDateAlertService.list(filters, page);
            items.value = response.data;
            meta.value = response.meta;
        } catch {
            // handled by API interceptor
        } finally {
            isLoading.value = false;
        }
    }

    function markAsLinkedToEvent(dateId: number, eventId: number): void {
        const item = items.value.find((i) => i.id === dateId);
        if (item) {
            item.calendar_event_id = eventId;
            item.has_calendar_event = true;
        }
    }

    function resetFilters(): void {
        filters.consultant_id = null;
        filters.case_type_id = null;
        filters.urgency = 'all';
        filters.search = '';
        filters.sort_by = 'urgency';
        filters.sort_dir = 'asc';
    }

    return { items, meta, isLoading, filters, fetch, markAsLinkedToEvent, resetFilters };
});
