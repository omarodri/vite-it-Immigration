import { beforeEach, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';

// Mock @/services/api (axios instance)
vi.mock('@/services/api', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
        interceptors: {
            request: { use: vi.fn() },
            response: { use: vi.fn() },
        },
    },
    refreshCsrfToken: vi.fn(),
}));

// Mock axios (for CSRF cookie calls)
vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        create: vi.fn(() => ({
            get: vi.fn(),
            post: vi.fn(),
            put: vi.fn(),
            delete: vi.fn(),
            interceptors: {
                request: { use: vi.fn() },
                response: { use: vi.fn() },
            },
        })),
    },
}));

// Mock @/composables/useNotification
vi.mock('@/composables/useNotification', () => ({
    useNotification: () => ({
        success: vi.fn(),
        error: vi.fn(),
        warning: vi.fn(),
        info: vi.fn(),
        confirm: vi.fn(),
        confirmDelete: vi.fn(),
        showValidationErrors: vi.fn(),
        showApiError: vi.fn(),
    }),
    default: () => ({
        success: vi.fn(),
        error: vi.fn(),
        warning: vi.fn(),
        info: vi.fn(),
        confirm: vi.fn(),
        confirmDelete: vi.fn(),
        showValidationErrors: vi.fn(),
        showApiError: vi.fn(),
    }),
}));

// Mock @/router
vi.mock('@/router', () => ({
    default: {
        push: vi.fn(),
        replace: vi.fn(),
        currentRoute: { value: { name: '' } },
    },
}));

// Reset Pinia before each test
beforeEach(() => {
    setActivePinia(createPinia());
});
