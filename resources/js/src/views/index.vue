<template>
    <div>
        <ul class="flex space-x-2 rtl:space-x-reverse">
            <li>
                <span class="text-primary">{{ t('dashboard.title') }}</span>
            </li>
        </ul>

        <div class="pt-5 space-y-6">
            <!-- Section 1: Quick Access Cards -->
            <div class="grid sm:grid-cols-2 gap-6">
                <!-- Cases Card -->
                <div class="panel">
                    <div class="flex items-center gap-4 mb-5">
                        <div class="w-12 h-12 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                            <icon-folder class="w-6 h-6 text-primary" />
                        </div>
                        <div>
                            <h5 class="font-semibold text-lg dark:text-white-light">{{ t('dashboard.cases_shortcut') }}</h5>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <router-link to="/cases" class="btn btn-outline-primary flex-1">
                            <icon-list-check class="w-4 h-4 ltr:mr-1.5 rtl:ml-1.5" />
                            {{ t('dashboard.view_list') }}
                        </router-link>
                        <router-link to="/cases/wizard" class="btn btn-primary flex-1">
                            <icon-plus class="w-4 h-4 ltr:mr-1.5 rtl:ml-1.5" />
                            {{ t('dashboard.create_new') }}
                        </router-link>
                    </div>
                </div>

                <!-- Clients Card -->
                <div class="panel">
                    <div class="flex items-center gap-4 mb-5">
                        <div class="w-12 h-12 rounded-lg bg-success/10 flex items-center justify-center shrink-0">
                            <icon-users class="w-6 h-6 text-success" />
                        </div>
                        <div>
                            <h5 class="font-semibold text-lg dark:text-white-light">{{ t('dashboard.clients_shortcut') }}</h5>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <router-link to="/clients" class="btn btn-outline-success flex-1">
                            <icon-list-check class="w-4 h-4 ltr:mr-1.5 rtl:ml-1.5" />
                            {{ t('dashboard.view_list') }}
                        </router-link>
                        <router-link to="/clients/create" class="btn btn-success flex-1">
                            <icon-plus class="w-4 h-4 ltr:mr-1.5 rtl:ml-1.5" />
                            {{ t('dashboard.create_new') }}
                        </router-link>
                    </div>
                </div>
            </div>

            <!-- Section 2+3: Two-column layout -->
            <div class="grid xl:grid-cols-3 gap-6">
                <!-- Left column (2/3) -->
                <div class="xl:col-span-2 space-y-6">
                    <!-- Stat Circles -->
                    <div class="grid sm:grid-cols-3 gap-4">
                        <StatCircle
                            :value="dashboard.metrics.active_cases_assigned_to_me"
                            :label="t('dashboard.my_active_cases')"
                            color="primary"
                            :loading="dashboard.isLoading && !dashboard.data"
                        />
                        <StatCircle
                            :value="dashboard.metrics.today_events"
                            :label="t('dashboard.events_today')"
                            color="warning"
                            :loading="dashboard.isLoading && !dashboard.data"
                        />
                        <StatCircle
                            :value="dashboard.metrics.pending_todos"
                            :label="t('dashboard.pending_tasks')"
                            color="info"
                            :loading="dashboard.isLoading && !dashboard.data"
                        />
                    </div>

                    <!-- Assigned Tasks -->
                    <div class="panel">
                        <div class="flex items-center justify-between mb-5">
                            <h5 class="font-semibold text-lg dark:text-white-light">
                                {{ t('dashboard.assigned_tasks') }}
                                <span v-if="dashboard.assignedTasks.length" class="badge badge-outline-danger ml-2">
                                    {{ dashboard.assignedTasks.length }}
                                </span>
                            </h5>
                        </div>

                        <div v-if="dashboard.isLoading && !dashboard.data" class="flex items-center justify-center py-10">
                            <span class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10 inline-block align-middle"></span>
                        </div>

                        <div v-else-if="dashboard.assignedTasks.length === 0" class="flex flex-col items-center justify-center py-10 text-gray-400 dark:text-gray-500">
                            <icon-list-check class="w-12 h-12 mb-3 opacity-50" />
                            <p>{{ t('dashboard.no_tasks') }}</p>
                        </div>

                        <div v-else class="table-responsive">
                            <table class="table-hover">
                                <thead>
                                    <tr>
                                        <th>{{ t('dashboard.task_id') }}</th>
                                        <th>{{ t('dashboard.task_title') }}</th>
                                        <th>{{ t('dashboard.task_tag') }}</th>
                                        <th>{{ t('dashboard.task_deadline') }}</th>
                                        <th>{{ t('dashboard.task_case') }}</th>
                                        <th>{{ t('dashboard.task_priority') }}</th>
                                        <th>{{ t('dashboard.task_status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <TaskRow v-for="task in dashboard.assignedTasks" :key="task.id" :task="task" />
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Cases -->
                    <div class="panel">
                        <div class="flex items-center justify-between mb-5">
                            <h5 class="font-semibold text-lg dark:text-white-light">{{ t('dashboard.recent_cases') }}</h5>
                        </div>

                        <div v-if="dashboard.isLoading && !dashboard.data" class="flex items-center justify-center py-10">
                            <span class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10 inline-block align-middle"></span>
                        </div>

                        <div v-else-if="dashboard.recentCases.length === 0" class="flex flex-col items-center justify-center py-10 text-gray-400 dark:text-gray-500">
                            <icon-folder class="w-12 h-12 mb-3 opacity-50" />
                            <p>{{ t('dashboard.no_recent_cases') }}</p>
                        </div>

                        <div v-else class="grid sm:grid-cols-2 gap-4">
                            <RecentCaseItem
                                v-for="c in dashboard.recentCases"
                                :key="c.id"
                                :dashboard-case="c"
                            />
                        </div>
                    </div>
                </div>

                <!-- Right sidebar (1/3) -->
                <div class="space-y-6">
                    <!-- Upcoming Events -->
                    <div class="panel">
                        <div class="flex items-center justify-between mb-5">
                            <h5 class="font-semibold text-lg dark:text-white-light">{{ t('dashboard.upcoming_events') }}</h5>
                        </div>

                        <div v-if="dashboard.isLoading && !dashboard.data" class="flex items-center justify-center py-10">
                            <span class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10 inline-block align-middle"></span>
                        </div>

                        <div v-else-if="dashboard.upcomingEvents.length === 0" class="flex flex-col items-center justify-center py-10 text-gray-400 dark:text-gray-500">
                            <icon-calendar class="w-12 h-12 mb-3 opacity-50" />
                            <p>{{ t('dashboard.no_events') }}</p>
                        </div>

                        <div v-else class="space-y-4">
                            <EventSidebarItem
                                v-for="event in dashboard.upcomingEvents"
                                :key="event.id"
                                :event="event"
                            />
                        </div>

                        <router-link to="/apps/calendar" class="btn btn-outline-primary w-full mt-5 text-center">
                            <icon-calendar class="w-4 h-4 ltr:mr-1.5 rtl:ml-1.5" />
                            {{ t('dashboard.add_event') }}
                        </router-link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMeta } from '@/composables/use-meta';
import { useDashboardStore } from '@/stores/dashboard';
import { useAppStore } from '@/stores/index';

import IconFolder from '@/components/icon/icon-folder.vue';
import IconUsers from '@/components/icon/icon-users.vue';
import IconPlus from '@/components/icon/icon-plus.vue';
import IconListCheck from '@/components/icon/icon-list-check.vue';
import IconCalendar from '@/components/icon/icon-calendar.vue';

import StatCircle from '@/views/dashboard/components/StatCircle.vue';
import TaskRow from '@/views/dashboard/components/TaskRow.vue';
import EventSidebarItem from '@/views/dashboard/components/EventSidebarItem.vue';
import RecentCaseItem from '@/views/dashboard/components/RecentCaseItem.vue';

const { t } = useI18n();
useMeta({ title: 'Dashboard' });

const store = useAppStore();
const dashboard = useDashboardStore();

onMounted(() => {
    dashboard.fetchDashboard();
});
</script>

