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
                                <span v-if="dashboard.assignedTasks.total > 0" class="badge badge-outline-danger ml-2">
                                    {{ dashboard.assignedTasks.total }}
                                </span>
                            </h5>
                        </div>

                        <!-- Skeleton: carga inicial o cambio de página -->
                        <div v-if="dashboard.assignedTasks.isLoading || (dashboard.isLoading && !dashboard.data)" class="space-y-2">
                            <div v-for="n in 5" :key="n" class="h-10 rounded bg-gray-100 dark:bg-gray-800 animate-pulse" />
                        </div>

                        <!-- Estado vacío -->
                        <div v-else-if="dashboard.assignedTasks.items.length === 0" class="flex flex-col items-center justify-center py-10 text-gray-400 dark:text-gray-500">
                            <icon-list-check class="w-12 h-12 mb-3 opacity-50" />
                            <p>{{ t('dashboard.no_tasks') }}</p>
                        </div>

                        <!-- Tabla de tareas -->
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
                                    <TaskRow
                                        v-for="task in dashboard.assignedTasks.items"
                                        :key="task.id"
                                        :task="task"
                                        :urgency-badge="urgencyBadge(task.due_date)"
                                    />
                                </tbody>
                            </table>
                        </div>

                        <!-- Footer paginación -->
                        <div
                            v-if="!dashboard.assignedTasks.isLoading && dashboard.assignedTasks.total > dashboard.assignedTasks.perPage"
                            class="flex items-center justify-between pt-4 mt-4 border-t border-gray-100 dark:border-gray-700"
                        >
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ t('dashboard.pagination_summary', {
                                    from: ((dashboard.assignedTasks.page - 1) * dashboard.assignedTasks.perPage) + 1,
                                    to: Math.min(dashboard.assignedTasks.page * dashboard.assignedTasks.perPage, dashboard.assignedTasks.total),
                                    total: dashboard.assignedTasks.total
                                }) }}
                            </p>
                            <div class="flex items-center gap-2">
                                <button
                                    class="btn btn-sm btn-outline-primary"
                                    :disabled="dashboard.assignedTasks.page <= 1 || dashboard.assignedTasks.isLoading"
                                    @click="goToPage(dashboard.assignedTasks.page - 1)"
                                >
                                    {{ t('common.prev') }}
                                </button>
                                <span class="text-xs font-medium text-gray-600 dark:text-gray-400">
                                    {{ dashboard.assignedTasks.page }} / {{ dashboard.assignedTasks.lastPage }}
                                </span>
                                <button
                                    class="btn btn-sm btn-outline-primary"
                                    :disabled="dashboard.assignedTasks.page >= dashboard.assignedTasks.lastPage || dashboard.assignedTasks.isLoading"
                                    @click="goToPage(dashboard.assignedTasks.page + 1)"
                                >
                                    {{ t('common.next') }}
                                </button>
                            </div>
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

                    <!-- Upcoming Legal Milestones -->
                    <UpcomingMilestonesWidget />

                    <!-- Expiring Documents -->
                    <div class="panel">
                        <div class="flex items-center justify-between mb-5">
                            <h5 class="font-semibold text-lg dark:text-white-light">{{ t('dashboard.expiring_documents') }}</h5>
                        </div>

                        <div v-if="dashboard.isLoading && !dashboard.data" class="flex items-center justify-center py-10">
                            <span class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10 inline-block align-middle"></span>
                        </div>

                        <div v-else-if="!dashboard.expiringDocuments?.length" class="flex flex-col items-center justify-center py-6 text-gray-400 dark:text-gray-500">
                            <icon-clock class="w-10 h-10 mb-2 opacity-50" />
                            <p class="text-sm">{{ t('dashboard.no_expiring_documents') }}</p>
                        </div>

                        <div v-else class="space-y-3">
                            <router-link
                                v-for="doc in dashboard.expiringDocuments"
                                :key="doc.id"
                                :to="`/clients/${doc.client_id || doc.entity_id}`"
                                class="flex items-start gap-2 p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                            >
                                <ExpirationAlertBadge
                                    :alert-status="doc.alert_status || 'critical'"
                                    :days-remaining="doc.days_remaining"
                                    class="shrink-0 mt-0.5"
                                />
                                <div class="min-w-0">
                                    <p class="text-sm font-medium truncate">{{ doc.entity_name }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ doc.document_type === 'other' && doc.display_name ? doc.display_name : t(`documents.type_${doc.document_type}`) }}</p>
                                    <p class="text-xs text-gray-400">{{ doc.expiry_date }}</p>
                                </div>
                            </router-link>
                        </div>

                        <router-link to="/expiration-alerts" class="btn btn-outline-warning w-full mt-5 text-center text-sm">
                            {{ t('dashboard.view_all_alerts') }}
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
import IconClock from '@/components/icon/icon-clock.vue';
import ExpirationAlertBadge from '@/components/ExpirationAlertBadge.vue';

import StatCircle from '@/views/dashboard/components/StatCircle.vue';
import TaskRow from '@/views/dashboard/components/TaskRow.vue';
import EventSidebarItem from '@/views/dashboard/components/EventSidebarItem.vue';
import RecentCaseItem from '@/views/dashboard/components/RecentCaseItem.vue';
import UpcomingMilestonesWidget from '@/views/dashboard/components/UpcomingMilestonesWidget.vue';

const { t } = useI18n();
useMeta({ title: 'Dashboard' });

const store = useAppStore();
const dashboard = useDashboardStore();

function urgencyBadge(dateStr: string | null): { cls: string; key: string } | null {
    if (!dateStr) return null;
    const d = new Date(dateStr + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const diffDays = Math.floor((d.getTime() - today.getTime()) / 86400000);
    if (diffDays < 0)  return { cls: 'badge badge-outline-danger',  key: 'dashboard.urgency_overdue' };
    if (diffDays <= 1) return { cls: 'badge badge-outline-warning', key: 'dashboard.urgency_due_soon' };
    return null;
}

function goToPage(page: number) {
    if (page < 1 || page > dashboard.assignedTasks.lastPage || dashboard.assignedTasks.isLoading) return;
    dashboard.fetchAssignedTasksPage(page);
}

onMounted(() => {
    dashboard.fetchDashboard(true);
});
</script>

