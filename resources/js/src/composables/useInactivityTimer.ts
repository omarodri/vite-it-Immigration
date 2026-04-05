/**
 * useInactivityTimer
 *
 * Detects user inactivity and triggers a callback (e.g. redirect to login).
 * Resets on any user interaction: mousemove, keydown, click, scroll, touchstart.
 *
 * Usage (in app-layout.vue or App.vue):
 *   const { start, stop } = useInactivityTimer(120, () => {
 *       router.push('/auth/boxed-signin');
 *   });
 *   onMounted(start);
 *   onUnmounted(stop);
 */

import { ref, onUnmounted } from 'vue';

const ACTIVITY_EVENTS: (keyof WindowEventMap)[] = [
    'mousemove',
    'keydown',
    'click',
    'scroll',
    'touchstart',
];

export function useInactivityTimer(
    timeoutMinutes: number,
    onExpired: () => void,
) {
    const timerId = ref<ReturnType<typeof setTimeout> | null>(null);
    const isRunning = ref(false);

    const reset = () => {
        if (!isRunning.value) return;
        if (timerId.value !== null) {
            clearTimeout(timerId.value);
        }
        timerId.value = setTimeout(() => {
            onExpired();
        }, timeoutMinutes * 60 * 1000);
    };

    const start = () => {
        isRunning.value = true;
        ACTIVITY_EVENTS.forEach(event => {
            window.addEventListener(event, reset, { passive: true });
        });
        reset();
    };

    const stop = () => {
        isRunning.value = false;
        if (timerId.value !== null) {
            clearTimeout(timerId.value);
            timerId.value = null;
        }
        ACTIVITY_EVENTS.forEach(event => {
            window.removeEventListener(event, reset);
        });
    };

    // Auto-cleanup when component unmounts
    onUnmounted(stop);

    return { start, stop, reset };
}
