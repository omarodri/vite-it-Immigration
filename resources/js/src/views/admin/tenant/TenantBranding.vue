<template>
    <div>
        <ul class="flex space-x-2 rtl:space-x-reverse mb-6">
            <li>
                <router-link to="/admin" class="text-primary hover:underline">{{ $t('sidebar.admin') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('tenant.branding_title') }}</span>
            </li>
        </ul>

        <div class="panel">
            <div class="mb-5">
                <h5 class="text-lg font-semibold dark:text-white-light">{{ $t('tenant.branding_title') }}</h5>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ $t('tenant.branding_description') }}
                </p>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-10">
                <div class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10"></div>
            </div>

            <div v-else class="space-y-8">
                <!-- Logo Section -->
                <div class="border border-[#e0e6ed] dark:border-[#1b2e4b] rounded-lg p-5">
                    <h6 class="font-semibold mb-4 dark:text-white-light">{{ $t('tenant.logo') }}</h6>

                    <div class="flex items-start gap-6">
                        <!-- Logo Preview -->
                        <div class="w-32 h-32 border-2 border-dashed border-[#e0e6ed] dark:border-[#1b2e4b] rounded-lg flex items-center justify-center overflow-hidden bg-gray-50 dark:bg-gray-800 shrink-0">
                            <img
                                v-if="logoPreview || currentLogoUrl"
                                :src="(logoPreview || currentLogoUrl)!"
                                alt="Company Logo"
                                class="max-w-full max-h-full object-contain"
                            />
                            <div v-else class="text-center text-gray-400">
                                <svg class="w-10 h-10 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span class="text-xs">No logo</span>
                            </div>
                        </div>

                        <!-- Upload Controls -->
                        <div class="space-y-3">
                            <div>
                                <input
                                    ref="fileInput"
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="onFileSelected"
                                />
                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    @click="($refs.fileInput as HTMLInputElement).click()"
                                    :disabled="uploadingLogo"
                                >
                                    <span v-if="uploadingLogo" class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 mr-2 inline-block"></span>
                                    {{ $t('tenant.upload_logo') }}
                                </button>
                                <button
                                    v-if="currentLogoUrl"
                                    type="button"
                                    class="btn btn-outline-danger ml-2"
                                    @click="deleteLogo"
                                    :disabled="deletingLogo"
                                >
                                    {{ $t('tenant.delete_logo') }}
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $t('tenant.logo_hint') }}
                            </p>

                            <!-- Selected file preview / upload confirm -->
                            <div v-if="selectedFile" class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <span class="text-sm text-gray-600 dark:text-gray-300">{{ selectedFile.name }}</span>
                                <span class="text-xs text-gray-400">({{ formatFileSize(selectedFile.size) }})</span>
                                <button type="button" class="btn btn-sm btn-success" @click="uploadLogo" :disabled="uploadingLogo">
                                    <span v-if="uploadingLogo" class="animate-spin border-2 border-white border-l-transparent rounded-full w-3 h-3 mr-1 inline-block"></span>
                                    {{ $t('save') }}
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-dark" @click="cancelFileSelection">
                                    {{ $t('cancel') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Colors Section -->
                <div class="border border-[#e0e6ed] dark:border-[#1b2e4b] rounded-lg p-5">
                    <h6 class="font-semibold mb-4 dark:text-white-light">{{ $t('tenant.colors_title') }}</h6>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Primary Color -->
                        <div>
                            <label class="block text-sm font-medium mb-2">{{ $t('tenant.primary_color') }}</label>
                            <div class="flex items-center gap-3">
                                <input
                                    type="color"
                                    v-model="colorForm.primary_color"
                                    class="w-12 h-10 rounded border border-[#e0e6ed] dark:border-[#1b2e4b] cursor-pointer"
                                    @input="previewColors"
                                />
                                <input
                                    type="text"
                                    v-model="colorForm.primary_color"
                                    class="form-input w-32"
                                    maxlength="7"
                                    @input="previewColors"
                                />
                                <div
                                    class="w-10 h-10 rounded-lg border border-[#e0e6ed] dark:border-[#1b2e4b]"
                                    :style="{ backgroundColor: colorForm.primary_color }"
                                ></div>
                            </div>
                        </div>

                        <!-- Secondary Color -->
                        <div>
                            <label class="block text-sm font-medium mb-2">{{ $t('tenant.secondary_color') }}</label>
                            <div class="flex items-center gap-3">
                                <input
                                    type="color"
                                    v-model="colorForm.secondary_color"
                                    class="w-12 h-10 rounded border border-[#e0e6ed] dark:border-[#1b2e4b] cursor-pointer"
                                    @input="previewColors"
                                />
                                <input
                                    type="text"
                                    v-model="colorForm.secondary_color"
                                    class="form-input w-32"
                                    maxlength="7"
                                    @input="previewColors"
                                />
                                <div
                                    class="w-10 h-10 rounded-lg border border-[#e0e6ed] dark:border-[#1b2e4b]"
                                    :style="{ backgroundColor: colorForm.secondary_color }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <!-- Color Preview Bar -->
                    <div class="mt-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <p class="text-sm font-medium mb-2">{{ $t('tenant.color_preview') }}</p>
                        <div class="flex gap-3">
                            <button class="btn text-white" :style="{ backgroundColor: colorForm.primary_color }">Primary Button</button>
                            <button class="btn text-white" :style="{ backgroundColor: colorForm.secondary_color }">Secondary Button</button>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="savingColors"
                            @click="saveColors"
                        >
                            <span v-if="savingColors" class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 mr-2 inline-block"></span>
                            {{ $t('tenant.save_settings') }}
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { useI18n } from 'vue-i18n';
import { useTenantStore } from '@/stores/tenant';
import { useMeta } from '@/composables/use-meta';

const { t } = useI18n();
useMeta({ title: 'Branding & Appearance' });

const tenantStore = useTenantStore();
const loading = ref(true);
const uploadingLogo = ref(false);
const deletingLogo = ref(false);
const savingColors = ref(false);


const currentLogoUrl = ref<string | null>(null);
const logoPreview = ref<string | null>(null);
const selectedFile = ref<File | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const colorForm = reactive({
    primary_color: '#4361ee',
    secondary_color: '#805dca',
});

const loadData = async () => {
    try {
        if (!tenantStore.isLoaded) {
            await tenantStore.fetchTenant();
        }
        if (tenantStore.tenant) {
            currentLogoUrl.value = tenantStore.tenant.branding?.logo_url ?? null;
            colorForm.primary_color = tenantStore.tenant.branding?.primary_color ?? '#4361ee';
            colorForm.secondary_color = tenantStore.tenant.branding?.secondary_color ?? '#805dca';
}
    } catch (error) {
        console.error('Failed to load tenant data:', error);
    } finally {
        loading.value = false;
    }
};

const onFileSelected = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    // Validate file size (max 2MB)
    if (file.size > 2 * 1024 * 1024) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'File size must be less than 2MB.',
        });
        input.value = '';
        return;
    }

    // Validate file type
    if (!file.type.startsWith('image/')) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Only image files are accepted.',
        });
        input.value = '';
        return;
    }

    selectedFile.value = file;

    // Generate preview
    const reader = new FileReader();
    reader.onload = (e) => {
        logoPreview.value = e.target?.result as string;
    };
    reader.readAsDataURL(file);
};

