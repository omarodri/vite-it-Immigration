<template>
    <label
        :class="[
            'flex items-center gap-4 p-4 rounded-lg border-2 cursor-pointer transition-all duration-200',
            isSelected
                ? 'border-primary bg-primary/5 dark:bg-primary/10'
                : 'border-gray-200 dark:border-gray-700 hover:border-primary/50',
        ]"
    >
        <input
            type="checkbox"
            :checked="isSelected"
            class="form-checkbox text-primary rounded"
            @change="$emit('toggle', companion.id)"
        />

        <!-- Avatar -->
        <div
            class="w-10 h-10 rounded-full bg-secondary/20 text-secondary flex items-center justify-center font-semibold"
        >
            {{ getInitials(companion.first_name, companion.last_name) }}
        </div>

        <!-- Info -->
        <div class="flex-1 min-w-0">
            <p class="font-medium text-gray-900 dark:text-white">
                {{ companion.full_name || `${companion.first_name} ${companion.last_name}` }}
            </p>
            <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                <span class="inline-flex items-center gap-1">
                    <icon-users class="w-3.5 h-3.5" />
                    {{ companion.relationship_label || $t(`companions.${companion.relationship}`) }}
                </span>
                <span v-if="companion.age" class="inline-flex items-center gap-1">
                    <icon-calendar class="w-3.5 h-3.5" />
                    {{ companion.age }} {{ $t('common.years') }}
                </span>
                <span v-if="companion.iuc" class="text-xs text-gray-500 dark:text-gray-400">
                    IUC: {{ companion.iuc }}
                </span>
            </div>
        </div>

        <!-- Delete Button -->
        <button
            v-if="showDelete"
            v-can="'companions.delete'"
            type="button"
            class="p-1.5 text-gray-400 hover:text-danger transition-colors rounded-lg hover:bg-danger/10"
            :title="$t('companions.confirm_delete')"
            @click.prevent.stop="$emit('delete', companion.id)"
        >
            <icon-trash class="w-4 h-4" />
        </button>
    </label>
</template>

<script lang="ts" setup>
import type { Companion } from '@/types/companion';
import IconUsers from '@/components/icon/icon-users.vue';
import IconCalendar from '@/components/icon/icon-calendar.vue';
import IconTrash from '@/components/icon/icon-trash.vue';

interface Props {
    companion: Companion;
    isSelected: boolean;
    showDelete?: boolean;
}

interface Emits {
    (e: 'toggle', id: number): void;
    (e: 'delete', id: number): void;
}

defineProps<Props>();
defineEmits<Emits>();

function getInitials(firstName: string, lastName: string): string {
    return `${firstName?.charAt(0) || ''}${lastName?.charAt(0) || ''}`.toUpperCase();
}
</script>
