/**
 * Case Task Service (Workflow Tasks board operations)
 *
 * Endpoints (Spec 47):
 *   POST   /cases/{case}/workflow-tasks
 *   PATCH  /cases/{case}/workflow-tasks/{task}/move
 *   DELETE /cases/{case}/workflow-tasks/{task}
 *   POST   /cases/{case}/workflow-tasks/{taskId}/restore
 *   GET    /cases/{case}/workflow-tasks/trashed
 */

import api from './api';
import type { WorkflowTask, TaskType, TaskPriority, UpdateWorkflowTaskCorePayload } from '@/types/workflow';

export interface MoveWorkflowTaskPayload {
    source_stage_id: number;
    target_stage_id: number;
    target_index: number;
}

export interface CreateWorkflowTaskPayload {
    case_stage_id: number;
    subject: string;
    description?: string | null;
    type: TaskType;
    priority: TaskPriority;
    due_date?: string | null;
    assigned_to?: number | null;
}

const caseTaskService = {
    async moveWorkflowTask(caseId: number, taskId: number, payload: MoveWorkflowTaskPayload): Promise<WorkflowTask> {
        const response = await api.patch<{ data: WorkflowTask }>(
            `/cases/${caseId}/workflow-tasks/${taskId}/move`,
            payload,
        );
        return response.data.data;
    },

    async createWorkflowTask(caseId: number, payload: CreateWorkflowTaskPayload): Promise<WorkflowTask> {
        const response = await api.post<{ data: WorkflowTask }>(
            `/cases/${caseId}/workflow-tasks`,
            payload,
        );
        return response.data.data;
    },

    async deleteWorkflowTask(caseId: number, taskId: number): Promise<void> {
        await api.delete(`/cases/${caseId}/workflow-tasks/${taskId}`);
    },

    async restoreWorkflowTask(caseId: number, taskId: number): Promise<WorkflowTask> {
        const response = await api.post<{ data: WorkflowTask }>(
            `/cases/${caseId}/workflow-tasks/${taskId}/restore`,
        );
        return response.data.data;
    },

    async listTrashedTasks(caseId: number): Promise<WorkflowTask[]> {
        const response = await api.get<{ data: WorkflowTask[] }>(
            `/cases/${caseId}/workflow-tasks/trashed`,
        );
        return response.data.data;
    },

    async updateWorkflowTaskCore(
        caseId: number,
        taskId: number,
        payload: UpdateWorkflowTaskCorePayload,
    ): Promise<WorkflowTask> {
        const response = await api.patch<{ data: WorkflowTask }>(
            `/cases/${caseId}/workflow-tasks/${taskId}/update-core`,
            payload,
        );
        return response.data.data;
    },

    async cloneWorkflowTask(
        caseId: number,
        taskId: number,
    ): Promise<{ task: WorkflowTask; cloned_from_task_id: number }> {
        const response = await api.post<{ data: WorkflowTask; meta: { cloned_from_task_id: number } }>(
            `/cases/${caseId}/workflow-tasks/${taskId}/clone`,
        );
        return {
            task: response.data.data,
            cloned_from_task_id: response.data.meta.cloned_from_task_id,
        };
    },
};

export default caseTaskService;
