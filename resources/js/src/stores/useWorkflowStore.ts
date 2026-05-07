import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { WorkflowStage } from '@/types/workflow';
import { workflowService, type StagePayload, type TemplatePayload } from '@/services/workflowService';

export const useWorkflowStore = defineStore('workflow', () => {
    const stages = ref<WorkflowStage[]>([]);
    const isLoading = ref(false);
    const currentCaseTypeId = ref<number | null>(null);
    const selectedStageId = ref<number | null>(null);

    async function loadStages(caseTypeId: number) {
        isLoading.value = true;
        try {
            currentCaseTypeId.value = caseTypeId;
            const { data } = await workflowService.listStages(caseTypeId);
            stages.value = data.data;
            if (stages.value.length && !stages.value.find(s => s.id === selectedStageId.value)) {
                selectedStageId.value = stages.value[0].id;
            }
        } finally {
            isLoading.value = false;
        }
    }

    function selectStage(id: number | null) {
        selectedStageId.value = id;
    }

    async function createStage(payload: StagePayload) {
        if (!currentCaseTypeId.value) return;
        const { data } = await workflowService.createStage(currentCaseTypeId.value, payload);
        stages.value.push(data.data);
        selectedStageId.value = data.data.id;
    }

    async function updateStage(id: number, payload: Partial<StagePayload>) {
        const { data } = await workflowService.updateStage(id, payload);
        const idx = stages.value.findIndex(s => s.id === id);
        if (idx >= 0) {
            stages.value[idx] = { ...stages.value[idx], ...data.data };
        }
    }

    async function deleteStage(id: number) {
        await workflowService.deleteStage(id);
        stages.value = stages.value.filter(s => s.id !== id);
        if (selectedStageId.value === id) {
            selectedStageId.value = stages.value[0]?.id ?? null;
        }
    }

    async function reorderStages(orderedIds: number[]) {
        if (!currentCaseTypeId.value) return;
        await workflowService.reorderStages(currentCaseTypeId.value, orderedIds);
        stages.value.sort((a, b) => orderedIds.indexOf(a.id) - orderedIds.indexOf(b.id));
        stages.value.forEach((s, i) => (s.sort_order = i));
    }

    async function createTemplate(stageId: number, payload: TemplatePayload) {
        const { data } = await workflowService.createTemplate(stageId, payload);
        const stage = stages.value.find(s => s.id === stageId);
        if (stage) {
            stage.task_templates = [...(stage.task_templates ?? []), data.data];
            stage.task_templates_count = (stage.task_templates_count ?? 0) + 1;
        }
    }

    async function updateTemplate(stageId: number, id: number, payload: Partial<TemplatePayload>) {
        const { data } = await workflowService.updateTemplate(id, payload);
        const stage = stages.value.find(s => s.id === stageId);
        if (stage?.task_templates) {
            const idx = stage.task_templates.findIndex(t => t.id === id);
            if (idx >= 0) stage.task_templates[idx] = data.data;
        }
    }

    async function deleteTemplate(stageId: number, id: number) {
        await workflowService.deleteTemplate(id);
        const stage = stages.value.find(s => s.id === stageId);
        if (stage?.task_templates) {
            stage.task_templates = stage.task_templates.filter(t => t.id !== id);
            stage.task_templates_count = Math.max(0, (stage.task_templates_count ?? 1) - 1);
        }
    }

    async function reorderTemplates(stageId: number, orderedIds: number[]) {
        await workflowService.reorderTemplates(stageId, orderedIds);
        const stage = stages.value.find(s => s.id === stageId);
        if (stage?.task_templates) {
            stage.task_templates.sort((a, b) => orderedIds.indexOf(a.id) - orderedIds.indexOf(b.id));
            stage.task_templates.forEach((t, i) => (t.sort_order = i));
        }
    }

    function reset() {
        stages.value = [];
        currentCaseTypeId.value = null;
        selectedStageId.value = null;
    }

    return {
        stages,
        isLoading,
        currentCaseTypeId,
        selectedStageId,
        loadStages,
        selectStage,
        createStage,
        updateStage,
        deleteStage,
        reorderStages,
        createTemplate,
        updateTemplate,
        deleteTemplate,
        reorderTemplates,
        reset,
    };
});
