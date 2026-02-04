<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <a href="javascript:;" class="text-primary hover:underline">{{ $t('sidebar.admin') }}</a>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('sidebar.roles') }}</span>
            </li>
        </ul>

        <div class="panel">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
                <h5 class="font-semibold text-lg dark:text-white-light">{{ $t('roles.role_management') }}</h5>
                <router-link
                    v-can="'roles.create'"
                    to="/admin/roles/create"
                    class="btn btn-primary gap-2"
                >
                    <icon-plus class="w-5 h-5" />
                    {{ $t('roles.add_role') }}
                </router-link>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-4 mb-5" role="search" aria-label="Filter roles">
                <!-- Search -->
                <div class="flex-1 min-w-[200px]">
                    <div class="relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            class="form-input pl-10 pr-4"
                            :placeholder="$t('roles.search_by_name')"
                            :aria-label="$t('roles.search_by_name')"
                            @input="debouncedSearch"
                        />
                        <div class="absolute left-3 top-1/2 -translate-y-1/2">
                            <span v-if="isDebouncing" class="animate-spin border-2 border-primary border-l-transparent rounded-full w-4 h-4 inline-block"></span>
                            <icon-search v-else class="w-5 h-5 text-gray-500" />
                        </div>
                    </div>
                </div>

                <!-- Per Page -->
                <div class="w-32">
                    <select v-model="perPage" class="form-select" aria-label="Results per page" @change="changePerPage">
                        <option :value="10">{{ $t('roles.10_per_page') }}</option>
                        <option :value="15">{{ $t('roles.15_per_page') }}</option>
                        <option :value="25">{{ $t('roles.25_per_page') }}</option>
                        <option :value="50">{{ $t('roles.50_per_page') }}</option>
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
                                <th><div class="h-4 w-24 bg-gray-200 dark:bg-gray-700 rounded"></div></th>
                                <th><div class="h-4 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div></th>
                                <th><div class="h-4 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="i in 5" :key="i">
                                <td><div class="h-4 w-32 bg-gray-200 dark:bg-gray-700 rounded"></div></td>
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

            <!-- Results area -->
            <div v-else-if="!showEmptyState" aria-live="polite">
                <!-- Desktop Table -->
                <div class="datatable hidden md:block">
                    <vue3-datatable
                        :key="`dt-${perPage}`"
                        :rows="roleStore.roles"
                        :columns="columns"
                        :totalRows="roleStore.totalRoles"
                        :isServerMode="true"
                        :loading="roleStore.isLoading"
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
                        <!-- Name Column -->
                        <template #name="data">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold">{{ data.value.name }}</span>
                                <span
                                    v-if="roleStore.isProtectedRole(data.value.name)"
                                    class="badge badge-outline-warning text-xs"
                                >
                                    {{ $t('roles.protected') }}
                                </span>
                            </div>
                        </template>

                        <!-- Permissions Column -->
                        <template #permissions="data">
                            <span class="badge badge-outline-primary">
                                {{ data.value.permissions?.length || 0 }} {{ $t('roles.permissions') }}
                            </span>
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
                                        :to="`/admin/roles/${data.value.id}`"
                                        class="btn btn-sm btn-outline-info p-1.5"
                                        :aria-label="`View ${data.value.name}`"
                                    >
                                        <icon-eye class="w-4 h-4" />
                                    </router-link>
                                </tippy>
                                <template v-if="!roleStore.isProtectedRole(data.value.name)">
                                    <tippy v-can="'roles.update'" content="Edit">
                                        <router-link
                                            :to="`/admin/roles/${data.value.id}/edit`"
                                            class="btn btn-sm btn-outline-primary p-1.5"
                                            :aria-label="`Edit ${data.value.name}`"
                                        >
                                            <icon-pencil class="w-4 h-4" />
                                        </router-link>
                                    </tippy>
                                    <tippy v-can="'roles.delete'" content="Delete">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger p-1.5"
                                            :aria-label="`Delete ${data.value.name}`"
                                            @click="handleDelete(data.value)"
                                        >
                                            <icon-trash-lines class="w-4 h-4" />
                                        </button>
                                    </tippy>
                                </template>
                                <template v-else>
                                    <tippy v-can="'roles.update'" content="Cannot edit protected role">
                                        <button type="button" class="btn btn-sm btn-outline-primary p-1.5 opacity-50 cursor-not-allowed" disabled>
                                            <icon-pencil class="w-4 h-4" />
                                        </button>
                                    </tippy>
                                    <tippy v-can="'roles.delete'" content="Cannot delete protected role">
                                        <button type="button" class="btn btn-sm btn-outline-danger p-1.5 opacity-50 cursor-not-allowed" disabled>
                                            <icon-trash-lines class="w-4 h-4" />
                                        </button>
                                    </tippy>
                                </template>
                            </div>
                        </template>
                    </vue3-datatable>
                </div>

                <!-- Mobile Cards -->
                <div class="md:hidden space-y-3">
                    <div v-if="roleStore.isLoading" class="text-center py-4">
                        <span class="animate-spin border-2 border-primary border-l-transparent rounded-full w-6 h-6 inline-block"></span>
                    </div>
                    <div
                        v-for="role in roleStore.roles"
                        :key="role.id"
                        class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-3"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold dark:text-white-light">{{ role.name }}</span>
                                <span
                                    v-if="roleStore.isProtectedRole(role.name)"
                                    class="badge badge-outline-warning text-xs"
                                >
                                    {{ $t('roles.protected') }}
                                </span>
                            </div>
                            <span class="badge badge-outline-primary">
                                {{ role.permissions?.length || 0 }} {{ $t('roles.permissions') }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-700">
                            <span class="text-xs text-gray-500">{{ formatDate(role.created_at) }}</span>
                            <div class="flex items-center gap-2">
                                <router-link
                                    :to="`/admin/roles/${role.id}`"
                                    class="btn btn-sm btn-outline-info p-1.5"
                                    :aria-label="`View ${role.name}`"
                                >
                                    <icon-eye class="w-4 h-4" />
                                </router-link>
                                <template v-if="!roleStore.isProtectedRole(role.name)">
                                    <router-link
                                        v-can="'roles.update'"
                                        :to="`/admin/roles/${role.id}/edit`"
                                        class="btn btn-sm btn-outline-primary p-1.5"
                                        :aria-label="`Edit ${role.name}`"
                                    >
                                        <icon-pencil class="w-4 h-4" />
                                    </router-link>
                                    <button
                                        v-can="'roles.delete'"
                                        type="button"
                                        class="btn btn-sm btn-outline-danger p-1.5"
                                        :aria-label="`Delete ${role.name}`"
                                        @click="handleDelete(role)"
                                    >
                                        <icon-trash-lines class="w-4 h-4" />
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile Pagination -->
                    <div v-if="roleStore.totalRoles > perPage" class="flex items-center justify-between pt-3">
                        <span class="text-sm text-gray-500">
                            {{ $t('roles.page') }} {{ currentPage }} {{ $t('roles.of') }} {{ Math.ceil(roleStore.totalRoles / perPage) }}
                        </span>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary"
                                :disabled="currentPage <= 1"
                                @click="handlePageChange(currentPage - 1)"
                            >
                                {{ $t('roles.previous') }}
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary"
                                :disabled="currentPage >= Math.ceil(roleStore.totalRoles / perPage)"
                                @click="handlePageChange(currentPage + 1)"
                            >
                                {{ $t('roles.next') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="showEmptyState" class="text-center py-10" aria-live="polite">
                <template v-if="hasActiveFilters">
                    <icon-search class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
                    <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">{{ $t('roles.no_results_found') }}</h3>
                    <p class="text-gray-500 dark:text-gray-500 mb-4">{{ $t('roles.no_roles_match_search') }}</p>
                    <button type="button" class="btn btn-outline-primary gap-2" @click="clearFilters">
                        <icon-x class="w-4 h-4" />
                        {{ $t('roles.clear_filters') }}
                    </button>
                </template>
                <template v-else>
                    <icon-lock-dots class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
                    <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">{{ $t('roles.no_roles_yet') }}</h3>
                    <p class="text-gray-500 dark:text-gray-500 mb-4">{{ $t('roles.get_started_by_adding_role') }}</p>
                    <router-link v-can="'roles.create'" to="/admin/roles/create" class="btn btn-primary gap-2">
                        <icon-plus class="w-5 h-5" />
                        {{ $t('roles.add_first_role') }}
                    </router-link>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import Vue3Datatable from '@bhplugin/vue3-datatable';
import { useMeta } from '@/composables/use-meta';
import { useRoleStore } from '@/stores/role';
import { useNotification } from '@/composables/useNotification';
import { useDebounce } from '@/composables/useDebounce';
import { formatDate } from '@/utils/formatters';
import type { RoleWithPermissions } from '@/services/roleService';

// Icons
import IconPlus from '@/components/icon/icon-plus.vue';
import IconSearch from '@/components/icon/icon-search.vue';
import IconEye from '@/components/icon/icon-eye.vue';
import IconPencil from '@/components/icon/icon-pencil.vue';
import IconTrashLines from '@/components/icon/icon-trash-lines.vue';
import IconLockDots from '@/components/icon/icon-lock-dots.vue';
import IconX from '@/components/icon/icon-x.vue';

useMeta({ title: 'Role Management' });

const roleStore = useRoleStore();
const { confirmDelete, success, error } = useNotification();
const { debounce, isDebouncing } = useDebounce(300);

// Local state
const searchQuery = ref('');
const perPage = ref(15);
const currentPage = ref(1);
const sortColumn = ref('name');
const sortDirection = ref<'asc' | 'desc'>('asc');
const initialLoading = ref(true);

// Computed
const hasActiveFilters = computed(() => !!searchQuery.value);
const showSkeleton = computed(() => initialLoading.value && roleStore.roles.length === 0);
const showEmptyState = computed(() => !roleStore.isLoading && !initialLoading.value && roleStore.roles.length === 0);

// Table columns
const columns = computed(() => [
    { field: 'name', title: 'Name', minWidth: '200px' },
    { field: 'permissions', title: 'Permissions', sort: false, width: '150px' },
    { field: 'created_at', title: 'Created', width: '150px' },
    { field: 'actions', title: 'Actions', sort: false, width: '150px', headerClass: 'justify-center' },
]);

// Methods
const debouncedSearch = () => {
    debounce(() => {
        currentPage.value = 1;
        fetchRoles();
    });
};

const clearFilters = () => {
    searchQuery.value = '';
    currentPage.value = 1;
    fetchRoles();
};

const changePerPage = () => {
    currentPage.value = 1;
    fetchRoles();
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
    fetchRoles();
};

const handlePageChange = (page: number) => {
    if (page !== currentPage.value) {
        currentPage.value = page;
        fetchRoles();
    }
};

const fetchRoles = async () => {
    try {
        await roleStore.fetchRoles({
            search: searchQuery.value || undefined,
            sort_by: sortColumn.value,
            sort_direction: sortDirection.value,
            per_page: perPage.value,
            page: currentPage.value,
        });
    } catch (err) {
        error('Failed to load roles');
    }
};

const handleDelete = async (role: RoleWithPermissions) => {
    const confirmed = await confirmDelete(role.name);

    if (confirmed) {
        try {
            await roleStore.deleteRole(role.id);
            success('Role deleted successfully');
        } catch (err: any) {
            error(err.response?.data?.message || 'Failed to delete role');
        }
    }
};

// Initialize
onMounted(async () => {
    try {
        await fetchRoles();
    } finally {
        initialLoading.value = false;
    }
});
</script>