const cancelFileSelection = () => {
    selectedFile.value = null;
    logoPreview.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const uploadLogo = async () => {
    if (!selectedFile.value) return;

    uploadingLogo.value = true;
    try {
        const formData = new FormData();
        formData.append('logo', selectedFile.value);

        const response = await axios.post('/api/tenant/branding/logo', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        currentLogoUrl.value = response.data.logo_url ?? response.data.data?.branding?.logo_url ?? null;
        cancelFileSelection();

        // Refresh tenant store
        await tenantStore.fetchTenant();

        Swal.fire({
            icon: 'success',
            title: t('tenant.logo_uploaded'),
            timer: 2000,
            showConfirmButton: false,
        });
    } catch (error: any) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.response?.data?.message || 'Failed to upload logo.',
        });
    } finally {
        uploadingLogo.value = false;
    }
};

const deleteLogo = async () => {
    const result = await Swal.fire({
        icon: 'warning',
        title: t('tenant.delete_logo') + '?',
        showCancelButton: true,
        confirmButtonText: t('tenant.delete_logo'),
        confirmButtonColor: '#e7515a',
    });

    if (!result.isConfirmed) return;

    deletingLogo.value = true;
    try {
        await axios.delete('/api/tenant/branding/logo');
        currentLogoUrl.value = null;
        logoPreview.value = null;

        // Refresh tenant store
        await tenantStore.fetchTenant();

        Swal.fire({
            icon: 'success',
            title: t('tenant.logo_deleted'),
            timer: 2000,
            showConfirmButton: false,
        });
    } catch (error: any) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.response?.data?.message || 'Failed to delete logo.',
        });
    } finally {
        deletingLogo.value = false;
    }
};

const previewColors = () => {
    const root = document.documentElement;
    root.style.setProperty('--tenant-primary', colorForm.primary_color);
    root.style.setProperty('--tenant-secondary', colorForm.secondary_color);
};

const saveColors = async () => {
    savingColors.value = true;
    try {
        const result = await tenantStore.updateBranding({
            primary_color: colorForm.primary_color,
            secondary_color: colorForm.secondary_color,
        });

        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: t('tenant.settings_saved'),
                timer: 2000,
                showConfirmButton: false,
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: result.error || t('tenant.save_failed'),
            });
        }
    } catch (error: any) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.response?.data?.message || t('tenant.save_failed'),
        });
    } finally {
        savingColors.value = false;
    }
};

const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

onMounted(loadData);
</script>
