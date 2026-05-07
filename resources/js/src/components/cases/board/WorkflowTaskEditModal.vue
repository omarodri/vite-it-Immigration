<template>
    <div
        v-if="modelValue && task"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        @click.self="close"
    >
        <div class="panel w-full max-w-lg mx-4">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-[#e0e6ed] px-5 py-4 dark:border-[#1b2e4b]">
                <h5 class="text-lg font-semibold dark:text-white">{{ t('workflow_task.edit_modal_title') }}</h5>
                <div class="flex items-center gap-2">
                    <span
                        v-if="task.task_template_id && !task.cloned_from_task_id"
                        class="badge bg-warning/20 text-warning text-[10px] uppercase tracking-wide"
                    >
                        {{ t('workflow_task.badge_core') }}
                    </span>
                    <span
                        v-if="task.cloned_from_task_id"
                        class="badge bg-info/20 text-info text-[10px] uppercase tracking-wide"
                    >
                        {{ t('workflow_task.badge_cloned') }}
                    </span>
                    <button
                        type="button"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1 rounded"
                        @click="close"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Body -->
            <div class="space-y-4 p-5">
                <!-- Subject -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ t('workflow_task.field_subject') }} *
                    </label>
                    <input
                        v-model="form.subject"
                        type="text"
                        class="form-input"
                        :class="{ 'border-danger': !form.subject?.trim() && submitted }"
                    />
                    <p v-if="!form.subject?.trim() && submitted" class="mt-1 text-xs text-danger">
                        {{ t('workflow_task.field_subject') }} {{ t('common.is_required') }}
                    </p>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        {{ t('workflow_task.field_description') }}
                    </label>
                    <textarea
                        v-model="form.description"
                        rows="4"
                        class="form-textarea"
                    />
                </div>

                <!-- Type + Priority -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ t('workflow_task.field_type') }}
                        </label>
                        <select v-model="form.type" class="form-select">
                            <option v-for="opt in TASK_TYPE_OPTIONS" :key="opt.value" :value="opt.value">
                                {{ t(opt.label) }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ t('workflow_task.field_priority') }}
                        </label>
                        <select v-model="form.priority" class="form-select">
                            <option value="low">{{ t('tasks.priority.low') }}</option>
                            <option value="medium">{{ t('tasks.priority.medium') }}</option>
                            <option value="high">{{ t('tasks.priority.high') }}</option>
                            <option value="urgent">{{ t('tasks.priority.urgent') }}</option>
                        </select>
                    </div>
                </div>

                <!-- Assigned to + Due date -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ t('workflow_task.field_assigned_to') }}
                        </label>
                        <select v-model="form.assigned_to" class="form-select">
                            <option :value="null">— {{ t('common.unassigned') }} —</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ t('workflow_task.field_due_date') }}
                        </label>
                        <input
                            v-model="form.due_date"
                            type="date"
                            class="form-input"
                        />
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3 border-t border-[#e0e6ed] px-5 py-4 dark:border-[#1b2e4b]">
                <button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="close">
                    {{ t('common.cancel') }}
                </button>
                <button
                    type="button"
                    class="btn btn-primary"
                    :disabled="saving"
                    @click="handleSave"
                >
                    <span
                        v-if="saving"
                        class="inline-block w-4 h-4 border-2 border-white border-l-transparent rounded-full animate-spin mr-2"
                    ></span>
                    {{ t('common.save') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { WorkflowTask, UpdateWorkflowTaskCorePayload } from '@/types/workflow';
import { TASK_TYPE_OPTIONS } from '@/types/workflow';
import { useCaseTasksStore } from '@/stores/useCaseTasksStore';

const props = defineProps<{
    modelValue: boolean;
    task: WorkflowTask | null;
    caseId: number;
    users: { id: number; name: string }[];
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', v: boolean): void;
    (e: 'saved', task: WorkflowTask): void;
}>();

const { t } = useI18n();

const form = ref<UpdateWorkflowTaskCorePayload>({
    subject: '',
    description: null,
    type: 'other',
    priority: 'medium',
    assigned_to: null,
    due_date: null,
});

const saving = ref(false);
const submitted = ref(false);

watch(
    () => props.task,
    (task) => {
        if (task) {
            form.value = {
                subject:     task.subject,
                description: task.description,
                type:        task.type,
                priority:    task.priority,
                assigned_to: task.assigned_to,
                due_date:    task.due_date ? task.due_date.slice(0, 10) : null,
            };
            submitted.value = false;
        }
    },
    { immediate: true },
);

function close() {
    emit('update:modelValue', false);
}

async function handleSave() {
    submitted.value = true;
    if (!props.task || !form.value.subject?.trim()) return;

    saving.value = true;
    try {
        const store = useCaseTasksStore();
        const updated = await store.updateTaskCore(props.caseId, props.task.id, form.value);
        if (updated) {
            emit('saved', updated);
            close();
        }
    } finally {
        saving.value = false;
    }
}
</script>
