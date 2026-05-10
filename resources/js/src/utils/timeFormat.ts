/**
 * Time formatting utilities for the timesheet feature.
 * All durations are stored as integer seconds.
 */

export function formatDuration(seconds: number): string {
    if (!seconds || seconds < 0) return '0m';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.floor(seconds % 60);
    if (h > 0) return `${h}h ${String(m).padStart(2, '0')}m`;
    if (m > 0) return `${m}m ${String(s).padStart(2, '0')}s`;
    return `${s}s`;
}

export function formatDurationDetailed(seconds: number): { hours: number; minutes: number; seconds: number } {
    return {
        hours: Math.floor(seconds / 3600),
        minutes: Math.floor((seconds % 3600) / 60),
        seconds: Math.floor(seconds % 60),
    };
}

export function durationToSeconds(hours: number, minutes: number): number {
    const h = Math.max(0, Math.floor(hours || 0));
    const m = Math.max(0, Math.floor(minutes || 0));
    return h * 3600 + m * 60;
}

/**
 * HH:MM:SS for stopwatch displays. Always two-digit minutes/seconds.
 */
export function formatStopwatch(seconds: number): string {
    const safe = Math.max(0, Math.floor(seconds));
    const h = Math.floor(safe / 3600);
    const m = Math.floor((safe % 3600) / 60);
    const s = safe % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}
