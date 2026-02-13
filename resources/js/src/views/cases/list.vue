<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <a href="javascript:;" class="text-primary hover:underline">{{ $t('sidebar.cases') }}</a>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('cases.list') }}</span>
            </li>
        </ul>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
            <div class="panel bg-gradient-to-r from-green-500 to-green-400">
                <div class="flex justify-between">
                    <div class="text-white">
                        <p class="text-lg font-semibold">{{ caseStore.statistics?.by_status.active ?? 0 }}</p>
                        <p class="text-sm opacity-80">{{ $t('cases.active') }}</p>
                    </div>
                    <icon-folder class="w-10 h-10 text-white opacity-50" />
                </div>
            </div>
            <div class="panel bg-gradient-to-r from-red-500 to-red-400">
                <div class="flex justify-between">
                    <div class="text-white">
                        <p class="text-lg font-semibold">{{ caseStore.statistics?.by_priority.urgent ?? 0 }}</p>
                        <p class="text-sm opacity-80">{{ $t('cases.urgent') }}</p>
                    </div>
                    <icon-bell class="w-10 h-10 text-white opacity-50" />
                </div>
            </div>
            <div class="panel bg-gradient-to-r from-blue-500 to-blue-400">
                <div class="flex justify-between">
                    <div class="text-white">
                        <p class="text-lg font-semibold">{{ caseStore.statistics?.upcoming_hearings ?? 0 }}</p>
                        <p class="text-sm opacity-80">{{ $t('cases.upcoming_hearings') }}</p>
                    </div>
                    <icon-calendar class="w-10 h-10 text-white opacity-50" />
                </div>
            </div>
            <div class="panel bg-gradient-to-r from-gray-500 to-gray-400">
                <div class="flex justify-between">
                    <div class="text-white">
                        <p class="text-lg font-semibold">{{ caseStore.statistics?.total ?? 0 }}</p>
                        <p class="text-sm opacity-80">{{ $t('cases.total_cases') }}</p>
                    </div>
                    <icon-archive class="w-10 h-10 text-white opacity-50" />
                </div>
            </div>
        </div>

        <div class="panel">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
                <h5 class="font-semibold text-lg dark:text-white-light">{{ $t('cases.case_management') }}</h5>
                <router-link
                    v-can="'cases.create'"
                    to="/cases/wizard"
                    class="btn btn-primary gap-2"
                >
                    <icon-plus class="w-5 h-5" />
                    {{ $t('cases.add_case') }}
                </router-link>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-4 mb-5" role="search" aria-label="Filter cases">
                <!-- Search -->
                <div class="flex-1 min-w-[200px]">
                    <div class="relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            class="form-input pl-10 pr-4"
                            :placeholder="$t('cases.search_placeholder')"
                            aria-label="Search cases"
                            @input="debouncedSearch"
                        />
                        <div class="absolute left-3 top-1/2 -translate-y-1/2">
                            <span v-if="isDebouncing" class="animate-spin border-2 border-primary border-l-transparent rounded-full w-4 h-4 inline-block"></span>
                            <icon-search v-else class="w-5 h-5 text-gray-500" />
                        </div>
                    </div>
                </div>


                <!-- Case Type Filter -->
                <div class="w-44">
                    <select v-model="caseTypeFilter" class="form-select" aria-label="Filter by case type" @change="applyFilters">
                        <option value="">{{ $t('cases.all_types') }}</option>
                        <option v-for="caseType in caseStore.activeCaseTypes" :key="caseType.id" :value="caseType.id">
                            {{ caseType.name }}
                        </option>
                    </select>
                </div>


                <!-- Status Filter -->
                <div class="w-36">
                    <select v-model="statusFilter" class="form-select" aria-label="Filter by status" @change="applyFilters">
                        <option value="">{{ $t('cases.all_statuses') }}</option>
                        <option value="active">{{ $t('cases.active') }}</option>
                        <option value="inactive">{{ $t('cases.inactive') }}</option>
                        <option value="archived">{{ $t('cases.archived') }}</option>
                        <option value="closed">{{ $t('cases.closed') }}</option>
                    </select>
                </div>

                <!-- Priority Filter -->
                <div class="w-36">
                    <select v-model="priorityFilter" class="form-select" aria-label="Filter by priority" @change="applyFilters">
                        <option value="">{{ $t('cases.all_priorities') }}</option>
                        <option value="urgent">{{ $t('cases.urgent') }}</option>
                        <option value="high">{{ $t('cases.high') }}</option>
                        <option value="medium">{{ $t('cases.medium') }}</option>
                        <option value="low">{{ $t('cases.low') }}</option>
                    </select>
                </div>


                <!-- Per Page -->
                <div class="w-32">
                    <select v-model="perPage" class="form-select" aria-label="Results per page" @change="changePerPage">
                        <option :value="10">10 {{ $t('cases.per_page') }}</option>
                        <option :value="20">20 {{ $t('cases.per_page') }}</option>
                        <option :value="50">50 {{ $t('cases.per_page') }}</option>
                    </select>
                </div>
            </div>

            <!-- Skeleton Loader -->
            <div v-if="showSkeleton" class="animate-pulse">
                <div class="table-responsive">
                    <table class="table-hover">
                        <thead>
                            <tr>
                                <th><div class="h-4 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div></th>
                                <th><div class="h-4 w-32 bg-gray-200 dark:bg-gray-700 rounded"></div></th>
                                <th><div class="h-4 w-24 bg-gray-200 dark:bg-gray-700 rounded"></div></th>
                                <th><div class="h-4 w-16 bg-gray-200 dark:bg-gray-700 rounded"></div></th>
                                <th><div class="h-4 w-16 bg-gray-200 dark:bg-gray-700 rounded"></div></th>
                                <th><div class="h-4 w-24 bg-gray-200 dark:bg-gray-700 rounded"></div></th>
                                <th><div class="h-4 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="i in 5" :key="i">
                                <td><div class="h-4 w-24 bg-gray-200 dark:bg-gray-700 rounded"></div></td>
                                <td><div class="h-4 w-40 bg-gray-200 dark:bg-gray-700 rounded"></div></td>
                                <td><div class="h-4 w-28 bg-gray-200 dark:bg-gray-700 rounded"></div></td>
                                <td><div class="h-5 w-16 bg-gray-200 dark:bg-gray-700 rounded-full"></div></td>
                                <td><div class="h-5 w-16 bg-gray-200 dark:bg-gray-700 rounded-full"></div></td>
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

            <!-- Results -->
            <div v-else-if="!showEmptyState" aria-live="polite">
                <!-- Desktop Table -->
                <div class="datatable hidden md:block">
                    <vue3-datatable
                        :key="`dt-${perPage}`"
                        :rows="caseStore.cases"
                        :columns="columns"
                        :totalRows="caseStore.totalCases"
                        :isServerMode="true"
                        :loading="caseStore.isLoading"
                        :sortable="true"
                        :sortColumn="sortColumn"
                        :sortDirection="sortDirection"
                        :pageSize="perPage"
                        :page="currentPage"
                        @change="handleTableChange"
                        skin="whitespace-nowrap bh-table-hover"
                        firstArrow='<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5 rtl:rotate-180"> <path d="M13 19L7 12L13 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path opacity="0.5" d="M16.9998 19L10.9998 12L16.9998 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> </svg>'
                        lastArrow='<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5 rtl:rotate-180"> <path d="M11 19L17 12L11 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path opacity="0.5" d="M6.99976 19L12.9998 12L6.99976 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> </svg>'
                        previousArrow='<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5 rtl:rotate-180"> <path d="M15 5L9 12L15 19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> </svg>'
                        nextArrow='<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5 rtl:rotate-180"> <path d="M9 5L15 12L9 19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> </svg>'
                    >
                        <!-- Case Number Column -->
                        <template #case_number="data">
                            <router-link :to="`/cases/${data.value.id}`" class="text-primary font-semibold hover:underline">
                                {{ data.value.case_number }}
                            </router-link>
                            <div class="text-xs text-gray-500">{{ $t('cases.created') }}: {{ formatDate(data.value.created_at) }}</div>
                        </template>

                        <!-- Client Column -->
                        <template #client="data">
                            <div v-if="data.value.client" class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center">
                                    <span class="text-xs font-semibold text-primary">
                                        {{ getInitials(data.value.client.first_name, data.value.client.last_name) }}
                                    </span>
                                </div>
                                <div>
                                    <router-link :to="`/clients/${data.value.client.id}`" class="text-primary font-semibold hover:underline">
                                        {{ data.value.client.full_name || `${data.value.client.first_name} ${data.value.client.last_name}` }}
                                    </router-link>
                                    <div class="text-xs text-gray-500">{{ data.value.client.email }}</div>
                                </div>
                            </div>
                            <span v-else class="text-gray-400">-</span>
                        </template>

                        <!-- Case Type Column -->
                        <template #case_type="data">
                            <span v-if="data.value.case_type" class="badge badge-outline-primary">
                                {{ data.value.case_type.name }}
                            </span>
                            <span v-else class="text-gray-400">-</span>
                        </template>

                        <!-- Status Column -->
                        <template #status="data">
                            <span class="badge" :class="getStatusBadgeClass(data.value.status)">
                                {{ $t(`cases.${data.value.status}`) }}
                            </span>
                        </template>

                        <!-- Priority Column -->
                        <template #priority="data">
                            <span class="badge" :class="getPriorityBadgeClass(data.value.priority)">
                                {{ $t(`cases.${data.value.priority}`) }}
                            </span>
                        </template>

                        <!-- Hearing Date Column -->
                        <template #hearing_date="data">
                            <div v-if="data.value.hearing_date">
                                <span>{{ formatDate(data.value.hearing_date) }}</span>
                                <span v-if="data.value.days_until_hearing !== null" class="text-xs ml-1" :class="getDaysUntilClass(data.value.days_until_hearing)">
                                    ({{ formatDaysUntil(data.value.days_until_hearing) }})
                                </span>
                            </div>
                            <span v-else class="text-gray-400">-</span>
                        </template>

                        <!-- Assigned Column -->
                        <template #assigned_to="data">
                            <span v-if="data.value.assigned_user">{{ data.value.assigned_user.name }}</span>
                            <span v-else class="text-gray-400 italic">{{ $t('cases.unassigned') }}</span>
                        </template>

                        <!-- Actions Column -->
                        <template #actions="data">
                            <div class="flex items-center gap-2">
                                <tippy content="View">
                                    <router-link
                                        :to="`/cases/${data.value.id}`"
                                        class="btn btn-sm btn-outline-info p-1.5"
                                    >
                                        <icon-eye class="w-4 h-4" />
                                    </router-link>
                                </tippy>
                                <tippy v-can="'cases.update'" content="Edit">
                                    <router-link
                                        :to="`/cases/${data.value.id}/edit`"
                                        class="btn btn-sm btn-outline-primary p-1.5"
                                    >
                                        <icon-pencil class="w-4 h-4" />
                                    </router-link>
                                </tippy>
                                <tippy v-can="'cases.delete'" content="Delete">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger p-1.5"
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
                    <div v-if="caseStore.isLoading" class="text-center py-4">
                        <span class="animate-spin border-2 border-primary border-l-transparent rounded-full w-6 h-6 inline-block"></span>
                    </div>
                    <div
                        v-for="caseItem in caseStore.cases"
                        :key="caseItem.id"
                        class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-3"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <router-link :to="`/cases/${caseItem.id}`" class="text-primary font-semibold hover:underline">
                                    {{ caseItem.case_number }}
                                </router-link>
                                <p v-if="caseItem.case_type" class="text-sm text-gray-500">{{ caseItem.case_type.name }}</p>
                            </div>
                            <div class="flex gap-1">
                                <span class="badge" :class="getStatusBadgeClass(caseItem.status)">
                                    {{ $t(`cases.${caseItem.status}`) }}
                                </span>
                                <span class="badge" :class="getPriorityBadgeClass(caseItem.priority)">
                                    {{ $t(`cases.${caseItem.priority}`) }}
                                </span>
                            </div>
                        </div>

                        <div v-if="caseItem.client" class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center">
                                <span class="text-xs font-semibold text-primary">
                                    {{ getInitials(caseItem.client.first_name, caseItem.client.last_name) }}
                                </span>
                            </div>
                            <div>
                                <div class="font-medium text-sm">
                                   <router-link :to="`/clients/${caseItem.client.id}`" class="text-primary font-semibold hover:underline"> 
                                        {{ caseItem.client.full_name || `${caseItem.client.first_name} ${caseItem.client.last_name}` }} 
                                   </router-link>
                                </div>
                                <div class="text-xs text-gray-500">{{ caseItem.client.email }}</div>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span>{{ $t('cases.progress') }}</span>
                                <span>{{ caseItem.progress }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="h-2 rounded-full transition-all" :class="getProgressBarClass(caseItem.progress)" :style="{ width: `${caseItem.progress}%` }"></div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-700">
                            <div class="text-xs text-gray-500">
                                <span v-if="caseItem.hearing_date">
                                    <icon-calendar class="w-3 h-3 inline" /> {{ formatDate(caseItem.hearing_date) }}
                                </span>
                                <span v-else>{{ $t('cases.no_hearing') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <router-link :to="`/cases/${caseItem.id}`" class="btn btn-sm btn-outline-info p-1.5">
                                    <icon-eye class="w-4 h-4" />
                                </router-link>
                                <router-link v-can="'cases.update'" :to="`/cases/${caseItem.id}/edit`" class="btn btn-sm btn-outline-primary p-1.5">
                                    <icon-pencil class="w-4 h-4" />
                                </router-link>
                                <button v-can="'cases.delete'" type="button" class="btn btn-sm btn-outline-danger p-1.5" @click="confirmDelete(caseItem)">
                                    <icon-trash-lines class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile Pagination -->
                    <div v-if="caseStore.totalCases > perPage" class="flex items-center justify-between pt-3">
                        <span class="text-sm text-gray-500">
                            {{ $t('cases.page') }} {{ currentPage }} {{ $t('cases.of') }} {{ Math.ceil(caseStore.totalCases / perPage) }}
                        </span>
                        <div class="flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" :disabled="currentPage <= 1" @click="handlePageChange(currentPage - 1)">
                                {{ $t('cases.previous') }}
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" :disabled="currentPage >= Math.ceil(caseStore.totalCases / perPage)" @click="handlePageChange(currentPage + 1)">
                                {{ $t('cases.next') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="showEmptyState" class="text-center py-10" aria-live="polite">
                <template v-if="hasActiveFilters">
                    <icon-search class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
                    <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">{{ $t('cases.no_results_found') }}</h3>
                    <p class="text-gray-500 mb-4">{{ $t('cases.no_cases_match_criteria') }}</p>
                    <button type="button" class="btn btn-outline-primary gap-2" @click="clearFilters">
                        <icon-x class="w-4 h-4" />
                        {{ $t('cases.clear_filters') }}
                    </button>
                </template>
                <template v-else>
                    <icon-folder class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
                    <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">{{ $t('cases.no_cases_yet') }}</h3>
                    <p class="text-gray-500 mb-4">{{ $t('cases.get_started_by_adding') }}</p>
                    <router-link v-can="'cases.create'" to="/cases/create" class="btn btn-primary gap-2">
                        <icon-plus class="w-5 h-5" />
                        {{ $t('cases.add_first_case') }}
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
import { useCaseStore } from '@/stores/case';
import { useNotification } from '@/composables/useNotification';
import { useDebounce } from '@/composables/useDebounce';
import { formatDate } from '@/utils/formatters';
import type { ImmigrationCase, CaseStatus, CasePriority } from '@/types/case';

// Icons
import IconFolder from '@/components/icon/icon-folder.vue';
import IconBell from '@/components/icon/icon-bell.vue';
import IconCalendar from '@/components/icon/icon-calendar.vue';
import IconArchive from '@/components/icon/icon-archive.vue';
import IconPlus from '@/components/icon/icon-plus.vue';
import IconSearch from '@/components/icon/icon-search.vue';
import IconEye from '@/components/icon/icon-eye.vue';
import IconPencil from '@/components/icon/icon-pencil.vue';
import IconTrashLines from '@/components/icon/icon-trash-lines.vue';
import IconX from '@/components/icon/icon-x.vue';

useMeta({ title: 'Case Management' });

const { t } = useI18n();
const caseStore = useCaseStore();
const { confirm: confirmDialog, success, error } = useNotification();
const { debounce, isDebouncing } = useDebounce(300);

// Local state
const searchQuery = ref('');
const statusFilter = ref('active');
const priorityFilter = ref('');
const caseTypeFilter = ref<number | ''>('');
const perPage = ref(10);
const currentPage = ref(1);
const sortColumn = ref('priority');
const sortDirection = ref<'asc' | 'desc'>('asc');
const initialLoading = ref(true);

// Computed
const hasActiveFilters = computed(() => !!searchQuery.value || !!statusFilter.value || !!priorityFilter.value || !!caseTypeFilter.value);
const showSkeleton = computed(() => initialLoading.value && caseStore.cases.length === 0);
const showEmptyState = computed(() => !caseStore.isLoading && !initialLoading.value && caseStore.cases.length === 0);

// Table columns
const columns = computed(() => [
    { field: 'case_number', title: t('cases.case_number'), width: '140px', isUnique: true },
    { field: 'client', title: t('cases.client'), minWidth: '200px', sort: false },
    { field: 'case_type', title: t('cases.case_type'), width: '150px', sort: false },
    { field: 'status', title: t('cases.status'), width: '100px' },
    { field: 'priority', title: t('cases.priority'), width: '100px' },
    { field: 'hearing_date', title: t('cases.hearing_date'), width: '150px' },
    { field: 'assigned_to', title: t('cases.assigned_to'), width: '140px', sort: false },
    { field: 'actions', title: t('cases.actions'), sort: false, width: '130px', headerClass: 'justify-center' },
]);

// Helper methods
const getInitials = (firstName: string, lastName: string): string => {
    return ((firstName?.[0] || '') + (lastName?.[0] || '')).toUpperCase();
};

const getStatusBadgeClass = (status: CaseStatus): string => {
    const classes: Record<CaseStatus, string> = {
        active: 'badge-outline-success',
        inactive: 'badge-outline-warning',
        archived: 'badge-outline-secondary',
        closed: 'badge-outline-dark',
    };
    return classes[status] || 'badge-outline-primary';
};

const getPriorityBadgeClass = (priority: CasePriority): string => {
    const classes: Record<CasePriority, string> = {
        urgent: 'badge-outline-danger',
        high: 'badge-outline-warning',
        medium: 'badge-outline-info',
        low: 'badge-outline-secondary',
    };
    return classes[priority] || 'badge-outline-primary';
};

const getProgressBarClass = (progress: number): string => {
    if (progress >= 75) return 'bg-success';
    if (progress >= 50) return 'bg-info';
    if (progress >= 25) return 'bg-warning';
    return 'bg-danger';
};

const getDaysUntilClass = (days: number): string => {
    if (days < 0) return 'text-danger';
    if (days <= 7) return 'text-warning';
    return 'text-gray-500';
};

const formatDaysUntil = (days: number): string => {
    if (days < 0) return `${Math.abs(days)}d ago`;
    if (days === 0) return 'Today';
    return `${days}d`;
};

// Actions
const debouncedSearch = () => {
    debounce(() => {
        currentPage.value = 1;
        fetchCases();
    });
};

const applyFilters = () => {
    currentPage.value = 1;
    fetchCases();
};

const clearFilters = () => {
    searchQuery.value = '';
    statusFilter.value = '';
    priorityFilter.value = '';
    caseTypeFilter.value = '';
    currentPage.value = 1;
    fetchCases();
};

const changePerPage = () => {
    currentPage.value = 1;
    fetchCases();
};

interface TableChangePayload {
    current_page: number;
    pagesize: number;
    sort_column: string;
    sort_direction: string;
}

const handleTableChange = (data: TableChangePayload) => {
    sortColumn.value = data.sort_column;
    sortDirection.value = data.sort_direction as 'asc' | 'desc';
    currentPage.value = data.current_page;
    perPage.value = data.pagesize;
    fetchCases();
};

const handlePageChange = (page: number) => {
    if (page !== currentPage.value) {
        currentPage.value = page;
        fetchCases();
    }
};

const fetchCases = async () => {
    try {
        await caseStore.fetchCases({
            search: searchQuery.value || undefined,
            status: (statusFilter.value as CaseStatus) || undefined,
            priority: (priorityFilter.value as CasePriority) || undefined,
            case_type_id: caseTypeFilter.value || undefined,
            sort_by: sortColumn.value,
            sort_direction: sortDirection.value,
            per_page: perPage.value,
            page: currentPage.value,
        });
    } catch (err) {
        error(t('cases.failed_to_load'));
    }
};

const confirmDelete = async (caseItem: ImmigrationCase) => {
    const confirmed = await confirmDialog({
        title: t('cases.confirm_delete', { number: caseItem.case_number }),
        text: t('cases.delete_warning'),
        icon: 'warning',
        confirmButtonText: t('cases.yes_delete'),
        cancelButtonText: t('cases.cancel'),
    });

    if (confirmed) {
        try {
            await caseStore.deleteCase(caseItem.id);
            success(t('cases.deleted_successfully'));
        } catch (err: any) {
            error(err.response?.data?.message || t('cases.delete_failed'));
        }
    }
};

// Initialize
onMounted(async () => {
    try {
        await Promise.all([
            caseStore.fetchCaseTypes(),
            fetchCases(),
            caseStore.fetchStatistics(),
        ]);
    } finally {
        initialLoading.value = false;
    }
});
</script>
