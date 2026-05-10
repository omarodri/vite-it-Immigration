/**
 * useActiveTimer
 *
 * Provides a reactive elapsed-seconds counter for the currently-running timer.
 * Re-evaluates every second by ticking a ref that the `elapsed` computed depends on.
 */

import { ref, computed, onMounted, onUnmounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useTimesheetStore } from '@/stores/useTimesheetStore';

export function useActiveTimer() {
    const store = useTimesheetStore();
    const { activeTimer } = storeToRefs(store);
    const tick = ref(0);
    let interval: ReturnType<typeof setInterval> | null = null;

    const elapsed = computed<number>(() => {
        if (!activeTimer.value?.started_at) return 0;
        // touch tick so that this computed re-runs every second
        // eslint-disable-next-line @typescript-eslint/no-unused-expressions
        tick.value;
        const start = new Date(activeTimer.value.started_at).getTime();
        const diff = Math.floor((Date.now() - start) / 1000);
        return diff > 0 ? diff : 0;
    });

    const isRunning = computed<boolean>(() => !!activeTimer.value);

    /**
     * True when the timer has been running over 8 hours; the UI should warn the user.
     */
    const isLongRunning = computed<boolean>(() => elapsed.value > 8 * 3600);

    onMounted(() => {
        interval = setInterval(() => {
            tick.value = (tick.value + 1) % Number.MAX_SAFE_INTEGER;
        }, 1000);
    });

    onUnmounted(() => {
        if (interval) {
            clearInterval(interval);
            interval = null;
        }
    });

    return { elapsed, isRunning, isLongRunning, activeTimer };
}

export default useActiveTimer;
