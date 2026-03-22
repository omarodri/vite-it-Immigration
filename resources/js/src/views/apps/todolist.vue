<template>
    <div>
        <div class="flex gap-5 relative sm:h-[calc(100vh_-_150px)] h-full">
            <!-- Sidebar -->
            <div
                class="panel p-4 flex-none w-[240px] max-w-full absolute xl:relative z-10 space-y-4 xl:h-auto h-full xl:block ltr:xl:rounded-r-md ltr:rounded-r-none rtl:xl:rounded-l-md rtl:rounded-l-none hidden"
                :class="{ '!block': isShowTaskMenu }"
            >
                <div class="flex flex-col h-full pb-16">
                    <div class="pb-5">
                        <div class="flex text-center items-center">
                            <div class="shrink-0">
                                <icon-clipboard-text />
                            </div>
                            <h3 class="text-lg font-semibold ltr:ml-3 rtl:mr-3">{{ $t('todo_list') }}</h3>
                        </div>
                    </div>
                    <div class="h-px w-full border-b border-[#e0e6ed] dark:border-[#1b2e4b] mb-5"></div>
                    <perfect-scrollbar
                        :options="{
                            swipeEasing: true,
                            wheelPropagation: false,
                        }"
                        class="relative ltr:pr-3.5 rtl:pl-3.5 ltr:-mr-3.5 rtl:-ml-3.5 h-full grow"
                    >
                        <div class="space-y-1">
                            <button
                                type="button"
                                class="w-full flex justify-between items-center p-2 hover:bg-white-dark/10 rounded-md dark:hover:text-primary hover:text-primary dark:hover:bg-[#181F32] font-medium h-10"
                                :class="{ 'bg-gray-100 dark:text-primary text-primary dark:bg-[#181F32]': selectedTab === '' }"
                                @click="tabChanged('')"
                            >
                                <div class="flex items-center">
                                    <icon-list-check class="w-4.5 h-4.5 shrink-0" />
                                    <div class="ltr:ml-3 rtl:mr-3">{{ $t('todo_inbox') }}</div>
                                </div>
                                <div class="bg-primary-light dark:bg-[#060818] rounded-md py-0.5 px-2 font-semibold whitespace-nowrap">
                                    {{ inboxCount }}
                                </div>
                            </button>
                            <button
                                type="button"
                                class="w-full flex justify-between items-center p-2 hover:bg-white-dark/10 rounded-md dark:hover:text-primary hover:text-primary dark:hover:bg-[#181F32] font-medium h-10"
                                :class="{ 'bg-gray-100 dark:text-primary text-primary dark:bg-[#181F32]': selectedTab === 'complete' }"
                                @click="tabChanged('complete')"
                            >
                                <div class="flex items-center">
                                    <icon-thumb-up class="w-5 h-5 shrink-0" />
                                    <div class="ltr:ml-3 rtl:mr-3">{{ $t('todo_done') }}</div>
                                </div>
                                <div class="bg-primary-light dark:bg-[#060818] rounded-md py-0.5 px-2 font-semibold whitespace-nowrap">
                                    {{ doneCount }}
                                </div>
                            </button>
                            <button
                                type="button"
                                class="w-full flex justify-between items-center p-2 hover:bg-white-dark/10 rounded-md dark:hover:text-primary hover:text-primary dark:hover:bg-[#181F32] font-medium h-10"
                                :class="{ 'bg-gray-100 dark:text-primary text-primary dark:bg-[#181F32]': selectedTab === 'important' }"
                                @click="tabChanged('important')"
                            >
                                <div class="flex items-center">
                                    <icon-star class="shrink-0" />
                                    <div class="ltr:ml-3 rtl:mr-3">{{ $t('todo_important') }}</div>
                                </div>
                                <div class="bg-primary-light dark:bg-[#060818] rounded-md py-0.5 px-2 font-semibold whitespace-nowrap">
                                    {{ importantCount }}
                                </div>
                            </button>
                            <button
                                type="button"
                                class="w-full flex justify-between items-center p-2 hover:bg-white-dark/10 rounded-md dark:hover:text-primary hover:text-primary dark:hover:bg-[#181F32] font-medium h-10"
                                :class="{ 'bg-gray-100 dark:text-primary text-primary dark:bg-[#181F32]': selectedTab === 'trash' }"
                                @click="tabChanged('trash')"
                            >
                                <div class="flex items-center">
                                    <icon-trash-lines class="shrink-0" />
                                    <div class="ltr:ml-3 rtl:mr-3">{{ $t('todo_trash') }}</div>
                                </div>
                            </button>
                            <div class="h-px w-full border-b border-[#e0e6ed] dark:border-[#1b2e4b]"></div>
                            <div class="text-white-dark px-1 py-3">{{ $t('todo_tags') }}</div>
                            <button
                                type="button"
                                class="w-full flex items-center h-10 p-1 hover:bg-white-dark/10 rounded-md dark:hover:bg-[#181F32] font-medium text-secondary ltr:hover:pl-3 rtl:hover:pr-3 duration-300"
                                :class="{ 'ltr:pl-3 rtl:pr-3 bg-gray-100 dark:bg-[#181F32]': selectedTab === 'archivar' }"
                                @click="tabChanged('archivar')"
                            >
                                <icon-square-rotated class="fill-secondary shrink-0" />
                                <div class="ltr:ml-3 rtl:mr-3">{{ $t('todo_tag_archivar') }}</div>
                            </button>

                            <button
                                type="button"
                                class="w-full flex items-center h-10 p-1 hover:bg-white-dark/10 rounded-md dark:hover:bg-[#181F32] font-medium text-info ltr:hover:pl-3 rtl:hover:pr-3 duration-300"
                                :class="{ 'ltr:pl-3 rtl:pr-3 bg-gray-100 dark:bg-[#181F32]': selectedTab === 'documentos' }"
                                @click="tabChanged('documentos')"
                            >
                                <icon-square-rotated class="fill-info shrink-0" />
                                <div class="ltr:ml-3 rtl:mr-3">{{ $t('todo_tag_documentos') }}</div>
                            </button>

                            <button
                                type="button"
                                class="w-full flex items-center h-10 p-1 hover:bg-white-dark/10 rounded-md dark:hover:bg-[#181F32] font-medium text-warning ltr:hover:pl-3 rtl:hover:pr-3 duration-300"
                                :class="{ 'ltr:pl-3 rtl:pr-3 bg-gray-100 dark:bg-[#181F32]': selectedTab === 'seguimiento' }"
                                @click="tabChanged('seguimiento')"
                            >
                                <icon-square-rotated class="fill-warning shrink-0" />
                                <div class="ltr:ml-3 rtl:mr-3">{{ $t('todo_tag_seguimiento') }}</div>
                            </button>

                            <button
                                type="button"
                                class="w-full flex items-center h-10 p-1 hover:bg-white-dark/10 rounded-md dark:hover:bg-[#181F32] font-medium text-primary ltr:hover:pl-3 rtl:hover:pr-3 duration-300"
                                :class="{ 'ltr:pl-3 rtl:pr-3 bg-gray-100 dark:bg-[#181F32]': selectedTab === 'ircc' }"
                                @click="tabChanged('ircc')"
                            >
                                <icon-square-rotated class="fill-primary shrink-0" />
                                <div class="ltr:ml-3 rtl:mr-3">{{ $t('todo_tag_ircc') }}</div>
                            </button>

                            <button
                                type="button"
                                class="w-full flex items-center h-10 p-1 hover:bg-white-dark/10 rounded-md dark:hover:bg-[#181F32] font-medium text-success ltr:hover:pl-3 rtl:hover:pr-3 duration-300"
                                :class="{ 'ltr:pl-3 rtl:pr-3 bg-gray-100 dark:bg-[#181F32]': selectedTab === 'contabilidad' }"
                                @click="tabChanged('contabilidad')"
                            >
                                <icon-square-rotated class="fill-success shrink-0" />
                                <div class="ltr:ml-3 rtl:mr-3">{{ $t('todo_tag_contabilidad') }}</div>
                            </button>
                        </div>
                    </perfect-scrollbar>
                    <div class="ltr:left-0 rtl:right-0 absolute bottom-0 p-4 w-full">
                        <button class="btn btn-primary w-full" type="button" @click="addEditTask()">
                            <icon-plus class="ltr:mr-2 rtl:ml-2 shrink-0" />
                            {{ $t('todo_add_task') }}
                        </button>
                    </div>
                </div>
            </div>
            <div
                class="overlay bg-black/60 z-[5] w-full h-full rounded-md absolute hidden"
                :class="{ '!block xl:!hidden': isShowTaskMenu }"
                @click="isShowTaskMenu = !isShowTaskMenu"
            ></div>
            <!-- Main Content -->
            <div class="panel p-0 flex-1 overflow-auto h-full">
                <div class="flex flex-col h-full">
                    <div class="p-4 flex sm:flex-row flex-col w-full sm:items-center gap-4">
                        <div class="ltr:mr-3 rtl:ml-3 flex items-center">
                            <button type="button" class="xl:hidden hover:text-primary block ltr:mr-3 rtl:ml-3" @click="isShowTaskMenu = !isShowTaskMenu">
                                <icon-menu />
                            </button>
                            <div class="relative group flex-1">
                                <input
                                    type="text"
                                    class="form-input peer ltr:!pr-10 rtl:!pl-10"
                                    :placeholder="$t('todo_search_placeholder')"
                                    v-model="searchTask"
                                    @keyup="onSearchKeyup"
                                />
                                <div class="absolute ltr:right-[11px] rtl:left-[11px] top-1/2 -translate-y-1/2 peer-focus:text-primary">
                                    <icon-search />
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <select v-model="filterAssigneeId" class="form-select text-sm py-1.5 w-auto" @change="refreshTodos()">
                                <option :value="null">{{ $t('todo_filter_all_assignees') }}</option>
                                <option v-for="a in todoStore.assignees" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                            <select v-model="filterCaseId" class="form-select text-sm py-1.5 w-auto" @change="refreshTodos()">
                                <option :value="null">{{ $t('todo_filter_all_cases') }}</option>
                                <option v-for="c in todoStore.cases" :key="c.id" :value="c.id">#{{ c.case_number }}</option>
                            </select>
                        </div>
                        <div class="flex items-center justify-center sm:justify-end sm:flex-auto flex-1">
                            <p class="ltr:mr-3 rtl:ml-3">
                                {{ pagerText }}
                            </p>
                            <button
                                type="button"
                                :disabled="todoStore.currentPage <= 1"
                                class="bg-[#f4f4f4] rounded-md p-1 enabled:hover:bg-primary-light dark:bg-white-dark/20 enabled:dark:hover:bg-white-dark/30 ltr:mr-3 rtl:ml-3 disabled:opacity-60 disabled:cursor-not-allowed"
                                @click="prevPage()"
                            >
                                <icon-caret-down class="w-5 h-5 rtl:-rotate-90 rotate-90" />
                            </button>
                            <button
                                type="button"
                                :disabled="todoStore.currentPage >= totalPages"
                                class="bg-[#f4f4f4] rounded-md p-1 enabled:hover:bg-primary-light dark:bg-white-dark/20 enabled:dark:hover:bg-white-dark/30 disabled:opacity-60 disabled:cursor-not-allowed"
                                @click="nextPage()"
                            >
                                <icon-caret-down class="w-5 h-5 rtl:rotate-90 -rotate-90" />
                            </button>
                        </div>
                    </div>
                    <div class="h-px w-full border-b border-[#e0e6ed] dark:border-[#1b2e4b]"></div>

                    <!-- Loading -->
                    <template v-if="todoStore.isLoading">
                        <div class="flex justify-center items-center sm:min-h-[300px] min-h-[400px] font-semibold text-lg h-full">
                            <span class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10 inline-block align-middle"></span>
                        </div>
                    </template>

                    <!-- Tasks Table -->
                    <template v-else-if="todoStore.todos.length">
                        <div class="table-responsive grow overflow-y-auto sm:min-h-[300px] min-h-[400px]">
                            <table class="table-hover">
                                <tbody>
                                    <template v-for="task in todoStore.todos" :key="task.id">
                                        <tr class="group cursor-pointer" :class="{ 'bg-white-light/30 dark:bg-[#1a2941]': task.status === 'complete' }">
                                            <td class="w-1">
                                                <input
                                                    type="checkbox"
                                                    :id="`chk-${task.id}`"
                                                    class="form-checkbox"
                                                    :checked="task.status === 'complete'"
                                                    @click.stop="taskComplete(task)"
                                                    :disabled="selectedTab === 'trash'"
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
                                                        class="text-white-dark overflow-hidden min-w-[300px] line-clamp-1"
                                                        :class="{ 'line-through': task.status === 'complete' }"
                                                        v-html="stripHtml(task.description)"
                                                    ></div>
                                                </div>
                                            </td>
                                            <td class="w-1">
                                                <div class="flex items-center ltr:justify-end rtl:justify-start space-x-2 rtl:space-x-reverse">
                                                    <template v-if="task.priority">
                                                        <div class="dropdown">
                                                            <Popper
                                                                :placement="store.rtlClass === 'rtl' ? 'bottom-start' : 'bottom-end'"
                                                                offsetDistance="0"
                                                                class="align-middle"
                                                                @open:popper="isPriorityMenu = task.id"
                                                                @close:popper="isPriorityMenu = null"
                                                            >
                                                                <a
                                                                    href="javascript:;"
                                                                    class="badge rounded-full capitalize hover:top-0 hover:text-white"
                                                                    :class="{
                                                                        'badge-outline-primary hover:bg-primary': task.priority === 'medium',
                                                                        'badge-outline-warning hover:bg-warning': task.priority === 'low',
                                                                        'badge-outline-danger hover:bg-danger': task.priority === 'high',
                                                                        'text-white bg-primary': task.priority === 'medium' && isPriorityMenu === task.id,
                                                                        'text-white bg-warning': task.priority === 'low' && isPriorityMenu === task.id,
                                                                        'text-white bg-danger': task.priority === 'high' && isPriorityMenu === task.id,
                                                                    }"
                                                                >
                                                                    {{ task.priority }}
                                                                </a>
                                                                <template #content="{ close }">
                                                                    <ul @click="close()" class="text-sm text-medium">
                                                                        <li>
                                                                            <button
                                                                                type="button"
                                                                                class="w-full text-danger ltr:text-left rtl:text-right"
                                                                                @click="setPriority(task, 'high')"
                                                                            >
                                                                                {{ $t('todo_priority_high') }}
                                                                            </button>
                                                                        </li>
                                                                        <li>
                                                                            <button
                                                                                type="button"
                                                                                class="w-full text-primary ltr:text-left rtl:text-right"
                                                                                @click="setPriority(task, 'medium')"
                                                                            >
                                                                                {{ $t('todo_priority_medium') }}
                                                                            </button>
                                                                        </li>
                                                                        <li>
                                                                            <button
                                                                                type="button"
                                                                                class="w-full text-warning ltr:text-left rtl:text-right"
                                                                                @click="setPriority(task, 'low')"
                                                                            >
                                                                                {{ $t('todo_priority_low') }}
                                                                            </button>
                                                                        </li>
                                                                    </ul>
                                                                </template>
                                                            </Popper>
                                                        </div>
                                                    </template>
                                                    <template v-if="task.tag">
                                                        <div class="dropdown">
                                                            <Popper
                                                                :placement="store.rtlClass === 'rtl' ? 'bottom-start' : 'bottom-end'"
                                                                offsetDistance="0"
                                                                class="align-middle"
                                                                @open:popper="isTagMenu = task.id"
                                                                @close:popper="isTagMenu = null"
                                                            >
                                                                <a
                                                                    href="javascript:;"
                                                                    class="badge rounded-full capitalize hover:top-0 hover:text-white"
                                                                    :class="tagBadgeClass(task.tag, isTagMenu === task.id)"
                                                                >
                                                                    {{ task.tag ? $t('todo_tag_' + task.tag) : '' }}
                                                                </a>
                                                                <template #content="{ close }">
                                                                    <ul @click="close()" class="text-sm text-medium">
                                                                        <li>
                                                                            <button type="button" class="w-full text-secondary" @click="setTag(task, 'archivar')">
                                                                                {{ $t('todo_tag_archivar') }}
                                                                            </button>
                                                                        </li>
                                                                        <li>
                                                                            <button type="button" class="w-full text-info" @click="setTag(task, 'documentos')">
                                                                                {{ $t('todo_tag_documentos') }}
                                                                            </button>
                                                                        </li>
                                                                        <li>
                                                                            <button type="button" class="w-full text-warning" @click="setTag(task, 'seguimiento')">
                                                                                {{ $t('todo_tag_seguimiento') }}
                                                                            </button>
                                                                        </li>
                                                                        <li>
                                                                            <button type="button" class="w-full text-primary" @click="setTag(task, 'ircc')">
                                                                                {{ $t('todo_tag_ircc') }}
                                                                            </button>
                                                                        </li>
                                                                        <li>
                                                                            <button type="button" class="w-full text-success" @click="setTag(task, 'contabilidad')">
                                                                                {{ $t('todo_tag_contabilidad') }}
                                                                            </button>
                                                                        </li>
                                                                        <li>
                                                                            <button type="button" class="w-full" @click="setTag(task, '')">{{ $t('todo_tag_none') }}</button>
                                                                        </li>
                                                                    </ul>
                                                                </template>
                                                            </Popper>
                                                        </div>
                                                    </template>
                                                </div>
                                            </td>
                                            <td class="w-1">
                                                <p
                                                    class="whitespace-nowrap font-medium"
                                                    :class="[task.due_date ? dueDateClass(task) : 'text-white-dark', { 'line-through': task.status === 'complete' }]"
                                                >
                                                    {{ formatDate(task.due_date || task.created_at) }}
                                                </p>
                                            </td>
                                            <td class="w-1">
                                                <router-link
                                                    v-if="task.case"
                                                    :to="`/cases/${task.case.id}`"
                                                    class="badge badge-outline-info text-xs whitespace-nowrap"
                                                    @click.stop
                                                >
                                                    #{{ task.case.case_number }}
                                                </router-link>
                                            </td>
                                            <td class="w-1">
                                                <div class="flex items-center justify-between w-max">
                                                    <div class="ltr:mr-2.5 rtl:ml-2.5 flex-shrink-0">
                                                        <UserAvatar v-if="task.assigned_to" :name="task.assigned_to.name" :avatar-url="task.assigned_to.avatar_url" size="sm" />
                                                        <div
                                                            v-else
                                                            class="border border-gray-300 dark:border-gray-800 rounded-full grid place-content-center h-8 w-8"
                                                        >
                                                            <icon-user class="w-4.5 h-4.5" />
                                                        </div>
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
                                                                    <template v-if="selectedTab !== 'trash'">
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
                                                                                <span>
                                                                                    {{ task.status === 'important' ? $t('todo_not_important') : $t('todo_important') }}
                                                                                </span>
                                                                            </a>
                                                                        </li>
                                                                    </template>
                                                                    <template v-if="selectedTab === 'trash'">
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
                        <div class="flex justify-center items-center sm:min-h-[300px] min-h-[400px] font-semibold text-lg h-full">{{ $t('todo_no_data') }}</div>
                    </template>
                </div>
            </div>

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
                                                <label for="title">{{ $t('todo_title') }}</label>
                                                <input id="title" type="text" :placeholder="$t('todo_title_placeholder')" class="form-input" v-model="params.title" />
                                            </div>
                                            <div class="mb-5">
                                                <label for="assignee">{{ $t('todo_assignee') }}</label>
                                                <select id="assignee" class="form-select" v-model="params.assigned_to_id">
                                                    <option :value="null">{{ $t('todo_assignee_placeholder') }}</option>
                                                    <option v-for="a in todoStore.assignees" :key="a.id" :value="a.id">{{ a.name }}</option>
                                                </select>
                                            </div>
                                            <div class="mb-5">
                                                <label for="taskCase">{{ $t('todo_case') }}</label>
                                                <select id="taskCase" class="form-select" v-model="params.case_id">
                                                    <option :value="null">{{ $t('todo_case_placeholder') }}</option>
                                                    <option v-for="c in todoStore.cases" :key="c.id" :value="c.id">
                                                        #{{ c.case_number }} <template v-if="c.client_name">&mdash; {{ c.client_name }}</template>
                                                    </option>
                                                </select>
                                            </div>
                                            <div class="mb-5 flex justify-between gap-4">
                                                <div class="flex-1">
                                                    <label for="tag">{{ $t('todo_tag_label') }}</label>
                                                    <select id="tag" class="form-select" v-model="params.tag">
                                                        <option value="">{{ $t('todo_tag_select') }}</option>
                                                        <option value="archivar">{{ $t('todo_tag_archivar') }}</option>
                                                        <option value="documentos">{{ $t('todo_tag_documentos') }}</option>
                                                        <option value="seguimiento">{{ $t('todo_tag_seguimiento') }}</option>
                                                        <option value="ircc">{{ $t('todo_tag_ircc') }}</option>
                                                        <option value="contabilidad">{{ $t('todo_tag_contabilidad') }}</option>
                                                    </select>
                                                </div>
                                                <div class="flex-1">
                                                    <label for="priority">{{ $t('todo_priority_label') }}</label>
                                                    <select id="priority" class="form-select" v-model="params.priority">
                                                        <option value="">{{ $t('todo_priority_select') }}</option>
                                                        <option value="low">{{ $t('todo_priority_low') }}</option>
                                                        <option value="medium">{{ $t('todo_priority_medium') }}</option>
                                                        <option value="high">{{ $t('todo_priority_high') }}</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="mb-5">
                                                <label for="dueDate">{{ $t('todo_due_date') }}</label>
                                                <input id="dueDate" type="date" class="form-input" v-model="params.due_date" />
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
                                    <div
                                        class="flex items-center flex-wrap gap-2 text-lg font-medium bg-[#fbfbfb] dark:bg-[#121c2c] ltr:pl-5 rtl:pr-5 py-3 ltr:pr-[50px] rtl:pl-[50px]"
                                    >
                                        <div>{{ selectedTask.title }}</div>
                                        <div
                                            v-show="selectedTask.priority"
                                            class="badge rounded-3xl capitalize"
                                            :class="{
                                                'badge-outline-primary': selectedTask.priority === 'medium',
                                                'badge-outline-warning ': selectedTask.priority === 'low',
                                                'badge-outline-danger ': selectedTask.priority === 'high',
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

                                        <div v-if="selectedTask.case" class="mt-2 flex items-center gap-2 text-sm text-white-dark">
                                            <span class="font-medium">{{ $t('todo_case') }}:</span>
                                            <router-link :to="`/cases/${selectedTask.case.id}`" class="text-info hover:underline">
                                                #{{ selectedTask.case.case_number }}
                                            </router-link>
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
    import { useMeta } from '@/composables/use-meta';
    import type { Todo, CreateTodoData, UpdateTodoData } from '@/types/todo';

    import IconClipboardText from '@/components/icon/icon-clipboard-text.vue';
    import IconListCheck from '@/components/icon/icon-list-check.vue';
    import IconThumbUp from '@/components/icon/icon-thumb-up.vue';
    import IconStar from '@/components/icon/icon-star.vue';
    import IconTrashLines from '@/components/icon/icon-trash-lines.vue';
    import IconSquareRotated from '@/components/icon/icon-square-rotated.vue';
    import IconPlus from '@/components/icon/icon-plus.vue';
    import IconMenu from '@/components/icon/icon-menu.vue';
    import IconSearch from '@/components/icon/icon-search.vue';
    import IconCaretDown from '@/components/icon/icon-caret-down.vue';
    import IconUser from '@/components/icon/icon-user.vue';
    import IconHorizontalDots from '@/components/icon/icon-horizontal-dots.vue';
    import IconPencilPaper from '@/components/icon/icon-pencil-paper.vue';
    import IconCopy from '@/components/icon/icon-copy.vue';
    import IconRestore from '@/components/icon/icon-restore.vue';
    import IconX from '@/components/icon/icon-x.vue';
    import UserAvatar from '@/components/UserAvatar.vue';

    useMeta({ title: 'Todolist' });
    const { t } = useI18n();
    const store = useAppStore();
    const todoStore = useTodoStore();

    const defaultParams = {
        id: null as number | null,
        title: '',
        description: '',
        assigned_to_id: null as number | null,
        case_id: null as number | null,
        tag: '',
        priority: 'low',
        due_date: '',
    };

    const selectedTab = ref('');
    const isShowTaskMenu = ref(false);
    const addTaskModal = ref(false);
    const viewTaskModal = ref(false);
    const isSaving = ref(false);

    const params = ref({ ...defaultParams });
    const searchTask = ref('');
    const selectedTask = ref<Partial<Todo>>({});
    const isPriorityMenu = ref<number | null>(null);
    const isTagMenu = ref<number | null>(null);
    const filterAssigneeId = ref<number | null>(null);
    const filterCaseId = ref<number | null>(null);

    let searchDebounce: ReturnType<typeof setTimeout> | null = null;

    const editorOptions = ref({
        modules: {
            toolbar: [[{ header: [1, 2, false] }], ['bold', 'italic', 'underline', 'link'], [{ list: 'ordered' }, { list: 'bullet' }], ['clean']],
        },
        placeholder: '',
    });
    const quillEditorObj = ref<any>(null);

    // Computed
    const totalPages = computed(() => Math.max(1, Math.ceil(todoStore.total / todoStore.perPage)));

    const pagerText = computed(() => {
        if (todoStore.total === 0) return '0 of 0';
        const start = (todoStore.currentPage - 1) * todoStore.perPage + 1;
        const end = Math.min(start + todoStore.todos.length - 1, todoStore.total);
        return `${start}-${end} of ${todoStore.total}`;
    });

    const inboxCount = computed(() => todoStore.todos.filter((d) => d.status !== 'trash').length);
    const doneCount = computed(() => todoStore.todos.filter((d) => d.status === 'complete').length);
    const importantCount = computed(() => todoStore.todos.filter((d) => d.status === 'important').length);

    // Lifecycle
    onMounted(async () => {
        await Promise.all([
            todoStore.fetchTodos(),
            todoStore.fetchAssignees(),
            todoStore.fetchCases(),
        ]);
    });

    // Methods
    const quillEditorReady = (quill: any) => {
        quillEditorObj.value = quill;
    };

    function buildFilterParams(): Record<string, any> {
        const p: Record<string, any> = {};

        if (selectedTab.value === 'complete' || selectedTab.value === 'important' || selectedTab.value === 'trash') {
            p.status = selectedTab.value;
        } else if (['archivar', 'documentos', 'seguimiento', 'ircc', 'contabilidad'].includes(selectedTab.value)) {
            p.tag = selectedTab.value;
        }
        // inbox (selectedTab === '') sends no status filter

        if (searchTask.value) p.search = searchTask.value;
        if (filterAssigneeId.value) p.assigned_to_id = filterAssigneeId.value;
        if (filterCaseId.value) p.case_id = filterCaseId.value;

        return p;
    }

    async function refreshTodos() {
        await todoStore.fetchTodos(buildFilterParams());
    }

    async function tabChanged(type: string) {
        selectedTab.value = type;
        todoStore.currentPage = 1;
        await refreshTodos();
        isShowTaskMenu.value = false;
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

    async function setPriority(task: Todo, priority: string) {
        await todoStore.updateTodo(task.id, { priority: priority as Todo['priority'] });
        await refreshTodos();
    }

    async function setTag(task: Todo, tag: string) {
        await todoStore.updateTodo(task.id, { tag });
        await refreshTodos();
    }

    async function cloneTask(task: Todo) {
        try {
            await todoStore.createTodo({
                title: task.title,
                description: task.description ?? undefined,
                assigned_to_id: task.assigned_to?.id ?? null,
                case_id: task.case?.id ?? null,
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
        setTimeout(() => {
            viewTaskModal.value = true;
        });
    }

    function addEditTask(task?: Todo) {
        isShowTaskMenu.value = false;
        params.value = { ...defaultParams };

        if (task) {
            params.value = {
                id: task.id,
                title: task.title,
                description: task.description || '',
                assigned_to_id: task.assigned_to?.id ?? null,
                case_id: task.case?.id ?? null,
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
                case_id: params.value.case_id || null,
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

    function getInitials(name: string): string {
        if (!name) return '';
        const parts = name.split(' ');
        if (parts.length >= 2) {
            return (parts[0][0] + parts[1][0]).toUpperCase();
        }
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

    const TAG_BADGE_OPEN: Record<string, string> = {
        archivar: 'text-white bg-secondary',
        documentos: 'text-white bg-info',
        seguimiento: 'text-white bg-warning',
        ircc: 'text-white bg-primary',
        contabilidad: 'text-white bg-success',
    };

    const TAG_BADGE_CLOSED: Record<string, string> = {
        archivar: 'badge-outline-secondary hover:bg-secondary',
        documentos: 'badge-outline-info hover:bg-info',
        seguimiento: 'badge-outline-warning hover:bg-warning',
        ircc: 'badge-outline-primary hover:bg-primary',
        contabilidad: 'badge-outline-success hover:bg-success',
    };

    const TAG_BADGE_OUTLINE: Record<string, string> = {
        archivar: 'badge-outline-secondary',
        documentos: 'badge-outline-info',
        seguimiento: 'badge-outline-warning',
        ircc: 'badge-outline-primary',
        contabilidad: 'badge-outline-success',
    };

    function tagBadgeClass(tag: string | undefined | null, isOpen: boolean): string {
        if (!tag) return 'badge-outline-info hover:bg-info';
        return isOpen ? (TAG_BADGE_OPEN[tag] ?? 'text-white bg-info') : (TAG_BADGE_CLOSED[tag] ?? 'badge-outline-info hover:bg-info');
    }

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
        toast.fire({
            icon: type,
            title: msg,
            padding: '10px 20px',
        });
    };
</script>
