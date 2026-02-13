<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/cases" class="text-primary hover:underline">{{ $t('sidebar.cases') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <router-link :to="`/cases/${route.params.id}`" class="text-primary hover:underline">{{ currentCase?.case_number || '...' }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('cases.edit') }}</span>
            </li>
        </ul>

        <!-- Loading State -->
        <div v-if="isLoading" class="panel">
            <div class="animate-pulse space-y-4">
                <div class="h-8 w-48 bg-gray-200 dark:bg-gray-700 rounded"></div>
                <div class="h-4 w-full bg-gray-200 dark:bg-gray-700 rounded"></div>
            </div>
        </div>

        <div v-else-if="currentCase" class="panel">
            <div class="mb-5">
                <h5 class="font-semibold text-lg dark:text-white-light">{{ $t('cases.edit_case') }}: {{ currentCase.case_number }}</h5>
            </div>

            <form @submit.prevent="handleSubmit">
                <!-- Actions -->
                <div class="flex justify-end gap-4 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <router-link :to="`/cases/${route.params.id}`" class="btn btn-outline-secondary">{{ $t('cases.cancel') }}</router-link>
                    <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                        <span v-if="isSubmitting" class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 inline-block mr-2"></span>
                        {{ isSubmitting ? $t('cases.saving') : $t('cases.save') }}
                    </button>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Main Information -->
                    <div class="space-y-5">
                        <h6 class="font-semibold border-b border-gray-200 dark:border-gray-700 pb-2">{{ $t('cases.main_information') }}</h6>

                        <!-- Case Number (Read Only) -->
                        <div>
                            <label class="block text-sm font-medium mb-2">{{ $t('cases.case_number') }}</label>
                            <input type="text" :value="currentCase.case_number" class="form-input bg-gray-100" disabled />
                        </div>

                        <!-- Status -->
                        <div>
                            <label for="status" class="block text-sm font-medium mb-2">{{ $t('cases.status') }}</label>
                            <select id="status" v-model="form.status" class="form-select">
                                <option value="active">{{ $t('cases.active') }}</option>
                                <option value="inactive">{{ $t('cases.inactive') }}</option>
                                <option value="archived">{{ $t('cases.archived') }}</option>
                                <option value="closed">{{ $t('cases.closed') }}</option>
                            </select>
                        </div>

                        <!-- assigned_to -->
                        <div>
                            <label class="block text-sm font-medium mb-2">{{ $t('cases.assigned_to') }}</label>
                            <select
                                id="assigned_to"
                                :value="form.assigned_to ?? ''"
                                class="form-select"
                                @change="form.assigned_to = ($event.target as HTMLSelectElement).value ? parseInt(($event.target as HTMLSelectElement).value) : null"
                            >
                                <option value="">{{ $t('cases.unassigned') }}</option>
                                <option v-for="staff in staffMembers" :key="staff.id" :value="staff.id">
                                    {{ staff.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Priority -->
                        <div>
                            <label for="priority" class="block text-sm font-medium mb-2">{{ $t('cases.priority') }}</label>
                            <select id="priority" v-model="form.priority" class="form-select">
                                <option value="low">{{ $t('cases.low') }}</option>
                                <option value="medium">{{ $t('cases.medium') }}</option>
                                <option value="high">{{ $t('cases.high') }}</option>
                                <option value="urgent">{{ $t('cases.urgent') }}</option>
                            </select>
                        </div>

                        <!-- Progress -->
                        <div>
                            <label for="progress" class="block text-sm font-medium mb-2">{{ $t('cases.progress') }}: {{ form.progress }}%</label>
                            <input id="progress" v-model.number="form.progress" type="range" min="0" max="100" class="w-full" />
                        </div>

                        <!-- Language -->
                        <div>
                            <label for="language" class="block text-sm font-medium mb-2">{{ $t('cases.language') }}</label>
                            <select id="language" v-model="form.language" class="form-select">
                                <option value="">{{ $t('clients.enter_language') }}</option>
                                <option v-for="opt in languageOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select> 
                        </div>
                    </div>

                    <!-- Dates -->
                    <div class="space-y-5">
                        <h6 class="font-semibold border-b border-gray-200 dark:border-gray-700 pb-2">{{ $t('cases.dates_section') }}</h6>

                        <!-- Hearing Date -->
                        <div>
                            <label for="hearing_date" class="block text-sm font-medium mb-2">{{ $t('cases.hearing_date') }}</label>
                            <flat-pickr
                                    v-model="form.hearing_date"
                                    :config="dateConfig"
                                    class="form-input"
                                    :placeholder="$t('clients.select_date')"
                                />  
                        </div>

                        <!-- FDA Deadline -->
                        <div>
                            <label for="fda_deadline" class="block text-sm font-medium mb-2">{{ $t('cases.fda_deadline') }}</label>
                            <flat-pickr
                                    v-model="form.fda_deadline"
                                    :config="dateConfig"
                                    class="form-input"
                                    :placeholder="$t('clients.select_date')"
                                />  
                        </div>

                        <!-- Evidence Deadline -->
                        <div>
                            <label for="evidence_deadline" class="block text-sm font-medium mb-2">{{ $t('cases.evidence_deadline') }}</label>
                            <flat-pickr
                                    v-model="form.evidence_deadline"
                                    :config="dateConfig"
                                    class="form-input"
                                    :placeholder="$t('clients.select_date')"
                                    
                                />                        
                            </div>

                        <!-- Brown Sheet Date -->
                        <div>
                            <label for="brown_sheet_date" class="block text-sm font-medium mb-2">{{ $t('cases.brown_sheet_date') }}</label>
                            <flat-pickr
                                    v-model="form.brown_sheet_date"
                                    :config="dateConfig"
                                    class="form-input"
                                    :placeholder="$t('clients.select_date')"
                                />  
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="mt-6">
                    <label for="description" class="block text-sm font-medium mb-2">{{ $t('cases.description') }}</label>
                    <textarea id="description" v-model="form.description" rows="4" class="form-textarea"></textarea>
                </div>

                <!-- Closure Notes (if closed) -->
                <div v-if="form.status === 'closed'" class="mt-6">
                    <label for="closure_notes" class="block text-sm font-medium mb-2">{{ $t('cases.closure_notes') }} <span class="text-danger">*</span></label>
                    <textarea id="closure_notes" v-model="form.closure_notes" rows="3" class="form-textarea" :class="{ 'border-danger': errors.closure_notes }" required></textarea>
                    <p v-if="errors.closure_notes" class="text-danger text-xs mt-1">{{ errors.closure_notes }}</p>
                </div>

                <!-- Archive Box Number (if archived) -->
                <div v-if="form.status === 'archived' || form.status === 'closed'" class="mt-6">
                    <label for="archive_box_number" class="block text-sm font-medium mb-2">{{ $t('cases.archive_box_number') }}</label>
                    <input id="archive_box_number" v-model="form.archive_box_number" type="text" class="form-input" placeholder="BOX-001" />
                </div>

                <!-- Actions -->
                <div class="flex justify-end gap-4 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <router-link :to="`/cases/${route.params.id}`" class="btn btn-outline-secondary">{{ $t('cases.cancel') }}</router-link>
                    <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                        <span v-if="isSubmitting" class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 inline-block mr-2"></span>
                        {{ isSubmitting ? $t('cases.saving') : $t('cases.save') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Not Found -->
        <div v-else class="panel text-center py-10">
            <icon-folder class="w-16 h-16 mx-auto text-gray-300 mb-4" />
            <h3 class="text-lg font-semibold text-gray-600 mb-2">{{ $t('cases.not_found') }}</h3>
            <router-link to="/cases" class="btn btn-primary mt-4">{{ $t('cases.back_to_list') }}</router-link>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useMeta } from '@/composables/use-meta';
import { useCaseStore } from '@/stores/case';
import { useNotification } from '@/composables/useNotification';
import type { UpdateCaseData } from '@/types/case';
import flatPickr from 'vue-flatpickr-component';
import 'flatpickr/dist/flatpickr.css';
import userService from '@/services/userService';
import type { StaffMember } from '@/types/wizard';

const staffMembers = ref<StaffMember[]>([]);

onMounted(async () => {
    staffMembers.value = await userService.getStaff();
});

const dateConfig = ref({
    dateFormat: 'Y-m-d H:i',
    allowInput: true,
    enableTime: true,
});

// Icons
import IconFolder from '@/components/icon/icon-folder.vue';

useMeta({ title: 'Edit Case' });

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const caseStore = useCaseStore();
const { success, error } = useNotification();

const languageOptions = computed(() => [
    { value: 'es', label: t('common.spanish') },
    { value: 'en', label: t('common.english') },
    { value: 'fr', label: t('common.french') },
]);


const isLoading = ref(true);
const isSubmitting = ref(false);
const errors = reactive<Record<string, string>>({});

const form = reactive<UpdateCaseData>({
    status: 'active',
    priority: 'medium',
    progress: 0,
    language: 'es',
    description: '',
    hearing_date: '',
    fda_deadline: '',
    brown_sheet_date: '',
    evidence_deadline: '',
    archive_box_number: '',
    closure_notes: '',
    assigned_to: null,
});

const currentCase = computed(() => caseStore.currentCase);

const handleSubmit = async () => {
    // Clear errors
    Object.keys(errors).forEach(key => delete errors[key]);

    // Validate closure notes if closing
    if (form.status === 'closed' && !form.closure_notes) {
        errors.closure_notes = t('cases.closure_notes_required');
        return;
    }

    isSubmitting.value = true;

    try {
        const caseId = parseInt(route.params.id as string);
        await caseStore.updateCase(caseId, form);
        success(t('cases.updated_successfully'));
        router.push(`/cases/${caseId}`);
    } catch (err: any) {
        if (err.response?.data?.errors) {
            Object.assign(errors, err.response.data.errors);
        }
        error(err.response?.data?.message || t('cases.update_failed'));
    } finally {
        isSubmitting.value = false;
    }
};

onMounted(async () => {
    const caseId = parseInt(route.params.id as string);
    try {
        await caseStore.fetchCase(caseId);
        if (currentCase.value) {
            // Populate form with current case data
            form.status = currentCase.value.status;
            form.priority = currentCase.value.priority;
            form.progress = currentCase.value.progress;
            form.language = currentCase.value.language;
            form.description = currentCase.value.description || '';
            form.hearing_date = currentCase.value.hearing_date || '';
            form.fda_deadline = currentCase.value.fda_deadline || '';
            form.brown_sheet_date = currentCase.value.brown_sheet_date || '';
            form.evidence_deadline = currentCase.value.evidence_deadline || '';
            form.archive_box_number = currentCase.value.archive_box_number || '';
            form.closure_notes = currentCase.value.closure_notes || '';
            form.assigned_to = currentCase.value.assigned_to ?? null;
        }
    } catch (err) {
        error(t('cases.failed_to_load'));
    } finally {
        isLoading.value = false;
    }
});
</script>
