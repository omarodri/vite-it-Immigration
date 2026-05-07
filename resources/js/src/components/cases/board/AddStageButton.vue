<template>
    <button
        type="button"
        class="min-w-[300px] w-[300px] max-w-[300px] flex flex-col items-center justify-center gap-2 border-2 border-dashed border-gray-300 dark:border-gray-600 hover:border-primary text-primary rounded-md transition py-8 self-start"
        @click="$emit('open-modal')"
    >
        <icon-plus class="w-8 h-8" />
        <span class="text-sm font-medium">{{ $t('cases.stage.add_new') }}</span>
    </button>
</template>

<script lang="ts" setup>
import IconPlus from '@/components/icon/icon-plus.vue';

defineProps<{
    caseId: number;
}>();

defineEmits<{
    (e: 'open-modal'): void;
}>();
</script>
