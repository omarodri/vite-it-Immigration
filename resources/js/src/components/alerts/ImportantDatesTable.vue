<script lang="ts" setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { storeToRefs } from 'pinia';
import { useImportantDatesStore } from '@/stores/useImportantDatesStore';
import { formatDate } from '@/utils/formatters';
import UrgencyBadge from '@/components/alerts/UrgencyBadge.vue';
import AddToCalendarButton from '@/components/alerts/AddToCalendarButton.vue';
import type { ImportantDateAlert, ImportantDateAlertMeta } from '@/types/important-date-alert';

const props = defineProps<{
    items: ImportantDateAlert[];
    meta: ImportantDateAlertMeta | null;
    isLoading: boolean;
}>();

const emit = defineEmits<{
    'page-change': [page: number];
    'sort-change': [col: string, dir: 'asc' | 'desc'];
    'add-to-calendar': [item: ImportantDateAlert];
    'view-in-calendar': [eventId: number];
}>();

const { t } = useI18n();
const store = useImportantDatesStore();
const { filters } = storeToRefs(store);

const sortableColumns = [
    { key: 'case_number', labelKey: 'alerts.column.case' },
    { key: 'urgency',     labelKey: 'alerts.column.status' },
    { key: 'due_date',    labelKey: 'alerts.column.due_date' },
] as const;

function isSortable(col: string): boolean {
    return sortableColumns.some((c) => c.key === col);
}

function toggleSort(col: string) {
    if (!isSortable(col)) return;
    if (filters.value.sort_by === col) {
        const newDir = filters.value.sort_dir === 'asc' ? 'desc' : 'asc';
        filters.value.sort_dir = newDir;
        emit('sort-change', col, newDir);
    } else {
        filters.value.sort_by = col as any;
        filters.value.sort_dir = 'asc';
        emit('sort-change', col, 'asc');
    }
}

function sortIcon(col: string): 'asc' | 'desc' | 'none' {
    if (!isSortable(col)) return 'none';
    if (filters.value.sort_by !== col) return 'none';
    return filters.value.sort_dir;
}

const visiblePages = computed(() => {
    if (!props.meta) return [];
    const pages: number[] = [];
    const start = Math.max(1, props.meta.current_page - 2);
    const end = Math.min(props.meta.last_page, props.meta.current_page + 2);
    for (let i = start; i <= end; i++) pages.push(i);
    return pages;
});

function goToPage(page: number) {
    if (!props.meta) return;
    if (page < 1 || page > props.meta.last_page) return;
    emit('page-change', page);
}
</script>

<template>
    <div>
        <div v-if="isLoading" class="flex items-center justify-center py-10">
            <span class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10 inline-block align-middle"></span>
        </div>

        <div
            v-else-if="items.length === 0"
            class="flex flex-col items-center justify-center py-10 text-gray-400 dark:text-gray-500"
        >
            <svg class="w-12 h-12 mb-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
            </svg>
            <p>{{ t('alerts.important_dates.empty') }}</p>
        </div>

        <div v-else class="table-responsive">
            <table class="table-hover">
                <thead>
                    <tr>
                        <th
                            class="cursor-pointer select-none"
                            @click="toggleSort('case_number')"
                        >
                            <span class="inline-flex items-center gap-1">
                                {{ t('alerts.column.case') }}
                                <svg v-if="sortIcon('case_number') === 'asc'" class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path d="M5 12l5-5 5 5H5z"/></svg>
                                <svg v-else-if="sortIcon('case_number') === 'desc'" class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5H5z"/></svg>
                                <svg v-else class="w-3 h-3 opacity-30" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5-5 5 5H5zM5 12l5 5 5-5H5z"/></svg>
                            </span>
                        </th>
                        <th>{{ t('alerts.column.client') }}</th>
                        <th>{{ t('alerts.column.milestone') }}</th>
                        <th
                            class="cursor-pointer select-none"
                            @click="toggleSort('due_date')"
                        >
                            <span class="inline-flex items-center gap-1">
                                {{ t('alerts.column.due_date') }}
                                <svg v-if="sortIcon('due_date') === 'asc'" class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path d="M5 12l5-5 5 5H5z"/></svg>
                                <svg v-else-if="sortIcon('due_date') === 'desc'" class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5H5z"/></svg>
                                <svg v-else class="w-3 h-3 opacity-30" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5-5 5 5H5zM5 12l5 5 5-5H5z"/></svg>
                            </span>
                        </th>
                        <th>{{ t('alerts.column.consultant') }}</th>
                        <th
                            class="cursor-pointer select-none"
                            @click="toggleSort('urgency')"
                        >
                            <span class="inline-flex items-center gap-1">
                                {{ t('alerts.column.status') }}
                                <svg v-if="sortIcon('urgency') === 'asc'" class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path d="M5 12l5-5 5 5H5z"/></svg>
                                <svg v-else-if="sortIcon('urgency') === 'desc'" class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5H5z"/></svg>
                                <svg v-else class="w-3 h-3 opacity-30" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5-5 5 5H5zM5 12l5 5 5-5H5z"/></svg>
                            </span>
                        </th>
                        <th class="text-center">{{ t('alerts.column.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items" :key="item.id">
                        <td>
                            <router-link
                                :to="item.case_url"
                                class="text-primary hover:underline font-medium"
                            >
                                #{{ item.case_number }}
                            </router-link>
                            <div v-if="item.case_type" class="text-xs text-gray-500 mt-0.5">
                                {{ item.case_type.name }}
                            </div>
                        </td>
                        <td>
                            <router-link
                                v-if="item.client_id"
                                :to="`/clients/${item.client_id}`"
                                class="hover:underline"
                            >
                                {{ item.client_name || '-' }}
                            </router-link>
                            <span v-else>{{ item.client_name || '-' }}</span>
                        </td>
                        <td>{{ item.label }}</td>
                        <td>{{ formatDate(item.due_date) }}</td>
                        <td>{{ item.consultant?.name || '-' }}</td>
                        <td>
                            <UrgencyBadge
                                :urgency-bucket="item.urgency_bucket"
                                :days-diff="item.days_diff"
                            />
                        </td>
                        <td class="text-center">
                            <AddToCalendarButton
                                :item="item"
                                @add-to-calendar="emit('add-to-calendar', $event)"
                                @view-in-calendar="emit('view-in-calendar', $event)"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between mt-5">
            <p class="text-sm text-gray-500">
                {{ t('clients.showing') }} {{ meta.from }}-{{ meta.to }} {{ t('clients.of') }} {{ meta.total }}
            </p>
            <div class="flex gap-1">
                <button
                    type="button"
                    class="btn btn-sm btn-outline-primary"
                    :disabled="meta.current_page <= 1"
                    @click="goToPage(meta.current_page - 1)"
                >
                    &laquo;
                </button>
                <button
                    v-for="page in visiblePages"
                    :key="page"
                    type="button"
                    class="btn btn-sm"
                    :class="page === meta.current_page ? 'btn-primary' : 'btn-outline-primary'"
                    @click="goToPage(page)"
                >
                    {{ page }}
                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-primary"
                    :disabled="meta.current_page >= meta.last_page"
                    @click="goToPage(meta.current_page + 1)"
                >
                    &raquo;
                </button>
            </div>
        </div>
    </div>
</template>
