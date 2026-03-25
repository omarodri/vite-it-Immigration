<template>
    <div class="flex flex-col lg:flex-row gap-4">
        <!-- Sidebar: Folder Tree -->
        <div class="lg:w-64 shrink-0">
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 bg-white dark:bg-[#0e1726]">
                <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3 px-2">
                    {{ $t('documents.title') }}
                </h3>
                <FolderTree
                    :folders="folders"
                    :current-folder-id="currentFolderId"
                    :show-sync-status="isCloudStorage"
                    @select-folder="selectFolder"
                    @create-folder="showNewFolderDialog"
                    @rename-folder="showRenameFolderDialog"
                    @delete-folder="confirmDeleteFolder"
                />
            </div>
        </div>

        <!-- Main Area -->
        <div class="flex-1 min-w-0">
            <DocumentToolbar
                :current-folder="currentFolder"
                :view-mode="viewMode"
                :is-syncing="syncStatus === 'syncing'"
                @upload-click="uploadDropzoneRef?.triggerFileInput()"
                @create-folder="showNewFolderDialog"
                @toggle-view="(m: 'grid' | 'list') => documentStore.setViewMode(m)"
                @refresh="refreshAll"
                @navigate-root="selectFolder(null)"
                @sync="handleSync"
            />

            <!-- Sync Status Banners -->
            <div
                v-if="isCloudStorage && (hasPendingSync || syncStatus === 'syncing')"
                class="flex items-center gap-2 px-4 py-2.5 mb-3 rounded-md bg-warning/10 border border-warning/20 text-warning text-sm"
            >
                <icon-clock class="w-4 h-4 shrink-0 animate-pulse" />
                <span>{{ $t('documents.sync_pending', { provider: storageProvider }) }}</span>
            </div>
            <div
                v-else-if="isCloudStorage && (hasFailedSync || syncStatus === 'error')"
                class="flex items-center justify-between gap-2 px-4 py-2.5 mb-3 rounded-md bg-danger/10 border border-danger/20 text-danger text-sm"
            >
                <div class="flex items-center gap-2">
                    <icon-x-circle class="w-4 h-4 shrink-0" />
                    <span>{{ $t('documents.sync_failed') }}</span>
                </div>
                <button
                    type="button"
                    class="btn btn-outline-danger btn-sm py-1 px-2 text-xs"
                    @click="handleSync"
                >
                    {{ $t('documents.sync_retry') }}
                </button>
            </div>

            <DocumentGrid
                :documents="documents"
                :view-mode="viewMode"
                :is-loading="isLoadingDocs"
                @download="handleDownload"
                @rename="showRenameDocDialog"
                @move="showMoveDocDialog"
                @replace="handleReplace"
                @delete="confirmDeleteDoc"
                @preview="handlePreview"
            />

            <UploadDropzone
                ref="uploadDropzoneRef"
                :case-id="caseId"
                :folder-id="currentFolderId"
                @upload-complete="onUploadComplete"
            />
        </div>

        <!-- Replace Document Modal -->
        <ReplaceDocumentModal
            :show="showReplaceModal"
            :document="replaceDoc"
            :file="replaceFile"
            @close="closeReplaceModal"
            @confirm="confirmReplace"
        />

        <!-- Move Document Modal -->
        <MoveDocumentModal
            :show="showMoveModal"
            :document="moveDoc"
            :folders="folders"
            :current-folder-id="moveDoc?.folder_id ?? null"
            @close="closeMoveModal"
            @confirm="confirmMove"
        />

        <!-- Preview Modal -->
        <Teleport to="body">
            <div
                v-if="previewDoc"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/70"
                @click.self="closePreview"
            >
                <div class="relative w-full max-w-5xl max-h-[90vh] mx-4 bg-white dark:bg-[#0e1726] rounded-lg shadow-xl overflow-hidden flex flex-col">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">{{ previewDoc.original_name }}</h3>
                        <div class="flex items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary gap-1" @click="handleDownload(previewDoc!)">
                                <icon-download class="w-4 h-4" />
                                {{ $t('documents.download') }}
                            </button>
                            <button type="button" class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" @click="closePreview">
                                <icon-x class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                    <!-- Content -->
                    <div class="flex-1 overflow-auto flex items-center justify-center p-4 min-h-[400px]">
                        <div v-if="!previewBlobUrl" class="flex flex-col items-center gap-2 text-gray-400">
                            <svg class="animate-spin h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <span class="text-sm">{{ $t('loading') }}...</span>
                        </div>
                        <img
                            v-else-if="previewDoc.mime_type?.startsWith('image/')"
                            :src="previewBlobUrl"
                            :alt="previewDoc.original_name"
                            class="max-w-full max-h-[75vh] object-contain"
                        />
                        <iframe
                            v-else-if="previewDoc.mime_type === 'application/pdf'"
                            :src="previewBlobUrl"
                            class="w-full h-[75vh] border-0"
                        ></iframe>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { storeToRefs } from 'pinia';
import Swal from 'sweetalert2';
import type { DocumentFolder, Document } from '@/types/document';
import documentService from '@/services/documentService';
import { useDocumentStore } from '@/stores/documentStore';
import { useTenantStore } from '@/stores/tenant';

import FolderTree from './documents/FolderTree.vue';
import DocumentToolbar from './documents/DocumentToolbar.vue';
import DocumentGrid from './documents/DocumentGrid.vue';
import UploadDropzone from './documents/UploadDropzone.vue';
import ReplaceDocumentModal from './documents/ReplaceDocumentModal.vue';
import MoveDocumentModal from './documents/MoveDocumentModal.vue';
import IconDownload from '@/components/icon/icon-download.vue';
import IconX from '@/components/icon/icon-x.vue';
import IconClock from '@/components/icon/icon-clock.vue';
import IconXCircle from '@/components/icon/icon-x-circle.vue';

const { t } = useI18n();
const documentStore = useDocumentStore();
const tenantStore = useTenantStore();
const { folders, documents, currentFolderId, isLoading: isLoadingDocs, viewMode, syncStatus } = storeToRefs(documentStore);

const isCloudStorage = computed(() => tenantStore.isCloudStorage);
const storageProvider = computed(() => {
    const type = tenantStore.storageType;
    if (type === 'onedrive') return t('documents.provider_onedrive');
    if (type === 'google_drive') return t('documents.provider_google_drive');
    return t('documents.provider_local');
});
const hasPendingSync = computed(() => documentStore.hasPendingSyncFolders);
const hasFailedSync = computed(() => documentStore.hasFailedSyncFolders);

const props = defineProps<{
    caseId: number;
}>();

const uploadDropzoneRef = ref<InstanceType<typeof UploadDropzone> | null>(null);
const previewDoc = ref<Document | null>(null);
const previewBlobUrl = ref('');
const isBusy = ref(false); // Guard against double-submit

const currentFolder = computed(() => documentStore.currentFolder);

// Replace modal state
const showReplaceModal = ref(false);
const replaceDoc = ref<Document | null>(null);
const replaceFile = ref<File | null>(null);

// Move modal state
const showMoveModal = ref(false);
const moveDoc = ref<Document | null>(null);

// ---- Data Loading ----

async function fetchFolders() {
    await documentStore.fetchFolders(props.caseId);
}

async function fetchDocuments() {
    await documentStore.fetchDocuments(props.caseId, currentFolderId.value);
}

async function refreshAll() {
    await Promise.all([fetchFolders(), fetchDocuments()]);

    // Auto-pull cloud folders and documents on refresh
    if (isCloudStorage.value && !isSyncing.value) {
        isSyncing.value = true;
        try {
            const result = await documentService.syncFromCloud(props.caseId);
            const hasChanges = result.folders_added > 0 || result.folders_removed > 0
                || result.documents_added > 0 || result.documents_removed > 0;
            if (hasChanges) {
                await Promise.all([fetchFolders(), fetchDocuments()]);
            }
        } catch {
            // Silent fail — cloud sync is best-effort on refresh
        } finally {
            isSyncing.value = false;
        }
    }
}

function selectFolder(id: number | null) {
    documentStore.setCurrentFolder(id);
}

watch(currentFolderId, () => {
    fetchDocuments();
});

onMounted(async () => {
    await Promise.all([fetchFolders(), fetchDocuments()]);

    // Auto-initialize folders if the case has none
    if (folders.value.length === 0) {
        try {
            const result = await documentService.initializeFolders(props.caseId);
            if (result.folders_count > 0) {
                await fetchFolders();
            }
        } catch {
            // Silent fail — initialization is best-effort
        }
    }

    // If tenant uses cloud storage, check folder sync status
    if (tenantStore.isCloudStorage) {
        await documentStore.fetchSyncStatus(props.caseId);
    }
});

onUnmounted(() => {
    documentStore.reset();
});

// ---- Cloud Sync ----

const isSyncing = ref(false);

async function handleSync() {
    if (isSyncing.value) return;
    isSyncing.value = true;
    try {
        await documentStore.syncFolders(props.caseId);

        // Pull folders and documents from cloud, prune deleted items
        try {
            const result = await documentService.syncFromCloud(props.caseId);
            const hasChanges = result.folders_added > 0 || result.folders_removed > 0
                || result.documents_added > 0 || result.documents_removed > 0;
            if (hasChanges) {
                await Promise.all([fetchFolders(), fetchDocuments()]);
                showMessage(result.message);
                return;
            }
        } catch {
            // Cloud sync is best-effort
        }

        if (documentStore.syncStatus === 'synced') {
            showMessage(t('documents.sync_success'));
        }
    } finally {
        isSyncing.value = false;
    }
}

// ---- Upload ----

function onUploadComplete() {
    showMessage(t('documents.upload_success'));
    refreshAll();
}

// ---- Preview ----

async function handlePreview(doc: Document) {
    previewDoc.value = doc;
    previewBlobUrl.value = '';

    try {
        const blob = await documentService.getPreviewBlob(props.caseId, doc.id);
        const typedBlob = new Blob([blob], { type: doc.mime_type || 'application/octet-stream' });
        previewBlobUrl.value = URL.createObjectURL(typedBlob);
    } catch {
        previewBlobUrl.value = '';
    }
}

function closePreview() {
    if (previewBlobUrl.value) {
        URL.revokeObjectURL(previewBlobUrl.value);
        previewBlobUrl.value = '';
    }
    previewDoc.value = null;
}

// ---- Folder Actions ----

async function showNewFolderDialog() {
    if (isBusy.value) return;

    const { value: name } = await Swal.fire({
        title: t('documents.new_folder'),
        input: 'text',
        inputLabel: t('documents.folder_name'),
        inputPlaceholder: t('documents.folder_name'),
        showCancelButton: true,
        confirmButtonText: t('add'),
        cancelButtonText: t('todo_cancel'),
        inputValidator: (value) => {
            if (!value || !value.trim()) return t('documents.folder_name');
            return null;
        },
    });

    if (name) {
        isBusy.value = true;
        try {
            await documentService.createFolder(props.caseId, {
                name: name.trim(),
                parent_id: currentFolderId.value ?? undefined,
            });
            showMessage(t('documents.folder_created'));
            await fetchFolders();
        } catch {
            // Error handled by api interceptor
        } finally {
            isBusy.value = false;
        }
    }
}

async function showRenameFolderDialog(folder: DocumentFolder) {
    if (isBusy.value) return;

    const { value: name } = await Swal.fire({
        title: t('documents.rename'),
        input: 'text',
        inputLabel: t('documents.folder_name'),
        inputValue: folder.name,
        showCancelButton: true,
        confirmButtonText: t('todo_update'),
        cancelButtonText: t('todo_cancel'),
        inputValidator: (value) => {
            if (!value || !value.trim()) return t('documents.folder_name');
            return null;
        },
    });

    if (name && name.trim() !== folder.name) {
        isBusy.value = true;
        try {
            await documentService.renameFolder(props.caseId, folder.id, name.trim());
            showMessage(t('documents.rename'));
            await fetchFolders();
        } catch {
            // Error handled by api interceptor
        } finally {
            isBusy.value = false;
        }
    }
}

async function confirmDeleteFolder(folder: DocumentFolder) {
    if (isBusy.value) return;

    if (folder.documents_count > 0 || (folder.children && folder.children.length > 0)) {
        showMessage(t('documents.folder_not_empty'), 'error');
        return;
    }

    const result = await Swal.fire({
        title: t('documents.delete'),
        text: t('documents.delete_confirm'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e7515a',
        confirmButtonText: t('documents.delete'),
        cancelButtonText: t('todo_cancel'),
    });

    if (result.isConfirmed) {
        isBusy.value = true;
        try {
            await documentService.deleteFolder(props.caseId, folder.id);
            if (currentFolderId.value === folder.id) {
                documentStore.setCurrentFolder(null);
            }
            showMessage(t('documents.folder_deleted'));
            await fetchFolders();
        } catch {
            // Error handled by api interceptor
        } finally {
            isBusy.value = false;
        }
    }
}

// ---- Document Actions ----

async function handleDownload(doc: Document) {
    try {
        await documentService.downloadDocument(props.caseId, doc.id);
    } catch {
        // Error handled by api interceptor
    }
}

async function showRenameDocDialog(doc: Document) {
    const { value: name } = await Swal.fire({
        title: t('documents.rename_file'),
        input: 'text',
        inputLabel: t('documents.rename_file'),
        inputValue: doc.original_name,
        showCancelButton: true,
        confirmButtonText: t('todo_update'),
        cancelButtonText: t('todo_cancel'),
        inputValidator: (value) => {
            if (!value || !value.trim()) return t('documents.rename_file');
            return null;
        },
    });

    if (name && name.trim() !== doc.original_name) {
        try {
            await documentService.updateDocument(props.caseId, doc.id, { original_name: name.trim() });
            showMessage(t('documents.rename'));
            await fetchDocuments();
        } catch {
            // Error handled by api interceptor
        }
    }
}

function showMoveDocDialog(doc: Document) {
    moveDoc.value = doc;
    showMoveModal.value = true;
}

function closeMoveModal() {
    showMoveModal.value = false;
    moveDoc.value = null;
}

async function confirmMove(targetFolderId: number | null) {
    if (!moveDoc.value) return;
    try {
        await documentService.moveDocument(props.caseId, moveDoc.value.id, targetFolderId as number);
        showMessage(t('documents.move'));
        closeMoveModal();
        await refreshAll();
    } catch {
        // Error handled by api interceptor
    }
}

function handleReplace(doc: Document) {
    // Open a file picker first, then show the confirmation modal
    const input = document.createElement('input');
    input.type = 'file';
    input.onchange = () => {
        const file = input.files?.[0];
        if (!file) return;
        replaceDoc.value = doc;
        replaceFile.value = file;
        showReplaceModal.value = true;
    };
    input.click();
}

function closeReplaceModal() {
    showReplaceModal.value = false;
    replaceDoc.value = null;
    replaceFile.value = null;
}

async function confirmReplace(file: File) {
    if (!replaceDoc.value) return;
    try {
        await documentService.replaceDocument(props.caseId, replaceDoc.value.id, file);
        showMessage(t('documents.upload_success'));
        closeReplaceModal();
        await fetchDocuments();
    } catch {
        // Error handled by api interceptor
    }
}

async function confirmDeleteDoc(doc: Document) {
    const result = await Swal.fire({
        title: t('documents.delete'),
        text: t('documents.delete_confirm'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e7515a',
        confirmButtonText: t('documents.delete'),
        cancelButtonText: t('todo_cancel'),
    });

    if (result.isConfirmed) {
        try {
            await documentService.deleteDocument(props.caseId, doc.id);
            showMessage(t('documents.delete_success'));
            await refreshAll();
        } catch {
            // Error handled by api interceptor
        }
    }
}

// ---- Helpers ----

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
</script>
