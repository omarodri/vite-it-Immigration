<script lang="ts" setup>
import { computed, ref, watch } from 'vue';
import flatPickr from 'vue-flatpickr-component';
import 'flatpickr/dist/flatpickr.css';
import { Spanish } from 'flatpickr/dist/l10n/es.js';
import { French } from 'flatpickr/dist/l10n/fr.js';
import { useAppStore } from '@/stores/index';

const props = withDefaults(defineProps<{
    modelValue: string | null | undefined;
    enableTime?: boolean;
    minDate?: string | null;
    maxDate?: string | null;
    placeholder?: string;
    inputClass?: string;
    disabled?: boolean;
}>(), {
    enableTime: false,
    minDate: null,
    maxDate: null,
    placeholder: '',
    inputClass: 'form-input',
    disabled: false,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string | null): void;
}>();

const store = useAppStore();

const localeMap: Record<string, any> = {
    es: Spanish,
    fr: French,
};

const altFormatMap: Record<string, string> = {
    es: 'd/m/Y',
    en: 'm/d/Y',
    fr: 'd/m/Y',
};

const altFormatTimeMap: Record<string, string> = {
    es: 'd/m/Y H:i',
    en: 'm/d/Y h:i K',
    fr: 'd/m/Y H:i',
};

const currentLocale = computed(() => store.locale || 'en');

const config = computed(() => {
    const locale = currentLocale.value;
    const fmt = props.enableTime
        ? (altFormatTimeMap[locale] || 'm/d/Y h:i K')
        : (altFormatMap[locale] || 'm/d/Y');

    const cfg: Record<string, any> = {
        dateFormat: props.enableTime ? 'Y-m-d H:i' : 'Y-m-d',
        altInput: true,
        altFormat: fmt,
        allowInput: true,
        enableTime: props.enableTime,
        time_24hr: locale !== 'en',
        // Parse date strings as local time to avoid UTC-offset day shift
        parseDate: (dateStr: string) => {
            const match = dateStr.match(/^(\d{4})-(\d{2})-(\d{2})/);
            if (match) {
                return new Date(+match[1], +match[2] - 1, +match[3]);
            }
            return new Date(dateStr);
        },
    };

    if (localeMap[locale]) {
        cfg.locale = localeMap[locale];
    }

    if (props.minDate) {
        cfg.minDate = props.minDate;
    }

    if (props.maxDate) {
        cfg.maxDate = props.maxDate;
    }

    return cfg;
});

const flatpickrRef = ref<InstanceType<typeof flatPickr> | null>(null);

const localValue = computed({
    get: () => props.modelValue ?? '',
    set: (val: string | null) => {
        emit('update:modelValue', val || null);
    },
});

// Re-create flatpickr when locale changes to apply new locale settings
watch(currentLocale, () => {
    const fp = (flatpickrRef.value as any)?.fp;
    if (fp) {
        const locale = currentLocale.value;
        const fmt = props.enableTime
            ? (altFormatTimeMap[locale] || 'm/d/Y h:i K')
            : (altFormatMap[locale] || 'm/d/Y');

        fp.set('locale', localeMap[locale] || undefined);
        fp.set('altFormat', fmt);
        fp.set('time_24hr', locale !== 'en');
    }
});
</script>

<template>
    <flat-pickr
        ref="flatpickrRef"
        v-model="localValue"
        :config="config"
        :class="inputClass"
        :placeholder="placeholder"
        :disabled="disabled"
    />
</template>
