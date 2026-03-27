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

            <div v-else class="space-y-6">
                <!-- Storage Type Section -->
                <div class="border border-[#e0e6ed] dark:border-[#1b2e4b] rounded-lg p-5">
                    <h6 class="font-semibold mb-4 dark:text-white-light">{{ $t('tenant.storage_type') }}</h6>

                    <div class="space-y-3">
                        <label class="flex items-center gap-3 p-3 rounded-lg border border-[#e0e6ed] dark:border-[#1b2e4b] cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors" :class="{ 'border-primary bg-primary/5': storageForm.storage_type === 'local' }">
                            <input type="radio" v-model="storageForm.storage_type" value="local" class="form-radio text-primary" />
                            <div>
                                <span class="font-medium dark:text-white-light">{{ $t('tenant.storage_local') }}</span>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('tenant.storage_local_desc') }}</p>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-3 rounded-lg border border-[#e0e6ed] dark:border-[#1b2e4b] cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors" :class="{ 'border-primary bg-primary/5': storageForm.storage_type === 'onedrive' }">
                            <input type="radio" v-model="storageForm.storage_type" value="onedrive" class="form-radio text-primary" />
                            <div>
                                <span class="font-medium dark:text-white-light">{{ $t('tenant.storage_onedrive') }}</span>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-3 rounded-lg border border-[#e0e6ed] dark:border-[#1b2e4b] cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors" :class="{ 'border-primary bg-primary/5': storageForm.storage_type === 'google_drive' }">
                            <input type="radio" v-model="storageForm.storage_type" value="google_drive" class="form-radio text-primary" />
                            <div>
                                <span class="font-medium dark:text-white-light">{{ $t('tenant.storage_google') }}</span>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-3 rounded-lg border border-[#e0e6ed] dark:border-[#1b2e4b] cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors" :class="{ 'border-primary bg-primary/5': storageForm.storage_type === 'sharepoint' }">
                            <input type="radio" v-model="storageForm.storage_type" value="sharepoint" class="form-radio text-primary" />
                            <div>
                                <span class="font-medium dark:text-white-light">{{ $t('tenant.storage_sharepoint') }}</span>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('tenant.storage_sharepoint_desc') }}</p>
                            </div>
                        </label>
                    </div>

                    <div class="mt-4">
                        <button type="button" class="btn btn-primary" :disabled="savingStorage" @click="saveStorageType">
                            <span v-if="savingStorage" class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 mr-2 inline-block"></span>
                            {{ $t('tenant.save_settings') }}
                        </button>
                    </div>

                    <!-- Base Folder Configuration -->
                    <div class="mt-4 pt-4 border-t border-[#e0e6ed] dark:border-[#1b2e4b]">
                        <label class="block text-sm font-medium mb-2 dark:text-white-light">{{ $t('tenant.base_folder') }}</label>
                        <div class="flex gap-2">
                            <input
                                v-model="baseFolderForm.base_folder_path"
                                type="text"
                                class="form-input flex-1"
                                :placeholder="$t('tenant.base_folder_placeholder')"
                            />
                            <button type="button" class="btn btn-primary" :disabled="savingBaseFolder" @click="saveBaseFolder">
                                <span v-if="savingBaseFolder" class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 mr-2 inline-block"></span>
                                {{ $t('save') }}
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $t('tenant.base_folder_hint') }}</p>
                        <p v-if="baseFolderForm.base_folder_path" class="text-xs text-primary mt-1">
                            {{ $t('tenant.base_folder_preview') }}: <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">{{ baseFolderForm.base_folder_path }}/{{ $t('tenant.base_folder_case_example') }}/...</code>
                        </p>
                    </div>
                </div>

                <!-- Provider Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Microsoft OAuth -->
                    <div class="border border-[#e0e6ed] dark:border-[#1b2e4b] rounded-lg p-5">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 flex items-center justify-center bg-[#00a4ef]/10 rounded-lg">
                                <svg class="w-6 h-6 text-[#00a4ef]" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M11.4 24H0V12.6h11.4V24zM24 24H12.6V12.6H24V24zM11.4 11.4H0V0h11.4v11.4zm12.6 0H12.6V0H24v11.4z"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h6 class="font-semibold dark:text-white-light">Microsoft OneDrive</h6>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span
                                        class="text-xs px-2 py-0.5 rounded"
                                        :class="credentialStatus.microsoft?.configured
                                            ? 'bg-success/20 text-success'
                                            : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                                    >
                                        {{ credentialStatus.microsoft?.configured ? $t('oauth.configured') : $t('oauth.not_configured') }}
                                    </span>
                                    <span
                                        v-if="credentialStatus.microsoft?.configured"
                                        class="text-xs px-2 py-0.5 rounded"
                                        :class="connectionStatus.microsoft?.connected
                                            ? 'bg-success/20 text-success'
                                            : 'bg-warning/20 text-warning'"
                                    >
                                        {{ connectionStatus.microsoft?.connected ? $t('oauth.connected') : $t('oauth.not_connected') }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- API Credentials Form -->
                        <form @submit.prevent="saveMicrosoft" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">Client ID</label>
                                <input
                                    v-model="microsoftForm.client_id"
                                    type="text"
                                    class="form-input"
                                    :placeholder="credentialStatus.microsoft?.client_id || 'Enter Microsoft Client ID'"
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
                                    v-if="credentialStatus.microsoft?.configured"
                                    type="button"
                                    class="btn btn-outline-danger"
                                    @click="removeMicrosoft"
                                    :disabled="removingMicrosoft"
                                >
                                    {{ $t('remove') }}
                                </button>
                            </div>
                        </form>

                        <!-- Connect/Disconnect OneDrive Account (tenant-level) -->
                        <div v-if="credentialStatus.microsoft?.configured" class="mt-4 pt-4 border-t border-[#e0e6ed] dark:border-[#1b2e4b]">
                            <p class="text-sm font-medium mb-2 dark:text-white-light">{{ $t('oauth.account_connection') }}</p>
                            <div v-if="connectionStatus.microsoft?.connected" class="flex items-center gap-3">
                                <span class="text-sm text-success">{{ $t('oauth.tenant_connected') }}</span>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    :disabled="connectingProvider !== null"
                                    @click="disconnectProvider('microsoft')"
                                >
                                    {{ $t('oauth.disconnect') }}
                                </button>
                            </div>
                            <div v-else>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ $t('oauth.connect_hint') }}</p>
                                <button
                                    type="button"
                                    class="btn btn-outline-primary btn-sm"
                                    :disabled="connectingProvider !== null"
                                    @click="connectProvider('microsoft')"
                                >
                                    <span v-if="connectingProvider === 'microsoft'" class="animate-spin border-2 border-primary border-l-transparent rounded-full w-4 h-4 mr-2"></span>
                                    {{ $t('oauth.connect_onedrive') }}
                                </button>
                            </div>
                        </div>

                        <div v-if="credentialStatus.system_fallback?.microsoft_available && !credentialStatus.microsoft?.configured" class="mt-4 text-sm text-info">
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
                            <div class="flex-1">
                                <h6 class="font-semibold dark:text-white-light">Google Drive</h6>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span
                                        class="text-xs px-2 py-0.5 rounded"
                                        :class="credentialStatus.google?.configured
                                            ? 'bg-success/20 text-success'
                                            : 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
                                    >
                                        {{ credentialStatus.google?.configured ? $t('oauth.configured') : $t('oauth.not_configured') }}
                                    </span>
                                    <span
                                        v-if="credentialStatus.google?.configured"
                                        class="text-xs px-2 py-0.5 rounded"
                                        :class="connectionStatus.google?.connected
                                            ? 'bg-success/20 text-success'
                                            : 'bg-warning/20 text-warning'"
                                    >
                                        {{ connectionStatus.google?.connected ? $t('oauth.connected') : $t('oauth.not_connected') }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- API Credentials Form -->
                        <form @submit.prevent="saveGoogle" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">Client ID</label>
                                <input
                                    v-model="googleForm.client_id"
                                    type="text"
                                    class="form-input"
                                    :placeholder="credentialStatus.google?.client_id || 'Enter Google Client ID'"
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
                                    v-if="credentialStatus.google?.configured"
                                    type="button"
                                    class="btn btn-outline-danger"
                                    @click="removeGoogle"
                                    :disabled="removingGoogle"
                                >
                                    {{ $t('remove') }}
                                </button>
                            </div>
                        </form>

                        <!-- Connect/Disconnect Google Drive Account (tenant-level) -->
                        <div v-if="credentialStatus.google?.configured" class="mt-4 pt-4 border-t border-[#e0e6ed] dark:border-[#1b2e4b]">
                            <p class="text-sm font-medium mb-2 dark:text-white-light">{{ $t('oauth.account_connection') }}</p>
                            <div v-if="connectionStatus.google?.connected" class="flex items-center gap-3">
                                <span class="text-sm text-success">{{ $t('oauth.tenant_connected') }}</span>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    :disabled="connectingProvider !== null"
                                    @click="disconnectProvider('google')"
                                >
                                    {{ $t('oauth.disconnect') }}
                                </button>
                            </div>
                            <div v-else>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ $t('oauth.connect_hint') }}</p>
                                <button
                                    type="button"
                                    class="btn btn-outline-primary btn-sm"
                                    :disabled="connectingProvider !== null"
                                    @click="connectProvider('google')"
                                >
                                    <span v-if="connectingProvider === 'google'" class="animate-spin border-2 border-primary border-l-transparent rounded-full w-4 h-4 mr-2"></span>
                                    {{ $t('oauth.connect_google') }}
                                </button>
                            </div>
                        </div>

                        <div v-if="credentialStatus.system_fallback?.google_available && !credentialStatus.google?.configured" class="mt-4 text-sm text-info">
                            <icon-info-circle class="w-4 h-4 inline mr-1" />
                            {{ $t('oauth.system_fallback_available') }}
                        </div>
                    </div>
                </div>

                <!-- SharePoint Configuration -->
                <div v-if="storageForm.storage_type === 'sharepoint' && connectionStatus.microsoft?.connected" class="border border-[#e0e6ed] dark:border-[#1b2e4b] rounded-lg p-5">
                    <h6 class="font-semibold mb-4 dark:text-white-light">{{ $t('tenant.sharepoint_config') }}</h6>

                    <!-- Site Selector -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">{{ $t('tenant.sharepoint_site') }}</label>
                            <div class="flex gap-2">
                                <select v-model="sharepointForm.site_id" class="form-select flex-1" @change="onSiteSelected">
                                    <option value="">{{ $t('tenant.sharepoint_select_site') }}</option>
                                    <option v-for="site in sharepointSites" :key="site.id" :value="site.id">{{ site.displayName }}</option>
                                </select>
                                <button type="button" class="btn btn-outline-primary btn-sm" :disabled="loadingSites" @click="fetchSharePointSites">
                                    <span v-if="loadingSites" class="animate-spin border-2 border-primary border-l-transparent rounded-full w-4 h-4"></span>
                                    <span v-else>{{ $t('tenant.sharepoint_refresh') }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Drive/Library Selector -->
                        <div v-if="sharepointForm.site_id">
                            <label class="block text-sm font-medium mb-1">{{ $t('tenant.sharepoint_library') }}</label>
                            <select v-model="sharepointForm.drive_id" class="form-select" :disabled="loadingDrives">
                                <option value="">{{ $t('tenant.sharepoint_select_library') }}</option>
                                <option v-for="drive in sharepointDrives" :key="drive.id" :value="drive.id">{{ drive.name }} ({{ drive.driveType }})</option>
                            </select>
                        </div>

                        <!-- Save Button -->
                        <div v-if="sharepointForm.site_id && sharepointForm.drive_id">
                            <button type="button" class="btn btn-primary" :disabled="savingSharepoint" @click="saveSharePointConfig">
                                <span v-if="savingSharepoint" class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 mr-2 inline-block"></span>
                                {{ $t('tenant.sharepoint_save_config') }}
                            </button>
                        </div>

                        <!-- Current Config Display -->
                        <div v-if="(tenantStore.tenant as any)?.sharepoint_configured" class="p-3 bg-success/10 rounded-lg">
                            <p class="text-sm text-success">{{ $t('tenant.sharepoint_configured_msg') }}</p>
                            <p v-if="(tenantStore.tenant as any)?.sharepoint_site_url" class="text-xs text-gray-500 mt-1">{{ (tenantStore.tenant as any).sharepoint_site_url }}</p>
                        </div>
                    </div>
                </div>

                <!-- Help Text -->
                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <h6 class="font-semibold mb-2 dark:text-white-light">{{ $t('oauth.help_title') }}</h6>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1 list-disc list-inside">
                        <li>{{ $t('oauth.help_microsoft') }}</li>
                        <li>{{ $t('oauth.help_google') }}</li>
                        <li>{{ $t('oauth.help_redirect') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useI18n } from 'vue-i18n';
import { useTenantStore } from '@/stores/tenant';
import IconInfoCircle from '@/components/icon/icon-info-circle.vue';
import { useMeta } from '@/composables/use-meta';

const { t } = useI18n();
useMeta({ title: 'OAuth Settings' });
const tenantStore = useTenantStore();

const loading = ref(true);
const savingMicrosoft = ref(false);
const savingGoogle = ref(false);
const removingMicrosoft = ref(false);
const removingGoogle = ref(false);
const savingStorage = ref(false);
const connectingProvider = ref<string | null>(null);
let pollInterval: ReturnType<typeof setInterval> | null = null;

const storageForm = reactive({ storage_type: 'local' });
const microsoftForm = reactive({ client_id: '', client_secret: '' });
const googleForm = reactive({ client_id: '', client_secret: '' });

const savingBaseFolder = ref(false);
const savingSharepoint = ref(false);
const loadingSites = ref(false);
const loadingDrives = ref(false);
const sharepointSites = ref<any[]>([]);
const sharepointDrives = ref<any[]>([]);

const baseFolderForm = reactive({ base_folder_path: '' });
const sharepointForm = reactive({ site_id: '', drive_id: '', site_url: '' });

// Credential status (from TenantOAuthController — client_id/secret configured?)
const credentialStatus = ref<any>({
    microsoft: { configured: false, client_id: null },
    google: { configured: false, client_id: null },
    system_fallback: { microsoft_available: false, google_available: false },
});

// Connection status (from OAuthFlowController — tenant has active OAuth token?)
const connectionStatus = ref<any>({
    microsoft: { connected: false },
    google: { connected: false },
});

// ---- Data Loading ----

const fetchAll = async () => {
    try {
        const [credRes, connRes] = await Promise.all([
            axios.get('/api/tenant/oauth/status'),
            axios.get('/api/oauth/status'),
        ]);
        credentialStatus.value = credRes.data;
        connectionStatus.value = {
            microsoft: connRes.data.microsoft ?? { connected: false },
            google: connRes.data.google ?? { connected: false },
        };

        if (!tenantStore.isLoaded) {
            await tenantStore.fetchTenant();
        }
        if (tenantStore.tenant) {
            const t = tenantStore.tenant as any;
            storageForm.storage_type = t.storage_type ?? 'local';
            baseFolderForm.base_folder_path = t.base_folder_path ?? '';

            // Restore saved SharePoint config
            if (t.sharepoint_site_id) sharepointForm.site_id = t.sharepoint_site_id;
            if (t.sharepoint_drive_id) sharepointForm.drive_id = t.sharepoint_drive_id;
            if (t.sharepoint_site_url) sharepointForm.site_url = t.sharepoint_site_url;
        }
    } catch (error) {
        console.error('Failed to fetch OAuth status:', error);
    } finally {
        loading.value = false;
    }
};

// ---- Credential Management ----

const saveMicrosoft = async () => {
    savingMicrosoft.value = true;
    try {
        await axios.put('/api/tenant/oauth/microsoft', microsoftForm);
        await fetchAll();
        microsoftForm.client_id = '';
        microsoftForm.client_secret = '';
        Swal.fire({ icon: 'success', title: 'Success', text: 'Microsoft OAuth credentials saved.', timer: 2000, showConfirmButton: false });
    } catch (error: any) {
        Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.message || error.response?.data?.error || 'Failed to save credentials.' });
    } finally {
        savingMicrosoft.value = false;
    }
};

