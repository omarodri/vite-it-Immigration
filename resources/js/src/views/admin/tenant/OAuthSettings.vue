<template>
    <div>
        <ul class="flex space-x-2 rtl:space-x-reverse mb-6">
            <li>
                <router-link to="/admin" class="text-primary hover:underline">{{ $t('sidebar.admin') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('oauth.settings') }}</span>
            </li>
        </ul>

        <div class="panel">
            <div class="mb-5">
                <h5 class="text-lg font-semibold dark:text-white-light">{{ $t('oauth.title') }}</h5>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ $t('oauth.description') }}
                </p>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-10">
                <div class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10"></div>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Microsoft OAuth -->
                <div class="border border-[#e0e6ed] dark:border-[#1b2e4b] rounded-lg p-5">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 flex items-center justify-center bg-[#00a4ef]/10 rounded-lg">
                            <svg class="w-6 h-6 text-[#00a4ef]" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M11.4 24H0V12.6h11.4V24zM24 24H12.6V12.6H24V24zM11.4 11.4H0V0h11.4v11.4zm12.6 0H12.6V0H24v11.4z"/>
                            </svg>
                        </div>
                        <div>
                            <h6 class="font-semibold dark:text-white-light">Microsoft</h6>
                            <span
                                class="text-xs px-2 py-0.5 rounded"
                                :class="oauthStatus.microsoft?.configured
                                    ? 'bg-success/20 text-success'
                                    : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                            >
                                {{ oauthStatus.microsoft?.configured ? $t('oauth.configured') : $t('oauth.not_configured') }}
                            </span>
                        </div>
                    </div>

                    <form @submit.prevent="saveMicrosoft" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Client ID</label>
                            <input
                                v-model="microsoftForm.client_id"
                                type="text"
                                class="form-input"
                                :placeholder="oauthStatus.microsoft?.client_id || 'Enter Microsoft Client ID'"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Client Secret</label>
                            <input
                                v-model="microsoftForm.client_secret"
                                type="password"
                                class="form-input"
                                placeholder="Enter Microsoft Client Secret"
                            />
                        </div>
                        <div class="flex gap-2">
                            <button
                                type="submit"
                                class="btn btn-primary"
                                :disabled="savingMicrosoft || !microsoftForm.client_id || !microsoftForm.client_secret"
                            >
                                <span v-if="savingMicrosoft" class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 mr-2"></span>
                                {{ $t('save') }}
                            </button>
                            <button
                                v-if="oauthStatus.microsoft?.configured"
                                type="button"
                                class="btn btn-outline-danger"
                                @click="removeMicrosoft"
                                :disabled="removingMicrosoft"
                            >
                                {{ $t('remove') }}
                            </button>
                        </div>
                    </form>

                    <div v-if="oauthStatus.system_fallback?.microsoft_available && !oauthStatus.microsoft?.configured" class="mt-4 text-sm text-info">
                        <icon-info-circle class="w-4 h-4 inline mr-1" />
                        {{ $t('oauth.system_fallback_available') }}
                    </div>
                </div>

                <!-- Google OAuth -->
                <div class="border border-[#e0e6ed] dark:border-[#1b2e4b] rounded-lg p-5">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 flex items-center justify-center bg-[#ea4335]/10 rounded-lg">
                            <svg class="w-6 h-6" viewBox="0 0 24 24">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                            </svg>
                        </div>
                        <div>
                            <h6 class="font-semibold dark:text-white-light">Google</h6>
                            <span
                                class="text-xs px-2 py-0.5 rounded"
                                :class="oauthStatus.google?.configured
                                    ? 'bg-success/20 text-success'
                                    : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                            >
                                {{ oauthStatus.google?.configured ? $t('oauth.configured') : $t('oauth.not_configured') }}
                            </span>
                        </div>
                    </div>

                    <form @submit.prevent="saveGoogle" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Client ID</label>
                            <input
                                v-model="googleForm.client_id"
                                type="text"
                                class="form-input"
                                :placeholder="oauthStatus.google?.client_id || 'Enter Google Client ID'"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Client Secret</label>
                            <input
                                v-model="googleForm.client_secret"
                                type="password"
                                class="form-input"
                                placeholder="Enter Google Client Secret"
                            />
                        </div>
                        <div class="flex gap-2">
                            <button
                                type="submit"
                                class="btn btn-primary"
                                :disabled="savingGoogle || !googleForm.client_id || !googleForm.client_secret"
                            >
                                <span v-if="savingGoogle" class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 mr-2"></span>
                                {{ $t('save') }}
                            </button>
                            <button
                                v-if="oauthStatus.google?.configured"
                                type="button"
                                class="btn btn-outline-danger"
                                @click="removeGoogle"
                                :disabled="removingGoogle"
                            >
                                {{ $t('remove') }}
                            </button>
                        </div>
                    </form>

                    <div v-if="oauthStatus.system_fallback?.google_available && !oauthStatus.google?.configured" class="mt-4 text-sm text-info">
                        <icon-info-circle class="w-4 h-4 inline mr-1" />
                        {{ $t('oauth.system_fallback_available') }}
                    </div>
                </div>
            </div>

            <!-- Help Text -->
            <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <h6 class="font-semibold mb-2 dark:text-white-light">{{ $t('oauth.help_title') }}</h6>
                <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1 list-disc list-inside">
                    <li>{{ $t('oauth.help_microsoft') }}</li>
                    <li>{{ $t('oauth.help_google') }}</li>
                    <li>{{ $t('oauth.help_redirect') }}</li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import IconInfoCircle from '@/components/icon/icon-info-circle.vue';
import { useMeta } from '@/composables/use-meta';

useMeta({ title: 'OAuth Settings' });

interface OAuthStatus {
    microsoft: {
        configured: boolean;
        client_id: string | null;
    };
    google: {
        configured: boolean;
        client_id: string | null;
    };
    system_fallback: {
        microsoft_available: boolean;
        google_available: boolean;
    };
}

const loading = ref(true);
const savingMicrosoft = ref(false);
const savingGoogle = ref(false);
const removingMicrosoft = ref(false);
const removingGoogle = ref(false);

const oauthStatus = ref<OAuthStatus>({
    microsoft: { configured: false, client_id: null },
    google: { configured: false, client_id: null },
    system_fallback: { microsoft_available: false, google_available: false },
});

const microsoftForm = reactive({
    client_id: '',
    client_secret: '',
});

const googleForm = reactive({
    client_id: '',
    client_secret: '',
});

const fetchStatus = async () => {
    try {
        const response = await axios.get('/api/tenant/oauth/status');
        oauthStatus.value = response.data;
    } catch (error) {
        console.error('Failed to fetch OAuth status:', error);
    } finally {
        loading.value = false;
    }
};

const saveMicrosoft = async () => {
    savingMicrosoft.value = true;
    try {
        await axios.put('/api/tenant/oauth/microsoft', microsoftForm);
        await fetchStatus();
        microsoftForm.client_id = '';
        microsoftForm.client_secret = '';
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: 'Microsoft OAuth credentials saved successfully.',
            timer: 2000,
            showConfirmButton: false,
        });
    } catch (error: any) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.response?.data?.error || 'Failed to save Microsoft credentials.',
        });
    } finally {
        savingMicrosoft.value = false;
    }
};

