import { defineStore } from 'pinia';
import dashboardService from '@/services/dashboardService';
import type {
    DashboardData,
    DashboardMetrics,
    DashboardTask,
    DashboardEvent,
    DashboardCase,
    DashboardExpiringDocument,
    DashboardMilestone,
} from '@/types/dashboard';

const STALE_TTL_MS = 5 * 60 * 1000;

interface AssignedTasksState {
    items: DashboardTask[];
    page: number;
    perPage: number;
    total: number;
    lastPage: number;
    isLoading: boolean;
    error: string | null;
}

interface DashboardState {
    data: DashboardData | null;
    isLoading: boolean;
    error: string | null;
    lastFetchedAt: number | null;
    assignedTasks: AssignedTasksState;
}

export const useDashboardStore = defineStore('dashboard', {
    state: (): DashboardState => ({
        data: null,
        isLoading: false,
        error: null,
        lastFetchedAt: null,
        assignedTasks: {
            items: [],
            page: 1,
            perPage: 10,
            total: 0,
            lastPage: 1,
            isLoading: false,
            error: null,
        },
    }),

    getters: {
        isStale(state): boolean {
            if (!state.lastFetchedAt) return true;
            return Date.now() - state.lastFetchedAt > STALE_TTL_MS;
        },

        metrics(state): DashboardMetrics {
            return state.data?.metrics ?? {
                active_cases_assigned_to_me: 0,
                today_events: 0,
                pending_todos: 0,
            };
        },

        upcomingEvents(state): DashboardEvent[] {
            return state.data?.upcoming_events ?? [];
        },

        recentCases(state): DashboardCase[] {
            return state.data?.recent_cases ?? [];
        },

        expiringDocuments(state): DashboardExpiringDocument[] {
            return state.data?.expiring_documents ?? [];
        },

        upcomingMilestones(state): DashboardMilestone[] {
            return state.data?.upcoming_milestones ?? [];
        },
    },

    actions: {
        async fetchDashboard(force = false) {
            if (!force && !this.isStale && this.data) {
                return;
            }

            this.isLoading = true;
            this.error = null;

            try {
                this.data = await dashboardService.getDashboard();
                this.lastFetchedAt = Date.now();
                this._syncAssignedTasksFromDashboard();
            } catch (error: any) {
                if (error?.response?.status === 401) throw error;
                this.error = error.response?.data?.message || 'Failed to load dashboard';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        _syncAssignedTasksFromDashboard() {
            if (!this.data) return;
            this.assignedTasks.items    = this.data.assigned_tasks ?? [];
            this.assignedTasks.total    = this.data.assigned_tasks_meta?.total ?? this.assignedTasks.items.length;
            this.assignedTasks.lastPage = this.data.assigned_tasks_meta?.last_page ?? 1;
            this.assignedTasks.page     = 1;
        },

        async fetchAssignedTasksPage(page: number) {
            if (page < 1 || page > this.assignedTasks.lastPage) return;
            this.assignedTasks.isLoading = true;
            this.assignedTasks.error = null;
            try {
                const res = await dashboardService.getAssignedTasks(page, this.assignedTasks.perPage);
                this.assignedTasks.items    = res.data;
                this.assignedTasks.page     = res.meta.current_page;
                this.assignedTasks.total    = res.meta.total;
                this.assignedTasks.lastPage = res.meta.last_page;
            } catch (e: any) {
                if (e?.response?.status === 401) throw e;
                this.assignedTasks.error = e.response?.data?.message ?? 'dashboard.tasks_load_error';
            } finally {
                this.assignedTasks.isLoading = false;
            }
        },

        invalidateAssignedTasks() {
            this.assignedTasks.page = 1;
            this.lastFetchedAt = null;
        },

        invalidate() {
            this.lastFetchedAt = null;
        },
    },
});
