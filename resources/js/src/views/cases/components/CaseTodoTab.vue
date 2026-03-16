<template>
    <div class="space-y-4">
        <!-- Top toolbar -->
        <div class="flex flex-wrap items-center gap-3">
            <button type="button" class="btn btn-primary btn-sm gap-1" @click="addEditTask()">
                <icon-plus class="w-4 h-4 shrink-0" />
                {{ $t('todo_add_task') }}
            </button>

            <div class="relative flex-1 min-w-[180px]">
                <input
                    type="text"
                    class="form-input peer ltr:!pr-9 rtl:!pl-9 py-1.5 text-sm"
                    :placeholder="$t('todo_search_placeholder')"
                    v-model="searchTask"
                    @keyup="onSearchKeyup"
                />
                <div class="absolute ltr:right-2 rtl:left-2 top-1/2 -translate-y-1/2 text-gray-400 peer-focus:text-primary">
                    <icon-search class="w-4 h-4" />
                </div>
            </div>

            <select v-model="filterAssigneeId" class="form-select text-sm py-1.5 w-auto" @change="refreshTodos()">
                <option :value="null">{{ $t('todo_filter_all_assignees') }}</option>
                <option v-for="a in todoStore.assignees" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>

            <select v-model="filterTag" class="form-select text-sm py-1.5 w-auto" @change="refreshTodos()">
                <option value="">{{ $t('todo_filter_all_tags') }}</option>
                <option value="archivar">{{ $t('todo_tag_archivar') }}</option>
                <option value="documentos">{{ $t('todo_tag_documentos') }}</option>
                <option value="seguimiento">{{ $t('todo_tag_seguimiento') }}</option>
                <option value="ircc">{{ $t('todo_tag_ircc') }}</option>
                <option value="contabilidad">{{ $t('todo_tag_contabilidad') }}</option>
            </select>
        </div>

        <!-- Status filter bar + pagination -->
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#e0e6ed] dark:border-[#1b2e4b] pb-3">
            <div class="flex items-center gap-1 flex-wrap">
                <button
                    v-for="tab in statusTabs"
                    :key="tab.value"
                    type="button"
                    class="px-3 py-1 rounded-full text-sm font-medium transition-colors"
                    :class="selectedStatus === tab.value
                        ? 'bg-primary text-white'
                        : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-primary/10 hover:text-primary'"
                    @click="changeStatus(tab.value)"
                >
                    {{ $t(tab.label) }}
                    <span v-if="tab.count !== undefined" class="ml-1 text-xs opacity-75">({{ tab.count }})</span>
                </button>
            </div>
            <div class="flex items-center gap-2 text-sm text-gray-500">
                <span>{{ pagerText }}</span>
                <button
                    type="button"
                    :disabled="todoStore.currentPage <= 1"
                    class="bg-[#f4f4f4] rounded-md p-1 enabled:hover:bg-primary-light dark:bg-white-dark/20 enabled:dark:hover:bg-white-dark/30 disabled:opacity-50 disabled:cursor-not-allowed"
                    @click="prevPage()"
                >
                    <icon-caret-down class="w-4 h-4 rtl:-rotate-90 rotate-90" />
                </button>
                <button
                    type="button"
                    :disabled="todoStore.currentPage >= totalPages"
                    class="bg-[#f4f4f4] rounded-md p-1 enabled:hover:bg-primary-light dark:bg-white-dark/20 enabled:dark:hover:bg-white-dark/30 disabled:opacity-50 disabled:cursor-not-allowed"
                    @click="nextPage()"
                >
                    <icon-caret-down class="w-4 h-4 rtl:rotate-90 -rotate-90" />
                </button>
            </div>
        </div>

        <!-- Loading -->
        <template v-if="todoStore.isLoading">
            <div class="flex justify-center items-center min-h-[200px]">
                <span class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10 inline-block"></span>
            </div>
        </template>

        <!-- Tasks table -->
        <template v-else-if="todoStore.todos.length">
            <div class="table-responsive">
                <table class="table-hover">
                    <tbody>
                        <template v-for="task in todoStore.todos" :key="task.id">
                            <tr class="group cursor-pointer" :class="{ 'bg-white-light/30 dark:bg-[#1a2941]': task.status === 'complete' }">
                                <td class="w-1">
                                    <input
                                        type="checkbox"
                                        :id="`chk-case-${task.id}`"
                                        class="form-checkbox"
                                        :checked="task.status === 'complete'"
                                        @click.stop="taskComplete(task)"
                                        :disabled="selectedStatus === 'trash'"
                                    />
                                </td>
                                <td>
                                    <div @click="viewTask(task)">
                                        <div class="flex items-center gap-1.5">
                                            <icon-star
                                                v-if="task.status === 'important'"
                                                class="w-4 h-4 shrink-0 text-warning fill-warning"
                                            />
                                            <div
                                                class="group-hover:text-primary font-semibold text-base whitespace-nowrap"
                                                :class="{ 'line-through': task.status === 'complete' }"
                                            >
                                                {{ task.title }}
                                            </div>
                                        </div>
                                        <div
                                            class="text-white-dark overflow-hidden min-w-[200px] line-clamp-1"
                                            :class="{ 'line-through': task.status === 'complete' }"
                                            v-html="stripHtml(task.description)"
                                        ></div>
                                    </div>
                                </td>
                                <td class="w-1">
                                    <div class="flex items-center ltr:justify-end rtl:justify-start gap-2">
                                        <template v-if="task.priority">
                                            <span
                                                class="badge rounded-full capitalize"
                                                :class="{
                                                    'badge-outline-primary': task.priority === 'medium',
                                                    'badge-outline-warning': task.priority === 'low',
                                                    'badge-outline-danger': task.priority === 'high',
                                                }"
                                            >
                                                {{ $t('todo_priority_' + task.priority) }}
                                            </span>
                                        </template>
                                        <template v-if="task.tag">
                                            <span
                                                class="badge rounded-full capitalize"
                                                :class="tagBadgeOutlineClass(task.tag)"
                                            >
                                                {{ $t('todo_tag_' + task.tag) }}
                                            </span>
                                        </template>
                                    </div>
                                </td>
                                <td class="w-1">
                                    <p
                                        class="whitespace-nowrap font-medium text-sm"
                                        :class="[task.due_date ? dueDateClass(task) : 'text-white-dark', { 'line-through': task.status === 'complete' }]"
                                    >
                                        {{ formatDate(task.due_date || task.created_at) }}
                                    </p>
                                </td>
                                <td class="w-1">
                                    <div class="flex items-center justify-between w-max gap-2">
                                        <div
                                            v-if="task.assigned_to"
                                            class="grid place-content-center h-7 w-7 rounded-full bg-primary text-white text-xs font-semibold"
                                            :title="task.assigned_to.name"
                                        >
                                            {{ getInitials(task.assigned_to.name) }}
                                        </div>
                                        <div
                                            v-else
                                            class="border border-gray-300 dark:border-gray-800 rounded-full grid place-content-center h-7 w-7"
                                        >
                                            <icon-user class="w-4 h-4" />
                                        </div>
                                        <div class="dropdown">
                                            <Popper
                                                :placement="store.rtlClass === 'rtl' ? 'bottom-start' : 'bottom-end'"
                                                offsetDistance="0"
                                                class="align-middle"
                                            >
                                                <a href="javascript:;">
                                                    <icon-horizontal-dots class="rotate-90 opacity-70" />
                                                </a>
                                                <template #content="{ close }">
                                                    <ul @click="close()" class="whitespace-nowrap">
                                                        <template v-if="selectedStatus !== 'trash'">
                                                            <li>
                                                                <a href="javascript:;" @click="addEditTask(task)">
                                                                    <icon-pencil-paper class="w-4.5 h-4.5 ltr:mr-2 rtl:ml-2 shrink-0" />
                                                                    {{ $t('todo_edit') }}
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="javascript:;" @click="cloneTask(task)">
                                                                    <icon-copy class="w-4.5 h-4.5 ltr:mr-2 rtl:ml-2 shrink-0" />
                                                                    {{ $t('todo_clone') }}
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="javascript:;" @click="deleteTask(task, 'delete')">
                                                                    <icon-trash-lines class="ltr:mr-2 rtl:ml-2 shrink-0" />
                                                                    {{ $t('todo_delete') }}
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="javascript:;" @click="setImportant(task)">
                                                                    <icon-star class="w-4.5 h-4.5 ltr:mr-2 rtl:ml-2 shrink-0" />
                                                                    {{ task.status === 'important' ? $t('todo_not_important') : $t('todo_important') }}
                                                                </a>
                                                            </li>
                                                        </template>
                                                        <template v-if="selectedStatus === 'trash'">
                                                            <li>
                                                                <a href="javascript:;" @click="deleteTask(task, 'deletePermanent')">
                                                                    <icon-trash-lines class="ltr:mr-2 rtl:ml-2 shrink-0" />
                                                                    {{ $t('todo_permanent_delete') }}
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="javascript:;" @click="deleteTask(task, 'restore')">
                                                                    <icon-restore class="ltr:mr-2 rtl:ml-2 shrink-0" />
                                                                    {{ $t('todo_restore') }}
                                                                </a>
                                                            </li>
                                                        </template>
                                                    </ul>
                                                </template>
                                            </Popper>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>
        <template v-else>
            <div class="flex justify-center items-center min-h-[200px] text-gray-500">
                {{ $t('todo_no_data') }}
            </div>
        </template>

        <!-- Add/Edit Task Modal -->
        <TransitionRoot appear :show="addTaskModal" as="template">
            <Dialog as="div" @close="addTaskModal = false" class="relative z-[51]">
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
                                    @click="addTaskModal = false"
                                >
                                    <icon-x />
                                </button>
                                <div class="text-lg font-medium bg-[#fbfbfb] dark:bg-[#121c2c] ltr:pl-5 rtl:pr-5 py-3 ltr:pr-[50px] rtl:pl-[50px]">
                                    {{ params.id ? $t('todo_edit_task') : $t('todo_add_task') }}
                                </div>
                                <div class="p-5">
                                    <form @submit.prevent="saveTask">
                                        <div class="mb-5">
                                            <label for="case-todo-title">{{ $t('todo_title') }}</label>
                                            <input id="case-todo-title" type="text" :placeholder="$t('todo_title_placeholder')" class="form-input" v-model="params.title" />
                                        </div>
                                        <div class="mb-5">
                                            <label for="case-todo-assignee">{{ $t('todo_assignee') }}</label>
                                            <select id="case-todo-assignee" class="form-select" v-model="params.assigned_to_id">
                                                <option :value="null">{{ $t('todo_assignee_placeholder') }}</option>
                                                <option v-for="a in todoStore.assignees" :key="a.id" :value="a.id">{{ a.name }}</option>
                                            </select>
                                        </div>
                                        <!-- case_id is pre-filled and shown as read-only info -->
                                        <div class="mb-5">
                                            <label>{{ $t('todo_case') }}</label>
                                            <div class="form-input bg-gray-50 dark:bg-gray-900 text-gray-500 cursor-not-allowed">
                                                #{{ caseNumber }}
                                            </div>
                                        </div>
                                        <div class="mb-5 flex justify-between gap-4">
                                            <div class="flex-1">
                                                <label for="case-todo-tag">{{ $t('todo_tag_label') }}</label>
                                                <select id="case-todo-tag" class="form-select" v-model="params.tag">
                                                    <option value="">{{ $t('todo_tag_select') }}</option>
                                                    <option value="archivar">{{ $t('todo_tag_archivar') }}</option>
                                                    <option value="documentos">{{ $t('todo_tag_documentos') }}</option>
                                                    <option value="seguimiento">{{ $t('todo_tag_seguimiento') }}</option>
                                                    <option value="ircc">{{ $t('todo_tag_ircc') }}</option>
                                                    <option value="contabilidad">{{ $t('todo_tag_contabilidad') }}</option>
                                                </select>
                                            </div>
                                            <div class="flex-1">
                                                <label for="case-todo-priority">{{ $t('todo_priority_label') }}</label>
                                                <select id="case-todo-priority" class="form-select" v-model="params.priority">
                                                    <option value="">{{ $t('todo_priority_select') }}</option>
                                                    <option value="low">{{ $t('todo_priority_low') }}</option>
                                                    <option value="medium">{{ $t('todo_priority_medium') }}</option>
                                                    <option value="high">{{ $t('todo_priority_high') }}</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="mb-5">
                                            <label for="case-todo-due-date">{{ $t('todo_due_date') }}</label>
                                            <input id="case-todo-due-date" type="date" class="form-input" v-model="params.due_date" />
                                        </div>
                                        <div class="mb-5">
                                            <label>{{ $t('todo_description') }}</label>
                                            <quillEditor
                                                ref="editor"
                                                v-model:value="params.description"
                                                :options="editorOptions"
                                                style="min-height: 200px"
                                                @ready="quillEditorReady($event)"
                                            ></quillEditor>
                                        </div>
                                        <div class="ltr:text-right rtl:text-left flex justify-end items-center mt-8">
                                            <button type="button" class="btn btn-outline-danger" @click="addTaskModal = false">{{ $t('todo_cancel') }}</button>
                                            <button type="submit" class="btn btn-primary ltr:ml-4 rtl:mr-4" :disabled="isSaving">
                                                <span v-if="isSaving" class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 inline-block align-middle ltr:mr-2 rtl:ml-2"></span>
                                                {{ params.id ? $t('todo_update') : $t('add') }}
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

        <!-- View Task Modal -->
        <TransitionRoot appear :show="viewTaskModal" as="template">
            <Dialog as="div" @close="viewTaskModal = false" class="relative z-[51]">
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
                                    @click="viewTaskModal = false"
                                >
                                    <icon-x />
                                </button>
                                <div class="flex items-center flex-wrap gap-2 text-lg font-medium bg-[#fbfbfb] dark:bg-[#121c2c] ltr:pl-5 rtl:pr-5 py-3 ltr:pr-[50px] rtl:pl-[50px]">
                                    <div>{{ selectedTask.title }}</div>
                                    <div
                                        v-show="selectedTask.priority"
                                        class="badge rounded-3xl capitalize"
                                        :class="{
                                            'badge-outline-primary': selectedTask.priority === 'medium',
                                            'badge-outline-warning': selectedTask.priority === 'low',
                                            'badge-outline-danger': selectedTask.priority === 'high',
                                        }"
                                    >
                                        {{ selectedTask.priority }}
                                    </div>
                                    <div
                                        v-show="selectedTask.tag"
                                        class="badge rounded-3xl capitalize"
                                        :class="tagBadgeOutlineClass(selectedTask.tag)"
                                    >
                                        {{ selectedTask.tag ? $t('todo_tag_' + selectedTask.tag) : '' }}
                                    </div>
                                </div>
                                <div class="p-5">
                                    <div class="text-base prose" v-html="selectedTask.description"></div>
                                    <div v-if="selectedTask.assigned_to" class="mt-4 flex items-center gap-2 text-sm text-white-dark">
                                        <span class="font-medium">{{ $t('todo_assignee') }}:</span>
                                        {{ selectedTask.assigned_to.name }}
                                    </div>
                                    <div v-if="selectedTask.due_date" class="mt-2 flex items-center gap-2 text-sm text-white-dark">
                                        <span class="font-medium">{{ $t('todo_due_date') }}:</span>
                                        {{ formatDate(selectedTask.due_date) }}
                                    </div>
                                    <div class="flex justify-end items-center mt-8">
                                        <button type="button" class="btn btn-outline-danger" @click="viewTaskModal = false">{{ $t('todo_close') }}</button>
                                    </div>
                                </div>
                            </DialogPanel>
                        </TransitionChild>
                    </div>
                </div>
            </Dialog>
        </TransitionRoot>
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogOverlay } from '@headlessui/vue';
import { quillEditor } from 'vue3-quill';
import 'vue3-quill/lib/vue3-quill.css';
import Swal from 'sweetalert2';

