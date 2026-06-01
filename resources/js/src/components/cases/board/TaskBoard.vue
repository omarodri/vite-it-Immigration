<template>
    <div>
        <!-- Live region for accessibility announcements -->
        <div class="sr-only" aria-live="polite" aria-atomic="true">{{ liveAnnouncement }}</div>

        <VueDraggable
            v-if="editable"
            v-model="draggableStages"
            :animation="150"
            :group="{ name: 'case-stages', pull: false, put: false }"
            handle=".stage-drag-handle"
            class="flex gap-4 overflow-x-auto pb-4 items-start"
            @end="onStageReorderEnd"
        >
            <StageColumn
                v-for="cs in draggableStages"
                :key="cs.id"
                :case-stage="cs"
                :tasks="tasksStore.tasksByStage[cs.id] ?? []"
                :case-id="caseId"
                :editable="true"
                :pending-ids="tasksStore.pendingTaskIds"
                :all-stages="stageOptionsForCard"
                :highlighted-task-id="tasksStore.recentlyClonedTaskId"
                @task-moved="onTaskMoved"
                @add-task="onAddTask"
                @toggle="onToggle"
                @delete="onDelete"
                @move-to-stage="onMoveToStage"
                @keyboard-move="onKeyboardMove"
                @local-reorder="onLocalReorder"
                @edit-stage="onStageEdit"
                @delete-stage="onStageDeleteRequest"
                @edit-task="onEditTask"
                @clone-task="onCloneTask"
            />
            <AddStageButton :case-id="caseId" @open-modal="openStageEditor(null)" />
        </VueDraggable>

        <div v-else class="flex gap-4 overflow-x-auto pb-4 items-start">
            <StageColumn
                v-for="cs in displayedStages"
                :key="cs.id"
                :case-stage="cs"
                :tasks="tasksStore.tasksByStage[cs.id] ?? []"
                :case-id="caseId"
                :editable="false"
                :pending-ids="tasksStore.pendingTaskIds"
                :all-stages="stageOptionsForCard"
                :highlighted-task-id="tasksStore.recentlyClonedTaskId"
                @toggle="onToggle"
                @delete="onDelete"
            />

            <!-- Ungrouped tasks (case_stage_id = null) — readonly view -->
            <StageColumn
                v-if="(tasksStore.tasksByStage[0] ?? []).length > 0"
                :stage="ungroupedStage"
                :tasks="tasksStore.tasksByStage[0] ?? []"
                :case-id="caseId"
                :editable="false"
                :pending-ids="tasksStore.pendingTaskIds"
                :all-stages="stageOptionsForCard"
                :highlighted-task-id="tasksStore.recentlyClonedTaskId"
                @toggle="onToggle"
                @delete="onDelete"
            />
        </div>

        <!-- Modals -->
        <NewTaskModal
            v-model="modalOpen"
            :stage-id="modalStageId"
            :case-id="caseId"
            @created="onTaskCreated"
        />

        <StageEditorModal
            v-model="stageEditorOpen"
            :stage="editingStage"
            :case-id="caseId"
        />

        <DeleteStagePolicyModal
            v-model="deleteModalOpen"
            :stage="deletingStage"
            :sibling-stages="siblingStagesForDelete"
            :tasks-count="deletingStageTaskCount"
            @confirm="onStageDeleteConfirm"
        />

        <WorkflowTaskEditModal
            v-model="editModalOpen"
            :task="editingTask"
            :case-id="caseId"
            :users="staffUsers"
            @saved="onTaskSaved"
        />
    </div>
</template>

<script lang="ts" setup>
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import { VueDraggable } from 'vue-draggable-plus';
import { useCaseTasksStore } from '@/stores/useCaseTasksStore';
import { useCaseStagesStore } from '@/stores/useCaseStagesStore';
import type { WorkflowTask, CaseStage, DeleteStagePolicy } from '@/types/workflow';
import StageColumn from './StageColumn.vue';
import NewTaskModal from './NewTaskModal.vue';
import StageEditorModal from './StageEditorModal.vue';
import DeleteStagePolicyModal from './DeleteStagePolicyModal.vue';
import AddStageButton from './AddStageButton.vue';
import WorkflowTaskEditModal from './WorkflowTaskEditModal.vue';
import api from '@/services/api';
import userService from '@/services/userService';
import { useNotification } from '@/composables/useNotification';
import Swal from 'sweetalert2';

interface StageInput {
    id: number;
    name: string;
    color?: string;
    sort_order?: number;
}

const props = defineProps<{
    caseId: number;
    stages?: StageInput[];
    tasks: WorkflowTask[];
    editable: boolean;
}>();

