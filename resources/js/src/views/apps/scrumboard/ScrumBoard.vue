<template>
    <div :style="{ '--scrum-grace': `${COMPLETE_GRACE_MS}ms` }">
        <!-- Header -->
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <h5 class="font-semibold text-xl dark:text-white">{{ $t('scrum_board') }}</h5>
            <div class="flex items-center gap-3 flex-wrap">
                <!-- View: Board | List (Spec 71) -->
                <ScrumViewToggle :model-value="store.viewMode" @update:model-value="store.setViewMode" />
                <!-- Filter: Asignado a -->
                <div class="flex items-center gap-2">
                    <label for="scrumFilterAssignee" class="text-sm font-medium dark:text-white whitespace-nowrap">{{ $t('scrum_task_assigned_to') }}:</label>
                    <select id="scrumFilterAssignee" v-model="filterUserId" class="form-select text-sm py-1.5 min-w-[140px]">
                        <option :value="null">{{ $t('scrum_filter_all') }}</option>
                        <option :value="currentUserId">@me</option>
                        <option v-for="user in otherAssignees" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>
                </div>
                <!-- Filter: show completed (hidden by default — Spec 71 R16) -->
                <label class="inline-flex items-center gap-2 cursor-pointer text-sm font-medium dark:text-white whitespace-nowrap mb-0">
                    <input v-model="showCompleted" type="checkbox" class="form-checkbox" />
                    {{ $t('scrum_show_completed') }}
                </label>
                <!-- Add Column -->
                <button type="button" class="btn btn-primary flex" @click="openColumnModal()">
                    <icon-plus class="w-5 h-5 ltr:mr-3 rtl:ml-3" />
                    {{ $t('scrum_add_column') }}
                </button>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="store.isLoading" class="flex justify-center py-20">
            <span class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10 inline-block align-middle"></span>
        </div>

        <div v-else-if="store.columns.length === 0" class="text-center text-gray-400 py-20">
            {{ $t('scrum_no_columns') }}
        </div>

        <ScrumKanbanView v-else-if="store.viewMode === 'board'" v-bind="viewProps" v-on="viewHandlers" />
        <ScrumListView v-else v-bind="viewProps" v-on="viewHandlers" />

        <!-- Modals: single instances shared by both views (Spec 71 R4.5 / R10.3) -->
        <ScrumColumnModal :is-open="showColumnModal" :column="editingColumn" @close="showColumnModal = false" @save="saveColumn" />
        <ScrumTaskModal :is-open="showTaskModal" :task="editingTask" :column-id="taskModalColumnId" @close="showTaskModal = false" @saved="onTaskSaved" />
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import Swal from 'sweetalert2';
import { useI18n } from 'vue-i18n';
import { useScrumStore } from '@/stores/scrum';
import { useAuthStore } from '@/stores/auth';
import { useMeta } from '@/composables/use-meta';
import { resolveDropPosition, type MoveTaskPayload } from '@/utils/scrumDragPosition';
import { COMPLETE_GRACE_MS } from './scrumUi';
import ScrumKanbanView from './components/ScrumKanbanView.vue';
import ScrumListView from './components/ScrumListView.vue';
import ScrumViewToggle from './components/ScrumViewToggle.vue';
import ScrumColumnModal from './components/ScrumColumnModal.vue';
import ScrumTaskModal from './components/ScrumTaskModal.vue';
import IconPlus from '@/components/icon/icon-plus.vue';

import type { ScrumColumn, ScrumTask } from '@/types/scrum';

useMeta({ title: 'Scrumboard' });

const { t } = useI18n();
const store = useScrumStore();
const authStore = useAuthStore();

const showColumnModal = ref(false);
const editingColumn = ref<ScrumColumn | null>(null);

const showTaskModal = ref(false);
const editingTask = ref<ScrumTask | null>(null);
const taskModalColumnId = ref<number | undefined>(undefined);

