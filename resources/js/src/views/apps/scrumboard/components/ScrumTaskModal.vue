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
                        <DialogPanel class="panel border-0 p-0 rounded-lg overflow-hidden w-full max-w-lg text-black dark:text-white-dark">
                            <button
                                type="button"
                                class="absolute top-4 ltr:right-4 rtl:left-4 text-gray-400 hover:text-gray-800 dark:hover:text-gray-600 outline-none"
                                @click="close"
                            >
                                <icon-x />
                            </button>
                            <div class="text-lg font-medium bg-[#fbfbfb] dark:bg-[#121c2c] ltr:pl-5 rtl:pr-5 py-3 ltr:pr-[50px] rtl:pl-[50px]">
                                {{ task ? $t('scrum_edit_task') : $t('scrum_add_task') }}
                            </div>
                            <div class="p-5 max-h-[80vh] overflow-y-auto">
                                <form @submit.prevent="submit">
                                    <div class="grid gap-5">
                                        <!-- Title -->
                                        <div>
                                            <label for="taskTitle">{{ $t('scrum_task_title') }} <span class="text-red-500">*</span></label>
                                            <input id="taskTitle" v-model="form.title" type="text" class="form-input mt-1" required maxlength="255" />
                                        </div>

                                        <!-- Description -->
                                        <div>
                                            <label for="taskDesc">{{ $t('scrum_task_description') }}</label>
                                            <textarea id="taskDesc" v-model="form.description" class="form-textarea min-h-[100px] mt-1" maxlength="5000"></textarea>
                                        </div>

                                        <!-- Tags -->
                                        <div>
                                            <label for="taskTags">{{ $t('scrum_task_tags') }}</label>
                                            <input id="taskTags" v-model="tagsInput" type="text" class="form-input mt-1" :placeholder="$t('scrum_task_tags_hint')" />
                                            <div v-if="parsedTags.length" class="flex flex-wrap gap-1 mt-2">
                                                <span v-for="tag in parsedTags" :key="tag" class="badge bg-primary/20 text-primary text-xs">{{ tag }}</span>
                                            </div>
                                        </div>

                                        <!-- Category -->
                                        <div>
                                            <label for="taskCategory">{{ $t('scrum_task_category') }}</label>
                                            <input id="taskCategory" v-model="form.category" type="text" class="form-input mt-1" maxlength="100" />
                                        </div>

                                        <!-- Due Date -->
                                        <div>
                                            <label for="taskDueDate">{{ $t('scrum_task_due_date') }}</label>
                                            <input id="taskDueDate" v-model="form.due_date" type="date" class="form-input mt-1" />
                                        </div>

                                        <!-- Assigned To -->
                                        <div>
                                            <label for="taskAssignee">{{ $t('scrum_task_assigned_to') }}</label>
                                            <select id="taskAssignee" v-model="form.assigned_to_id" class="form-select mt-1">
                                                <option :value="null">&mdash;</option>
                                                <option v-for="user in store.assignees" :key="user.id" :value="user.id">{{ user.name }}</option>
                                            </select>
                                        </div>

                                        <!-- Related Case -->
                                        <div>
                                            <label for="taskCase">{{ $t('scrum_task_case') }}</label>
                                            <select id="taskCase" v-model="form.case_id" class="form-select mt-1">
                                                <option :value="null">&mdash;</option>
                                                <option v-for="c in cases" :key="c.id" :value="c.id">
                                                    {{ c.case_number }} — {{ c.client_name }}
                                                </option>
                                            </select>
                                        </div>

                                        <!-- Created at (readonly in edit mode) -->
                                        <div v-if="task">
                                            <label class="text-gray-500 dark:text-gray-400">{{ $t('scrum_task_created_at') }}</label>
                                            <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ formatDate(task.created_at) }}</p>
                                        </div>
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
import { ref, computed, watch } from 'vue';
import { TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogOverlay } from '@headlessui/vue';
import IconX from '@/components/icon/icon-x.vue';
import { useScrumStore } from '@/stores/scrum';
import scrumService from '@/services/scrumService';
import api from '@/services/api';
import type { ScrumTask } from '@/types/scrum';

interface CaseOption {
    id: number;
    case_number: string;
    client_name: string;
}

const props = defineProps<{
    isOpen: boolean;
    task?: ScrumTask | null;
    columnId?: number;
}>();

const emit = defineEmits<{
    close: [];
    saved: [task: ScrumTask];
}>();

const store = useScrumStore();
const loading = ref(false);
const cases = ref<CaseOption[]>([]);

const form = ref({
    title: '',
    description: '',
    category: '',
    due_date: '',
    assigned_to_id: null as number | null,
    case_id: null as number | null,
});
const tagsInput = ref('');

async function loadCases() {
    if (cases.value.length > 0) return;
    try {
        const res = await api.get('/cases?per_page=200&sort_by=created_at&sort_direction=desc');
        cases.value = (res.data.data ?? []).map((c: any) => ({
            id: c.id,
            case_number: c.case_number ?? `#${c.id}`,
            client_name: c.client?.full_name ?? c.client?.first_name ?? '—',
        }));
    } catch {}
}

const parsedTags = computed(() => {
    return tagsInput.value
        .split(',')
        .map((t) => t.trim())
        .filter((t) => t.length > 0);
});

watch(
    () => props.isOpen,
    async (val) => {
        if (val) {
            store.fetchAssignees();
            loadCases();
            if (props.task) {
                // Load full task detail to get complete description
                let fullTask = props.task;
                try {
                    const res = await scrumService.getTask(props.task.id);
                    fullTask = res.data as unknown as ScrumTask;
                } catch {}
                form.value = {
                    title: fullTask.title,
                    description: fullTask.description ?? '',
                    category: fullTask.category ?? '',
                    due_date: fullTask.due_date ?? '',
                    assigned_to_id: fullTask.assigned_to?.id ?? null,
                    case_id: fullTask.case?.id ?? null,
                };
                tagsInput.value = (fullTask.tags ?? []).join(', ');
            } else {
                form.value = { title: '', description: '', category: '', due_date: '', assigned_to_id: null, case_id: null };
                tagsInput.value = '';
            }
        }
    }
);

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString();
}

function close() {
    emit('close');
}

async function submit() {
    loading.value = true;
    try {
        const data = {
            ...form.value,
            tags: parsedTags.value,
            description: form.value.description || undefined,
            category: form.value.category || undefined,
            due_date: form.value.due_date || undefined,
        };

        let savedTask: ScrumTask | null = null;
        if (props.task) {
            savedTask = await store.updateTask(props.task.id, data);
        } else {
            savedTask = await store.createTask({ ...data, scrum_column_id: props.columnId! });
        }

        if (savedTask) emit('saved', savedTask);
        close();
    } finally {
        loading.value = false;
    }
}
</script>
