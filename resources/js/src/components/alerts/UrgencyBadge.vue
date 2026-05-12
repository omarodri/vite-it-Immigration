<script lang="ts" setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { UrgencyBucket } from '@/types/important-date-alert';

const props = defineProps<{
    urgencyBucket: UrgencyBucket;
    daysDiff: number;
}>();

const { t } = useI18n();

const config = {
    overdue_critical: { labelKey: 'alerts.urgency.overdue_critical', cls: 'bg-danger-light text-danger dark:bg-danger/20' },
    overdue_recent:   { labelKey: 'alerts.urgency.overdue_recent',   cls: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' },
    today:            { labelKey: 'alerts.urgency.today',            cls: 'bg-warning-light text-warning dark:bg-warning/20' },
    upcoming_week:    { labelKey: 'alerts.urgency.this_week',        cls: 'bg-info-light text-info dark:bg-info/20' },
    upcoming_month:   { labelKey: 'alerts.urgency.upcoming',         cls: 'bg-success-light text-success dark:bg-success/20' },
} as const;

const current = computed(() => config[props.urgencyBucket]);

const daysText = computed(() => {
    if (props.daysDiff === 0) return '';
    const n = Math.abs(props.daysDiff);
    return props.daysDiff < 0
        ? `(${t('alerts.days_overdue', { n })})`
        : `(${t('alerts.days_remaining', { n })})`;
});
</script>

<template>
    <span :class="['inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap', current.cls]">
        {{ t(current.labelKey) }}
        <span v-if="daysText" class="opacity-75 font-normal">{{ daysText }}</span>
    </span>
</template>