onMounted(() => {
    if (authStore.user?.id) store.initUiPreferences(authStore.user.id);
    store.fetchBoard();
    store.fetchAssignees();
});

const currentUserId = computed(() => authStore.user?.id);

const otherAssignees = computed(() => store.assignees.filter((a) => a.id !== currentUserId.value));

// Filters live in the store so both views share them and they survive SPA navigation (R5/R11)
const filterUserId = computed({
    get: () => store.filterUserId,
    set: (value: number | null) => store.setFilterUserId(value),
});

const showCompleted = computed({
    get: () => store.showCompleted,
    set: (value: boolean) => store.setShowCompleted(value),
});

// Same contract for both views
const viewProps = computed(() => ({
    columns: store.columns,
    visibleTasks: store.visibleTasks,
    hiddenCompletedCount: store.hiddenCompletedCount,
    assigneeMatchCount: store.assigneeMatchCount,
    // Fade only while completed tasks are hidden; if the user shows them, nothing fades
    fadingTaskIds: store.showCompleted ? [] : store.pendingHideTaskIds,
}));

const viewHandlers = {
    'add-task': openTaskModal,
    'open-task': openEditTask,
    'clone-task': cloneTask,
    'delete-task': confirmDeleteTask,
    'toggle-task': toggleTask,
    'move-task': onMoveTask,
    'edit-column': openColumnModal,
    'delete-column': confirmDeleteColumn,
    'reorder-columns': (columns: ScrumColumn[]) => store.reorderColumns(columns),
    'show-completed': () => store.setShowCompleted(true),
};

function openColumnModal(column?: ScrumColumn) {
    editingColumn.value = column ?? null;
    showColumnModal.value = true;
}

function openTaskModal(columnId: number) {
    editingTask.value = null;
    taskModalColumnId.value = columnId;
    showTaskModal.value = true;
}

function openEditTask(task: ScrumTask) {
    editingTask.value = task;
    taskModalColumnId.value = task.scrum_column_id;
    showTaskModal.value = true;
}

function onTaskSaved(_task: ScrumTask) {
    showTaskModal.value = false;
    showMessage(t('scrum_task_saved'));
}

async function saveColumn(title: string) {
    try {
        if (editingColumn.value) {
            await store.updateColumn(editingColumn.value.id, title);
        } else {
            await store.createColumn(title);
        }
        showColumnModal.value = false;
        showMessage(t('scrum_column_saved'));
    } catch (e: any) {
        Swal.fire({ icon: 'error', title: e?.response?.data?.message ?? 'Error' });
    }
}

