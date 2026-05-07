<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/admin/workflows" class="text-primary hover:underline">{{ $t('workflow.title') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ caseType?.name ?? '...' }}</span>
            </li>
        </ul>

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-semibold dark:text-white-light">
                    {{ caseType?.name ?? $t('common.loading') }}
                </h1>
                <p v-if="caseType" class="text-sm text-gray-500 mt-1">
                    {{ $t(`case_types.${caseType.category}`) }} - {{ caseType.code }}
                </p>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="store.isLoading" class="panel">
            <div class="animate-pulse space-y-3">
                <div class="h-12 bg-gray-200 dark:bg-gray-700 rounded"></div>
                <div class="h-12 bg-gray-200 dark:bg-gray-700 rounded"></div>
            </div>
        </div>

        <!-- Builder: 2 columns (stages | templates of selected stage) -->
        <div v-else class="grid grid-cols-1 lg:grid-cols-5 gap-4">
            <!-- Stages column -->
            <div class="panel lg:col-span-2">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold">
                        {{ $t('workflow.stages') }}
                        <span class="badge badge-outline-primary ml-2">{{ store.stages.length }}</span>
                    </h3>
                    <button
                        v-can="'workflows.create'"
                        type="button"
                        class="btn btn-primary btn-sm"
                        @click="openStageModal(null)"
                    >
                        + {{ $t('workflow.add_stage') }}
                    </button>
                </div>

                <div v-if="store.stages.length === 0" class="text-center py-8 text-gray-400 text-sm">
                    {{ $t('workflow.no_stages_yet') }}
                </div>

                <VueDraggable
                    v-else
                    v-model="store.stages"
                    :animation="150"
                    handle=".stage-drag-handle"
                    @end="onStagesReorder"
                    class="space-y-2"
                >
                    <div
                        v-for="stage in store.stages"
                        :key="stage.id"
                        class="border border-[#e0e6ed] dark:border-[#1b2e4b] rounded p-3 transition cursor-pointer"
                        :class="store.selectedStageId === stage.id
                            ? 'border-primary bg-primary/5'
                            : 'hover:border-primary/50 bg-white dark:bg-gray-900'"
                        @click="store.selectStage(stage.id)"
                    >
                        <div class="flex items-center gap-2">
                            <span class="stage-drag-handle cursor-grab text-gray-400 shrink-0" @click.stop>
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM8 12a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM8 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM14 6a2 2 0 1 1 4 0 2 2 0 0 1-4 0zM14 12a2 2 0 1 1 4 0 2 2 0 0 1-4 0zM14 18a2 2 0 1 1 4 0 2 2 0 0 1-4 0z"/>
                                </svg>
                            </span>
                            <span
                                class="w-7 h-7 rounded-full text-xs flex items-center justify-center font-semibold shrink-0"
                                :class="`bg-${stage.color || 'primary'} text-white`"
                            >
                                {{ stage.sort_order + 1 }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-sm truncate">{{ getName(stage.translations) }}</div>
                                <div class="text-xs text-gray-400">{{ stage.code }}</div>
                            </div>
                            <span v-if="stage.is_terminal" class="badge badge-outline-success text-xs shrink-0">
                                {{ $t('workflow.terminal_short') }}
                            </span>
                            <span v-if="!stage.is_active" class="badge badge-outline-warning text-xs shrink-0">
                                {{ $t('workflow.inactive_short') }}
                            </span>
                            <span class="badge badge-outline-secondary text-xs shrink-0">
                                {{ stage.task_templates?.length ?? 0 }}
                            </span>
                            <button
                                v-can="'workflows.update'"
                                type="button"
                                class="text-primary hover:text-primary/80"
                                @click.stop="openStageModal(stage)"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                            <button
                                v-can="'workflows.delete'"
                                type="button"
                                class="text-danger hover:text-danger/80"
                                @click.stop="confirmDeleteStage(stage)"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </VueDraggable>
            </div>

            <!-- Templates column -->
            <div class="panel lg:col-span-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold">
                        {{ $t('workflow.task_templates') }}
                        <span v-if="selectedStage" class="text-sm text-gray-500 ml-2">- {{ getName(selectedStage.translations) }}</span>
                    </h3>
                    <button
                        v-can="'workflows.create'"
                        type="button"
                        class="btn btn-primary btn-sm"
                        :disabled="!selectedStage"
                        @click="openTemplateModal(null)"
                    >
                        + {{ $t('workflow.add_task_template') }}
                    </button>
                </div>

                <div v-if="!selectedStage" class="text-center py-12 text-gray-400 text-sm">
                    {{ $t('workflow.select_stage_to_view_templates') }}
                </div>
                <div v-else-if="(selectedStage.task_templates?.length ?? 0) === 0" class="text-center py-8 text-gray-400 text-sm">
                    {{ $t('workflow.no_templates_yet') }}
                </div>
                <VueDraggable
                    v-else
                    :model-value="selectedStage.task_templates ?? []"
                    @update:model-value="(v: any) => updateLocalTemplates(v)"
                    :animation="150"
                    handle=".template-drag-handle"
                    @end="onTemplatesReorder"
                    class="space-y-2"
                >
                    <div
                        v-for="tmpl in selectedStage.task_templates"
                        :key="tmpl.id"
                        class="border border-[#e0e6ed] dark:border-[#1b2e4b] rounded p-3 bg-white dark:bg-gray-900"
                    >
                        <div class="flex items-center gap-2">
                            <span class="template-drag-handle cursor-grab text-gray-400 shrink-0">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM8 12a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM8 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM14 6a2 2 0 1 1 4 0 2 2 0 0 1-4 0zM14 12a2 2 0 1 1 4 0 2 2 0 0 1-4 0zM14 18a2 2 0 1 1 4 0 2 2 0 0 1-4 0z"/>
                                </svg>
                            </span>
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-sm truncate">{{ getName(tmpl.translations) }}</div>
                                <div class="text-xs text-gray-400 flex items-center gap-2">
                                    <span>{{ tmpl.code }}</span>
                                    <span v-if="tmpl.due_offset_days != null">+{{ tmpl.due_offset_days }}d</span>
                                </div>
                            </div>
                            <span :class="`badge badge-outline-${priorityColor(tmpl.default_priority)} text-xs shrink-0`">
                                {{ $t(`tasks.priority.${tmpl.default_priority}`) }}
                            </span>
                            <span v-if="tmpl.is_required" class="badge badge-outline-danger text-xs shrink-0">
                                {{ $t('workflow.required_short') }}
                            </span>
                            <span v-if="!tmpl.is_active" class="badge badge-outline-warning text-xs shrink-0">
                                {{ $t('workflow.inactive_short') }}
                            </span>
                            <button
                                v-can="'workflows.update'"
                                type="button"
                                class="text-primary hover:text-primary/80"
                                @click="openTemplateModal(tmpl)"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                            <button
                                v-can="'workflows.delete'"
                                type="button"
                                class="text-danger hover:text-danger/80"
                                @click="confirmDeleteTemplate(tmpl)"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </VueDraggable>
            </div>
        </div>

        <!-- Modals -->
        <StageEditorModal
            :show="stageModalShow"
            :stage="editingStage"
            @close="stageModalShow = false"
            @saved="onStageSaved"
        />
        <TaskTemplateEditorModal
            v-if="selectedStage"
            :show="templateModalShow"
            :stage-id="selectedStage.id"
            :template="editingTemplate"
            @close="templateModalShow = false"
            @saved="onTemplateSaved"
        />
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { VueDraggable } from 'vue-draggable-plus';
import { useMeta } from '@/composables/use-meta';
import { useNotification } from '@/composables/useNotification';
import { useWorkflowStore } from '@/stores/useWorkflowStore';
import caseService from '@/services/caseService';
import StageEditorModal from '@/components/admin/StageEditorModal.vue';
import TaskTemplateEditorModal from '@/components/admin/TaskTemplateEditorModal.vue';
import type { CaseType } from '@/types/case';
import type { TranslationsByField, WorkflowStage, TaskTemplate, TaskPriority } from '@/types/workflow';

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();
const store = useWorkflowStore();
const notification = useNotification();

useMeta({ title: t('workflow.title') });

const caseType = ref<CaseType | null>(null);
const stageModalShow = ref(false);
const templateModalShow = ref(false);
const editingStage = ref<WorkflowStage | null>(null);
const editingTemplate = ref<TaskTemplate | null>(null);

const caseTypeId = computed(() => Number(route.params.caseTypeId));

const selectedStage = computed(() =>
    store.stages.find(s => s.id === store.selectedStageId) ?? null
);

function getName(translations: TranslationsByField | undefined): string {
    if (!translations?.name) return '';
    const loc = locale.value as 'es' | 'en' | 'fr';
    return translations.name[loc] ?? translations.name.es ?? Object.values(translations.name)[0] ?? '';
}

function priorityColor(p: TaskPriority): string {
    return ({ urgent: 'danger', high: 'warning', medium: 'info', low: 'secondary' } as const)[p] ?? 'secondary';
}

function openStageModal(stage: WorkflowStage | null) {
    editingStage.value = stage;
    stageModalShow.value = true;
}

function openTemplateModal(tmpl: TaskTemplate | null) {
    editingTemplate.value = tmpl;
    templateModalShow.value = true;
}

async function onStagesReorder() {
    const ids = store.stages.map(s => s.id);
    try {
        await store.reorderStages(ids);
    } catch {
        notification.error('Error al reordenar etapas');
    }
}

function updateLocalTemplates(value: TaskTemplate[]) {
    if (selectedStage.value) {
        selectedStage.value.task_templates = value;
    }
}

async function onTemplatesReorder() {
    if (!selectedStage.value?.task_templates) return;
    const ids = selectedStage.value.task_templates.map(t => t.id);
    try {
        await store.reorderTemplates(selectedStage.value.id, ids);
    } catch {
        notification.error('Error al reordenar plantillas');
    }
}

async function confirmDeleteStage(stage: WorkflowStage) {
    const ok = await notification.confirm({
        title: t('workflow.confirm_delete_stage'),
        icon: 'warning',
    });
    if (!ok) return;
    try {
        await store.deleteStage(stage.id);
        notification.success('Etapa eliminada');
    } catch (e: any) {
        notification.error(e?.response?.data?.message || 'Error al eliminar');
    }
}

async function confirmDeleteTemplate(tmpl: TaskTemplate) {
    const ok = await notification.confirm({
        title: t('workflow.confirm_delete_template'),
        icon: 'warning',
    });
    if (!ok || !selectedStage.value) return;
    try {
        await store.deleteTemplate(selectedStage.value.id, tmpl.id);
        notification.success('Tarea eliminada');
    } catch (e: any) {
        notification.error(e?.response?.data?.message || 'Error al eliminar');
    }
}

async function onStageSaved() {
    await store.loadStages(caseTypeId.value);
}

async function onTemplateSaved() {
    await store.loadStages(caseTypeId.value);
}

watch(caseTypeId, async (id) => {
    if (id) {
        await loadCaseType();
        await store.loadStages(id);
    }
}, { immediate: false });

async function loadCaseType() {
    try {
        caseType.value = await caseService.getCaseType(caseTypeId.value);
    } catch {
        router.push('/admin/workflows');
    }
}

onMounted(async () => {
    if (!caseTypeId.value) {
        router.push('/admin/workflows');
        return;
    }
    await loadCaseType();
    await store.loadStages(caseTypeId.value);
});
</script>
