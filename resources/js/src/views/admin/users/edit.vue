<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/admin/users" class="text-primary hover:underline">{{ $t('users.user_management') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('users.edit_user') }}</span>
            </li>
        </ul>

        <!-- Loading State -->
        <div v-if="isLoading" class="panel">
            <div class="flex items-center justify-center py-20">
                <span class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10"></span>
            </div>
        </div>

        <!-- Not Found -->
        <div v-else-if="!user" class="panel">
            <div class="text-center py-20">
                <icon-info-hexagon class="w-16 h-16 mx-auto text-danger mb-4" />
                <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">{{ $t('users.user_not_found') }}</h3>
                <p class="text-gray-500 mb-4">The user you're looking for doesn't exist or has been deleted.</p>
                <router-link to="/admin/users" class="btn btn-primary">
                    {{ $t('users.back_to_users') }}
                </router-link>
            </div>
        </div>

        <!-- Edit Form -->
        <div v-else class="panel">
            <!-- Header -->
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center">
                        <span class="text-primary font-semibold text-lg">
                            {{ getInitials(user.name) }}
                        </span>
                    </div>
                    <div>
                        <h5 class="font-semibold text-lg dark:text-white-light">{{ $t('users.edit_user') }}</h5>
                        <p class="text-gray-500 text-sm">{{ user.email }}</p>
                    </div>
                </div>
                <router-link to="/admin/users" class="btn btn-outline-secondary gap-2">
                    <icon-arrow-left class="w-4 h-4" />
                    {{ $t('users.back_to_list') }}
                </router-link>
            </div>

            <!-- Form -->
            <form @submit.prevent="handleSubmit" class="space-y-5">
                <!-- Error Alert -->
                <div v-if="errorMessage" role="alert" class="flex items-center p-3.5 rounded text-danger bg-danger-light dark:bg-danger-dark-light">
                    <span class="ltr:pr-2 rtl:pl-2">{{ errorMessage }}</span>
                    <button type="button" class="ltr:ml-auto rtl:mr-auto hover:opacity-80" aria-label="Dismiss error" @click="errorMessage = ''">
                        <icon-x class="w-4 h-4" />
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Name -->
                    <div>
                        <label for="name" class="mb-2 block">
                            {{ $t('users.name') }} <span class="text-danger">*</span>
                        </label>
                        <div class="relative">
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                :placeholder="$t('users.enter_full_name')"
                                class="form-input pl-10"
                                :class="{ 'border-danger': v$.name.$error }"
                                :aria-invalid="v$.name.$error"
                                :aria-describedby="v$.name.$error ? 'name-error' : undefined"
                            />
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">
                                <icon-user class="w-5 h-5" />
                            </span>
                        </div>
                        <template v-if="v$.name.$error">
                            <p id="name-error" role="alert" class="text-danger mt-1 text-sm">{{ v$.name.$errors[0]?.$message }}</p>
                        </template>
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="mb-2 block">
                            {{ $t('users.email') }} <span class="text-danger">*</span>
                        </label>
                        <div class="relative">
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                :placeholder="$t('users.enter_email_address')"
                                class="form-input pl-10"
                                :class="{ 'border-danger': v$.email.$error }"
                                :aria-invalid="v$.email.$error"
                                :aria-describedby="v$.email.$error ? 'email-error' : undefined"
                            />
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">
                                <icon-mail class="w-5 h-5" />
                            </span>
                        </div>
                        <template v-if="v$.email.$error">
                            <p id="email-error" role="alert" class="text-danger mt-1 text-sm">{{ v$.email.$errors[0]?.$message }}</p>
                        </template>
                    </div>
                </div>

                <!-- Password Section -->
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h6 class="font-semibold dark:text-white-light">{{ $t('users.change_password') }}</h6>
                            <p class="text-gray-500 text-sm">{{ $t('users.leave_empty_to_keep_current_password') }}</p>
                        </div>
                        <label class="flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="changePassword"
                                class="form-checkbox text-warning"
                            />
                            <span class="ml-2 text-sm">{{ $t('users.change_password') }}</span>
                        </label>
                    </div>

                    <div v-if="changePassword" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- New Password -->
                        <div>
                            <label for="password" class="mb-2 block">
                                {{ $t('users.new_password') }} <span class="text-danger">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    :placeholder="$t('users.enter_new_password')"
                                    class="form-input pl-10 pr-10"
                                    :class="{ 'border-danger': v$.password.$error }"
                                    :aria-invalid="v$.password.$error"
                                    :aria-describedby="v$.password.$error ? 'password-error' : 'password-hint'"
                                />
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">
                                    <icon-lock-dots class="w-5 h-5" />
                                </span>
                                <button
                                    type="button"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                                    aria-label="Toggle password visibility"
                                    @click="showPassword = !showPassword"
                                >
                                    <icon-eye class="w-5 h-5" />
                                </button>
                            </div>
                            <template v-if="v$.password.$error">
                                <p id="password-error" role="alert" class="text-danger mt-1 text-sm">{{ v$.password.$errors[0]?.$message }}</p>
                            </template>
                            <p id="password-hint" class="text-gray-500 text-xs mt-1">{{ $t('users.minimum_8_characters') }}</p>
                        </div>

                        <!-- Password Confirmation -->
                        <div>
                            <label for="password_confirmation" class="mb-2 block">
                                {{ $t('users.confirm_password') }} <span class="text-danger">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    :type="showPasswordConfirm ? 'text' : 'password'"
                                    :placeholder="$t('users.confirm_new_password')"
                                    class="form-input pl-10 pr-10"
                                    :class="{ 'border-danger': v$.password_confirmation.$error }"
                                    :aria-invalid="v$.password_confirmation.$error"
                                    :aria-describedby="v$.password_confirmation.$error ? 'password-confirm-error' : undefined"
                                />
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">
                                    <icon-lock-dots class="w-5 h-5" />
                                </span>
                                <button
                                    type="button"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                                    aria-label="Toggle password confirmation visibility"
                                    @click="showPasswordConfirm = !showPasswordConfirm"
                                >
                                    <icon-eye class="w-5 h-5" />
                                </button>
                            </div>
                            <template v-if="v$.password_confirmation.$error">
                                <p id="password-confirm-error" role="alert" class="text-danger mt-1 text-sm">{{ v$.password_confirmation.$errors[0]?.$message }}</p>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Roles -->
                <fieldset>
                    <legend class="mb-2 block">
                        {{ $t('users.roles') }} <span class="text-danger">*</span>
                    </legend>
                    <div v-if="isLoadingRoles" class="flex items-center gap-2 text-gray-500">
                        <span class="animate-spin border-2 border-primary border-l-transparent rounded-full w-4 h-4"></span>
                        {{ $t('users.loading_roles') }}
                    </div>
                    <div v-else class="flex flex-wrap gap-4" role="group" aria-label="User roles">
                        <label
                            v-for="role in roles"
                            :key="role.id"
                            class="flex items-center cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                :value="role.name"
                                v-model="form.roles"
                                class="form-checkbox"
                                :class="getRoleCheckboxClass(role.name)"
                            />
                            <span class="ml-2" :class="getRoleTextClass(role.name)">
                                {{ capitalizeFirst(role.name) }}
                            </span>
                        </label>
                    </div>
                    <template v-if="v$.roles.$error">
                        <p id="roles-error" role="alert" class="text-danger mt-1 text-sm">{{ v$.roles.$errors[0]?.$message }}</p>
                    </template>
                </fieldset>

                <!-- User Info -->
                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                    <h6 class="font-semibold mb-3 dark:text-white-light">{{ $t('users.user_information') }}</h6>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">ID:</span>
                            <span class="ml-2 font-medium">#{{ user.id }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">{{ $t('users.status') }}:</span>
                            <span v-if="user.email_verified_at" class="ml-2 badge badge-outline-success">Verified</span>
                            <span v-else class="ml-2 badge badge-outline-warning">Pending</span>
                        </div>
                        <div>
                            <span class="text-gray-500">{{ $t('users.created') }}:</span>
                            <span class="ml-2">{{ formatDate(user.created_at) }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">{{ $t('users.updated') }}:</span>
                            <span class="ml-2">{{ formatDate(user.updated_at) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <router-link to="/admin/users" class="btn btn-outline-danger">
                        {{ $t('users.cancel') }}
                    </router-link>
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="isSubmitting"
                    >
                        <template v-if="isSubmitting">
                            <span class="animate-spin border-2 border-white border-l-transparent rounded-full w-5 h-5 inline-block mr-2"></span>
                            {{ $t('users.saving') }}
                        </template>
                        <template v-else>
                            <icon-save class="w-5 h-5 mr-2" />
                            {{ $t('users.save_changes') }}
                        </template>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useVuelidate } from '@vuelidate/core';
import { required, email, minLength, sameAs, helpers, requiredIf } from '@vuelidate/validators';
import { useMeta } from '@/composables/use-meta';
import { useUserStore } from '@/stores/user';
import { useNotification } from '@/composables/useNotification';
import { formatDate } from '@/utils/formatters';
import roleService, { type RoleWithPermissions } from '@/services/roleService';
import userService from '@/services/userService';
import type { User } from '@/types/user';

// Icons
import IconArrowLeft from '@/components/icon/icon-arrow-left.vue';
import IconUser from '@/components/icon/icon-user.vue';
import IconMail from '@/components/icon/icon-mail.vue';
import IconLockDots from '@/components/icon/icon-lock-dots.vue';
import IconEye from '@/components/icon/icon-eye.vue';
import IconSave from '@/components/icon/icon-save.vue';
import IconX from '@/components/icon/icon-x.vue';
import IconInfoHexagon from '@/components/icon/icon-info-hexagon.vue';

useMeta({ title: 'Edit User' });

const router = useRouter();
const route = useRoute();
const userStore = useUserStore();
const { success, error } = useNotification();

// State
const user = ref<User | null>(null);
const roles = ref<RoleWithPermissions[]>([]);
const isLoading = ref(true);
const isSubmitting = ref(false);
const isLoadingRoles = ref(false);
const errorMessage = ref('');
const showPassword = ref(false);
const showPasswordConfirm = ref(false);
const changePassword = ref(false);

// Form
const form = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [] as string[],
});

