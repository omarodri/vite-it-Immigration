<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/" class="text-primary hover:underline">{{ $t('home') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('alerts.title') }}</span>
            </li>
        </ul>

        <div class="panel">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
                <h5 class="font-semibold text-lg dark:text-white-light">{{ $t('alerts.title') }}</h5>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3 mb-5">
                <!-- Status filter -->
                <select v-model="filters.status" class="form-select w-48" @change="fetchAlerts">
                    <option value="">{{ $t('alerts.filter_all') }}</option>
                    <option value="overdue">{{ $t('alerts.filter_overdue') }}</option>
                    <option value="critical">{{ $t('alerts.filter_critical') }}</option>
                    <option value="ok">{{ $t('alerts.filter_ok') }}</option>
                </select>

                <!-- Search -->
                <div class="relative flex-1 max-w-xs">
                    <input
                        v-model="filters.search"
                        type="text"
                        class="form-input pl-9 peer"
                        :placeholder="$t('clients.search_placeholder')"
                        @input="onSearchInput"
                    />
                    <div class="absolute ltr:left-3 rtl:right-3 top-1/2 -translate-y-1/2 peer-focus:text-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="11.5" cy="11.5" r="9.5" stroke="currentColor" stroke-width="1.5" opacity="0.5" />
                            <path d="M18.5 18.5L22 22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Loading -->
            <div v-if="isLoading" class="flex items-center justify-center py-10">
                <span class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10 inline-block align-middle"></span>
            </div>

            <!-- Empty state -->
            <div v-else-if="alerts.length === 0" class="flex flex-col items-center justify-center py-10 text-gray-400 dark:text-gray-500">
                <icon-bell class="w-12 h-12 mb-3 opacity-50" />
                <p>{{ $t('alerts.no_alerts') }}</p>
            </div>

            <!-- Table -->
            <div v-else class="table-responsive">
                <table class="table-hover">
                    <thead>
                        <tr>
                            <th>{{ $t('alerts.entity_client') }} / {{ $t('alerts.entity_companion') }}</th>
                            <th>{{ $t('documents.document_type') }}</th>
                            <th>{{ $t('documents.document_number') }}</th>
                            <th>{{ $t('documents.expiry_date') }}</th>
                            <th>{{ $t('clients.status') }}</th>
                            <th class="text-center">{{ $t('clients.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="doc in alerts" :key="doc.id">
                            <td>
                                <router-link
                                    :to="`/clients/${doc.client_id || doc.entity_id}`"
                                    class="text-primary hover:underline font-medium"
                                >
                                    {{ doc.entity_name || '-' }}
                                </router-link>
                                <span v-if="doc.entity_type === 'companion'" class="badge badge-outline-secondary ml-2 text-xs">
                                    {{ $t('alerts.entity_companion') }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-outline-primary text-xs">
                                    {{ doc.document_type === 'other' && doc.display_name ? doc.display_name : getDocumentTypeLabel(doc.document_type) }}
                                </span>
                            </td>
                            <td>{{ doc.document_number || '-' }}</td>
                            <td>{{ doc.expiry_date ? formatDate(doc.expiry_date) : '-' }}</td>
                            <td>
                                <ExpirationAlertBadge
                                    :alert-status="doc.alert_status || 'none'"
                                    :days-remaining="doc.days_remaining"
                                />
                            </td>
                            <td class="text-center">
                                <button
                                    v-if="doc.id && doc.expiry_date"
                                    type="button"
                                    class="btn btn-sm btn-outline-primary p-1"
                                    :title="$t('documents.sync_to_calendar')"
                                    @click="syncToCalendar(doc)"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="pagination.lastPage > 1" class="flex items-center justify-between mt-5">
                <p class="text-sm text-gray-500">
                    {{ $t('clients.showing') }} {{ pagination.from }}-{{ pagination.to }} {{ $t('clients.of') }} {{ pagination.total }}
                </p>
                <div class="flex gap-1">
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary"
                        :disabled="pagination.currentPage <= 1"
                        @click="goToPage(pagination.currentPage - 1)"
                    >
                        &laquo;
                    </button>
                    <button
                        v-for="page in visiblePages"
                        :key="page"
                        type="button"
                        class="btn btn-sm"
                        :class="page === pagination.currentPage ? 'btn-primary' : 'btn-outline-primary'"
                        @click="goToPage(page)"
                    >
                        {{ page }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary"
                        :disabled="pagination.currentPage >= pagination.lastPage"
                        @click="goToPage(pagination.currentPage + 1)"
                    >
                        &raquo;
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMeta } from '@/composables/use-meta';
import { useNotification } from '@/composables/useNotification';
import { legalDocumentService } from '@/services/legalDocumentService';
import { formatDate } from '@/utils/formatters';
import { DOCUMENT_TYPES } from '@/types/legal-document';
import type { LegalDocument } from '@/types/legal-document';
import ExpirationAlertBadge from '@/components/ExpirationAlertBadge.vue';

import IconBell from '@/components/icon/icon-bell.vue';

const { t } = useI18n();
useMeta({ title: 'Expiration Alerts' });
const { success, error } = useNotification();

const isLoading = ref(false);
const alerts = ref<LegalDocument[]>([]);

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const filters = reactive({
    status: '',
    search: '',
    per_page: '20',
});

const pagination = reactive({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    from: 0,
    to: 0,
});

const visiblePages = computed(() => {
    const pages: number[] = [];
    const start = Math.max(1, pagination.currentPage - 2);
    const end = Math.min(pagination.lastPage, pagination.currentPage + 2);
    for (let i = start; i <= end; i++) {
        pages.push(i);
    }
    return pages;
});

function getDocumentTypeLabel(type: string): string {
    const found = DOCUMENT_TYPES.find(dt => dt.value === type);
    return found ? t(found.labelKey) : type;
}

async function fetchAlerts(page = 1) {
    isLoading.value = true;
    try {
        const params: Record<string, string> = {
            page: page.toString(),
            per_page: filters.per_page,
        };
        if (filters.status) params.status = filters.status;
        if (filters.search) params.search = filters.search;

        const response = await legalDocumentService.getExpirationAlerts(params);
        alerts.value = response.data;
        pagination.currentPage = response.meta?.current_page || response.current_page || 1;
        pagination.lastPage = response.meta?.last_page || response.last_page || 1;
        pagination.total = response.meta?.total || response.total || 0;
        pagination.from = response.meta?.from || response.from || 0;
        pagination.to = response.meta?.to || response.to || 0;
    } catch {
        // Error handled by API interceptor
    } finally {
        isLoading.value = false;
    }
}

function onSearchInput() {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchAlerts(1);
    }, 400);
}

function goToPage(page: number) {
    if (page < 1 || page > pagination.lastPage) return;
    fetchAlerts(page);
}

async function syncToCalendar(doc: LegalDocument) {
    if (!doc.id) return;
    try {
        await legalDocumentService.syncToCalendar(doc.id);
        success(t('documents.calendar_sync_success'));
    } catch {
        error(t('documents.calendar_sync_failed'));
    }
}

onMounted(() => {
    fetchAlerts();
});
</script>
