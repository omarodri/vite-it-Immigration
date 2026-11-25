<template>
    <!-- Spec 71 — board (kanban) view, extracted from ScrumBoard.vue. Emits only; never mutates the store. -->
    <div ref="rootEl" class="relative pt-5">
        <div class="h-full -mx-2">
            <div class="overflow-x-auto pb-2">
                <draggable
                    :model-value="columns"
                    group="columns"
                    handle=".column-drag-handle"
                    :animation="200"
                    class="flex items-start flex-nowrap gap-5 px-2"
                    @update:model-value="(cols: ScrumColumn[]) => emit('reorder-columns', cols)"
                >
                    <div v-for="column in columns" :key="column.id" class="panel w-80 flex-none">
                        <!-- Column header -->
                        <div class="flex justify-between mb-5">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="column-drag-handle cursor-grab text-gray-400 hover:text-gray-600">
                                    <icon-menu class="w-4 h-4" />
                                </span>
                                <h4 class="text-base font-semibold truncate outline-none" tabindex="-1" :data-column-focus="column.id">{{ column.title }}</h4>
                                <span class="badge bg-gray-200 dark:bg-gray-700 text-xs">{{ visibleTasks(column.id).length }}</span>
                                <span
                                    v-if="hiddenCompletedCount(column.id) > 0"
                                    class="flex items-center gap-0.5 text-xs text-gray-400 shrink-0"
                                    :title="$t('scrum_hidden_completed', { n: hiddenCompletedCount(column.id) })"
                                >
                                    <icon-circle-check class="w-3.5 h-3.5" />
                                    {{ hiddenCompletedCount(column.id) }}
                                </span>
                            </div>
                            <div class="flex items-center">
                                <button type="button" class="hover:text-primary ltr:mr-2 rtl:ml-2" :aria-label="$t('scrum_add_task')" @click="emit('add-task', column.id)">
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
                                                    <a href="javascript:;" @click="emit('edit-column', column)">{{ $t('scrum_edit_column') }}</a>
                                                </li>
                                                <li>
                                                    <a href="javascript:;" class="text-danger" @click="emit('delete-column', column)">{{ $t('scrum_delete_column') }}</a>
                                                </li>
                                            </ul>
                                        </template>
                                    </Popper>
                                </div>
                            </div>
                        </div>

                        <!-- Task list: one-way bound to the visible tasks; moves go through move-task -->
                        <div class="relative">
                            <draggable
                                :key="`tasks-${column.id}-${listsKey}`"
                                :model-value="visibleTasks(column.id)"
                                group="tasks"
                                :animation="200"
                                ghost-class="sortable-ghost"
                                drag-class="sortable-drag"
                                class="connect-sorting-content min-h-[150px]"
                                :data-column-id="column.id"
                                @start="onDragStart"
                                @end="onDragEnd"
                            >
                                <ScrumTaskCard
                                    v-for="task in visibleTasks(column.id)"
                                    :key="task.id"
                                    :data-task-id="task.id"
                                    :task="task"
                                    :columns="columns"
                                    :fading="fadingTaskIds.includes(task.id)"
                                    @open="openTask"
                                    @edit="(t) => emit('open-task', t)"
                                    @clone="(t) => emit('clone-task', t)"
                                    @delete="(t) => emit('delete-task', t)"
                                    @toggle="(t) => emit('toggle-task', t)"
                                    @move="(id) => moveVia(task, id)"
                                />
                            </draggable>
                            <ScrumEmptyState
                                v-if="visibleTasks(column.id).length === 0"
                                class="absolute inset-0"
                                :total="column.tasks.length"
                                :matching="assigneeMatchCount(column.id)"
                                :hidden-completed="hiddenCompletedCount(column.id)"
                                @show-completed="emit('show-completed')"
                            />
                        </div>

                        <div class="pt-3">
                            <button type="button" class="btn btn-primary mx-auto" @click="emit('add-task', column.id)">
                                <icon-plus />
                                {{ $t('scrum_add_task') }}
                            </button>
                        </div>
                    </div>
                </draggable>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref } from 'vue';
import { VueDraggable as draggable } from 'vue-draggable-plus';
import { useAppStore } from '@/stores/index';
import ScrumTaskCard from './ScrumTaskCard.vue';
import ScrumEmptyState from './ScrumEmptyState.vue';
import IconPlus from '@/components/icon/icon-plus.vue';
import IconPlusCircle from '@/components/icon/icon-plus-circle.vue';
import IconHorizontalDots from '@/components/icon/icon-horizontal-dots.vue';
import IconMenu from '@/components/icon/icon-menu.vue';
import IconCircleCheck from '@/components/icon/icon-circle-check.vue';
import type { MoveTaskPayload } from '@/utils/scrumDragPosition';
import { useScrumViewInteractions } from '../scrumUi';
import type { ScrumColumn, ScrumTask } from '@/types/scrum';

const props = defineProps<{
    columns: ScrumColumn[];
    visibleTasks: (columnId: number) => ScrumTask[];
    hiddenCompletedCount: (columnId: number) => number;
    assigneeMatchCount: (columnId: number) => number;
    fadingTaskIds: number[];
}>();

const emit = defineEmits<{
    'add-task': [columnId: number];
    'open-task': [task: ScrumTask];
    'clone-task': [task: ScrumTask];
    'delete-task': [task: ScrumTask];
    'toggle-task': [task: ScrumTask];
    'move-task': [payload: MoveTaskPayload];
    'edit-column': [column: ScrumColumn];
    'delete-column': [column: ScrumColumn];
    'reorder-columns': [columns: ScrumColumn[]];
    'show-completed': [];
}>();

const appStore = useAppStore();
const rootEl = ref<HTMLElement | null>(null);

// listsKey remounts the task lists after every drag — see DRAG_REMOUNT_NOTE in scrumUi.ts
const { listsKey, openTask, onDragStart, onDragEnd, moveVia } = useScrumViewInteractions({
    rootEl,
    visibleTasks: () => props.visibleTasks,
    emitOpen: (task) => emit('open-task', task),
    emitMove: (payload) => emit('move-task', payload),
});
</script>
