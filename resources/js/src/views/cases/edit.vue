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
                                    {{ staff.name }}{{ staff.is_current_assignment ? ` — ${$t('cases.inactive_consultant')}` : '' }}
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

                        <!-- Progress (driven by workflow tasks) -->
                        <div>
                            <div class="space-y-1">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">{{ $t('cases.progress') }}</span>
                                    <span class="font-semibold">{{ form.progress }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div class="h-2 rounded-full bg-primary transition-all" :style="{ width: `${form.progress}%` }"></div>
                                </div>
                                <p class="text-xs text-gray-400">{{ $t('cases.lifecycle_auto_progress') }}</p>
                            </div>
                        </div>

                        <!-- Language -->
                        <div>
                            <label for="language" class="block text-sm font-medium mb-2">{{ $t('cases.language') }}</label>
                            <select id="language" v-model="form.language" class="form-select">
                                <option value="">{{ $t('clients.enter_language') }}</option>
                                <option v-for="opt in languageOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>

                        <!-- Operational Tracking -->
                        <h6 class="font-semibold border-b border-gray-200 dark:border-gray-700 pb-2 mt-4">{{ $t('cases.operational_info') }}</h6>

                        <!-- Current Workflow Stage -->
                        <div>
                            <label for="current_stage_id" class="block text-sm font-medium mb-2">{{ $t('cases.stage') }}</label>
                            <select id="current_stage_id" v-model="selectedStageId" class="form-select">
                                <option :value="null">{{ $t('cases.no_stage') }}</option>
                                <option
                                    v-for="stage in workflowStages"
                                    :key="stage.id"
                                    :value="stage.id"
                                >
                                    {{ stage.name }}
                                </option>
                            </select>
                        </div>

                        <!-- IRCC Status -->
                        <div>
                            <label for="ircc_status" class="block text-sm font-medium mb-2">{{ $t('cases.ircc_status') }}</label>
                            <select id="ircc_status" v-model="form.ircc_status" class="form-select">
                                <option :value="null">{{ $t('cases.no_ircc_status') }}</option>
                                <option v-for="opt in IRCC_STATUS_OPTIONS" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Final Result (only when ircc_status is approved or refused) -->
                        <div v-if="form.ircc_status === 'approved' || form.ircc_status === 'refused'">
                            <label for="final_result" class="block text-sm font-medium mb-2">{{ $t('cases.final_result') }}</label>
                            <select id="final_result" v-model="form.final_result" class="form-select">
                                <option :value="null">---</option>
                                <option v-for="opt in FINAL_RESULT_OPTIONS" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>

                        <!-- IRCC Code -->
                        <div>
                            <label for="ircc_code" class="block text-sm font-medium mb-2">{{ $t('cases.ircc_code') }}</label>
                            <input id="ircc_code" v-model="form.ircc_code" type="text" class="form-input" placeholder="B000123456" maxlength="50" />
                        </div>
                    </div>

                    <!-- Important Dates -->
                    <div class="space-y-5">
                        <h6 class="font-semibold border-b border-gray-200 dark:border-gray-700 pb-2">{{ $t('cases.important_dates') }}</h6>
                        <DateManager v-model="form.important_dates" />
                    </div>
                </div>

                <!-- Description -->
                <div class="mt-6">
                    <label for="description" class="block text-sm font-medium mb-2">{{ $t('cases.description') }}</label>
                    <textarea id="description" v-model="form.description" rows="4" class="form-textarea"></textarea>
                </div>



                <!-- Financial Information Section -->
                <div class="mt-6">
                    <h6 class="text-base font-semibold text-[#3b3f5c] dark:text-white-light mb-4 border-b border-[#e0e6ed] dark:border-[#1b2e4b] pb-2">
                        {{ $t('cases.financial_info') }}
                    </h6>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label for="service_type" class="block text-sm font-medium mb-1">{{ $t('cases.service_type') }}</label>
                            <select id="service_type" v-model="form.service_type" class="form-select">
                                <option v-for="opt in SERVICE_TYPE_OPTIONS" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label for="contract_number" class="block text-sm font-medium mb-1">{{ $t('cases.contract_number') }}</label>
                            <input id="contract_number" v-model="form.contract_number" type="text" class="form-input" maxlength="50" />
                        </div>
                        <div v-if="canViewFees">
                            <label for="fees" class="block text-sm font-medium mb-1">{{ $t('cases.fees') }}</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">$</span>
                                <input id="fees" v-model.number="form.fees" type="number" class="form-input pl-7" min="0" step="0.01" placeholder="0.00" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Companions Section -->
                <div class="mt-6">
                    <h6 class="font-semibold border-b border-gray-200 dark:border-gray-700 pb-2 mb-4">
                        {{ $t('cases.select_companions') }}
                        <span class="text-gray-500 font-normal text-sm ml-1">
                            ({{ selectedCompanionIds.length }}/{{ availableCompanions.length }})
                        </span>
                    </h6>
                    <p class="text-sm text-gray-500 mb-4">{{ $t('cases.select_companions_description') }}</p>

                    <!-- Loading skeleton -->
                    <div v-if="isLoadingCompanions" class="space-y-3">
                        <div v-for="i in 3" :key="i" class="animate-pulse flex items-center gap-4 p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <div class="w-12 h-12 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/3"></div>
                                <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-else-if="availableCompanions.length === 0" class="text-center py-6 text-gray-500">
                        <p class="text-sm">{{ $t('cases.no_companions') }}</p>
                    </div>

                    <!-- Companions list -->
                    <div v-else class="space-y-3">
                        <div v-for="companion in availableCompanions" :key="companion.id"
                             class="flex items-center gap-4 p-4 border rounded-lg cursor-pointer transition-colors"
                             :class="selectedCompanionIds.includes(companion.id)
                                 ? 'border-secondary bg-secondary/5 dark:bg-secondary/10'
                                 : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'"
                             @click="toggleCompanion(companion.id)">
                            <input type="checkbox"
                                   :checked="selectedCompanionIds.includes(companion.id)"
                                   class="form-checkbox text-secondary w-5 h-5"
                                   @click.stop="toggleCompanion(companion.id)" />
                            <div class="w-10 h-10 rounded-full bg-secondary/10 flex items-center justify-center shrink-0">
                                <span class="text-sm font-semibold text-secondary">
                                    {{ ((companion.first_name?.[0] || '') + (companion.last_name?.[0] || '')).toUpperCase() }}
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-medium truncate">{{ companion.full_name }}</div>
                                <div class="text-sm text-gray-500">
                                    {{ companion.relationship_label || companion.relationship }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Selected count info -->
                    <div v-if="selectedCompanionIds.length > 0" class="mt-3 p-3 bg-info/10 rounded-lg text-sm text-info">
                        {{ selectedCompanionIds.length }} {{ $t('cases.companions') }}
                    </div>
                </div>

                <!-- Lifecycle / Tasks Section -->
                <div class="mt-6">
                    <div class="flex items-center justify-between border-b border-[#e0e6ed] dark:border-[#1b2e4b] pb-2 mb-4">
                        <div class="flex items-center gap-4">
                            <button
                                type="button"
                                class="text-base font-semibold pb-1 border-b-2"
                                :class="activeBoardTab === 'board'
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-[#3b3f5c] dark:text-white-light'"
                                @click="activeBoardTab = 'board'"
                            >
                                {{ $t('cases.lifecycle_title') }}
                            </button>
                            <button
                                type="button"
                                class="text-base font-semibold pb-1 border-b-2 flex items-center gap-1"
                                :class="activeBoardTab === 'trash'
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-[#3b3f5c] dark:text-white-light'"
                                @click="activeBoardTab = 'trash'"
                            >
                                <icon-trash class="w-4 h-4" />
                                {{ $t('cases.task.trash_tab') }}
                            </button>
                        </div>
                    </div>
                    <TaskBoard
                        v-if="currentCase && activeBoardTab === 'board'"
                        ref="taskBoardRef"
                        :case-id="currentCase.id"
                        :stages="workflowStages.map(s => ({ id: s.id, name: s.name, sort_order: s.sort_order, color: stageColorById[s.id] }))"
                        :tasks="currentCase.tasks ?? []"
                        :editable="true"
                        @progress-updated="(p) => { form.progress = p }"
                        @delete-template-task-request="onDeleteTemplateTaskRequest"
                    />
                    <TrashTab
                        v-else-if="currentCase && activeBoardTab === 'trash'"
                        :case-id="currentCase.id"
                    />
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

        <!-- Todo Core delete confirmation -->
        <TodoCoreConfirmationModal
            :show="showTodoCoreDeleteModal"
            scenario="remove_from_existing"
            :tasks-affected="todoCoreDeleteTasksAffected"
            :consultant-name="todoCoreDeleteConsultantName"
            @confirm="onTodoCoreDeleteConfirm"
            @cancel="onTodoCoreDeleteCancel"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useMeta } from '@/composables/use-meta';
import { useCaseStore } from '@/stores/case';
import { useCompanionStore } from '@/stores/companion';
import companionService from '@/services/companionService';
import { useNotification } from '@/composables/useNotification';
import type { UpdateCaseData, ImportantDate, CaseTask, CaseStage, IrccStatus, FinalResult, ServiceType } from '@/types/case';
import { IRCC_STATUS_OPTIONS, FINAL_RESULT_OPTIONS, SERVICE_TYPE_OPTIONS } from '@/types/case';
import type { Companion } from '@/types/companion';
import DateManager from '@/components/DateManager.vue';
import TaskBoard from '@/components/cases/board/TaskBoard.vue';
import TrashTab from '@/components/cases/board/TrashTab.vue';
import TodoCoreConfirmationModal from '@/components/cases/TodoCoreConfirmationModal.vue';
import IconTrash from '@/components/icon/icon-trash.vue';
import type { WorkflowTask, CaseStage as WorkflowCaseStage } from '@/types/workflow';
import { useCaseStagesStore } from '@/stores/useCaseStagesStore';
import userService from '@/services/userService';
import { usePermissions } from '@/composables/usePermissions';
import type { StaffMember } from '@/types/wizard';

const staffMembers = ref<StaffMember[]>([]);
const isLoadingStaff = ref(false);
const staffError = ref(false);

const { can } = usePermissions();
const canViewFees = computed(() => can('cases.view-fees'));

// Icons
import IconFolder from '@/components/icon/icon-folder.vue';

useMeta({ title: 'Edit Case' });

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();
const caseStore = useCaseStore();
const companionStore = useCompanionStore();
const caseStagesStore = useCaseStagesStore();
const { success, error } = useNotification();

const availableCompanions = ref<Companion[]>([]);
const selectedCompanionIds = ref<number[]>([]);
const isLoadingCompanions = ref(false);

const languageOptions = computed(() => [
    { value: 'es', label: t('common.spanish') },
    { value: 'en', label: t('common.english') },
    { value: 'fr', label: t('common.french') },
]);

// Workflow stages derived from workflow_snapshot (standard cases) or
// from CaseStage records (old/ad-hoc cases that have no snapshot).
const workflowStages = computed(() => {
    void locale.value;
    const snapshot = currentCase.value?.workflow_snapshot;
    if (snapshot?.stages?.length) {
        return snapshot.stages
            .slice()
            .sort((a, b) => a.sort_order - b.sort_order)
            .map((s) => ({
                id: s.id,
                name: s.name ?? `Stage ${s.sort_order + 1}`,
                sort_order: s.sort_order,
            }));
    }
    // Fallback: ad-hoc / old cases — use CaseStage records loaded by caseStagesStore
    return caseStagesStore.orderedStages.map((s: WorkflowCaseStage) => ({
        id: s.id,
        name: s.name[locale.value] ?? s.name['es'] ?? s.name_resolved ?? s.code,
        sort_order: s.sort_order,
    }));
});

const stageColorById = computed<Record<number, string>>(() => {
    const map: Record<number, string> = {};
    const snapshot = currentCase.value?.workflow_snapshot;
    if (snapshot?.stages) {
        for (const s of snapshot.stages) {
            map[s.id] = (s as any).color ?? '#4361ee';
        }
    }
    return map;
});

// For snapshot cases the dropdown value is the WorkflowStage ID (current_stage_id).
// For ad-hoc/old cases it is the CaseStage instance ID (current_case_stage_id).
const hasWorkflowSnapshot = computed(() => !!(currentCase.value?.workflow_snapshot?.stages?.length));

const selectedStageId = computed<number | null>({
    get: () => hasWorkflowSnapshot.value ? form.current_stage_id : form.current_case_stage_id,
    set: (val: number | null) => {
        if (hasWorkflowSnapshot.value) {
            form.current_stage_id = val;
        } else {
            form.current_case_stage_id = val;
        }
    },
});

const activeBoardTab = ref<'board' | 'trash'>('board');

// Todo Core delete confirmation state
const taskBoardRef = ref<InstanceType<typeof TaskBoard> | null>(null);
const showTodoCoreDeleteModal = ref(false);
const pendingDeleteTask = ref<WorkflowTask | null>(null);
const pendingDeleteCascade = ref<string | undefined>(undefined);
const todoCoreDeleteTasksAffected = ref<{ id?: number; subject: string; assignedToName?: string; dueDate?: string }[]>([]);
const todoCoreDeleteConsultantName = ref<string>('');

function onDeleteTemplateTaskRequest(task: WorkflowTask) {
    pendingDeleteTask.value = task;
    pendingDeleteCascade.value = undefined;
    todoCoreDeleteTasksAffected.value = [{
        id: task.id,
        subject: task.subject,
        assignedToName: task.assignee?.name,
        dueDate: task.due_date ?? undefined,
    }];
    todoCoreDeleteConsultantName.value =
        task.assignee?.name ?? currentCase.value?.assigned_user?.name ?? '';
    showTodoCoreDeleteModal.value = true;
}

async function onTodoCoreDeleteConfirm(payload: { cascadeStrategy?: string }) {
    pendingDeleteCascade.value = payload.cascadeStrategy;
    showTodoCoreDeleteModal.value = false;
    if (pendingDeleteTask.value && taskBoardRef.value) {
        await taskBoardRef.value.performTemplateTaskDelete(
            pendingDeleteTask.value.id,
            payload.cascadeStrategy,
        );
        success(t('todo_core.success_removed'));
    }
    pendingDeleteTask.value = null;
    pendingDeleteCascade.value = undefined;
}

function onTodoCoreDeleteCancel() {
    showTodoCoreDeleteModal.value = false;
    pendingDeleteTask.value = null;
    pendingDeleteCascade.value = undefined;
}


const isLoading = ref(true);
const isSubmitting = ref(false);
const errors = reactive<Record<string, string>>({});

const form = reactive<UpdateCaseData & { important_dates: ImportantDate[]; tasks: CaseTask[]; current_stage_id: number | null; current_case_stage_id: number | null }>({
    status: 'active',
    priority: 'medium',
    progress: 0,
    language: 'es',
    description: '',
    important_dates: [],
    tasks: [] as CaseTask[],
    archive_box_number: '',
    closure_notes: '',
    assigned_to: null,
    companion_ids: [],
    stage: null as CaseStage | null,
    current_stage_id: null as number | null,
    current_case_stage_id: null as number | null,
    ircc_status: null as IrccStatus | null,
    final_result: null as FinalResult | null,
    ircc_code: '',
    contract_number: '',
    service_type: 'fee_based' as ServiceType,
    fees: null as number | null,
});

function toggleCompanion(id: number) {
    const idx = selectedCompanionIds.value.indexOf(id);
    if (idx === -1) selectedCompanionIds.value.push(id);
    else selectedCompanionIds.value.splice(idx, 1);
}

watch(selectedCompanionIds, (ids) => {
    form.companion_ids = [...ids];
}, { deep: true });


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
        const { tasks: _tasks, ...formWithoutTasks } = form;
        const payload: UpdateCaseData & { important_dates: ImportantDate[] } = {
            ...formWithoutTasks,
            ircc_code: form.ircc_code || null,
            contract_number: form.contract_number || null,
            fees: canViewFees.value ? form.fees : undefined,
        };
        await caseStore.updateCase(caseId, payload);
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
        // For old/ad-hoc cases without a workflow_snapshot, load CaseStage records
        // so the stage dropdown can show the stages created in the roadmap editor.
        if (!caseStore.currentCase?.workflow_snapshot) {
            await caseStagesStore.load(caseId);
        }
        if (currentCase.value) {
            // Populate form with current case data
            form.status = currentCase.value.status;
            form.priority = currentCase.value.priority;
            form.progress = currentCase.value.progress;
            form.language = currentCase.value.language;
            form.description = currentCase.value.description || '';
            form.important_dates = currentCase.value.important_dates?.map(d => ({
                id: d.id,
                label: d.label,
                due_date: d.due_date,
                sort_order: d.sort_order,
            })) ?? [];
            form.archive_box_number = currentCase.value.archive_box_number || '';
            form.closure_notes = currentCase.value.closure_notes || '';
            form.assigned_to = currentCase.value.assigned_to ?? null;
            form.stage = currentCase.value.stage;
            form.ircc_status = currentCase.value.ircc_status;
            form.final_result = currentCase.value.final_result;
            form.ircc_code = currentCase.value.ircc_code ?? '';
            form.contract_number = currentCase.value.contract_number ?? '';
            form.service_type = currentCase.value.service_type ?? 'fee_based';
            form.fees = currentCase.value.fees ?? null;
            form.current_stage_id = currentCase.value.current_stage_id ?? null;
            form.current_case_stage_id = currentCase.value.current_case_stage_id ?? null;
            form.tasks = [];

            // Load staff members (include current assignee even if inactive)
            isLoadingStaff.value = true;
            staffError.value = false;
            try {
                staffMembers.value = await userService.getStaff(currentCase.value.assigned_to ?? undefined);
            } catch (err) {
                staffError.value = true;
                console.error('Failed to load staff members:', err);
            } finally {
                isLoadingStaff.value = false;
            }

            // Load companions from the case
            if (currentCase.value.companions) {
                selectedCompanionIds.value = currentCase.value.companions.map((c: any) => c.id);
            }
            // Load available companions to add to the case.
            // Spec 69 — Company cases: only show direct family of the beneficiary employee
            //   (parent_companion_id === primary_applicant_companion_id).
            // Person cases: show all companions of the client (legacy behaviour).
            if (currentCase.value.client_id) {
                isLoadingCompanions.value = true;
                try {
                    const isCompany = currentCase.value.client?.type === 'company';
                    const employeeId = currentCase.value.primary_applicant_companion_id;
                    if (isCompany && employeeId) {
                        availableCompanions.value = await companionService.getFamilyMembers(employeeId);
                    } else {
                        await companionStore.fetchCompanions(currentCase.value.client_id);
                        availableCompanions.value = companionStore.companions;
                    }
                } finally {
                    isLoadingCompanions.value = false;
                }
            }
        }
    } catch (err) {
        error(t('cases.failed_to_load'));
    } finally {
        isLoading.value = false;
    }
});
</script>
