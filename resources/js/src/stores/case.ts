/**
 * Case Store
 * Manages case list state, pagination, and CRUD operations
 */

import { defineStore } from 'pinia';
import caseService from '@/services/caseService';
import type {
    ImmigrationCase,
    CaseType,
    CaseTask,
    CreateCaseData,
    UpdateCaseData,
    CaseFilters,
    CaseStatistics,
    CaseActivityLog,
    CaseStatus,
    CasePriority,
    CaseTypeCategory,
} from '@/types/case';
import type { PaginationMeta, PaginationLinks } from '@/types/pagination';

interface CaseState {
    cases: ImmigrationCase[];
    currentCase: ImmigrationCase | null;
    caseTypes: CaseType[];
    statistics: CaseStatistics | null;
    timeline: CaseActivityLog[];
    meta: PaginationMeta | null;
    links: PaginationLinks | null;
    filters: CaseFilters;
    isLoading: boolean;
    isLoadingTimeline: boolean;
    error: string | null;
}

export const useCaseStore = defineStore('case', {
    state: (): CaseState => ({
        cases: [],
        currentCase: null,
        caseTypes: [],
        statistics: null,
        timeline: [],
        meta: null,
        links: null,
        filters: {
            search: '',
            status: undefined,
            priority: undefined,
            case_type_id: undefined,
            assigned_to: undefined,
            client_id: undefined,
            sort_by: 'created_at',
            sort_direction: 'desc',
            per_page: 15,
            page: 1,
        },
        isLoading: false,
        isLoadingTimeline: false,
        error: null,
    }),

    getters: {
        getCaseById: (state) => (id: number): ImmigrationCase | undefined => {
            return state.cases.find((c) => c.id === id);
        },

        totalCases: (state): number => {
            return state.meta?.total ?? 0;
        },

        currentPage: (state): number => {
            return state.meta?.current_page ?? 1;
        },

        lastPage: (state): number => {
            return state.meta?.last_page ?? 1;
        },

        hasNextPage: (state): boolean => {
            return state.links?.next !== null;
        },

        hasPrevPage: (state): boolean => {
            return state.links?.prev !== null;
        },

        statusOptions: (): Array<{ value: CaseStatus | ''; label: string; color: string }> => {
            return [
                { value: '', label: 'All Statuses', color: '' },
                { value: 'active', label: 'Active', color: 'success' },
                { value: 'inactive', label: 'Inactive', color: 'warning' },
                { value: 'archived', label: 'Archived', color: 'secondary' },
                { value: 'closed', label: 'Closed', color: 'dark' },
            ];
        },

        priorityOptions: (): Array<{ value: CasePriority | ''; label: string; color: string }> => {
            return [
                { value: '', label: 'All Priorities', color: '' },
                { value: 'urgent', label: 'Urgent', color: 'danger' },
                { value: 'high', label: 'High', color: 'warning' },
                { value: 'medium', label: 'Medium', color: 'info' },
                { value: 'low', label: 'Low', color: 'secondary' },
            ];
        },

        urgentCount: (state): number => {
            return state.statistics?.by_priority.urgent ?? 0;
        },

        upcomingHearingsCount: (state): number => {
            return state.statistics?.upcoming_hearings ?? 0;
        },

        unassignedCount: (state): number => {
            return state.statistics?.unassigned ?? 0;
        },

        activeCases: (state): ImmigrationCase[] => {
            return state.cases.filter((c) => c.status === 'active');
        },

        caseTypesByCategory: (state): Record<CaseTypeCategory, CaseType[]> => {
            return {
                temporary_residence: state.caseTypes.filter((ct) => ct.category === 'temporary_residence'),
                permanent_residence: state.caseTypes.filter((ct) => ct.category === 'permanent_residence'),
                humanitarian: state.caseTypes.filter((ct) => ct.category === 'humanitarian'),
            };
        },

        activeCaseTypes: (state): CaseType[] => {
            return state.caseTypes.filter((ct) => ct.is_active);
        },
    },

    actions: {
        // ===============================
        // FETCH OPERATIONS
        // ===============================

        async fetchCases(filters?: Partial<CaseFilters>) {
            this.isLoading = true;
            this.error = null;

            // Merge filters
            if (filters) {
                this.filters = { ...this.filters, ...filters };
            }

            try {
                const response = await caseService.getCases(this.filters);
                this.cases = response.data;

                // Handle Laravel's pagination format
                if ('meta' in response) {
                    this.meta = response.meta;
                    this.links = response.links;
                } else {
                    // Map Laravel's flat format to our meta structure
                    this.meta = {
                        current_page: (response as any).current_page,
                        from: (response as any).from,
                        last_page: (response as any).last_page,
                        per_page: (response as any).per_page,
                        to: (response as any).to,
                        total: (response as any).total,
                        path: (response as any).path || '',
                    };
                    this.links = {
                        first: (response as any).first_page_url || null,
                        last: (response as any).last_page_url || null,
                        prev: (response as any).prev_page_url || null,
                        next: (response as any).next_page_url || null,
                    };
                }
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to fetch cases';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async fetchCase(id: number) {
            this.isLoading = true;
            this.error = null;

            try {
                this.currentCase = await caseService.getCase(id);
                return this.currentCase;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to fetch case';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async fetchCaseTypes() {
            // Cache case types - only fetch if not already loaded
            if (this.caseTypes.length > 0) {
                return;
            }

            try {
                this.caseTypes = await caseService.getCaseTypes();
            } catch (error: any) {
                console.error('Failed to fetch case types:', error);
            }
        },

        async fetchStatistics() {
            try {
                this.statistics = await caseService.getStatistics();
            } catch (error: any) {
                console.error('Failed to fetch statistics:', error);
            }
        },

        async fetchTimeline(caseId: number) {
            this.isLoadingTimeline = true;

            try {
                this.timeline = await caseService.getTimeline(caseId);
            } catch (error: any) {
                console.error('Failed to fetch timeline:', error);
            } finally {
                this.isLoadingTimeline = false;
            }
        },

        // ===============================
        // CRUD OPERATIONS
        // ===============================

        async createCase(data: CreateCaseData) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await caseService.createCase(data);
                // Refresh the list
                await this.fetchCases();
                await this.fetchStatistics();
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to create case';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async updateCase(id: number, data: UpdateCaseData) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await caseService.updateCase(id, data);
                // Update case in list
                const index = this.cases.findIndex((c) => c.id === id);
                if (index !== -1) {
                    this.cases[index] = response.data;
                }
                // Update currentCase if it's the same
                if (this.currentCase?.id === id) {
                    this.currentCase = response.data;
                }
                await this.fetchStatistics();
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to update case';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async deleteCase(id: number) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await caseService.deleteCase(id);
                // Remove from list
                this.cases = this.cases.filter((c) => c.id !== id);
                // Update total count
                if (this.meta) {
                    this.meta.total--;
                }
                await this.fetchStatistics();
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to delete case';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async assignCase(id: number, userId: number) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await caseService.assignCase(id, userId);
                // Update case in list
                const index = this.cases.findIndex((c) => c.id === id);
                if (index !== -1) {
                    this.cases[index] = response.data;
                }
                // Update currentCase if it's the same
                if (this.currentCase?.id === id) {
                    this.currentCase = response.data;
                }
                await this.fetchStatistics();
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to assign case';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        // ===============================
        // FILTER OPERATIONS
        // ===============================

        setSearch(search: string) {
            this.filters.search = search;
            this.filters.page = 1; // Reset to first page
        },

        setStatusFilter(status: CaseStatus | undefined) {
            this.filters.status = status;
            this.filters.page = 1;
        },

        setPriorityFilter(priority: CasePriority | undefined) {
            this.filters.priority = priority;
            this.filters.page = 1;
        },

        setCaseTypeFilter(caseTypeId: number | undefined) {
            this.filters.case_type_id = caseTypeId;
            this.filters.page = 1;
        },

        setAssignedToFilter(userId: number | undefined) {
            this.filters.assigned_to = userId;
            this.filters.page = 1;
        },

        setClientFilter(clientId: number | undefined) {
            this.filters.client_id = clientId;
            this.filters.page = 1;
        },

        setSort(sortBy: string, direction: 'asc' | 'desc') {
            this.filters.sort_by = sortBy;
            this.filters.sort_direction = direction;
        },

        setPage(page: number) {
            this.filters.page = page;
        },

        setPerPage(perPage: number) {
            this.filters.per_page = perPage;
            this.filters.page = 1;
        },

        resetFilters() {
            this.filters = {
                search: '',
                status: undefined,
                priority: undefined,
                case_type_id: undefined,
                assigned_to: undefined,
                client_id: undefined,
                sort_by: 'created_at',
                sort_direction: 'desc',
                per_page: 15,
                page: 1,
            };
        },

        // ===============================
        // CLEAR OPERATIONS
        // ===============================

        clearCurrentCase() {
            this.currentCase = null;
        },

        clearTimeline() {
            this.timeline = [];
        },

        clearError() {
            this.error = null;
        },

        // ===============================
        // TASK OPERATIONS (Lifecycle)
        // ===============================

        async toggleTask(caseId: number, taskId: number): Promise<{ task: CaseTask; progress: number }> {
            try {
                const result = await caseService.toggleTask(caseId, taskId);

                // Update currentCase tasks and progress if applicable
                if (this.currentCase?.id === caseId) {
                    const taskIdx = this.currentCase.tasks?.findIndex(t => t.id === taskId);
                    if (taskIdx !== undefined && taskIdx !== -1 && this.currentCase.tasks) {
                        this.currentCase.tasks[taskIdx] = result.task;
                    }
                    this.currentCase.progress = result.progress;
                }

                return result;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to toggle task';
                throw error;
            }
        },

        async bulkUpdateTasks(caseId: number, tasks: Omit<CaseTask, 'id' | 'completed_at'>[]) {
            try {
                const response = await caseService.bulkUpdateTasks(caseId, tasks);
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to update tasks';
                throw error;
            }
        },
    },
});
