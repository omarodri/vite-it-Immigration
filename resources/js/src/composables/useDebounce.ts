import { ref, readonly, onUnmounted } from 'vue';

export function useDebounce(delay: number = 300) {
    const isDebouncing = ref(false);
    let timer: ReturnType<typeof setTimeout> | null = null;

    const debounce = (callback: () => void, customDelay?: number): void => {
        cancel();
        isDebouncing.value = true;
        timer = setTimeout(() => {
            isDebouncing.value = false;
            callback();
        }, customDelay ?? delay);
    };

    const cancel = (): void => {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }
        isDebouncing.value = false;
    };

    onUnmounted(() => {
        cancel();
    });

    return {
        debounce,
        cancel,
        isDebouncing: readonly(isDebouncing),
    };
}

export default useDebounce;
