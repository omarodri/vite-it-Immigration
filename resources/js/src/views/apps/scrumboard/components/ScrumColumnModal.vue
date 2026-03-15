<template>
    <TransitionRoot appear :show="isOpen" as="template">
        <Dialog as="div" @close="close" class="relative z-[51]">
            <TransitionChild
                as="template"
                enter="duration-300 ease-out"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="duration-200 ease-in"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <DialogOverlay class="fixed inset-0 bg-[black]/60" />
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
                        <DialogPanel class="panel border-0 p-0 rounded-lg overflow-hidden w-full max-w-sm text-black dark:text-white-dark">
                            <button
                                type="button"
                                class="absolute top-4 ltr:right-4 rtl:left-4 text-gray-400 hover:text-gray-800 dark:hover:text-gray-600 outline-none"
                                @click="close"
                            >
                                <icon-x />
                            </button>
                            <div class="text-lg font-medium bg-[#fbfbfb] dark:bg-[#121c2c] ltr:pl-5 rtl:pr-5 py-3 ltr:pr-[50px] rtl:pl-[50px]">
                                {{ column ? $t('scrum_edit_column') : $t('scrum_add_column') }}
                            </div>
                            <div class="p-5">
                                <form @submit.prevent="submit">
                                    <div class="mb-4">
                                        <label for="columnTitle" class="block text-sm font-medium mb-1">{{ $t('scrum_column_title') }}</label>
                                        <input
                                            id="columnTitle"
                                            v-model="form.title"
                                            type="text"
                                            class="form-input"
                                            :placeholder="$t('scrum_column_title')"
                                            required
                                            maxlength="100"
                                        />
                                    </div>
                                    <div class="flex justify-end items-center mt-8">
                                        <button type="button" class="btn btn-outline-danger" @click="close">{{ $t('cancel') }}</button>
                                        <button type="submit" class="btn btn-primary ltr:ml-4 rtl:mr-4" :disabled="loading">
                                            <span v-if="loading" class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 inline-block align-middle ltr:mr-2 rtl:ml-2"></span>
                                            {{ $t('save') }}
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
import { TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogOverlay } from '@headlessui/vue';
import IconX from '@/components/icon/icon-x.vue';
import type { ScrumColumn } from '@/types/scrum';

const props = defineProps<{
    isOpen: boolean;
    column?: ScrumColumn | null;
}>();

const emit = defineEmits<{
    close: [];
    save: [title: string];
}>();

const form = ref({ title: '' });
const loading = ref(false);

watch(
    () => props.isOpen,
    (val) => {
        if (val) {
            form.value.title = props.column?.title ?? '';
        }
    }
);

function close() {
    emit('close');
}

function submit() {
    if (!form.value.title.trim()) return;
    emit('save', form.value.title.trim());
}
</script>
