<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <a href="javascript:;" class="text-primary hover:underline">Admin</a>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>Users</span>
            </li>
        </ul>

        <div class="panel">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
                <h5 class="font-semibold text-lg dark:text-white-light">User Management</h5>
                <div class="flex items-center gap-2">
                    <!-- Bulk Actions Dropdown -->
                    <div v-if="selectedUsers?.length > 0" class="dropdown">
                        <Popper :placement="'bottom-end'" :offsetDistance="0" :arrow="true">
                            <button type="button" class="btn btn-outline-danger gap-2">
                                <icon-trash-lines class="w-5 h-5" />
                                Actions ({{ selectedUsers.length }})
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
                                            Delete Selected
                                        </button>
                                    </li>
                                </ul>
                            </template>
                        </Popper>
                    </div>

                    <!-- Selected Counter -->
                    <span v-if="selectedUsers?.length > 0" class="text-sm text-gray-500">
                        {{ selectedUsers.length }} selected
                    </span>

                    <router-link
                        v-can="'users.create'"
                        to="/admin/users/create"
                        class="btn btn-primary gap-2"
                    >
                        <icon-user-plus class="w-5 h-5" />
                        Add User
                    </router-link>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-4 mb-5">
                <!-- Search -->
                <div class="flex-1 min-w-[200px]">
                    <div class="relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            class="form-input pl-10 pr-4"
                            placeholder="Search by name or email..."
                            @input="debouncedSearch"
                        />
                        <div class="absolute left-3 top-1/2 -translate-y-1/2">
                            <icon-search class="w-5 h-5 text-gray-500" />
                        </div>
                    </div>
                </div>

                <!-- Role Filter -->
                <div class="w-48">
                    <select v-model="roleFilter" class="form-select" @change="applyRoleFilter">
                        <option value="">All Roles</option>
                        <option v-for="role in userStore.roleOptions" :key="role.value" :value="role.value">
                            {{ role.label }}
                        </option>
                    </select>
                </div>

                <!-- Per Page -->
                <div class="w-32">
                    <select v-model="perPage" class="form-select" @change="changePerPage">
                        <option :value="10">10 / page</option>
                        <option :value="15">15 / page</option>
                        <option :value="25">25 / page</option>
                        <option :value="50">50 / page</option>
                    </select>
                </div>

                <!-- Clear Selection Button -->
                <button
                    v-if="selectedUsers?.length > 0"
                    type="button"
                    class="btn btn-outline-secondary btn-sm"
                    @click="clearSelection"
                >
                    Clear Selection
                </button>
            </div>

            <!-- Table -->
            <div class="datatable">
                <vue3-datatable
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
                    :checkedRows="selectedUsers"
                    @pageChange="handlePageChange"
                    @pageSizeChange="handlePageSizeChange"
                    @sortChange="handleSortChange"
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
                            Verified
                        </span>
                        <span v-else class="badge badge-outline-warning gap-1">
                            <icon-info-triangle class="w-3.5 h-3.5" />
                            Pending
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
                                >
                                    <icon-eye class="w-4 h-4" />
                                </router-link>
                            </tippy>
                            <tippy v-can="'users.update'" content="Edit">
                                <router-link
                                    :to="`/admin/users/${data.value.id}/edit`"
                                    class="btn btn-sm btn-outline-primary p-1.5"
                                >
                                    <icon-pencil class="w-4 h-4" />
                                </router-link>
                            </tippy>
                            <tippy v-can="'users.delete'" content="Delete">
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

            <!-- Empty State -->
            <div v-if="!userStore.isLoading && userStore.users.length === 0" class="text-center py-10">
                <icon-users class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
                <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">No users found</h3>
                <p class="text-gray-500 dark:text-gray-500 mb-4">
                    {{ searchQuery || roleFilter ? 'Try adjusting your search or filter.' : 'Get started by adding a new user.' }}
                </p>
                <router-link v-can="'users.create'" to="/admin/users/create" class="btn btn-primary">
                    <icon-user-plus class="w-5 h-5 mr-2" />
                    Add First User
                </router-link>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import Vue3Datatable from '@bhplugin/vue3-datatable';
