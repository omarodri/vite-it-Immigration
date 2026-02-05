<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <a href="javascript:;" class="text-primary hover:underline">{{ $t('sidebar.admin') }}</a>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('sidebar.users') }}</span>
            </li>
        </ul>

        <div class="panel">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
                <h5 class="font-semibold text-lg dark:text-white-light">{{ $t('users.user_management') }}</h5>
                <div class="flex items-center gap-2">
                    <!-- Bulk Actions Dropdown -->
                    <div v-if="selectedUsers?.length > 0" class="dropdown">
                        <Popper :placement="'bottom-end'" :offsetDistance="0" :arrow="true">
                            <button type="button" class="btn btn-outline-danger gap-2">
                                <icon-trash-lines class="w-5 h-5" />
                                {{ $t('users.actions') }} ({{ selectedUsers.length }})
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
                                            {{ $t('users.delete_selected') }}
                                        </button>
                                    </li>
                                </ul>
                            </template>
                        </Popper>
                    </div>

                    <!-- Selected Counter -->
                    <span v-if="selectedUsers?.length > 0" class="text-sm text-gray-500">
                        {{ selectedUsers.length }} {{ $t('users.selected') }}
                    </span>

                    <router-link
                        v-can="'users.create'"
                        to="/admin/users/create"
                        class="btn btn-primary gap-2"
                    >
                        <icon-user-plus class="w-5 h-5" />
                        {{ $t('users.add_user') }}
                    </router-link>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-4 mb-5" role="search" aria-label="Filter users">
                <!-- Search -->
                <div class="flex-1 min-w-[200px]">
                    <div class="relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            class="form-input pl-10 pr-4"
                            :placeholder="$t('users.search_by_name_or_email')"
                            aria-label="$t('users.search_by_name_or_email')"
                            @input="debouncedSearch"
                        />
                        <div class="absolute left-3 top-1/2 -translate-y-1/2">
                            <span v-if="isDebouncing" class="animate-spin border-2 border-primary border-l-transparent rounded-full w-4 h-4 inline-block"></span>
                            <icon-search v-else class="w-5 h-5 text-gray-500" />
                        </div>
                    </div>
                </div>

                <!-- Role Filter -->
                <div class="w-48">
                    <select v-model="roleFilter" class="form-select" aria-label="Filter by role" @change="applyRoleFilter">
                        <option value="">{{ $t('users.all_roles') }}</option>
                        <option v-for="role in userStore.roleOptions" :key="role.value" :value="role.value">
                            {{ role.label }}
                        </option>
                    </select>
                </div>

                <!-- Per Page -->
                <div class="w-32">
                    <select v-model="perPage" class="form-select" aria-label="Results per page" @change="changePerPage">
                        <option :value="10">{{ $t('users.10_per_page') }}</option>
                        <option :value="15">{{ $t('users.15_per_page') }}</option>
                        <option :value="25">{{ $t('users.25_per_page') }}</option>
                        <option :value="50">{{ $t('users.50_per_page') }}</option>
                    </select>
                </div>

                <!-- Clear Selection Button -->
                <button
                    v-if="selectedUsers?.length > 0"
                    type="button"
                    class="btn btn-outline-secondary btn-sm"
                    @click="clearSelection"
                >
                    {{ $t('users.clear_selection') }}
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
                        :key="`dt-${perPage}`"
                        :rows="userStore.users"
                        :columns="columns"
                        :totalRows="userStore.totalUsers"
                        :isServerMode="true"
                        :loading="userStore.isLoading"
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
                        <template #name="data">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center">
                                    <span class="text-primary font-semibold text-sm">
                                        {{ getInitials(data.value.name || '') }}
                                    </span>
                                </div>
                                <div>
                                    <div class="font-semibold">{{ data.value.name }}</div>
                                    <div class="text-xs text-gray-500">{{ data.value.email }}</div>
                                </div>
                            </div>
                        </template>

                        <!-- Roles Column -->
                        <template #roles="data">
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="role in data.value.roles"
                                    :key="role.id"
                                    class="badge"
                                    :class="getRoleBadgeClass(role.name)"
                                >
                                    {{ role.name }}
                                </span>
                                <span v-if="!data.value.roles?.length" class="text-gray-400 text-sm">No roles</span>
                            </div>
                        </template>

                        <!-- Email Verified Column -->
                        <template #email_verified_at="data">
                            <span v-if="data.value.email_verified_at" class="badge badge-outline-success gap-1">
                                <icon-circle-check class="w-3.5 h-3.5" />
                                {{ $t('users.verified') }}
                            </span>
                            <span v-else class="badge badge-outline-warning gap-1">
                                <icon-info-triangle class="w-3.5 h-3.5" />
                                {{ $t('users.pending') }}
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
                                        :to="`/admin/users/${data.value.id}`"
                                        class="btn btn-sm btn-outline-info p-1.5"
                                        :aria-label="`View ${data.value.name}`"
                                    >
                                        <icon-eye class="w-4 h-4" />
                                    </router-link>
                                </tippy>
                                <tippy v-can="'users.update'" content="Edit">
                                    <router-link
                                        :to="`/admin/users/${data.value.id}/edit`"
                                        class="btn btn-sm btn-outline-primary p-1.5"
                                        :aria-label="`Edit ${data.value.name}`"
                                    >
                                        <icon-pencil class="w-4 h-4" />
                                    </router-link>
                                </tippy>
                                <tippy v-can="'users.delete'" content="Delete">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger p-1.5"
                                        :aria-label="`Delete ${data.value.name}`"
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
                    <div v-if="userStore.isLoading" class="text-center py-4">
                        <span class="animate-spin border-2 border-primary border-l-transparent rounded-full w-6 h-6 inline-block"></span>
                    </div>
                    <div
                        v-for="user in userStore.users"
                        :key="user.id"
                        class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-3"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center">
                                    <span class="text-primary font-semibold text-sm" aria-hidden="true">
                                        {{ getInitials(user.name || '') }}
                                    </span>
                                </div>
                                <div>
                                    <div class="font-semibold dark:text-white-light">{{ user.name }}</div>
                                    <div class="text-xs text-gray-500">{{ user.email }}</div>
                                </div>
                            </div>
                            <span class="text-primary font-semibold text-sm">#{{ user.id }}</span>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                v-for="role in user.roles"
                                :key="role.id"
                                class="badge"
                                :class="getRoleBadgeClass(role.name)"
                            >
                                {{ role.name }}
                            </span>
                            <span v-if="user.email_verified_at" class="badge badge-outline-success gap-1">
                                <icon-circle-check class="w-3 h-3" />
                                {{ $t('users.verified') }}
                            </span>
                            <span v-else class="badge badge-outline-warning gap-1">
                                <icon-info-triangle class="w-3 h-3" />
                                {{ $t('users.pending') }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-700">
                            <span class="text-xs text-gray-500">{{ formatDate(user.created_at) }}</span>
                            <div class="flex items-center gap-2">
                                <router-link
                                    :to="`/admin/users/${user.id}`"
                                    class="btn btn-sm btn-outline-info p-1.5"
                                    :aria-label="`View ${user.name}`"
                                >
                                    <icon-eye class="w-4 h-4" />
                                </router-link>
                                <router-link
                                    v-can="'users.update'"
                                    :to="`/admin/users/${user.id}/edit`"
                                    class="btn btn-sm btn-outline-primary p-1.5"
                                    :aria-label="`Edit ${user.name}`"
                                >
                                    <icon-pencil class="w-4 h-4" />
                                </router-link>
                                <button
                                    v-can="'users.delete'"
                                    type="button"
                                    class="btn btn-sm btn-outline-danger p-1.5"
                                    :aria-label="`Delete ${user.name}`"
                                    @click="confirmDelete(user)"
                                >
                                    <icon-trash-lines class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile Pagination -->
                    <div v-if="userStore.totalUsers > perPage" class="flex items-center justify-between pt-3">
                        <span class="text-sm text-gray-500">
                            {{ $t('users.page') }} {{ currentPage }} {{ $t('users.of') }} {{ Math.ceil(userStore.totalUsers / perPage) }}
                        </span>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary"
                                :disabled="currentPage <= 1"
                                @click="handlePageChange(currentPage - 1)"
                            >
                                {{ $t('users.previous') }}
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary"
                                :disabled="currentPage >= Math.ceil(userStore.totalUsers / perPage)"
                                @click="handlePageChange(currentPage + 1)"
                            >
                                {{ $t('users.next') }}
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
                    <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">{{ $t('users.no_results_found') }}</h3>
                    <p class="text-gray-500 dark:text-gray-500 mb-4">
                        {{ $t('users.no_users_match_your_current_search_or_filter_criteria') }}
                    </p>
                    <button type="button" class="btn btn-outline-primary gap-2" @click="clearFilters">
                        <icon-x class="w-4 h-4" />
                        {{ $t('users.clear_filters') }}
                    </button>
                </template>
                <!-- No data at all -->
                <template v-else>
                    <icon-users class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
                    <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">{{ $t('users.no_users_yet') }}</h3>
                    <p class="text-gray-500 dark:text-gray-500 mb-4">
                        {{ $t('users.get_started_by_adding_your_first_user') }}
                    </p>
                    <router-link v-can="'users.create'" to="/admin/users/create" class="btn btn-primary gap-2">
                        <icon-user-plus class="w-5 h-5" />
                        {{ $t('users.add_first_user') }}
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
import { useUserStore } from '@/stores/user';
import { useAuthStore } from '@/stores/auth';
import { useNotification } from '@/composables/useNotification';
import { useDebounce } from '@/composables/useDebounce';
import { formatDate } from '@/utils/formatters';
import type { User } from '@/types/user';

// Icons
import IconUserPlus from '@/components/icon/icon-user-plus.vue';
import IconSearch from '@/components/icon/icon-search.vue';
import IconEye from '@/components/icon/icon-eye.vue';
import IconPencil from '@/components/icon/icon-pencil.vue';
import IconTrashLines from '@/components/icon/icon-trash-lines.vue';
import IconUsers from '@/components/icon/icon-users.vue';
import IconCircleCheck from '@/components/icon/icon-circle-check.vue';
import IconInfoTriangle from '@/components/icon/icon-info-triangle.vue';
import IconCaretDown from '@/components/icon/icon-caret-down.vue';
import IconX from '@/components/icon/icon-x.vue';

useMeta({ title: 'User Management' });

const { t } = useI18n();
const userStore = useUserStore();
const authStore = useAuthStore();
const { confirm: confirmDialog, success, error, warning } = useNotification();
const { debounce, isDebouncing } = useDebounce(300);

// Local state
const searchQuery = ref('');
const roleFilter = ref('');
const perPage = ref(15);
const currentPage = ref(1);
const sortColumn = ref('created_at');
const sortDirection = ref<'asc' | 'desc'>('desc');
const selectedUsers = ref<User[]>([]);
const initialLoading = ref(true);

// Computed
const hasActiveFilters = computed(() => !!searchQuery.value || !!roleFilter.value);
const showSkeleton = computed(() => initialLoading.value && userStore.users.length === 0);
const showEmptyState = computed(() => !userStore.isLoading && !initialLoading.value && userStore.users.length === 0);

// Table columns
const columns = computed(() => [
    { field: 'id', title: 'ID', width: '80px', isUnique: true },
    { field: 'name', title: 'User', minWidth: '250px' },
    { field: 'roles', title: 'Roles', sort: false, minWidth: '150px' },
    { field: 'email_verified_at', title: 'Status', sort: false, width: '120px' },
    { field: 'created_at', title: 'Created', width: '150px' },
    { field: 'actions', title: 'Actions', sort: false, width: '150px', headerClass: 'justify-center' },
]);

// Methods
const getInitials = (name: string): string => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

const getRoleBadgeClass = (roleName: string): string => {
    const classes: Record<string, string> = {
        admin: 'badge-outline-danger',
        editor: 'badge-outline-warning',
        user: 'badge-outline-info',
    };
    return classes[roleName] || 'badge-outline-primary';
};

const debouncedSearch = () => {
    debounce(() => {
        currentPage.value = 1;
        clearSelection();
        fetchUsers();
    });
};

const clearFilters = () => {
    searchQuery.value = '';
    roleFilter.value = '';
    currentPage.value = 1;
    clearSelection();
    fetchUsers();
};

const applyRoleFilter = () => {
    currentPage.value = 1;
    clearSelection();
    fetchUsers();
};

const changePerPage = () => {
    currentPage.value = 1;
    clearSelection();
    fetchUsers();
};

// Server mode unified change handler
// vue3-datatable in isServerMode emits @change instead of individual events
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
    fetchUsers();
};

