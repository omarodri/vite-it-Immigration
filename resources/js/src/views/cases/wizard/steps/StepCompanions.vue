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

        <!-- Add Companion Modal -->
        <TransitionRoot appear :show="showCompanionModal" as="template">
            <Dialog as="div" class="relative z-50" @close="closeCompanionModal">
                <TransitionChild
                    as="template"
                    enter="duration-300 ease-out"
                    enter-from="opacity-0"
                    enter-to="opacity-100"
                    leave="duration-200 ease-in"
                    leave-from="opacity-100"
                    leave-to="opacity-0"
                >
                    <div class="fixed inset-0 bg-black/50" />
                </TransitionChild>

                <div class="fixed inset-0 overflow-y-auto">
                    <div class="flex min-h-full items-center justify-center p-4">
                        <TransitionChild
                            as="template"
                            enter="duration-300 ease-out"
                            enter-from="opacity-0 scale-95"
                            enter-to="opacity-100 scale-100"
                            leave="duration-200 ease-in"
                            leave-from="opacity-100 scale-100"
                            leave-to="opacity-0 scale-95"
                        >
                            <DialogPanel class="w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white dark:bg-gray-900 p-6 text-left align-middle shadow-xl transition-all">
                                <DialogTitle as="h3" class="text-lg font-medium leading-6 text-gray-900 dark:text-white mb-4">
                                    {{ $t('companions.add_companion') }}
                                </DialogTitle>

                                <form @submit.prevent="saveCompanion" class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium mb-1">{{ $t('companions.first_name') }} *</label>
                                            <input
                                                v-model="companionForm.first_name"
                                                type="text"
                                                class="form-input"
                                                :class="{ 'border-danger': companionErrors.first_name }"
                                                required
                                            />
                                            <p v-if="companionErrors.first_name" class="text-danger text-xs mt-1">{{ companionErrors.first_name[0] }}</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium mb-1">{{ $t('companions.last_name') }} *</label>
                                            <input
                                                v-model="companionForm.last_name"
                                                type="text"
                                                class="form-input"
                                                :class="{ 'border-danger': companionErrors.last_name }"
                                                required
                                            />
                                            <p v-if="companionErrors.last_name" class="text-danger text-xs mt-1">{{ companionErrors.last_name[0] }}</p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium mb-1">{{ $t('companions.relationship') }} *</label>
                                            <select
                                                v-model="companionForm.relationship"
                                                class="form-select"
                                                :class="{ 'border-danger': companionErrors.relationship }"
                                                required
                                            >
                                                <option value="">{{ $t('companions.select_relationship') }}</option>
                                                <option value="spouse">{{ $t('companions.spouse') }}</option>
                                                <option value="child">{{ $t('companions.child') }}</option>
                                                <option value="parent">{{ $t('companions.parent') }}</option>
                                                <option value="sibling">{{ $t('companions.sibling') }}</option>
                                                <option value="other">{{ $t('companions.other') }}</option>
                                            </select>
                                            <p v-if="companionErrors.relationship" class="text-danger text-xs mt-1">{{ companionErrors.relationship[0] }}</p>
                                        </div>
                                        <div v-if="companionForm.relationship === 'other'">
                                            <label class="block text-sm font-medium mb-1">{{ $t('companions.specify_relationship') }} *</label>
                                            <input
                                                v-model="companionForm.relationship_other"
                                                type="text"
                                                class="form-input"
                                                :class="{ 'border-danger': companionErrors.relationship_other }"
                                            />
                                            <p v-if="companionErrors.relationship_other" class="text-danger text-xs mt-1">{{ companionErrors.relationship_other[0] }}</p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium mb-1">{{ $t('companions.date_of_birth') }}</label>
                                            <input
                                                v-model="companionForm.date_of_birth"
                                                type="date"
                                                class="form-input"
                                                :max="today"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium mb-1">{{ $t('companions.gender') }}</label>
                                            <select v-model="companionForm.gender" class="form-select">
                                                <option value="">{{ $t('companions.select_gender') }}</option>
                                                <option value="male">{{ $t('companions.male') }}</option>
                                                <option value="female">{{ $t('companions.female') }}</option>
                                                <option value="other">{{ $t('companions.gender_other') }}</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium mb-1">{{ $t('companions.nationality') }}</label>
                                            <input
                                                v-model="companionForm.nationality"
                                                type="text"
                                                class="form-input"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium mb-1">{{ $t('companions.passport_number') }}</label>
                                            <input
                                                v-model="companionForm.passport_number"
                                                type="text"
                                                class="form-input"
                                            />
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium mb-1">{{ $t('companions.passport_country') }}</label>
                                            <input
                                                v-model="companionForm.passport_country"
                                                type="text"
                                                class="form-input"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium mb-1">{{ $t('companions.passport_expiry') }}</label>
                                            <input
                                                v-model="companionForm.passport_expiry_date"
                                                type="date"
                                                class="form-input"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium mb-1">{{ $t('companions.notes') }}</label>
                                        <textarea
                                            v-model="companionForm.notes"
                                            rows="2"
                                            class="form-textarea"
                                        ></textarea>
                                    </div>

                                    <div class="flex justify-end gap-3 mt-6">
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary"
                                            @click="closeCompanionModal"
                                        >
                                            {{ $t('companions.cancel') }}
                                        </button>
                                        <button
                                            type="submit"
                                            class="btn btn-primary"
                                            :disabled="isSavingCompanion"
                                        >
                                            <span v-if="isSavingCompanion" class="animate-spin border-2 border-white border-t-transparent rounded-full w-4 h-4 mr-2 inline-block"></span>
                                            {{ $t('companions.save') }}
                                        </button>
                                    </div>
                                </form>
                            </DialogPanel>
                        </TransitionChild>
                    </div>
                </div>
            </Dialog>
        </TransitionRoot>
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, inject, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Dialog, DialogPanel, DialogTitle, TransitionRoot, TransitionChild } from '@headlessui/vue';
import { useCompanionStore } from '@/stores/companion';
import { useNotification } from '@/composables/useNotification';
import type { Companion, CreateCompanionData, RelationshipType } from '@/types/companion';
import CompanionCheckbox from '../components/CompanionCheckbox.vue';
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

