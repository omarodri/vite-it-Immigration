<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <a href="javascript:;" class="text-primary hover:underline">{{ $t('sidebar.clients') }}</a>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('clients.list') }}</span>
            </li>
        </ul>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
            <div class="panel bg-gradient-to-r from-cyan-500 to-cyan-400">
                <div class="flex justify-between">
                    <div class="text-white">
                        <p class="text-lg font-semibold">{{ clientStore.statistics?.prospect ?? 0 }}</p>
                        <p class="text-sm opacity-80">{{ $t('clients.prospects') }}</p>
                    </div>
                    <icon-user-plus class="w-10 h-10 text-white opacity-50" />
                </div>
            </div>
            <div class="panel bg-gradient-to-r from-green-500 to-green-400">
                <div class="flex justify-between">
                    <div class="text-white">
                        <p class="text-lg font-semibold">{{ clientStore.statistics?.active ?? 0 }}</p>
                        <p class="text-sm opacity-80">{{ $t('clients.active') }}</p>
                    </div>
                    <icon-users class="w-10 h-10 text-white opacity-50" />
                </div>
            </div>
            <div class="panel bg-gradient-to-r from-yellow-500 to-yellow-400">
                <div class="flex justify-between">
                    <div class="text-white">
                        <p class="text-lg font-semibold">{{ clientStore.statistics?.inactive ?? 0 }}</p>
                        <p class="text-sm opacity-80">{{ $t('clients.inactive') }}</p>
                    </div>
                    <icon-user class="w-10 h-10 text-white opacity-50" />
                </div>
            </div>
            <div class="panel bg-gradient-to-r from-gray-500 to-gray-400">
                <div class="flex justify-between">
                    <div class="text-white">
                        <p class="text-lg font-semibold">{{ clientStore.statistics?.archived ?? 0 }}</p>
                        <p class="text-sm opacity-80">{{ $t('clients.archived') }}</p>
                    </div>
                    <icon-archive class="w-10 h-10 text-white opacity-50" />
                </div>
            </div>
        </div>

        <div class="panel">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
                <h5 class="font-semibold text-lg dark:text-white-light">{{ $t('clients.client_management') }}</h5>
                <div class="flex items-center gap-2">
                    <!-- Bulk Actions Dropdown -->
                    <div v-if="selectedClients?.length > 0" class="dropdown">
                        <Popper :placement="'bottom-end'" :offsetDistance="0" :arrow="true">
                            <button type="button" class="btn btn-outline-danger gap-2">
                                <icon-trash-lines class="w-5 h-5" />
                                {{ $t('clients.actions') }} ({{ selectedClients.length }})
                                <icon-caret-down class="w-4 h-4" />
                            </button>
                            <template #content="{ close }">
                                <ul class="!min-w-[170px]">
                                    <li>
                                        <button
                                            type="button"
                                            class="w-full text-left"
                                            @click="confirmBulkDelete(); close()"
                                        >
                                            <icon-trash-lines class="w-4.5 h-4.5 mr-2 shrink-0" />
                                            {{ $t('clients.delete_selected') }}
                                        </button>
                                    </li>
                                </ul>
                            </template>
                        </Popper>
                    </div>

                    <!-- Selected Counter -->
                    <span v-if="selectedClients?.length > 0" class="text-sm text-gray-500">
                        {{ selectedClients.length }} {{ $t('clients.selected') }}
                    </span>

                    <router-link
                        v-can="'clients.create'"
                        to="/clients/create"
                        class="btn btn-primary gap-2"
                    >
                        <icon-user-plus class="w-5 h-5" />
                        {{ $t('clients.add_client') }}
                    </router-link>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-4 mb-5" role="search" aria-label="Filter clients">
                <!-- Search -->
                <div class="flex-1 min-w-[200px]">
                    <div class="relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            class="form-input pl-10 pr-4"
                            :placeholder="$t('clients.search_placeholder')"
                            aria-label="Search clients"
                            @input="debouncedSearch"
                        />
                        <div class="absolute left-3 top-1/2 -translate-y-1/2">
                            <span v-if="isDebouncing" class="animate-spin border-2 border-primary border-l-transparent rounded-full w-4 h-4 inline-block"></span>
                            <icon-search v-else class="w-5 h-5 text-gray-500" />
                        </div>
                    </div>
                </div>

                <!-- Status Filter -->
                <div class="w-40">
                    <select v-model="statusFilter" class="form-select" aria-label="Filter by status" @change="applyStatusFilter">
                        <option value="">{{ $t('clients.all_statuses') }}</option>
                        <option value="prospect">{{ $t('clients.prospect') }}</option>
                        <option value="active">{{ $t('clients.active') }}</option>
                        <option value="inactive">{{ $t('clients.inactive') }}</option>
                        <option value="archived">{{ $t('clients.archived') }}</option>
                    </select>
                </div>

                <!-- Per Page -->
                <div class="w-32">
                    <select v-model="perPage" class="form-select" aria-label="Results per page" @change="changePerPage">
                        <option :value="10">10 {{ $t('clients.per_page') }}</option>
                        <option :value="20">20 {{ $t('clients.per_page') }}</option>
                        <option :value="30">30 {{ $t('clients.per_page') }}</option>
                        <option :value="50">50 {{ $t('clients.per_page') }}</option>
                        <option :value="100">100 {{ $t('clients.per_page') }}</option>
                    </select>
                </div>

                <!-- Clear Selection Button -->
                <button
                    v-if="selectedClients?.length > 0"
                    type="button"
                    class="btn btn-outline-secondary btn-sm"
                    @click="clearSelection"
                >
                    {{ $t('clients.clear_selection') }}
                </button>
            </div>

            <!-- Skeleton Loader (initial load) -->
            <div v-if="showSkeleton" class="animate-pulse">
                <div class="table-responsive">
                    <table class="table-hover">
                        <thead>
                            <tr>
                                <th class="w-10"><div class="h-4 w-4 bg-gray-200 dark:bg-gray-700 rounded"></div></th>
                                <th class="w-20"><div class="h-4 w-8 bg-gray-200 dark:bg-gray-700 rounded"></div></th>
                                <th><div class="h-4 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div></th>
                                <th><div class="h-4 w-16 bg-gray-200 dark:bg-gray-700 rounded"></div></th>
                                <th><div class="h-4 w-16 bg-gray-200 dark:bg-gray-700 rounded"></div></th>
                                <th><div class="h-4 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div></th>
                                <th><div class="h-4 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="i in 5" :key="i">
                                <td><div class="h-4 w-4 bg-gray-200 dark:bg-gray-700 rounded"></div></td>
                                <td><div class="h-4 w-10 bg-gray-200 dark:bg-gray-700 rounded"></div></td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                                        <div class="space-y-2">
                                            <div class="h-4 w-32 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                            <div class="h-3 w-40 bg-gray-100 dark:bg-gray-800 rounded"></div>
                                        </div>
                                    </div>
                                </td>
                                <td><div class="h-5 w-16 bg-gray-200 dark:bg-gray-700 rounded-full"></div></td>
                                <td><div class="h-5 w-20 bg-gray-200 dark:bg-gray-700 rounded-full"></div></td>
                                <td><div class="h-4 w-24 bg-gray-200 dark:bg-gray-700 rounded"></div></td>
                                <td>
                                    <div class="flex gap-2">
                                        <div class="h-7 w-7 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                        <div class="h-7 w-7 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                        <div class="h-7 w-7 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Results area -->
            <div v-else-if="!showEmptyState" aria-live="polite">
                <!-- Desktop Table -->
                <div class="datatable hidden md:block">
                    <vue3-datatable
                        :key="`dt-${perPage}-${tableKey}`"
                        :rows="clientStore.clients"
                        :columns="columns"
                        :totalRows="clientStore.totalClients"
                        :isServerMode="true"
                        :loading="clientStore.isLoading"
                        :sortable="true"
                        :sortColumn="sortColumn"
                        :sortDirection="sortDirection"
                        :pageSize="perPage"
                        :page="currentPage"
                        :hasCheckbox="true"
                        @change="handleTableChange"
                        @rowSelect="handleRowSelect"
                        skin="whitespace-nowrap bh-table-hover"
                        firstArrow='<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5 rtl:rotate-180"> <path d="M13 19L7 12L13 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path opacity="0.5" d="M16.9998 19L10.9998 12L16.9998 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> </svg>'
                        lastArrow='<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5 rtl:rotate-180"> <path d="M11 19L17 12L11 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path opacity="0.5" d="M6.99976 19L12.9998 12L6.99976 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> </svg>'
                        previousArrow='<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5 rtl:rotate-180"> <path d="M15 5L9 12L15 19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> </svg>'
                        nextArrow='<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5 rtl:rotate-180"> <path d="M9 5L15 12L9 19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> </svg>'
                    >
                        <!-- ID Column -->
                        <template #id="data">
                            <span class="text-primary font-semibold">#{{ data.value.id }}</span>
                        </template>

                        <!-- Name Column -->
                        <template #first_name="data">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center" :class="getStatusAvatarClass(data.value.status)">
                                    <span class="font-semibold text-sm">
                                        {{ getInitials(data.value.first_name, data.value.last_name) }}
                                    </span>
                                </div>
                                <div>
                                    <div class="font-semibold">{{ data.value.first_name }} {{ data.value.last_name }}</div>
                                    <div class="text-xs text-gray-500">{{ data.value.email || $t('clients.no_email') }}</div>
                                </div>
                            </div>
                        </template>

                        <!-- Phone Column -->
                        <template #phone="data">
                            <span v-if="data.value.phone">{{ data.value.phone }}</span>
                            <span v-else class="text-gray-400">{{ $t('clients.no_phone') }}</span>
                        </template>

                        <!-- Status Column -->
                        <template #status="data">
                            <span class="badge" :class="getStatusBadgeClass(data.value.status)">
                                {{ $t(`clients.${data.value.status}`) }}
                            </span>
                        </template>

                        <!-- Canada Status Column -->
                        <template #canada_status="data">
                            <span v-if="data.value.canada_status" class="badge badge-outline-primary">
                                {{ formatCanadaStatus(data.value.canada_status) }}
                            </span>
                            <span v-else class="text-gray-400">-</span>
                        </template>

                        <!-- Created At Column -->
                        <template #created_at="data">
                            <span>{{ formatDate(data.value.created_at) }}</span>
                        </template>

                        <!-- Actions Column -->
                        <template #actions="data">
                            <div class="flex items-center gap-2">
                                <tippy content="View">
                                    <router-link
                                        :to="`/clients/${data.value.id}`"
                                        class="btn btn-sm btn-outline-info p-1.5"
                                        :aria-label="`View ${data.value.first_name}`"
                                    >
                                        <icon-eye class="w-4 h-4" />
                                    </router-link>
                                </tippy>
                                <tippy v-can="'clients.update'" content="Edit">
                                    <router-link
                                        :to="`/clients/${data.value.id}/edit`"
                                        class="btn btn-sm btn-outline-primary p-1.5"
                                        :aria-label="`Edit ${data.value.first_name}`"
                                    >
                                        <icon-pencil class="w-4 h-4" />
                                    </router-link>
                                </tippy>
                                <tippy v-if="data.value.status === 'prospect'" v-can="'clients.update'" content="Convert to Active">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-success p-1.5"
                                        :aria-label="`Convert ${data.value.first_name} to active`"
                                        @click="confirmConvert(data.value)"
                                    >
                                        <icon-arrow-forward class="w-4 h-4" />
                                    </button>
                                </tippy>
                                <tippy v-can="'clients.delete'" content="Delete">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger p-1.5"
                                        :aria-label="`Delete ${data.value.first_name}`"
                                        @click="confirmDelete(data.value)"
                                    >
                                        <icon-trash-lines class="w-4 h-4" />
                                    </button>
                                </tippy>
                            </div>
                        </template>
                    </vue3-datatable>
                </div>

                <!-- Mobile Cards -->
                <div class="md:hidden space-y-3">
                    <div v-if="clientStore.isLoading" class="text-center py-4">
                        <span class="animate-spin border-2 border-primary border-l-transparent rounded-full w-6 h-6 inline-block"></span>
                    </div>
                    <div
                        v-for="client in clientStore.clients"
                        :key="client.id"
                        class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-3"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center" :class="getStatusAvatarClass(client.status)">
                                    <span class="font-semibold text-sm">
                                        {{ getInitials(client.first_name, client.last_name) }}
                                    </span>
                                </div>
                                <div>
                                    <div class="font-semibold dark:text-white-light">{{ client.first_name }} {{ client.last_name }}</div>
                                    <div class="text-xs text-gray-500">{{ client.email || $t('clients.no_email') }}</div>
                                </div>
                            </div>
                            <span class="text-primary font-semibold text-sm">#{{ client.id }}</span>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge" :class="getStatusBadgeClass(client.status)">
                                {{ $t(`clients.${client.status}`) }}
                            </span>
                            <span v-if="client.canada_status" class="badge badge-outline-primary">
                                {{ formatCanadaStatus(client.canada_status) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-700">
                            <span class="text-xs text-gray-500">{{ formatDate(client.created_at) }}</span>
                            <div class="flex items-center gap-2">
                                <router-link
                                    :to="`/clients/${client.id}`"
                                    class="btn btn-sm btn-outline-info p-1.5"
                                >
                                    <icon-eye class="w-4 h-4" />
                                </router-link>
                                <router-link
                                    v-can="'clients.update'"
                                    :to="`/clients/${client.id}/edit`"
                                    class="btn btn-sm btn-outline-primary p-1.5"
                                >
                                    <icon-pencil class="w-4 h-4" />
                                </router-link>
                                <button
                                    v-if="client.status === 'prospect'"
                                    v-can="'clients.update'"
                                    type="button"
                                    class="btn btn-sm btn-outline-success p-1.5"
                                    @click="confirmConvert(client)"
                                >
                                    <icon-arrow-forward class="w-4 h-4" />
                                </button>
                                <button
                                    v-can="'clients.delete'"
                                    type="button"
                                    class="btn btn-sm btn-outline-danger p-1.5"
                                    @click="confirmDelete(client)"
                                >
                                    <icon-trash-lines class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile Pagination -->
                    <div v-if="clientStore.totalClients > perPage" class="flex items-center justify-between pt-3">
                        <span class="text-sm text-gray-500">
                            {{ $t('clients.page') }} {{ currentPage }} {{ $t('clients.of') }} {{ Math.ceil(clientStore.totalClients / perPage) }}
                        </span>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary"
                                :disabled="currentPage <= 1"
                                @click="handlePageChange(currentPage - 1)"
                            >
                                {{ $t('clients.previous') }}
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary"
                                :disabled="currentPage >= Math.ceil(clientStore.totalClients / perPage)"
                                @click="handlePageChange(currentPage + 1)"
                            >
                                {{ $t('clients.next') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="showEmptyState" class="text-center py-10" aria-live="polite">
                <!-- With active filters -->
                <template v-if="hasActiveFilters">
                    <icon-search class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
                    <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">{{ $t('clients.no_results_found') }}</h3>
                    <p class="text-gray-500 dark:text-gray-500 mb-4">
                        {{ $t('clients.no_clients_match_criteria') }}
                    </p>
                    <button type="button" class="btn btn-outline-primary gap-2" @click="clearFilters">
                        <icon-x class="w-4 h-4" />
                        {{ $t('clients.clear_filters') }}
                    </button>
                </template>
                <!-- No data at all -->
                <template v-else>
                    <icon-users class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
                    <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">{{ $t('clients.no_clients_yet') }}</h3>
                    <p class="text-gray-500 dark:text-gray-500 mb-4">
                        {{ $t('clients.get_started_by_adding') }}
                    </p>
                    <router-link v-can="'clients.create'" to="/clients/create" class="btn btn-primary gap-2">
                        <icon-user-plus class="w-5 h-5" />
                        {{ $t('clients.add_first_client') }}
                    </router-link>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Vue3Datatable from '@bhplugin/vue3-datatable';
import { useMeta } from '@/composables/use-meta';
import { useClientStore } from '@/stores/client';
import { useNotification } from '@/composables/useNotification';
import { useDebounce } from '@/composables/useDebounce';
import { formatDate } from '@/utils/formatters';
import type { Client, ClientStatus } from '@/types/client';

// Icons
import IconUserPlus from '@/components/icon/icon-user-plus.vue';
import IconUser from '@/components/icon/icon-user.vue';
import IconSearch from '@/components/icon/icon-search.vue';
import IconEye from '@/components/icon/icon-eye.vue';
import IconPencil from '@/components/icon/icon-pencil.vue';
import IconTrashLines from '@/components/icon/icon-trash-lines.vue';
import IconUsers from '@/components/icon/icon-users.vue';
import IconCaretDown from '@/components/icon/icon-caret-down.vue';
import IconX from '@/components/icon/icon-x.vue';
import IconArchive from '@/components/icon/icon-archive.vue';
import IconArrowForward from '@/components/icon/icon-arrow-forward.vue';

useMeta({ title: 'Client Management' });

const { t } = useI18n();
const clientStore = useClientStore();
const { confirm: confirmDialog, success, error } = useNotification();
const { debounce, isDebouncing } = useDebounce(300);

// Local state
const searchQuery = ref('');
const statusFilter = ref('');
const perPage = ref(10);
const currentPage = ref(1);
const sortColumn = ref('created_at');
const sortDirection = ref<'asc' | 'desc'>('desc');
const selectedClients = ref<Client[]>([]);
const initialLoading = ref(true);
const tableKey = ref(0);

// Computed
const hasActiveFilters = computed(() => !!searchQuery.value || !!statusFilter.value);
const showSkeleton = computed(() => initialLoading.value && clientStore.clients.length === 0);
const showEmptyState = computed(() => !clientStore.isLoading && !initialLoading.value && clientStore.clients.length === 0);

// Table columns
const columns = computed(() => [
    { field: 'id', title: 'ID', width: '80px', isUnique: true },
    { field: 'first_name', title: t('clients.name'), minWidth: '250px' },
    { field: 'phone', title: t('clients.phone'), sort: false, minWidth: '150px' },
    { field: 'status', title: t('clients.status'), sort: true, width: '120px' },
    { field: 'canada_status', title: t('clients.canada_status'), sort: false, width: '150px' },
    { field: 'created_at', title: t('clients.created'), width: '150px' },
    { field: 'actions', title: t('clients.actions'), sort: false, width: '180px', headerClass: 'justify-center' },
]);

// Methods
const getInitials = (firstName: string, lastName: string): string => {
    return ((firstName?.[0] || '') + (lastName?.[0] || '')).toUpperCase();
};

const getStatusBadgeClass = (status: ClientStatus): string => {
    const classes: Record<ClientStatus, string> = {
        prospect: 'badge-outline-info',
        active: 'badge-outline-success',
        inactive: 'badge-outline-warning',
        archived: 'badge-outline-secondary',
    };
    return classes[status] || 'badge-outline-primary';
};

const getStatusAvatarClass = (status: ClientStatus): string => {
    const classes: Record<ClientStatus, string> = {
        prospect: 'bg-info/10 text-info',
        active: 'bg-success/10 text-success',
        inactive: 'bg-warning/10 text-warning',
        archived: 'bg-secondary/10 text-secondary',
    };
    return classes[status] || 'bg-primary/10 text-primary';
};

const formatCanadaStatus = (status: string): string => {
    return status.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
};

const debouncedSearch = () => {
    debounce(() => {
        currentPage.value = 1;
        clearSelection();
        fetchClients();
    });
};

const clearFilters = () => {
    searchQuery.value = '';
    statusFilter.value = '';
    currentPage.value = 1;
    clearSelection();
    fetchClients();
};

const applyStatusFilter = () => {
    currentPage.value = 1;
    clearSelection();
    fetchClients();
};

const changePerPage = () => {
    currentPage.value = 1;
    clearSelection();
    fetchClients();
};

interface TableChangePayload {
    current_page: number;
    pagesize: number;
    offset: number;
    sort_column: string;
    sort_direction: string;
    search: string;
    column_filters: any[];
    change_type: string;
}

const handleTableChange = (data: TableChangePayload) => {
    sortColumn.value = data.sort_column;
    sortDirection.value = data.sort_direction as 'asc' | 'desc';
    currentPage.value = data.current_page;
    perPage.value = data.pagesize;
    clearSelection();
    fetchClients();
};

const handlePageChange = (page: number) => {
    if (page !== currentPage.value) {
        currentPage.value = page;
        clearSelection();
        fetchClients();
    }
};

const handleRowSelect = (rows: Client[]) => {
    selectedClients.value = rows;
};

const clearSelection = () => {
    selectedClients.value = [];
};

const fetchClients = async () => {
    try {
        await clientStore.fetchClients({
            search: searchQuery.value || undefined,
            status: (statusFilter.value as ClientStatus) || undefined,
            sort_by: sortColumn.value,
            sort_direction: sortDirection.value,
            per_page: perPage.value,
            page: currentPage.value,
        });
    } catch (err) {
        error(t('clients.failed_to_load'));
    }
};

const confirmDelete = async (client: Client) => {
    const confirmed = await confirmDialog({
        title: t('clients.confirm_delete', { name: `${client.first_name} ${client.last_name}` }),
        text: t('clients.delete_warning'),
        icon: 'warning',
        confirmButtonText: t('clients.yes_delete'),
        cancelButtonText: t('clients.cancel'),
    });

    if (confirmed) {
        try {
            await clientStore.deleteClient(client.id);
            success(t('clients.deleted_successfully'));
            selectedClients.value = selectedClients.value.filter(c => c.id !== client.id);
        } catch (err: any) {
            error(err.response?.data?.message || t('clients.delete_failed'));
        }
    }
};

const confirmBulkDelete = async () => {
    if (selectedClients.value.length === 0) return;

    const confirmed = await confirmDialog({
        title: t('clients.confirm_bulk_delete', { count: selectedClients.value.length }),
        text: t('clients.delete_warning'),
        icon: 'warning',
        confirmButtonText: t('clients.yes_delete'),
        cancelButtonText: t('clients.cancel'),
    });

    if (confirmed) {
        try {
            const ids = selectedClients.value.map(c => c.id);
            await clientStore.bulkDeleteClients(ids);
            success(t('clients.bulk_deleted_successfully', { count: ids.length }));
            clearSelection();
        } catch (err: any) {
            error(err.response?.data?.message || t('clients.delete_failed'));
        }
    }
};

const confirmConvert = async (client: Client) => {
    const confirmed = await confirmDialog({
        title: t('clients.confirm_convert', { name: `${client.first_name} ${client.last_name}` }),
        text: t('clients.convert_description'),
        icon: 'info',
        confirmButtonText: t('clients.yes_convert'),
        cancelButtonText: t('clients.cancel'),
    });

    if (confirmed) {
        try {
            await clientStore.convertProspect(client.id);
            success(t('clients.converted_successfully'));
            // Bump key to force full datatable remount (avoids vnode patch
            // errors when conditional slot elements change between renders)
            tableKey.value++;
            await Promise.all([fetchClients(), clientStore.fetchStatistics()]);
        } catch (err: any) {
            error(err.response?.data?.message || t('clients.convert_failed'));
        }
    }
};

// Initialize
onMounted(async () => {
    try {
        await Promise.all([
            fetchClients(),
            clientStore.fetchStatistics(),
        ]);
    } finally {
        initialLoading.value = false;
    }
});
</script>
