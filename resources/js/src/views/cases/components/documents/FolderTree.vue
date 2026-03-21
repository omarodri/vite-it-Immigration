<template>
    <div class="space-y-1">
        <!-- All Files -->
        <button
            type="button"
            class="flex items-center gap-2 w-full px-3 py-2 rounded-md text-sm font-medium transition-colors"
            :class="currentFolderId === null
                ? 'bg-primary/10 text-primary'
                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'"
            @click="$emit('select-folder', null)"
        >
            <icon-folder class="w-4 h-4 shrink-0" />
            {{ $t('documents.all_files') }}
        </button>

        <!-- Folder items -->
        <FolderTreeItem
            v-for="folder in folders"
            :key="folder.id"
            :folder="folder"
            :current-folder-id="currentFolderId"
            :depth="0"
            @select-folder="(id: number) => $emit('select-folder', id)"
            @rename-folder="(f: DocumentFolder) => $emit('rename-folder', f)"
            @delete-folder="(f: DocumentFolder) => $emit('delete-folder', f)"
        />
    </div>
</template>

<script lang="ts" setup>
import { useI18n } from 'vue-i18n';
import type { DocumentFolder } from '@/types/document';
import FolderTreeItem from './FolderTreeItem.vue';
import IconFolder from '@/components/icon/icon-folder.vue';

const { t } = useI18n();

defineProps<{
    folders: DocumentFolder[];
    currentFolderId: number | null;
}>();

defineEmits<{
    (e: 'select-folder', id: number | null): void;
    (e: 'create-folder'): void;
    (e: 'rename-folder', folder: DocumentFolder): void;
    (e: 'delete-folder', folder: DocumentFolder): void;
}>();
</script>
