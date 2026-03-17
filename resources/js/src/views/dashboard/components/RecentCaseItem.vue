<script lang="ts" setup>
import { useI18n } from 'vue-i18n';
import type { DashboardCase } from '@/types/dashboard';

defineProps<{
    dashboardCase: DashboardCase;
}>();

const { t } = useI18n();

const priorityDotColors: Record<string, string> = {
    urgent: 'bg-danger',
    high: 'bg-warning',
    medium: 'bg-info',
    low: 'bg-secondary',
};

function formatDeadline(dateStr: string | null): string {
    if (!dateStr) return t('dashboard.case_no_deadline');
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>

<template>
    <div class="panel border border-gray-200 dark:border-gray-700 p-4 space-y-2">
        <!-- Header: case number + case type badge -->
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
                <span
                    class="w-2 h-2 rounded-full shrink-0"
                    :class="priorityDotColors[dashboardCase.priority] || 'bg-gray-400'"
                ></span>
                <router-link
                    :to="`/cases/${dashboardCase.id}`"
                    class="text-primary hover:underline font-semibold text-sm truncate"
                >
                    #{{ dashboardCase.case_number }}
                </router-link>
            </div>
            <span v-if="dashboardCase.case_type" class="badge badge-outline-primary text-xs shrink-0">
                {{ dashboardCase.case_type.code }}
            </span>
        </div>

        <!-- Client name -->
        <p v-if="dashboardCase.client" class="text-sm text-gray-700 dark:text-gray-300 truncate">
            {{ dashboardCase.client.full_name }}
        </p>

        <!-- Stage label -->
        <p class="text-xs text-gray-500 dark:text-gray-400">
            <span>{{ t('cases.stage') }}:</span>
            {{ dashboardCase.stage_label || '--' }}
        </p>

        <!-- Progress bar -->
        <div class="space-y-1">
            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>{{ t('dashboard.case_progress') }}</span>
                <span>{{ dashboardCase.progress }}%</span>
            </div>
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                <div
                    class="bg-primary h-1.5 rounded-full transition-all duration-300"
                    :style="{ width: dashboardCase.progress + '%' }"
                ></div>
            </div>
        </div>

        <!-- Next deadline -->
        <div class="flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span>{{ formatDeadline(dashboardCase.next_deadline) }}</span>
        </div>
    </div>
</template>
