<template>
    <TransitionRoot appear :show="modelValue" as="template">
        <Dialog as="div" class="relative z-[60]" @close="() => {}">
            <TransitionChild
                as="template"
                enter="duration-300 ease-out"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="duration-200 ease-in"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <DialogOverlay class="fixed inset-0 bg-black/60" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center px-4 py-8">
                    <TransitionChild
                        as="template"
                        enter="duration-300 ease-out"
                        enter-from="opacity-0 scale-95"
                        enter-to="opacity-100 scale-100"
                        leave="duration-200 ease-in"
                        leave-from="opacity-100 scale-100"
                        leave-to="opacity-0 scale-95"
                    >
                        <DialogPanel
                            class="panel border-0 p-0 rounded-lg overflow-hidden w-full max-w-lg text-black dark:text-white-dark"
                        >
                            <button
                                type="button"
                                class="absolute top-4 ltr:right-4 rtl:left-4 text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 outline-none"
                                @click="close"
                            >
                                <icon-x />
                            </button>
                            <div
                                class="text-lg font-medium bg-[#fbfbfb] dark:bg-[#121c2c] ltr:pl-5 rtl:pr-5 py-3 ltr:pr-[50px] rtl:pl-[50px]"
                            >
                                {{ $t('cases.task.modal_title_new') }}
                            </div>
                            <div class="p-5">
                                <form @submit.prevent="submit">
                                    <div class="mb-4">
                                        <label for="task-subject" class="block text-sm font-medium mb-1">
                                            {{ $t('cases.task.modal_field_subject') }} <span class="text-danger">*</span>
                                        </label>
                                        <input
                                            id="task-subject"
                                            v-model="form.subject"
                                            type="text"
                                            class="form-input"
                                            maxlength="255"
                                            required
                                        />
                                        <p v-if="errors.subject" class="text-danger text-xs mt-1">{{ errors.subject }}</p>
                                    </div>

                                    <div class="mb-4">
                                        <label for="task-description" class="block text-sm font-medium mb-1">
                                            {{ $t('cases.task.modal_field_description') }}
                                        </label>
                                        <textarea
                                            id="task-description"
                                            v-model="form.description"
                                            rows="3"
                                            class="form-textarea"
                                        ></textarea>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label for="task-type" class="block text-sm font-medium mb-1">
                                                {{ $t('cases.task.modal_field_type') }} <span class="text-danger">*</span>
                                            </label>
                                            <select id="task-type" v-model="form.type" class="form-select">
                                                <option value="case_creation">{{ $t('tasks.types.case_creation') }}</option>
                                                <option value="translation">{{ $t('tasks.types.translation') }}</option>
                                                <option value="accounting">{{ $t('tasks.types.accounting') }}</option>
                                                <option value="filing">{{ $t('tasks.types.filing') }}</option>
                                                <option value="document">{{ $t('tasks.types.document') }}</option>
                                                <option value="other">{{ $t('tasks.types.other') }}</option>
                                            </select>
                                            <p v-if="errors.type" class="text-danger text-xs mt-1">{{ errors.type }}</p>
                                        </div>

                                        <div>
                                            <label for="task-priority" class="block text-sm font-medium mb-1">
                                                {{ $t('cases.task.modal_field_priority') }} <span class="text-danger">*</span>
                                            </label>
                                            <select id="task-priority" v-model="form.priority" class="form-select">
                                                <option value="low">{{ $t('tasks.priority.low') }}</option>
                                                <option value="medium">{{ $t('tasks.priority.medium') }}</option>
                                                <option value="high">{{ $t('tasks.priority.high') }}</option>
                                                <option value="urgent">{{ $t('tasks.priority.urgent') }}</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label for="task-due" class="block text-sm font-medium mb-1">
                                                {{ $t('cases.task.modal_field_due_date') }}
                                            </label>
                                            <input
                                                id="task-due"
                                                v-model="form.due_date"
                                                type="date"
                                                class="form-input"
                                            />
                                        </div>

                                        <div>
                                            <label for="task-assignee" class="block text-sm font-medium mb-1">
                                                {{ $t('cases.task.modal_field_assigned_to') }}
                                            </label>
                                            <select
                                                id="task-assignee"
                                                :value="form.assigned_to ?? ''"
                                                class="form-select"
                                                @change="onAssigneeChange"
                                            >
                                                <option value="">—</option>
                                                <option v-for="u in staff" :key="u.id" :value="u.id">
                                                    {{ u.name }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="flex justify-end gap-3 mt-6">
                                        <button type="button" class="btn btn-outline-secondary" @click="close">
                                            {{ $t('cases.cancel') }}
                                        </button>
                                        <button type="submit" class="btn btn-primary" :disabled="submitting">
                                            <span
                                                v-if="submitting"
                                                class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 inline-block mr-2"
                                            ></span>
                                            {{ $t('cases.save') }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from 'vue';
import { Dialog, DialogPanel, DialogOverlay, TransitionRoot, TransitionChild } from '@headlessui/vue';
import IconX from '@/components/icon/icon-x.vue';
import { useCaseTasksStore } from '@/stores/useCaseTasksStore';
import userService from '@/services/userService';
import type { TaskType, TaskPriority } from '@/types/workflow';
import type { StaffMember } from '@/types/wizard';

const props = defineProps<{
    modelValue: boolean;
    stageId: number | null;
    caseId: number;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
    (e: 'created'): void;
}>();

const store = useCaseTasksStore();
const submitting = ref(false);
const staff = ref<StaffMember[]>([]);

const form = reactive<{
    subject: string;
    description: string;
    type: TaskType;
    priority: TaskPriority;
    due_date: string;
    assigned_to: number | null;
}>({
    subject: '',
    description: '',
    type: 'other',
    priority: 'medium',
    due_date: '',
    assigned_to: null,
});

const errors = reactive<Record<string, string>>({});

watch(
    () => props.modelValue,
    async (val) => {
        if (val) {
            // Reset form
            form.subject = '';
            form.description = '';
            form.type = 'other';
            form.priority = 'medium';
            form.due_date = '';
            form.assigned_to = null;
            Object.keys(errors).forEach((k) => delete errors[k]);
            // Load staff lazily
            if (staff.value.length === 0) {
                try {
                    staff.value = await userService.getStaff(null, 'tasks.create', ['admin', 'user']);
                } catch {
                    staff.value = [];
                }
            }
        }
    },
);

function close() {
    if (submitting.value) return;
    emit('update:modelValue', false);
}

function onAssigneeChange(e: Event) {
    const v = (e.target as HTMLSelectElement).value;
    form.assigned_to = v ? parseInt(v) : null;
}

async function submit() {
    Object.keys(errors).forEach((k) => delete errors[k]);
    if (!form.subject.trim()) {
        errors.subject = 'required';
        return;
    }
    if (!props.stageId) {
        return;
    }
    submitting.value = true;
    try {
        const result = await store.createTask(props.caseId, {
            case_stage_id: props.stageId,
            subject: form.subject.trim(),
            description: form.description || null,
            type: form.type,
            priority: form.priority,
            due_date: form.due_date || null,
            assigned_to: form.assigned_to,
        });
        if (result) {
            emit('created');
            emit('update:modelValue', false);
        }
    } catch (e: any) {
        const fieldErrors = e?.response?.data?.errors as Record<string, string[]> | undefined;
        if (fieldErrors) {
            Object.entries(fieldErrors).forEach(([field, msgs]) => {
                errors[field] = Array.isArray(msgs) ? msgs[0] : String(msgs);
            });
        }
    } finally {
        submitting.value = false;
    }
}
</script>
