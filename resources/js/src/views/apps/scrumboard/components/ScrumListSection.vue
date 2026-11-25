<template>
    <!-- Spec 71 R13 — one column rendered as a collapsible list section -->
    <section class="panel p-0" :aria-labelledby="titleId">
        <header class="relative flex items-center gap-2 px-4 py-3" :class="{ 'border-b border-white-light dark:border-[#1b2e4b]': !collapsed }">
            <span class="scrum-section-handle cursor-grab text-gray-400 hover:text-gray-600 shrink-0" :title="$t('scrum_section_reorder')">
                <icon-menu class="w-4 h-4" />
            </span>

            <button
                type="button"
                class="flex items-center gap-2 min-w-0 text-start outline-none focus-visible:ring-2 focus-visible:ring-primary rounded"
                :data-column-focus="column.id"
                :aria-expanded="!collapsed"
                :aria-controls="bodyId"
                :title="collapsed ? $t('scrum_section_expand') : $t('scrum_section_collapse')"
                @click="emit('toggle-collapse', column.id)"
            >
                <icon-caret-down class="w-4 h-4 shrink-0 transition-transform" :class="{ 'ltr:-rotate-90 rtl:rotate-90': collapsed }" />
                <h4 :id="titleId" class="text-base font-semibold truncate">{{ column.title }}</h4>
            </button>

            <span class="badge bg-gray-200 dark:bg-gray-700 text-xs shrink-0">{{ tasks.length }}</span>
            <span v-if="hiddenCompleted > 0" class="flex items-center gap-0.5 text-xs text-gray-400 shrink-0" :title="$t('scrum_hidden_completed', { n: hiddenCompleted })">
                <icon-circle-check class="w-3.5 h-3.5" />
                {{ hiddenCompleted }}
            </span>

            <div class="flex items-center ltr:ml-auto rtl:mr-auto shrink-0">
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

            <!--
                R1.3 — while a task is being dragged, a collapsed section's header becomes a drop zone.
                The task lands first in the column (parseTaskDragEnd reads data-drop-first).
                Children are hidden so the dragged placeholder does not render inside the header.
            -->
            <draggable
                v-if="collapsed && dragging"
                :model-value="[]"
                :group="{ name: 'tasks', pull: false, put: true }"
                class="scrum-drop-first absolute inset-0 rounded-md bg-primary/5 border-2 border-dashed border-primary/40 has-[.sortable-ghost]:bg-primary/15 has-[.sortable-ghost]:border-primary"
                :data-column-id="column.id"
                data-drop-first="1"
                :title="$t('scrum_drop_first_hint')"
            />
        </header>

        <div v-show="!collapsed" :id="bodyId" class="px-2 pb-3 pt-1">
            <div class="relative">
                <draggable
                    :key="`tasks-${column.id}-${listsKey}`"
                    :model-value="tasks"
                    group="tasks"
                    handle=".scrum-row-handle"
                    :animation="150"
                    ghost-class="sortable-ghost"
                    drag-class="sortable-drag"
                    class="min-h-[48px]"
                    :data-column-id="column.id"
                    @start="emit('drag-start')"
                    @end="(evt: SortableEndEvent) => emit('drag-end', evt)"
                >
                    <ScrumTaskRow
                        v-for="task in tasks"
                        :key="task.id"
                        :data-task-id="task.id"
                        :task="task"
                        :columns="columns"
                        :fading="fadingTaskIds.includes(task.id)"
                        @open="(t) => emit('open-task', t)"
                        @edit="(t) => emit('edit-task', t)"
                        @clone="(t) => emit('clone-task', t)"
                        @delete="(t) => emit('delete-task', t)"
                        @toggle="(t) => emit('toggle-task', t)"
                        @move="(id) => emit('move-to', task, id)"
                    />
                </draggable>
                <ScrumEmptyState
                    v-if="tasks.length === 0"
                    class="absolute inset-0"
                    :total="column.tasks.length"
                    :matching="matching"
                    :hidden-completed="hiddenCompleted"
                    @show-completed="emit('show-completed')"
                />
            </div>
            <div class="pt-2 px-2">
                <button type="button" class="btn btn-sm btn-outline-primary" @click="emit('add-task', column.id)">
                    <icon-plus class="w-4 h-4 ltr:mr-1 rtl:ml-1" />
                    {{ $t('scrum_add_task') }}
                </button>
            </div>
        </div>
    </section>
</template>

<script lang="ts" setup>
import { computed } from 'vue';
import { VueDraggable as draggable } from 'vue-draggable-plus';
import { useAppStore } from '@/stores/index';
import ScrumTaskRow from './ScrumTaskRow.vue';
import ScrumEmptyState from './ScrumEmptyState.vue';
import IconMenu from '@/components/icon/icon-menu.vue';
import IconCaretDown from '@/components/icon/icon-caret-down.vue';
import IconPlus from '@/components/icon/icon-plus.vue';
import IconPlusCircle from '@/components/icon/icon-plus-circle.vue';
import IconHorizontalDots from '@/components/icon/icon-horizontal-dots.vue';
import IconCircleCheck from '@/components/icon/icon-circle-check.vue';
import type { SortableEndEvent } from '@/utils/scrumDragPosition';
import type { ScrumColumn, ScrumTask } from '@/types/scrum';

const props = defineProps<{
    column: ScrumColumn;
    columns: ScrumColumn[];
    /** Visible tasks of this column (filters applied) */
    tasks: ScrumTask[];
    hiddenCompleted: number;
    /** Tasks passing the assignee filter (base for the empty-state message) */
    matching: number;
    fadingTaskIds: number[];
    collapsed: boolean;
    /** A task drag is in progress somewhere in the list */
    dragging: boolean;
    /** Remount key for the task list (see DRAG_REMOUNT_NOTE in scrumUi.ts) */
    listsKey: number;
}>();

const emit = defineEmits<{
    'add-task': [columnId: number];
    'open-task': [task: ScrumTask];
    'edit-task': [task: ScrumTask];
    'clone-task': [task: ScrumTask];
    'delete-task': [task: ScrumTask];
    'toggle-task': [task: ScrumTask];
    'move-to': [task: ScrumTask, columnId: number];
    'edit-column': [column: ScrumColumn];
    'delete-column': [column: ScrumColumn];
    'toggle-collapse': [columnId: number];
    'show-completed': [];
    'drag-start': [];
    'drag-end': [evt: SortableEndEvent];
}>();

const appStore = useAppStore();
const bodyId = computed(() => `scrum-section-body-${props.column.id}`);
const titleId = computed(() => `scrum-section-title-${props.column.id}`);
</script>

<style>
/* The dragged placeholder must not render inside a collapsed header drop zone */
.scrum-drop-first > * {
    display: none !important;
}
</style>
