<script lang="ts" setup>
import { onMounted, computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';

import { useTimesheetStore } from '@/stores/useTimesheetStore';
import { usePermissions } from '@/composables/usePermissions';
import TimeDisplay from './TimeDisplay.vue';

import IconTrashLines from '@/components/icon/icon-trash-lines.vue';

interface Props {
    caseId: number;
}
const props = defineProps<Props>();

const { t } = useI18n();
const store = useTimesheetStore();
const { caseLogs, caseLogsMeta, isLoading } = storeToRefs(store);
const { can } = usePermissions();

const logs = computed(() => caseLogs.value[props.caseId] ?? []);
const meta = computed(() => caseLogsMeta.value[props.caseId] ?? null);

onMounted(() => {
    store.fetchCaseLogs(props.caseId);
});

async function removeLog(logId: number) {
    const result = await Swal.fire({
        title: t('timesheet.confirm_delete'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e7515a',
        cancelButtonText: t('cases.cancel'),
        confirmButtonText: t('cases.delete'),
    });
    if (result.isConfirmed) {
        try {
            await store.deleteLog(props.caseId, logId);
        } catch (e: any) {
            Swal.fire({
                icon: 'error',
                title: t('timesheet.error_title'),
                text: e?.response?.data?.message ?? t('timesheet.error_generic'),
            });
        }
    }
}
</script>

<template>
    <div>
        <div v-if="isLoading && logs.length === 0" class="text-center py-6 text-gray-400">
            <span class="animate-spin inline-block w-5 h-5 border-2 border-primary border-t-transparent rounded-full"></span>
        </div>
        <div v-else-if="logs.length === 0" class="text-center py-6 text-gray-400 text-sm">
            {{ t('timesheet.no_time_logged') }}
        </div>
        <div v-else class="table-responsive">
            <table class="table-hover">
                <thead>
                    <tr>
                        <th>{{ t('timesheet.date') }}</th>
                        <th>{{ t('timesheet.logged_by') }}</th>
                        <th>{{ t('timesheet.task_optional') }}</th>
                        <th>{{ t('timesheet.total_time') }}</th>
                        <th>{{ t('timesheet.description') }}</th>
                        <th v-if="can('time_logs.delete')" class="!text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="log in logs" :key="log.id">
                        <td class="text-sm whitespace-nowrap">{{ log.work_date }}</td>
                        <td class="text-sm whitespace-nowrap">{{ log.user?.name }}</td>
                        <td class="text-sm text-gray-500">
                            {{ log.task?.title ?? log.todo?.title ?? '—' }}
                        </td>
                        <td>
                            <TimeDisplay :seconds="log.duration_seconds" size="sm" :show-icon="false" />
                        </td>
                        <td class="text-sm text-gray-500 max-w-xs truncate">
                            {{ log.description ?? '—' }}
                        </td>
                        <td v-if="can('time_logs.delete')" class="!text-end">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger gap-1"
                                @click="removeLog(log.id)"
                            >
                                <icon-trash-lines class="w-3.5 h-3.5" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="meta && meta.total !== undefined" class="text-xs text-gray-400 mt-3">
                {{ logs.length }} / {{ meta.total }}
            </div>
        </div>
    </div>
</template>
