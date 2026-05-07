<template>
    <TransitionRoot appear :show="modelValue" as="template">
        <Dialog as="div" class="relative z-[60]" @close="close">
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
                            class="panel border-0 p-0 rounded-lg overflow-hidden w-full max-w-md text-black dark:text-white-dark"
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
                                {{ $t('cases.stage.delete_policy_title') }}
                            </div>
                            <div class="p-5">
                                <p v-if="stage?.is_terminal" class="text-warning text-sm mb-3 bg-warning/10 p-2 rounded">
                                    {{ $t('cases.stage.delete_blocked_terminal') }}
                                </p>

                                <p class="text-sm mb-4">
                                    <strong>{{ stage?.name_resolved }}</strong>
                                    <span v-if="tasksCount > 0" class="text-gray-500"> &mdash; {{ tasksCount }} </span>
                                </p>

                                <div class="space-y-3">
                                    <label class="flex items-start gap-2 cursor-pointer">
                                        <input
                                            v-model="policy"
                                            type="radio"
                                            value="move"
                                            class="form-radio mt-0.5"
                                        />
                                        <div class="flex-1">
                                            <span class="text-sm font-medium">
                                                {{ $t('cases.stage.delete_policy_move') }}
                                            </span>
                                            <select
                                                v-if="policy === 'move'"
                                                v-model.number="moveToId"
                                                class="form-select mt-2"
                                            >
                                                <option :value="null">{{ $t('cases.stage.delete_policy_dest') }}</option>
                                                <option v-for="s in destinationOptions" :key="s.id" :value="s.id">
                                                    {{ s.name_resolved }}
                                                </option>
                                            </select>
                                        </div>
                                    </label>

                                    <label class="flex items-start gap-2 cursor-pointer">
                                        <input
                                            v-model="policy"
                                            type="radio"
                                            value="trash"
                                            class="form-radio mt-0.5"
                                        />
                                        <span class="text-sm font-medium">
                                            {{ $t('cases.stage.delete_policy_trash') }}
                                        </span>
                                    </label>
                                </div>

                                <div class="flex justify-end gap-3 mt-6">
                                    <button type="button" class="btn btn-outline-secondary" @click="close">
                                        {{ $t('cases.cancel') }}
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-danger"
                                        :disabled="!isValid"
                                        @click="confirm"
                                    >
                                        {{ $t('cases.delete') }}
                                    </button>
                                </div>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script lang="ts" setup>
import { ref, computed, watch } from 'vue';
import { Dialog, DialogPanel, DialogOverlay, TransitionRoot, TransitionChild } from '@headlessui/vue';
import IconX from '@/components/icon/icon-x.vue';
import type { CaseStage, DeleteStagePolicy } from '@/types/workflow';

const props = defineProps<{
    modelValue: boolean;
    stage: CaseStage | null;
    siblingStages: CaseStage[];
    tasksCount: number;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
    (e: 'confirm', payload: { policy: DeleteStagePolicy; moveToStageId?: number }): void;
}>();

const policy = ref<DeleteStagePolicy>('move');
const moveToId = ref<number | null>(null);

const destinationOptions = computed(() =>
    props.siblingStages.filter((s) => s.id !== props.stage?.id),
);

const isValid = computed(() => {
    if (props.tasksCount === 0) return true;
    if (policy.value === 'trash') return true;
    return policy.value === 'move' && !!moveToId.value;
});

watch(
    () => props.modelValue,
    (val) => {
        if (val) {
            policy.value = props.tasksCount === 0 ? 'trash' : 'move';
            moveToId.value = destinationOptions.value[0]?.id ?? null;
        }
    },
);

function close() {
    emit('update:modelValue', false);
}

function confirm() {
    if (!isValid.value) return;
    emit('confirm', {
        policy: policy.value,
        moveToStageId: policy.value === 'move' ? moveToId.value ?? undefined : undefined,
    });
    emit('update:modelValue', false);
}
</script>