import { useMeta } from '@/composables/use-meta';
import { useUserStore } from '@/stores/user';
import { useAuthStore } from '@/stores/auth';
import { useNotification } from '@/composables/useNotification';
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

useMeta({ title: 'User Management' });

const userStore = useUserStore();
const authStore = useAuthStore();
const { confirmDelete: confirmDeleteDialog, success, error, warning } = useNotification();

// Local state
const searchQuery = ref('');
const roleFilter = ref('');
const perPage = ref(15);
const currentPage = ref(1);
const sortColumn = ref('created_at');
const sortDirection = ref<'asc' | 'desc'>('desc');
const selectedUsers = ref<User[]>([]);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

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
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    searchTimeout = setTimeout(() => {
        currentPage.value = 1;
        clearSelection();
        fetchUsers();
    }, 300);
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

const handlePageChange = (page: number) => {
    if (page !== currentPage.value) {
        currentPage.value = page;
        clearSelection();
        fetchUsers();
    }
};

const handlePageSizeChange = (size: number) => {
    perPage.value = size;
    currentPage.value = 1;
    clearSelection();
    fetchUsers();
};

const handleSortChange = (data: { column: string; direction: 'asc' | 'desc' }) => {
    if (data.column !== sortColumn.value || data.direction !== sortDirection.value) {
        sortColumn.value = data.column;
        sortDirection.value = data.direction;
        fetchUsers();
    }
};

const handleRowSelect = (data: { selectedRows: User[] }) => {
    selectedUsers.value = data.selectedRows;
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
        warning('You cannot delete your own account');
        return;
    }

    const result = await confirmDeleteDialog(
        `Are you sure you want to delete "${user.name}"?`,
        'This action cannot be undone.'
    );

    if (result.isConfirmed) {
        try {
            await userStore.deleteUser(user.id);
            success('User deleted successfully');
            // Remove from selection if was selected
            selectedUsers.value = selectedUsers.value.filter(u => u.id !== user.id);
        } catch (err: any) {
            error(err.response?.data?.message || 'Failed to delete user');
        }
    }
};

const confirmBulkDelete = async () => {
    if (selectedUsers.value.length === 0) {
        warning('No users selected');
        return;
    }

    // Filter out current user from selection
    const currentUserId = authStore.user?.id;
    const usersToDelete = selectedUsers.value.filter(u => u.id !== currentUserId);

    if (usersToDelete.length === 0) {
        warning('You cannot delete your own account');
        return;
    }

    // Check if trying to delete self
    if (usersToDelete.length < selectedUsers.value.length) {
        warning('You cannot delete your own account. It has been excluded from the selection.');
    }

    // Check for admin users being deleted
    const adminUsers = usersToDelete.filter(u => u.roles?.some(r => r.name === 'admin'));
    let warningMessage = '';
    if (adminUsers.length > 0) {
        warningMessage = `\n\nWarning: ${adminUsers.length} administrator(s) will be deleted.`;
    }

    const result = await confirmDeleteDialog(
        `Are you sure you want to delete ${usersToDelete.length} user(s)?${warningMessage}`,
        'This action cannot be undone. All selected users will be permanently removed.'
    );

    if (result.isConfirmed) {
        try {
            const ids = usersToDelete.map(u => u.id);
            await userStore.bulkDeleteUsers(ids);
            success(`${usersToDelete.length} user(s) deleted successfully`);
            clearSelection();
        } catch (err: any) {
            error(err.response?.data?.message || 'Failed to delete users');
        }
    }
};

// Initialize
onMounted(async () => {
    await Promise.all([
        fetchUsers(),
        userStore.fetchRoles(),
    ]);
});
</script>
