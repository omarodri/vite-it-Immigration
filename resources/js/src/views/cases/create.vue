<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/cases" class="text-primary hover:underline">{{ $t('sidebar.cases') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('cases.create_case') }}</span>
            </li>
        </ul>

        <div class="panel">
            <div class="mb-5">
                <h5 class="font-semibold text-lg dark:text-white-light">{{ $t('cases.create_case') }}</h5>
            </div>

            <form @submit.prevent="handleSubmit">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Main Information -->
                    <div class="space-y-5">
                        <h6 class="font-semibold border-b border-gray-200 dark:border-gray-700 pb-2">{{ $t('cases.main_information') }}</h6>

                        <!-- Client Selection -->
                        <div>
                            <label for="client_id" class="block text-sm font-medium mb-2">{{ $t('cases.select_client') }} <span class="text-danger">*</span></label>
                            <select id="client_id" v-model="form.client_id" class="form-select" :class="{ 'border-danger': errors.client_id }" required>
                                <option value="">{{ $t('cases.select_client') }}</option>
                                <option v-for="client in clients" :key="client.id" :value="client.id">
                                    {{ client.first_name }} {{ client.last_name }} - {{ client.email }}
                                </option>
                            </select>
                            <p v-if="errors.client_id" class="text-danger text-xs mt-1">{{ errors.client_id }}</p>
                        </div>

                        <!-- Case Type Selection -->
                        <div>
                            <label for="case_type_id" class="block text-sm font-medium mb-2">{{ $t('cases.select_type') }} <span class="text-danger">*</span></label>
                            <select id="case_type_id" v-model="form.case_type_id" class="form-select" :class="{ 'border-danger': errors.case_type_id }" required>
                                <option value="">{{ $t('cases.select_type') }}</option>
                                <option v-for="caseType in caseStore.activeCaseTypes" :key="caseType.id" :value="caseType.id">
                                    {{ caseType.name }} ({{ caseType.code }})
                                </option>
                            </select>
                            <p v-if="errors.case_type_id" class="text-danger text-xs mt-1">{{ errors.case_type_id }}</p>
                        </div>

                        <!-- Priority -->
                        <div>
                            <label for="priority" class="block text-sm font-medium mb-2">{{ $t('cases.priority') }}</label>
                            <select id="priority" v-model="form.priority" class="form-select">
                                <option value="medium">{{ $t('cases.medium') }}</option>
                                <option value="low">{{ $t('cases.low') }}</option>
                                <option value="high">{{ $t('cases.high') }}</option>
                                <option value="urgent">{{ $t('cases.urgent') }}</option>
                            </select>
                        </div>

                        <!-- Language -->
                        <div>
                            <label for="language" class="block text-sm font-medium mb-2">{{ $t('cases.language') }}</label>
                            <select id="language" v-model="form.language" class="form-select">
                                <option value="es">{{ $t('cases.spanish') }}</option>
                                <option value="en">{{ $t('cases.english') }}</option>
                                <option value="fr">{{ $t('cases.french') }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Dates -->
                    <div class="space-y-5">
                        <h6 class="font-semibold border-b border-gray-200 dark:border-gray-700 pb-2">{{ $t('cases.dates_section') }}</h6>

                        <!-- Hearing Date -->
                        <div>
                            <label for="hearing_date" class="block text-sm font-medium mb-2">{{ $t('cases.hearing_date') }}</label>
                            <input id="hearing_date" v-model="form.hearing_date" type="date" class="form-input" />
                        </div>

                        <!-- FDA Deadline -->
                        <div>
                            <label for="fda_deadline" class="block text-sm font-medium mb-2">{{ $t('cases.fda_deadline') }}</label>
                            <input id="fda_deadline" v-model="form.fda_deadline" type="date" class="form-input" />
                        </div>

                        <!-- Evidence Deadline -->
                        <div>
                            <label for="evidence_deadline" class="block text-sm font-medium mb-2">{{ $t('cases.evidence_deadline') }}</label>
                            <input id="evidence_deadline" v-model="form.evidence_deadline" type="date" class="form-input" />
                        </div>

                        <!-- Brown Sheet Date -->
                        <div>
                            <label for="brown_sheet_date" class="block text-sm font-medium mb-2">{{ $t('cases.brown_sheet_date') }}</label>
                            <input id="brown_sheet_date" v-model="form.brown_sheet_date" type="date" class="form-input" />
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="mt-6">
                    <label for="description" class="block text-sm font-medium mb-2">{{ $t('cases.description') }}</label>
                    <textarea id="description" v-model="form.description" rows="4" class="form-textarea" :placeholder="$t('cases.description_placeholder')"></textarea>
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-4 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <router-link to="/cases" class="btn btn-outline-secondary">{{ $t('cases.cancel') }}</router-link>
                    <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                        <span v-if="isSubmitting" class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 inline-block mr-2"></span>
                        {{ isSubmitting ? $t('cases.saving') : $t('cases.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useMeta } from '@/composables/use-meta';
import { useCaseStore } from '@/stores/case';
import { useNotification } from '@/composables/useNotification';
import clientService from '@/services/clientService';
import type { CreateCaseData } from '@/types/case';
import type { Client } from '@/types/client';

useMeta({ title: 'Create Case' });

const router = useRouter();
const { t } = useI18n();
const caseStore = useCaseStore();
const { success, error } = useNotification();

const clients = ref<Client[]>([]);
const isSubmitting = ref(false);
const errors = reactive<Record<string, string>>({});

const form = reactive<CreateCaseData>({
    client_id: 0,
    case_type_id: 0,
    priority: 'medium',
    language: 'es',
    description: '',
    hearing_date: '',
    fda_deadline: '',
    brown_sheet_date: '',
    evidence_deadline: '',
});

const handleSubmit = async () => {
    // Clear errors
    Object.keys(errors).forEach(key => delete errors[key]);

    // Validate
    if (!form.client_id) {
        errors.client_id = t('cases.client_required');
        return;
    }
    if (!form.case_type_id) {
        errors.case_type_id = t('cases.type_required');
        return;
    }

    isSubmitting.value = true;

    try {
        // Clean empty date fields
        const data: CreateCaseData = {
            client_id: form.client_id,
            case_type_id: form.case_type_id,
            priority: form.priority,
            language: form.language,
            description: form.description || undefined,
            hearing_date: form.hearing_date || undefined,
            fda_deadline: form.fda_deadline || undefined,
            brown_sheet_date: form.brown_sheet_date || undefined,
            evidence_deadline: form.evidence_deadline || undefined,
        };

        const response = await caseStore.createCase(data);
        success(t('cases.created_successfully'));
        router.push(`/cases/${response.data.id}`);
    } catch (err: any) {
        if (err.response?.data?.errors) {
            Object.assign(errors, err.response.data.errors);
        }
        error(err.response?.data?.message || t('cases.create_failed'));
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(async () => {
    // Load case types
    await caseStore.fetchCaseTypes();

    // Load clients for dropdown
    try {
        const response = await clientService.getClients({ per_page: 100, status: 'active' });
        clients.value = response.data;
    } catch (err) {
        console.error('Failed to load clients:', err);
    }
});
</script>