const emit = defineEmits<{
    (e: 'progress-updated', progress: number): void;
    (e: 'delete-template-task-request', task: WorkflowTask): void;
}>();

const tasksStore = useCaseTasksStore();
const stagesStore = useCaseStagesStore();
const { t } = useI18n();
const { error: notifyError, success: notifySuccess } = useNotification();

const modalOpen = ref(false);
const modalStageId = ref<number | null>(null);
const liveAnnouncement = ref('');

// Stage editor modal
const stageEditorOpen = ref(false);
const editingStage = ref<CaseStage | null>(null);

// Delete stage modal
const deleteModalOpen = ref(false);
const deletingStage = ref<CaseStage | null>(null);

// Edit task modal
const editModalOpen = ref(false);
const editingTask = ref<WorkflowTask | null>(null);
const staffUsers = ref<{ id: number; name: string }[]>([]);

// Local mirror for VueDraggable v-model on stages (it mutates the array on drag)
const draggableStages = ref<CaseStage[]>([]);

const displayedStages = computed<CaseStage[]>(() => {
    if (stagesStore.stages.length > 0) {
        return [...stagesStore.stages].sort((a, b) => a.sort_order - b.sort_order);
    }
    // Fallback: legacy stages prop adapted to CaseStage shape
    if (!props.stages?.length) return [];
    return [...props.stages]
        .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
        .map<CaseStage>((s) => ({
            id: s.id,
            case_id: props.caseId,
            workflow_stage_id: s.id,
            is_ad_hoc: false,
            code: '',
            sort_order: s.sort_order ?? 0,
            is_terminal: false,
            color: s.color || 'primary',
            name: { es: s.name },
            description: {},
            name_resolved: s.name,
        }));
});

const stageOptionsForCard = computed(() =>
    displayedStages.value.map((s) => ({
        id: s.id,
        name: s.name_resolved,
        color: s.color,
    })),
);

const ungroupedStage = computed(() => ({
    id: 0,
    name: t('cases.no_stage'),
    color: '#9ca3af',
}));

const siblingStagesForDelete = computed(() =>
    deletingStage.value
        ? displayedStages.value.filter((s) => s.id !== deletingStage.value!.id)
        : [],
);

const deletingStageTaskCount = computed(() => {
    if (!deletingStage.value) return 0;
    return (tasksStore.tasksByStage[deletingStage.value.id] ?? []).length;
});

watch(
    displayedStages,
    (val) => {
        draggableStages.value = [...val];
    },
    { immediate: true },
);

onMounted(async () => {
    tasksStore.loadTasks(props.tasks);
    if (props.editable) {
        await stagesStore.load(props.caseId);
        // Load staff for assignee dropdown (silent failure)
        try {
            const staff = await userService.getStaff(null, 'tasks.create', ['admin', 'user']);
            staffUsers.value = staff.map((u) => ({ id: u.id, name: u.name }));
        } catch {
            staffUsers.value = [];
        }
    }
});

onBeforeUnmount(() => {
    tasksStore.reset();
    stagesStore.reset();
});

watch(
    () => props.tasks,
    (val) => {
        tasksStore.loadTasks(val);
    },
);

function announce(text: string) {
    liveAnnouncement.value = '';
    setTimeout(() => {
        liveAnnouncement.value = text;
    }, 30);
}

function stageNameById(id: number): string {
    return stageOptionsForCard.value.find((s) => s.id === id)?.name ?? '—';
}

async function onTaskMoved(payload: {
    taskId: number;
    sourceStageId: number;
    targetStageId: number;
    targetIndex: number;
}) {
    try {
        await tasksStore.moveTask(
            props.caseId,
            payload.taskId,
            payload.sourceStageId,
            payload.targetStageId,
            payload.targetIndex,
        );
        announce(`Tarea movida a ${stageNameById(payload.targetStageId)}`);
    } catch {
        // already handled
    }
}

function onLocalReorder(_: { stageId: number; tasks: WorkflowTask[] }) {
    // VueDraggable v-model handles the local mutation; @update triggers persist.
}

function onAddTask(stageId: number) {
    modalStageId.value = stageId;
    modalOpen.value = true;
}

function onTaskCreated() {
    // Modal closes itself.
}

async function onToggle(task: WorkflowTask) {
    try {
        const response = await api.patch(`/cases/${props.caseId}/workflow-tasks/${task.id}/toggle`);
        const updated: WorkflowTask = response.data.data;
        Object.assign(task, updated);
        if (response.data?.meta?.case_progress != null) {
            emit('progress-updated', response.data.meta.case_progress);
        }
    } catch (e: any) {
        notifyError(e?.response?.data?.message || 'No se pudo actualizar la tarea');
    }
}

