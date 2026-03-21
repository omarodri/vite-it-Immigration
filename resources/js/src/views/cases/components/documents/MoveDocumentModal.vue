<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70"
            @click.self="$emit('close')"
        >
            <div class="relative w-full max-w-md mx-4 bg-white dark:bg-[#0e1726] rounded-lg shadow-xl overflow-hidden">
                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                        {{ $t('documents.move_document') }}
                    </h3>
                    <button
                        type="button"
                        class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                        @click="$emit('close')"
                    >
                        <icon-x class="w-5 h-5" />
                    </button>
                </div>

                <!-- Body -->
                <div class="px-5 py-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                        {{ $t('documents.move_description') }}
                    </p>

                    <!-- Document info -->
                    <div class="flex items-center gap-2 mb-4 p-2 rounded bg-gray-50 dark:bg-gray-800/50">
                        <icon-file class="w-5 h-5 text-gray-400 shrink-0" />
                        <span class="text-sm text-gray-700 dark:text-gray-300 truncate">{{ document?.original_name }}</span>
                    </div>

                    <!-- Folder list -->
                    <div class="border border-gray-200 dark:border-gray-700 rounded-md max-h-64 overflow-y-auto">
                        <!-- Root (All Files) -->
                        <button
                            type="button"
                            class="flex items-center gap-2 w-full px-3 py-2.5 text-sm transition-colors border-b border-gray-100 dark:border-gray-800"
                            :class="getFolderClass(null)"
                            :disabled="currentFolderId === null"
                            @click="selectTargetFolder(null)"
                        >
                            <icon-folder class="w-4 h-4 shrink-0" />
                            <span class="font-medium">{{ $t('documents.all_files') }}</span>
                            <span
                                v-if="currentFolderId === null"
                                class="ml-auto text-xs text-gray-400"
                            >
                                ({{ $t('documents.current_location') }})
                            </span>
                        </button>

                        <!-- Flat folder rows -->
                        <button
                            v-for="folder in flatFolders"
                            :key="folder.id"
                            type="button"
                            class="flex items-center gap-2 w-full px-3 py-2.5 text-sm transition-colors border-b border-gray-100 dark:border-gray-800 last:border-b-0"
                            :class="getFolderClass(folder.id)"
                            :disabled="folder.id === currentFolderId"
                            :style="{ paddingLeft: `${(folder._depth ?? 0) * 16 + 12}px` }"
                            @click="selectTargetFolder(folder.id)"
                        >
                            <icon-folder class="w-4 h-4 shrink-0" />
                            <span class="truncate">{{ folder.name }}</span>
                            <span
                                v-if="folder.id === currentFolderId"
                                class="ml-auto text-xs text-gray-400 shrink-0"
                            >
                                ({{ $t('documents.current_location') }})
                            </span>
                        </button>

                        <!-- Empty state -->
                        <div
                            v-if="flatFolders.length === 0"
                            class="p-4 text-center text-sm text-gray-400"
                        >
                            {{ $t('documents.select_folder') }}
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-end gap-3 px-5 py-4 border-t border-gray-200 dark:border-gray-700">
                    <button
                        type="button"
                        class="btn btn-outline-dark btn-sm"
                        @click="$emit('close')"
                    >
                        {{ $t('todo_cancel') }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        :disabled="!canMove"
                        @click="confirmMove"
                    >
                        <icon-arrow-forward class="w-4 h-4 ltr:mr-1.5 rtl:ml-1.5" />
                        {{ $t('documents.move') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script lang="ts" setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Document, DocumentFolder } from '@/types/document';
import IconX from '@/components/icon/icon-x.vue';
import IconFile from '@/components/icon/icon-file.vue';
import IconFolder from '@/components/icon/icon-folder.vue';
import IconArrowForward from '@/components/icon/icon-arrow-forward.vue';

interface FlatFolder extends DocumentFolder {
    _depth: number;
}

const { t } = useI18n();

const props = defineProps<{
    show: boolean;
    document: Document | null;
    folders: DocumentFolder[];
    currentFolderId: number | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'confirm', targetFolderId: number | null): void;
}>();

const selectedFolderId = ref<number | null | undefined>(undefined);

// Reset selection when modal opens
watch(() => props.show, (val) => {
    if (val) {
        selectedFolderId.value = undefined;
    }
});

const flatFolders = computed((): FlatFolder[] => {
    const result: FlatFolder[] = [];
    function flatten(folders: DocumentFolder[], depth: number) {
        for (const f of folders) {
            result.push({ ...f, _depth: depth });
            if (f.children && f.children.length > 0) {
                flatten(f.children, depth + 1);
            }
        }
    }
    flatten(props.folders, 0);
    return result;
});

const canMove = computed(() => {
    return selectedFolderId.value !== undefined && selectedFolderId.value !== props.currentFolderId;
});

function selectTargetFolder(id: number | null) {
    if (id === props.currentFolderId) return;
    selectedFolderId.value = id;
}

function getFolderClass(folderId: number | null): string {
    if (folderId === props.currentFolderId) {
        return 'text-gray-400 cursor-not-allowed bg-gray-50 dark:bg-gray-800/30';
    }
    if (selectedFolderId.value === folderId) {
        return 'bg-primary/10 text-primary font-medium';
    }
    return 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer';
}

function confirmMove() {
    if (canMove.value) {
        emit('confirm', selectedFolderId.value as number | null);
    }
}
</script>
