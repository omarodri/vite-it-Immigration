<template>
    <div
        class="stage-column min-w-[300px] w-[300px] max-w-[300px] flex flex-col bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-md"
        role="list"
        :aria-label="stageName"
    >
        <!-- Header (editable Spec 48 vs simple) -->
        <StageHeaderEditable
            v-if="caseStage"
            :stage="caseStage"
            :editable="editable"
            :task-count="tasks.length"
            @edit="$emit('edit-stage', caseStage)"
            @delete="$emit('delete-stage', caseStage)"
        />
        <div v-else class="flex items-center gap-2 px-3 py-2 border-b border-gray-200 dark:border-gray-700">
            <span
                class="inline-block w-2.5 h-2.5 rounded-full shrink-0"
                :style="{ backgroundColor: stageColorHex }"
            ></span>
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-200 truncate">
                {{ stageName }}
            </span>
            <span class="ml-auto text-[11px] text-gray-500 dark:text-gray-400">{{ tasks.length }}</span>
        </div>

        <!-- Body / draggable -->
        <div class="flex-1 p-2 min-h-[60px]">
            <VueDraggable
                v-if="editable"
                v-model="localTasks"
                :group="{ name: 'case-tasks', pull: true, put: true }"
                :animation="150"
                handle=".task-drag-handle"
                ghost-class="opacity-50"
                class="space-y-2 min-h-[40px]"
                @add="onAdd"
                @update="onUpdate"
            >
                <TaskCard
                    v-for="element in localTasks"
                    :key="element.id"
                    :task="element"
                    :pending="pendingIds.has(element.id)"
                    :editable="editable"
                    :stages="otherStagesForCard"
                    :highlighted="element.id === highlightedTaskId"
                    @toggle="$emit('toggle', $event)"
                    @delete="$emit('delete', $event)"
                    @move-to-stage="$emit('move-to-stage', $event)"
                    @keyboard-move="$emit('keyboard-move', $event)"
                    @edit="$emit('edit-task', $event)"
                    @clone="$emit('clone-task', $event)"
                />
            </VueDraggable>

            <div v-else class="space-y-2">
                <TaskCard
                    v-for="t in tasks"
                    :key="t.id"
                    :task="t"
                    :pending="pendingIds.has(t.id)"
                    :editable="false"
                    :stages="otherStagesForCard"
                    :highlighted="t.id === highlightedTaskId"
                />
            </div>

            <p
                v-if="tasks.length === 0"
                class="text-xs text-gray-400 italic text-center py-4"
            >
                {{ $t('cases.task.empty_stage') }}
            </p>
        </div>

        <!-- Add task -->
        <div v-if="editable" class="p-2 border-t border-gray-200 dark:border-gray-700">
            <button
                type="button"
                class="w-full text-sm text-primary hover:bg-primary/10 rounded px-2 py-1.5 text-left flex items-center gap-1"
                @click="$emit('add-task', stageId)"
            >
                <icon-plus class="w-4 h-4" />
                <span>{{ $t('cases.task.add_new') }}</span>
            </button>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { computed, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import type { WorkflowTask, CaseStage } from '@/types/workflow';
import TaskCard from './TaskCard.vue';
import StageHeaderEditable from './StageHeaderEditable.vue';
import IconPlus from '@/components/icon/icon-plus.vue';

interface StageOption {
    id: number;
    name: string;
    color?: string;
}

const props = defineProps<{
    stage?: StageOption;
    caseStage?: CaseStage | null;
    tasks: WorkflowTask[];
    caseId: number;
    editable: boolean;
    pendingIds: Set<number>;
    allStages: StageOption[];
    highlightedTaskId?: number | null;
}>();

const emit = defineEmits<{
    (e: 'task-moved', payload: { taskId: number; sourceStageId: number; targetStageId: number; targetIndex: number }): void;
    (e: 'add-task', stageId: number): void;
    (e: 'toggle', task: WorkflowTask): void;
    (e: 'delete', task: WorkflowTask): void;
    (e: 'move-to-stage', payload: { task: WorkflowTask; targetStageId: number }): void;
    (e: 'keyboard-move', payload: { task: WorkflowTask; direction: 'up' | 'down' }): void;
    (e: 'local-reorder', payload: { stageId: number; tasks: WorkflowTask[] }): void;
    (e: 'edit-stage', stage: CaseStage): void;
    (e: 'delete-stage', stage: CaseStage): void;
    (e: 'edit-task', task: WorkflowTask): void;
    (e: 'clone-task', task: WorkflowTask): void;
}>();

const COLOR_HEX: Record<string, string> = {
    primary: '#4361ee',
    secondary: '#805dca',
    success: '#00ab55',
    danger: '#e7515a',
    warning: '#e2a03f',
    info: '#2196f3',
    dark: '#3b3f5c',
};

const stageId = computed(() => props.caseStage?.id ?? props.stage?.id ?? 0);
const stageName = computed(() => props.caseStage?.name_resolved ?? props.stage?.name ?? '');
const stageColorHex = computed(() => {
    const c = props.caseStage?.color ?? props.stage?.color ?? '#4361ee';
    return COLOR_HEX[c] ?? c;
});

const otherStagesForCard = computed(() => props.allStages);

const localTasks = ref<WorkflowTask[]>([...props.tasks]);
watch(
    () => props.tasks,
    (val) => {
        localTasks.value = [...val];
    },
    { deep: false },
);
watch(
    localTasks,
    (val) => {
        emit('local-reorder', { stageId: stageId.value, tasks: val });
    },
    { deep: false },
);

function onAdd(evt: any) {
    const task: WorkflowTask | undefined =
        evt?.data ?? evt?.item?.__draggable_context?.element ?? undefined;
    const newIndex: number = evt?.newIndex ?? evt?.newDraggableIndex ?? 0;
    if (!task) return;
    const sourceStageId = task.case_stage_id ?? task.workflow_stage_id ?? stageId.value;
    emit('task-moved', {
        taskId: task.id,
        sourceStageId,
        targetStageId: stageId.value,
        targetIndex: newIndex,
    });
}

function onUpdate(evt: any) {
    const task: WorkflowTask | undefined =
        evt?.data ?? evt?.item?.__draggable_context?.element ?? undefined;
    const newIndex: number = evt?.newIndex ?? evt?.newDraggableIndex ?? 0;
    if (!task) return;
    emit('task-moved', {
        taskId: task.id,
        sourceStageId: stageId.value,
        targetStageId: stageId.value,
        targetIndex: newIndex,
    });
}
</script>
