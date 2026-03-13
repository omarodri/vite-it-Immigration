/**
 * Case Service
 * Handles all case-related API calls
 */

import api from './api';
import type {
    ImmigrationCase,
    CaseType,
    CreateCaseData,
    UpdateCaseData,
    CaseFilters,
    CaseStatistics,
    CaseActivityLog,
    CaseResponse,
    CaseDeleteResponse,
} from '@/types/case';
import type { PaginatedResponse } from '@/types/pagination';

const caseService = {
    // ===============================
    // CASE TYPES
    // ===============================

    /**
     * Get all active case types
     */
    async getCaseTypes(): Promise<CaseType[]> {
        const response = await api.get<{ data: CaseType[] }>('/case-types');
        return response.data.data;
    },

    /**
     * Get case type by ID
     */
    async getCaseType(id: number): Promise<CaseType> {
        const response = await api.get<{ data: CaseType }>(`/case-types/${id}`);
        return response.data.data;
    },

    // ===============================
    // CASES - CRUD
    // ===============================

    /**
     * Get paginated list of cases with filters
     */
    async getCases(filters: CaseFilters = {}): Promise<PaginatedResponse<ImmigrationCase>> {
        const params = new URLSearchParams();

        if (filters.search) params.append('search', filters.search);
        if (filters.status) params.append('status', filters.status);
        if (filters.priority) params.append('priority', filters.priority);
        if (filters.case_type_id) params.append('case_type_id', filters.case_type_id.toString());
        if (filters.assigned_to) params.append('assigned_to', filters.assigned_to.toString());
        if (filters.client_id) params.append('client_id', filters.client_id.toString());
        if (filters.date_from) params.append('date_from', filters.date_from);
        if (filters.date_to) params.append('date_to', filters.date_to);
        if (filters.sort_by) params.append('sort_by', filters.sort_by);
        if (filters.sort_direction) params.append('sort_direction', filters.sort_direction);
        if (filters.per_page) params.append('per_page', filters.per_page.toString());
        if (filters.page) params.append('page', filters.page.toString());

        const response = await api.get<PaginatedResponse<ImmigrationCase>>(`/cases?${params.toString()}`);
        return response.data;
    },

    /**
     * Get single case by ID with relations
     */
    async getCase(id: number): Promise<ImmigrationCase> {
        const response = await api.get<{ data: ImmigrationCase }>(`/cases/${id}`);
        return response.data.data;
    },

    /**
     * Create a new case
     */
    async createCase(data: CreateCaseData): Promise<CaseResponse> {
        const response = await api.post<CaseResponse>('/cases', data);
        return response.data;
    },

    /**
     * Update an existing case
     */
    async updateCase(id: number, data: UpdateCaseData): Promise<CaseResponse> {
        const response = await api.put<CaseResponse>(`/cases/${id}`, data);
        return response.data;
    },

    /**
     * Delete (soft delete) a case
     */
    async deleteCase(id: number): Promise<CaseDeleteResponse> {
        const response = await api.delete<CaseDeleteResponse>(`/cases/${id}`);
        return response.data;
    },

    // ===============================
    // CASES - ACTIONS
    // ===============================

    /**
     * Assign case to a user
     */
    async assignCase(id: number, userId: number): Promise<CaseResponse> {
        const response = await api.post<CaseResponse>(`/cases/${id}/assign`, {
            assigned_to: userId,
        });
        return response.data;
    },

    /**
     * Get case activity timeline
     */
    async getTimeline(id: number): Promise<CaseActivityLog[]> {
        const response = await api.get<{ data: CaseActivityLog[] }>(`/cases/${id}/timeline`);
        return response.data.data;
    },

    /**
     * Get case statistics for dashboard
     */
    async getStatistics(): Promise<CaseStatistics> {
        const response = await api.get<{ data: CaseStatistics }>('/cases/statistics');
        return response.data.data;
    },
};

export default caseService;