// Computed for sameAs validation
const passwordRef = computed(() => form.password);

// Validation rules
const rules = computed(() => ({
    name: {
        required: helpers.withMessage('Name is required', required),
        minLength: helpers.withMessage('Name must be at least 2 characters', minLength(2)),
    },
    email: {
        required: helpers.withMessage('Email is required', required),
        email: helpers.withMessage('Please enter a valid email address', email),
    },
    password: {
        requiredIf: helpers.withMessage('Password is required', requiredIf(changePassword)),
        minLength: helpers.withMessage('Password must be at least 8 characters', minLength(8)),
    },
    password_confirmation: {
        requiredIf: helpers.withMessage('Please confirm the password', requiredIf(changePassword)),
        sameAs: helpers.withMessage('Passwords do not match', sameAs(passwordRef)),
    },
    roles: {
        required: helpers.withMessage('Please select at least one role', required),
        minLength: helpers.withMessage('Please select at least one role', minLength(1)),
    },
}));

const v$ = useVuelidate(rules, form);

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

const getRoleCheckboxClass = (roleName: string): string => {
    const classes: Record<string, string> = {
        admin: 'text-danger',
        editor: 'text-warning',
        user: 'text-info',
    };
    return classes[roleName] || 'text-primary';
};

const getRoleTextClass = (roleName: string): string => {
    const classes: Record<string, string> = {
        admin: 'text-danger font-semibold',
        editor: 'text-warning',
        user: 'text-info',
    };
    return classes[roleName] || '';
};

