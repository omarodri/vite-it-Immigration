<script lang="ts" setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { DashboardEvent } from '@/types/dashboard';

const props = defineProps<{
    event: DashboardEvent;
}>();

const { t } = useI18n();

const formattedDate = computed(() => {
    const startStr = props.event.start_date;
    const now = new Date();

    // For all-day events the API returns a date-only string ("YYYY-MM-DD").
    // new Date("YYYY-MM-DD") parses as UTC midnight which shifts the day for
    // negative-offset timezones. Parse directly into local date components.
    if (props.event.all_day) {
        const [y, m, d] = startStr.slice(0, 10).split('-').map(Number);
        const eventDay  = new Date(y, m - 1, d);
        const todayDay  = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const tomorrowDay = new Date(todayDay);
        tomorrowDay.setDate(tomorrowDay.getDate() + 1);

        if (eventDay.getTime() === todayDay.getTime())     return t('dashboard.today');
        if (eventDay.getTime() === tomorrowDay.getTime())  return t('dashboard.tomorrow');
        return eventDay.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' });
    }

    // Timed events: ISO string with timezone — parse normally.
    const date = new Date(startStr);
    const eventDay  = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    const todayDay  = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const tomorrowDay = new Date(todayDay);
    tomorrowDay.setDate(tomorrowDay.getDate() + 1);

    let dayPart: string;
    if (eventDay.getTime() === todayDay.getTime())     dayPart = t('dashboard.today');
    else if (eventDay.getTime() === tomorrowDay.getTime()) dayPart = t('dashboard.tomorrow');
    else dayPart = date.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' });

    const timePart = date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
    return `${dayPart} - ${timePart}`;
});
</script>

<template>
    <div class="flex items-start gap-3">
        <div
            class="w-2.5 h-2.5 rounded-full mt-1.5 shrink-0"
            :style="{ backgroundColor: event.hex_color }"
        ></div>
        <div class="flex-1 min-w-0">
            <p class="font-semibold text-sm text-gray-800 dark:text-white truncate">{{ event.title }}</p>
            <p v-if="event.client_name" class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ event.client_name }}</p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ formattedDate }}</p>
        </div>
    </div>
</template>
