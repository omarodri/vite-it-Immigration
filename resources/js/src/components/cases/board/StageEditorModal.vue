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
                            class="panel border-0 p-0 rounded-lg overflow-hidden w-full max-w-xl text-black dark:text-white-dark"
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
                                {{ isEdit ? $t('cases.stage.modal_title_edit') : $t('cases.stage.modal_title_new') }}
                            </div>
                            <div class="p-5">
                                <form @submit.prevent="submit">
                                    <!-- Locale tabs -->
                                    <div class="flex gap-2 mb-4 border-b border-gray-200 dark:border-gray-700">
                                        <button
                                            v-for="loc in locales"
                                            :key="loc"
                                            type="button"
                                            class="px-3 py-1.5 text-sm font-medium border-b-2 -mb-px"
                                            :class="activeLocale === loc
                                                ? 'border-primary text-primary'
                                                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                                            @click="activeLocale = loc"
                                        >
                                            {{ $t('cases.stage.locale_' + loc) }}
                                            <span v-if="loc === 'es'" class="text-danger">*</span>
                                        </button>
                                    </div>

                                    <div v-for="loc in locales" v-show="activeLocale === loc" :key="`fields-${loc}`">
                                        <div class="mb-4">
                                            <label class="block text-sm font-medium mb-1">
                                                {{ $t('cases.stage.field_name') }}
                                                <span v-if="loc === 'es'" class="text-danger">*</span>
                                            </label>
                                            <input
                                                v-model="form.name[loc]"
                                                type="text"
                                                class="form-input"
                                                maxlength="120"
                                                :required="loc === 'es'"
                                            />
                                            <p v-if="loc === 'es' && errors.name_es" class="text-danger text-xs mt-1">
                                                {{ errors.name_es }}
                                            </p>
                                        </div>

                                        <div class="mb-4">
                                            <label class="block text-sm font-medium mb-1">
                                                {{ $t('cases.stage.field_description') }}
                                            </label>
                                            <textarea
                                                v-model="form.description[loc]"
                                                rows="3"
                                                class="form-textarea"
                                                maxlength="500"
                                            ></textarea>
                                        </div>
                                    </div>

                                    <!-- Color picker -->
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium mb-2">
                                            {{ $t('cases.stage.field_color') }}
                                            <span class="text-danger">*</span>
                                        </label>
                                        <div class="flex gap-2 flex-wrap">
                                            <button
                                                v-for="opt in colorOptions"
                                                :key="opt.value"
                                                type="button"
                                                class="w-8 h-8 rounded-full border-2 transition flex items-center justify-center"
                                                :class="form.color === opt.value
                                                    ? 'border-gray-900 dark:border-white scale-110'
                                                    : 'border-transparent hover:scale-105'"
                                                :style="{ backgroundColor: opt.hex }"
                                                :title="opt.value"
                                                @click="form.color = opt.value"
                                            >
                                                <span v-if="form.color === opt.value" class="text-white text-xs">&#10003;</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Terminal toggle -->
                                    <div class="mb-4">
                                        <label class="inline-flex items-center cursor-pointer">
                                            <input
                                                v-model="form.is_terminal"
                                                type="checkbox"
                                                class="form-checkbox"
                                            />
                                            <span class="ml-2 text-sm font-medium">
                                                {{ $t('cases.stage.field_is_terminal') }}
                                            </span>
                                        </label>
                                        <p v-if="form.is_terminal" class="text-warning text-xs mt-2 bg-warning/10 p-2 rounded">
                                            {{ $t('cases.stage.is_terminal_warning') }}
                                        </p>
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
import { reactive, ref, watch, computed } from 'vue';
import { Dialog, DialogPanel, DialogOverlay, TransitionRoot, TransitionChild } from '@headlessui/vue';
import IconX from '@/components/icon/icon-x.vue';
import { useCaseStagesStore } from '@/stores/useCaseStagesStore';
import type { CaseStage, Locale } from '@/types/workflow';

const props = defineProps<{
    modelValue: boolean;
    stage?: CaseStage | null;
    caseId: number;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
    (e: 'saved', stage: CaseStage): void;
}>();

const stagesStore = useCaseStagesStore();

const locales: Locale[] = ['es', 'en', 'fr'];
const activeLocale = ref<Locale>('es');

const colorOptions = [
    { value: 'primary',   hex: '#4361ee' },
    { value: 'secondary', hex: '#805dca' },
    { value: 'success',   hex: '#00ab55' },
    { value: 'danger',    hex: '#e7515a' },
    { value: 'warning',   hex: '#e2a03f' },
    { value: 'info',      hex: '#2196f3' },
    { value: 'dark',      hex: '#3b3f5c' },
] as const;

const isEdit = computed(() => !!props.stage);
const submitting = ref(false);

interface FormShape {
    name: Record<Locale, string>;
    description: Record<Locale, string>;
    color: string;
    is_terminal: boolean;
}

const form = reactive<FormShape>({
    name: { es: '', en: '', fr: '' },
    description: { es: '', en: '', fr: '' },
    color: 'primary',
    is_terminal: false,
});

const errors = reactive<Record<string, string>>({});

watch(
    () => props.modelValue,
    (val) => {
        if (val) {
            // Reset/populate
            Object.keys(errors).forEach((k) => delete errors[k]);
            activeLocale.value = 'es';
            if (props.stage) {
                form.name = {
                    es: props.stage.name?.es ?? '',
                    en: props.stage.name?.en ?? '',
                    fr: props.stage.name?.fr ?? '',
                };
                form.description = {
                    es: props.stage.description?.es ?? '',
                    en: props.stage.description?.en ?? '',
                    fr: props.stage.description?.fr ?? '',
                };
                form.color = props.stage.color || 'primary';
                form.is_terminal = !!props.stage.is_terminal;
            } else {
                form.name = { es: '', en: '', fr: '' };
                form.description = { es: '', en: '', fr: '' };
                form.color = 'primary';
                form.is_terminal = false;
            }
        }
    },
);

function close() {
    if (submitting.value) return;
    emit('update:modelValue', false);
}

function buildPayload() {
    const name: Record<string, string> = {};
    const description: Record<string, string> = {};
    for (const loc of locales) {
        const n = form.name[loc]?.trim();
        if (n) name[loc] = n;
        const d = form.description[loc]?.trim();
        if (d) description[loc] = d;
    }
    return { name, description };
}

async function submit() {
    Object.keys(errors).forEach((k) => delete errors[k]);
    if (!form.name.es?.trim()) {
        errors.name_es = 'required';
        activeLocale.value = 'es';
        return;
    }

    submitting.value = true;
    try {
        const { name, description } = buildPayload();
        let saved: CaseStage | null = null;
        if (isEdit.value && props.stage) {
            saved = await stagesStore.updateStage(props.caseId, props.stage.id, {
                name,
                description,
                color: form.color,
                is_terminal: form.is_terminal,
            });
        } else {
            saved = await stagesStore.createStage(props.caseId, {
                name,
                description,
                color: form.color,
                is_terminal: form.is_terminal,
            });
        }
        if (saved) {
            emit('saved', saved);
            emit('update:modelValue', false);
        }
    } finally {
        submitting.value = false;
    }
}
</script>
