<template>
    <multiselect
        :modelValue="modelValue"
        @update:modelValue="$emit('update:modelValue', $event)"
        :options="COUNTRIES"
        :searchable="true"
        :placeholder="placeholder"
        :id="id"
        :disabled="disabled"
        class="custom-multiselect"
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

<style>
.dark .multiselect__tags {
    background-color: rgb(17 24 39);
    border-color: rgb(55 65 81);
    color: rgb(243 244 246);
}

.dark .multiselect__single,
.dark .multiselect__input,
.dark .multiselect__placeholder {
    background-color: rgb(17 24 39);
    color: rgb(243 244 246);
}

.dark .multiselect__input::placeholder {
    color: rgb(107 114 128);
}

.dark .multiselect__content-wrapper {
    background-color: rgb(17 24 39);
    border-color: rgb(55 65 81);
}

.dark .multiselect__option {
    background-color: rgb(17 24 39);
    color: rgb(243 244 246);
}

.dark .multiselect__option--highlight {
    background-color: rgb(55 65 81) !important;
    color: #ffffff !important;
}

.dark .multiselect__option--selected {
    background-color: rgb(37 99 235 / 0.2);
    color: rgb(147 197 253);
}

.dark .multiselect__option--selected.multiselect__option--highlight {
    background-color: rgb(37 99 235 / 0.3);
    color: rgb(147 197 253);
}

.multiselect__content-wrapper {
    z-index: 9999 !important;
}
</style>
