<template>
    <div>
        <div
            class="flex items-center gap-1 w-full rounded-md text-sm transition-colors group"
            :class="currentFolderId === folder.id
                ? 'bg-primary/10 text-primary'
                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'"
            :style="{ paddingLeft: `${depth * 16 + 8}px` }"
        >
            <!-- Expand toggle -->
            <button
                v-if="folder.children && folder.children.length > 0"
                type="button"
                class="p-1 shrink-0"
                @click.stop="isExpanded = !isExpanded"
            >
                <icon-caret-down
                    class="w-3 h-3 transition-transform"
                    :class="{ '-rotate-90': !isExpanded }"
                />
            </button>
            <span v-else class="w-5 shrink-0"></span>

            <!-- Folder button -->
            <button
                type="button"
                class="flex items-center gap-2 flex-1 py-2 pr-1 text-left truncate"
                @click="$emit('select-folder', folder.id)"
            >
                <icon-folder-plus v-if="currentFolderId === folder.id" class="w-4 h-4 shrink-0 text-primary" />
                <icon-folder v-else class="w-4 h-4 shrink-0" />
                <span class="truncate">{{ folder.name }}</span>
                <span
                    v-if="folder.documents_count > 0"
                    class="ml-auto text-xs text-gray-400 shrink-0"
                >
                    {{ folder.documents_count }}
                </span>
            </button>

            <!-- Context menu button -->
            <div class="relative shrink-0">
                <button
                    type="button"
                    class="p-1 opacity-0 group-hover:opacity-100 transition-opacity text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    @click.stop="showMenu = !showMenu"
                >
                    <icon-horizontal-dots class="w-4 h-4" />
                </button>
                <!-- Backdrop -->
                <div v-if="showMenu" class="fixed inset-0 z-10" @click.stop="showMenu = false"></div>
                <div
                    v-if="showMenu"
                    class="absolute right-0 top-full mt-1 bg-white dark:bg-[#1b2e4b] border border-gray-200 dark:border-gray-700 rounded-md shadow-lg py-1 z-20 min-w-[120px]"
                >
                    <button
                        type="button"
                        class="flex items-center gap-2 w-full px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                        @click="showMenu = false; $emit('rename-folder', folder)"
                    >
                        <icon-pencil class="w-3.5 h-3.5" />
                        {{ $t('documents.rename') }}
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-2 w-full px-3 py-1.5 text-sm hover:bg-gray-100 dark:hover:bg-gray-700"
                        :class="canDelete ? 'text-danger' : 'text-gray-400 cursor-not-allowed'"
                        :disabled="!canDelete"
                        @click="if (canDelete) { showMenu = false; $emit('delete-folder', folder); }"
                    >
                        <icon-trash-lines class="w-3.5 h-3.5" />
                        {{ $t('documents.delete') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Children -->
        <div v-if="isExpanded && folder.children && folder.children.length > 0">
            <FolderTreeItem
                v-for="child in folder.children"
                :key="child.id"
                :folder="child"
                :current-folder-id="currentFolderId"
                :depth="depth + 1"
                @select-folder="(id: number) => $emit('select-folder', id)"
                @rename-folder="(f: DocumentFolder) => $emit('rename-folder', f)"
                @delete-folder="(f: DocumentFolder) => $emit('delete-folder', f)"
            />
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { DocumentFolder } from '@/types/document';

import IconFolder from '@/components/icon/icon-folder.vue';
import IconFolderPlus from '@/components/icon/icon-folder-plus.vue';
import IconCaretDown from '@/components/icon/icon-caret-down.vue';
import IconHorizontalDots from '@/components/icon/icon-horizontal-dots.vue';
import IconPencil from '@/components/icon/icon-pencil.vue';
import IconTrashLines from '@/components/icon/icon-trash-lines.vue';

const { t } = useI18n();

const props = defineProps<{
    folder: DocumentFolder;
    currentFolderId: number | null;
    depth: number;
}>();

defineEmits<{
    (e: 'select-folder', id: number): void;
    (e: 'rename-folder', folder: DocumentFolder): void;
    (e: 'delete-folder', folder: DocumentFolder): void;
}>();

const isExpanded = ref(true);
const showMenu = ref(false);

const canDelete = computed(() => {
    return props.folder.documents_count === 0
        && (!props.folder.children || props.folder.children.length === 0);
});
</script>
