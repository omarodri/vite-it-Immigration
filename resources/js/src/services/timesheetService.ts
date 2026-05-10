/**
 * Timesheet Service
 * Handles all time-log related API calls.
 *
 * Note: `api` already has `baseURL: '/api'`, so we use relative paths.
 */

import api from './api';
import type { ManualLogPayload, StartTimerPayload, TimeLogFilters } from '@/types/timesheet';

const timesheetService = {
    getActiveTimer() {
        return api.get('/me/active-timer');
    },

    getCaseLogs(caseId: number, params?: TimeLogFilters) {
        return api.get(`/cases/${caseId}/time-logs`, { params });
    },

    createManualLog(caseId: number, data: ManualLogPayload) {
        return api.post(`/cases/${caseId}/time-logs`, data);
    },

    startTimer(caseId: number, data?: StartTimerPayload) {
        return api.post(`/cases/${caseId}/time-logs/start`, data ?? {});
    },

    stopTimer(caseId: number, logId: number) {
        return api.patch(`/cases/${caseId}/time-logs/${logId}/stop`);
    },

    updateLog(caseId: number, logId: number, data: Partial<ManualLogPayload>) {
        return api.put(`/cases/${caseId}/time-logs/${logId}`, data);
    },

    deleteLog(caseId: number, logId: number) {
        return api.delete(`/cases/${caseId}/time-logs/${logId}`);
    },
};

export default timesheetService;
export { timesheetService };
