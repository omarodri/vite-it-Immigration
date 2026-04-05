<script lang="ts" setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { AlertStatus } from '@/types/legal-document';

const props = defineProps<{
    alertStatus: AlertStatus;
    daysRemaining: number | null | undefined;
}>();

const { t } = useI18n();

const badgeClass = computed(() => {
    const classes: Record<AlertStatus, string> = {
        overdue: 'bg-danger/10 text-danger',
        critical: 'bg-warning/10 text-warning',
        warning: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        ok: 'bg-success/10 text-success',
        none: 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
    };
    return classes[props.alertStatus] || classes.none;
});

const label = computed(() => {
    const days = props.daysRemaining;

    if (props.alertStatus === 'none' || days === null || days === undefined) {
        return '-';
    }

    if (days === 0) {
        return t('alerts.expires_today');
    }

    if (days < 0) {
        return t('alerts.overdue_days', { n: Math.abs(days) });
    }

    return t('alerts.in_days', { n: days });
});
</script>

<template>
    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium whitespace-nowrap" :class="badgeClass">
        {{ label }}
    </span>
</template>
