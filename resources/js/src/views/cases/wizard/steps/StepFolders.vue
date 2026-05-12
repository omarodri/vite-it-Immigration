<script lang="ts" setup>
import { computed, inject } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMeta } from '@/composables/use-meta';
import FolderSelector from '@/components/cases/wizard/FolderSelector.vue';
import type { useCaseWizard } from '@/composables/useCaseWizard';
import type { CaseFolderInput } from '@/types/case';

const { t } = useI18n();
useMeta({ title: t('wizard.step_folders.title') });

const wizard = inject<ReturnType<typeof useCaseWizard>>('wizard')!;

const caseLocale = computed(() => wizard.state.caseDetails?.language || 'es');

const selectedFolders = computed<CaseFolderInput[]>({
    get: () => wizard.state.folders?.selected ?? [],
    set: (value) => {
        if (wizard.state.folders) {
            wizard.state.folders.selected = value;
        }
        wizard.saveToSession();
    },
});
</script>

<template>
    <div class="step-folders">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
            {{ t('wizard.step_folders.title') }}
        </h3>
        <p class="text-gray-500 dark:text-gray-400 mb-6">
            {{ t('wizard.step_folders.description') }}
        </p>

        <FolderSelector
            v-model="selectedFolders"
            :locale="caseLocale"
        />
    </div>
</template>
