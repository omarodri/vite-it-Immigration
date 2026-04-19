<template>
    <div class="panel mt-5">
        <div class="flex items-center justify-between mb-5">
            <h5 class="font-semibold text-lg dark:text-white-light">{{ $t('calendar_sync.title') }}</h5>
        </div>

        <div v-if="loading && !status" class="flex justify-center py-8">
            <span class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10 inline-block"></span>
        </div>

        <div v-else class="space-y-4">
            <!-- Google Calendar -->
            <div class="flex items-center justify-between p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full bg-danger/10 flex items-center justify-center shrink-0">
                        <span class="text-danger font-bold text-sm">G</span>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $t('calendar_sync.google_calendar') }}</p>
                        <p v-if="status?.google.connected" class="text-xs text-success">{{ $t('calendar_sync.connected') }}</p>
                        <p v-else class="text-xs text-gray-400">{{ $t('calendar_sync.not_connected') }}</p>
                        <p v-if="status?.google.last_pull_at" class="text-xs text-gray-400 mt-0.5">
                            {{ $t('calendar_sync.last_sync') }}: {{ formatDate(status.google.last_pull_at) }}
                        </p>
                        <p v-if="status?.google.sync_status === 'error' && status.google.last_error" class="text-xs text-danger mt-0.5">
                            {{ status.google.last_error }}
                        </p>
                    </div>
                </div>
                <div class="shrink-0 ml-4">
                    <button
                        v-if="status?.google.connected"
                        type="button"
                        class="btn btn-outline-danger btn-sm"
                        :disabled="loading"
                        @click="disconnect('google')"
                    >
                        {{ $t('calendar_sync.disconnect') }}
                    </button>
                    <button
                        v-else
                        type="button"
                        class="btn btn-outline-primary btn-sm"
                        :disabled="loading || !status?.google.credentials_configured"
                        @click="connect('google')"
                    >
                        {{ $t('calendar_sync.connect') }}
                    </button>
                </div>
            </div>

            <!-- Microsoft Outlook -->
            <div class="flex items-center justify-between p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full bg-info/10 flex items-center justify-center shrink-0">
                        <span class="text-info font-bold text-sm">O</span>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $t('calendar_sync.microsoft_outlook') }}</p>
                        <p v-if="status?.microsoft.connected" class="text-xs text-success">{{ $t('calendar_sync.connected') }}</p>
                        <p v-else class="text-xs text-gray-400">{{ $t('calendar_sync.not_connected') }}</p>
                        <p v-if="status?.microsoft.last_pull_at" class="text-xs text-gray-400 mt-0.5">
                            {{ $t('calendar_sync.last_sync') }}: {{ formatDate(status.microsoft.last_pull_at) }}
                        </p>
                        <p v-if="status?.microsoft.sync_status === 'error' && status.microsoft.last_error" class="text-xs text-danger mt-0.5">
                            {{ status.microsoft.last_error }}
                        </p>
                    </div>
                </div>
                <div class="shrink-0 ml-4">
                    <button
                        v-if="status?.microsoft.connected"
                        type="button"
                        class="btn btn-outline-danger btn-sm"
                        :disabled="loading"
                        @click="disconnect('microsoft')"
                    >
                        {{ $t('calendar_sync.disconnect') }}
                    </button>
                    <button
                        v-else
                        type="button"
                        class="btn btn-outline-primary btn-sm"
                        :disabled="loading || !status?.microsoft.credentials_configured"
                        @click="connect('microsoft')"
                    >
                        {{ $t('calendar_sync.connect') }}
                    </button>
                </div>
            </div>

            <p class="text-xs text-gray-400 text-center pt-1">{{ $t('calendar_sync.sync_every_15_min') }}</p>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import Swal from 'sweetalert2'
import { calendarSyncService, type CalendarConnectionStatus } from '@/services/calendarSyncService'
import { formatDate } from '@/utils/formatters'

const { t } = useI18n()
const route = useRoute()

const status = ref<CalendarConnectionStatus | null>(null)
const loading = ref(false)

onMounted(async () => {
    await fetchStatus()

    const connected = route.query.calendar_connected as string
    if (connected) {
        const providerName = connected === 'google'
            ? t('calendar_sync.google_calendar')
            : t('calendar_sync.microsoft_outlook')
        Swal.fire({
            icon: 'success',
            title: t('calendar_sync.connected_success', { provider: providerName }),
            timer: 3000,
            showConfirmButton: false,
        })
    }

    const calendarError = route.query.calendar_error as string
    if (calendarError) {
        Swal.fire({
            icon: 'error',
            title: t('calendar_sync.connect_error'),
            text: decodeURIComponent(calendarError),
        })
    }
})

async function fetchStatus() {
    loading.value = true
    try {
        status.value = await calendarSyncService.getStatus()
    } catch {
        // non-critical
    } finally {
        loading.value = false
    }
}

async function connect(provider: 'google' | 'microsoft') {
    loading.value = true
    try {
        const { url } = await calendarSyncService.getRedirectUrl(provider)
        window.location.href = url
    } catch {
        Swal.fire({ icon: 'error', title: t('calendar_sync.connect_error') })
        loading.value = false
    }
}

async function disconnect(provider: 'google' | 'microsoft') {
    const result = await Swal.fire({
        title: t('calendar_sync.confirm_disconnect'),
        text: t('calendar_sync.disconnect_warning'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: t('calendar_sync.confirm'),
        cancelButtonText: t('calendar_sync.cancel'),
        confirmButtonColor: '#e7515a',
    })

    if (!result.isConfirmed) return

    loading.value = true
    try {
        await calendarSyncService.disconnect(provider)
        await fetchStatus()
        Swal.fire({
            icon: 'success',
            title: t('calendar_sync.disconnected'),
            timer: 2000,
            showConfirmButton: false,
        })
    } catch {
        Swal.fire({ icon: 'error', title: t('calendar_sync.connect_error') })
    } finally {
        loading.value = false
    }
}
</script>
