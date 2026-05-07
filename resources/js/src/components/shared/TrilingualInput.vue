<template>
    <div>
        <label v-if="label" class="block text-sm font-semibold mb-2 text-gray-700 dark:text-white-light">
            {{ label }}
            <span v-if="requiredLocale" class="text-danger ml-0.5">*</span>
        </label>
        <div class="border border-[#e0e6ed] dark:border-[#1b2e4b] rounded">
            <!-- Locale tabs -->
            <div class="flex border-b border-[#e0e6ed] dark:border-[#1b2e4b] bg-gray-50 dark:bg-gray-900">
                <button
                    v-for="loc in locales"
                    :key="loc"
                    type="button"
                    class="px-4 py-2 text-xs font-semibold uppercase transition flex items-center gap-1"
                    :class="activeLocale === loc
                        ? 'bg-white dark:bg-gray-800 text-primary border-b-2 border-primary -mb-px'
                        : 'text-gray-500 hover:text-primary'"
                    @click="activeLocale = loc"
                >
                    {{ loc }}
                    <span v-if="loc === requiredLocale" class="text-danger">*</span>
                    <span
                        v-else-if="!modelValue?.[loc]"
                        class="text-warning text-base leading-none"
                        :title="$t('workflow.translation_missing')"
                    >!</span>
                </button>
            </div>
            <!-- Input area -->
            <div class="p-2 bg-white dark:bg-gray-800">
                <textarea
                    v-if="textarea"
                    :value="modelValue?.[activeLocale] ?? ''"
                    :rows="rows ?? 3"
                    :maxlength="maxlength"
                    :placeholder="placeholder"
                    class="form-textarea w-full text-sm border-0 focus:ring-0 dark:bg-gray-800"
                    @input="onInput"
                />
                <input
                    v-else
                    type="text"
                    :value="modelValue?.[activeLocale] ?? ''"
                    :maxlength="maxlength"
                    :placeholder="placeholder"
                    class="form-input w-full text-sm border-0 focus:ring-0 dark:bg-gray-800"
                    @input="onInput"
                />
            </div>
        </div>
        <p v-if="hint" class="text-xs text-gray-400 mt-1">{{ hint }}</p>
    </div>
</template>

<script lang="ts" setup>
import { ref } from 'vue';
import type { Locale } from '@/types/workflow';

const props = defineProps<{
    modelValue: Partial<Record<Locale, string>> | undefined;
    label?: string;
    locales?: Locale[];
    requiredLocale?: Locale;
    textarea?: boolean;
    rows?: number;
    maxlength?: number;
    placeholder?: string;
    hint?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', v: Partial<Record<Locale, string>>): void;
}>();

const locales = props.locales ?? (['es', 'en', 'fr'] as Locale[]);
const activeLocale = ref<Locale>(props.requiredLocale ?? locales[0]);

function onInput(e: Event) {
    const value = (e.target as HTMLInputElement | HTMLTextAreaElement).value;
    emit('update:modelValue', { ...(props.modelValue ?? {}), [activeLocale.value]: value });
}
</script>
