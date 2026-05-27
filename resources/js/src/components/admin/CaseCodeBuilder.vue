<template>
    <div class="space-y-6">
        <!-- 1. Drag & Drop area -->
        <div>
            <label class="block text-sm font-medium mb-1">{{ $t('settings.case_code.blocks_order') }}</label>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">{{ $t('settings.case_code.drag_hint') }}</p>

            <VueDraggable
                v-model="blocks"
                :animation="200"
                item-key="key"
                class="flex flex-wrap gap-2 p-4 bg-gray-50 dark:bg-[#1b2e4b] border border-dashed border-gray-300 dark:border-gray-600 rounded-lg min-h-[70px]"
                @end="markDirty"
            >
                <div
                    v-for="block in blocks"
                    :key="block.key"
                    class="cursor-move flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-full shadow select-none"
                    :title="$t(`settings.case_code.block.${block.key}_desc`)"
                >
                    <icon-menu class="w-4 h-4 opacity-70" />
                    <span class="font-mono font-bold text-sm">{{ block.key }}</span>
                    <icon-info-circle class="w-3.5 h-3.5 opacity-70" />
                </div>
            </VueDraggable>

            <!-- Block legend -->
            <div class="mt-3 flex flex-wrap gap-x-6 gap-y-1">
                <div v-for="block in blocks" :key="`legend-${block.key}`" class="text-xs text-gray-500 dark:text-gray-400">
                    <span class="font-mono font-semibold text-gray-700 dark:text-gray-300">{{ block.key }}</span>
                    = {{ $t(`settings.case_code.block.${block.key}`) }}
                    <span class="text-gray-400 dark:text-gray-500">({{ block.example }})</span>
                </div>
            </div>
        </div>

        <!-- 2. Separator selector -->
        <div>
            <label class="block text-sm font-medium mb-2">{{ $t('settings.case_code.separator') }}</label>
            <div class="flex flex-wrap gap-4">
                <label
                    v-for="opt in separatorOptions"
                    :key="`sep-${opt.value}`"
                    class="flex items-center gap-2 cursor-pointer"
                >
                    <input
                        type="radio"
                        v-model="separator"
                        :value="opt.value"
                        class="form-radio"
                        @change="markDirty"
                    />
                    <span class="text-sm font-mono">{{ opt.label }}</span>
                </label>
            </div>
        </div>

        <!-- 3. Include NAME in directory toggle -->
        <div class="flex items-start justify-between gap-4 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/40 rounded-lg">
            <div class="flex-1 min-w-0">
                <p class="font-medium text-sm flex items-center gap-2">
                    <icon-folder class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" />
                    {{ $t('settings.case_code.include_name') }}
                </p>
                <p class="text-xs text-amber-700 dark:text-amber-400 mt-1 font-medium">
                    {{ $t('settings.case_code.include_name_directory_only') }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ $t('settings.case_code.include_name_hint') }}
                </p>
            </div>
            <label class="w-12 h-6 relative inline-block shrink-0 mt-0.5">
                <input
                    type="checkbox"
                    v-model="includeName"
                    class="custom_switch absolute w-full h-full opacity-0 z-10 cursor-pointer peer"
                    @change="markDirty"
                />
                <span
                    class="bg-gray-300 dark:bg-gray-700 block h-full rounded-full before:absolute before:left-1 before:bg-white dark:before:bg-white-dark dark:peer-checked:before:bg-white before:bottom-1 before:w-4 before:h-4 before:rounded-full peer-checked:before:left-7 peer-checked:bg-primary before:transition-all before:duration-300"
                ></span>
            </label>
        </div>

        <!-- 4. Live previews -->
        <!-- 4a. Case code preview (always visible — blocks only) -->
        <div class="border-2 border-dashed border-primary/40 bg-primary/5 dark:bg-primary/10 p-5 rounded-xl">
            <p class="text-xs uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-2">
                {{ $t('settings.case_code.preview') }}
            </p>
            <p class="text-2xl font-mono font-bold text-primary break-all">{{ codePreview }}</p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">{{ $t('settings.case_code.preview_note') }}</p>
        </div>

        <!-- 4b. Directory name preview (only when include_name is on) -->
        <div v-if="includeName" class="border border-dashed border-amber-400/60 bg-amber-50 dark:bg-amber-900/20 p-4 rounded-xl">
            <p class="text-xs uppercase tracking-widest text-amber-600 dark:text-amber-400 mb-2 flex items-center gap-1">
                <icon-folder class="w-3.5 h-3.5" />
                {{ $t('settings.case_code.dir_preview') }}
            </p>
            <p class="text-base font-mono font-semibold text-amber-700 dark:text-amber-300 break-all">{{ dirPreview }}</p>
            <p class="text-xs text-amber-600/70 dark:text-amber-500/70 mt-1">{{ $t('settings.case_code.dir_preview_note') }}</p>
        </div>

        <!-- 5. Actions -->
        <div class="flex justify-end gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
            <button
                type="button"
                class="btn btn-outline-secondary"
                :disabled="!isDirty || isSaving"
                @click="resetToInitial"
            >
                {{ $t('common.cancel') }}
            </button>
            <button
                type="button"
                class="btn btn-primary"
                :disabled="!isDirty || isSaving"
                @click="confirmAndSave"
            >
                <span
                    v-if="isSaving"
                    class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 inline-block mr-2 align-middle"
                ></span>
                {{ $t('common.save') }}
            </button>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';
import { VueDraggable } from 'vue-draggable-plus';

