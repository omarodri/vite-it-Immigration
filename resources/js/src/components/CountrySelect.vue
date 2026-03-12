<template>
    <multiselect
        :modelValue="modelValue"
        @update:modelValue="$emit('update:modelValue', $event)"
        :options="COUNTRIES"
        :searchable="true"
        :placeholder="placeholder"
        :id="id"
        :disabled="disabled"
        class="custom-multiselect dark:bg-gray-900 dark:text-white"
        :allow-empty="true"
        :show-labels="false"
    />
</template>

<script lang="ts" setup>
import Multiselect from '@suadelabs/vue3-multiselect';
import '@suadelabs/vue3-multiselect/dist/vue3-multiselect.css';
import { COUNTRIES } from '@/data/countries';

withDefaults(
    defineProps<{
        modelValue: string | null;
        placeholder?: string;
        id?: string;
        disabled?: boolean;
    }>(),
    {
        placeholder: 'Select country',
        id: undefined,
        disabled: false,
    }
);

defineEmits<{
    (e: 'update:modelValue', value: string | null): void;
}>();
</script>
