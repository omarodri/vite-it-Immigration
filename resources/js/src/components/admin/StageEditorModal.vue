<template>
    <TransitionRoot appear :show="show" as="template">
        <Dialog as="div" @close="handleClose" class="relative z-[51]">
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
                                {{ stage ? $t('workflow.edit_stage') : $t('workflow.add_stage') }}
                            </div>
                            <div class="p-5 space-y-4">
                                <form @submit.prevent="save" class="space-y-4">
                                    <TrilingualInput
                                        v-model="form.translations.name"
                                        :label="$t('workflow.stage_name')"
                                        required-locale="es"
                                        :maxlength="255"
                                    />

                                    <TrilingualInput
                                        v-model="form.translations.description"
                                        :label="$t('workflow.stage_description')"
                                        textarea
                                        :rows="3"
                                    />

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-semibold mb-1">{{ $t('workflow.stage_code') }} *</label>
                                            <input
                                                v-model="form.code"
                                                type="text"
                                                class="form-input"
                                                maxlength="50"
                                                pattern="[a-z0-9_]+"
                                                required
                                                :placeholder="'admision'"
                                            />
                                            <p class="text-xs text-gray-400 mt-1">{{ $t('workflow.code_hint') }}</p>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-semibold mb-1">{{ $t('workflow.stage_color') }}</label>
                                            <select v-model="form.color" class="form-select">
                                                <option v-for="c in STAGE_COLOR_OPTIONS" :key="c" :value="c">{{ c }}</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-4 pt-2">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input v-model="form.is_active" type="checkbox" class="form-checkbox" />
                                            <span class="text-sm">{{ $t('workflow.is_active') }}</span>
                                        </label>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input v-model="form.is_terminal" type="checkbox" class="form-checkbox" />
                                            <span class="text-sm">{{ $t('workflow.stage_is_terminal') }}</span>
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
import type { WorkflowStage, TranslationsByField } from '@/types/workflow';
import { STAGE_COLOR_OPTIONS } from '@/types/workflow';

const props = defineProps<{ show: boolean; stage: WorkflowStage | null }>();
const emit = defineEmits<{ (e: 'close'): void; (e: 'saved'): void }>();

const store = useWorkflowStore();
const notification = useNotification();
const isSaving = ref(false);

interface FormShape {
    code: string;
    color: string;
    is_active: boolean;
    is_terminal: boolean;
    translations: TranslationsByField;
}

const form = ref<FormShape>({
    code: '',
    color: 'primary',
    is_active: true,
    is_terminal: false,
    translations: { name: {}, description: {} },
});

watch(() => props.show, (open) => {
    if (open) {
        if (props.stage) {
            form.value = {
                code: props.stage.code,
                color: props.stage.color || 'primary',
                is_active: props.stage.is_active,
                is_terminal: props.stage.is_terminal,
                translations: {
                    name: { ...(props.stage.translations.name ?? {}) },
                    description: { ...(props.stage.translations.description ?? {}) },
                },
            };
        } else {
            form.value = {
                code: '',
                color: 'primary',
                is_active: true,
                is_terminal: false,
                translations: { name: {}, description: {} },
            };
        }
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
        const payload = {
            code: form.value.code,
            color: form.value.color,
            is_active: form.value.is_active,
            is_terminal: form.value.is_terminal,
            translations: form.value.translations,
        };
        if (props.stage) {
            await store.updateStage(props.stage.id, payload);
        } else {
            await store.createStage(payload);
        }
        notification.success(props.stage ? 'Etapa actualizada' : 'Etapa creada');
        emit('saved');
        emit('close');
    } catch (e: any) {
        notification.error(e?.response?.data?.message || 'Error al guardar');
    } finally {
        isSaving.value = false;
    }
}
</script>