const saveGoogle = async () => {
    savingGoogle.value = true;
    try {
        await axios.put('/api/tenant/oauth/google', googleForm);
        await fetchStatus();
        googleForm.client_id = '';
        googleForm.client_secret = '';
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: 'Google OAuth credentials saved successfully.',
            timer: 2000,
            showConfirmButton: false,
        });
    } catch (error: any) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.response?.data?.error || 'Failed to save Google credentials.',
        });
    } finally {
        savingGoogle.value = false;
    }
};

const removeMicrosoft = async () => {
    const result = await Swal.fire({
        icon: 'warning',
        title: 'Remove Microsoft OAuth?',
        text: 'This will disconnect Microsoft integrations for your organization.',
        showCancelButton: true,
        confirmButtonText: 'Yes, remove',
        confirmButtonColor: '#e7515a',
    });

    if (result.isConfirmed) {
        removingMicrosoft.value = true;
        try {
            await axios.delete('/api/tenant/oauth/microsoft');
            await fetchStatus();
            Swal.fire({
                icon: 'success',
                title: 'Removed',
                text: 'Microsoft OAuth credentials removed.',
                timer: 2000,
                showConfirmButton: false,
            });
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to remove Microsoft credentials.',
            });
        } finally {
            removingMicrosoft.value = false;
        }
    }
};

const removeGoogle = async () => {
    const result = await Swal.fire({
        icon: 'warning',
        title: 'Remove Google OAuth?',
        text: 'This will disconnect Google integrations for your organization.',
        showCancelButton: true,
        confirmButtonText: 'Yes, remove',
        confirmButtonColor: '#e7515a',
    });

    if (result.isConfirmed) {
        removingGoogle.value = true;
        try {
            await axios.delete('/api/tenant/oauth/google');
            await fetchStatus();
            Swal.fire({
                icon: 'success',
                title: 'Removed',
                text: 'Google OAuth credentials removed.',
                timer: 2000,
                showConfirmButton: false,
            });
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Failed to remove Google credentials.',
            });
        } finally {
            removingGoogle.value = false;
        }
    }
};

onMounted(fetchStatus);
</script>
