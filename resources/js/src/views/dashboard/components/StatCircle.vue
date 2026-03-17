<script lang="ts" setup>
defineProps<{
    value: number;
    label: string;
    color: 'primary' | 'success' | 'warning' | 'info' | 'danger';
    loading?: boolean;
}>();

const colorMap: Record<string, { border: string; text: string; bg: string }> = {
    primary: { border: 'border-primary', text: 'text-primary', bg: 'bg-primary/10' },
    success: { border: 'border-success', text: 'text-success', bg: 'bg-success/10' },
    warning: { border: 'border-warning', text: 'text-warning', bg: 'bg-warning/10' },
    info: { border: 'border-info', text: 'text-info', bg: 'bg-info/10' },
    danger: { border: 'border-danger', text: 'text-danger', bg: 'bg-danger/10' },
};
</script>

<template>
    <div class="panel flex flex-col items-center gap-3 py-5">
        <template v-if="loading">
            <div class="w-20 h-20 rounded-full bg-gray-200 dark:bg-gray-700 animate-pulse"></div>
            <div class="h-4 w-20 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
        </template>
        <template v-else>
            <div
                class="w-20 h-20 rounded-full border-4 flex items-center justify-center"
                :class="[colorMap[color].border, colorMap[color].bg]"
            >
                <span class="text-3xl font-bold" :class="colorMap[color].text">{{ value }}</span>
            </div>
            <span class="text-sm font-medium text-center text-gray-600 dark:text-gray-400">{{ label }}</span>
        </template>
    </div>
</template>
