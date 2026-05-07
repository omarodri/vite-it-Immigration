<template>
    <div v-if="stages.length" class="panel">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-white-light">
                {{ $t('workflow.roadmap') }}
            </h3>
            <span class="text-xs text-gray-500">
                {{ currentIndex >= 0 ? `${currentIndex + 1} / ${stages.length}` : '-' }}
            </span>
        </div>
        <div class="flex items-center gap-1 overflow-x-auto py-2">
            <template v-for="(stage, idx) in stages" :key="stage.id">
                <div class="flex flex-col items-center gap-1 shrink-0 min-w-[80px]">
                    <div
                        class="flex items-center justify-center w-8 h-8 rounded-full text-xs font-semibold transition"
                        :class="getCircleClasses(stage, idx)"
                        :title="stage.description ?? ''"
                    >
                        <svg v-if="isCompleted(idx)" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                        </svg>
                        <span v-else>{{ idx + 1 }}</span>
                    </div>
                    <span
                        class="text-xs text-center leading-tight max-w-[120px]"
                        :class="stage.id === currentStageId
                            ? 'font-semibold text-primary dark:text-primary-light'
                            : 'text-gray-500'"
                    >
                        {{ stage.name ?? stage.code }}
                    </span>
                </div>
                <div
                    v-if="idx < stages.length - 1"
                    class="h-0.5 flex-1 min-w-[24px] -mt-5"
                    :class="isCompleted(idx) ? 'bg-success' : 'bg-gray-200 dark:bg-gray-700'"
                />
            </template>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue';
import type { WorkflowSnapshot, WorkflowSnapshotStage } from '@/types/workflow';

const props = defineProps<{
    snapshot: WorkflowSnapshot | null;
    currentStageId: number | null;
}>();

const stages = computed<WorkflowSnapshotStage[]>(() => props.snapshot?.stages ?? []);

const currentIndex = computed(() =>
    stages.value.findIndex(s => s.id === props.currentStageId)
);

function isCompleted(idx: number): boolean {
    if (currentIndex.value < 0) return false;
    return idx < currentIndex.value;
}

function getCircleClasses(stage: WorkflowSnapshotStage, idx: number): string {
    if (isCompleted(idx)) return 'bg-success text-white';
    if (stage.id === props.currentStageId) {
        return `bg-${stage.color || 'primary'} text-white ring-4 ring-primary/20`;
    }
    return 'bg-gray-200 dark:bg-gray-700 text-gray-500';
}
</script>
