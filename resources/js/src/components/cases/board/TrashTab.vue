<template>
    <div class="space-y-3">
        <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
            <icon-trash class="w-4 h-4" />
            <span class="font-medium">{{ $t('cases.task.trash_tab') }}</span>
        </div>

        <div v-if="store.isLoadingTrash" class="text-center py-6 text-gray-400 text-sm">
            <span class="w-5 h-5 border-2 border-primary border-l-transparent rounded-full animate-spin inline-block"></span>
        </div>

        <div
            v-else-if="store.trashedTasks.length === 0"
            class="text-center py-8 text-gray-400 italic text-sm border border-dashed border-gray-300 dark:border-gray-700 rounded-md"
        >
            {{ $t('cases.task.trash_empty') }}
        </div>

        <div v-else class="space-y-2">
            <div
                v-for="task in store.trashedTasks"
                :key="task.id"
                class="flex items-start gap-3 p-3 rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800"
            >
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 dark:text-gray-100 break-words">
                        {{ task.subject }}
                    </p>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                        <span v-if="task.updated_at">
                            {{ formatDate(task.updated_at) }}
                        </span>
                        <span
                            v-if="!task.task_template_id"
                            class="badge text-[10px] uppercase tracking-wide bg-secondary/10 text-secondary border border-secondary/30"
                        >
                            {{ $t('cases.task.manual') }}
                        </span>
                        <span
                            v-else
                            class="badge text-[10px] uppercase tracking-wide bg-primary/10 text-primary border border-primary/30"
                        >
                            template
                        </span>
                    </div>
                </div>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-success"
                    @click="onRestore(task.id)"
                >
                    {{ $t('cases.task.restored').replace('!', '') || 'Restore' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useCaseTasksStore } from '@/stores/useCaseTasksStore';
import { useNotification } from '@/composables/useNotification';
import IconTrash from '@/components/icon/icon-trash.vue';

const props = defineProps<{
    caseId: number;
}>();

const store = useCaseTasksStore();
const { t } = useI18n();
const { success } = useNotification();

onMounted(async () => {
    await store.loadTrash(props.caseId);
});

async function onRestore(taskId: number) {
    await store.restoreTask(props.caseId, taskId);
    success(t('cases.task.restored'));
}

function formatDate(s: string): string {
    try {
        return new Date(s).toLocaleDateString();
    } catch {
        return s;
    }
}
</script>
