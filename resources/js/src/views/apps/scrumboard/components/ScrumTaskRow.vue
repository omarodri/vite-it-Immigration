<template>
    <!-- Spec 71 R12 — one task in the list view. Drag only starts from the grip handle. -->
    <div
        class="flex items-center gap-3 px-2 py-2 rounded-md cursor-pointer outline-none hover:bg-gray-50 dark:hover:bg-white-dark/5 focus-visible:ring-2 focus-visible:ring-primary"
        :class="{ 'opacity-60': task.is_completed, 'scrum-task-fading': fading }"
        tabindex="0"
        :aria-label="$t('scrum_open_task', { title: task.title })"
        @click="emit('open', task)"
        @keydown.enter.self.prevent="emit('open', task)"
    >
        <span class="scrum-row-handle cursor-grab text-gray-400 hover:text-gray-600 shrink-0" :title="$t('scrum_drag_handle')" @click.stop>
            <icon-menu class="w-4 h-4" />
        </span>

        <button
            type="button"
            class="shrink-0 transition-colors"
            :class="task.is_completed ? 'text-success' : 'text-gray-300 hover:text-gray-400'"
            @click.stop="emit('toggle', task)"
        >
            <icon-circle-check class="w-4 h-4" />
        </button>

        <!-- Title, category, description -->
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 min-w-0">
                <span class="text-sm font-medium truncate" :class="{ 'line-through text-gray-400': task.is_completed }" :title="task.title">{{ task.title }}</span>
                <span v-if="task.category" class="hidden md:inline-flex badge bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-xs shrink-0">
                    {{ task.category }}
                </span>
            </div>
            <p v-if="description" class="hidden md:block text-xs text-gray-500 dark:text-gray-400 truncate" :title="description">{{ description }}</p>
        </div>

        <!-- Tags (max 2 + "+N") -->
        <div v-if="task.tags && task.tags.length" class="hidden md:flex items-center gap-1 shrink-0">
            <span v-for="tag in task.tags.slice(0, 2)" :key="tag" class="badge badge-outline-primary text-xs max-w-[8rem] truncate">{{ tag }}</span>
            <span v-if="task.tags.length > 2" class="badge badge-outline-primary text-xs" :title="task.tags.slice(2).join(', ')">
                {{ $t('scrum_more_tags', { n: task.tags.length - 2 }) }}
            </span>
        </div>

        <span v-if="task.case" class="hidden md:inline text-info text-xs font-medium shrink-0">#{{ task.case.case_number }}</span>

        <span v-if="task.due_date" class="text-xs font-medium flex items-center shrink-0" :class="isOverdue(task.due_date) ? 'text-danger' : 'text-gray-500 dark:text-gray-400'">
            <icon-calendar class="ltr:mr-1 rtl:ml-1 shrink-0 w-4 h-4" />
            {{ formatDueDate(task.due_date) }}
        </span>

        <div v-if="task.assigned_to" class="flex items-center gap-1 shrink-0">
            <UserAvatar :name="task.assigned_to.name" :avatar-url="task.assigned_to.avatar_url" size="xs" />
            <span class="hidden sm:inline truncate max-w-[70px] text-xs text-gray-400">{{ task.assigned_to.name.split(' ')[0] }}</span>
        </div>

        <div class="flex gap-1 shrink-0">
            <ScrumMoveToMenu v-if="columns.length > 1" :columns="columns" :current-column-id="task.scrum_column_id" @select="(id) => emit('move', id)" />
            <button type="button" class="hover:text-success" @click.stop="emit('clone', task)">
                <icon-copy class="w-4 h-4" />
            </button>
            <button type="button" class="hover:text-info" @click.stop="emit('edit', task)">
                <icon-edit class="w-4 h-4" />
            </button>
            <button type="button" class="hover:text-danger" @click.stop="emit('delete', task)">
                <icon-trash-lines class="w-4 h-4" />
            </button>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue';
import IconMenu from '@/components/icon/icon-menu.vue';
import IconEdit from '@/components/icon/icon-edit.vue';
import IconTrashLines from '@/components/icon/icon-trash-lines.vue';
import IconCalendar from '@/components/icon/icon-calendar.vue';
import IconCopy from '@/components/icon/icon-copy.vue';
import IconCircleCheck from '@/components/icon/icon-circle-check.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import ScrumMoveToMenu from './ScrumMoveToMenu.vue';
import { formatDueDate, isOverdue } from '../scrumUi';
import type { ScrumColumn, ScrumTask } from '@/types/scrum';

const props = defineProps<{
    task: ScrumTask;
    columns: ScrumColumn[];
    fading?: boolean;
}>();

const emit = defineEmits<{
    open: [task: ScrumTask];
    edit: [task: ScrumTask];
    delete: [task: ScrumTask];
    clone: [task: ScrumTask];
    toggle: [task: ScrumTask];
    move: [columnId: number];
}>();

const description = computed(() => props.task.description_preview ?? props.task.description ?? '');
</script>