async function onDelete(task: WorkflowTask) {
    // If the task originates from a template (workflow), the parent must intercept
    // to confirm the cascade strategy for the linked operational todo.
    if (task.task_template_id) {
        emit('delete-template-task-request', task);
        return;
    }
    const result = await Swal.fire({
        title: t('cases.task.confirm_delete_title'),
        text: t('cases.task.confirm_delete_text'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e7515a',
        cancelButtonColor: '#3085d6',
        confirmButtonText: t('cases.delete'),
        cancelButtonText: t('cases.cancel'),
        reverseButtons: true,
        padding: '2em',
    });
    if (!result.isConfirmed) return;
    try {
        await tasksStore.deleteTask(props.caseId, task.id);
    } catch {
        // handled
    }
}

async function onMoveToStage(payload: { task: WorkflowTask; targetStageId: number }) {
    const sourceStageId =
        payload.task.case_stage_id ?? payload.task.workflow_stage_id ?? payload.targetStageId;
    const targetTasks = tasksStore.tasksByStage[payload.targetStageId] ?? [];
    await onTaskMoved({
        taskId: payload.task.id,
        sourceStageId,
        targetStageId: payload.targetStageId,
        targetIndex: targetTasks.length,
    });
}

async function onKeyboardMove(payload: { task: WorkflowTask; direction: 'up' | 'down' }) {
    const stageIdRaw = payload.task.case_stage_id ?? payload.task.workflow_stage_id ?? 0;
    const arr = tasksStore.tasksByStage[stageIdRaw] ?? [];
    const idx = arr.findIndex((t) => t.id === payload.task.id);
    if (idx < 0) return;
    const newIdx = payload.direction === 'up' ? idx - 1 : idx + 1;
    if (newIdx < 0 || newIdx >= arr.length) return;
    if (!stageIdRaw) return;
    await onTaskMoved({
        taskId: payload.task.id,
        sourceStageId: stageIdRaw,
        targetStageId: stageIdRaw,
        targetIndex: newIdx,
    });
}

// === Stage editor handlers ===

function openStageEditor(stage: CaseStage | null) {
    editingStage.value = stage;
    stageEditorOpen.value = true;
}

function onStageEdit(stage: CaseStage) {
    openStageEditor(stage);
}

function onStageDeleteRequest(stage: CaseStage) {
    deletingStage.value = stage;
    deleteModalOpen.value = true;
}

async function onStageDeleteConfirm(payload: { policy: DeleteStagePolicy; moveToStageId?: number }) {
    if (!deletingStage.value) return;
    const ok = await stagesStore.deleteStage(
        props.caseId,
        deletingStage.value.id,
        payload.policy,
        payload.moveToStageId,
    );
    if (ok) {
        deletingStage.value = null;
    }
}

async function performTemplateTaskDelete(taskId: number, cascadeStrategy?: string) {
    try {
        await api.delete(`/cases/${props.caseId}/workflow-tasks/${taskId}`, {
            data: cascadeStrategy ? { cascade_todo: cascadeStrategy } : undefined,
        });
        // Local optimistic removal across all stage buckets.
        const next: Record<number, WorkflowTask[]> = {};
        for (const [k, arr] of Object.entries(tasksStore.tasksByStage)) {
            next[Number(k)] = arr.filter((t) => t.id !== taskId);
        }
        tasksStore.tasksByStage = next;
    } catch (e: any) {
        notifyError(e?.response?.data?.message || 'No se pudo eliminar la tarea');
    }
}

defineExpose({ performTemplateTaskDelete });

// === Edit / Clone task handlers ===

function onEditTask(task: WorkflowTask) {
    editingTask.value = task;
    editModalOpen.value = true;
}

async function onCloneTask(task: WorkflowTask) {
    const cloned = await tasksStore.cloneTask(props.caseId, task.id);
    if (cloned) {
        notifySuccess(t('workflow_task.clone_success'));
        announce(t('workflow_task.clone_success'));
    }
}

function onTaskSaved(_task: WorkflowTask) {
    notifySuccess(t('workflow_task.update_success'));
    announce(t('workflow_task.update_success'));
}

async function onStageReorderEnd() {
    const ids = draggableStages.value.map((s) => s.id);
    try {
        await stagesStore.reorder(props.caseId, ids);
    } catch {
        // store reverted state
        draggableStages.value = [...stagesStore.stages].sort((a, b) => a.sort_order - b.sort_order);
    }
}
</script>
