<template>
    <div class="border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-[#0e1726] p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-700 dark:text-gray-300">
                {{ $t('documents.cloud_settings') }}
            </h3>
            <button
                type="button"
                class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                @click="$emit('close')"
            >
                <icon-x class="w-5 h-5" />
            </button>
        </div>

        <!-- Current Storage Mode -->
        <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $t('documents.current_mode') }}:</span>
            <span class="badge bg-primary/10 text-primary text-xs px-2 py-0.5 rounded">
                {{ storageLabel }}
            </span>
        </div>

        <!-- Microsoft OneDrive Card -->
        <div
            v-if="storageType === 'onedrive'"
            class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
        >
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 flex items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20 shrink-0">
                    <svg class="w-6 h-6 text-blue-600" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M14.5 15.41l-4.83-2.96 6.19-5.13A5.49 5.49 0 0 0 9.5 4a5.5 5.5 0 0 0-4.58 8.54L9.5 15.41l5-0z" opacity="0.5"/>
                        <path d="M9.5 15.41l-4.58-2.87A4.49 4.49 0 0 0 0 16.5 4.5 4.5 0 0 0 4.5 21h8l-3-5.59z"/>
                        <path d="M15.86 7.32l-.22.04-6.19 5.13L14.5 15.41l3 5.59H19.5a4.5 4.5 0 0 0 .36-8.97z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ $t('documents.storage_onedrive') }}
                    </h4>
                    <p v-if="status.microsoft.connected" class="text-xs text-success mt-0.5">
                        {{ $t('documents.connected_as', { email: status.microsoft.email }) }}
                    </p>
                    <p v-else class="text-xs text-gray-400 mt-0.5">
                        {{ $t('documents.not_connected') }}
                    </p>
                </div>
                <button
                    v-if="status.microsoft.connected"
                    type="button"
                    class="btn btn-outline-danger btn-sm"
                    :disabled="loading"
                    @click="disconnect('microsoft')"
                >
                    {{ $t('documents.disconnect') }}
                </button>
                <button
                    v-else
                    type="button"
                    class="btn btn-outline-primary btn-sm"
                    :disabled="loading"
                    @click="connect('microsoft')"
                >
                    {{ $t('documents.connect') }}
                </button>
            </div>
        </div>

        <!-- Google Drive Card -->
        <div
            v-if="storageType === 'google_drive'"
            class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
        >
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 flex items-center justify-center rounded-lg bg-green-50 dark:bg-green-900/20 shrink-0">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4.433 22l-1.766-3.06 7.567-13.1h3.533L6.2 22H4.433z" fill="#0066DA"/>
                        <path d="M22 15.94H8.867l1.766 3.06H22l-1.767 3.06z" fill="#00AC47" opacity="0.8"/>
                        <path d="M14.233 5.84L10.7 11.95l1.767 3.06 5.3-9.17H14.233z" fill="#EA4335" opacity="0.8"/>
                        <path d="M8.867 15.94l-3.533-6.12 1.766-3.06 3.534 6.12-1.767 3.06z" fill="#00832D"/>
                        <path d="M15.467 5.84h3.534l-5.3 9.17-1.767-3.06 3.533-6.11z" fill="#2684FC"/>
                        <path d="M8.867 15.94l1.767 3.06L22 15.94h-3.534l-9.599.001z" fill="#FFBA00" opacity="0.8"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ $t('documents.storage_google_drive') }}
                    </h4>
                    <p v-if="status.google.connected" class="text-xs text-success mt-0.5">
                        {{ $t('documents.connected_as', { email: status.google.email }) }}
                    </p>
                    <p v-else class="text-xs text-gray-400 mt-0.5">
                        {{ $t('documents.not_connected') }}
                    </p>
                </div>
                <button
                    v-if="status.google.connected"
                    type="button"
                    class="btn btn-outline-danger btn-sm"
                    :disabled="loading"
                    @click="disconnect('google')"
                >
                    {{ $t('documents.disconnect') }}
                </button>
                <button
                    v-else
                    type="button"
                    class="btn btn-outline-primary btn-sm"
                    :disabled="loading"
                    @click="connect('google')"
                >
                    {{ $t('documents.connect') }}
                </button>
            </div>
        </div>

        <!-- Local storage info -->
        <div
            v-if="storageType === 'local'"
            class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
        >
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 flex items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800 shrink-0">
                    <icon-server class="w-5 h-5 text-gray-500" />
                </div>
                <div class="flex-1 min-w-0">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ $t('documents.storage_local') }}
                    </h4>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ $t('documents.storage_local_desc') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';
import oauthService from '@/services/oauthService';
import type { OAuthStatus, OAuthProvider } from '@/types/oauth';

import IconX from '@/components/icon/icon-x.vue';
import IconServer from '@/components/icon/icon-server.vue';

const { t } = useI18n();

const props = defineProps<{
    storageType: 'local' | 'onedrive' | 'google_drive';
}>();

defineEmits<{
    (e: 'close'): void;
}>();

const loading = ref(false);
const status = ref<OAuthStatus>({
    microsoft: { connected: false },
    google: { connected: false },
});

const storageLabel = computed(() => {
    switch (props.storageType) {
        case 'onedrive':
            return t('documents.storage_onedrive');
        case 'google_drive':
            return t('documents.storage_google_drive');
        default:
            return t('documents.storage_local');
    }
});

async function fetchStatus() {
    try {
        status.value = await oauthService.getStatus();
    } catch {
        // Error handled by api interceptor
    }
}

async function connect(provider: OAuthProvider) {
    loading.value = true;
    try {
        const url = await oauthService.getRedirectUrl(provider);
        window.open(url, '_blank', 'width=600,height=700');

        // Poll for status changes after the user completes the OAuth flow
        const pollInterval = setInterval(async () => {
            try {
                const newStatus = await oauthService.getStatus();
                if (
                    (provider === 'microsoft' && newStatus.microsoft.connected) ||
                    (provider === 'google' && newStatus.google.connected)
                ) {
                    status.value = newStatus;
                    clearInterval(pollInterval);
                    loading.value = false;
                    showMessage(t('documents.connected_success'));
                }
            } catch {
                // Silently continue polling
            }
        }, 3000);

        // Stop polling after 5 minutes
        setTimeout(() => {
            clearInterval(pollInterval);
            loading.value = false;
        }, 300000);
    } catch {
        loading.value = false;
    }
}

async function disconnect(provider: OAuthProvider) {
    const result = await Swal.fire({
        title: t('documents.disconnect_confirm'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e7515a',
        confirmButtonText: t('documents.disconnect'),
        cancelButtonText: t('todo_cancel'),
    });

    if (result.isConfirmed) {
        loading.value = true;
        try {
            await oauthService.disconnect(provider);
            await fetchStatus();
            showMessage(t('documents.disconnected'));
        } catch {
            // Error handled by api interceptor
        } finally {
            loading.value = false;
        }
    }
}

function showMessage(msg = '', type = 'success') {
    const toast: any = Swal.mixin({
        toast: true,
        position: 'top',
        showConfirmButton: false,
        timer: 3000,
        customClass: { container: 'toast' },
    });
    toast.fire({ icon: type, title: msg, padding: '10px 20px' });
}

onMounted(() => {
    if (props.storageType !== 'local') {
        fetchStatus();
    }
});
</script>
