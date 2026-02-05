import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [vue()],
    test: {
        globals: true,
        environment: 'happy-dom',
        setupFiles: ['./resources/js/tests/setup.ts'],
        include: ['resources/js/tests/**/*.spec.ts'],
        coverage: {
            provider: 'v8',
            include: ['resources/js/src/stores/**', 'resources/js/src/services/**'],
        },
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js/src'),
        },
    },
});