import { useAppStore } from '@/stores/index';
import { useTodoStore } from '@/stores/todo';
import type { Todo, CreateTodoData, UpdateTodoData } from '@/types/todo';

import IconPlus from '@/components/icon/icon-plus.vue';
import IconSearch from '@/components/icon/icon-search.vue';
import IconCaretDown from '@/components/icon/icon-caret-down.vue';
import IconStar from '@/components/icon/icon-star.vue';
import IconTrashLines from '@/components/icon/icon-trash-lines.vue';
import IconUser from '@/components/icon/icon-user.vue';
import IconHorizontalDots from '@/components/icon/icon-horizontal-dots.vue';
import IconPencilPaper from '@/components/icon/icon-pencil-paper.vue';
import IconCopy from '@/components/icon/icon-copy.vue';
import IconRestore from '@/components/icon/icon-restore.vue';
import IconX from '@/components/icon/icon-x.vue';

const props = defineProps<{
    caseId: number;
    caseNumber: string;
}>();

const { t } = useI18n();
const store = useAppStore();
const todoStore = useTodoStore();

const defaultParams = {
    id: null as number | null,
    title: '',
    description: '',
    assigned_to_id: null as number | null,
    tag: '',
    priority: 'low',
    due_date: '',
};

const selectedStatus = ref('');
const filterTag = ref('');
const filterAssigneeId = ref<number | null>(null);
const searchTask = ref('');
const addTaskModal = ref(false);
const viewTaskModal = ref(false);
const isSaving = ref(false);
const params = ref({ ...defaultParams });
const selectedTask = ref<Partial<Todo>>({});

