<template>
    <!-- Spec 71 — list view. Same props/emits contract as ScrumKanbanView; never mutates board data. -->
    <div ref="rootEl" class="pt-5">
        <draggable
            :model-value="columns"
            group="columns"
            handle=".scrum-section-handle"
            :animation="200"
            class="space-y-4"
            @update:model-value="(cols: ScrumColumn[]) => emit('reorder-columns', cols)"
        >
            <ScrumListSection
                v-for="column in columns"
                :key="column.id"
                :column="column"
                :columns="columns"
                :tasks="visibleTasks(column.id)"
                :hidden-completed="hiddenCompletedCount(column.id)"
                :matching="assigneeMatchCount(column.id)"
                :fading-task-ids="fadingTaskIds"
                :collapsed="store.collapsedColumnIds.includes(column.id)"
                :dragging="isDraggingTask"
                :lists-key="listsKey"
                @add-task="(id) => emit('add-task', id)"
                @open-task="openTask"
                @edit-task="(t) => emit('open-task', t)"
                @clone-task="(t) => emit('clone-task', t)"
                @delete-task="(t) => emit('delete-task', t)"
                @toggle-task="(t) => emit('toggle-task', t)"
                @move-to="moveVia"
                @edit-column="(c) => emit('edit-column', c)"
                @delete-column="(c) => emit('delete-column', c)"
                @toggle-collapse="(id) => store.toggleColumnCollapsed(id)"
                @show-completed="emit('show-completed')"
                @drag-start="onDragStart"
                @drag-end="onDragEnd"
            />
        </draggable>
    </div>
</template>

<script lang="ts" setup>
import { ref } from 'vue';
import { VueDraggable as draggable } from 'vue-draggable-plus';
import { useScrumStore } from '@/stores/scrum';
import ScrumListSection from './ScrumListSection.vue';
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

// Collapsed sections are list-only UI state, read straight from the store
const store = useScrumStore();
const rootEl = ref<HTMLElement | null>(null);

// listsKey remounts the task lists after every drag — see DRAG_REMOUNT_NOTE in scrumUi.ts.
// isDraggingTask shows the drop zones on collapsed section headers.
const { listsKey, isDraggingTask, openTask, onDragStart, onDragEnd, moveVia } = useScrumViewInteractions({
    rootEl,
    visibleTasks: () => props.visibleTasks,
    emitOpen: (task) => emit('open-task', task),
    emitMove: (payload) => emit('move-task', payload),
});
</script>
