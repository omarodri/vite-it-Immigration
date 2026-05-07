<template>
    <div>
        <!-- Empty state -->
        <div v-if="localTasks.length === 0" class="text-sm text-gray-400 italic py-4 text-center">
            {{ $t('cases.lifecycle_no_tasks') }}
        </div>

        <template v-else>
            <!-- Progress bar -->
            <div class="mb-4">
                <div class="flex items-center justify-between text-sm mb-1">
                    <span class="text-gray-500">{{ $t('cases.lifecycle_title') }}</span>
                    <span class="font-semibold">{{ completedCount }} / {{ totalCount }} — {{ progressPercent }}%</span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                    <div
                        class="h-2 rounded-full transition-all"
                        :class="progressClass"
                        :style="{ width: `${progressPercent}%` }"
                    ></div>
                </div>
            </div>

            <!-- Grouped by stage -->
            <div v-for="group in groupedTasks" :key="group.stageId ?? 'ungrouped'" class="mb-5">
                <div v-if="group.stageName" class="flex items-center gap-2 mb-2">
                    <span
                        class="inline-block w-2 h-2 rounded-full"
                        :style="{ backgroundColor: stageColorMap[group.stageId!] ?? '#4361ee' }"
                    ></span>
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ group.stageName }}
                    </span>
                </div>

                <!-- Draggable in edit mode, plain list in readonly -->
                <VueDraggable
                    v-if="editable"
                    v-model="group.tasks"
                    :animation="150"
                    handle=".drag-handle"
                    @end="onDragEnd"
                    class="space-y-2"
                >
                    <div
                        v-for="task in group.tasks"
                        :key="task.id"
                        class="flex items-center gap-3 p-2 rounded border"
                        :class="isCompleted(task)
                            ? 'border-success/30 bg-success/5 dark:bg-success/10'
                            : 'border-[#e0e6ed] dark:border-[#1b2e4b]'"
                    >
                        <span class="drag-handle cursor-grab text-gray-400 shrink-0">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM8 12a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM8 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM14 6a2 2 0 1 1 4 0 2 2 0 0 1-4 0zM14 12a2 2 0 1 1 4 0 2 2 0 0 1-4 0zM14 18a2 2 0 1 1 4 0 2 2 0 0 1-4 0z"/>
                            </svg>
                        </span>
                        <input
                            type="checkbox"
                            class="form-checkbox shrink-0"
                            :checked="isCompleted(task)"
                            :disabled="toggling === task.id"
                            @change="toggle(task)"
                        />
                        <span class="flex-1 text-sm" :class="isCompleted(task) ? 'line-through text-gray-400' : ''">
                            {{ task.subject }}
                        </span>
                        <span v-if="task.priority === 'urgent'" class="badge badge-outline-danger text-xs shrink-0">
                            {{ $t('tasks.priority.urgent') }}
                        </span>
                        <span v-if="toggling === task.id" class="w-4 h-4 border-2 border-primary border-l-transparent rounded-full animate-spin shrink-0"></span>
                    </div>
                </VueDraggable>

                <div v-else class="space-y-2">
                    <div
                        v-for="task in group.tasks"
                        :key="task.id"
                        class="flex items-center gap-3 p-2 rounded border"
                        :class="isCompleted(task)
                            ? 'border-success/30 bg-success/5 dark:bg-success/10'
                            : 'border-[#e0e6ed] dark:border-[#1b2e4b]'"
                    >
                        <input
                            type="checkbox"
                            class="form-checkbox"
                            :checked="isCompleted(task)"
                            :disabled="toggling === task.id"
                            @change="toggle(task)"
                        />
                        <span class="flex-1 text-sm" :class="isCompleted(task) ? 'line-through text-gray-400' : ''">
                            {{ task.subject }}
                        </span>
                        <span v-if="task.priority" :class="getPriorityBadgeClass(task.priority)">
                            {{ $t('tasks.priority.' + task.priority) }}
                        </span>
                        <span v-if="task.due_date" :class="getDueDateBadgeClass(task.due_date)">
                            {{ new Date(task.due_date).toLocaleDateString() }}
                        </span>
                        <span v-if="toggling === task.id" class="w-4 h-4 border-2 border-primary border-l-transparent rounded-full animate-spin shrink-0"></span>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { VueDraggable } from 'vue-draggable-plus';
import type { WorkflowTask } from '@/types/workflow';
import api from '@/services/api';

const props = defineProps<{
    tasks: WorkflowTask[];
    caseId: number;
    editable?: boolean;
}>();

