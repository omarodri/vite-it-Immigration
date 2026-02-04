<template>
    <div class="dark:text-white-dark text-center ltr:sm:text-left rtl:sm:text-right p-6 pt-0 mt-auto">
        © {{ new Date().getFullYear() }}. VITE-IT {{ $t('footer.rights') }}.
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>
