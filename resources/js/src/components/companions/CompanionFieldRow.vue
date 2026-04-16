<template>
    <div :class="span ? 'col-span-2 sm:col-span-3' : ''">
        <p class="text-[11px] font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">{{ label }}</p>
        <slot name="value">
            <p class="text-sm" :class="value ? 'text-gray-800 dark:text-gray-200' : 'text-gray-400 dark:text-gray-600 italic'">
                {{ value || '—' }}
            </p>
        </slot>
    </div>
</template>

<script lang="ts" setup>
defineProps<{
    label: string
    value?: string | number | null
    span?: boolean
}>()
</script>
