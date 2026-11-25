<template>
    <!-- Spec 71 R9 — same segmented control pattern as DocumentToolbar -->
    <div class="flex items-center border border-gray-200 dark:border-gray-700 rounded-md" role="group" :aria-label="$t('scrum_view_toggle_label')">
        <button
            type="button"
            class="flex items-center gap-1.5 px-2.5 py-1.5 text-sm transition-colors ltr:rounded-l-md rtl:rounded-r-md"
            :class="modelValue === 'board' ? 'bg-primary text-white' : 'text-gray-500 hover:text-primary'"
            :title="$t('scrum_view_board')"
            :aria-pressed="modelValue === 'board'"
            @click="emit('update:modelValue', 'board')"
        >
            <icon-layout-grid class="w-4 h-4" />
            <span class="hidden sm:inline">{{ $t('scrum_view_board') }}</span>
        </button>
        <button
            type="button"
            class="flex items-center gap-1.5 px-2.5 py-1.5 text-sm transition-colors ltr:rounded-r-md rtl:rounded-l-md"
            :class="modelValue === 'list' ? 'bg-primary text-white' : 'text-gray-500 hover:text-primary'"
            :title="$t('scrum_view_list')"
            :aria-pressed="modelValue === 'list'"
            @click="emit('update:modelValue', 'list')"
        >
            <icon-list-check class="w-4 h-4" />
            <span class="hidden sm:inline">{{ $t('scrum_view_list') }}</span>
        </button>
    </div>
</template>

<script lang="ts" setup>
import IconLayoutGrid from '@/components/icon/icon-layout-grid.vue';
import IconListCheck from '@/components/icon/icon-list-check.vue';
import type { ScrumViewMode } from '@/stores/scrum';

defineProps<{ modelValue: ScrumViewMode }>();
const emit = defineEmits<{ 'update:modelValue': [mode: ScrumViewMode] }>();
</script>
