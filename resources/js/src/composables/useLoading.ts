import { ref, computed } from 'vue';

// Global loading state
const loadingCount = ref(0);
const loadingMessage = ref<string | null>(null);

export function useLoading() {
    const isLoading = computed(() => loadingCount.value > 0);

    const startLoading = (message?: string): void => {
        loadingCount.value++;
        if (message) {
            loadingMessage.value = message;
        }
    };

    const stopLoading = (): void => {
        if (loadingCount.value > 0) {
            loadingCount.value--;
        }
        if (loadingCount.value === 0) {
            loadingMessage.value = null;
        }
    };

    const resetLoading = (): void => {
        loadingCount.value = 0;
        loadingMessage.value = null;
    };

    const withLoading = async <T>(
        promise: Promise<T>,
        message?: string
    ): Promise<T> => {
        startLoading(message);
        try {
            return await promise;
        } finally {
            stopLoading();
        }
    };

    return {
        isLoading,
        loadingMessage: computed(() => loadingMessage.value),
        startLoading,
        stopLoading,
        resetLoading,
        withLoading,
    };
}

export default useLoading;
