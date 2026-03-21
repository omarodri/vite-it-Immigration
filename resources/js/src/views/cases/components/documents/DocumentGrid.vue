<template>
    <div>
        <!-- Loading -->
        <div v-if="isLoading" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <div v-for="n in 8" :key="n" class="animate-pulse">
                <div class="bg-gray-200 dark:bg-gray-700 rounded-lg h-32"></div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="documents.length === 0" class="text-center py-10 text-gray-500">
            <icon-file class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
            <p>{{ $t('documents.empty') }}</p>
            <p class="text-sm mt-1">{{ $t('documents.drop_files') }}</p>
        </div>

        <!-- Grid View -->
        <div v-else-if="viewMode === 'grid'" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <div
                v-for="doc in documents"
                :key="doc.id"
                class="group relative border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-primary/50 hover:shadow-md transition-all cursor-pointer bg-white dark:bg-[#1b2e4b]"
                @click="onDocClick(doc)"
                @contextmenu.prevent="openContextMenu($event, doc)"
            >
                <!-- File icon -->
                <div class="flex justify-center mb-3">
                    <component :is="getFileIcon(doc.mime_type)" class="w-10 h-10" :class="getFileIconColor(doc.mime_type)" />
                </div>
                <!-- File name -->
                <p class="text-sm font-medium text-center truncate text-gray-700 dark:text-gray-300" :title="doc.original_name">
                    {{ doc.original_name }}
                </p>
                <!-- Meta -->
                <div class="flex items-center justify-center gap-2 mt-2 text-xs text-gray-400">
                    <span>{{ formatFileSize(doc.size) }}</span>
                    <span>&middot;</span>
                    <span>{{ formatShortDate(doc.created_at) }}</span>
                </div>
                <!-- Dropdown trigger -->
                <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button
                        type="button"
                        class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 bg-white dark:bg-[#1b2e4b] rounded"
                        @click.stop="openContextMenu($event, doc)"
                    >
                        <icon-horizontal-dots class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- List View -->
        <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400">{{ $t('documents.rename_file') }}</th>
                        <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400">{{ $t('documents.size') }}</th>
                        <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400">{{ $t('documents.category') }}</th>
                        <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400">{{ $t('documents.uploaded_by') }}</th>
                        <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400">{{ $t('documents.date') }}</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="doc in documents"
                        :key="doc.id"
                        class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors group cursor-pointer"
                        @click="onDocClick(doc)"
                        @contextmenu.prevent="openContextMenu($event, doc)"
                    >
                        <td class="py-2 px-3">
                            <div class="flex items-center gap-2">
                                <component :is="getFileIcon(doc.mime_type)" class="w-5 h-5 shrink-0" :class="getFileIconColor(doc.mime_type)" />
                                <span class="truncate max-w-[200px]" :title="doc.original_name">{{ doc.original_name }}</span>
                                <span v-if="doc.version > 1" class="text-xs text-gray-400">v{{ doc.version }}</span>
                            </div>
                        </td>
                        <td class="py-2 px-3 text-gray-500">{{ formatFileSize(doc.size) }}</td>
                        <td class="py-2 px-3">
                            <span v-if="doc.category" class="badge badge-outline-info text-xs">{{ doc.category }}</span>
                        </td>
                        <td class="py-2 px-3 text-gray-500">{{ doc.uploaded_by?.name || '-' }}</td>
                        <td class="py-2 px-3 text-gray-500">{{ formatShortDate(doc.created_at) }}</td>
                        <td class="py-2 px-3">
                            <button
                                type="button"
                                class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 opacity-0 group-hover:opacity-100 transition-opacity"
                                @click.stop="openContextMenu($event, doc)"
                            >
                                <icon-horizontal-dots class="w-4 h-4 rotate-90" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Context Menu -->
        <Teleport to="body">
            <!-- Backdrop to close menu on click outside -->
            <div
                v-if="contextMenu.visible"
                class="fixed inset-0 z-40"
                @click="closeContextMenu"
            ></div>
            <div
                v-if="contextMenu.visible"
                class="fixed z-50 bg-white dark:bg-[#1b2e4b] border border-gray-200 dark:border-gray-700 rounded-md shadow-lg py-1 min-w-[160px]"
                :style="{ left: contextMenu.x + 'px', top: contextMenu.y + 'px' }"
            >
                <button
                    v-if="isPreviewable(contextMenu.document?.mime_type)"
                    type="button"
                    class="flex items-center gap-2 w-full px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                    @click="handleAction('preview')"
                >
                    <icon-eye class="w-4 h-4" />
                    {{ $t('documents.preview') }}
                </button>
                <button
                    type="button"
                    class="flex items-center gap-2 w-full px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                    @click="handleAction('download')"
                >
                    <icon-download class="w-4 h-4" />
                    {{ $t('documents.download') }}
                </button>
                <button
                    type="button"
                    class="flex items-center gap-2 w-full px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                    @click="handleAction('rename')"
                >
                    <icon-pencil class="w-4 h-4" />
                    {{ $t('documents.rename') }}
                </button>
                <button
                    type="button"
                    class="flex items-center gap-2 w-full px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                    @click="handleAction('move')"
                >
                    <icon-arrow-forward class="w-4 h-4" />
                    {{ $t('documents.move') }}
                </button>
                <button
                    type="button"
                    class="flex items-center gap-2 w-full px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                    @click="handleAction('replace')"
                >
                    <icon-refresh class="w-4 h-4" />
                    {{ $t('documents.replace') }}
                </button>
                <hr class="my-1 border-gray-200 dark:border-gray-700" />
                <button
                    type="button"
                    class="flex items-center gap-2 w-full px-3 py-1.5 text-sm text-danger hover:bg-gray-100 dark:hover:bg-gray-700"
                    @click="handleAction('delete')"
                >
                    <icon-trash-lines class="w-4 h-4" />
                    {{ $t('documents.delete') }}
                </button>
            </div>
        </Teleport>
    </div>
</template>

<script lang="ts" setup>
import { reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Document } from '@/types/document';

import IconFile from '@/components/icon/icon-file.vue';
import IconTxtFile from '@/components/icon/icon-txt-file.vue';
import IconZipFile from '@/components/icon/icon-zip-file.vue';
import IconHorizontalDots from '@/components/icon/icon-horizontal-dots.vue';
import IconDownload from '@/components/icon/icon-download.vue';
import IconPencil from '@/components/icon/icon-pencil.vue';
import IconArrowForward from '@/components/icon/icon-arrow-forward.vue';
import IconRefresh from '@/components/icon/icon-refresh.vue';
import IconTrashLines from '@/components/icon/icon-trash-lines.vue';
import IconEye from '@/components/icon/icon-eye.vue';

const { t } = useI18n();

defineProps<{
    documents: Document[];
    viewMode: 'grid' | 'list';
    isLoading: boolean;
}>();

const emit = defineEmits<{
    (e: 'download', doc: Document): void;
    (e: 'rename', doc: Document): void;
    (e: 'move', doc: Document): void;
    (e: 'replace', doc: Document): void;
    (e: 'delete', doc: Document): void;
    (e: 'preview', doc: Document): void;
}>();

const contextMenu = reactive({
    visible: false,
    x: 0,
    y: 0,
    document: null as Document | null,
});

function openContextMenu(event: MouseEvent, doc: Document) {
    contextMenu.visible = true;
    contextMenu.x = event.clientX;
    contextMenu.y = event.clientY;
    contextMenu.document = doc;
}

function closeContextMenu() {
    contextMenu.visible = false;
    contextMenu.document = null;
}

function handleAction(action: 'download' | 'rename' | 'move' | 'replace' | 'delete' | 'preview') {
    if (!contextMenu.document) return;
    const doc = contextMenu.document;
    closeContextMenu();
    switch (action) {
        case 'download': emit('download', doc); break;
        case 'rename': emit('rename', doc); break;
        case 'move': emit('move', doc); break;
        case 'replace': emit('replace', doc); break;
        case 'delete': emit('delete', doc); break;
        case 'preview': emit('preview', doc); break;
    }
}

function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function formatShortDate(dateStr: string): string {
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    } catch {
        return dateStr;
    }
}

function isPreviewable(mimeType: string | null | undefined): boolean {
    if (!mimeType) return false;
    return mimeType === 'application/pdf' || mimeType.startsWith('image/');
}

function onDocClick(doc: Document) {
    if (isPreviewable(doc.mime_type)) {
        emit('preview', doc);
    } else {
        emit('download', doc);
    }
}

function getFileIcon(mimeType: string | null): any {
    if (!mimeType) return IconFile;
    if (mimeType.includes('zip') || mimeType.includes('compressed') || mimeType.includes('archive')) return IconZipFile;
    if (mimeType.includes('text')) return IconTxtFile;
    return IconFile;
}

function getFileIconColor(mimeType: string | null): string {
    if (!mimeType) return 'text-gray-400';
    if (mimeType === 'application/pdf') return 'text-danger';
    if (mimeType.startsWith('image/')) return 'text-info';
    if (mimeType.includes('word') || mimeType.includes('document')) return 'text-primary';
    if (mimeType.includes('spreadsheet') || mimeType.includes('excel')) return 'text-success';
    if (mimeType.includes('zip') || mimeType.includes('compressed')) return 'text-warning';
    return 'text-gray-400';
}
</script>