let searchDebounce: ReturnType<typeof setTimeout> | null = null;

const editorOptions = ref({
    modules: {
        toolbar: [[{ header: [1, 2, false] }], ['bold', 'italic', 'underline', 'link'], [{ list: 'ordered' }, { list: 'bullet' }], ['clean']],
    },
    placeholder: '',
});
const quillEditorObj = ref<any>(null);

const statusTabs = computed(() => [
    { value: '',          label: 'todo_inbox',     count: todoStore.todos.filter(d => d.status !== 'trash').length },
    { value: 'complete',  label: 'todo_done',      count: todoStore.todos.filter(d => d.status === 'complete').length },
    { value: 'important', label: 'todo_important', count: todoStore.todos.filter(d => d.status === 'important').length },
    { value: 'trash',     label: 'todo_trash',     count: undefined },
]);

const totalPages = computed(() => Math.max(1, Math.ceil(todoStore.total / todoStore.perPage)));

const pagerText = computed(() => {
    if (todoStore.total === 0) return '0 of 0';
    const start = (todoStore.currentPage - 1) * todoStore.perPage + 1;
    const end = Math.min(start + todoStore.todos.length - 1, todoStore.total);
    return `${start}-${end} of ${todoStore.total}`;
});

onMounted(async () => {
    await Promise.all([
        todoStore.fetchAssignees(),
        refreshTodos(),
    ]);
});

