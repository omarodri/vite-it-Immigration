<template>
    <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
            {{ $t('wizard.step5.title') }}
        </h3>
        <p class="text-gray-500 dark:text-gray-400 mb-6">
            {{ $t('wizard.step5.description') }}
        </p>

        <div class="space-y-6">
            <!-- Case Type Section -->
            <section class="panel" aria-labelledby="case-type-heading">
                <div class="flex justify-between items-center mb-4">
                    <h4 id="case-type-heading" class="font-semibold text-gray-900 dark:text-white">
                        {{ $t('wizard.step5.case_type') }}
                    </h4>
                    <button
                        type="button"
                        class="text-primary text-sm hover:underline"
                        :aria-label="`${$t('wizard.edit')} ${$t('wizard.step5.case_type')}`"
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
            <section class="panel" aria-labelledby="client-heading">
                <div class="flex justify-between items-center mb-4">
                    <h4 id="client-heading" class="font-semibold text-gray-900 dark:text-white">
                        {{ $t('wizard.step5.client') }}
                    </h4>
                    <button
                        type="button"
                        class="text-primary text-sm hover:underline"
                        :aria-label="`${$t('wizard.edit')} ${$t('wizard.step5.client')}`"
                        @click="wizard.goToStep(2)"
                    >
                        {{ $t('wizard.edit') }}
                    </button>
                </div>
                <div v-if="selectedClient" class="flex items-center gap-4">
                    <div
                        class="w-12 h-12 rounded-full bg-primary/20 text-primary flex items-center justify-center font-semibold text-lg"
                    >
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
            <section class="panel" aria-labelledby="companions-heading">
                <div class="flex justify-between items-center mb-4">
                    <h4 id="companions-heading" class="font-semibold text-gray-900 dark:text-white">
                        {{ $t('wizard.step5.companions') }}
                    </h4>
                    <button
                        type="button"
                        class="text-primary text-sm hover:underline"
                        :aria-label="`${$t('wizard.edit')} ${$t('wizard.step5.companions')}`"
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
                    {{ $t('wizard.step5.no_companions_selected') }}
                </p>
            </section>

            <!-- Details Section -->
            <section class="panel" aria-labelledby="details-heading">
                <div class="flex justify-between items-center mb-4">
                    <h4 id="details-heading" class="font-semibold text-gray-900 dark:text-white">
                        {{ $t('wizard.step5.details') }}
                    </h4>
                    <button
                        type="button"
                        class="text-primary text-sm hover:underline"
                        :aria-label="`${$t('wizard.edit')} ${$t('wizard.step5.details')}`"
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

            <!-- Confirmation Message -->
            <div class="p-4 bg-warning/10 border border-warning/20 rounded-lg">
                <p class="text-sm text-warning-dark dark:text-warning">
                    {{ $t('wizard.step5.confirmation_message') }}
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
import type { Client } from '@/types/client';
import type { Companion } from '@/types/companion';
import type { StaffMember } from '@/types/wizard';

const { t } = useI18n();

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
        temporary_residence: 'bg-info/20 text-info',
        permanent_residence: 'bg-success/20 text-success',
        humanitarian: 'bg-warning/20 text-warning',
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
