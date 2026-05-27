<template>
    <div>
        <div class="flex justify-between items-center mb-4">
            <h5 class="text-lg font-semibold">{{ $t('clients.family_companions') }}</h5>
            <button
                v-if="wizard.state.clientId && !isCompanyCase"
                v-can="'companions.create'"
                type="button"
                class="btn btn-primary btn-sm gap-2"
                @click="openCompanionModal()"
            >
                <icon-plus class="w-4 h-4" />
                {{ $t('companions.add') }}
            </button>
        </div>

        <!-- ============================================================
             BENEFICIARY EMPLOYEE SECTION (Company-type clients only — DT-09)
             ============================================================ -->
        <section
            v-if="wizard.state.clientId && isCompanyCase"
            class="mb-6 p-4 border-2 rounded-lg"
            :class="hasBeneficiary
                ? 'border-success/40 bg-success/5'
                : wizard.state.primaryApplicantCompanionId
                    ? 'border-gray-300/60 bg-gray-50 dark:border-gray-600/60 dark:bg-gray-800/30'
                    : 'border-warning/40 bg-warning/5'"
            aria-labelledby="beneficiary-section-heading"
        >
            <div class="flex justify-between items-start gap-3 mb-3">
                <div class="flex-1 min-w-0">
                    <h4 id="beneficiary-section-heading" class="font-semibold text-gray-900 dark:text-white">
                        {{ $t('wizard.step_companions.beneficiary_section_title') }}
                        <span class="text-danger ml-1">*</span>
                    </h4>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ $t('wizard.step_companions.beneficiary_section_desc') }}
                    </p>
                </div>
                <!-- Add button always visible so user can add a new employee at any time -->
                <button
                    v-can="'companions.create'"
                    type="button"
                    class="btn btn-primary btn-sm gap-2 shrink-0"
                    @click="openBeneficiaryModal()"
                >
                    <icon-plus class="w-4 h-4" />
                    {{ $t('wizard.step_companions.add_beneficiary') }}
                </button>
            </div>

            <!-- Dropdown to pick from existing beneficiary employees -->
            <div v-if="beneficiaryCompanions.length > 0" class="mb-3">
                <select
                    v-model="selectedBeneficiaryId"
                    class="form-select"
                    :class="{ 'border-danger': !hasBeneficiary && validationAttempted }"
                >
                    <option value="">{{ $t('wizard.step_companions.select_beneficiary') }}</option>
                    <option v-for="c in beneficiaryCompanions" :key="c.id" :value="c.id">
                        {{ c.full_name }}<template v-if="c.iuc"> — IUC: {{ c.iuc }}</template>
                    </option>
                </select>
            </div>

            <!-- Selected beneficiary card -->
            <div v-if="hasBeneficiary && beneficiaryCompanion" class="flex items-center gap-3 p-3 rounded-lg bg-white dark:bg-gray-900 border border-success/30">
                <div class="shrink-0 w-10 h-10 rounded-full bg-success/20 text-success flex items-center justify-center font-semibold">
                    {{ beneficiaryCompanion.initials || getInitials(beneficiaryCompanion.first_name, beneficiaryCompanion.last_name) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-900 dark:text-white truncate">
                        {{ beneficiaryCompanion.full_name }}
                        <span class="badge badge-outline-success text-xs ml-2 align-middle">
                            {{ $t('wizard.step_companions.beneficiary_badge') }}
                        </span>
                    </p>
                    <p v-if="beneficiaryCompanion.email || beneficiaryCompanion.iuc" class="text-xs text-gray-500 dark:text-gray-400 truncate">
                        <span v-if="beneficiaryCompanion.iuc">IUC: {{ beneficiaryCompanion.iuc }}</span>
                        <span v-if="beneficiaryCompanion.iuc && beneficiaryCompanion.email"> · </span>
                        <span v-if="beneficiaryCompanion.email">{{ beneficiaryCompanion.email }}</span>
                    </p>
                </div>
                <button
                    v-can="'companions.delete'"
                    type="button"
                    class="p-1.5 text-gray-400 hover:text-danger transition-colors rounded-lg hover:bg-danger/10"
                    :title="$t('companions.confirm_delete')"
                    @click="confirmDeleteCompanion(beneficiaryCompanion.id)"
                >
                    <icon-trash class="w-4 h-4" />
                </button>
            </div>

            <!-- No beneficiary selected yet -->
            <p
                v-else-if="!hasBeneficiary"
                class="text-sm flex items-center gap-2 text-warning"
            >
                <icon-info-triangle class="w-4 h-4 shrink-0" aria-hidden="true" />
                {{ $t('wizard.step_companions.beneficiary_required') }}
            </p>
        </section>

        <!-- ============================================================
             PRIMARY APPLICANT SELECTION — person cases only
             For company cases the beneficiary IS the primary applicant;
             the green "Empleado Beneficiario" box above handles this.
             ============================================================ -->
        <div
            v-if="wizard.state.clientId && !loading && companions.length > 0 && !isCompanyCase"
            class="mb-6 p-4 border border-primary/30 rounded-lg bg-primary/5"
        >
            <h4 class="font-semibold text-gray-900 dark:text-white mb-1">
                {{ isCompanyCase ? $t('wizard.step_companions.primary_applicant_company_title') : $t('wizard.step3.primary_applicant_title') }}
                <span class="text-danger ml-1">*</span>
            </h4>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                {{ isCompanyCase ? $t('wizard.step_companions.primary_applicant_company_desc') : $t('wizard.step3.primary_applicant_desc') }}
            </p>
            <select
                v-model="primaryApplicantSelection"
                class="form-select"
                :class="{ 'border-danger': validationAttempted && !primaryApplicantSelection }"
            >
                <option value="">{{ $t('wizard.step3.select_primary_applicant') }}</option>
                <!-- Client as primary applicant only makes sense for person clients -->
                <option v-if="!isCompanyCase" :value="`client:${wizard.state.clientId}`">
                    {{ selectedClientName }} ({{ $t('wizard.step3.client_label') }})
                </option>
                <!-- For company cases show ALL companions; for person cases show only selected ones -->
                <option
                    v-for="companion in primaryApplicantCompanions"
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

        <!-- Family Members section header for company cases -->
        <div v-else-if="isCompanyCase" class="mb-4">
            <div class="flex justify-between items-center mb-2">
                <h4 class="font-semibold text-gray-900 dark:text-white">
                    {{ $t('wizard.step_companions.family_section_title') }}
                </h4>
                <button
                    v-can="'companions.create'"
                    type="button"
                    class="btn btn-outline-primary btn-sm gap-2"
                    @click="openCompanionModal()"
                >
                    <icon-plus class="w-4 h-4" />
                    {{ $t('companions.add') }}
                </button>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $t('wizard.step_companions.family_section_desc') }}
            </p>
        </div>

        <!-- No Companions (excluding beneficiary) -->
        <div
            v-if="!loading && wizard.state.clientId && nonBeneficiaryCompanions.length === 0 && (!isCompanyCase || hasBeneficiary)"
            class="text-center py-10"
            role="status"
            aria-live="polite"
        >
            <icon-users class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" aria-hidden="true" />
            <p class="text-gray-600 dark:text-gray-400 mb-2">
                {{ $t('wizard.step3.no_companions') }}
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-500">
                {{ $t('wizard.step3.skip_message') }}
            </p>
        </div>

        <!-- Companions List (excluding beneficiary, which has its own dedicated section) -->
        <fieldset v-else-if="!loading && nonBeneficiaryCompanions.length > 0" class="space-y-3">
            <h3 v-if="!isCompanyCase" class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                {{ $t('wizard.step3.title') }}
            </h3>
            <p v-if="!isCompanyCase" class="text-gray-500 dark:text-gray-400 mb-6">
                {{ $t('wizard.step3.description') }}
            </p>
            <legend class="sr-only">{{ $t('wizard.step3.title') }}</legend>

            <!-- Client shown as dependent when a companion is the primary applicant (non-company cases only) -->
            <div
                v-if="!isCompanyCase && wizard.state.primaryApplicantType === 'companion' && selectedClientName"
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
                <div class="shrink-0 w-10 h-10 rounded-full bg-secondary/20 text-secondary flex items-center justify-center font-semibold text-sm">
                    {{ clientInitials }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-900 dark:text-white text-sm truncate">{{ selectedClientName }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('wizard.step3.client_label') }}</p>
                </div>
            </div>

            <CompanionCheckbox
                v-for="companion in nonBeneficiaryCompanions"
                :key="companion.id"
                :companion="companion"
                :is-selected="isSelected(companion.id)"
                :show-delete="true"
                @toggle="toggleCompanion"
                @delete="confirmDeleteCompanion"
            />
        </fieldset>

        <!-- Selection Summary -->
        <div v-if="nonBeneficiaryCompanions.length > 0" class="mt-6 p-4 bg-info/10 rounded-lg" role="status" aria-live="polite">
            <p class="text-sm text-info">
                <strong>{{ selectedNonBeneficiaryCount }}</strong> {{ $t('wizard.step3.companions_selected') }}
            </p>
        </div>

        <!-- Companion Form Modal (shared component) -->
        <CompanionFormModal
            v-model:show="showCompanionModal"
            :client-id="wizard.state.clientId ?? 0"
            :companion="null"
            :preset-relationship="presetRelationshipForModal"
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
import type { Companion, RelationshipType } from '@/types/companion';
import CompanionCheckbox from '../components/CompanionCheckbox.vue';
import CompanionFormModal from '@/components/companions/CompanionFormModal.vue';
import IconUsers from '@/components/icon/icon-users.vue';
import IconInfoTriangle from '@/components/icon/icon-info-triangle.vue';
import IconPlus from '@/components/icon/icon-plus.vue';
import IconTrash from '@/components/icon/icon-trash.vue';

// Get wizard from parent
const wizard = inject<ReturnType<typeof import('@/composables/useCaseWizard').useCaseWizard>>('wizard')!;

const { t } = useI18n();
const companionStore = useCompanionStore();
const { success, error: showError, confirm: confirmDialog } = useNotification();

const companions = ref<Companion[]>([]);
const loading = ref(false);
const showCompanionModal = ref(false);
const presetRelationshipForModal = ref<RelationshipType | null>(null);

const validationAttempted = ref(false);
const selectedClientName = ref('');

// =============================================
// Company-case / Beneficiary helpers (DT-09)
// =============================================

const isCompanyCase = computed<boolean>(() => wizard.state.clientType === 'company');

// All companions with relationship='beneficiary' (a company client can have multiple employees).
const beneficiaryCompanions = computed<Companion[]>(() =>
    companions.value.filter((c) => c.relationship === 'beneficiary')
);

// The currently selected beneficiary companion (matched by primaryApplicantCompanionId).
const beneficiaryCompanion = computed<Companion | null>(() => {
    const id = wizard.state.primaryApplicantCompanionId;
    if (!id) return null;
    return companions.value.find((c) => c.id === id && c.relationship === 'beneficiary') ?? null;
});

// True when the user has actually selected a beneficiary for this case.
const hasBeneficiary = computed<boolean>(() => beneficiaryCompanion.value !== null);

// Writable computed — drives the beneficiary <select> in the green box.
const selectedBeneficiaryId = computed<number | ''>({
    get(): number | '' {
        return wizard.state.primaryApplicantCompanionId ?? '';
    },
    set(value: number | '') {
        const id = value === '' ? null : Number(value);
        wizard.state.primaryApplicantType = id ? 'companion' : 'client';
        wizard.state.primaryApplicantCompanionId = id;
        if (id) {
            if (!wizard.state.selectedCompanionIds.includes(id)) {
                wizard.toggleCompanion(id);
            }
            wizard.state.clientIsDependent = false;
        }
        wizard.saveToSession();
    },
});

const nonBeneficiaryCompanions = computed<Companion[]>(() => {
    return companions.value.filter((c) => c.relationship !== 'beneficiary');
});

const selectedNonBeneficiaryCount = computed<number>(() => {
    return wizard.state.selectedCompanionIds.filter((id) => {
        const c = companions.value.find((x) => x.id === id);
        return c && c.relationship !== 'beneficiary';
    }).length;
});

// For the primary-applicant dropdown:
// - Company cases → show ALL companions so the user can pick any one as the
//   employee/primary-applicant (the beneficiary section auto-selects, but
//   the dropdown allows manual override when companions already exist).
// - Non-company cases → show only companions already included in the case.
const primaryApplicantCompanions = computed(() =>
    isCompanyCase.value ? companions.value : selectedCompanions.value
);

// =============================================
// Primary applicant (non-company only)
// =============================================

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
        // For company cases, the chosen companion must be included in the case.
        if (isCompanyCase.value && type === 'companion') {
            const companionId = parseInt(id);
            if (!wizard.state.selectedCompanionIds.includes(companionId)) {
                wizard.toggleCompanion(companionId);
            }
        }
        wizard.saveToSession();
    },
});

