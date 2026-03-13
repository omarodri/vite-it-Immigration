<template>
    <div>
        <!-- Progress bar -->
        <div v-if="totalCount > 0" class="mb-4">
            <div class="flex items-center justify-between text-sm mb-1">
                <span class="text-gray-500">{{ $t('cases.lifecycle_title') }}</span>
                <span class="font-semibold">{{ completedCount }} / {{ totalCount }} — {{ progressPercent }}%</span>
            </div>
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div
                    class="h-2 rounded-full transition-all"
                    :class="getProgressClass(progressPercent)"
                    :style="{ width: `${progressPercent}%` }"
                ></div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="localTasks.length === 0" class="text-sm text-gray-400 italic py-4 text-center">
            {{ $t('cases.lifecycle_no_tasks') }}
        </div>

        <!-- READONLY mode (show.vue) -->
        <template v-if="readonly">
            <div class="space-y-2">
                <div
                    v-for="task in localTasks"
                    :key="task.id ?? task.sort_order"
                    class="flex items-center gap-3 p-2 rounded border"
                    :class="task.is_completed
                        ? 'border-success/30 bg-success/5 dark:bg-success/10'
                        : 'border-[#e0e6ed] dark:border-[#1b2e4b]'"
                >
                    <input
                        type="checkbox"
                        class="form-checkbox"
                        :checked="task.is_completed"
                        @change="toggleRemote(task)"
                    />
                    <span class="flex-1 text-sm" :class="task.is_completed ? 'line-through text-gray-400' : ''">
                        {{ task.label }}
                    </span>
                    <span v-if="task.is_custom" class="badge badge-outline-secondary text-xs shrink-0">custom</span>
                    <span v-if="task.completed_at" class="text-xs text-gray-400 shrink-0">
                        {{ new Date(task.completed_at).toLocaleDateString() }}
                    </span>
                </div>
            </div>
        </template>

        <!-- EDIT mode (edit.vue) -->
        <template v-else>
            <VueDraggable
                v-model="localTasks"
                :animation="150"
                handle=".drag-handle"
                @end="recalculateSortOrder"
                class="space-y-2"
            >
                <div
                    v-for="(task, idx) in localTasks"
                    :key="task.id ?? `new-${idx}`"
                    class="flex items-center gap-2 p-2 rounded border border-[#e0e6ed] dark:border-[#1b2e4b]"
                    :class="task.is_completed ? 'bg-success/5' : ''"
                >
                    <span class="drag-handle cursor-grab text-gray-400 shrink-0">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM8 12a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM8 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM14 6a2 2 0 1 1 4 0 2 2 0 0 1-4 0zM14 12a2 2 0 1 1 4 0 2 2 0 0 1-4 0zM14 18a2 2 0 1 1 4 0 2 2 0 0 1-4 0z"/>
                        </svg>
                    </span>
                    <input type="checkbox" class="form-checkbox shrink-0" v-model="task.is_completed" @change="toggleLocal(idx)" />
                    <span class="flex-1 text-sm" :class="task.is_completed ? 'line-through text-gray-400' : ''">
                        {{ task.label }}
                    </span>
                    <span v-if="task.is_custom" class="badge badge-outline-secondary text-xs shrink-0">custom</span>
                    <button type="button" @click="removeTask(idx)" class="text-danger hover:text-danger/80 shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </VueDraggable>

            <!-- Add custom task -->
            <div class="flex gap-2 mt-3">
                <input
                    v-model="customLabel"
                    type="text"
                    class="form-input flex-1 text-sm"
                    :placeholder="$t('cases.lifecycle_task_placeholder')"
                    maxlength="150"
                    @keydown.enter.prevent="addCustomTask"
                />
                <button type="button" class="btn btn-outline-primary btn-sm" @click="addCustomTask">
                    {{ $t('cases.lifecycle_add_task') }}
                </button>
            </div>

            <!-- Auto-progress notice -->
            <p v-if="totalCount > 0" class="text-xs text-gray-400 mt-2">
                <svg class="w-3 h-3 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ $t('cases.lifecycle_auto_progress') }}
            </p>
        </template>
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { VueDraggable } from 'vue-draggable-plus';
import type { CaseTask } from '@/types/case';
import { useCaseStore } from '@/stores/case';

const props = withDefaults(defineProps<{
    modelValue: CaseTask[];
    readonly?: boolean;
    caseId?: number;
}>(), { readonly: false });

const emit = defineEmits<{
    (e: 'update:modelValue', value: CaseTask[]): void;
    (e: 'progress-updated', progress: number): void;
}>();

const { t } = useI18n();
const caseStore = useCaseStore();

const localTasks = ref<CaseTask[]>([...props.modelValue]);

// Keep localTasks in sync when props change (e.g., after fetching case data)
watch(() => props.modelValue, (newVal) => {
    localTasks.value = [...newVal];
}, { deep: true });

const completedCount = computed(() => localTasks.value.filter(t => t.is_completed).length);
const totalCount = computed(() => localTasks.value.length);
const progressPercent = computed(() =>
    totalCount.value > 0 ? Math.round((completedCount.value / totalCount.value) * 100) : 0
);

const customLabel = ref('');

function getProgressClass(pct: number): string {
    if (pct >= 75) return 'bg-success';
    if (pct >= 50) return 'bg-info';
    if (pct >= 25) return 'bg-warning';
    return 'bg-danger';
}

function recalculateSortOrder() {
    localTasks.value.forEach((t, i) => { t.sort_order = i; });
    emit('update:modelValue', [...localTasks.value]);
}

function toggleLocal(idx: number) {
    const task = localTasks.value[idx];
    task.completed_at = task.is_completed ? new Date().toISOString() : null;
    emit('update:modelValue', [...localTasks.value]);
}

async function toggleRemote(task: CaseTask) {
    if (!props.caseId || !task.id) return;
    try {
        const result = await caseStore.toggleTask(props.caseId, task.id);
        const idx = localTasks.value.findIndex(t => t.id === task.id);
        if (idx !== -1) {
            localTasks.value[idx] = { ...localTasks.value[idx], ...result.task };
        }
        emit('progress-updated', result.progress);
    } catch (e) {
        console.error('Failed to toggle task', e);
    }
}

function removeTask(idx: number) {
    localTasks.value.splice(idx, 1);
    recalculateSortOrder();
}

function addCustomTask() {
    if (!customLabel.value.trim()) return;
    localTasks.value.push({
        label: customLabel.value.trim(),
        is_completed: false,
        is_custom: true,
        sort_order: localTasks.value.length,
    });
    customLabel.value = '';
    emit('update:modelValue', [...localTasks.value]);
}
</script>
