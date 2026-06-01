/**
 * Case Type Service (Admin)
 * CRUD + cascade clone for case types (Spec 70).
 *
 * The Laravel API wraps resources in a `data` envelope (Resource / ResourceCollection).
 * These methods unwrap it so views receive plain `CaseType` / `CaseType[]`.
 */

import api from '@/services/api';
import type { CaseType, CaseTypeForm } from '@/types';

const caseTypeService = {
    async list(): Promise<CaseType[]> {
        const response = await api.get<{ data: CaseType[] }>('/admin/case-types');
        return response.data.data;
    },

    async get(id: number): Promise<CaseType> {
        const response = await api.get<{ data: CaseType }>(`/admin/case-types/${id}`);
        return response.data.data;
    },

    async create(data: CaseTypeForm): Promise<CaseType> {
        const response = await api.post<{ data: CaseType }>('/admin/case-types', data);
        return response.data.data;
    },

    async update(id: number, data: Partial<CaseTypeForm>): Promise<CaseType> {
        const response = await api.put<{ data: CaseType }>(`/admin/case-types/${id}`, data);
        return response.data.data;
    },

    async remove(id: number): Promise<void> {
        await api.delete(`/admin/case-types/${id}`);
    },

    async clone(id: number): Promise<CaseType> {
        const response = await api.post<{ data: CaseType }>(`/admin/case-types/${id}/clone`);
        return response.data.data;
    },

    /**
     * Informative count of active cases for the pre-delete warning (Spec 70 D7).
     * Loaded on demand just before showing the delete modal — never blocks deletion.
     */
    async getActiveCasesCount(id: number): Promise<number> {
        const response = await api.get<{ count: number }>(`/admin/case-types/${id}/active-cases-count`);
        return response.data.count;
    },
};

export default caseTypeService;
