<template>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <!-- Left: Breadcrumb -->
        <div class="flex items-center gap-2 text-sm">
            <button
                type="button"
                class="text-gray-500 hover:text-primary transition-colors"
                @click="$emit('navigate-root')"
            >
                {{ $t('documents.all_files') }}
            </button>
            <template v-if="currentFolder">
                <span class="text-gray-400">/</span>
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ currentFolder.name }}</span>
            </template>
        </div>

        <!-- Right: Actions -->
        <div class="flex items-center gap-2">
            <button
                type="button"
                class="btn btn-primary btn-sm gap-1"
                @click="$emit('upload-click')"
            >
                <icon-plus class="w-4 h-4 shrink-0" />
                {{ $t('documents.upload') }}
            </button>
            <button
                type="button"
                class="btn btn-outline-primary btn-sm gap-1"
                @click="$emit('create-folder')"
            >
                <icon-folder-plus class="w-4 h-4 shrink-0" />
                {{ $t('documents.new_folder') }}
            </button>
            <div class="flex items-center border border-gray-200 dark:border-gray-700 rounded-md">
                <button
                    type="button"
                    class="p-1.5 transition-colors"
                    :class="viewMode === 'grid' ? 'bg-primary text-white' : 'text-gray-500 hover:text-primary'"
                    :title="$t('documents.grid_view')"
                    @click="$emit('toggle-view', 'grid')"
                >
                    <icon-layout-grid class="w-4 h-4" />
                </button>
                <button
                    type="button"
                    class="p-1.5 transition-colors"
                    :class="viewMode === 'list' ? 'bg-primary text-white' : 'text-gray-500 hover:text-primary'"
                    :title="$t('documents.list_view')"
                    @click="$emit('toggle-view', 'list')"
                >
                    <icon-list-check class="w-4 h-4" />
                </button>
            </div>
            <button
                type="button"
                class="btn btn-outline-secondary btn-sm p-1.5"
                @click="$emit('refresh')"
            >
                <icon-refresh class="w-4 h-4" />
            </button>
            <button
                type="button"
                class="btn btn-outline-secondary btn-sm p-1.5"
                :title="$t('documents.cloud_settings')"
                @click="$emit('settings')"
            >
                <icon-settings class="w-4 h-4" />
            </button>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { useI18n } from 'vue-i18n';
import type { DocumentFolder } from '@/types/document';

import IconPlus from '@/components/icon/icon-plus.vue';
import IconFolderPlus from '@/components/icon/icon-folder-plus.vue';
import IconLayoutGrid from '@/components/icon/icon-layout-grid.vue';
import IconListCheck from '@/components/icon/icon-list-check.vue';
import IconRefresh from '@/components/icon/icon-refresh.vue';
import IconSettings from '@/components/icon/icon-settings.vue';

const { t } = useI18n();

defineProps<{
    currentFolder: DocumentFolder | null;
    viewMode: 'grid' | 'list';
}>();

defineEmits<{
    (e: 'upload-click'): void;
    (e: 'create-folder'): void;
    (e: 'toggle-view', mode: 'grid' | 'list'): void;
    (e: 'refresh'): void;
    (e: 'navigate-root'): void;
    (e: 'settings'): void;
}>();
</script>
