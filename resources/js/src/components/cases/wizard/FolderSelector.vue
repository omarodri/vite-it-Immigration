<script lang="ts" setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { caseFolderTemplateService } from '@/services/caseFolderTemplateService';
import type { CaseFolderInput, FolderTemplate, FolderValidationConfig } from '@/types/case';

const props = defineProps<{
    modelValue: CaseFolderInput[];
    locale: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: CaseFolderInput[]): void;
}>();

const { t, te } = useI18n();

const templates = ref<FolderTemplate[]>([]);
const validation = ref<FolderValidationConfig | null>(null);
const loading = ref(true);
const checked = ref<Record<string, boolean>>({});
const customFolders = ref<{ name: string; error: string | null }[]>([]);
const newCustomName = ref('');
const newCustomError = ref<string | null>(null);

onMounted(async () => {
    try {
        const res = await caseFolderTemplateService.getDefaults();
        templates.value = res.data;
        validation.value = res.validation;

        if (props.modelValue.length > 0) {
            templates.value.forEach((tpl) => {
                const translated = resolveName(tpl);
                checked.value[tpl.key] = props.modelValue.some(
                    (f) => !f.is_custom && f.name === translated,
                );
            });
            customFolders.value = props.modelValue
                .filter((f) => f.is_custom)
                .map((f) => ({ name: f.name, error: null }));
        } else {
            templates.value.forEach((tpl) => {
                checked.value[tpl.key] = tpl.enabled_by_default;
            });
        }
    } catch (e) {
        console.error('Failed to load folder templates:', e);
    } finally {
        loading.value = false;
        emitSelection();
    }
});

function resolveName(tpl: FolderTemplate): string {
    if (te(tpl.i18n_key, props.locale)) return t(tpl.i18n_key, {}, { locale: props.locale });
    if (te(tpl.i18n_key, 'en')) return t(tpl.i18n_key, {}, { locale: 'en' });
    return tpl.key;
}