function buildFilterParams(): Record<string, any> {
    const p: Record<string, any> = { case_id: props.caseId };

    if (selectedStatus.value === 'complete' || selectedStatus.value === 'important' || selectedStatus.value === 'trash') {
        p.status = selectedStatus.value;
    }
    if (filterTag.value) p.tag = filterTag.value;
    if (searchTask.value) p.search = searchTask.value;
    if (filterAssigneeId.value) p.assigned_to_id = filterAssigneeId.value;

    return p;
}

async function refreshTodos() {
    await todoStore.fetchTodos(buildFilterParams());
}

async function changeStatus(status: string) {
    selectedStatus.value = status;
    todoStore.currentPage = 1;
    await refreshTodos();
}

function onSearchKeyup() {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(async () => {
        todoStore.currentPage = 1;
        await refreshTodos();
    }, 400);
}

async function prevPage() {
    if (todoStore.currentPage > 1) {
        todoStore.currentPage--;
        await refreshTodos();
    }
}

async function nextPage() {
    if (todoStore.currentPage < totalPages.value) {
        todoStore.currentPage++;
        await refreshTodos();
    }
}

async function taskComplete(task: Todo) {
    const newStatus = task.status === 'complete' ? 'pending' : 'complete';
    await todoStore.updateStatus(task.id, newStatus);
    await refreshTodos();
}

