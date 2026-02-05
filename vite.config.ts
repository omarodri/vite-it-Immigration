import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import path from "path";
import VueI18nPlugin from "@intlify/unplugin-vue-i18n/vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    server: {
        host: '0.0.0.0',
        hmr: {
            host: 'localhost',
        },
        cors: true,
    },
    plugins: [
        laravel({
            input: ["resources/js/src/main.ts"],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    includeAbsolute: false,
                },
            },
        }),
        VueI18nPlugin({
            include: path.resolve("resources/js/src/locales/**"),
        }),
    ],
    resolve: {
        alias: {
            "@": path.resolve("./resources/js/src"),
            "@fullcalendar/core/vdom": "@fullcalendar/core",
        },
    },
    optimizeDeps: {
        include: ["@fullcalendar/core", "@fullcalendar/vue3", "quill"],
    },
    build: {
        chunkSizeWarningLimit: 1000, // 1MB - some libraries like highlight.js are large
        rollupOptions: {
            output: {
                manualChunks: {
                    // Vue core
                    'vue-vendor': ['vue', 'vue-router', 'pinia'],
                    // Charts
                    'apexcharts': ['apexcharts', 'vue3-apexcharts'],
                    // Calendar
                    'fullcalendar': [
                        '@fullcalendar/core',
                        '@fullcalendar/daygrid',
                        '@fullcalendar/timegrid',
                        '@fullcalendar/interaction',
                        '@fullcalendar/vue3',
                    ],
                    // Rich text editors
                    'editors': ['vue3-quill', 'easymde', 'vue3-easymde'],
                    // Code highlighting
                    'highlight': ['highlight.js'],
                    // UI utilities
                    'ui-utils': ['sweetalert2', 'vue-flatpickr-component', 'tippy.js'],
                    // Carousel & animations
                    'swiper': ['swiper'],
                    // Data tables
                    'datatables': ['@bhplugin/vue3-datatable'],
                    // i18n
                    'i18n': ['vue-i18n'],
                },
            },
        },
    },
});
