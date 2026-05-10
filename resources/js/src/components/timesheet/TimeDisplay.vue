<script lang="ts" setup>
import { computed } from 'vue';
import { formatDuration } from '@/utils/timeFormat';

interface Props {
    seconds: number;
    showIcon?: boolean;
    size?: 'sm' | 'md' | 'lg';
    isRunning?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showIcon: true,
    size: 'md',
    isRunning: false,
});

const formatted = computed(() => formatDuration(props.seconds));

const sizeClasses = computed(() => {
    return {
        sm: 'text-xs',
        md: 'text-sm font-semibold',
        lg: 'text-lg font-bold',
    }[props.size];
});

const iconSize = computed(() => (props.size === 'lg' ? 'w-5 h-5' : 'w-4 h-4'));
</script>

<template>
    <div
        class="inline-flex items-center gap-1"
        :class="[sizeClasses, isRunning ? 'text-success animate-pulse' : 'text-dark dark:text-white-dark']"
    >
        <svg
            v-if="showIcon"
            xmlns="http://www.w3.org/2000/svg"
            class="shrink-0"
            :class="iconSize"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <circle cx="12" cy="12" r="10" />
            <polyline points="12 6 12 12 16 14" />
        </svg>
        <span class="tabular-nums">{{ formatted }}</span>
        <span v-if="isRunning" class="text-xs font-normal opacity-75">●</span>
    </div>
</template>