async function setImportant(task: Todo) {
    const newStatus = task.status === 'important' ? 'pending' : 'important';
    await todoStore.updateStatus(task.id, newStatus);
    await refreshTodos();
}

async function cloneTask(task: Todo) {
    try {
        await todoStore.createTodo({
            title: task.title,
            description: task.description ?? undefined,
            assigned_to_id: task.assigned_to?.id ?? null,
            case_id: props.caseId,
            tag: task.tag ?? undefined,
            priority: task.priority ?? 'low',
            due_date: task.due_date ?? undefined,
            status: 'pending',
        });
        showMessage(t('todo_task_cloned'));
        await refreshTodos();
    } catch {
        showMessage(t('todo_save_failed'), 'error');
    }
}

async function deleteTask(task: Todo, type: string) {
    if (type === 'delete') {
        await todoStore.updateStatus(task.id, 'trash');
    } else if (type === 'deletePermanent') {
        await todoStore.deleteTodo(task.id);
    } else if (type === 'restore') {
        await todoStore.updateStatus(task.id, 'pending');
    }
    await refreshTodos();
}

function viewTask(item: Todo) {
    selectedTask.value = item;
    setTimeout(() => { viewTaskModal.value = true; });
}

function addEditTask(task?: Todo) {
    params.value = { ...defaultParams };
    if (task) {
        params.value = {
            id: task.id,
            title: task.title,
            description: task.description || '',
            assigned_to_id: task.assigned_to?.id ?? null,
            tag: task.tag || '',
            priority: task.priority || 'low',
            due_date: task.due_date || '',
        };
    }
    addTaskModal.value = true;
}

