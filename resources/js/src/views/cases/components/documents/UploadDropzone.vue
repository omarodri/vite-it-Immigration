<template>
    <div>
        <!-- Hidden file input -->
        <input
            ref="fileInputRef"
            type="file"
            multiple
            class="hidden"
            @change="onFileSelect"
        />

        <!-- Drop overlay (shown on drag) -->
        <Teleport to="body">
            <div
                v-if="isDragging"
                class="fixed inset-0 z-50 bg-primary/10 border-4 border-dashed border-primary flex items-center justify-center pointer-events-none"
            >
                <div class="bg-white dark:bg-[#1b2e4b] rounded-xl p-8 shadow-xl text-center">
                    <icon-cloud-download class="w-16 h-16 mx-auto text-primary mb-4" />
                    <p class="text-lg font-semibold text-gray-700 dark:text-gray-300">
                        {{ $t('documents.drop_files') }}
                    </p>
                </div>
            </div>
        </Teleport>

        <!-- Upload progress panel -->
        <div v-if="uploadQueue.length > 0" class="mt-4 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
            <div class="bg-gray-50 dark:bg-[#1b2e4b] px-4 py-2 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ $t('documents.uploading') }} ({{ completedCount }}/{{ uploadQueue.length }})
                </span>
                <button
                    v-if="allDone"
                    type="button"
                    class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    @click="clearQueue"
                >
                    &times;
                </button>
            </div>
            <div class="max-h-48 overflow-y-auto">
                <div
                    v-for="(item, index) in uploadQueue"
                    :key="index"
                    class="flex items-center gap-3 px-4 py-2 border-b border-gray-100 dark:border-gray-800 last:border-0"
                >
                    <icon-file class="w-4 h-4 shrink-0 text-gray-400" />
                    <div class="flex-1 min-w-0">
                        <p class="text-sm truncate text-gray-700 dark:text-gray-300">{{ item.file.name }}</p>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 mt-1">
                            <div
                                class="h-1.5 rounded-full transition-all"
                                :class="{
                                    'bg-primary': item.status === 'uploading',
                                    'bg-success': item.status === 'done',
                                    'bg-danger': item.status === 'error',
                                    'bg-gray-300': item.status === 'pending',
                                }"
                                :style="{ width: `${item.status === 'error' ? 100 : item.progress}%` }"
                            ></div>
                        </div>
                        <p v-if="item.status === 'error' && item.error" class="text-xs text-danger mt-0.5 truncate">
                            {{ item.error }}
                        </p>
                    </div>
                    <span class="text-xs shrink-0" :class="{
                        'text-gray-400': item.status === 'pending',
                        'text-primary': item.status === 'uploading',
                        'text-success': item.status === 'done',
                        'text-danger': item.status === 'error',
                    }">
                        <template v-if="item.status === 'uploading'">{{ item.progress }}%</template>
                        <template v-else-if="item.status === 'done'">&#10003;</template>
                        <template v-else-if="item.status === 'error'" :title="item.error">&#10007;</template>
                        <template v-else>&bull;</template>
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import type { UploadItem } from '@/types/document';
import documentService from '@/services/documentService';

import IconCloudDownload from '@/components/icon/icon-cloud-download.vue';
import IconFile from '@/components/icon/icon-file.vue';

const { t } = useI18n();

const props = defineProps<{
    caseId: number;
    folderId: number | null;
}>();

const emit = defineEmits<{
    (e: 'upload-complete'): void;
}>();

const fileInputRef = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const isUploading = ref(false);
const uploadQueue = ref<UploadItem[]>([]);

const completedCount = computed(() => uploadQueue.value.filter(i => i.status === 'done' || i.status === 'error').length);
const allDone = computed(() => uploadQueue.value.length > 0 && uploadQueue.value.every(i => i.status === 'done' || i.status === 'error'));

let dragCounter = 0;

function onDragEnter(e: DragEvent) {
    e.preventDefault();
    dragCounter++;
    if (e.dataTransfer?.types.includes('Files')) {
        isDragging.value = true;
    }
}

function onDragLeave(e: DragEvent) {
    e.preventDefault();
    dragCounter--;
    if (dragCounter <= 0) {
        isDragging.value = false;
        dragCounter = 0;
    }
}

function onDragOver(e: DragEvent) {
    e.preventDefault();
}

function onDrop(e: DragEvent) {
    e.preventDefault();
    isDragging.value = false;
    dragCounter = 0;
    const files = e.dataTransfer?.files;
    if (files && files.length > 0) {
        processFiles(Array.from(files));
    }
}

function onFileSelect(e: Event) {
    const input = e.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
        processFiles(Array.from(input.files));
        input.value = '';
    }
}

function triggerFileInput() {
    fileInputRef.value?.click();
}

async function processFiles(files: File[]) {
    if (isUploading.value) return;
    isUploading.value = true;

    const startIndex = uploadQueue.value.length;
    const newItems: UploadItem[] = files.map(file => ({
        file,
        progress: 0,
        status: 'pending',
    }));
    uploadQueue.value.push(...newItems);

    // Use reactive references from the queue (not the local plain objects)
    const reactiveItems = uploadQueue.value.slice(startIndex);
    let hasSuccess = false;

    for (const item of reactiveItems) {
        item.status = 'uploading';
        try {
            await documentService.uploadDocument(
                props.caseId,
                item.file,
                { folder_id: props.folderId ?? undefined },
                (progress: number) => {
                    item.progress = progress;
                }
            );
            item.status = 'done';
            item.progress = 100;
            hasSuccess = true;
        } catch (err: any) {
            item.status = 'error';
            item.progress = 0;
            const validationErrors = err.response?.data?.errors;
            if (validationErrors) {
                // Flatten validation errors (e.g. { file: ['Allowed file types: ...'] })
                item.error = Object.values(validationErrors).flat().join('. ');
            } else {
                item.error = err.response?.data?.message || 'Upload failed';
            }
        }
    }

    if (hasSuccess) {
        emit('upload-complete');
    }

    isUploading.value = false;

    // Auto-clear successful items after a delay; keep errors visible longer
    const hasErrors = reactiveItems.some(i => i.status === 'error');
    setTimeout(() => {
        clearQueue();
    }, hasErrors ? 5000 : 2000);
}

function clearQueue() {
    uploadQueue.value = [];
}

onMounted(() => {
    document.addEventListener('dragenter', onDragEnter);
    document.addEventListener('dragleave', onDragLeave);
    document.addEventListener('dragover', onDragOver);
    document.addEventListener('drop', onDrop);
});

onUnmounted(() => {
    document.removeEventListener('dragenter', onDragEnter);
    document.removeEventListener('dragleave', onDragLeave);
    document.removeEventListener('dragover', onDragOver);
    document.removeEventListener('drop', onDrop);
});

defineExpose({ triggerFileInput });
</script>
