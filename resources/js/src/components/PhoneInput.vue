<template>
    <div class="flex gap-2">
        <select
            :value="countryCode"
            @change="$emit('update:countryCode', ($event.target as HTMLSelectElement).value)"
            class="form-select w-[130px] shrink-0"
            :class="{ 'border-danger': error }"
            :disabled="disabled"
        >
            <option v-for="item in PHONE_CODES" :key="item.country" :value="item.code">
                {{ item.code }}
            </option>
        </select>
        <input
            :value="modelValue"
            @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
            type="text"
            class="form-input flex-1"
            :class="{ 'border-danger': error }"
            :placeholder="phonePlaceholder"
            :required="required"
            :disabled="disabled"
            v-maska="phoneMask"
        />
    </div>
</template>

<script lang="ts" setup>
import { computed } from 'vue';
import { vMaska } from 'maska/vue';
import { PHONE_CODES } from '@/data/phoneCodes';

const props = withDefaults(defineProps<{
    modelValue: string;
    countryCode: string;
    error?: boolean;
    required?: boolean;
    disabled?: boolean;
}>(), {
    countryCode: '+1',
    error: false,
    required: false,
    disabled: false,
});

defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'update:countryCode', value: string): void;
}>();

const isNorthAmerican = computed(() => props.countryCode === '+1');

const phoneMask = computed(() => isNorthAmerican.value ? '(###) ###-####' : '');

const phonePlaceholder = computed(() =>
    isNorthAmerican.value ? '(___) ___-____' : '1234567890'
);
</script>
