/**
 * Case Stage Service (Spec 48 — Etapas Personalizadas por Expediente)
 *
 * Endpoints:
 *   GET    /cases/{case}/stages
 *   POST   /cases/{case}/stages
 *   PATCH  /cases/{case}/stages/reorder
 *   PATCH  /cases/{case}/stages/{stage}
 *   DELETE /cases/{case}/stages/{stage}
 */

import api from './api';
import type {
    CaseStage,
    CreateCaseStagePayload,
    UpdateCaseStagePayload,
    DeleteCaseStageBody,
} from '@/types/workflow';

const caseStageService = {
    async listStages(caseId: number): Promise<CaseStage[]> {
        const response = await api.get<{ data: CaseStage[] }>(`/cases/${caseId}/stages`);
        return response.data.data;
    },

    async createStage(caseId: number, payload: CreateCaseStagePayload): Promise<CaseStage> {
        const response = await api.post<{ data: CaseStage }>(`/cases/${caseId}/stages`, payload);
        return response.data.data;
    },

    async updateStage(caseId: number, stageId: number, payload: UpdateCaseStagePayload): Promise<CaseStage> {
        const response = await api.patch<{ data: CaseStage }>(
            `/cases/${caseId}/stages/${stageId}`,
            payload,
        );
        return response.data.data;
    },

    async reorderStages(caseId: number, stageIds: number[]): Promise<CaseStage[]> {
        const response = await api.patch<{ data: CaseStage[] }>(
            `/cases/${caseId}/stages/reorder`,
            { stage_ids: stageIds },
        );
        return response.data.data;
    },

    async deleteStage(caseId: number, stageId: number, body: DeleteCaseStageBody): Promise<void> {
        await api.delete(`/cases/${caseId}/stages/${stageId}`, { data: body });
    },
};

export default caseStageService;