// Modal state
const showCompanionModal = ref(false);
const isSavingCompanion = ref(false);
const companionErrors = ref<Record<string, string[]>>({});
const today = new Date().toISOString().split('T')[0];

const companionForm = ref<CreateCompanionData>({
    first_name: '',
    last_name: '',
    relationship: '' as RelationshipType,
    relationship_other: '',
    date_of_birth: '',
    gender: undefined,
    nationality: '',
    passport_number: '',
    passport_country: '',
    passport_expiry_date: '',
    notes: '',
});

const selectedCount = computed(() => wizard.state.selectedCompanionIds.length);

// Check if companion is selected
function isSelected(id: number): boolean {
    return wizard.state.selectedCompanionIds.includes(id);
}

// Toggle companion selection
function toggleCompanion(id: number) {
    wizard.toggleCompanion(id);
}

// Modal methods
function resetCompanionForm() {
    companionForm.value = {
        first_name: '',
        last_name: '',
        relationship: '' as RelationshipType,
        relationship_other: '',
        date_of_birth: '',
        gender: undefined,
        nationality: '',
        passport_number: '',
        passport_country: '',
        passport_expiry_date: '',
        notes: '',
    };
    companionErrors.value = {};
}

function openCompanionModal() {
    resetCompanionForm();
    showCompanionModal.value = true;
}

function closeCompanionModal() {
    showCompanionModal.value = false;
    resetCompanionForm();
}

async function saveCompanion() {
    const clientId = wizard.state.clientId;
    if (!clientId) return;

    isSavingCompanion.value = true;
    companionErrors.value = {};

    try {
        await companionStore.createCompanion(clientId, companionForm.value);
        companions.value = [...companionStore.companions];
        success(t('companions.created_successfully'));
        closeCompanionModal();
    } catch (err: any) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            companionErrors.value = err.response.data.errors;
        } else {
            showError(err.response?.data?.message || t('companions.save_failed'));
        }
    } finally {
        isSavingCompanion.value = false;
    }
}

// Delete companion
async function confirmDeleteCompanion(id: number) {
    const clientId = wizard.state.clientId;
    if (!clientId) return;

    const companion = companions.value.find((c) => c.id === id);
    if (!companion) return;

    const confirmed = await confirmDialog({
        title: t('companions.confirm_delete'),
        text: t('companions.delete_warning', { name: `${companion.first_name} ${companion.last_name}` }),
        icon: 'warning',
        confirmButtonText: t('companions.yes_delete'),
        cancelButtonText: t('companions.cancel'),
    });

    if (confirmed) {
        try {
            await companionStore.deleteCompanion(clientId, id);
            companions.value = [...companionStore.companions];
            // Remove from wizard selection if it was selected
            if (wizard.state.selectedCompanionIds.includes(id)) {
                wizard.toggleCompanion(id);
            }
            success(t('companions.deleted_successfully'));
        } catch (err: any) {
            showError(err.response?.data?.message || t('companions.delete_failed'));
        }
    }
}

// Load companions when client changes
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
