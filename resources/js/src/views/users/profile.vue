<template>
    <div>
        <ul class="flex space-x-2 rtl:space-x-reverse">
            <li>
                <a href="javascript:;" class="text-primary hover:underline">Users</a>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>Profile</span>
            </li>
        </ul>

        <!-- Loading State -->
        <div v-if="profileStore.isLoading" class="pt-5">
            <div class="panel">
                <div class="flex items-center justify-center py-20">
                    <span class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10"></span>
                </div>
            </div>
        </div>

        <!-- Profile Content -->
        <div v-else class="pt-5">
            <div class="grid grid-cols-1 lg:grid-cols-3 xl:grid-cols-4 gap-5 mb-5">
                <!-- Profile Card -->
                <div class="panel">
                    <div class="flex items-center justify-between mb-5">
                        <h5 class="font-semibold text-lg dark:text-white-light">Profile</h5>
                        <router-link to="/users/user-account-settings" class="ltr:ml-auto rtl:mr-auto btn btn-primary p-2 rounded-full">
                            <icon-pencil-paper />
                        </router-link>
                    </div>
                    <div class="mb-5">
                        <div class="flex flex-col justify-center items-center">
                            <!-- Avatar -->
                            <div v-if="profileStore.avatarUrl" class="w-24 h-24 rounded-full overflow-hidden mb-5">
                                <img :src="profileStore.avatarUrl" alt="Avatar" class="w-full h-full object-cover" />
                            </div>
                            <div v-else class="w-24 h-24 rounded-full bg-gradient-to-r from-primary to-secondary flex items-center justify-center text-white mb-5">
                                <span class="text-3xl font-bold">{{ profileStore.initials }}</span>
                            </div>
                            <p class="font-semibold text-primary text-xl">{{ profileStore.fullName }}</p>
                            <p class="text-gray-500 text-sm">{{ profileStore.email }}</p>
                        </div>

                        <ul class="mt-5 flex flex-col max-w-[200px] m-auto space-y-4 font-semibold text-white-dark">
                            <li v-if="profile?.bio" class="flex items-start gap-2">
                                <icon-info-circle class="shrink-0 mt-0.5" />
                                <span class="text-sm">{{ profile.bio }}</span>
                            </li>
                            <li v-if="profile?.date_of_birth" class="flex items-center gap-2">
                                <icon-calendar class="shrink-0" />
                                {{ formatDate(profile.date_of_birth) }}
                            </li>
                            <li v-if="profileStore.location" class="flex items-center gap-2">
                                <icon-map-pin class="shrink-0" />
                                {{ profileStore.location }}
                            </li>
                            <li class="flex items-center gap-2">
                                <icon-mail class="w-5 h-5 shrink-0" />
                                <a :href="`mailto:${profileStore.email}`" class="text-primary truncate">{{ profileStore.email }}</a>
                            </li>
                            <li v-if="profile?.phone" class="flex items-center gap-2">
                                <icon-phone class="shrink-0" />
                                <span class="whitespace-nowrap" dir="ltr">{{ profile.phone }}</span>
                            </li>
                            <li v-if="profile?.website" class="flex items-center gap-2">
                                <icon-link class="shrink-0" />
                                <a :href="profile.website" target="_blank" class="text-primary truncate">{{ profile.website }}</a>
                            </li>
                        </ul>

                        <!-- Social Links -->
                        <ul v-if="hasSocialLinks" class="mt-7 flex items-center justify-center gap-2">
                            <li v-if="profile?.social_links?.twitter">
                                <a
                                    class="btn btn-info flex items-center justify-center rounded-full w-10 h-10 p-0"
                                    :href="`https://twitter.com/${profile.social_links.twitter}`"
                                    target="_blank"
                                >
                                    <icon-twitter class="w-5 h-5" />
                                </a>
                            </li>
                            <li v-if="profile?.social_links?.linkedin">
                                <a
                                    class="btn btn-primary flex items-center justify-center rounded-full w-10 h-10 p-0"
                                    :href="`https://linkedin.com/in/${profile.social_links.linkedin}`"
                                    target="_blank"
                                >
                                    <icon-linkedin class="w-5 h-5" />
                                </a>
                            </li>
                            <li v-if="profile?.social_links?.github">
                                <a
                                    class="btn btn-dark flex items-center justify-center rounded-full w-10 h-10 p-0"
                                    :href="`https://github.com/${profile.social_links.github}`"
                                    target="_blank"
                                >
                                    <icon-github class="w-5 h-5" />
                                </a>
                            </li>
                            <li v-if="profile?.social_links?.facebook">
                                <a
                                    class="btn btn-secondary flex items-center justify-center rounded-full w-10 h-10 p-0"
                                    :href="`https://facebook.com/${profile.social_links.facebook}`"
                                    target="_blank"
                                >
                                    <icon-facebook class="w-5 h-5" />
                                </a>
                            </li>
                        </ul>

                        <!-- Roles -->
                        <div v-if="authStore.roles.length > 0" class="mt-5 pt-5 border-t border-gray-200 dark:border-gray-700">
                            <p class="text-center text-sm text-gray-500 mb-2">Roles</p>
                            <div class="flex flex-wrap justify-center gap-2">
                                <span
                                    v-for="role in authStore.roles"
                                    :key="role"
                                    class="badge"
                                    :class="getRoleBadgeClass(role)"
                                >
                                    {{ capitalizeFirst(role) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Information -->
                <div class="panel lg:col-span-2 xl:col-span-3">
                    <div class="mb-5">
                        <h5 class="font-semibold text-lg dark:text-white-light">Account Information</h5>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Basic Info -->
                        <div class="space-y-4">
                            <h6 class="font-semibold text-gray-600 dark:text-gray-400">Basic Information</h6>
                            <div class="flex items-center border-b border-gray-200 dark:border-gray-700 pb-3">
                                <span class="w-1/3 text-gray-500">Full Name</span>
                                <span class="w-2/3 font-medium">{{ profileStore.fullName }}</span>
                            </div>
                            <div class="flex items-center border-b border-gray-200 dark:border-gray-700 pb-3">
                                <span class="w-1/3 text-gray-500">Email</span>
                                <span class="w-2/3 font-medium">{{ profileStore.email }}</span>
                            </div>
                            <div class="flex items-center border-b border-gray-200 dark:border-gray-700 pb-3">
                                <span class="w-1/3 text-gray-500">Phone</span>
                                <span class="w-2/3 font-medium">{{ profile?.phone || '-' }}</span>
                            </div>
                            <div class="flex items-center border-b border-gray-200 dark:border-gray-700 pb-3">
                                <span class="w-1/3 text-gray-500">Date of Birth</span>
                                <span class="w-2/3 font-medium">{{ profile?.date_of_birth ? formatDate(profile.date_of_birth) : '-' }}</span>
                            </div>
                            <div class="flex items-center pb-3">
                                <span class="w-1/3 text-gray-500">Website</span>
                                <span class="w-2/3 font-medium">
                                    <a v-if="profile?.website" :href="profile.website" target="_blank" class="text-primary">
                                        {{ profile.website }}
                                    </a>
                                    <span v-else>-</span>
                                </span>
                            </div>
                        </div>

                        <!-- Address Info -->
                        <div class="space-y-4">
                            <h6 class="font-semibold text-gray-600 dark:text-gray-400">Address Information</h6>
                            <div class="flex items-center border-b border-gray-200 dark:border-gray-700 pb-3">
                                <span class="w-1/3 text-gray-500">Address</span>
                                <span class="w-2/3 font-medium">{{ profile?.address || '-' }}</span>
                            </div>
                            <div class="flex items-center border-b border-gray-200 dark:border-gray-700 pb-3">
                                <span class="w-1/3 text-gray-500">City</span>
                                <span class="w-2/3 font-medium">{{ profile?.city || '-' }}</span>
                            </div>
                            <div class="flex items-center border-b border-gray-200 dark:border-gray-700 pb-3">
                                <span class="w-1/3 text-gray-500">State</span>
                                <span class="w-2/3 font-medium">{{ profile?.state || '-' }}</span>
                            </div>
                            <div class="flex items-center border-b border-gray-200 dark:border-gray-700 pb-3">
                                <span class="w-1/3 text-gray-500">Country</span>
                                <span class="w-2/3 font-medium">{{ profile?.country || '-' }}</span>
                            </div>
                            <div class="flex items-center pb-3">
                                <span class="w-1/3 text-gray-500">Postal Code</span>
                                <span class="w-2/3 font-medium">{{ profile?.postal_code || '-' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Bio Section -->
                    <div v-if="profile?.bio" class="mt-5 pt-5 border-t border-gray-200 dark:border-gray-700">
                        <h6 class="font-semibold text-gray-600 dark:text-gray-400 mb-3">About Me</h6>
                        <p class="text-gray-600 dark:text-gray-300">{{ profile.bio }}</p>
                    </div>

                    <!-- Preferences -->
                    <div class="mt-5 pt-5 border-t border-gray-200 dark:border-gray-700">
                        <h6 class="font-semibold text-gray-600 dark:text-gray-400 mb-3">Preferences</h6>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex items-center">
                                <span class="w-1/3 text-gray-500">Timezone</span>
                                <span class="w-2/3 font-medium">{{ profile?.timezone || 'Not set' }}</span>
                            </div>
                            <div class="flex items-center">
                                <span class="w-1/3 text-gray-500">Language</span>
                                <span class="w-2/3 font-medium">{{ profile?.language || 'Not set' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                <router-link to="/users/user-account-settings" class="panel hover:shadow-lg transition-shadow">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center">
                            <icon-settings class="w-6 h-6 text-primary" />
                        </div>
                        <div>
                            <h6 class="font-semibold">Account Settings</h6>
                            <p class="text-gray-500 text-sm">Manage your account</p>
                        </div>
                    </div>
                </router-link>

                <router-link to="/users/user-account-settings#security" class="panel hover:shadow-lg transition-shadow">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-warning/10 flex items-center justify-center">
                            <icon-lock-dots class="w-6 h-6 text-warning" />
                        </div>
                        <div>
                            <h6 class="font-semibold">Security</h6>
                            <p class="text-gray-500 text-sm">Change password</p>
                        </div>
                    </div>
                </router-link>

                <div class="panel">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-success/10 flex items-center justify-center">
                            <icon-circle-check class="w-6 h-6 text-success" />
                        </div>
                        <div>
                            <h6 class="font-semibold">Email Status</h6>
                            <p v-if="authStore.isEmailVerified" class="text-success text-sm">Verified</p>
                            <p v-else class="text-warning text-sm">Not verified</p>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-info/10 flex items-center justify-center">
                            <icon-calendar class="w-6 h-6 text-info" />
                        </div>
                        <div>
                            <h6 class="font-semibold">Member Since</h6>
                            <p class="text-gray-500 text-sm">{{ formatDate(authStore.user?.created_at) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { computed, onMounted } from 'vue';
import { useProfileStore } from '@/stores/profile';
import { useAuthStore } from '@/stores/auth';
import { useMeta } from '@/composables/use-meta';
import { formatDate } from '@/utils/formatters';

// Icons
import IconPencilPaper from '@/components/icon/icon-pencil-paper.vue';
import IconCalendar from '@/components/icon/icon-calendar.vue';
import IconMapPin from '@/components/icon/icon-map-pin.vue';
import IconMail from '@/components/icon/icon-mail.vue';
import IconPhone from '@/components/icon/icon-phone.vue';
import IconTwitter from '@/components/icon/icon-twitter.vue';
import IconGithub from '@/components/icon/icon-github.vue';
import IconFacebook from '@/components/icon/icon-facebook.vue';
import IconLinkedin from '@/components/icon/icon-linkedin.vue';
import IconLink from '@/components/icon/icon-link.vue';
import IconInfoCircle from '@/components/icon/icon-info-circle.vue';
import IconSettings from '@/components/icon/icon-settings.vue';
import IconLockDots from '@/components/icon/icon-lock-dots.vue';
import IconCircleCheck from '@/components/icon/icon-circle-check.vue';

useMeta({ title: 'My Profile' });

const profileStore = useProfileStore();
const authStore = useAuthStore();

// Computed
const profile = computed(() => profileStore.profile);

const hasSocialLinks = computed(() => {
    const links = profile.value?.social_links;
    if (!links) return false;
    return links.twitter || links.linkedin || links.github || links.facebook;
});

// Methods
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

// Initialize
onMounted(async () => {
    await profileStore.fetchProfile();
});
</script>
