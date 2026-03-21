<template>
    <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
            {{ $t('wizard.step6.title') }}
        </h3>
        <p class="text-gray-500 dark:text-gray-400 mb-6">
            {{ $t('wizard.step6.description') }}
        </p>

        <div class="space-y-6">
            <!-- Case Number Preview -->
            <div class="flex items-center gap-3 px-4 py-3 bg-primary/5 border border-primary/20 rounded-lg">
                <div class="shrink-0 w-9 h-9 rounded-full bg-primary/15 flex items-center justify-center">
                    <svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('wizard.step6.case_number_preview_label') }}</p>
                    <p class="font-mono font-semibold text-primary tracking-wider">{{ caseNumberPreview }}</p>
                </div>
                <p class="ltr:ml-auto rtl:mr-auto text-xs text-gray-400 dark:text-gray-500 shrink-0">
                    {{ $t('wizard.step6.case_number_preview_note') }}
                </p>
            </div>

            <!-- Case Type Section -->
            <section class="prose bg-[#f1f2f3] px-4 py- sm:px-8 sm:py-4 rounded max-w-full dark:bg-[#1b2e4b] dark:text-white-light" aria-labelledby="case-type-heading">
                <div class="flex justify-between items-center mb-4">
                    <h4 id="case-type-heading" class="font-semibold text-gray-900 dark:text-white">
                        {{ $t('wizard.step6.case_type') }}
                    </h4>
                    <button
                        type="button"
                        class="btn btn-primary mt-1"
                        :aria-label="`${$t('wizard.edit')} ${$t('wizard.step6.case_type')}`"
                        @click="wizard.goToStep(1)"
                    >
                        {{ $t('wizard.edit') }}
                    </button>
                </div>
                <div v-if="selectedCaseType" class="flex items-center gap-3">
                    <span
                        :class="[
                            'px-2 py-1 text-xs font-medium rounded-full',
                            getCategoryClass(selectedCaseType.category),
                        ]"
                    >
                        {{ selectedCaseType.category_label }}
                    </span>
                    <span class="font-medium text-gray-900 dark:text-white">
                        {{ selectedCaseType.name }}
                    </span>
                    <span class="text-sm text-gray-500">
                        ({{ selectedCaseType.code }})
                    </span>
                </div>
            </section>

            <!-- Client Section -->
            <section class="prose bg-[#f1f2f3] px-4 py- sm:px-8 sm:py-4 rounded max-w-full dark:bg-[#1b2e4b] dark:text-white-light" aria-labelledby="client-heading">
                <div class="flex justify-between items-center mb-4">
                    <h4 id="client-heading" class="font-semibold text-gray-900 dark:text-white">
                        {{ $t('wizard.step6.client') }}
                    </h4>
                    <button
                        type="button"
                        class="btn btn-primary mt-1"
                        :aria-label="`${$t('wizard.edit')} ${$t('wizard.step6.client')}`"
                        @click="wizard.goToStep(2)"
                    >
                        {{ $t('wizard.edit') }}
                    </button>
                </div>
                <div v-if="selectedClient" class="flex items-center gap-4">
                    <div
                        class="w-12 h-12 rounded-full bg-primary/20 text-primary flex items-center justify-center font-semibold text-lg">
                        {{ getInitials(selectedClient.first_name, selectedClient.last_name) }}
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ selectedClient.full_name || `${selectedClient.first_name} ${selectedClient.last_name}` }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ selectedClient.email || selectedClient.phone || '' }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- Companions Section -->
            <section class="prose bg-[#f1f2f3] px-4 py- sm:px-8 sm:py-4 rounded max-w-full dark:bg-[#1b2e4b] dark:text-white-light" aria-labelledby="companions-heading">
                <div class="flex justify-between items-center mb-4">
                    <h4 id="companions-heading" class="font-semibold text-gray-900 dark:text-white">
                        {{ $t('wizard.step6.companions') }}
                    </h4>
                    <button
                        type="button"
                        class="btn btn-primary mt-1"
                        :aria-label="`${$t('wizard.edit')} ${$t('wizard.step6.companions')}`"
                        @click="wizard.goToStep(3)"
                    >
                        {{ $t('wizard.edit') }}
                    </button>
                </div>
                <div v-if="selectedCompanions.length > 0" class="flex flex-wrap gap-2">
                    <span
                        v-for="companion in selectedCompanions"
                        :key="companion.id"
                        class="inline-flex items-center gap-2 px-3 py-1.5 bg-gray-100 dark:bg-gray-800 rounded-full"
                    >
                        <span class="w-6 h-6 rounded-full bg-secondary/20 text-secondary text-xs flex items-center justify-center font-medium">
                            {{ getInitials(companion.first_name, companion.last_name) }}
                        </span>
                        <span class="text-sm">{{ companion.full_name || `${companion.first_name} ${companion.last_name}` }}</span>
                        <span class="text-xs text-gray-500">({{ companion.relationship_label || companion.relationship }})</span>
                    </span>
                </div>
                <p v-else class="text-gray-500 dark:text-gray-400 text-sm">
                    {{ $t('wizard.step6.no_companions_selected') }}
                </p>
            </section>

            <!-- Details Section -->
            <section class="prose bg-[#f1f2f3] px-4 py- sm:px-8 sm:py-4 rounded max-w-full dark:bg-[#1b2e4b] dark:text-white-light" aria-labelledby="details-heading">
                <div class="flex justify-between items-center mb-4">
                    <h4 id="details-heading" class="font-semibold text-gray-900 dark:text-white">
                        {{ $t('wizard.step6.details') }}
                    </h4>
                    <button
                        type="button"
                        class="btn btn-primary mt-1"
                        :aria-label="`${$t('wizard.edit')} ${$t('wizard.step6.details')}`"
                        @click="wizard.goToStep(4)"
                    >
                        {{ $t('wizard.edit') }}
                    </button>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <p class="text-gray-500 dark:text-gray-400 mb-1">{{ $t('cases.priority') }}</p>
                        <span :class="getPriorityBadge(wizard.state.caseDetails.priority)">
                            {{ $t(`cases.${wizard.state.caseDetails.priority}`) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-gray-500 dark:text-gray-400 mb-1">{{ $t('cases.language') }}</p>
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ getLanguageLabel(wizard.state.caseDetails.language) }}
                        </p>
                    </div>
                    <div v-if="assignedStaff">
                        <p class="text-gray-500 dark:text-gray-400 mb-1">{{ $t('cases.assigned_to') }}</p>
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ assignedStaff.name }}
                        </p>
                    </div>
                    <div>
                        <p class="text-gray-500 dark:text-gray-400 mb-1">{{ $t('cases.service_type') }}</p>
                        <span class="badge" :class="wizard.state.caseDetails.service_type === 'pro_bono' ? 'badge-outline-success' : 'badge-outline-primary'">
                            {{ serviceTypeLabel }}
                        </span>
                    </div>
                    <div v-if="wizard.state.caseDetails.contract_number">
                        <p class="text-gray-500 dark:text-gray-400 mb-1">{{ $t('cases.contract_number') }}</p>
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ wizard.state.caseDetails.contract_number }}
                        </p>
                    </div>
                    <div v-if="canViewFees && wizard.state.caseDetails.fees !== null">
                        <p class="text-gray-500 dark:text-gray-400 mb-1">{{ $t('cases.fees') }}</p>
                        <p class="font-medium text-success">
                            ${{ Number(wizard.state.caseDetails.fees).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                        </p>
                    </div>
                </div>

                <!-- Description -->
                <div v-if="wizard.state.caseDetails.description" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <p class="text-gray-500 dark:text-gray-400 mb-2 text-sm">{{ $t('cases.description') }}</p>
                    <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                        {{ wizard.state.caseDetails.description }}
                    </p>
                </div>

                <!-- Important Dates -->
                <div v-if="wizard.state.caseDetails.important_dates.length > 0" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <p class="text-gray-500 dark:text-gray-400 mb-2 text-sm font-semibold">{{ $t('cases.important_dates') }}</p>
                    <ul class="mt-1 space-y-1">
                        <li v-for="date in wizard.state.caseDetails.important_dates" :key="date.sort_order" class="text-sm">
                            <span class="font-medium">{{ date.label }}:</span>
                            <span class="ml-1 text-gray-500">{{ date.due_date ?? '---' }}</span>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- Checklist / Lifecycle Section -->
            <section v-if="wizard.state.selectedTasks?.length > 0" class="prose bg-[#f1f2f3] px-4 py- sm:px-8 sm:py-4 rounded max-w-full dark:bg-[#1b2e4b] dark:text-white-light" aria-labelledby="checklist-heading">
                <div class="flex justify-between items-center mb-4">
                    <h4 id="checklist-heading" class="font-semibold text-gray-900 dark:text-white">
                        {{ $t('wizard.step6.checklist') }}
                    </h4>
                    <button
                        type="button"
                        class="btn btn-primary mt-1"
                        :aria-label="`${$t('wizard.edit')} ${$t('wizard.step6.checklist')}`"
                        @click="wizard.goToStep(5)"
                    >
                        {{ $t('wizard.edit') }}
                    </button>
                </div>
                <ul class="space-y-1">
                    <li v-for="(task, idx) in wizard.state.selectedTasks" :key="idx" class="text-sm flex items-center gap-2">
                        <span class="text-gray-400">{{ idx + 1 }}.</span>
                        <span>{{ task.label }}</span>
                        <span v-if="task.is_custom" class="badge badge-outline-secondary text-xs">
                            {{ $t('case_tasks.custom_task_label') }}
                        </span>
                    </li>
                </ul>
            </section>

            <!-- Confirmation Message -->
            <div class="p-4 bg-warning/10 border border-warning/20 rounded-lg">
                <p class="text-sm text-warning-dark dark:text-warning">
                    {{ $t('wizard.step6.confirmation_message') }}
                </p>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, inject, onMounted, onActivated, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import caseService from '@/services/caseService';
import clientService from '@/services/clientService';
import { useCompanionStore } from '@/stores/companion';
import userService from '@/services/userService';
import type { CaseType, CaseTypeCategory, CasePriority } from '@/types/case';
import { SERVICE_TYPE_OPTIONS } from '@/types/case';
import { usePermissions } from '@/composables/usePermissions';
import type { Client } from '@/types/client';
import type { Companion } from '@/types/companion';
import type { StaffMember } from '@/types/wizard';

const { t } = useI18n();
const { can } = usePermissions();
const canViewFees = computed(() => can('cases.view-fees'));

const serviceTypeLabel = computed(() => {
    return SERVICE_TYPE_OPTIONS.find(o => o.value === wizard.state.caseDetails.service_type)?.label ?? '';
});

// Get wizard from parent
const wizard = inject<ReturnType<typeof import('@/composables/useCaseWizard').useCaseWizard>>('wizard')!;

const selectedCaseType = ref<CaseType | null>(null);
const selectedClient = ref<Client | null>(null);
const selectedCompanions = ref<Companion[]>([]);
const staffMembers = ref<StaffMember[]>([]);

const assignedStaff = computed(() => {
    if (!wizard.state.caseDetails.assigned_to) return null;
    return staffMembers.value.find((s) => s.id === wizard.state.caseDetails.assigned_to) || null;
});

/**
 * Visual placeholder for the case number.
 * Builds: YY-TYPE-LAST4-???? using available wizard data.
 * The real consecutive is assigned server-side on save.
 */
const caseNumberPreview = computed(() => {
    const year2    = new Date().getFullYear().toString().slice(-2);
    const typeCode = selectedCaseType.value?.code ?? '???';
    const lastName = selectedClient.value?.last_name ?? '';
    const slug     = lastName
        ? lastName.toUpperCase().replace(/[^A-Z]/g, '').padEnd(4, 'X').slice(0, 4)
        : '????';
    return `${year2}-${typeCode}-${slug}-????`;
});

// Load all data on mount (first time)
onMounted(async () => {
    await loadAllData();
});

// Reload all data when component is re-activated (KeepAlive)
onActivated(async () => {
    await loadAllData();
});

async function loadAllData() {
    await Promise.all([loadCaseType(), loadClient(), loadCompanions(), loadStaff()]);
}

// Reload companions when selection changes
watch(() => wizard.state.selectedCompanionIds, loadCompanions, { deep: true });

async function loadCaseType() {
    if (wizard.state.caseTypeId) {
        try {
            selectedCaseType.value = await caseService.getCaseType(wizard.state.caseTypeId);
        } catch (error) {
            console.error('Failed to load case type:', error);
        }
    }
}

async function loadClient() {
    if (wizard.state.clientId) {
        try {
            const response = await clientService.getClient(wizard.state.clientId);
            selectedClient.value = (response as any).data || response;
        } catch (error) {
            console.error('Failed to load client:', error);
        }
    }
}

async function loadCompanions() {
    if (wizard.state.clientId && wizard.state.selectedCompanionIds.length > 0) {
        try {
            const companionStore = useCompanionStore();
            // Always fetch fresh companions to ensure we have latest data from step 3
            await companionStore.fetchCompanions(wizard.state.clientId);
            selectedCompanions.value = companionStore.companions.filter((c: Companion) =>
                wizard.state.selectedCompanionIds.includes(c.id)
            );
        } catch (error) {
            console.error('Failed to load companions:', error);
        }
    } else {
        selectedCompanions.value = [];
    }
}

async function loadStaff() {
    try {
        staffMembers.value = await userService.getStaff();
    } catch (error) {
        console.error('Failed to load staff:', error);
    }
}

function getInitials(firstName: string, lastName: string): string {
    return `${firstName?.charAt(0) || ''}${lastName?.charAt(0) || ''}`.toUpperCase();
}

function getCategoryClass(category: CaseTypeCategory): string {
    const classes: Record<CaseTypeCategory, string> = {
        'temporary_residence': 'bg-info/20 text-info',
        'permanent_residence': 'bg-success/20 text-success',
        'refugee': 'bg-warning/20 text-warning',
        'citizenship': 'bg-primary/20 text-primary',
    };
    return classes[category] || 'bg-gray-200 text-gray-700';
}

function getPriorityBadge(priority: CasePriority): string {
    const classes: Record<CasePriority, string> = {
        urgent: 'badge badge-outline-danger',
        high: 'badge badge-outline-warning',
        medium: 'badge badge-outline-info',
        low: 'badge badge-outline-secondary',
    };
    return classes[priority] || 'badge badge-outline-primary';
}

function getLanguageLabel(lang: string): string {
    const labels: Record<string, string> = {
        es: t('common.spanish'),
        en: t('common.english'),
        fr: t('common.french'),
    };
    return labels[lang] || lang;
}

function formatDate(dateStr: string): string {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString();
}
</script>
