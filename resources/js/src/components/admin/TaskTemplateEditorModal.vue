<template>
    <TransitionRoot appear :show="show" as="template">
        <Dialog as="div" @close="() => {}" class="relative z-[51]">
            <TransitionChild
                as="template"
                enter="duration-300 ease-out" enter-from="opacity-0" enter-to="opacity-100"
                leave="duration-200 ease-in" leave-from="opacity-100" leave-to="opacity-0"
            >
                <DialogOverlay class="fixed inset-0 bg-[black]/60" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center px-4 py-8">
                    <TransitionChild
                        as="template"
                        enter="duration-300 ease-out" enter-from="opacity-0 scale-95" enter-to="opacity-100 scale-100"
                        leave="duration-200 ease-in" leave-from="opacity-100 scale-100" leave-to="opacity-0 scale-95"
                    >
                        <DialogPanel class="panel border-0 p-0 rounded-lg overflow-hidden w-full max-w-2xl text-black dark:text-white-dark">
                            <button
                                type="button"
                                class="absolute top-4 ltr:right-4 rtl:left-4 text-gray-400 hover:text-gray-800 dark:hover:text-gray-600 outline-none"
                                @click="handleClose"
                            >
                                <icon-x />
                            </button>
                            <div class="text-lg font-medium bg-[#fbfbfb] dark:bg-[#121c2c] ltr:pl-5 rtl:pr-5 py-3 ltr:pr-[50px] rtl:pl-[50px]">
                                {{ template ? $t('workflow.edit_task_template') : $t('workflow.add_task_template') }}
                            </div>
                            <div class="p-5">
                                <form @submit.prevent="save" class="space-y-4">
                                    <TrilingualInput
                                        v-model="form.translations.name"
                                        :label="$t('workflow.template_name')"
                                        required-locale="es"
                                        :maxlength="255"
                                    />

                                    <TrilingualInput
                                        v-model="form.translations.description"
                                        :label="$t('workflow.template_description')"
                                        textarea
                                        :rows="3"
                                    />

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-semibold mb-1">{{ $t('workflow.template_code') }} *</label>
                                            <input
                                                v-model="form.code"
                                                type="text"
                                                class="form-input"
                                                maxlength="80"
                                                required
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-semibold mb-1">{{ $t('workflow.due_offset_days') }}</label>
                                            <input
                                                v-model.number="form.due_offset_days"
                                                type="number"
                                                min="0"
                                                max="3650"
                                                class="form-input"
                                            />
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-semibold mb-1">{{ $t('workflow.default_type') }}</label>
                                            <select v-model="form.default_type" class="form-select">
                                                <option v-for="opt in TASK_TYPE_OPTIONS" :key="opt.value" :value="opt.value">
                                                    {{ $t(opt.label) }}
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-semibold mb-1">{{ $t('workflow.default_priority') }}</label>
                                            <select v-model="form.default_priority" class="form-select">
                                                <option v-for="opt in TASK_PRIORITY_OPTIONS" :key="opt.value" :value="opt.value">
                                                    {{ $t(opt.label) }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-4 pt-2">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input v-model="form.is_required" type="checkbox" class="form-checkbox" />
                                            <span class="text-sm">{{ $t('workflow.required_task') }}</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input v-model="form.blocks_stage_completion" type="checkbox" class="form-checkbox" />
                                            <span class="text-sm">{{ $t('workflow.blocks_completion') }}</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input v-model="form.is_active" type="checkbox" class="form-checkbox" />
                                            <span class="text-sm">{{ $t('workflow.is_active') }}</span>
                                        </label>
                                    </div>

                                    <div class="flex justify-end gap-2 pt-4 border-t border-[#e0e6ed] dark:border-[#1b2e4b]">
                                        <button type="button" class="btn btn-outline-danger" @click="handleClose">
                                            {{ $t('common.cancel') }}
                                        </button>
                                        <button type="submit" class="btn btn-primary" :disabled="isSaving">
                                            {{ $t('common.save') }}
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
import { ref, watch } from 'vue';
import { Dialog, DialogPanel, DialogOverlay, TransitionRoot, TransitionChild } from '@headlessui/vue';
import { useNotification } from '@/composables/useNotification';
import IconX from '@/components/icon/icon-x.vue';
import TrilingualInput from '@/components/shared/TrilingualInput.vue';
import { useWorkflowStore } from '@/stores/useWorkflowStore';
import type { TaskTemplate, TranslationsByField, TaskType, TaskPriority } from '@/types/workflow';
import { TASK_TYPE_OPTIONS, TASK_PRIORITY_OPTIONS } from '@/types/workflow';

const props = defineProps<{
    show: boolean;
    stageId: number;
    template: TaskTemplate | null;
}>();
const emit = defineEmits<{ (e: 'close'): void; (e: 'saved'): void }>();

const store = useWorkflowStore();
const notification = useNotification();
const isSaving = ref(false);

interface FormShape {
    code: string;
    is_required: boolean;
    blocks_stage_completion: boolean;
    is_active: boolean;
    default_type: TaskType;
    default_priority: TaskPriority;
    due_offset_days: number | null;
    translations: TranslationsByField;
}

const form = ref<FormShape>(emptyForm());

function emptyForm(): FormShape {
    return {
        code: '',
        is_required: true,
        blocks_stage_completion: true,
        is_active: true,
        default_type: 'other',
        default_priority: 'medium',
        due_offset_days: null,
        translations: { name: {}, description: {} },
    };
}

watch(() => props.show, (open) => {
    if (!open) return;
    if (props.template) {
        form.value = {
            code: props.template.code,
            is_required: props.template.is_required,
            blocks_stage_completion: props.template.blocks_stage_completion,
            is_active: props.template.is_active,
            default_type: props.template.default_type,
            default_priority: props.template.default_priority,
            due_offset_days: props.template.due_offset_days,
            translations: {
                name: { ...(props.template.translations.name ?? {}) },
                description: { ...(props.template.translations.description ?? {}) },
            },
        };
    } else {
        form.value = emptyForm();
    }
});

function handleClose() {
    if (!isSaving.value) emit('close');
}

async function save() {
    if (!form.value.translations.name?.es) {
        notification.error('Falta el nombre en espanol');
        return;
    }
    isSaving.value = true;
    try {
        const payload = { ...form.value };
        if (props.template) {
            await store.updateTemplate(props.stageId, props.template.id, payload);
        } else {
            await store.createTemplate(props.stageId, payload);
        }
        notification.success(props.template ? 'Tarea actualizada' : 'Tarea creada');
        emit('saved');
        emit('close');
    } catch (e: any) {
        notification.error(e?.response?.data?.message || 'Error al guardar');
    } finally {
        isSaving.value = false;
    }
}
</script>