// Mobile pagination handler (datatable not used on mobile)
const handlePageChange = (page: number) => {
    if (page !== currentPage.value) {
        currentPage.value = page;
        clearSelection();
        fetchUsers();
    }
};

// rowSelect emits User[] directly in vue3-datatable (not wrapped in object)
const handleRowSelect = (rows: User[]) => {
    selectedUsers.value = rows;
};

const clearSelection = () => {
    selectedUsers.value = [];
};

const fetchUsers = async () => {
    try {
        await userStore.fetchUsers({
            search: searchQuery.value || undefined,
            role: roleFilter.value || undefined,
            sort_by: sortColumn.value,
            sort_direction: sortDirection.value,
            per_page: perPage.value,
            page: currentPage.value,
        });
    } catch (err) {
        error('Failed to load users');
    }
};

const confirmDelete = async (user: User) => {
    // Prevent self-deletion
    if (authStore.user?.id === user.id) {
        warning(t('users.cannot_delete_own_account'));
        return;
    }

    const confirmed = await confirmDialog({
        title: t('users.are_you_sure_you_want_to_delete', { name: user.name }),
        text: t('users.this_action_cannot_be_undone'),
        icon: 'warning',
        confirmButtonText: t('users.yes_delete'),
        cancelButtonText: t('users.cancel'),
    });

    if (confirmed) {
        try {
            await userStore.deleteUser(user.id);
            success(t('users.user_deleted_successfully'));
            // Remove from selection if was selected
            selectedUsers.value = selectedUsers.value.filter(u => u.id !== user.id);
        } catch (err: any) {
            error(err.response?.data?.message || t('users.failed_to_delete_user'));
        }
    }
};

