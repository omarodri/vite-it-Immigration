<template>
    <div>
        <div class="flex justify-between items-center mb-4">
            <h5 class="text-lg font-semibold">{{ $t('clients.family_companions') }}</h5>
            <button
                v-if="wizard.state.clientId"
                v-can="'companions.create'"
                type="button"
                class="btn btn-primary btn-sm gap-2"
                @click="openCompanionModal()"
            >
                <icon-plus class="w-4 h-4" />
                {{ $t('companions.add') }}
            </button>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="space-y-3">
            <div v-for="i in 3" :key="i" class="animate-pulse">
                <div class="h-20 bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
            </div>
        </div>

        <!-- No Client Selected -->
        <div v-else-if="!wizard.state.clientId" class="text-center py-10">
            <icon-info-triangle class="w-16 h-16 mx-auto text-warning mb-4" />
            <p class="text-gray-500 dark:text-gray-400">
                {{ $t('wizard.step3.select_client_first') }}
            </p>
        </div>

        <!-- No Companions -->
        <div v-else-if="companions.length === 0" class="text-center py-10" role="status" aria-live="polite">
            <icon-users class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" aria-hidden="true" />
            <p class="text-gray-600 dark:text-gray-400 mb-2">
                {{ $t('wizard.step3.no_companions') }}
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-500">
                {{ $t('wizard.step3.skip_message') }}
            </p>
        </div>

        <!-- Companions List -->
        <fieldset v-else class="space-y-3">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                {{ $t('wizard.step3.title') }}
            </h3>
            <p class="text-gray-500 dark:text-gray-400 mb-6">
                {{ $t('wizard.step3.description') }}
            </p>
            <legend class="sr-only">{{ $t('wizard.step3.title') }}</legend>
            <CompanionCheckbox
                v-for="companion in companions"
                :key="companion.id"
                :companion="companion"
                :is-selected="isSelected(companion.id)"
                :show-delete="true"
                @toggle="toggleCompanion"
                @delete="confirmDeleteCompanion"
            />
        </fieldset>

        <!-- Selection Summary -->
        <div v-if="companions.length > 0" class="mt-6 p-4 bg-info/10 rounded-lg" role="status" aria-live="polite">
            <p class="text-sm text-info">
                <strong>{{ selectedCount }}</strong> {{ $t('wizard.step3.companions_selected') }}
            </p>
        </div>

        <!-- Companion Form Modal (shared component) -->
        <CompanionFormModal
            v-model:show="showCompanionModal"
            :client-id="wizard.state.clientId ?? 0"
            :companion="null"
            @saved="onCompanionSaved"
        />
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, inject, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useCompanionStore } from '@/stores/companion';
import { useNotification } from '@/composables/useNotification';
import type { Companion } from '@/types/companion';
import CompanionCheckbox from '../components/CompanionCheckbox.vue';
import CompanionFormModal from '@/components/companions/CompanionFormModal.vue';
import IconUsers from '@/components/icon/icon-users.vue';
import IconInfoTriangle from '@/components/icon/icon-info-triangle.vue';
import IconPlus from '@/components/icon/icon-plus.vue';

// Get wizard from parent
const wizard = inject<ReturnType<typeof import('@/composables/useCaseWizard').useCaseWizard>>('wizard')!;

const { t } = useI18n();
const companionStore = useCompanionStore();
const { success, error: showError, confirm: confirmDialog } = useNotification();

const companions = ref<Companion[]>([]);
const loading = ref(false);
const showCompanionModal = ref(false);

const selectedCount = computed(() => wizard.state.selectedCompanionIds.length);

function isSelected(id: number): boolean {
    return wizard.state.selectedCompanionIds.includes(id);
}

function toggleCompanion(id: number) {
    wizard.toggleCompanion(id);
}

function openCompanionModal() {
    showCompanionModal.value = true;
}

async function onCompanionSaved(companion: Companion) {
    showCompanionModal.value = false;
    await companionStore.fetchCompanions(wizard.state.clientId!);
    companions.value = [...companionStore.companions];
    // Auto-select the newly created companion
    if (!wizard.state.selectedCompanionIds.includes(companion.id)) {
        wizard.toggleCompanion(companion.id);
    }
}

async function confirmDeleteCompanion(id: number) {
    const clientId = wizard.state.clientId;
    if (!clientId) return;

    const companion = companions.value.find((c) => c.id === id);
    if (!companion) return;

    const confirmed = await confirmDialog({
        title: t('companions.confirm_delete'),
        text: t('companions.delete_warning', { name: companion.full_name }),
        icon: 'warning',
        confirmButtonText: t('companions.yes_delete'),
        cancelButtonText: t('companions.cancel'),
    });

    if (confirmed) {
        try {
            await companionStore.deleteCompanion(clientId, id);
            companions.value = [...companionStore.companions];
            if (wizard.state.selectedCompanionIds.includes(id)) {
                wizard.toggleCompanion(id);
            }
            success(t('companions.deleted_successfully'));
        } catch (err: any) {
            showError(err.response?.data?.message || t('companions.delete_failed'));
        }
    }
}

watch(
    () => wizard.state.clientId,
    async (clientId) => {
        if (clientId) {
            loading.value = true;
            try {
                await companionStore.fetchCompanions(clientId);
                companions.value = companionStore.companions;
            } catch (error) {
                console.error('Failed to load companions:', error);
                companions.value = [];
            } finally {
                loading.value = false;
            }
        } else {
            companions.value = [];
        }
    },
    { immediate: true }
);
</script>