const saveGoogle = async () => {
    savingGoogle.value = true;
    try {
        await axios.put('/api/tenant/oauth/google', googleForm);
        await fetchAll();
        googleForm.client_id = '';
        googleForm.client_secret = '';
        Swal.fire({ icon: 'success', title: 'Success', text: 'Google OAuth credentials saved.', timer: 2000, showConfirmButton: false });
    } catch (error: any) {
        Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.message || error.response?.data?.error || 'Failed to save credentials.' });
    } finally {
        savingGoogle.value = false;
    }
};

const removeMicrosoft = async () => {
    const result = await Swal.fire({ icon: 'warning', title: 'Remove Microsoft OAuth?', text: 'This will remove credentials and disconnect OneDrive for the organization.', showCancelButton: true, confirmButtonText: 'Yes, remove', confirmButtonColor: '#e7515a' });
    if (!result.isConfirmed) return;
    removingMicrosoft.value = true;
    try {
        await axios.delete('/api/oauth/microsoft/disconnect').catch(() => {});
        await axios.delete('/api/tenant/oauth/microsoft');
        await fetchAll();
        Swal.fire({ icon: 'success', title: 'Removed', text: 'Microsoft credentials removed.', timer: 2000, showConfirmButton: false });
    } catch { Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to remove credentials.' }); }
    finally { removingMicrosoft.value = false; }
};

const removeGoogle = async () => {
    const result = await Swal.fire({ icon: 'warning', title: 'Remove Google OAuth?', text: 'This will remove credentials and disconnect Google Drive for the organization.', showCancelButton: true, confirmButtonText: 'Yes, remove', confirmButtonColor: '#e7515a' });
    if (!result.isConfirmed) return;
    removingGoogle.value = true;
    try {
        await axios.delete('/api/oauth/google/disconnect').catch(() => {});
        await axios.delete('/api/tenant/oauth/google');
        await fetchAll();
        Swal.fire({ icon: 'success', title: 'Removed', text: 'Google credentials removed.', timer: 2000, showConfirmButton: false });
    } catch { Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to remove credentials.' }); }
    finally { removingGoogle.value = false; }
};

// ---- OAuth Connect/Disconnect (tenant-level) ----

const connectProvider = async (provider: string) => {
    connectingProvider.value = provider;
    try {
        const response = await axios.get(`/api/oauth/${provider}/redirect`);
        const url = response.data.url;
        window.open(url, '_blank', 'width=600,height=700');

        // Poll for connection status
        pollInterval = setInterval(async () => {
            try {
                const connRes = await axios.get('/api/oauth/status');
                const providerStatus = provider === 'microsoft' ? connRes.data.microsoft : connRes.data.google;
                if (providerStatus?.connected) {
                    stopPolling();
                    connectingProvider.value = null;
                    connectionStatus.value = {
                        microsoft: connRes.data.microsoft ?? { connected: false },
                        google: connRes.data.google ?? { connected: false },
                    };
                    Swal.fire({ icon: 'success', title: t('oauth.connected'), timer: 2000, showConfirmButton: false });
                }
            } catch { /* continue polling */ }
        }, 3000);

        // Stop polling after 5 minutes
        setTimeout(() => { stopPolling(); connectingProvider.value = null; }, 300000);
    } catch (error: any) {
        Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.message || 'Failed to start OAuth flow.' });
        connectingProvider.value = null;
    }
};