const confirmBulkDelete = async () => {
    if (selectedUsers.value.length === 0) {
        warning(t('users.no_users_selected'));
        return;
    }

    // Filter out current user from selection
    const currentUserId = authStore.user?.id;
    const usersToDelete = selectedUsers.value.filter(u => u.id !== currentUserId);

    if (usersToDelete.length === 0) {
        warning(t('users.cannot_delete_own_account'));
        return;
    }

    // Check if trying to delete self
    if (usersToDelete.length < selectedUsers.value.length) {
        warning(t('users.cannot_delete_own_account_excluded'));
    }

    // Check for admin users being deleted
    const adminUsers = usersToDelete.filter(u => u.roles?.some(r => r.name === 'admin'));
    let titleText = t('users.are_you_sure_you_want_to_delete_users', { count: usersToDelete.length });
    if (adminUsers.length > 0) {
        titleText += '\n\n' + t('users.warning_administrator_will_be_deleted', { count: adminUsers.length });
    }

    const confirmed = await confirmDialog({
        title: titleText,
        text: t('users.this_action_cannot_be_undone'),
        icon: 'warning',
        confirmButtonText: t('users.yes_delete'),
        cancelButtonText: t('users.cancel'),
    });

    if (confirmed) {
        try {
            const ids = usersToDelete.map(u => u.id);
            await userStore.bulkDeleteUsers(ids);
            success(t('users.users_deleted_successfully', { count: usersToDelete.length }));
            clearSelection();
        } catch (err: any) {
            error(err.response?.data?.message || t('users.failed_to_delete_users'));
        }
    }
};

// Initialize
onMounted(async () => {
    try {
        await Promise.all([
            fetchUsers(),
            userStore.fetchRoles(),
        ]);
    } finally {
        initialLoading.value = false;
    }
});
</script>
