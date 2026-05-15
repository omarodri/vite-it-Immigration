<script lang="ts" setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDashboardStore } from '@/stores/dashboard';
import UrgencyBadge from '@/components/alerts/UrgencyBadge.vue';
import IconClock from '@/components/icon/icon-clock.vue';

const { t } = useI18n();
const dashboard = useDashboardStore();

const milestones = computed(() => dashboard.upcomingMilestones);
</script>

<template>
    <div class="panel">
        <div class="flex items-center justify-between mb-5">
            <h5 class="font-semibold text-lg dark:text-white-light">
                {{ t('dashboard.upcoming_milestones') }}
            </h5>
        </div>

        <!-- Skeleton: carga inicial -->
        <div v-if="dashboard.isLoading && !dashboard.data" class="flex items-center justify-center py-10">
            <span class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10 inline-block align-middle"></span>
        </div>

        <!-- Empty state -->
        <div
            v-else-if="!milestones.length"
            class="flex flex-col items-center justify-center py-6 text-gray-400 dark:text-gray-500"
        >
            <icon-clock class="w-10 h-10 mb-2 opacity-50" />
            <p class="text-sm">{{ t('dashboard.no_upcoming_milestones') }}</p>
        </div>

        <!-- Lista de hitos -->
        <div v-else class="space-y-3">
            <router-link
                v-for="m in milestones"
                :key="m.id"
                :to="`/cases/${m.case_id}`"
                class="flex items-start gap-2 p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
            >
                <UrgencyBadge
                    :urgency-bucket="m.urgency_bucket"
                    :days-diff="m.days_diff"
                    class="shrink-0 mt-0.5"
                />
                <div class="min-w-0">
                    <p class="text-sm font-medium truncate">
                        {{ m.case_number ?? '—' }}<span v-if="m.client_name" class="font-normal text-gray-500 dark:text-gray-400"> — {{ m.client_name }}</span>
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ m.label }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ m.due_date }}</p>
                </div>
            </router-link>
        </div>

        <!-- Footer -->
        <router-link
            to="/expiration-alerts"
            class="btn btn-outline-warning w-full mt-5 text-center text-sm"
        >
            {{ t('dashboard.view_all_milestones') }}
        </router-link>
    </div>
</template>