const disconnectProvider = async (provider: string) => {
    const result = await Swal.fire({ icon: 'warning', title: t('oauth.disconnect_confirm'), text: t('oauth.disconnect_warning'), showCancelButton: true, confirmButtonText: t('oauth.disconnect'), confirmButtonColor: '#e7515a' });
    if (!result.isConfirmed) return;
    try {
        await axios.delete(`/api/oauth/${provider}/disconnect`);
        await fetchAll();
        Swal.fire({ icon: 'success', title: t('oauth.disconnected'), timer: 2000, showConfirmButton: false });
    } catch { Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to disconnect.' }); }
};

const stopPolling = () => {
    if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
};

// ---- Storage Type ----

const saveStorageType = async () => {
    if (storageForm.storage_type === 'local' && (tenantStore.tenant as any)?.storage_type && (tenantStore.tenant as any).storage_type !== 'local') {
        const confirm = await Swal.fire({ icon: 'warning', title: t('tenant.storage_type'), text: t('tenant.storage_warning'), showCancelButton: true, confirmButtonText: t('tenant.save_settings'), confirmButtonColor: '#e2a03f' });
        if (!confirm.isConfirmed) return;
    }
    savingStorage.value = true;
    try {
        await axios.put('/api/tenant/storage-type', { storage_type: storageForm.storage_type });
        await tenantStore.fetchTenant();
        Swal.fire({ icon: 'success', title: t('tenant.settings_saved'), timer: 2000, showConfirmButton: false });
    } catch (error: any) {
        Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.message || t('tenant.save_failed') });
    } finally { savingStorage.value = false; }
};

