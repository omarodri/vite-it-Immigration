<template>
    <div class="sortable-list">
        <div class="shadow bg-[#f4f4f4] dark:bg-white-dark/20 p-3 pb-4 rounded-md mb-3 space-y-2 cursor-move" :class="{ 'opacity-60': task.is_completed }">
            <!-- Title & Actions -->
            <div class="flex items-start justify-between gap-2">
                <div class="flex items-start gap-2 flex-1 min-w-0">
                    <button type="button" class="shrink-0 mt-0.5 transition-colors" :class="task.is_completed ? 'text-success' : 'text-gray-300 hover:text-gray-400'" @click.stop="$emit('toggle', task)">
                        <icon-circle-check class="w-4 h-4" />
                    </button>
                    <span class="text-sm font-medium leading-tight" :class="{ 'line-through text-gray-400': task.is_completed }">{{ task.title }}</span>
                </div>
                <div class="flex gap-1 shrink-0">
                    <button type="button" @click.stop="$emit('clone', task)" class="hover:text-success">
                        <icon-copy class="w-4 h-4" />
                    </button>
                    <button type="button" @click.stop="$emit('edit', task)" class="hover:text-info">
                        <icon-edit class="w-4 h-4" />
                    </button>
                    <button type="button" @click.stop="$emit('delete', task)" class="hover:text-danger">
                        <icon-trash-lines class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Description preview -->
            <p v-if="task.description_preview ?? task.description" class="text-xs text-gray-500 dark:text-gray-400 break-all" style="display: -webkit-box; -webkit-line-clamp: 5; -webkit-box-orient: vertical; overflow: hidden;">
                {{ task.description_preview ?? task.description }}
            </p>

            <!-- Tags -->
            <div v-if="task.tags && task.tags.length" class="flex gap-2 items-center flex-wrap">
                <div v-for="tag in task.tags" :key="tag" class="btn px-2 py-1 flex btn-outline-primary">
                    <icon-tag class="shrink-0" />
                    <span class="ltr:ml-2 rtl:mr-2">{{ tag }}</span>
                </div>
            </div>

            <!-- Footer meta -->
            <div class="flex items-center justify-between text-xs pt-1">
                <div class="flex items-center gap-3">
                    <!-- Due date -->
                    <span v-if="task.due_date" class="font-medium flex items-center hover:text-primary" :class="isOverdue ? 'text-danger' : ''">
                        <icon-calendar class="ltr:mr-1 rtl:ml-1 shrink-0 w-4 h-4" />
                        {{ formatDate(task.due_date) }}
                    </span>
                </div>
                <!-- Assignee -->
                <div v-if="task.assigned_to" class="flex items-center gap-1">
                    <UserAvatar :name="task.assigned_to.name" :avatar-url="task.assigned_to.avatar_url" size="xs" />
                    <span class="truncate max-w-[60px] text-gray-400">{{ task.assigned_to.name.split(' ')[0] }}</span>
                </div>
            </div>
            <div class="flex items-center justify-between text-xs pt-1">
                <div class="flex items-center gap-3">
                    <!-- Case number -->
                    <span v-if="task.case" class="text-info font-medium">
                        #{{ task.case.case_number }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue';
import IconEdit from '@/components/icon/icon-edit.vue';
import IconTrashLines from '@/components/icon/icon-trash-lines.vue';
import IconCalendar from '@/components/icon/icon-calendar.vue';
import IconTag from '@/components/icon/icon-tag.vue';
import IconCopy from '@/components/icon/icon-copy.vue';
import IconCircleCheck from '@/components/icon/icon-circle-check.vue';
import type { ScrumTask } from '@/types/scrum';
import UserAvatar from '@/components/UserAvatar.vue';

const props = defineProps<{ task: ScrumTask }>();
defineEmits<{ edit: [task: ScrumTask]; delete: [task: ScrumTask]; clone: [task: ScrumTask]; toggle: [task: ScrumTask] }>();

const isOverdue = computed(() => {
    if (!props.task.due_date) return false;
    return new Date(props.task.due_date) < new Date();
});

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('en-CA'); // YYYY-MM-DD format
}
</script>
