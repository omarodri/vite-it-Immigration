<template>
    <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">
            {{ $t('case_tasks.step_title') }}
        </h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
            {{ $t('case_tasks.step_subtitle') }}
        </p>

        <!-- Loading -->
        <div v-if="isLoading" class="panel">
            <div class="animate-pulse space-y-3">
                <div v-for="n in 3" :key="n" class="h-16 bg-gray-200 dark:bg-gray-700 rounded"></div>
            </div>
        </div>

        <!-- No workflow defined -->
        <div v-else-if="!hasWorkflow" class="panel border-warning border bg-warning/5">
            <div class="flex items-start gap-3">
                <svg class="w-6 h-6 text-warning shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z" />
                </svg>
                <div class="flex-1">
                    <h4 class="font-semibold text-sm mb-1">{{ $t('workflow.no_workflow_defined') }}</h4>
                    <p class="text-xs text-gray-500 mb-2">{{ $t('workflow.no_workflow_help') }}</p>
                    <router-link
                        v-if="canViewWorkflows && caseTypeId"
                        :to="`/admin/workflows/${caseTypeId}`"
                        class="text-primary text-xs hover:underline"
                    >
                        {{ $t('workflow.configure_workflow') }} &rarr;
                    </router-link>
                </div>
            </div>
        </div>

        <!-- Workflow stages with their templates -->
        <div v-else class="space-y-4">
            <div
                v-for="stage in stages"
                :key="stage.id"
                class="panel"
            >
                <div class="flex items-center gap-3 mb-3 pb-3 border-b border-[#e0e6ed] dark:border-[#1b2e4b]">
                    <span
                        class="w-7 h-7 rounded-full text-xs flex items-center justify-center font-semibold shrink-0"
                        :class="`bg-${stage.color || 'primary'} text-white`"
                    >
                        {{ stage.sort_order + 1 }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-semibold text-sm dark:text-white-light">
                            {{ getTranslated(stage.translations, 'name') }}
                        </h4>
                        <p v-if="getTranslated(stage.translations, 'description')" class="text-xs text-gray-500">
                            {{ getTranslated(stage.translations, 'description') }}
                        </p>
                    </div>
                    <span class="text-xs text-gray-400">
                        {{ activeTemplatesIn(stage) }}/{{ stage.task_templates?.length ?? 0 }} {{ $t('case_tasks.tasks_short') }}
                    </span>
                </div>

                <div v-if="!stage.task_templates?.length" class="text-xs text-gray-400 italic">
                    {{ $t('workflow.no_templates_in_stage') }}
                </div>

                <div v-else class="space-y-2">
                    <label
                        v-for="tmpl in stage.task_templates"
                        :key="tmpl.id"
                        class="flex items-start gap-3 p-2 rounded transition"
                        :class="tmpl.is_required
                            ? 'bg-gray-50 dark:bg-gray-900 cursor-not-allowed'
                            : 'hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer'"
                    >
                        <input
                            type="checkbox"
                            class="form-checkbox mt-0.5"
                            :checked="!isExcluded(tmpl.id)"
                            :disabled="tmpl.is_required"
                            @change="toggleTemplate(tmpl.id)"
                        />
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm">{{ getTranslated(tmpl.translations, 'name') }}</span>
                                <span v-if="tmpl.is_required" class="badge badge-outline-danger text-[10px]">
                                    {{ $t('workflow.required_short') }}
                                </span>
                                <span v-if="tmpl.due_offset_days != null" class="text-xs text-gray-400">
                                    +{{ tmpl.due_offset_days }}d
                                </span>
                            </div>
                            <p v-if="getTranslated(tmpl.translations, 'description')" class="text-xs text-gray-500 mt-0.5">
                                {{ getTranslated(tmpl.translations, 'description') }}
                            </p>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <p class="text-xs text-gray-400 mt-4 text-center">
            <svg class="w-3 h-3 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {{ $t('case_tasks.optional_step') }}
        </p>
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, watch, inject } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { workflowService } from '@/services/workflowService';
import type { WorkflowStage, TranslationsByField } from '@/types/workflow';

const wizard = inject<ReturnType<typeof import('@/composables/useCaseWizard').useCaseWizard>>('wizard')!;

const { locale: i18nLocale } = useI18n();
const authStore = useAuthStore();

const stages = ref<WorkflowStage[]>([]);
const isLoading = ref(false);

const caseTypeId = computed<number | null>(() => wizard.state.caseTypeId);
const caseLanguage = computed<string>(() => wizard.state.caseDetails.language || (i18nLocale.value as string));
const hasWorkflow = computed(() => stages.value.length > 0);
const canViewWorkflows = computed(() => authStore.hasPermission('workflows.view'));

function getTranslated(translations: TranslationsByField | undefined, field: string): string {
    if (!translations?.[field]) return '';
    const loc = caseLanguage.value as 'es' | 'en' | 'fr';
    return translations[field][loc] ?? translations[field].es ?? Object.values(translations[field])[0] ?? '';
}

function isExcluded(templateId: number): boolean {
    return wizard.state.excludedTemplateIds.includes(templateId);
}

function toggleTemplate(templateId: number) {
    if (isExcluded(templateId)) {
        wizard.state.excludedTemplateIds = wizard.state.excludedTemplateIds.filter(id => id !== templateId);
    } else {
        wizard.state.excludedTemplateIds = [...wizard.state.excludedTemplateIds, templateId];
    }
}

function activeTemplatesIn(stage: WorkflowStage): number {
    if (!stage.task_templates) return 0;
    return stage.task_templates.filter(t => !isExcluded(t.id)).length;
}

async function loadWorkflow(typeId: number) {
    isLoading.value = true;
    try {
        const { data } = await workflowService.workflowPreview(typeId);
        stages.value = data.data;
        // Reset excluded when changing case type; required ones are forced included
        wizard.state.excludedTemplateIds = [];
    } catch (err: any) {
        // Don't swallow auth errors — they were already handled by the axios interceptor.
        // For any other failure, just leave the stages empty so the wizard can still submit.
        if (err?.response?.status && err.response.status !== 401) {
            console.warn('[StepChecklist] workflow-preview failed:', err.response.status, err.response.data);
        }
        stages.value = [];
    } finally {
        isLoading.value = false;
    }
}

watch(caseTypeId, (id) => {
    if (id) loadWorkflow(id);
}, { immediate: true });
</script>
