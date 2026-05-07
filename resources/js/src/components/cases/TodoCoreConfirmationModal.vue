<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="show" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
                <div
                    class="absolute inset-0 bg-black/50 dark:bg-black/70"
                    @click="onCancel"
                ></div>
                <div
                    class="panel relative z-10 w-full max-w-lg max-h-[90vh] overflow-y-auto bg-white dark:bg-[#0e1726] dark:text-white-light shadow-xl"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="titleId"
                >
                    <div class="panel-header flex items-center justify-between border-b border-[#e0e6ed] dark:border-[#1b2e4b] pb-3 mb-4">
                        <h3 :id="titleId" class="text-lg font-semibold dark:text-white-light">
                            {{ $t('todo_core.confirmation_title') }}
                        </h3>
                        <button
                            type="button"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                            aria-label="Close"
                            @click="onCancel"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>

                    <div class="panel-body">
                        <p class="text-sm text-gray-700 dark:text-gray-200 mb-4">
                            {{ message }}
                        </p>

                        <div v-if="tasksAffected.length > 0" class="mb-4">
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                                {{ $t('todo_core.summary_count_label', { count: tasksAffected.length }) }}
                            </p>
                            <ul class="space-y-2 max-h-56 overflow-y-auto pr-1">
                                <li
                                    v-for="(task, idx) in tasksAffected"
                                    :key="task.id ?? `task-${idx}`"
                                    class="flex items-start gap-3 p-3 rounded-md border border-[#e0e6ed] dark:border-[#1b2e4b] bg-gray-50 dark:bg-[#1b2e4b]/40"
                                >
                                    <div class="w-2 h-2 rounded-full bg-primary mt-2 shrink-0"></div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium text-gray-800 dark:text-white-light truncate">
                                            {{ task.subject }}
                                        </div>
                                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            <span v-if="task.assignedToName">{{ task.assignedToName }}</span>
                                            <span v-if="task.dueDate">
                                                {{ $t('todo_core.summary_due_label', { date: task.dueDate }) }}
                                            </span>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <div class="flex flex-wrap justify-end gap-3 pt-4 border-t border-[#e0e6ed] dark:border-[#1b2e4b]">
                            <template v-if="scenario === 'remove_from_existing'">
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    @click="onCancel"
                                >
                                    {{ $t('todo_core.action_cancel') }}
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    @click="onConfirmStrategy('keep_orphan')"
                                >
                                    {{ $t('todo_core.action_cascade_keep_orphan') }}
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-danger"
                                    @click="onConfirmStrategy('soft_delete')"
                                >
                                    {{ $t('todo_core.action_cascade_soft_delete') }}
                                </button>
                            </template>
                            <template v-else>
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    @click="onCancel"
                                >
                                    {{ $t('todo_core.action_cancel') }}
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    @click="onConfirmStrategy()"
                                >
                                    {{ $t('todo_core.action_confirm') }}
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script lang="ts" setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface AffectedTask {
    id?: number;
    subject: string;
    assignedToName?: string;
    dueDate?: string;
}

const props = defineProps<{
    show: boolean;
    scenario: 'wizard_create' | 'add_to_existing' | 'remove_from_existing';
    tasksAffected: AffectedTask[];
    consultantName?: string;
}>();

const emit = defineEmits<{
    (e: 'confirm', payload: { cascadeStrategy?: string }): void;
    (e: 'cancel'): void;
}>();

const { t } = useI18n();

const titleId = computed(() => `todo-core-modal-title-${Math.random().toString(36).slice(2, 8)}`);

const message = computed(() => {
    const consultant = props.consultantName ?? '';
    const count = props.tasksAffected.length;
    if (props.scenario === 'wizard_create') {
        return t('todo_core.confirm_create_from_wizard', { count, consultant });
    }
    if (props.scenario === 'add_to_existing') {
        return t('todo_core.confirm_create_for_existing_case', { consultant });
    }
    return t('todo_core.confirm_remove_with_todo', { consultant });
});

function onCancel() {
    emit('cancel');
}

function onConfirmStrategy(strategy?: string) {
    emit('confirm', strategy ? { cascadeStrategy: strategy } : {});
}
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
