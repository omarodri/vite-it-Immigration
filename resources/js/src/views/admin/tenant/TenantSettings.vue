<template>
    <div>
        <ul class="flex space-x-2 rtl:space-x-reverse mb-6">
            <li>
                <router-link to="/admin" class="text-primary hover:underline">{{ $t('sidebar.admin') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('tenant.settings_title') }}</span>
            </li>
        </ul>

        <div class="panel">
            <div class="mb-5">
                <h5 class="text-lg font-semibold dark:text-white-light">{{ $t('tenant.settings_title') }}</h5>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ $t('tenant.settings_description') }}
                </p>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-10">
                <div class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10"></div>
            </div>

            <form v-else @submit.prevent="saveSettings" class="space-y-5 max-w-2xl">
                <!-- Company Name -->
                <div>
                    <label for="company_name" class="block text-sm font-medium mb-1">{{ $t('tenant.company_name') }}</label>
                    <input
                        id="company_name"
                        v-model="form.company_name"
                        type="text"
                        class="form-input"
                        :placeholder="$t('tenant.company_name')"
                    />
                </div>

                <!-- Timezone -->
                <div>
                    <label for="timezone" class="block text-sm font-medium mb-1">{{ $t('tenant.timezone') }}</label>
                    <select id="timezone" v-model="form.timezone" class="form-select">
                        <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                    </select>
                </div>

                <!-- Date Format -->
                <div>
                    <label for="date_format" class="block text-sm font-medium mb-1">{{ $t('tenant.date_format') }}</label>
                    <select id="date_format" v-model="form.date_format" class="form-select">
                        <option v-for="fmt in dateFormats" :key="fmt.value" :value="fmt.value">
                            {{ fmt.label }}
                        </option>
                    </select>
                </div>

                <!-- Default Language -->
                <div>
                    <label for="language" class="block text-sm font-medium mb-1">{{ $t('tenant.default_language') }}</label>
                    <select id="language" v-model="form.language" class="form-select">
                        <option value="en">English</option>
                        <option value="es">Español</option>
                        <option value="fr">Français</option>
                    </select>
                </div>

                <!-- Name Display Format -->
                <div>
                    <label class="block text-sm font-medium mb-1">{{ $t('tenant.name_format') }}</label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">{{ $t('tenant.name_format_description') }}</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label
                            class="flex items-start gap-3 p-4 rounded-lg border-2 cursor-pointer transition-all duration-200"
                            :class="form.name_format === 'first_last'
                                ? 'border-primary bg-primary/5 dark:bg-primary/10'
                                : 'border-gray-200 dark:border-gray-700 hover:border-primary/50'"
                        >
                            <input
                                type="radio"
                                v-model="form.name_format"
                                value="first_last"
                                class="form-radio text-primary mt-0.5"
                            />
                            <div>
                                <div class="font-medium text-sm text-gray-900 dark:text-white">{{ $t('tenant.name_format_first_last') }}</div>
                                <div
                                    v-if="form.name_format === 'first_last'"
                                    class="mt-2 text-xs text-primary font-semibold flex items-center gap-1"
                                >
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {{ $t('tenant.name_format_preview') }}
                                </div>
                            </div>
                        </label>
                        <label
                            class="flex items-start gap-3 p-4 rounded-lg border-2 cursor-pointer transition-all duration-200"
                            :class="form.name_format === 'last_first'
                                ? 'border-primary bg-primary/5 dark:bg-primary/10'
                                : 'border-gray-200 dark:border-gray-700 hover:border-primary/50'"
                        >
                            <input
                                type="radio"
                                v-model="form.name_format"
                                value="last_first"
                                class="form-radio text-primary mt-0.5"
                            />
                            <div>
                                <div class="font-medium text-sm text-gray-900 dark:text-white">{{ $t('tenant.name_format_last_first') }}</div>
                                <div
                                    v-if="form.name_format === 'last_first'"
                                    class="mt-2 text-xs text-primary font-semibold flex items-center gap-1"
                                >
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {{ $t('tenant.name_format_preview') }}
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Max Timer Duration -->
                <div>
                    <label for="max_timer_duration" class="block text-sm font-medium mb-1">
                        {{ $t('tenant.max_timer_duration') }}
                    </label>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                        {{ $t('tenant.max_timer_duration_description') }}
                    </p>
                    <div class="flex items-center gap-3">
                        <input
                            id="max_timer_duration"
                            v-model.number="form.max_timer_duration"
                            type="number"
                            min="15"
                            max="1440"
                            class="form-input w-32"
                        />
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $t('tenant.minutes') }}</span>
                    </div>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        {{ $t('tenant.max_timer_duration_hint') }}
                    </p>
                </div>

                <!-- Show Theme Customizer -->
                <div>
                    <label class="block text-sm font-medium mb-2">{{ $t('tenant.show_customizer') }}</label>
                    <label class="w-12 h-6 relative">
                        <input
                            type="checkbox"
                            v-model="form.show_customizer"
                            class="custom_switch absolute w-full h-full opacity-0 z-10 cursor-pointer peer"
                        />
                        <span
                            class="bg-[#ebedf2] dark:bg-dark block h-full rounded-full before:absolute before:left-1 before:bg-white dark:before:bg-white-dark before:bottom-1 before:w-4 before:h-4 before:rounded-full peer-checked:before:left-7 peer-checked:bg-primary before:transition-all before:duration-300"
                        ></span>
                    </label>
                </div>

                <!-- Save Button -->
                <div class="pt-2">
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="saving"
                    >
                        <span v-if="saving" class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 mr-2 inline-block"></span>
                        {{ $t('tenant.save_settings') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, onMounted } from 'vue';
import Swal from 'sweetalert2';
import { useI18n } from 'vue-i18n';
import { useTenantStore } from '@/stores/tenant';
import { useMeta } from '@/composables/use-meta';

const { t } = useI18n();
useMeta({ title: 'Company Settings' });

const tenantStore = useTenantStore();
const loading = ref(true);
const saving = ref(false);

const timezones = [
    'America/New_York',
    'America/Toronto',
    'America/Chicago',
    'America/Denver',
    'America/Los_Angeles',
    'America/Mexico_City',
    'America/Bogota',
    'America/Sao_Paulo',
    'Europe/London',
    'Europe/Madrid',
    'Europe/Paris',
];

const dateFormats = [
    { value: 'Y-m-d', label: 'Y-m-d (2026-03-21)' },
    { value: 'm/d/Y', label: 'm/d/Y (03/21/2026)' },
    { value: 'd/m/Y', label: 'd/m/Y (21/03/2026)' },
    { value: 'd-m-Y', label: 'd-m-Y (21-03-2026)' },
];

const form = reactive({
    company_name: '',
    timezone: 'America/Toronto',
    date_format: 'Y-m-d',
    language: 'es',
    name_format: 'first_last' as 'first_last' | 'last_first',
    show_customizer: true,
    max_timer_duration: 120,
});

const loadData = async () => {
    try {
        if (!tenantStore.isLoaded) {
            await tenantStore.fetchTenant();
        }
        if (tenantStore.tenant) {
            form.company_name = tenantStore.tenant.company?.name ?? tenantStore.tenant.name ?? '';
            form.timezone = tenantStore.tenant.preferences?.timezone ?? 'America/Toronto';
            form.date_format = tenantStore.tenant.preferences?.date_format ?? 'Y-m-d';
            form.language = tenantStore.tenant.preferences?.language ?? 'es';
            form.name_format = (tenantStore.tenant.preferences?.name_format ?? 'first_last') as 'first_last' | 'last_first';
            form.show_customizer = tenantStore.tenant.theme?.show_customizer !== false;
            form.max_timer_duration = tenantStore.tenant.preferences?.max_timer_duration ?? 120;
        }
    } catch (error) {
        console.error('Failed to load tenant data:', error);
    } finally {
        loading.value = false;
    }
};

const saveSettings = async () => {
    saving.value = true;
    try {
        // Save general settings
        const result = await tenantStore.updateSettings({
            name: form.company_name,
            company_name: form.company_name,
            timezone: form.timezone,
            date_format: form.date_format,
            language: form.language,
            name_format: form.name_format,
            max_timer_duration: form.max_timer_duration ? parseInt(String(form.max_timer_duration), 10) : null,
        });

        // Save show_customizer (theme setting)
        const themeResult = await tenantStore.updateTheme({
            show_customizer: form.show_customizer,
        });

        if (result.success && themeResult.success) {
            Swal.fire({
                icon: 'success',
                title: t('tenant.settings_saved'),
                timer: 2000,
                showConfirmButton: false,
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: result.error || themeResult.error || t('tenant.save_failed'),
            });
        }
    } catch (error: any) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.response?.data?.message || t('tenant.save_failed'),
        });
    } finally {
        saving.value = false;
    }
};

onMounted(loadData);
</script>