// ---- Base Folder ----

const saveBaseFolder = async () => {
    savingBaseFolder.value = true;
    try {
        await axios.put('/api/tenant/base-folder', { base_folder_path: baseFolderForm.base_folder_path || null });
        await tenantStore.fetchTenant();
        Swal.fire({ icon: 'success', title: t('tenant.settings_saved'), timer: 2000, showConfirmButton: false });
    } catch (error: any) {
        Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.message || t('tenant.save_failed') });
    } finally { savingBaseFolder.value = false; }
};

// ---- SharePoint Config ----

const fetchSharePointSites = async () => {
    loadingSites.value = true;
    try {
        const res = await axios.get('/api/tenant/sharepoint/sites');
        sharepointSites.value = res.data.data ?? [];
    } catch (error: any) {
        Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.message || 'Failed to fetch SharePoint sites.' });
    } finally { loadingSites.value = false; }
};

const onSiteSelected = async () => {
    sharepointForm.drive_id = '';
    sharepointDrives.value = [];
    const selectedSite = sharepointSites.value.find((s: any) => s.id === sharepointForm.site_id);
    sharepointForm.site_url = selectedSite?.webUrl ?? '';

    if (!sharepointForm.site_id) return;

    loadingDrives.value = true;
    try {
        const res = await axios.get(`/api/tenant/sharepoint/sites/${sharepointForm.site_id}/drives`);
        sharepointDrives.value = res.data.data ?? [];
    } catch (error: any) {
        Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.message || 'Failed to fetch drives.' });
    } finally { loadingDrives.value = false; }
};

const saveSharePointConfig = async () => {
    savingSharepoint.value = true;
    try {
        await axios.put('/api/tenant/sharepoint/config', {
            sharepoint_site_id: sharepointForm.site_id,
            sharepoint_drive_id: sharepointForm.drive_id,
            sharepoint_site_url: sharepointForm.site_url,
        });
        await tenantStore.fetchTenant();
        Swal.fire({ icon: 'success', title: t('tenant.settings_saved'), timer: 2000, showConfirmButton: false });
    } catch (error: any) {
        Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.message || t('tenant.save_failed') });
    } finally { savingSharepoint.value = false; }
};

onMounted(fetchAll);
onUnmounted(stopPolling);
</script>