function validateName(name: string, ignoreIndex: number | null = null): string | null {
    if (!validation.value) return null;
    const trimmed = name.trim();
    if (trimmed.length === 0) return t('wizard.step_folders.empty');
    if (trimmed.length > validation.value.name_max_length) {
        return t('wizard.step_folders.invalid_chars');
    }
    if (/[<>:"/\\|?*]/.test(trimmed)) return t('wizard.step_folders.invalid_chars');
    if (/^\.+\s*$/.test(trimmed)) return t('wizard.step_folders.invalid_chars');
    const base = trimmed.split('.')[0].toUpperCase();
    if (validation.value.reserved_names.includes(base)) return t('wizard.step_folders.reserved_name');
    const lower = trimmed.toLowerCase();
    if (currentNamesLower(ignoreIndex).includes(lower)) return t('wizard.step_folders.duplicate');
    return null;
}

function currentNamesLower(ignoreCustomIndex: number | null): string[] {
    const names: string[] = [];
    templates.value.forEach((tpl) => {
        if (checked.value[tpl.key]) names.push(resolveName(tpl).toLowerCase());
    });
    customFolders.value.forEach((cf, i) => {
        if (i !== ignoreCustomIndex && cf.name.trim()) names.push(cf.name.trim().toLowerCase());
    });
    return names;
}

const maxCustom = computed(() => validation.value?.max_custom_per_case ?? 10);
const canAddCustom = computed(() => customFolders.value.length < maxCustom.value);
const totalSelected = computed(() => {
    const std = Object.values(checked.value).filter(Boolean).length;
    return std + customFolders.value.length;
});
const noSelection = computed(() => totalSelected.value === 0);

function addCustom(): void {
    if (!canAddCustom.value) {
        newCustomError.value = t('wizard.step_folders.max_custom_reached', { n: maxCustom.value });
        return;
    }
    const err = validateName(newCustomName.value);
    if (err) {
        newCustomError.value = err;
        return;
    }
    customFolders.value.push({ name: newCustomName.value.trim(), error: null });
    newCustomName.value = '';
    newCustomError.value = null;
    emitSelection();
}

function removeCustom(index: number): void {
    customFolders.value.splice(index, 1);
    emitSelection();
}

function updateCustom(index: number, value: string): void {
    customFolders.value[index].name = value;
    customFolders.value[index].error = validateName(value, index);
    emitSelection();
}

function emitSelection(): void {
    const result: CaseFolderInput[] = [];
    templates.value.forEach((tpl) => {
        if (checked.value[tpl.key]) {
            result.push({ name: resolveName(tpl), category: tpl.category, is_custom: false });
        }
    });
    customFolders.value.forEach((cf) => {
        if (cf.name.trim() && !cf.error) {
            result.push({ name: cf.name.trim(), category: null, is_custom: true });
        }
    });
    emit('update:modelValue', result);
}

watch(() => props.locale, () => emitSelection());
</script>

<template>
    <div class="folder-selector">
        <div v-if="loading" class="text-center py-8 text-gray-500 dark:text-gray-400">
            {{ t('common.loading') }}
        </div>

        <template v-else>
            <!-- Predefined catalog -->
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-6">
                <label
                    v-for="tpl in templates"
                    :key="tpl.key"
                    class="flex items-center gap-2 p-3 border border-gray-200 dark:border-gray-700 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                    :class="{ 'border-primary bg-primary/5': checked[tpl.key] }"
                >
                    <input
                        type="checkbox"
                        v-model="checked[tpl.key]"
                        class="form-checkbox text-primary rounded"
                        @change="emitSelection"
                    />
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        {{ resolveName(tpl) }}
                    </span>
                </label>
            </div>

            <!-- Custom folders -->
            <div class="mb-4">
                <h4 class="font-semibold text-gray-800 dark:text-white mb-3">
                    {{ t('wizard.step_folders.add_custom') }}
                    <span class="text-xs font-normal text-gray-500 ml-2">
                        ({{ customFolders.length }}/{{ maxCustom }})
                    </span>
                </h4>

                <ul v-if="customFolders.length > 0" class="mb-3 space-y-2">
                    <li v-for="(cf, i) in customFolders" :key="i" class="flex flex-col gap-1">
                        <div class="flex items-center gap-2">
                            <input
                                type="text"
                                :value="cf.name"
                                class="form-input flex-1"
                                :class="{ 'border-danger': cf.error }"
                                @input="updateCustom(i, ($event.target as HTMLInputElement).value)"
                            />
                            <button
                                type="button"
                                class="text-danger hover:underline text-sm whitespace-nowrap"
                                @click="removeCustom(i)"
                            >
                                {{ t('common.remove') }}
                            </button>
                        </div>
                        <p v-if="cf.error" class="text-danger text-xs">{{ cf.error }}</p>
                    </li>
                </ul>

                <div class="flex items-center gap-2">
                    <input
                        v-model="newCustomName"
                        type="text"
                        :placeholder="t('wizard.step_folders.custom_placeholder')"
                        :disabled="!canAddCustom"
                        class="form-input flex-1"
                        @keyup.enter="addCustom"
                    />
                    <button
                        type="button"
                        class="btn btn-primary whitespace-nowrap"
                        :disabled="!canAddCustom || !newCustomName.trim()"
                        @click="addCustom"
                    >
                        + {{ t('common.add') }}
                    </button>
                </div>
                <p v-if="newCustomError" class="text-danger text-xs mt-1">{{ newCustomError }}</p>
            </div>

            <!-- Empty selection warning -->
            <div
                v-if="noSelection"
                class="bg-warning/10 border border-warning/30 text-warning-dark dark:text-warning p-3 rounded-lg text-sm"
            >
                {{ t('wizard.step_folders.no_folders_warning') }}
            </div>

            <div v-else class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                {{ t('wizard.step_folders.total_selected', { n: totalSelected }) }}
            </div>
        </template>
    </div>
</template>
