<template>
    <TransitionRoot appear :show="show" as="template">
        <Dialog as="div" class="relative z-50" @close="handleClose">
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
                        <DialogPanel class="w-full max-w-4xl transform overflow-hidden rounded-2xl bg-white dark:bg-gray-900 p-6 text-left align-middle shadow-xl transition-all">
                            <button
                                type="button"
                                class="absolute top-4 ltr:right-4 rtl:left-4 text-gray-400 hover:text-gray-800 dark:hover:text-gray-600 outline-none"
                                @click="handleClose"
                            >
                                <icon-x />
                            </button>
                            <DialogTitle as="h3" class="text-lg font-medium leading-6 text-gray-900 dark:text-white mb-4">
                                {{ companion ? $t('companions.edit_companion') : $t('companions.add_companion') }}
                            </DialogTitle>

                            <form @submit.prevent="handleSave" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- First Name -->
                                    <div>
                                        <label class="block text-sm font-medium mb-1 dark:text-white">{{ $t('companions.first_name') }} *</label>
                                        <input
                                            v-model="companionForm.first_name"
                                            type="text"
                                            class="form-input"
                                            :class="{ 'border-danger': companionErrors.first_name }"
                                            required
                                        />
                                        <p v-if="companionErrors.first_name" class="text-danger text-xs mt-1">{{ companionErrors.first_name[0] }}</p>
                                    </div>
                                    <!-- Last Name -->
                                    <div>
                                        <label class="block text-sm font-medium mb-1 dark:text-white">{{ $t('companions.last_name') }} *</label>
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
                                    <!-- Relationship (locked when preset is provided, e.g. 'beneficiary') -->
                                    <div v-if="lockedRelationship">
                                        <label class="block text-sm font-medium mb-1 dark:text-white">{{ $t('companions.relationship') }} *</label>
                                        <input
                                            type="text"
                                            class="form-input bg-gray-100 dark:bg-gray-800 cursor-not-allowed"
                                            :value="lockedRelationshipLabel"
                                            readonly
                                            disabled
                                        />
                                    </div>
                                    <div v-else>
                                        <label class="block text-sm font-medium mb-1 dark:text-white">{{ $t('companions.relationship') }} *</label>
                                        <select
                                            v-model="companionForm.relationship"
                                            class="form-select"
                                            :class="{ 'border-danger': companionErrors.relationship }"
                                            required
                                        >
                                            <option value="">{{ $t('companions.select_relationship') }}</option>
                                            <option value="spouse">{{ $t('companions.spouse') }}</option>
                                            <option value="common-law partner">{{ $t('companions.common-law partner') }}</option>
                                            <option value="child">{{ $t('companions.child') }}</option>
                                            <option value="dependent child">{{ $t('companions.dependent child') }}</option>
                                            <option value="parent">{{ $t('companions.parent') }}</option>
                                            <option value="sibling">{{ $t('companions.sibling') }}</option>
                                            <option value="half-sibling">{{ $t('companions.half-sibling') }}</option>
                                            <option value="step-sibling">{{ $t('companions.step-sibling') }}</option>
                                            <option value="grandchild">{{ $t('companions.grandchild') }}</option>
                                            <option value="grandparent">{{ $t('companions.grandparent') }}</option>
                                            <option value="aunt / uncle">{{ $t('companions.aunt / uncle') }}</option>
                                            <option value="niece / nephew">{{ $t('companions.niece / nephew') }}</option>
                                            <option value="cousin">{{ $t('companions.cousin') }}</option>
                                            <option value="child-in-law">{{ $t('companions.child-in-law') }}</option>
                                            <option value="parent-in-law">{{ $t('companions.parent-in-law') }}</option>
                                            <option value="employee">{{ $t('companions.employee') }}</option>
                                            <option value="other">{{ $t('companions.other') }}</option>
                                        </select>
                                        <p v-if="companionErrors.relationship" class="text-danger text-xs mt-1">{{ companionErrors.relationship[0] }}</p>
                                    </div>
                                    <!-- Relationship Other -->
                                    <div v-if="companionForm.relationship === 'other'">
                                        <label class="block text-sm font-medium mb-1 dark:text-white">{{ $t('companions.specify_relationship') }} *</label>
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
                                        <label class="block text-sm font-medium mb-1 dark:text-white">{{ $t('companions.date_of_birth') }}</label>
                                        <AppDatePicker
                                            v-model="companionForm.date_of_birth"
                                            :max-date="maxBirthDate.toISOString().split('T')[0]"
                                            :placeholder="$t('clients.select_date')"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1 dark:text-white">{{ $t('companions.gender') }}</label>
                                        <select v-model="companionForm.gender" class="form-select">
                                            <option value="">{{ $t('companions.select_gender') }}</option>
                                            <option value="male">{{ $t('companions.male') }}</option>
                                            <option value="female">{{ $t('companions.female') }}</option>
                                            <option value="other">{{ $t('companions.gender_other') }}</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Marital Status -->
                                    <div>
                                        <label class="block text-sm font-medium mb-1 dark:text-white">{{ $t('companions.marital_status') }}</label>
                                        <select v-model="companionForm.marital_status" class="form-select">
                                            <option value="">{{ $t('companions.select_marital_status') }}</option>
                                            <option value="single">{{ $t('clients.single') }}</option>
                                            <option value="married">{{ $t('clients.married') }}</option>
                                            <option value="divorced">{{ $t('clients.divorced') }}</option>
                                            <option value="widowed">{{ $t('clients.widowed') }}</option>
                                            <option value="common_law">{{ $t('clients.common_law') }}</option>
                                            <option value="legally_separated">{{ $t('clients.legally_separated') }}</option>
                                            <option value="annulled_marriage">{{ $t('clients.annulled_marriage') }}</option>
                                            <option value="unknown">{{ $t('clients.unknown') }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1 dark:text-white">{{ $t('companions.nationality') }}</label>
                                        <CountrySelect
                                            id="nationality"
                                            v-model="companionForm.nationality"
                                            :placeholder="$t('clients.select_nationality')"
                                            class="dark:bg-gray-900 dark:text-white"
                                        />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- IUC -->
                                    <div>
                                        <label class="block text-sm font-medium mb-1 dark:text-white">
                                            {{ $t('companions.iuc') }}
                                        </label>
                                        <input
                                            v-model="companionForm.iuc"
                                            type="text"
                                            class="form-input"
                                            maxlength="20"
                                            :placeholder="$t('companions.iuc_placeholder')"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-1 dark:text-white">{{ $t('companions.notes') }}</label>
                                    <textarea
                                        v-model="companionForm.notes"
                                        rows="2"
                                        class="form-textarea"
                                    ></textarea>
                                </div>

                                <!-- Legal Documents -->
                                <div v-if="showDocuments" class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-2">
                                    <h6 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-3">{{ $t('documents.legal_documents') }}</h6>
                                    <DocumentRepeater
                                        v-model="companionDocs"
                                        entity-type="companion"
                                        :entity-id="companion?.id ?? 0"
                                    />
                                </div>

                                <!-- Contact & Immigration Status -->
                                <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-2">
                                    <h6 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-3">{{ $t('clients.contact_information') }}</h6>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium mb-1 dark:text-white">{{ $t('clients.email') }}</label>
                                            <input
                                                v-model="companionForm.email"
                                                type="email"
                                                class="form-input"
                                                :placeholder="$t('clients.email')"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium mb-1 dark:text-white">{{ $t('clients.phone') }}</label>
                                            <PhoneInput
                                                v-model="companionForm.phone as string"
                                                v-model:country-code="companionForm.phone_country_code as string"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium mb-1 dark:text-white">{{ $t('clients.canada_status') }}</label>
                                            <select v-model="companionForm.canada_status" class="form-select">
                                                <option value="">{{ $t('clients.select_status') }}</option>
                                                <option v-for="option in CANADA_STATUS_OPTIONS" :key="option.value" :value="option.value">
                                                    {{ $t(`clients.status_${option.value}`) }}
                                                </option>
                                            </select>
                                        </div>
                                        <div v-if="companionForm.canada_status === 'other'">
                                            <label class="block text-sm font-medium mb-1 dark:text-white">{{ $t('clients.canada_status_other') }}</label>
                                            <input
                                                v-model="companionForm.canada_status_other"
                                                type="text"
                                                class="form-input"
                                                :placeholder="$t('clients.canada_status_other_placeholder')"
                                                required
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- Arrival Date -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium mb-1 dark:text-white">{{ $t('clients.arrival_date') }}</label>
                                        <AppDatePicker
                                            v-model="companionForm.arrival_date"
                                            max-date="today"
                                            :placeholder="$t('clients.select_date')"
                                        />
                                    </div>
                                </div>

                                <div class="flex justify-end gap-3 mt-6">
                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary"
                                        @click="handleClose"
                                    >
                                        {{ $t('companions.cancel') }}
                                    </button>
                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                        :disabled="isSavingCompanion"
                                    >
                                        <span v-if="isSavingCompanion" class="animate-spin border-2 border-white border-t-transparent rounded-full w-4 h-4 mr-2 inline-block"></span>
                                        {{ companion ? $t('companions.update') : $t('companions.save') }}
                                    </button>
                                </div>
                            </form>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script lang="ts" setup>
import { ref, watch, computed } from 'vue'
import { Dialog, DialogPanel, DialogTitle, TransitionRoot, TransitionChild } from '@headlessui/vue'
import { useI18n } from 'vue-i18n'
import { useCompanionStore } from '@/stores/companion'
import { useNotification } from '@/composables/useNotification'
import { useModalGuard } from '@/composables/useModalGuard'
import { legalDocumentService } from '@/services/legalDocumentService'
import { CANADA_STATUS_OPTIONS } from '@/types/client'
import type { Companion, CreateCompanionData, UpdateCompanionData, RelationshipType } from '@/types/companion'
import type { LegalDocument } from '@/types/legal-document'
import AppDatePicker from '@/components/AppDatePicker.vue'
import CountrySelect from '@/components/CountrySelect.vue'
import PhoneInput from '@/components/PhoneInput.vue'
import DocumentRepeater from '@/components/DocumentRepeater.vue'
import IconX from '@/components/icon/icon-x.vue';

const props = withDefaults(defineProps<{
    show: boolean
    clientId: number
    companion?: Companion | null
    showDocuments?: boolean
    /**
     * If provided, the relationship is pre-set AND locked (read-only) in the form.
     * Used by the case wizard when creating the mandatory beneficiary companion
     * for company-type clients (DT-09).
     */
    presetRelationship?: RelationshipType | null
}>(), {
    companion: null,
    showDocuments: true,
    presetRelationship: null,
})

const emit = defineEmits<{
    (e: 'update:show', value: boolean): void
    (e: 'saved', companion: Companion): void
    (e: 'close'): void
}>()

const { t } = useI18n()
const companionStore = useCompanionStore()
const { success, error } = useNotification()

const companionForm = ref<CreateCompanionData>({
    first_name: '',
    last_name: '',
    relationship: '' as RelationshipType,
    relationship_other: '',
    date_of_birth: '',
    gender: undefined,
    marital_status: '',
    nationality: '',
    iuc: '' as string | null,
    notes: '',
    email: '',
    phone: '',
    phone_country_code: '+1',
    canada_status: '',
    canada_status_other: '',
    arrival_date: '',
})

const companionDocs = ref<LegalDocument[]>([])
const companionOriginalDocIds = ref<number[]>([])
const companionErrors = ref<Record<string, string[]>>({})
const isSavingCompanion = ref(false)

const { captureSnapshot, handleClose: guardClose, forceClose } = useModalGuard(
    () => ({ ...companionForm.value }),
    () => {
        emit('update:show', false)
        emit('close')
        resetForm()
    },
)

const maxBirthDate = computed(() => {
    const d = new Date()
    d.setDate(d.getDate() - 1)
    return d
})

// Lock the relationship field when a preset is provided AND we are creating
// a new companion (editing existing companions is not affected).
const lockedRelationship = computed<RelationshipType | null>(() => {
    return !props.companion && props.presetRelationship ? props.presetRelationship : null
})

const lockedRelationshipLabel = computed<string>(() => {
    if (!lockedRelationship.value) return ''
    // Use the i18n key the rest of the app uses for relationship labels.
    return t(`companions.${lockedRelationship.value}`)
})

function resetForm() {
    companionForm.value = {
        first_name: '',
        last_name: '',
        relationship: (props.presetRelationship ?? '') as RelationshipType,
        relationship_other: '',
        date_of_birth: '',
        gender: undefined,
        marital_status: '',
        nationality: '',
        iuc: '' as string | null,
        notes: '',
        email: '',
        phone: '',
        phone_country_code: '+1',
        canada_status: '',
        canada_status_other: '',
        arrival_date: '',
    }
    companionDocs.value = []
    companionOriginalDocIds.value = []
    companionErrors.value = {}
}

async function loadData() {
    if (props.companion) {
        companionForm.value = {
            first_name: props.companion.first_name,
            last_name: props.companion.last_name,
            relationship: props.companion.relationship,
            relationship_other: props.companion.relationship_other || '',
            date_of_birth: props.companion.date_of_birth || '',
            gender: props.companion.gender || undefined,
            marital_status: props.companion.marital_status || '',
            nationality: props.companion.nationality || '',
            iuc: props.companion.iuc || '',
            notes: props.companion.notes || '',
            email: props.companion.email || '',
            phone: props.companion.phone || '',
            phone_country_code: props.companion.phone_country_code || '+1',
            canada_status: props.companion.canada_status || '',
            canada_status_other: props.companion.canada_status_other || '',
            arrival_date: props.companion.arrival_date || '',
        }
        if (props.showDocuments) {
            try {
                const docs = await legalDocumentService.getForCompanion(props.companion.id)
                companionDocs.value = docs
                companionOriginalDocIds.value = docs.filter(d => d.id).map(d => d.id as number)
            } catch {
                // Docs load failure is non-critical
            }
        }
    } else {
        resetForm()
    }
}

async function handleSave() {
    if (!props.clientId) return
    isSavingCompanion.value = true
    companionErrors.value = {}

    try {
        let savedCompanion: Companion

        if (props.companion) {
            // Update existing companion
            await companionStore.updateCompanion(
                props.clientId,
                props.companion.id,
                companionForm.value as UpdateCompanionData
            )
            // Sync legal documents
            if (props.showDocuments) {
                const companionId = props.companion.id
                const currentIds = companionDocs.value.filter(d => d.id).map(d => d.id as number)
                const deletedIds = companionOriginalDocIds.value.filter(id => !currentIds.includes(id))
                await Promise.all([
                    ...deletedIds.map(id => legalDocumentService.remove(id)),
                    ...companionDocs.value.map(doc =>
                        doc.id
                            ? legalDocumentService.update(doc.id, doc)
                            : legalDocumentService.createForCompanion(companionId, doc)
                    ),
                ])
            }
            savedCompanion = { ...props.companion, ...companionForm.value } as Companion
            success(t('companions.updated_successfully'))
        } else {
            // Create new companion
            const newCompanion = await companionStore.createCompanion(
                props.clientId,
                companionForm.value as CreateCompanionData
            )
            // Save docs for newly created companion if any
            if (props.showDocuments && newCompanion?.id && companionDocs.value.length > 0) {
                await Promise.all(
                    companionDocs.value.map(doc =>
                        legalDocumentService.createForCompanion(newCompanion.id, doc)
                    )
                )
            }
            savedCompanion = newCompanion
            success(t('companions.created_successfully'))
        }

        emit('saved', savedCompanion)
        forceClose()
    } catch (err: any) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            companionErrors.value = err.response.data.errors
        } else {
            error(err.response?.data?.message || t('companions.save_failed'))
        }
    } finally {
        isSavingCompanion.value = false
    }
}

const handleClose = guardClose

// Watch for modal open
watch(() => props.show, async (newVal) => {
    if (newVal) {
        if (props.companion) {
            await loadData()
        } else {
            resetForm()
        }
        captureSnapshot()
    }
})
</script>
