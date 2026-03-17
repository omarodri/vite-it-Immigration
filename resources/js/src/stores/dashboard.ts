/**
 * Dashboard Store
 * Manages dashboard data with stale-while-revalidate caching (5 min TTL)
 */

import { defineStore } from 'pinia';
import dashboardService from '@/services/dashboardService';
import type { DashboardData, DashboardMetrics, DashboardTask, DashboardEvent, DashboardCase } from '@/types/dashboard';

const STALE_TTL_MS = 5 * 60 * 1000; // 5 minutes

interface DashboardState {
    data: DashboardData | null;
    isLoading: boolean;
    error: string | null;
    lastFetchedAt: number | null;
}

export const useDashboardStore = defineStore('dashboard', {
    state: (): DashboardState => ({
        data: null,
        isLoading: false,
        error: null,
        lastFetchedAt: null,
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

        assignedTasks(state): DashboardTask[] {
            return state.data?.assigned_tasks ?? [];
        },

        upcomingEvents(state): DashboardEvent[] {
            return state.data?.upcoming_events ?? [];
        },

        recentCases(state): DashboardCase[] {
            return state.data?.recent_cases ?? [];
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
            } catch (error: any) {
                if (error?.response?.status === 401) throw error;
                this.error = error.response?.data?.message || 'Failed to load dashboard';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        invalidate() {
            this.lastFetchedAt = null;
        },
    },
});
