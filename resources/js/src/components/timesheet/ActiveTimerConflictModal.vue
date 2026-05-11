<template>
    <Teleport to="body">
        <transition name="fade">
        <div v-if="show" class="fixed inset-0 z-[999] flex items-center justify-center bg-black/50 px-4">
            <div class="w-full max-w-md rounded-xl bg-white dark:bg-[#1b2e4b] shadow-xl overflow-hidden">
                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-white/10">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-warning" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <h5 class="text-base font-semibold text-dark dark:text-white">{{ $t('timer.conflict_title') }}</h5>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-white transition-colors" @click="$emit('close')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="px-5 py-4 space-y-3">
                    <div class="flex items-start gap-3 p-3 rounded-lg bg-warning/10 border border-warning/20">
                        <div class="space-y-1.5 text-sm w-full">
                            <p class="text-dark dark:text-white">
                                <span class="text-gray-500 dark:text-white/60">{{ $t('timer.case') }}:</span>
                                <router-link
                                    v-if="active?.immigration_case"
                                    :to="`/cases/${active.immigration_case.id}`"
                                    class="ml-1 font-bold text-primary hover:underline"
                                    @click="$emit('close')"
                                >
                                    {{ active.immigration_case.case_code }}
                                </router-link>
                                <span v-if="active?.immigration_case?.client" class="ml-1 text-gray-600 dark:text-white/70">
                                    — {{ active.immigration_case.client.name }}
                                </span>
                            </p>
                            <p class="text-gray-500 dark:text-white/60">
                                {{ $t('timer.started_by') }}: <strong class="text-dark dark:text-white">{{ active?.user?.name }}</strong>
                            </p>
                            <p class="text-gray-500 dark:text-white/60">
                                {{ $t('timer.elapsed') }}: <strong class="text-success">{{ elapsedText }}</strong>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-end gap-2 px-5 py-4 border-t border-gray-100 dark:border-white/10">
                    <button type="button" class="btn btn-outline-secondary" @click="$emit('close')">
                        {{ $t('cancel') }}
                    </button>
                    <button
                        v-if="isMine"
                        type="button"
                        class="btn btn-warning"
                        :disabled="loading"
                        @click="stopAndStartNew"
                    >
                        <span v-if="loading" class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 inline-block mr-2 align-middle"></span>
                        {{ $t('timer.stop_previous_start_new') }}
                    </button>
                    <span v-else class="text-sm text-gray-400 italic">{{ $t('timer.cannot_stop_others') }}</span>
                </div>
            </div>
        </div>
    </transition>
    </Teleport>
</template>

<script lang="ts" setup>
import { computed, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useTimesheetStore } from '@/stores/useTimesheetStore';

const props = defineProps<{
    show: boolean;
    newCaseId: number;
    newTodoId?: number | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'started'): void;
}>();

const authStore = useAuthStore();
const timesheetStore = useTimesheetStore();
const loading = ref(false);

const active = computed(() => timesheetStore.activeTimer);
const isMine = computed(() => active.value?.user?.id === authStore.user?.id);

const elapsedText = computed(() => {
    const t = active.value;
    if (!t?.started_at) return '—';
    const sec = Math.max(0, Math.floor((Date.now() - new Date(t.started_at).getTime()) / 1000));
    const h = Math.floor(sec / 3600);
    const m = String(Math.floor((sec % 3600) / 60)).padStart(2, '0');
    const s = String(sec % 60).padStart(2, '0');
    return h > 0 ? `${h}h ${m}m` : `${m}m ${s}s`;
});

const stopAndStartNew = async (): Promise<void> => {
    loading.value = true;
    try {
        await timesheetStore.stopTimer();
        await timesheetStore.startTimer(props.newCaseId, { todo_id: props.newTodoId ?? null });
        emit('started');
        emit('close');
    } finally {
        loading.value = false;
    }
};
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