async function saveTask() {
    if (!params.value.title) {
        showMessage(t('todo_title_required'), 'error');
        return;
    }

    isSaving.value = true;
    try {
        const data: CreateTodoData | UpdateTodoData = {
            title: params.value.title,
            description: params.value.description,
            assigned_to_id: params.value.assigned_to_id || null,
            case_id: props.caseId,
            tag: params.value.tag || undefined,
            priority: (params.value.priority as Todo['priority']) || 'low',
            due_date: params.value.due_date || undefined,
        };

        if (params.value.id) {
            await todoStore.updateTodo(params.value.id, data);
            showMessage(t('todo_task_updated'));
        } else {
            await todoStore.createTodo({ ...data, status: 'pending' } as CreateTodoData);
            showMessage(t('todo_task_created'));
        }
        addTaskModal.value = false;
        await refreshTodos();
    } catch {
        showMessage(t('todo_save_failed'), 'error');
    } finally {
        isSaving.value = false;
    }
}

function quillEditorReady(quill: any) {
    quillEditorObj.value = quill;
}

function getInitials(name: string): string {
    if (!name) return '';
    const parts = name.split(' ');
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return name.substring(0, 2).toUpperCase();
}

function stripHtml(html?: string | null): string {
    if (!html) return '';
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
}

function formatDate(dateStr?: string | null): string {
    if (!dateStr) return '';
    try {
        const d = new Date(dateStr);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${months[d.getMonth()]}, ${String(d.getDate()).padStart(2, '0')} ${d.getFullYear()}`;
    } catch {
        return dateStr;
    }
}

function dueDateClass(task: Todo): string {
    if (!task.due_date || task.status === 'complete' || task.status === 'trash') return 'text-white-dark';
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const due = new Date(task.due_date);
    const diffDays = Math.ceil((due.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
    if (diffDays < 0) return 'text-danger';
    if (diffDays <= 7) return 'text-warning';
    return 'text-success';
}

const TAG_BADGE_OUTLINE: Record<string, string> = {
    archivar: 'badge-outline-secondary',
    documentos: 'badge-outline-info',
    seguimiento: 'badge-outline-warning',
    ircc: 'badge-outline-primary',
    contabilidad: 'badge-outline-success',
};

function tagBadgeOutlineClass(tag: string | undefined | null): string {
    if (!tag) return 'badge-outline-info';
    return TAG_BADGE_OUTLINE[tag] ?? 'badge-outline-info';
}

const showMessage = (msg = '', type = 'success') => {
    const toast: any = Swal.mixin({
        toast: true,
        position: 'top',
        showConfirmButton: false,
        timer: 3000,
        customClass: { container: 'toast' },
    });
    toast.fire({ icon: type, title: msg, padding: '10px 20px' });
};
</script>
