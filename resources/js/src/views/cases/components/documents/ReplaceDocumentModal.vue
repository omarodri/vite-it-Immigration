<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70"
            @click.self="$emit('close')"
        >
            <div class="relative w-full max-w-lg mx-4 bg-white dark:bg-[#0e1726] rounded-lg shadow-xl overflow-hidden">
                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                        {{ $t('documents.replace_confirm') }}
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
                <div class="px-5 py-4 space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $t('documents.replace_description') }}
                    </p>

                    <!-- Current File -->
                    <div class="rounded-md border border-gray-200 dark:border-gray-700 p-3">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                            {{ $t('documents.current_file') }}
                        </p>
                        <div class="flex items-center gap-3">
                            <icon-file class="w-8 h-8 text-gray-400 shrink-0" />
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate" :title="document?.original_name">
                                    {{ document?.original_name }}
                                </p>
                                <div class="flex items-center gap-2 text-xs text-gray-400 mt-0.5">
                                    <span>{{ formatFileSize(document?.size ?? 0) }}</span>
                                    <span>&middot;</span>
                                    <span>{{ formatDate(document?.created_at ?? '') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Arrow -->
                    <div class="flex justify-center">
                        <icon-arrow-forward class="w-5 h-5 text-primary rotate-90" />
                    </div>

                    <!-- New File -->
                    <div class="rounded-md border border-primary/30 bg-primary/5 dark:bg-primary/10 p-3">
                        <p class="text-xs font-semibold text-primary uppercase tracking-wide mb-2">
                            {{ $t('documents.new_file') }}
                        </p>
                        <div class="flex items-center gap-3">
                            <icon-file class="w-8 h-8 text-primary shrink-0" />
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate" :title="file?.name">
                                    {{ file?.name }}
                                </p>
                                <div class="text-xs text-gray-400 mt-0.5">
                                    <span>{{ formatFileSize(file?.size ?? 0) }}</span>
                                </div>
                            </div>
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
                        @click="$emit('confirm', file)"
                    >
                        <icon-refresh class="w-4 h-4 ltr:mr-1.5 rtl:ml-1.5" />
                        {{ $t('documents.replace') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script lang="ts" setup>
import { useI18n } from 'vue-i18n';
import type { Document } from '@/types/document';
import IconX from '@/components/icon/icon-x.vue';
import IconFile from '@/components/icon/icon-file.vue';
import IconArrowForward from '@/components/icon/icon-arrow-forward.vue';
import IconRefresh from '@/components/icon/icon-refresh.vue';

const { t } = useI18n();

const props = defineProps<{
    show: boolean;
    document: Document | null;
    file: File | null;
}>();

defineEmits<{
    (e: 'close'): void;
    (e: 'confirm', file: File): void;
}>();

function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function formatDate(dateStr: string): string {
    if (!dateStr) return '';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    } catch {
        return dateStr;
    }
}
</script>