const emit = defineEmits<{
    (e: 'progress-updated', progress: number): void;
    (e: 'task-toggled', task: WorkflowTask): void;
}>();

const { locale } = useI18n();
const toggling = ref<number | null>(null);

// Local mutable copy for drag&drop
const localTasks = ref<WorkflowTask[]>([...props.tasks]);
watch(() => props.tasks, (val) => { localTasks.value = [...val]; }, { deep: true });

function isCompleted(task: WorkflowTask): boolean {
    return task.status === 'resolved' || task.status === 'closed';
}

function getDueDateBadgeClass(dueDate: string): string {
    const diffDays = Math.ceil((new Date(dueDate).getTime() - new Date().getTime()) / (1000 * 60 * 60 * 24));
    if (diffDays < 0) return 'badge  rounded-full badge-outline-danger';
    if (diffDays <= 7) return 'badge rounded-full badge-outline-warning';
    return 'badge rounded-full badge-outline-success';
}

function getPriorityBadgeClass(priority: WorkflowTask['priority']): string {
    const classes: Record<WorkflowTask['priority'], string> = {
        urgent: 'badge badge-outline-danger',
        high: 'badge badge-outline-warning',
        medium: 'badge badge-outline-info',
        low: 'badge badge-outline-secondary',
    };
    return classes[priority] || 'badge badge-outline-primary';
}

const completedCount = computed(() => localTasks.value.filter(isCompleted).length);
const totalCount = computed(() => localTasks.value.length);
const progressPercent = computed(() =>
    totalCount.value > 0 ? Math.round((completedCount.value / totalCount.value) * 100) : 0
);

const progressClass = computed(() => {
    const pct = progressPercent.value;
    if (pct >= 75) return 'bg-success';
    if (pct >= 50) return 'bg-info';
    if (pct >= 25) return 'bg-warning';
    return 'bg-danger';
});

const stageColorMap = computed<Record<number, string>>(() => {
    const map: Record<number, string> = {};
    for (const task of localTasks.value) {
        if (task.workflow_stage_id && task.workflow_stage) {
            map[task.workflow_stage_id] = task.workflow_stage.color ?? '#4361ee';
        }
    }
    return map;
});

interface TaskGroup {
    stageId: number | null;
    stageName: string | null;
    stageSortOrder: number;
    tasks: WorkflowTask[];
}

const groupedTasks = computed<TaskGroup[]>(() => {
    const map = new Map<number | null, TaskGroup>();

    for (const task of localTasks.value) {
        const stageId = task.workflow_stage_id ?? null;
        if (!map.has(stageId)) {
            const stage = task.workflow_stage;
            const loc = locale.value as 'es' | 'en' | 'fr';
            const name = stage
                ? (stage.translations?.['name']?.[loc]
                    ?? stage.translations?.['name']?.['es']
                    ?? null)
                : null;
            map.set(stageId, {
                stageId,
                stageName: name,
                stageSortOrder: stage?.sort_order ?? 9999,
                tasks: [],
            });
        }
        map.get(stageId)!.tasks.push(task);
    }

    return [...map.values()].sort((a, b) => a.stageSortOrder - b.stageSortOrder);
});

async function toggle(task: WorkflowTask) {
    if (toggling.value !== null) return;
    toggling.value = task.id;
    const prevStatus = task.status;
    task.status = isCompleted(task) ? 'new' : 'resolved'; // optimistic
    try {
        const response = await api.patch(
            `/cases/${props.caseId}/workflow-tasks/${task.id}/toggle`
        );
        const updated: WorkflowTask = response.data.data;
        task.status = updated.status;
        emit('progress-updated', response.data.meta.case_progress);
        emit('task-toggled', updated);
    } catch (e) {
        task.status = prevStatus; // rollback on error
        console.error('Failed to toggle workflow task', e);
    } finally {
        toggling.value = null;
    }
}

async function onDragEnd() {
    // Flatten all groups back into a single ordered list and persist sort_order
    const reordered: { id: number; sort_order: number }[] = [];
    let globalIdx = 0;
    for (const group of groupedTasks.value) {
        for (const task of group.tasks) {
            task.sort_order = globalIdx;
            reordered.push({ id: task.id, sort_order: globalIdx });
            globalIdx++;
        }
    }
    try {
        await api.patch(`/cases/${props.caseId}/workflow-tasks/reorder`, { tasks: reordered });
    } catch (e) {
        console.error('Failed to reorder tasks', e);
    }
}
</script>
