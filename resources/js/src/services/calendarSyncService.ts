import api from './api';

export interface CalendarProviderStatus {
    credentials_configured: boolean;
    connected: boolean;
    expires_at: string | null;
    is_expired: boolean;
    sync_status: 'active' | 'error' | 'paused' | null;
    last_pull_at: string | null;
    last_push_at: string | null;
    last_error: string | null;
}

export interface CalendarConnectionStatus {
    google: CalendarProviderStatus;
    microsoft: CalendarProviderStatus;
}

export const calendarSyncService = {
    async getStatus(): Promise<CalendarConnectionStatus> {
        const { data } = await api.get('/calendar-oauth/status');
        return data;
    },

    async getRedirectUrl(provider: 'google' | 'microsoft'): Promise<{ url: string }> {
        const { data } = await api.get(`/calendar-oauth/${provider}/redirect`);
        return data;
    },

    async disconnect(provider: 'google' | 'microsoft'): Promise<void> {
        await api.post(`/calendar-oauth/${provider}/disconnect`);
    },
};
