<template>
    <div>
        <!-- Header -->
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <h5 class="font-semibold text-xl dark:text-white">{{ $t('scrum_board') }}</h5>
            <div class="flex items-center gap-3">
                <!-- Filter: Asignado a -->
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium dark:text-white whitespace-nowrap">{{ $t('scrum_task_assigned_to') }}:</label>
                    <select v-model="filterUserId" class="form-select text-sm py-1.5 min-w-[140px]">
                        <option :value="null">{{ $t('scrum_filter_all') }}</option>
                        <option :value="currentUserId">@me</option>
                        <option v-for="user in otherAssignees" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>
                </div>
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

        <!-- Board -->
        <div v-else class="relative pt-5">
            <div class="h-full -mx-2">
                <div class="overflow-x-auto pb-2">
                    <draggable
                        v-model="store.columns"
                        group="columns"
                        handle=".column-drag-handle"
                        :animation="200"
                        item-key="id"
                        class="flex items-start flex-nowrap gap-5 px-2"
                        @end="onColumnDragEnd"
                    >
                        <template v-for="column in store.columns" :key="column.id">
                            <div class="panel w-80 flex-none">
                                <!-- Column header -->
                                <div class="flex justify-between mb-5">
                                    <div class="flex items-center gap-2">
                                        <span class="column-drag-handle cursor-grab text-gray-400 hover:text-gray-600">
                                            <icon-menu class="w-4 h-4" />
                                        </span>
                                        <h4 class="text-base font-semibold">{{ column.title }}</h4>
                                        <span class="badge bg-gray-200 dark:bg-gray-700 text-xs">{{ getVisibleTasks(column).length }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <button type="button" class="hover:text-primary ltr:mr-2 rtl:ml-2" @click="openTaskModal(column.id)">
                                            <icon-plus-circle />
                                        </button>
                                        <div class="dropdown">
                                            <Popper :placement="appStore.rtlClass === 'rtl' ? 'bottom-start' : 'bottom-end'" offsetDistance="0">
                                                <button type="button" class="hover:text-primary">
                                                    <icon-horizontal-dots class="opacity-70 hover:opacity-100" />
                                                </button>
                                                <template #content="{ close }">
                                                    <ul @click="close()" class="text-black dark:text-white-dark whitespace-nowrap">
                                                        <li>
                                                            <a href="javascript:;" @click="openColumnModal(column)">{{ $t('scrum_edit_column') }}</a>
                                                        </li>
                                                        <li>
                                                            <a href="javascript:;" class="text-danger" @click="confirmDeleteColumn(column)">{{ $t('scrum_delete_column') }}</a>
                                                        </li>
                                                    </ul>
                                                </template>
                                            </Popper>
                                        </div>
                                    </div>
                                </div>

                                <!-- Task list -->
                                <draggable
                                    v-model="column.tasks"
                                    group="tasks"
                                    :animation="200"
                                    ghost-class="sortable-ghost"
                                    drag-class="sortable-drag"
                                    class="connect-sorting-content min-h-[150px]"
                                    :data-column-id="column.id"
                                    @end="onTaskDragEnd"
                                >
                                    <ScrumTaskCard
                                        v-for="task in getVisibleTasks(column)"
                                        :key="task.id"
                                        :task="task"
                                        @edit="openEditTask"
                                        @clone="cloneTask"
                                        @delete="confirmDeleteTask"
                                        @toggle="toggleTask"
                                    />
                                </draggable>

                                <div class="pt-3">
                                    <button type="button" class="btn btn-primary mx-auto" @click="openTaskModal(column.id)">
                                        <icon-plus />
                                        {{ $t('scrum_add_task') }}
                                    </button>
                                </div>
                            </div>
                        </template>
                    </draggable>
                </div>
            </div>

            <div v-if="store.columns.length === 0" class="text-center text-gray-400 py-20">
                {{ $t('scrum_no_columns') }}
            </div>
        </div>

        <!-- Column Modal -->
        <ScrumColumnModal :is-open="showColumnModal" :column="editingColumn" @close="showColumnModal = false" @save="saveColumn" />

        <!-- Task Modal -->
        <ScrumTaskModal :is-open="showTaskModal" :task="editingTask" :column-id="taskModalColumnId" @close="showTaskModal = false" @saved="onTaskSaved" />
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, onMounted } from 'vue';
import { VueDraggable as draggable } from 'vue-draggable-plus';
import Swal from 'sweetalert2';
import { useI18n } from 'vue-i18n';
import { useAppStore } from '@/stores/index';
import { useScrumStore } from '@/stores/scrum';
import { useAuthStore } from '@/stores/auth';
import { useMeta } from '@/composables/use-meta';
import ScrumTaskCard from './components/ScrumTaskCard.vue';
import ScrumColumnModal from './components/ScrumColumnModal.vue';
import ScrumTaskModal from './components/ScrumTaskModal.vue';

import IconPlus from '@/components/icon/icon-plus.vue';
import IconPlusCircle from '@/components/icon/icon-plus-circle.vue';
import IconHorizontalDots from '@/components/icon/icon-horizontal-dots.vue';
import IconMenu from '@/components/icon/icon-menu.vue';

import type { ScrumColumn, ScrumTask } from '@/types/scrum';

useMeta({ title: 'Scrumboard' });

const { t } = useI18n();
const appStore = useAppStore();
const store = useScrumStore();
const authStore = useAuthStore();

const filterUserId = ref<number | null>(null);

const showColumnModal = ref(false);
const editingColumn = ref<ScrumColumn | null>(null);

const showTaskModal = ref(false);
const editingTask = ref<ScrumTask | null>(null);
const taskModalColumnId = ref<number | undefined>(undefined);

onMounted(() => {
    store.fetchBoard();
    store.fetchAssignees();
});

const currentUserId = computed(() => authStore.user?.id);

const otherAssignees = computed(() =>
    store.assignees.filter((a) => a.id !== currentUserId.value)
);

function getVisibleTasks(column: ScrumColumn): ScrumTask[] {
    if (filterUserId.value !== null) {
        return column.tasks.filter((t) => t.assigned_to?.id === filterUserId.value);
    }
    return column.tasks;
}

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
        showMessage(t('scrum_column_not_empty'), 'error');
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

async function toggleTask(task: ScrumTask) {
    await store.toggleTask(task.id);
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

function onColumnDragEnd() {
    store.reorderColumns(store.columns);
}

function onTaskDragEnd(evt: any) {
    const targetColumnEl = evt.to;
    const targetColumnId = parseInt(targetColumnEl.dataset.columnId);
    const newIndex = evt.newIndex;

    // vue-draggable-plus already updated the v-model arrays
    const targetCol = store.columns.find((c) => c.id === targetColumnId);
    if (!targetCol) return;

    const movedTask = targetCol.tasks[newIndex];
    if (!movedTask) return;

    store.moveTask(movedTask.id, targetColumnId, newIndex);
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