const selectedCompanions = computed(() =>
    companions.value.filter((c) => wizard.state.selectedCompanionIds.includes(c.id))
);

const clientInitials = computed(() => {
    const parts = selectedClientName.value.trim().split(/\s+/);
    return parts.map((p) => p[0] || '').join('').slice(0, 2).toUpperCase();
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

function getInitials(firstName: string, lastName: string): string {
    return `${firstName?.charAt(0) || ''}${lastName?.charAt(0) || ''}`.toUpperCase();
}

function openCompanionModal() {
    presetRelationshipForModal.value = null;
    showCompanionModal.value = true;
}

function openBeneficiaryModal() {
    presetRelationshipForModal.value = 'beneficiary';
    showCompanionModal.value = true;
}

async function onCompanionSaved(companion: Companion) {
    showCompanionModal.value = false;
    presetRelationshipForModal.value = null;

    await companionStore.fetchCompanions(wizard.state.clientId!);
    companions.value = [...companionStore.companions];

    // Auto-select the newly created companion
    if (!wizard.state.selectedCompanionIds.includes(companion.id)) {
        wizard.toggleCompanion(companion.id);
    }

    // DT-09: For company cases the beneficiary is always the primary applicant.
    if (isCompanyCase.value && companion.relationship === 'beneficiary') {
        wizard.state.primaryApplicantType = 'companion';
        wizard.state.primaryApplicantCompanionId = companion.id;
        // The "client" of a company case is the company itself — never a
        // dependent person on the application.
        wizard.state.clientIsDependent = false;
        wizard.saveToSession();
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
            // If the deleted companion was the selected primary applicant, reset it.
            if (companion.relationship === 'beneficiary' && isCompanyCase.value &&
                wizard.state.primaryApplicantCompanionId === id) {
                wizard.state.primaryApplicantType = 'client';
                wizard.state.primaryApplicantCompanionId = null;
                wizard.saveToSession();
            }
            success(t('companions.deleted_successfully'));
        } catch (err: any) {
            showError(err.response?.data?.message || t('companions.delete_failed'));
        }
    }
}

// =============================================
// Companion list lifecycle
// =============================================

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
                selectedClientName.value = client.display_name || client.full_name || `${client.first_name ?? ''} ${client.last_name ?? ''}`.trim();
                // Keep wizard.clientType in sync when this step is the entry
                // point after a session restore.
                if (client?.type && wizard.state.clientType !== client.type) {
                    wizard.setClientType(client.type);
                }
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

// For company cases: enforce clientIsDependent=false and ensure the restored
// primaryApplicantCompanionId (from sessionStorage) is included in selectedCompanionIds.
// No auto-assignment to the first beneficiary — the user must pick explicitly.
watch(
    [isCompanyCase, companions],
    ([company]) => {
        if (!company) return;
        wizard.state.clientIsDependent = false;
        const id = wizard.state.primaryApplicantCompanionId;
        if (id) {
            wizard.state.primaryApplicantType = 'companion';
            if (!wizard.state.selectedCompanionIds.includes(id)) {
                wizard.toggleCompanion(id);
            }
        }
        wizard.saveToSession();
    },
    { immediate: true }
);
</script>
