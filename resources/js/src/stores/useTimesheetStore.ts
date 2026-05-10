/**
 * Timesheet Store
 * Manages the active timer (one per user) and the per-case log cache.
 */

import { defineStore } from 'pinia';
import timesheetService from '@/services/timesheetService';
import type {
    TimeLog,
    ManualLogPayload,
    StartTimerPayload,
    TimeLogFilters,
    TimeLogPaginationMeta,
} from '@/types/timesheet';

interface TimesheetState {
    activeTimer: TimeLog | null;
    caseLogs: Record<number, TimeLog[]>;
    caseLogsMeta: Record<number, TimeLogPaginationMeta>;
    isLoading: boolean;
}

export const useTimesheetStore = defineStore('timesheet', {
    state: (): TimesheetState => ({
        activeTimer: null,
        caseLogs: {},
        caseLogsMeta: {},
        isLoading: false,
    }),

    getters: {
        hasActiveTimer: (state): boolean => state.activeTimer !== null,

        activeTimerCaseId: (state): number | null => state.activeTimer?.case_id ?? null,

        getLogsForCase:
            (state) =>
            (caseId: number): TimeLog[] =>
                state.caseLogs[caseId] ?? [],

        getMetaForCase:
            (state) =>
            (caseId: number): TimeLogPaginationMeta | null =>
                state.caseLogsMeta[caseId] ?? null,
    },

    actions: {
        async fetchActiveTimer(): Promise<void> {
            try {
                const res = await timesheetService.getActiveTimer();
                this.activeTimer = res.data?.data ?? null;
            } catch {
                // Silent: 401 will be handled by axios interceptor; any other failure should not break the app
                this.activeTimer = null;
            }
        },

        async startTimer(caseId: number, payload?: StartTimerPayload): Promise<TimeLog> {
            this.isLoading = true;
            try {
                const res = await timesheetService.startTimer(caseId, payload);
                this.activeTimer = res.data.data;
                return res.data.data as TimeLog;
            } finally {
                this.isLoading = false;
            }
        },

        async stopTimer(): Promise<TimeLog | null> {
            if (!this.activeTimer) return null;
            this.isLoading = true;
            const caseId = this.activeTimer.case_id;
            const logId = this.activeTimer.id;
            try {
                const res = await timesheetService.stopTimer(caseId, logId);
                const stopped: TimeLog = res.data.data;
                this.activeTimer = null;
                await this.fetchCaseLogs(caseId);
                return stopped;
            } finally {
                this.isLoading = false;
            }
        },

        async createManualLog(caseId: number, data: ManualLogPayload): Promise<TimeLog> {
            this.isLoading = true;
            try {
                const res = await timesheetService.createManualLog(caseId, data);
                await this.fetchCaseLogs(caseId);
                return res.data.data as TimeLog;
            } finally {
                this.isLoading = false;
            }
        },

        async fetchCaseLogs(caseId: number, params: TimeLogFilters = {}): Promise<void> {
            this.isLoading = true;
            try {
                const res = await timesheetService.getCaseLogs(caseId, params);
                this.caseLogs[caseId] = res.data.data ?? [];
                this.caseLogsMeta[caseId] = res.data.meta ?? {};
            } finally {
                this.isLoading = false;
            }
        },

        async updateLog(caseId: number, logId: number, data: Partial<ManualLogPayload>): Promise<TimeLog> {
            const res = await timesheetService.updateLog(caseId, logId, data);
            const updated: TimeLog = res.data.data;
            if (this.caseLogs[caseId]) {
                const idx = this.caseLogs[caseId].findIndex((l) => l.id === logId);
                if (idx !== -1) this.caseLogs[caseId][idx] = updated;
            }
            return updated;
        },

        async deleteLog(caseId: number, logId: number): Promise<void> {
            await timesheetService.deleteLog(caseId, logId);
            if (this.caseLogs[caseId]) {
                this.caseLogs[caseId] = this.caseLogs[caseId].filter((l) => l.id !== logId);
            }
        },

        clearAll(): void {
            this.activeTimer = null;
            this.caseLogs = {};
            this.caseLogsMeta = {};
        },
    },
});