import { useTenantStore } from '@/stores/tenant';
import { useNotification } from '@/composables/useNotification';

import IconMenu from '@/components/icon/icon-menu.vue';
import IconInfoCircle from '@/components/icon/icon-info-circle.vue';
import IconFolder from '@/components/icon/icon-folder.vue';

import type { CaseCodeBlockKey, CaseCodeSeparator } from '@/services/tenantService';

interface CaseCodeBlock {
    key: CaseCodeBlockKey;
    example: string;
}

const props = withDefaults(
    defineProps<{
        initialBlocks?: CaseCodeBlockKey[];
        initialSeparator?: CaseCodeSeparator;
        initialIncludeName?: boolean;
    }>(),
    {
        initialBlocks: () => ['YY', 'TT', 'AAAA', 'NNNN'] as CaseCodeBlockKey[],
        initialSeparator: '-' as CaseCodeSeparator,
        initialIncludeName: false,
    },
);

const emit = defineEmits<{
    saved: [payload: { blocks: CaseCodeBlockKey[]; separator: CaseCodeSeparator; includeName: boolean }];
}>();

const { t } = useI18n();
const tenantStore = useTenantStore();
const notification = useNotification();

// --- Constants ---------------------------------------------------------
const PREVIEW_DATA: Record<CaseCodeBlockKey, string> = {
    YY: '26',
    TT: 'SP',
    AAAA: 'RODR',
    NNNN: '0005',
};

const PREVIEW_NAME = 'Omar-Andres-Rodriguez-Perez';

// --- Reactive state ----------------------------------------------------
const blocks = ref<CaseCodeBlock[]>([]);
const separator = ref<CaseCodeSeparator>('-');
const includeName = ref(false);
const isDirty = ref(false);
const isSaving = ref(false);

const separatorOptions = computed(() => [
    { value: '-' as CaseCodeSeparator, label: t('settings.case_code.separator_dash') },
    { value: '_' as CaseCodeSeparator, label: t('settings.case_code.separator_underscore') },
    { value: '' as CaseCodeSeparator, label: t('settings.case_code.separator_none') },
]);

// --- Computed ----------------------------------------------------------
/** Case code preview — blocks only, NOMBRE never included */
const codePreview = computed<string>(() => {
    const parts = blocks.value.map((b) => PREVIEW_DATA[b.key]);
    return parts.join(separator.value);
});

/** Directory name preview — code + applicant name when include_name is on */
const dirPreview = computed<string>(() => {
    const sep = separator.value !== '' ? separator.value : '-';
    return codePreview.value + sep + PREVIEW_NAME;
});

// --- Helpers -----------------------------------------------------------
function buildBlocks(keys: CaseCodeBlockKey[]): CaseCodeBlock[] {
    return keys.map((key) => ({ key, example: PREVIEW_DATA[key] }));
}

function applyInitial() {
    blocks.value = buildBlocks(
        props.initialBlocks && props.initialBlocks.length > 0
            ? props.initialBlocks
            : ['YY', 'TT', 'AAAA', 'NNNN'],
    );
    separator.value = props.initialSeparator ?? '-';
    includeName.value = props.initialIncludeName ?? false;
    isDirty.value = false;
}

function markDirty() {
    isDirty.value = true;
}

function resetToInitial() {
    applyInitial();
}

async function confirmAndSave() {
    if (!isDirty.value || isSaving.value) return;

    // Local UX guard: NNNN must be present (backend also enforces)
    const hasNnnn = blocks.value.some((b) => b.key === 'NNNN');
    if (!hasNnnn) {
        await Swal.fire({
            icon: 'error',
            title: t('settings.case_code.must_include_nnnn'),
        });
        return;
    }

    const result = await Swal.fire({
        icon: 'warning',
        title: t('settings.case_code.confirm_title'),
        html: `<p>${t('settings.case_code.confirm_body')}</p>
               <p class="mt-3 font-mono text-lg font-bold" style="color:#4361ee">${codePreview.value}</p>`,
        showCancelButton: true,
        confirmButtonText: t('common.save'),
        cancelButtonText: t('common.cancel'),
        confirmButtonColor: '#4361ee',
        reverseButtons: true,
        padding: '2em',
    });

    if (!result.isConfirmed) return;

    isSaving.value = true;
    try {
        const payload = {
            case_code_blocks: blocks.value.map((b) => b.key),
            case_code_separator: separator.value,
            case_code_include_name: includeName.value,
        };

        const res = await tenantStore.updateCaseCodePattern(payload);

        if (!res.success) {
            const errMsg = res.validationErrors
                ? Object.values(res.validationErrors).flat().join('<br>')
                : res.error ?? t('common.save_failed');
            await Swal.fire({
                icon: 'error',
                title: t('common.save_failed'),
                html: errMsg,
            });
            return;
        }

        isDirty.value = false;
        emit('saved', {
            blocks: blocks.value.map((b) => b.key),
            separator: separator.value,
            includeName: includeName.value,
        });

        notification.success(t('settings.case_code.saved_successfully'));
    } catch (err: any) {
        notification.showApiError?.(err, t('common.save_failed'));
    } finally {
        isSaving.value = false;
    }
}

// --- Lifecycle ---------------------------------------------------------
onMounted(() => {
    applyInitial();
});

// React if parent updates props (e.g. after async tenant fetch)
watch(
    () => [props.initialBlocks, props.initialSeparator, props.initialIncludeName],
    () => {
        if (!isDirty.value) applyInitial();
    },
);
</script>
