import { defineStore } from 'pinia';
import { calendarSyncService, type CalendarProviderStatus } from '@/services/calendarSyncService';

interface CalendarSyncState {
    google: CalendarProviderStatus | null;
    microsoft: CalendarProviderStatus | null;
    isLoading: boolean;
    isPulling: boolean;
    isRetrying: { google: boolean; microsoft: boolean };
    lastFetchedAt: number | null;
}

export const useCalendarSyncStore = defineStore('calendarSync', {
    state: (): CalendarSyncState => ({
        google: null,
        microsoft: null,
        isLoading: false,
        isPulling: false,
        isRetrying: { google: false, microsoft: false },
        lastFetchedAt: null,
    }),

    getters: {
        lastPullAt(state): string | null {
            const gPull = state.google?.last_pull_at ?? null;
            const mPull = state.microsoft?.last_pull_at ?? null;
            if (!gPull && !mPull) return null;
            if (!gPull) return mPull;
            if (!mPull) return gPull;
            return gPull > mPull ? gPull : mPull;
        },
        hasAnyConnection(state): boolean {
            return (
                state.google?.connected === true ||
                state.microsoft?.connected === true
            );
        },
    },

    actions: {
        async fetchStatus(force = false): Promise<void> {
            if (!force && this.lastFetchedAt && Date.now() - this.lastFetchedAt < 60_000) return;
            this.isLoading = true;
            try {
                const status = await calendarSyncService.getStatus();
                this.google    = status.google;
                this.microsoft = status.microsoft;
                this.lastFetchedAt = Date.now();
            } catch {
                // non-critical
            } finally {
                this.isLoading = false;
            }
        },

        async refreshNow(): Promise<void> {
            if (this.isPulling) return;
            this.isPulling = true;
            try {
                const result = await calendarSyncService.pullOnDemand();
                if (result.pulled) {
                    await this.fetchStatus(true);
                }
            } catch {
                // non-critical
            } finally {
                this.isPulling = false;
            }
        },

        async retry(provider: 'google' | 'microsoft'): Promise<boolean> {
            this.isRetrying[provider] = true;
            try {
                const result = await calendarSyncService.retry(provider);
                if (result.success) {
                    await this.fetchStatus(true);
                }
                return result.success;
            } catch {
                return false;
            } finally {
                this.isRetrying[provider] = false;
            }
        },

        async disconnect(provider: 'google' | 'microsoft'): Promise<void> {
            await calendarSyncService.disconnect(provider);
            await this.fetchStatus(true);
        },
    },
});
