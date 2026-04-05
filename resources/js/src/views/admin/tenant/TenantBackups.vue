<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-6">
            <li>
                <router-link to="/admin" class="text-primary hover:underline">{{ $t('sidebar.admin') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('backup.title') }}</span>
            </li>
        </ul>

        <div class="panel">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                <div>
                    <h5 class="text-lg font-semibold dark:text-white-light">{{ $t('backup.title') }}</h5>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $t('backup.description') }}</p>
                </div>
                <button
                    type="button"
                    class="btn btn-primary gap-2"
                    :disabled="isGenerating"
                    @click="generateBackup"
                >
                    <span
                        v-if="isGenerating"
                        class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 inline-block"
                    ></span>
                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                    </svg>
                    {{ isGenerating ? $t('backup.generating') : $t('backup.generate') }}
                </button>
            </div>

            <!-- Info banner -->
            <div class="flex items-start gap-3 p-4 rounded-lg bg-info/10 border border-info/20 mb-6 text-sm text-info dark:text-blue-300">
                <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ $t('backup.async_info') }}</span>
            </div>

            <!-- Loading -->
            <div v-if="isLoading" class="flex items-center justify-center py-10">
                <span class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10 inline-block"></span>
            </div>

            <!-- Empty state -->
            <div v-else-if="backups.length === 0" class="flex flex-col items-center justify-center py-12 text-gray-400 dark:text-gray-500">
                <svg class="w-14 h-14 mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                </svg>
                <p class="text-sm">{{ $t('backup.no_backups') }}</p>
            </div>

            <!-- Table -->
            <div v-else class="table-responsive">
                <table class="table-hover">
                    <thead>
                        <tr>
                            <th>{{ $t('backup.col_date') }}</th>
                            <th>{{ $t('backup.col_filename') }}</th>
                            <th>{{ $t('backup.col_size') }}</th>
                            <th>{{ $t('backup.col_rows') }}</th>
                            <th>{{ $t('backup.col_duration') }}</th>
                            <th>{{ $t('backup.col_source') }}</th>
                            <th>{{ $t('backup.col_status') }}</th>
                            <th class="text-center">{{ $t('clients.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in backups" :key="log.id">
                            <td class="whitespace-nowrap text-sm">{{ formatDate(log.created_at) }}</td>
                            <td class="text-xs text-gray-500 dark:text-gray-400 max-w-xs truncate">{{ log.filename || '-' }}</td>
                            <td class="whitespace-nowrap text-sm">{{ log.file_size_mb ? log.file_size_mb + ' MB' : '-' }}</td>
                            <td class="whitespace-nowrap text-sm">{{ log.row_count ? formatNumber(log.row_count) : '-' }}</td>
                            <td class="whitespace-nowrap text-sm">{{ log.duration_s != null ? log.duration_s + 's' : '-' }}</td>
                            <td>
                                <span class="badge badge-outline-secondary text-xs">{{ log.trigger_source }}</span>
                            </td>
                            <td>
                                <span class="badge text-xs" :class="statusBadgeClass(log.status)">
                                    {{ $t('backup.status_' + log.status) }}
                                </span>
                                <p v-if="log.error_message" class="text-xs text-danger mt-1 max-w-xs truncate" :title="log.error_message">
                                    {{ log.error_message }}
                                </p>
                            </td>
                            <td class="text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <button
                                        v-if="log.status === 'completed'"
                                        type="button"
                                        class="btn btn-sm btn-outline-primary p-1.5"
                                        :title="$t('backup.download')"
                                        :disabled="downloading === log.id"
                                        @click="downloadBackup(log)"
                                    >
                                        <span v-if="downloading === log.id" class="animate-spin border-2 border-primary border-l-transparent rounded-full w-3 h-3 inline-block"></span>
                                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger p-1.5"
                                        :title="$t('backup.delete')"
                                        @click="confirmDelete(log)"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                    <button
                                        v-if="log.status === 'running' || log.status === 'pending'"
                                        type="button"
                                        class="btn btn-sm btn-outline-info p-1.5"
                                        :title="$t('backup.refresh')"
                                        @click="loadBackups"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMeta } from '@/composables/use-meta';
import { useNotification } from '@/composables/useNotification';
import { useTenantStore } from '@/stores/tenant';
import backupService from '@/services/backupService';
import { formatDate } from '@/utils/formatters';
import type { BackupLog, BackupStatus } from '@/types/backup';

// SweetAlert2 for confirm dialog
import Swal from 'sweetalert2';

useMeta({ title: 'Backups' });

const { t } = useI18n();
const { success, error } = useNotification();
const tenantStore = useTenantStore();

const isLoading    = ref(false);
const isGenerating = ref(false);
const downloading  = ref<number | null>(null);
const backups      = ref<BackupLog[]>([]);

function statusBadgeClass(status: BackupStatus): string {
    const map: Record<BackupStatus, string> = {
        pending:   'badge-outline-secondary',
        running:   'badge-outline-info',
        completed: 'badge-outline-success',
        failed:    'badge-outline-danger',
    };
    return map[status] ?? 'badge-outline-secondary';
}

function formatNumber(n: number): string {
    return n.toLocaleString();
}

async function loadBackups() {
    const tenantId = tenantStore.tenant?.id;
    if (!tenantId) return;

    isLoading.value = true;
    try {
        backups.value = await backupService.listBackups(tenantId);
    } catch {
        error(t('backup.load_failed'));
    } finally {
        isLoading.value = false;
    }
}

async function generateBackup() {
    const tenantId = tenantStore.tenant?.id;
    if (!tenantId) return;

    isGenerating.value = true;
    try {
        await backupService.runBackup(tenantId);
        success(t('backup.queued_success'));
        // Reload after short delay so the pending entry shows
        setTimeout(() => loadBackups(), 1500);
    } catch {
        error(t('backup.generate_failed'));
    } finally {
        isGenerating.value = false;
    }
}

async function downloadBackup(log: BackupLog) {
    downloading.value = log.id;
    try {
        const blob = await backupService.downloadBackup(log.id);
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = log.filename || `backup-${log.id}.sql.gz`;
        a.click();
        URL.revokeObjectURL(url);
    } catch {
        error(t('backup.download_failed'));
    } finally {
        downloading.value = null;
    }
}

async function confirmDelete(log: BackupLog) {
    const result = await Swal.fire({
        title: t('backup.confirm_delete_title'),
        text:  t('backup.confirm_delete_text'),
        icon:  'warning',
        showCancelButton:  true,
        confirmButtonText: t('backup.yes_delete'),
        cancelButtonText:  t('clients.cancel'),
        padding: '2em',
        customClass: { popup: 'sweet-alerts' },
    });

    if (result.isConfirmed) {
        try {
            await backupService.deleteBackup(log.id);
            backups.value = backups.value.filter(b => b.id !== log.id);
            success(t('backup.deleted_success'));
        } catch {
            error(t('backup.delete_failed'));
        }
    }
}

onMounted(async () => {
    if (!tenantStore.isLoaded) {
        await tenantStore.fetchTenant();
    }
    loadBackups();
});
</script>
