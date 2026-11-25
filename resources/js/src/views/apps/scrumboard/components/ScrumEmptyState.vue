<template>
    <!--
        Spec 71 R15 — rendered as an overlay on top of an empty task list.
        pointer-events-none keeps the list underneath droppable; only the link is clickable.
    -->
    <div class="pointer-events-none flex flex-col items-center justify-center gap-1 text-center text-sm text-gray-400 px-2">
        <template v-if="total === 0">{{ $t('scrum_no_tasks') }}</template>
        <template v-else-if="hiddenCompleted > 0 && hiddenCompleted === matching">
            <span>{{ $t('scrum_all_tasks_completed') }}</span>
            <button type="button" class="pointer-events-auto text-primary hover:underline" @click="emit('show-completed')">
                {{ $t('scrum_show_completed') }}
            </button>
        </template>
        <template v-else>{{ $t('scrum_no_tasks_filtered') }}</template>
    </div>
</template>

<script lang="ts" setup>
defineProps<{
    /** All tasks in the column, visible or not */
    total: number;
    /** Tasks passing the assignee filter (same base as hiddenCompleted) */
    matching: number;
    /** Completed tasks hidden by the "show completed" filter (assignee filter applied) */
    hiddenCompleted: number;
}>();

const emit = defineEmits<{ 'show-completed': [] }>();
</script>
