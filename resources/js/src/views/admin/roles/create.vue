<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/admin/roles" class="text-primary hover:underline">{{ $t('sidebar.roles') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('roles.create_role') }}</span>
            </li>
        </ul>

        <div class="panel">
            <!-- Header -->
            <div class="flex items-center justify-between mb-5">
                <h5 class="font-semibold text-lg dark:text-white-light">{{ $t('roles.create_new_role') }}</h5>
                <router-link to="/admin/roles" class="btn btn-outline-secondary gap-2">
                    <icon-arrow-left class="w-4 h-4" />
                    {{ $t('roles.back_to_list') }}
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

                <!-- Role Name -->
                <div>
                    <label for="name" class="mb-2 block">
                        {{ $t('roles.role_name') }} <span class="text-danger">*</span>
                    </label>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="e.g., manager, support-agent"
                        class="form-input"
                        :class="{ 'border-danger': v$.name.$error }"
                        :aria-invalid="v$.name.$error"
                        :aria-describedby="v$.name.$error ? 'name-error' : 'name-hint'"
                    />
                    <template v-if="v$.name.$error">
                        <p id="name-error" role="alert" class="text-danger mt-1 text-sm">{{ v$.name.$errors[0]?.$message }}</p>
                    </template>
                    <p id="name-hint" class="text-gray-500 text-xs mt-1">{{ $t('roles.name_hint') }}</p>
                </div>

                <!-- Permissions -->
                <div>
                    <h6 class="font-semibold dark:text-white-light mb-3">{{ $t('roles.permissions') }}</h6>

                    <div v-if="roleStore.permissionGroups.length === 0" class="flex items-center gap-2 text-gray-500">
                        <span class="animate-spin border-2 border-primary border-l-transparent rounded-full w-4 h-4"></span>
                        Loading permissions...
                    </div>

                    <div v-else class="space-y-4">
                        <div
                            v-for="group in roleStore.permissionGroups"
                            :key="group.name"
                            class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
                        >
                            <div class="flex items-center justify-between mb-3">
                                <h6 class="font-semibold dark:text-white-light">{{ group.display_name }}</h6>
                                <label class="flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        class="form-checkbox text-primary"
                                        :checked="isGroupFullySelected(group.name)"
                                        :indeterminate="isGroupPartiallySelected(group.name)"
                                        @change="toggleGroup(group.name, $event)"
                                    />
                                    <span class="ml-2 text-sm">{{ $t('roles.select_all') }}</span>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                <label
                                    v-for="permission in group.permissions"
                                    :key="permission.id"
                                    class="flex items-center cursor-pointer"
                                >
                                    <input
                                        type="checkbox"
                                        :value="permission.name"
                                        v-model="form.permissions"
                                        class="form-checkbox text-primary"
                                    />
                                    <span class="ml-2 text-sm">{{ formatPermissionName(permission.name) }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <router-link to="/admin/roles" class="btn btn-outline-danger">
                        {{ $t('roles.cancel') }}
                    </router-link>
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="isSubmitting"
                    >
                        <template v-if="isSubmitting">
                            <span class="animate-spin border-2 border-white border-l-transparent rounded-full w-5 h-5 inline-block mr-2"></span>
                            {{ $t('roles.creating') }}
                        </template>
                        <template v-else>
                            <icon-save class="w-5 h-5 mr-2" />
                            {{ $t('roles.create_role') }}
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
import { required, helpers } from '@vuelidate/validators';
import { useMeta } from '@/composables/use-meta';
import { useRoleStore } from '@/stores/role';
import { useNotification } from '@/composables/useNotification';

// Icons
import IconArrowLeft from '@/components/icon/icon-arrow-left.vue';
import IconSave from '@/components/icon/icon-save.vue';
import IconX from '@/components/icon/icon-x.vue';

useMeta({ title: 'Create Role' });

const router = useRouter();
const roleStore = useRoleStore();
const { success, error } = useNotification();

// State
const isSubmitting = ref(false);
const errorMessage = ref('');

// Form
const form = reactive({
    name: '',
    permissions: [] as string[],
});

// Validation
const kebabCase = helpers.regex(/^[a-z0-9-]+$/);

const rules = computed(() => ({
    name: {
        required: helpers.withMessage('Role name is required', required),
        kebabCase: helpers.withMessage('Role name must contain only lowercase letters, numbers, and hyphens', kebabCase),
    },
}));

const v$ = useVuelidate(rules, form);

// Methods
const formatPermissionName = (name: string): string => {
    const parts = name.split('.');
    const action = parts[parts.length - 1];
    return action.charAt(0).toUpperCase() + action.slice(1);
};

const isGroupFullySelected = (groupName: string): boolean => {
    const group = roleStore.permissionGroups.find(g => g.name === groupName);
    if (!group) return false;
    return group.permissions.every(p => form.permissions.includes(p.name));
};

const isGroupPartiallySelected = (groupName: string): boolean => {
    const group = roleStore.permissionGroups.find(g => g.name === groupName);
    if (!group) return false;
    const selectedCount = group.permissions.filter(p => form.permissions.includes(p.name)).length;
    return selectedCount > 0 && selectedCount < group.permissions.length;
};

const toggleGroup = (groupName: string, event: Event) => {
    const group = roleStore.permissionGroups.find(g => g.name === groupName);
    if (!group) return;

    const checked = (event.target as HTMLInputElement).checked;
    const groupPermissionNames = group.permissions.map(p => p.name);

    if (checked) {
        const newPermissions = new Set([...form.permissions, ...groupPermissionNames]);
        form.permissions = Array.from(newPermissions);
    } else {
        form.permissions = form.permissions.filter(p => !groupPermissionNames.includes(p));
    }
};

const handleSubmit = async () => {
    const isValid = await v$.value.$validate();
    if (!isValid) return;

    isSubmitting.value = true;
    errorMessage.value = '';

    try {
        await roleStore.createRole({
            name: form.name,
            permissions: form.permissions,
        });

        success('Role created successfully');
        router.push('/admin/roles');
    } catch (err: any) {
        if (err.response?.data?.errors) {
            const errors = err.response.data.errors;
            const firstError = Object.values(errors)[0];
            errorMessage.value = Array.isArray(firstError) ? firstError[0] : String(firstError);
        } else {
            errorMessage.value = err.response?.data?.message || 'Failed to create role';
        }
    } finally {
        isSubmitting.value = false;
    }
};

// Initialize
onMounted(() => {
    roleStore.fetchPermissions();
});
</script>
