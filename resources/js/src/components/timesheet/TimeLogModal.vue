<script lang="ts" setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { TransitionRoot, TransitionChild, Dialog, DialogOverlay, DialogPanel } from '@headlessui/vue';
import Swal from 'sweetalert2';

import { useTimesheetStore } from '@/stores/useTimesheetStore';
import { useActiveTimer } from '@/composables/useActiveTimer';
import { durationToSeconds, formatStopwatch } from '@/utils/timeFormat';
import type { TimeLog } from '@/types/timesheet';

import IconX from '@/components/icon/icon-x.vue';

interface TodoOption {
    id: number;
    title: string;
}

interface Props {
    caseId: number;
    todos?: TodoOption[];
    isOpen: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    todos: () => [] as TodoOption[],
});

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'logged', log: TimeLog): void;
}>();

const { t } = useI18n();
const store = useTimesheetStore();
const { elapsed, isRunning, isLongRunning, activeTimer } = useActiveTimer();

const today = () => new Date().toISOString().split('T')[0];

const activeTab = ref<'manual' | 'timer'>('manual');
const hours = ref(0);
const minutes = ref(0);
const workDate = ref(today());
const description = ref('');
const selectedTodoId = ref<number | null>(null);
const isSubmitting = ref(false);

function selectTodo(id: number | null) {
    selectedTodoId.value = id;
}

const elapsedFormatted = computed(() => formatStopwatch(elapsed.value));

// True when the existing timer belongs to a different case — start is disabled in that scenario
const timerLockedToOtherCase = computed(() => {
    return !!activeTimer.value && activeTimer.value.case_id !== props.caseId;
});

function resetForm() {
    hours.value = 0;
    minutes.value = 0;
    description.value = '';
    selectedTodoId.value = null;
    workDate.value = today();
}

watch(
    () => props.isOpen,
    (v) => {
        if (v) {
            // Auto-switch to timer tab if a timer is running for this case
            if (isRunning.value && activeTimer.value?.case_id === props.caseId) {
                activeTab.value = 'timer';
            } else {
                activeTab.value = 'manual';
            }
        } else {
            resetForm();
        }
    },
);

async function submitManual() {
    if (hours.value === 0 && minutes.value === 0) return;
    isSubmitting.value = true;
    try {
        const log = await store.createManualLog(props.caseId, {
            case_id: props.caseId,
            duration_seconds: durationToSeconds(hours.value, minutes.value),
            work_date: workDate.value,
            description: description.value || undefined,
            todo_id: selectedTodoId.value,
        });
        emit('logged', log);
        resetForm();
        emit('close');
    } catch (e: any) {
        Swal.fire({
            icon: 'error',
            title: t('timesheet.error_title'),
            text: e?.response?.data?.message ?? t('timesheet.error_generic'),
        });
    } finally {
        isSubmitting.value = false;
    }
}

async function handleStartTimer() {
    if (timerLockedToOtherCase.value) return;
    isSubmitting.value = true;
    try {
        await store.startTimer(props.caseId, { todo_id: selectedTodoId.value });
        activeTab.value = 'timer';
    } catch (e: any) {
        Swal.fire({
            icon: 'error',
            title: t('timesheet.error_title'),
            text: e?.response?.data?.message ?? t('timesheet.error_generic'),
        });
    } finally {
        isSubmitting.value = false;
    }
}

async function handleStopTimer() {
    isSubmitting.value = true;
    try {
        const log = await store.stopTimer();
        if (log) {
            emit('logged', log);
            emit('close');
        }
    } catch (e: any) {
        Swal.fire({
            icon: 'error',
            title: t('timesheet.error_title'),
            text: e?.response?.data?.message ?? t('timesheet.error_generic'),
        });
    } finally {
        isSubmitting.value = false;
    }
}

function handleClose() {
    emit('close');
}
</script>

