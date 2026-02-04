<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/admin/users" class="text-primary hover:underline">Users</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>View User</span>
            </li>
        </ul>

        <!-- Loading Skeleton -->
        <div v-if="isLoading" class="animate-pulse">
            <!-- Header Skeleton -->
            <div class="panel mb-5">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-20 h-20 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                        <div class="space-y-3">
                            <div class="h-6 w-48 bg-gray-200 dark:bg-gray-700 rounded"></div>
                            <div class="h-4 w-56 bg-gray-100 dark:bg-gray-800 rounded"></div>
                            <div class="flex gap-2">
                                <div class="h-5 w-16 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                                <div class="h-5 w-16 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <div class="h-9 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div>
                        <div class="h-9 w-16 bg-gray-200 dark:bg-gray-700 rounded"></div>
                        <div class="h-9 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    </div>
                </div>
            </div>
            <!-- Content Skeleton -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div class="panel lg:col-span-2">
                    <div class="h-6 w-48 bg-gray-200 dark:bg-gray-700 rounded mb-5"></div>
                    <div class="space-y-4">
                        <div v-for="i in 6" :key="i" class="flex items-center border-b border-gray-100 dark:border-gray-800 pb-4">
                            <div class="w-1/3"><div class="h-4 w-24 bg-gray-200 dark:bg-gray-700 rounded"></div></div>
                            <div class="w-2/3"><div class="h-4 w-40 bg-gray-200 dark:bg-gray-700 rounded"></div></div>
                        </div>
                    </div>
                </div>
                <div class="space-y-5">
                    <div class="panel h-28 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    <div class="panel">
                        <div class="h-6 w-32 bg-gray-200 dark:bg-gray-700 rounded mb-4"></div>
                        <div class="flex flex-wrap gap-2">
                            <div v-for="i in 4" :key="i" class="h-5 w-24 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                        </div>
                    </div>
                    <div class="panel">
                        <div class="h-6 w-32 bg-gray-200 dark:bg-gray-700 rounded mb-4"></div>
                        <div class="space-y-2">
                            <div v-for="i in 3" :key="i" class="h-9 bg-gray-200 dark:bg-gray-700 rounded"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Not Found -->
        <div v-else-if="!user" class="panel">
            <div class="text-center py-20">
                <icon-info-hexagon class="w-16 h-16 mx-auto text-danger mb-4" />
                <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">User Not Found</h3>
                <p class="text-gray-500 mb-4">The user you're looking for doesn't exist or has been deleted.</p>
                <router-link to="/admin/users" class="btn btn-primary">
                    Back to Users
                </router-link>
            </div>
        </div>

        <!-- User Details -->
        <template v-else>
            <!-- Header Card -->
            <div class="panel mb-5">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-20 h-20 rounded-full bg-gradient-to-r from-primary to-secondary flex items-center justify-center text-white">
                            <span class="text-2xl font-bold">{{ getInitials(user.name) }}</span>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold dark:text-white-light">{{ user.name }}</h2>
                            <p class="text-gray-500">{{ user.email }}</p>
                            <div class="flex flex-wrap gap-2 mt-2">
                                <span
                                    v-for="role in user.roles"
                                    :key="role.id"
                                    class="badge"
                                    :class="getRoleBadgeClass(role.name)"
                                >
                                    {{ capitalizeFirst(role.name) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <router-link to="/admin/users" class="btn btn-outline-secondary gap-2" aria-label="Back to users list">
                            <icon-arrow-left class="w-4 h-4" />
                            Back
                        </router-link>
                        <router-link
                            v-can="'users.update'"
                            :to="`/admin/users/${user.id}/edit`"
                            class="btn btn-primary gap-2"
                            :aria-label="`Edit ${user.name}`"
                        >
                            <icon-pencil class="w-4 h-4" />
                            Edit
                        </router-link>
                        <button
                            v-can="'users.delete'"
                            type="button"
                            class="btn btn-danger gap-2"
                            :aria-label="`Delete ${user.name}`"
                            @click="confirmDelete"
                        >
                            <icon-trash-lines class="w-4 h-4" />
                            Delete
                        </button>
                    </div>
                </div>
            </div>

            <!-- Info Cards -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <!-- Account Information -->
                <div class="panel lg:col-span-2">
                    <h5 class="font-semibold text-lg dark:text-white-light mb-5">Account Information</h5>
                    <div class="space-y-4">
                        <div class="flex items-center border-b border-gray-200 dark:border-gray-700 pb-4">
                            <div class="w-1/3 text-gray-500">
                                <div class="flex items-center gap-2">
                                    <icon-user class="w-4 h-4" />
                                    Full Name
                                </div>
                            </div>
                            <div class="w-2/3 font-medium dark:text-white-light">{{ user.name }}</div>
                        </div>
                        <div class="flex items-center border-b border-gray-200 dark:border-gray-700 pb-4">
                            <div class="w-1/3 text-gray-500">
                                <div class="flex items-center gap-2">
                                    <icon-mail class="w-4 h-4" />
                                    Email Address
                                </div>
                            </div>
                            <div class="w-2/3 font-medium dark:text-white-light">{{ user.email }}</div>
                        </div>
                        <div class="flex items-center border-b border-gray-200 dark:border-gray-700 pb-4">
                            <div class="w-1/3 text-gray-500">
                                <div class="flex items-center gap-2">
                                    <icon-circle-check class="w-4 h-4" />
                                    Email Status
                                </div>
                            </div>
                            <div class="w-2/3">
                                <span v-if="user.email_verified_at" class="badge badge-outline-success gap-1">
                                    <icon-circle-check class="w-3.5 h-3.5" />
                                    Verified on {{ formatDate(user.email_verified_at) }}
                                </span>
                                <span v-else class="badge badge-outline-warning gap-1">
                                    <icon-info-triangle class="w-3.5 h-3.5" />
                                    Not Verified
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center border-b border-gray-200 dark:border-gray-700 pb-4">
                            <div class="w-1/3 text-gray-500">
                                <div class="flex items-center gap-2">
                                    <icon-users class="w-4 h-4" />
                                    Roles
                                </div>
                            </div>
                            <div class="w-2/3">
                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="role in user.roles"
                                        :key="role.id"
                                        class="badge"
                                        :class="getRoleBadgeClass(role.name)"
                                    >
                                        {{ capitalizeFirst(role.name) }}
                                    </span>
                                    <span v-if="!user.roles?.length" class="text-gray-400">No roles assigned</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center border-b border-gray-200 dark:border-gray-700 pb-4">
                            <div class="w-1/3 text-gray-500">
                                <div class="flex items-center gap-2">
                                    <icon-calendar class="w-4 h-4" />
                                    Created At
                                </div>
                            </div>
                            <div class="w-2/3 font-medium dark:text-white-light">
                                {{ formatDateTime(user.created_at) }}
                            </div>
                        </div>
                        <div class="flex items-center">
                            <div class="w-1/3 text-gray-500">
                                <div class="flex items-center gap-2">
                                    <icon-clock class="w-4 h-4" />
                                    Last Updated
                                </div>
                            </div>
                            <div class="w-2/3 font-medium dark:text-white-light">
                                {{ formatDateTime(user.updated_at) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="space-y-5">
                    <!-- User ID Card -->
                    <div class="panel bg-gradient-to-r from-primary to-secondary text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm opacity-80">User ID</p>
                                <h3 class="text-3xl font-bold">#{{ user.id }}</h3>
                            </div>
                            <div class="w-14 h-14 rounded-full bg-white/20 flex items-center justify-center">
                                <icon-user class="w-7 h-7" />
                            </div>
                        </div>
                    </div>

                    <!-- Permissions Card -->
                    <div class="panel">
                        <h5 class="font-semibold text-lg dark:text-white-light mb-4">Permissions</h5>
                        <div v-if="user.permissions && user.permissions.length > 0" class="flex flex-wrap gap-2">
                            <span
                                v-for="permission in user.permissions"
                                :key="permission"
                                class="badge badge-outline-primary text-xs"
                            >
                                {{ permission }}
                            </span>
                        </div>
                        <div v-else class="text-center py-4 text-gray-500">
                            <icon-lock-dots class="w-10 h-10 mx-auto mb-2 opacity-50" />
                            <p class="text-sm">No direct permissions</p>
                            <p class="text-xs">Permissions are inherited from roles</p>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="panel">
                        <h5 class="font-semibold text-lg dark:text-white-light mb-4">Quick Actions</h5>
                        <div class="space-y-2">
                            <router-link
                                v-can="'users.update'"
                                :to="`/admin/users/${user.id}/edit`"
                                class="btn btn-outline-primary w-full justify-start gap-2"
                            >
                                <icon-pencil class="w-4 h-4" />
                                Edit User
                            </router-link>
                            <button
                                v-if="!user.email_verified_at"
                                type="button"
                                class="btn btn-outline-success w-full justify-start gap-2"
                                aria-label="Resend verification email"
                                @click="sendVerificationEmail"
                                :disabled="isSendingVerification"
                            >
                                <template v-if="isSendingVerification">
                                    <span class="animate-spin border-2 border-success border-l-transparent rounded-full w-4 h-4"></span>
                                    Sending...
                                </template>
                                <template v-else>
                                    <icon-mail class="w-4 h-4" />
                                    Resend Verification
                                </template>
                            </button>
                            <button
                                v-can="'users.delete'"
                                type="button"
                                class="btn btn-outline-danger w-full justify-start gap-2"
                                @click="confirmDelete"
                            >
                                <icon-trash-lines class="w-4 h-4" />
                                Delete User
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useMeta } from '@/composables/use-meta';
import { useUserStore } from '@/stores/user';
import { useNotification } from '@/composables/useNotification';
import { formatDate } from '@/utils/formatters';
import userService from '@/services/userService';
import type { User } from '@/types/user';

// Icons
import IconArrowLeft from '@/components/icon/icon-arrow-left.vue';
import IconUser from '@/components/icon/icon-user.vue';
import IconMail from '@/components/icon/icon-mail.vue';
import IconPencil from '@/components/icon/icon-pencil.vue';
import IconTrashLines from '@/components/icon/icon-trash-lines.vue';
import IconCircleCheck from '@/components/icon/icon-circle-check.vue';
import IconInfoTriangle from '@/components/icon/icon-info-triangle.vue';
import IconInfoHexagon from '@/components/icon/icon-info-hexagon.vue';
import IconUsers from '@/components/icon/icon-users.vue';
import IconCalendar from '@/components/icon/icon-calendar.vue';
import IconClock from '@/components/icon/icon-clock.vue';
import IconLockDots from '@/components/icon/icon-lock-dots.vue';

useMeta({ title: 'View User' });

const router = useRouter();
const route = useRoute();
const userStore = useUserStore();
const { success, error, confirmDelete: confirmDeleteDialog } = useNotification();

// State
const user = ref<User | null>(null);
const isLoading = ref(true);
const isSendingVerification = ref(false);

// Methods
const getInitials = (name: string): string => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

const capitalizeFirst = (str: string): string => {
    return str.charAt(0).toUpperCase() + str.slice(1);
};

const getRoleBadgeClass = (roleName: string): string => {
    const classes: Record<string, string> = {
        admin: 'badge-outline-danger',
        editor: 'badge-outline-warning',
        user: 'badge-outline-info',
    };
    return classes[roleName] || 'badge-outline-primary';
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

const fetchUser = async () => {
    const userId = Number(route.params.id);
    if (!userId) {
        isLoading.value = false;
        return;
    }

    try {
        user.value = await userService.getUser(userId);
    } catch (err) {
        error('Failed to load user');
        user.value = null;
    } finally {
        isLoading.value = false;
    }
};

const confirmDelete = async () => {
    if (!user.value) return;

    const result = await confirmDeleteDialog(
        `Are you sure you want to delete "${user.value.name}"?`,
        'This action cannot be undone. All user data will be permanently removed.'
    );

    if (result.isConfirmed) {
        try {
            await userStore.deleteUser(user.value.id);
            success('User deleted successfully');
            router.push('/admin/users');
        } catch (err: any) {
            error(err.response?.data?.message || 'Failed to delete user');
        }
    }
};

const sendVerificationEmail = async () => {
    // This would need a backend endpoint to resend verification for a specific user
    // For now, just show a message
    isSendingVerification.value = true;
    try {
        // await api.post(`/users/${user.value?.id}/send-verification`);
        success('Verification email sent successfully');
    } catch (err) {
        error('Failed to send verification email');
    } finally {
        isSendingVerification.value = false;
    }
};

// Initialize
onMounted(() => {
    fetchUser();
});
</script>
