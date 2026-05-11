import { defineStore } from 'pinia';

const SESSION_KEY        = 'session_kicked_out';
const LOCK_KEY           = 'session_account_locked';

interface KickedPayload {
    revokedAt?: string | null;
}

interface LockedPayload {
    lockedUntil?: string | null;
}

interface SessionState {
    kickedOut: boolean;
    kickedOutAt: string | null;
    accountLocked: boolean;
    accountLockedUntil: string | null;
}

export const useSessionStore = defineStore('session', {
    state: (): SessionState => ({
        kickedOut: false,
        kickedOutAt: null,
        accountLocked: false,
        accountLockedUntil: null,
    }),

    actions: {
        setKickedOut(payload: KickedPayload): void {
            this.kickedOut = true;
            this.kickedOutAt = payload.revokedAt ?? new Date().toISOString();
            try {
                sessionStorage.setItem(SESSION_KEY, JSON.stringify({
                    kickedOut: true,
                    kickedOutAt: this.kickedOutAt,
                }));
            } catch { /* sessionStorage may not be available in all contexts */ }
        },

        consume(): void {
            this.kickedOut = false;
            this.kickedOutAt = null;
            try {
                sessionStorage.removeItem(SESSION_KEY);
            } catch { /* ignore */ }
        },

        setAccountLocked(payload: LockedPayload): void {
            this.accountLocked = true;
            this.accountLockedUntil = payload.lockedUntil ?? null;
            try {
                sessionStorage.setItem(LOCK_KEY, JSON.stringify({
                    accountLocked: true,
                    accountLockedUntil: this.accountLockedUntil,
                }));
            } catch { /* ignore */ }
        },

        consumeLock(): void {
            this.accountLocked = false;
            this.accountLockedUntil = null;
            try {
                sessionStorage.removeItem(LOCK_KEY);
            } catch { /* ignore */ }
        },

        hydrateFromStorage(): void {
            try {
                const raw = sessionStorage.getItem(SESSION_KEY);
                if (raw) {
                    const data = JSON.parse(raw) as Partial<SessionState>;
                    this.kickedOut = data.kickedOut ?? false;
                    this.kickedOutAt = data.kickedOutAt ?? null;
                }
                const lockRaw = sessionStorage.getItem(LOCK_KEY);
                if (lockRaw) {
                    const data = JSON.parse(lockRaw) as Partial<SessionState>;
                    this.accountLocked = data.accountLocked ?? false;
                    this.accountLockedUntil = data.accountLockedUntil ?? null;
                }
            } catch { /* ignore parse errors */ }
        },
    },
});

// BroadcastChannel — propagate kick to other tabs of the same browser
const sessionChannel = typeof BroadcastChannel !== 'undefined'
    ? new BroadcastChannel('session')
    : null;

if (sessionChannel) {
    sessionChannel.onmessage = async (event: MessageEvent) => {
        if (event.data?.type === 'session_revoked') {
            const { useSessionStore: getStore } = await import('@/stores/useSessionStore');
            const { useAuthStore } = await import('@/stores/auth');
            const { default: router } = await import('@/router');

            const store = getStore();
            const authStore = useAuthStore();

            store.setKickedOut({ revokedAt: event.data.revokedAt ?? null });
            authStore.$patch({ user: null, isAuthenticated: false });

            const currentRoute = router.currentRoute.value.name as string;
            if (currentRoute !== 'boxed-signin' && currentRoute !== 'cover-login') {
                router.push({ name: 'boxed-signin' });
            }
        }
    };
}

export function broadcastSessionRevoked(revokedAt?: string | null): void {
    try {
        sessionChannel?.postMessage({ type: 'session_revoked', revokedAt: revokedAt ?? null });
    } catch { /* BroadcastChannel is best-effort */ }
}
