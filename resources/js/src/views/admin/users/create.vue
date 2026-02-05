<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/admin/users" class="text-primary hover:underline">{{ $t('users.users') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('users.create_user') }}</span>
            </li>
        </ul>

        <div class="panel">
            <!-- Header -->
            <div class="flex items-center justify-between mb-5">
                <h5 class="font-semibold text-lg dark:text-white-light">{{ $t('users.create_new_user') }}</h5>
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

                    <!-- Password -->
                    <div>
                        <label for="password" class="mb-2 block">
                            {{ $t('users.password') }} <span class="text-danger">*</span>
                        </label>
                        <div class="relative">
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                :placeholder="$t('users.enter_password')"
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
                        <p id="password-hint" class="text-gray-500 text-xs mt-1">{{ $t('minimum_8_characters') }}</p>
                    </div>

                    <!-- Password Confirmation -->
                    <div>
                        <label for="password_confirmation" class="mb-2 block">
                            {{ $t('confirm_password') }} <span class="text-danger">*</span>
                        </label>
                        <div class="relative">
                            <input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                :type="showPasswordConfirm ? 'text' : 'password'"
                                :placeholder="$t('users.confirm_password')"
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

                <!-- Send Welcome Email -->
                <div>
                    <label class="flex items-center cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.send_welcome_email"
                            class="form-checkbox text-success"
                        />
                        <span class="ml-2 text-white-dark">
                            {{ $t('users.send_welcome_email_with_login_credentials') }}
                        </span>
                    </label>
                    <p class="text-gray-500 text-xs mt-1 ml-6">
                        {{ $t('users.the_user_will_receive_an_email_with_their_login_details_and_instructions') }}
                    </p>
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
                            {{ $t('users.creating') }}
                        </template>
                        <template v-else>
                            <icon-save class="w-5 h-5 mr-2" />
                            {{ $t('users.create_user') }}
                        </template>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useVuelidate } from '@vuelidate/core';
import { required, email, minLength, sameAs, helpers } from '@vuelidate/validators';
import { useMeta } from '@/composables/use-meta';
import { useUserStore } from '@/stores/user';
import { useNotification } from '@/composables/useNotification';
import roleService, { type RoleWithPermissions } from '@/services/roleService';
import { useI18n } from 'vue-i18n';

// Icons
import IconArrowLeft from '@/components/icon/icon-arrow-left.vue';
import IconUser from '@/components/icon/icon-user.vue';
import IconMail from '@/components/icon/icon-mail.vue';
import IconLockDots from '@/components/icon/icon-lock-dots.vue';
import IconEye from '@/components/icon/icon-eye.vue';
import IconSave from '@/components/icon/icon-save.vue';
import IconX from '@/components/icon/icon-x.vue';

useMeta({ title: 'Create User' });

const router = useRouter();
const userStore = useUserStore();
const { success, error } = useNotification();
const { t } = useI18n();

// State
const roles = ref<RoleWithPermissions[]>([]);
const isSubmitting = ref(false);
const isLoadingRoles = ref(false);
const errorMessage = ref('');
const showPassword = ref(false);
const showPasswordConfirm = ref(false);

// Form
const form = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [] as string[],
    send_welcome_email: true,
});

// Computed for sameAs validation
const passwordRef = computed(() => form.password);

// Validation rules
const rules = computed(() => ({
    name: {
        required: helpers.withMessage( () => t('users.name_is_required'), required),
        minLength: helpers.withMessage( () => t('users.name_must_be_at_least_2_characters'), minLength(2)),
    },
    email: {
        required: helpers.withMessage( () => t('users.email_is_required'), required),
        email: helpers.withMessage( () => t('users.please_enter_a_valid_email_address'), email),
    },
    password: {
        required: helpers.withMessage( () => t('users.password_is_required'), required),
        minLength: helpers.withMessage( () => t('users.password_must_be_at_least_8_characters'), minLength(8)),
    },
    password_confirmation: {
        required: helpers.withMessage( () => t('users.please_confirm_the_password'), required),
        sameAs: helpers.withMessage( () => t('users.passwords_do_not_match'), sameAs(passwordRef)),
    },
    roles: {
        required: helpers.withMessage( () => t('users.please_select_at_least_one_role'), required),
        minLength: helpers.withMessage( () => t('users.please_select_at_least_one_role'), minLength(1)),
    },
}));

const v$ = useVuelidate(rules, form);

// Methods
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
        await userStore.createUser({
            name: form.name,
            email: form.email,
            password: form.password,
            password_confirmation: form.password_confirmation,
            roles: form.roles,
            send_welcome_email: form.send_welcome_email,
        });

        success('User created successfully');
        router.push('/admin/users');
    } catch (err: any) {
        if (err.response?.data?.errors) {
            const errors = err.response.data.errors;
            const firstError = Object.values(errors)[0];
            errorMessage.value = Array.isArray(firstError) ? firstError[0] : String(firstError);
        } else {
            errorMessage.value = err.response?.data?.message || 'Failed to create user';
        }
    } finally {
        isSubmitting.value = false;
    }
};

// Initialize
onMounted(() => {
    fetchRoles();
});
</script>
