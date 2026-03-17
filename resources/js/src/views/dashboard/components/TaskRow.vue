<script lang="ts" setup>
import { useI18n } from 'vue-i18n';
import type { DashboardTask } from '@/types/dashboard';

defineProps<{
    task: DashboardTask;
}>();

const { t } = useI18n();

const priorityClasses: Record<string, string> = {
    high: 'badge badge-outline-danger',
    medium: 'badge badge-outline-warning',
    low: 'badge badge-outline-secondary',
};

const statusClasses: Record<string, string> = {
    pending: 'badge badge-outline-info',
    important: 'badge badge-outline-danger',
};

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '--';
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}
</script>

<template>
    <tr>
        <td class="whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">#{{ task.id }}</td>
        <td class="max-w-[200px] text-xs">
            <span class="truncate block" :title="task.title">{{ task.title }}</span>
        </td>
        <td>
            <span v-if="task.tag" class="badge badge-outline-primary text-xs text-wrap">{{ task.tag }}</span>
            <span v-else class="text-gray-400">--</span>
        </td>
        <td class="whitespace-nowrap text-xs">{{ formatDate(task.due_date) }}</td>
        <td class="whitespace-nowrap">
            <router-link
                v-if="task.case"
                :to="`/cases/${task.case.id}`"
                class="text-primary hover:underline text-xs"
            >
                {{ task.case.case_number }}
            </router-link>
            <span v-else class="text-gray-400">--</span>
        </td>
        <td>
            <span :class="priorityClasses[task.priority] || 'badge badge-outline-secondary'">
                {{ task.priority }}
            </span>
        </td>
        <td>
            <span :class="statusClasses[task.status] || 'badge badge-outline-secondary'">
                {{ task.status }}
            </span>
        </td>
    </tr>
</template>
