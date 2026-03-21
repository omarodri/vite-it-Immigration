<template>
    <div
        :class="[
            'relative p-4 rounded-lg border-2 cursor-pointer transition-all duration-200',
            isSelected
                ? 'border-primary bg-primary/5 dark:bg-primary/10'
                : 'border-gray-200 dark:border-gray-700 hover:border-primary/50',
        ]"
        @click="$emit('select', caseType.id)"
    >
        <!-- Selected Indicator -->
        <div
            v-if="isSelected"
            class="absolute top-2 right-2 w-6 h-6 rounded-full bg-primary text-white flex items-center justify-center"
        >
            <icon-circle-check class="w-4 h-4" />
        </div>

        <!-- Category Badge -->
        <span
            :class="[
                'inline-block px-2 py-0.5 text-xs font-medium rounded-full mb-2',
                getCategoryClass(caseType.category),
            ]"
        >
            {{ $t(`case_types.category.${caseType.category}`) }}
        </span>

        <!-- Case Type Name -->
        <h4 class="font-semibold text-gray-900 dark:text-white mb-1">
            {{ $t(`case_types.${caseType.name}`) }}
        </h4>

        <!-- Code -->
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
            {{ caseType.code }}
        </p>

        <!-- Description -->
        <p
            v-if="caseType.description"
            class="text-sm text-gray-600 dark:text-gray-300 line-clamp-2"
        >
            {{ $t(`case_types.${caseType.description}`) }}
        </p>
    </div>
</template>

<script lang="ts" setup>
import type { CaseType, CaseTypeCategory } from '@/types/case';
import IconCircleCheck from '@/components/icon/icon-circle-check.vue';

interface Props {
    caseType: CaseType;
    isSelected: boolean;
}

interface Emits {
    (e: 'select', id: number): void;
}

defineProps<Props>();
defineEmits<Emits>();

function getCategoryClass(category: CaseTypeCategory): string {
    const classes: Record<string, string> = {
        temporary_residence: 'bg-info/20 text-info',
        permanent_residence: 'bg-success/20 text-success',
        refugee: 'bg-warning/20 text-warning',
        citizenship: 'bg-danger/20 text-danger',
    };
    return classes[category] || 'bg-gray-200 text-gray-700';
}
</script>
