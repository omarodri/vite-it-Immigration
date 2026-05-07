<template>
    <div
        :class="[
            'task-card relative rounded-md shadow-sm border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 hover:shadow-md transition outline-none focus-visible:ring-2 focus-visible:ring-primary',
            isCompleted ? 'opacity-70' : '',
            highlighted ? 'ring-2 ring-success ring-offset-1' : '',
        ]"
        role="article"
        tabindex="0"
        :aria-grabbed="pending ? 'true' : 'false'"
        :aria-label="task.subject"
        @keydown="onKeydown"
    >
        <div class="flex items-start gap-2">
            <!-- Drag handle -->
            <span
                class="task-drag-handle shrink-0 mt-0.5 text-gray-400 cursor-grab active:cursor-grabbing"
                :title="$t('cases.task.move_to_stage')"
                aria-hidden="true"
            >
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path
                        d="M8 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM8 12a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM8 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM14 6a2 2 0 1 1 4 0 2 2 0 0 1-4 0zM14 12a2 2 0 1 1 4 0 2 2 0 0 1-4 0zM14 18a2 2 0 1 1 4 0 2 2 0 0 1-4 0z"
                    />
                </svg>
            </span>

            <input
                type="checkbox"
                class="form-checkbox shrink-0 mt-0.5"
                :checked="isCompleted"
                :disabled="!editable || pending"
                @change="$emit('toggle', task)"
                @click.stop
            />

            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2">
                    <p
                        class="text-sm font-medium text-gray-800 dark:text-gray-100 break-words"
                        :class="isCompleted ? 'line-through text-gray-400' : ''"
                    >
                        {{ task.subject }}
                    </p>

                    <!-- Kebab menu -->
                    <div v-if="editable" class="relative shrink-0">
                        <button
                            type="button"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 rounded outline-none focus-visible:ring-2 focus-visible:ring-primary"
                            :aria-label="$t('cases.task.move_to_stage')"
                            aria-haspopup="menu"
                            :aria-expanded="menuOpen ? 'true' : 'false'"
                            @click.stop="toggleMenu"
                        >
                            <icon-horizontal-dots class="w-4 h-4" />
                        </button>

                        <div
                            v-if="menuOpen"
                            v-click-outside="closeMenu"
                            role="menu"
                            class="absolute z-30 ltr:right-0 rtl:left-0 mt-1 w-56 rounded-md shadow-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 py-1 text-sm"
                        >
                            <div class="relative" @mouseenter="submenuOpen = true" @mouseleave="submenuOpen = false">
                                <button
                                    type="button"
                                    role="menuitem"
                                    class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center justify-between"
                                    @click.stop="submenuOpen = !submenuOpen"
                                >
                                    <span>{{ $t('cases.task.move_to_stage') }}</span>
                                    <span class="text-gray-400">›</span>
                                </button>
                                <div
                                    v-if="submenuOpen"
                                    role="menu"
                                    class="absolute top-0 ltr:left-full rtl:right-full ltr:ml-1 rtl:mr-1 w-56 rounded-md shadow-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 py-1"
                                >
                                    <template v-if="otherStages.length > 0">
                                        <button
                                            v-for="s in otherStages"
                                            :key="s.id"
                                            type="button"
                                            role="menuitem"
                                            class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2"
                                            @click.stop="moveToStage(s.id)"
                                        >
                                            <span
                                                class="inline-block w-2 h-2 rounded-full"
                                                :style="{ backgroundColor: s.color || '#4361ee' }"
                                            ></span>
                                            <span class="truncate">{{ s.name }}</span>
                                        </button>
                                    </template>
                                    <div v-else class="px-3 py-2 text-gray-400 italic">—</div>
                                </div>
                            </div>

                            <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>

                            <button
                                type="button"
                                role="menuitem"
                                class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2"
                                @click.stop="onEdit"
                            >
                                <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                <span>{{ $t('workflow_task.action_edit') }}</span>
                            </button>
                            <button
                                type="button"
                                role="menuitem"
                                class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2"
                                @click.stop="onClone"
                            >
                                <svg class="w-4 h-4 text-info" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                <span>{{ $t('workflow_task.action_clone') }}</span>
                            </button>

                            <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>

                            <button
                                type="button"
                                role="menuitem"
                                class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 text-danger flex items-center gap-2"
                                @click.stop="onDelete"
                            >
                                <icon-trash class="w-4 h-4" />
                                <span>{{ $t('cases.delete') }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <p
                    v-if="task.description"
                    class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2"
                >
                    {{ task.description }}
                </p>

                <!-- Badges -->
                <div class="mt-2 flex flex-wrap items-center gap-1">
                    <span :class="['badge text-[10px] uppercase tracking-wide', typeBadgeClass]">
                        {{ task.type }}
                    </span>
                    <span :class="['badge text-[10px] uppercase tracking-wide', priorityBadgeClass]">
                        {{ task.priority }}
                    </span>
                    <span
                        v-if="!task.task_template_id"
                        class="badge text-[10px] uppercase tracking-wide bg-secondary/10 text-secondary border border-secondary/30"
                    >
                        {{ $t('cases.task.manual') }}
                    </span>
                    <!-- Badge Core (task instantiated from template) -->
                    <span
                        v-if="task.task_template_id && !task.cloned_from_task_id"
                        class="badge text-[10px] uppercase tracking-wide bg-warning/10 text-warning border border-warning/30"
                    >
                        {{ $t('workflow_task.badge_core') }}
                    </span>
                    <!-- Badge Cloned -->
                    <span
                        v-if="task.cloned_from_task_id"
                        class="badge text-[10px] uppercase tracking-wide bg-info/10 text-info border border-info/30"
                    >
                        {{ $t('workflow_task.badge_cloned') }}
                    </span>
                    <span
                        v-if="task.due_date"
                        class="text-[10px] text-gray-500 dark:text-gray-400"
                    >
                        {{ formatDate(task.due_date) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Pending overlay -->
        <div
            v-if="pending"
            class="absolute inset-0 bg-white/60 dark:bg-gray-900/60 rounded-md flex items-center justify-center"
            aria-hidden="true"
        >
            <span class="w-5 h-5 border-2 border-primary border-l-transparent rounded-full animate-spin"></span>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { computed, ref } from 'vue';
import type { WorkflowTask } from '@/types/workflow';
import IconHorizontalDots from '@/components/icon/icon-horizontal-dots.vue';
import IconTrash from '@/components/icon/icon-trash.vue';

interface StageOption {
    id: number;
    name: string;
    color?: string;
}

const props = defineProps<{
    task: WorkflowTask;
    pending: boolean;
    editable: boolean;
    stages: StageOption[];
    highlighted?: boolean;
}>();

const emit = defineEmits<{
    (e: 'toggle', task: WorkflowTask): void;
    (e: 'delete', task: WorkflowTask): void;
    (e: 'move-to-stage', payload: { task: WorkflowTask; targetStageId: number }): void;
    (e: 'keyboard-move', payload: { task: WorkflowTask; direction: 'up' | 'down' }): void;
    (e: 'edit', task: WorkflowTask): void;
    (e: 'clone', task: WorkflowTask): void;
}>();

const menuOpen = ref(false);
const submenuOpen = ref(false);

function toggleMenu() {
    menuOpen.value = !menuOpen.value;
    if (!menuOpen.value) submenuOpen.value = false;
}

function closeMenu() {
    menuOpen.value = false;
    submenuOpen.value = false;
}

function moveToStage(targetStageId: number) {
    emit('move-to-stage', { task: props.task, targetStageId });
    closeMenu();
}

function onDelete() {
    emit('delete', props.task);
    closeMenu();
}

function onEdit() {
    emit('edit', props.task);
    closeMenu();
}

function onClone() {
    emit('clone', props.task);
    closeMenu();
}

const isCompleted = computed(() => props.task.status === 'resolved' || props.task.status === 'closed');

const otherStages = computed(() => props.stages.filter((s) => s.id !== props.task.workflow_stage_id));

const typeBadgeClass = computed(() => {
    switch (props.task.type) {
        case 'filing':
            return 'bg-primary/10 text-primary border border-primary/30';
        case 'document':
            return 'bg-info/10 text-info border border-info/30';
        default:
            return 'bg-secondary/10 text-secondary border border-secondary/30';
    }
});

const priorityBadgeClass = computed(() => {
    switch (props.task.priority) {
        case 'low':
            return 'bg-gray-100 text-gray-600 border border-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600';
        case 'medium':
            return 'bg-warning/10 text-warning border border-warning/30';
        case 'high':
        case 'urgent':
            return 'bg-danger/10 text-danger border border-danger/30';
        default:
            return 'bg-gray-100 text-gray-600';
    }
});

function formatDate(s: string): string {
    try {
        return new Date(s).toLocaleDateString();
    } catch {
        return s;
    }
}

function onKeydown(e: KeyboardEvent) {
    if (!props.editable) return;
    const meta = e.metaKey || e.ctrlKey;
    if (!meta) return;
    if (e.key === 'ArrowUp') {
        e.preventDefault();
        emit('keyboard-move', { task: props.task, direction: 'up' });
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        emit('keyboard-move', { task: props.task, direction: 'down' });
    }
}

// Lightweight click-outside directive (local).
const vClickOutside = {
    mounted(el: HTMLElement, binding: { value: () => void }) {
        (el as any)._clickOutside = (event: Event) => {
            if (!el.contains(event.target as Node)) binding.value();
        };
        document.addEventListener('click', (el as any)._clickOutside);
    },
    unmounted(el: HTMLElement) {
        document.removeEventListener('click', (el as any)._clickOutside);
    },
};
</script>
