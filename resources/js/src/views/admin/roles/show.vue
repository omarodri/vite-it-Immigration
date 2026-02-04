<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/admin/roles" class="text-primary hover:underline">{{ $t('sidebar.roles') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ role ? capitalizeFirst(role.name) : $t('roles.role_details') }}</span>
            </li>
        </ul>

        <!-- Loading Skeleton -->
        <div v-if="isLoading" class="animate-pulse">
            <!-- Header Skeleton -->
            <div class="panel mb-5">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                        <div class="space-y-3">
                            <div class="h-6 w-40 bg-gray-200 dark:bg-gray-700 rounded"></div>
                            <div class="h-4 w-24 bg-gray-100 dark:bg-gray-800 rounded-full"></div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <div class="h-9 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div>
                        <div class="h-9 w-16 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    </div>
                </div>
            </div>
            <!-- Content Skeleton -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div class="panel lg:col-span-2">
                    <div class="h-6 w-48 bg-gray-200 dark:bg-gray-700 rounded mb-5"></div>
                    <div class="space-y-4">
                        <div v-for="i in 3" :key="i" class="flex items-center border-b border-gray-100 dark:border-gray-800 pb-4">
                            <div class="w-1/3"><div class="h-4 w-24 bg-gray-200 dark:bg-gray-700 rounded"></div></div>
                            <div class="w-2/3"><div class="h-4 w-40 bg-gray-200 dark:bg-gray-700 rounded"></div></div>
                        </div>
                    </div>
                </div>
                <div class="space-y-5">
                    <div class="panel">
                        <div class="h-6 w-32 bg-gray-200 dark:bg-gray-700 rounded mb-4"></div>
                        <div class="space-y-3">
                            <div v-for="i in 3" :key="i" class="h-20 bg-gray-100 dark:bg-gray-800 rounded-lg"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Not Found -->
        <div v-else-if="!role" class="panel">
            <div class="text-center py-20">
                <icon-info-hexagon class="w-16 h-16 mx-auto text-danger mb-4" />
                <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">{{ $t('roles.no_results_found') }}</h3>
                <p class="text-gray-500 mb-4">The role you're looking for doesn't exist or has been deleted.</p>
                <router-link to="/admin/roles" class="btn btn-primary">
                    {{ $t('roles.back_to_list') }}
                </router-link>
            </div>
        </div>

        <!-- Role Details -->
        <template v-else>
            <!-- Header Card -->
            <div class="panel mb-5">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-full bg-gradient-to-r from-primary to-secondary flex items-center justify-center text-white">
                            <icon-lock-dots class="w-7 h-7" />
                        </div>
                        <div>
                            <h2 class="text-xl font-bold dark:text-white-light">{{ capitalizeFirst(role.name) }}</h2>
                            <div class="flex flex-wrap gap-2 mt-1">
                                <span v-if="isProtected" class="badge badge-outline-warning gap-1">
                                    <icon-lock class="w-3 h-3" />
                                    {{ $t('roles.protected') }}
                                </span>
                                <span class="badge badge-outline-info">
                                    {{ role.permissions?.length || 0 }} {{ $t('roles.permissions') }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <router-link to="/admin/roles" class="btn btn-outline-secondary gap-2" aria-label="Back to roles list">
                            <icon-arrow-left class="w-4 h-4" />
                            {{ $t('roles.back_to_list') }}
                        </router-link>
                        <router-link
                            v-if="!isProtected"
                            v-can="'roles.update'"
                            :to="`/admin/roles/${role.id}/edit`"
                            class="btn btn-primary gap-2"
                            :aria-label="`Edit ${role.name}`"
                        >
                            <icon-pencil class="w-4 h-4" />
                            {{ $t('roles.edit') }}
                        </router-link>
                    </div>
                </div>
            </div>

            <!-- Info Cards -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <!-- Role Information -->
                <div class="panel lg:col-span-2">
                    <h5 class="font-semibold text-lg dark:text-white-light mb-5">{{ $t('roles.role_details') }}</h5>
                    <div class="space-y-4">
                        <div class="flex items-center border-b border-gray-200 dark:border-gray-700 pb-4">
                            <div class="w-1/3 text-gray-500">
                                <div class="flex items-center gap-2">
                                    <icon-lock-dots class="w-4 h-4" />
                                    {{ $t('roles.role_name') }}
                                </div>
                            </div>
                            <div class="w-2/3 font-medium dark:text-white-light">
                                {{ role.name }}
                                <span v-if="isProtected" class="badge badge-outline-warning ml-2 text-xs">{{ $t('roles.protected') }}</span>
                            </div>
                        </div>
                        <div class="flex items-center border-b border-gray-200 dark:border-gray-700 pb-4">
                            <div class="w-1/3 text-gray-500">
                                <div class="flex items-center gap-2">
                                    <icon-calendar class="w-4 h-4" />
                                    {{ $t('roles.created_at') }}
                                </div>
                            </div>
                            <div class="w-2/3 font-medium dark:text-white-light">
                                {{ formatDateTime(role.created_at) }}
                            </div>
                        </div>
                        <div class="flex items-center">
                            <div class="w-1/3 text-gray-500">
                                <div class="flex items-center gap-2">
                                    <icon-clock class="w-4 h-4" />
                                    {{ $t('roles.updated_at') }}
                                </div>
                            </div>
                            <div class="w-2/3 font-medium dark:text-white-light">
                                {{ formatDateTime(role.updated_at) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="space-y-5">
                    <!-- Role ID Card -->
                    <div class="panel bg-gradient-to-r from-primary to-secondary text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm opacity-80">Role ID</p>
                                <h3 class="text-3xl font-bold">#{{ role.id }}</h3>
                            </div>
                            <div class="w-14 h-14 rounded-full bg-white/20 flex items-center justify-center">
                                <icon-lock-dots class="w-7 h-7" />
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions Card -->
                    <div class="panel">
                        <h5 class="font-semibold text-lg dark:text-white-light mb-4">Quick Actions</h5>
                        <div class="space-y-2">
                            <router-link
                                v-if="!isProtected"
                                v-can="'roles.update'"
                                :to="`/admin/roles/${role.id}/edit`"
                                class="btn btn-outline-primary w-full justify-start gap-2"
                            >
                                <icon-pencil class="w-4 h-4" />
                                {{ $t('roles.edit') }}
                            </router-link>
                            <button
                                v-if="!isProtected"
                                v-can="'roles.delete'"
                                type="button"
                                class="btn btn-outline-danger w-full justify-start gap-2"
                                @click="handleDelete"
                            >
                                <icon-trash-lines class="w-4 h-4" />
                                {{ $t('roles.delete') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Permissions Panel -->
            <div class="panel mt-5">
                <h5 class="font-semibold text-lg dark:text-white-light mb-5">
                    {{ $t('roles.permissions') }} ({{ role.permissions?.length || 0 }})
                </h5>

                <div v-if="!role.permissions || role.permissions.length === 0" class="text-center py-10 text-gray-500">
                    <icon-lock-dots class="w-12 h-12 mx-auto mb-3 opacity-50" />
                    <p>{{ $t('roles.no_permissions_assigned') }}</p>
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-for="group in groupedPermissions"
                        :key="group.name"
                        class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
                    >
                        <h6 class="font-semibold dark:text-white-light mb-3">
                            {{ group.display_name }}
                            <span class="text-xs text-gray-400 font-normal ml-1">({{ group.permissions.length }})</span>
                        </h6>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="permission in group.permissions"
                                :key="permission.id"
                                class="badge badge-outline-primary text-xs"
                            >
                                {{ formatPermissionName(permission.name) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useMeta } from '@/composables/use-meta';
import { useRoleStore } from '@/stores/role';
import { useNotification } from '@/composables/useNotification';
import type { RoleWithPermissions } from '@/services/roleService';

// Icons
import IconArrowLeft from '@/components/icon/icon-arrow-left.vue';
import IconLockDots from '@/components/icon/icon-lock-dots.vue';
import IconLock from '@/components/icon/icon-lock.vue';
import IconPencil from '@/components/icon/icon-pencil.vue';
import IconTrashLines from '@/components/icon/icon-trash-lines.vue';
import IconInfoHexagon from '@/components/icon/icon-info-hexagon.vue';
import IconCalendar from '@/components/icon/icon-calendar.vue';
import IconClock from '@/components/icon/icon-clock.vue';

useMeta({ title: 'View Role' });

const router = useRouter();
const route = useRoute();
const roleStore = useRoleStore();
const { success, error, confirmDelete } = useNotification();

// State
const role = ref<RoleWithPermissions | null>(null);
const isLoading = ref(true);

// Computed
const isProtected = computed(() => {
    return role.value ? roleStore.isProtectedRole(role.value.name) : false;
});

const groupedPermissions = computed(() => {
    if (!role.value?.permissions) return [];

    const groups: Record<string, { id: number; name: string }[]> = {};

    for (const permission of role.value.permissions) {
        const groupName = permission.name.split('.')[0];
        if (!groups[groupName]) {
            groups[groupName] = [];
        }
        groups[groupName].push(permission);
    }

    return Object.entries(groups).map(([name, permissions]) => ({
        name,
        display_name: name.charAt(0).toUpperCase() + name.slice(1),
        permissions,
    }));
});

// Methods
const capitalizeFirst = (str: string): string => {
    return str.charAt(0).toUpperCase() + str.slice(1);
};

const formatPermissionName = (name: string): string => {
    const parts = name.split('.');
    const action = parts[parts.length - 1];
    return action.charAt(0).toUpperCase() + action.slice(1);
};

const formatDateTime = (date: string): string => {
    if (!date) return '-';
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const handleDelete = async () => {
    if (!role.value) return;

    const confirmed = await confirmDelete(role.value.name);
    if (confirmed) {
        try {
            await roleStore.deleteRole(role.value.id);
            success('Role deleted successfully');
            router.push('/admin/roles');
        } catch (err: any) {
            error(err.response?.data?.message || 'Failed to delete role');
        }
    }
};

// Initialize
onMounted(async () => {
    const roleId = Number(route.params.id);
    if (!roleId) {
        isLoading.value = false;
        return;
    }

    try {
        role.value = await roleStore.fetchRole(roleId);
    } catch (err) {
        error('Failed to load role');
        role.value = null;
    } finally {
        isLoading.value = false;
    }
});
</script>
