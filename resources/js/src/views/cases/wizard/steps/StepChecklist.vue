<template>
    <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">
            {{ $t('case_tasks.step_title') }}
        </h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
            {{ $t('case_tasks.step_subtitle') }}
        </p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left: Available base tasks -->
            <div class="panel">
                <h6 class="text-base font-medium mb-3">{{ $t('case_tasks.available_tasks') }}</h6>
                <div class="space-y-2">
                    <label
                        v-for="task in DEFAULT_CASE_TASKS"
                        :key="task.key"
                        class="flex items-center gap-3 p-2 rounded cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                    >
                        <input
                            type="checkbox"
                            class="form-checkbox"
                            :checked="isTaskSelected(task.key)"
                            @change="toggleBaseTask(task.key)"
                        />
                        <span class="text-sm">{{ getTaskLabel(task.key) }}</span>
                    </label>
                </div>
            </div>

            <!-- Right: Selected tasks with drag-and-drop -->
            <div class="panel">
                <h6 class="text-base font-medium mb-3">
                    {{ $t('case_tasks.selected_tasks') }}
                    <span class="badge badge-outline-primary ml-2">{{ selectedTasks.length }}</span>
                </h6>

                <div v-if="selectedTasks.length === 0" class="text-sm text-gray-400 italic py-4 text-center">
                    {{ $t('case_tasks.no_selected') }}
                </div>

                <VueDraggable
                    v-model="wizard.state.selectedTasks"
                    :animation="150"
                    handle=".drag-handle"
                    @end="recalculateSortOrder"
                    class="space-y-2"
                >
                    <div
                        v-for="(task, idx) in selectedTasks"
                        :key="task.key ?? `custom-${idx}`"
                        class="flex items-center gap-2 p-2 bg-white dark:bg-gray-900 rounded border border-[#e0e6ed] dark:border-[#1b2e4b]"
                    >
                        <!-- Drag handle -->
                        <span class="drag-handle cursor-grab text-gray-400 shrink-0">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM8 12a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM8 18a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM14 6a2 2 0 1 1 4 0 2 2 0 0 1-4 0zM14 12a2 2 0 1 1 4 0 2 2 0 0 1-4 0zM14 18a2 2 0 1 1 4 0 2 2 0 0 1-4 0z"/>
                            </svg>
                        </span>
                        <!-- Label -->
                        <span class="flex-1 text-sm">{{ task.label }}</span>
                        <!-- Custom badge -->
                        <span v-if="task.is_custom" class="badge badge-outline-secondary text-xs shrink-0">
                            {{ $t('case_tasks.custom_task_label') }}
                        </span>
                        <!-- Remove -->
                        <button type="button" @click="removeTask(idx)" class="text-danger hover:text-danger/80 shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </VueDraggable>

                <!-- Add custom task -->
                <div class="flex gap-2 mt-4">
                    <input
                        v-model="customTaskLabel"
                        type="text"
                        class="form-input flex-1 text-sm"
                        :placeholder="$t('case_tasks.custom_task_label') + '...'"
                        maxlength="150"
                        @keydown.enter.prevent="addCustomTask"
                    />
                    <button type="button" class="btn btn-outline-primary btn-sm" @click="addCustomTask">
                        {{ $t('case_tasks.add_custom') }}
                    </button>
                </div>
            </div>
        </div>

        <p class="text-xs text-gray-400 mt-4 text-center">
            <svg class="w-3 h-3 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ $t('case_tasks.optional_step') }}
        </p>
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, watch, inject } from 'vue';
import { useI18n } from 'vue-i18n';
import { VueDraggable } from 'vue-draggable-plus';
import { DEFAULT_CASE_TASKS } from '@/types/case';
import type { WizardTaskItem } from '@/types/wizard';

// Get wizard from parent via inject (same pattern as StepDetails, StepCompanions)
const wizard = inject<ReturnType<typeof import('@/composables/useCaseWizard').useCaseWizard>>('wizard')!;

const { t } = useI18n();

// The language selected in step 4 (caseDetails)
const caseLanguage = computed(() => wizard.state.caseDetails.language || 'es');

// Translated label for a base task key in the case language
function getTaskLabel(key: string): string {
    // vue-i18n supports per-call locale override via the 3rd argument
    return t(`case_tasks.${key}`, 1, { locale: caseLanguage.value });
}

// Selected tasks (reactive reference to wizard state)
const selectedTasks = computed(() => wizard.state.selectedTasks as WizardTaskItem[]);

// Check if a base task key is selected
function isTaskSelected(key: string): boolean {
    return selectedTasks.value.some(t => t.key === key);
}

// Toggle a base task
function toggleBaseTask(key: string) {
    if (isTaskSelected(key)) {
        wizard.state.selectedTasks = selectedTasks.value.filter(t => t.key !== key);
    } else {
        wizard.state.selectedTasks = [
            ...selectedTasks.value,
            { key, label: getTaskLabel(key), is_custom: false, sort_order: selectedTasks.value.length },
        ];
    }
    recalculateSortOrder();
}

// Custom task
const customTaskLabel = ref('');

function addCustomTask() {
    if (!customTaskLabel.value.trim()) return;
    wizard.state.selectedTasks = [
        ...selectedTasks.value,
        { key: null, label: customTaskLabel.value.trim(), is_custom: true, sort_order: selectedTasks.value.length },
    ];
    customTaskLabel.value = '';
    recalculateSortOrder();
}

function removeTask(idx: number) {
    wizard.state.selectedTasks.splice(idx, 1);
    recalculateSortOrder();
}

function recalculateSortOrder() {
    wizard.state.selectedTasks.forEach((t: WizardTaskItem, i: number) => { t.sort_order = i; });
}

// When language changes in step 4, retranslate base tasks
watch(caseLanguage, (newLang) => {
    wizard.state.selectedTasks = selectedTasks.value.map((task: WizardTaskItem) => {
        if (!task.is_custom && task.key) {
            return { ...task, label: t(`case_tasks.${task.key}`, 1, { locale: newLang }) };
        }
        return task;
    });
});
</script>