<template>
    <TransitionRoot appear :show="isOpen" as="template">
        <Dialog as="div" @close="handleClose" class="relative z-[51]">
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
                        <DialogPanel class="panel border-0 p-0 rounded-lg overflow-hidden w-full max-w-md text-black dark:text-white-dark">
                            <button
                                type="button"
                                class="absolute top-4 ltr:right-4 rtl:left-4 text-gray-400 hover:text-gray-800 dark:hover:text-gray-600 outline-none"
                                @click="handleClose"
                            >
                                <icon-x />
                            </button>
                            <div class="text-lg font-medium bg-[#fbfbfb] dark:bg-[#121c2c] ltr:pl-5 rtl:pr-5 py-3 ltr:pr-[50px] rtl:pl-[50px]">
                                {{ t('timesheet.log_time') }}
                            </div>

                            <!-- Tabs -->
                            <div class="flex border-b border-[#e0e6ed] dark:border-[#1b2e4b]">
                                <button
                                    type="button"
                                    class="flex-1 py-2.5 text-sm font-medium transition-colors"
                                    :class="activeTab === 'manual'
                                        ? 'text-primary border-b-2 border-primary'
                                        : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 border-b-2 border-transparent'"
                                    @click="activeTab = 'manual'"
                                >
                                    {{ t('timesheet.manual_entry') }}
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 py-2.5 text-sm font-medium transition-colors"
                                    :class="activeTab === 'timer'
                                        ? 'text-primary border-b-2 border-primary'
                                        : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 border-b-2 border-transparent'"
                                    @click="activeTab = 'timer'"
                                >
                                    {{ t('timesheet.timer_mode') }}
                                </button>
                            </div>

                            <!-- Tab: Manual -->
                            <form v-if="activeTab === 'manual'" @submit.prevent="submitManual" class="p-5 space-y-4">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                            {{ t('timesheet.hours') }}
                                        </label>
                                        <input
                                            v-model.number="hours"
                                            type="number"
                                            min="0"
                                            max="99"
                                            class="form-input"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                            {{ t('timesheet.minutes') }}
                                        </label>
                                        <input
                                            v-model.number="minutes"
                                            type="number"
                                            min="0"
                                            max="59"
                                            class="form-input"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        {{ t('timesheet.work_date') }}
                                    </label>
                                    <input
                                        v-model="workDate"
                                        type="date"
                                        :max="today()"
                                        class="form-input"
                                    />
                                </div>

                                <div v-if="props.todos.length > 0">
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        {{ t('timesheet.todo_optional') }}
                                    </label>
                                    <select
                                        :value="selectedTodoId"
                                        class="form-select"
                                        @change="selectTodo(($event.target as HTMLSelectElement).value ? +($event.target as HTMLSelectElement).value : null)"
                                    >
                                        <option value="">—</option>
                                        <option v-for="todo in props.todos" :key="todo.id" :value="todo.id">
                                            {{ todo.title }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                        {{ t('timesheet.description') }}
                                    </label>
                                    <textarea v-model="description" rows="2" class="form-textarea" />
                                </div>

                                <div class="flex justify-end gap-2 pt-2">
                                    <button type="button" class="btn btn-outline-danger" @click="handleClose">
                                        {{ t('cases.cancel') }}
                                    </button>
                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                        :disabled="isSubmitting || (hours === 0 && minutes === 0)"
                                    >
                                        {{ t('save') }}
                                    </button>
                                </div>
                            </form>

                            <!-- Tab: Timer -->
                            <div v-else class="p-5 space-y-4">
                                <div v-if="isRunning && activeTimer?.case_id === props.caseId" class="text-center py-4">
                                    <div class="text-4xl font-mono font-bold text-primary mb-2 tabular-nums">
                                        {{ elapsedFormatted }}
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ t('timesheet.timer_running') }}
                                    </p>
                                    <p
                                        v-if="isLongRunning"
                                        class="mt-3 text-xs text-warning"
                                    >
                                        {{ t('timesheet.long_running_warning') }}
                                    </p>
                                </div>

                                <div v-else-if="timerLockedToOtherCase" class="text-center py-4">
                                    <p class="text-sm text-warning">
                                        {{ t('timesheet.error_active_timer_exists') }}
                                    </p>
                                </div>

                                <div v-else>
                                    <div v-if="props.todos.length > 0">
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                            {{ t('timesheet.todo_optional') }}
                                        </label>
                                        <select
                                            :value="selectedTodoId"
                                            class="form-select"
                                            @change="selectTodo(($event.target as HTMLSelectElement).value ? +($event.target as HTMLSelectElement).value : null)"
                                        >
                                            <option value="">—</option>
                                            <option v-for="todo in props.todos" :key="todo.id" :value="todo.id">
                                                {{ todo.title }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="flex justify-center pt-2">
                                    <button
                                        v-if="!isRunning || activeTimer?.case_id !== props.caseId"
                                        type="button"
                                        class="btn btn-success gap-2"
                                        :disabled="isSubmitting || timerLockedToOtherCase"
                                        @click="handleStartTimer"
                                    >
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M8 5v14l11-7z" />
                                        </svg>
                                        {{ t('timesheet.start_timer') }}
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="btn btn-danger gap-2"
                                        :disabled="isSubmitting"
                                        @click="handleStopTimer"
                                    >
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M6 6h12v12H6z" />
                                        </svg>
                                        {{ t('timesheet.stop_timer') }}
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
