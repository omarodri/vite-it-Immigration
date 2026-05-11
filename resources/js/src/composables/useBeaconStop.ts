import { useTimesheetStore } from '@/stores/useTimesheetStore';

export function registerBeaconStop(): void {
    const getCsrf = (): string => {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    };

    const sendStop = (): void => {
        const ts = useTimesheetStore();
        if (!ts.hasActiveTimer) return;
        const blob = new Blob(
            [JSON.stringify({ _token: getCsrf() })],
            { type: 'application/json' }
        );
        navigator.sendBeacon('/api/me/active-timer/beacon-stop', blob);
    };

    window.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') sendStop();
    });
    window.addEventListener('pagehide', sendStop);
}