const fetchUser = async () => {
    const userId = Number(route.params.id);
    if (!userId) {
        isLoading.value = false;
        return;
    }

    try {
        user.value = await userService.getUser(userId);

        // Populate form
        form.name = user.value.name;
        form.email = user.value.email;
        form.roles = user.value.roles?.map(r => r.name) || [];
    } catch (err) {
        error('Failed to load user');
        user.value = null;
    } finally {
        isLoading.value = false;
    }
};

const fetchRoles = async () => {
    isLoadingRoles.value = true;
    try {
        roles.value = await roleService.getRoles();
    } catch (err) {
        error('Failed to load roles');
    } finally {
        isLoadingRoles.value = false;
    }
};

const handleSubmit = async () => {
    const isValid = await v$.value.$validate();
    if (!isValid) return;

    isSubmitting.value = true;
    errorMessage.value = '';

    try {
        const userId = Number(route.params.id);
        const updateData: any = {
            name: form.name,
            email: form.email,
            roles: form.roles,
        };

        // Only include password if changing
        if (changePassword.value && form.password) {
            updateData.password = form.password;
            updateData.password_confirmation = form.password_confirmation;
        }

        await userStore.updateUser(userId, updateData);

        success('User updated successfully');
        router.push('/admin/users');
    } catch (err: any) {
        if (err.response?.data?.errors) {
            const errors = err.response.data.errors;
            const firstError = Object.values(errors)[0];
            errorMessage.value = Array.isArray(firstError) ? firstError[0] : String(firstError);
        } else {
            errorMessage.value = err.response?.data?.message || 'Failed to update user';
        }
    } finally {
        isSubmitting.value = false;
    }
};

// Watch for password change toggle
watch(changePassword, (newValue) => {
    if (!newValue) {
        form.password = '';
        form.password_confirmation = '';
        v$.value.password.$reset();
        v$.value.password_confirmation.$reset();
    }
});

// Initialize
onMounted(() => {
    Promise.all([fetchUser(), fetchRoles()]);
});
</script>
