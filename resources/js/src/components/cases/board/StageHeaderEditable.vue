<template>
    <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-200 dark:border-gray-700">
        <!-- Drag handle -->
        <button
            v-if="editable"
            type="button"
            class="stage-drag-handle cursor-grab active:cursor-grabbing text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 shrink-0"
            :title="$t('cases.stage.edit')"
            @click.stop
        >
            <icon-horizontal-dots class="w-4 h-4 rotate-90" />
        </button>

        <!-- Color dot -->
        <span
            class="inline-block w-2.5 h-2.5 rounded-full shrink-0"
            :style="{ backgroundColor: colorHex }"
        ></span>

        <!-- Name (clickable to edit) -->
        <button
            v-if="editable"
            type="button"
            class="text-xs font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-200 truncate hover:text-primary text-left"
            @click="$emit('edit')"
        >
            {{ stage.name_resolved }}
        </button>
        <span
            v-else
            class="text-xs font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-200 truncate"
        >
            {{ stage.name_resolved }}
        </span>

        <!-- Ad-hoc badge -->
        <span
            v-if="stage.is_ad_hoc"
            class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-secondary/10 text-secondary"
        >
            {{ $t('cases.stage.adhoc_badge') }}
        </span>

        <!-- Terminal flag -->
        <span v-if="stage.is_terminal" class="text-success" :title="$t('cases.stage.field_is_terminal')">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                <path d="M4 22V4a1 1 0 011-1h12.5l-2 4 2 4H6v11H4z" />
            </svg>
        </span>

        <!-- Task count -->
        <span class="ml-auto text-[11px] text-gray-500 dark:text-gray-400">{{ taskCount }}</span>

        <!-- Kebab menu -->
        <Menu v-if="editable" as="div" class="relative">
            <MenuButton class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 p-1">
                <icon-horizontal-dots class="w-4 h-4" />
            </MenuButton>
            <transition
                enter-active-class="transition duration-100 ease-out"
                enter-from-class="transform scale-95 opacity-0"
                enter-to-class="transform scale-100 opacity-100"
                leave-active-class="transition duration-75 ease-in"
                leave-from-class="transform scale-100 opacity-100"
                leave-to-class="transform scale-95 opacity-0"
            >
                <MenuItems
                    class="absolute right-0 mt-1 w-40 origin-top-right rounded-md bg-white dark:bg-[#1c232f] shadow-lg ring-1 ring-black/5 dark:ring-white/10 focus:outline-none z-30"
                >
                    <MenuItem v-slot="{ active }">
                        <button
                            type="button"
                            class="w-full text-left px-3 py-2 text-sm flex items-center gap-2"
                            :class="active ? 'bg-gray-100 dark:bg-gray-800' : ''"
                            @click="$emit('edit')"
                        >
                            <icon-pencil class="w-4 h-4" />
                            {{ $t('cases.stage.edit') }}
                        </button>
                    </MenuItem>
                    <MenuItem v-slot="{ active }">
                        <button
                            type="button"
                            class="w-full text-left px-3 py-2 text-sm text-danger flex items-center gap-2"
                            :class="active ? 'bg-gray-100 dark:bg-gray-800' : ''"
                            @click="$emit('delete')"
                        >
                            <icon-trash class="w-4 h-4" />
                            {{ $t('cases.stage.delete') }}
                        </button>
                    </MenuItem>
                </MenuItems>
            </transition>
        </Menu>
    </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue';
import IconHorizontalDots from '@/components/icon/icon-horizontal-dots.vue';
import IconPencil from '@/components/icon/icon-pencil.vue';
import IconTrash from '@/components/icon/icon-trash.vue';
import type { CaseStage } from '@/types/workflow';

const props = defineProps<{
    stage: CaseStage;
    editable: boolean;
    taskCount: number;
}>();

defineEmits<{
    (e: 'edit'): void;
    (e: 'delete'): void;
}>();

const COLOR_HEX: Record<string, string> = {
    primary: '#4361ee',
    secondary: '#805dca',
    success: '#00ab55',
    danger: '#e7515a',
    warning: '#e2a03f',
    info: '#2196f3',
    dark: '#3b3f5c',
};

const colorHex = computed(() => COLOR_HEX[props.stage.color] ?? props.stage.color ?? '#4361ee');
</script>