async function confirmDeleteColumn(column: ScrumColumn) {
    if (column.tasks && column.tasks.length > 0) {
        // A column that LOOKS empty may hold tasks hidden by the filters (R7.1)
        const looksEmpty = store.visibleTasks(column.id).length === 0;
        showMessage(looksEmpty ? t('scrum_column_has_hidden_tasks', { n: column.tasks.length }) : t('scrum_column_not_empty'), 'error');
        return;
    }

    const result = await Swal.fire({
        title: t('scrum_confirm_delete_column'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: t('delete'),
        cancelButtonText: t('cancel'),
    });
    if (!result.isConfirmed) return;
    try {
        await store.deleteColumn(column.id);
        showMessage(t('scrum_column_deleted'));
    } catch (e: any) {
        Swal.fire({ icon: 'error', title: e?.response?.data?.message ?? 'Error' });
    }
}

/** One grace timer per task: re-completing within the grace period restarts it. */
const graceTimers = new Map<number, ReturnType<typeof setTimeout>>();

function startGrace(taskId: number) {
    clearTimeout(graceTimers.get(taskId));
    store.holdTaskVisible(taskId);
    graceTimers.set(
        taskId,
        setTimeout(() => {
            graceTimers.delete(taskId);
            store.releaseTaskVisible(taskId);
        }, COMPLETE_GRACE_MS),
    );
}

function endGrace(taskId: number) {
    clearTimeout(graceTimers.get(taskId));
    graceTimers.delete(taskId);
    store.releaseTaskVisible(taskId);
}

onBeforeUnmount(() => graceTimers.forEach((timer) => clearTimeout(timer)));

/** Popup of the currently shown "Undo" toast, so we never close an unrelated dialog. */
let undoToastPopup: HTMLElement | null = null;

/**
 * Completing a task while completed tasks are hidden: keep it visible for a grace period,
 * fade it out and offer "Undo" instead of making it vanish instantly (R16.5).
 */
async function toggleTask(task: ScrumTask) {
    const hidesOnComplete = !task.is_completed && !store.showCompleted;
    if (!hidesOnComplete) {
        await store.toggleTask(task.id);
        return;
    }

    const taskId = task.id;
    startGrace(taskId);
    showUndoToast(taskId);

    await store.toggleTask(taskId);
    if (!store.findTask(taskId)?.is_completed) {
        // Server rejected the change (optimistic update reverted): nothing to hide or undo
        endGrace(taskId);
        if (undoToastPopup && Swal.getPopup() === undoToastPopup) Swal.close();
    }
}

function showUndoToast(taskId: number) {
    Swal.fire({
        didOpen: (popup) => {
            undoToastPopup = popup;
        },
        didDestroy: () => {
            undoToastPopup = null;
        },
        toast: true,
        position: 'top',
        icon: 'success',
        title: t('scrum_task_completed_toast'),
        showConfirmButton: true,
        confirmButtonText: t('scrum_undo'),
        timer: 5000,
        timerProgressBar: true,
        padding: '10px 20px',
        customClass: { container: 'toast' },
    }).then((result) => {
        if (!result.isConfirmed) return;
        endGrace(taskId);
        if (store.findTask(taskId)?.is_completed) store.toggleTask(taskId);
    });
}

async function cloneTask(task: ScrumTask) {
    try {
        await store.cloneTask(task);
        showMessage(t('scrum_task_saved'));
    } catch {
        // Error handled by api interceptor
    }
}

async function confirmDeleteTask(task: ScrumTask) {
    const result = await Swal.fire({
        title: t('scrum_confirm_delete_task'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: t('delete'),
        cancelButtonText: t('cancel'),
    });
    if (!result.isConfirmed) return;
    try {
        await store.deleteTask(task.id);
        showMessage(t('scrum_task_deleted'));
    } catch {
        // Error handled by api interceptor
    }
}

/**
 * Single entry point for drags (both views) and "Move to…". The views report a visible
 * index / special target; it is translated to a full-list position here (Spec 71 §3.2).
 */
function onMoveTask(payload: MoveTaskPayload) {
    const targetCol = store.columns.find((c) => c.id === payload.targetColumnId);
    if (!targetCol) return;

    const position = resolveDropPosition(targetCol.tasks, payload.taskId, payload.target, store.isTaskVisible);
    store.moveTask(payload.taskId, payload.targetColumnId, position);

    if (payload.viaMenu) showMessage(t('scrum_moved_to', { column: targetCol.title }));
}

function showMessage(msg = '', type = 'success') {
    const toast: any = Swal.mixin({
        toast: true,
        position: 'top',
        showConfirmButton: false,
        timer: 3000,
        customClass: { container: 'toast' },
    });
    toast.fire({
        icon: type,
        title: msg,
        padding: '10px 20px',
    });
}
</script>

<style>
/* Spec 71 R16.5 — a task completed while completed tasks are hidden stays briefly, then fades.
   Duration comes from COMPLETE_GRACE_MS (scrumUi.ts) via the --scrum-grace custom property. */
@keyframes scrum-fade-out {
    0%,
    60% {
        opacity: 1;
    }
    100% {
        opacity: 0;
    }
}
.scrum-task-fading {
    animation: scrum-fade-out var(--scrum-grace, 1.5s) ease-in forwards;
}
</style>
