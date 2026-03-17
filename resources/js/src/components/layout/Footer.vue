<template>
    <div class="dark:text-white-dark p-6 pt-0 mt-auto flex items-center justify-between gap-4">
        <div class="text-left">© {{ new Date().getFullYear() }}. VITE-IT {{ $t('footer.rights') }}.</div>
        <div class="text-right; pr-10">
            <a href="https://vite-it.com" target="_blank" class="text-sm text-gray-500 dark:text-gray-400">Versión 1.0.0</a>
        </div>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>
