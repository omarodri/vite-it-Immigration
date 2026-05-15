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

        <!-- Primary Applicant Selection -->
        <div v-if="wizard.state.clientId" class="mb-6 p-4 border border-primary/30 rounded-lg bg-primary/5">
            <h4 class="font-semibold text-gray-900 dark:text-white mb-1">
                {{ $t('wizard.step3.primary_applicant_title') }}
                <span class="text-danger ml-1">*</span>
            </h4>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                {{ $t('wizard.step3.primary_applicant_desc') }}
            </p>
            <select
                v-model="primaryApplicantSelection"
                class="form-select"
                :class="{ 'border-danger': validationAttempted && !primaryApplicantSelection }"
            >
                <option value="">{{ $t('wizard.step3.select_primary_applicant') }}</option>
                <option :value="`client:${wizard.state.clientId}`">
                    {{ selectedClientName }} ({{ $t('wizard.step3.client_label') }})
                </option>
                <option
                    v-for="companion in selectedCompanions"
                    :key="companion.id"
                    :value="`companion:${companion.id}`"
                >
                    {{ companion.full_name }} — {{ companion.relationship_label || companion.relationship }}
                </option>
            </select>
            <p v-if="validationAttempted && !primaryApplicantSelection" class="text-danger text-sm mt-1">
                {{ $t('wizard.step3.primary_applicant_required') }}
            </p>
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

            <!-- Client shown as dependent when a companion is the primary applicant (toggleable) -->
            <div
                v-if="wizard.state.primaryApplicantType === 'companion' && selectedClientName"
                role="checkbox"
                :aria-checked="wizard.state.clientIsDependent"
                tabindex="0"
                class="flex items-center gap-3 p-4 rounded-lg border cursor-pointer select-none transition-colors"
                :class="wizard.state.clientIsDependent
                    ? 'border-primary bg-primary/5 dark:bg-primary/10'
                    : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/30 opacity-60'"
                @click="toggleClientDependent"
                @keydown.space.prevent="toggleClientDependent"
                @keydown.enter.prevent="toggleClientDependent"
            >
                <!-- Checkbox indicator -->
                <div
                    class="shrink-0 w-5 h-5 rounded flex items-center justify-center border-2 transition-colors"
                    :class="wizard.state.clientIsDependent
                        ? 'bg-primary border-primary'
                        : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700'"
                >
                    <svg v-if="wizard.state.clientIsDependent" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                </div>
                <!-- Avatar -->
                <div class="shrink-0 w-10 h-10 rounded-full bg-secondary/20 text-secondary flex items-center justify-center font-semibold text-sm">
                    {{ clientInitials }}
                </div>
                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-900 dark:text-white text-sm truncate">{{ selectedClientName }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('wizard.step3.client_label') }}</p>
                </div>
            </div>

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
import clientService from '@/services/clientService';
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

const validationAttempted = ref(false);
const selectedClientName = ref('');

// Derived from wizard state — always in sync, no watcher needed
const primaryApplicantSelection = computed({
    get(): string {
        if (wizard.state.primaryApplicantType === 'companion' && wizard.state.primaryApplicantCompanionId) {
            return `companion:${wizard.state.primaryApplicantCompanionId}`;
        }
        if (wizard.state.clientId) {
            return `client:${wizard.state.clientId}`;
        }
        return '';
    },
    set(value: string) {
        if (!value) return;
        const [type, id] = value.split(':');
        wizard.state.primaryApplicantType = type as 'client' | 'companion';
        wizard.state.primaryApplicantCompanionId = type === 'companion' ? parseInt(id) : null;
        wizard.saveToSession();
    },
});

const selectedCount = computed(() => wizard.state.selectedCompanionIds.length);

const selectedCompanions = computed(() =>
    companions.value.filter(c => wizard.state.selectedCompanionIds.includes(c.id))
);

const clientInitials = computed(() => {
    const parts = selectedClientName.value.trim().split(/\s+/);
    return parts.map(p => p[0] || '').join('').slice(0, 2).toUpperCase();
});

function toggleClientDependent() {
    wizard.state.clientIsDependent = !wizard.state.clientIsDependent;
    wizard.saveToSession();
}

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

            try {
                const response = await clientService.getClient(clientId);
                const client = (response as any).data || response;
                selectedClientName.value = client.full_name || `${client.first_name} ${client.last_name}`;
            } catch (e) {
                console.error('Failed to load client name:', e);
            }

        } else {
            companions.value = [];
            selectedClientName.value = '';
        }
    },
    { immediate: true }
);
</script>
