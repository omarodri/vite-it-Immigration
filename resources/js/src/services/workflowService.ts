import api from './api';
import type { WorkflowStage, TaskTemplate, TranslationsByField, TaskType, TaskPriority } from '@/types/workflow';

export interface StagePayload {
    code: string;
    sort_order?: number;
    is_active?: boolean;
    is_terminal?: boolean;
    color?: string;
    translations: TranslationsByField;
}

export interface TemplatePayload {
    code: string;
    sort_order?: number;
    is_required?: boolean;
    blocks_stage_completion?: boolean;
    default_type?: TaskType;
    default_priority?: TaskPriority;
    due_offset_days?: number | null;
    is_active?: boolean;
    translations: TranslationsByField;
}

export const workflowService = {
    // Stages
    listStages: (caseTypeId: number) =>
        api.get<{ data: WorkflowStage[] }>(`/admin/workflow/case-types/${caseTypeId}/stages`),

    createStage: (caseTypeId: number, payload: StagePayload) =>
        api.post<{ data: WorkflowStage }>(`/admin/workflow/case-types/${caseTypeId}/stages`, payload),

    updateStage: (id: number, payload: Partial<StagePayload>) =>
        api.put<{ data: WorkflowStage }>(`/admin/workflow/stages/${id}`, payload),

    deleteStage: (id: number) =>
        api.delete(`/admin/workflow/stages/${id}`),

    reorderStages: (caseTypeId: number, orderedIds: number[]) =>
        api.patch(`/admin/workflow/case-types/${caseTypeId}/stages/reorder`, { ordered_ids: orderedIds }),

    // Templates
    listTemplates: (stageId: number) =>
        api.get<{ data: TaskTemplate[] }>(`/admin/workflow/stages/${stageId}/templates`),

    createTemplate: (stageId: number, payload: TemplatePayload) =>
        api.post<{ data: TaskTemplate }>(`/admin/workflow/stages/${stageId}/templates`, payload),

    updateTemplate: (id: number, payload: Partial<TemplatePayload>) =>
        api.put<{ data: TaskTemplate }>(`/admin/workflow/templates/${id}`, payload),

    deleteTemplate: (id: number) =>
        api.delete(`/admin/workflow/templates/${id}`),

    reorderTemplates: (stageId: number, orderedIds: number[]) =>
        api.patch(`/admin/workflow/stages/${stageId}/templates/reorder`, { ordered_ids: orderedIds }),

    // Wizard preview
    workflowPreview: (caseTypeId: number) =>
        api.get<{ data: WorkflowStage[] }>(`/cases/workflow-preview/${caseTypeId}`),

    // Case operations
    advanceStage: (caseId: number) =>
        api.patch(`/cases/${caseId}/advance-stage`),
};
