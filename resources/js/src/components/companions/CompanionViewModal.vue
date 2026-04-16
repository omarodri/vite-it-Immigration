<template>
    <TransitionRoot appear :show="show" as="template">
        <Dialog as="div" class="relative z-50" @close="$emit('close')">
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
                        <DialogPanel class="w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white dark:bg-gray-900 text-left align-middle shadow-xl transition-all">

                            <!-- Header -->
                            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-info/10 flex items-center justify-center text-sm font-bold text-info shrink-0">
                                        {{ companion?.initials || getInitials(companion?.first_name, companion?.last_name) }}
                                    </div>
                                    <div>
                                        <DialogTitle as="h3" class="text-base font-semibold text-gray-900 dark:text-white leading-tight">
                                            {{ companion?.full_name }}
                                        </DialogTitle>
                                        <p class="text-xs text-gray-500">
                                            {{ companion?.relationship_label || formatRelationship(companion?.relationship) }}
                                            <span v-if="companion?.age"> · {{ companion.age }} {{ $t('companions.years_old') }}</span>
                                        </p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 outline-none transition-colors"
                                    @click="$emit('close')"
                                >
                                    <icon-x class="w-5 h-5" />
                                </button>
                            </div>

                            <!-- Body -->
                            <div class="px-6 py-5 space-y-6 overflow-y-auto max-h-[70vh]">

                                <!-- Personal Info -->
                                <section>
                                    <h4 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">
                                        {{ $t('companions.section_personal') }}
                                    </h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                                        <FieldRow :label="$t('companions.first_name')" :value="companion?.first_name" />
                                        <FieldRow :label="$t('companions.last_name')" :value="companion?.last_name" />
                                        <FieldRow :label="$t('companions.relationship')" :value="companion?.relationship_label || formatRelationship(companion?.relationship)" />
                                        <FieldRow
                                            v-if="companion?.relationship === 'other' && companion?.relationship_other"
                                            :label="$t('companions.specify_relationship')"
                                            :value="companion?.relationship_other"
                                        />
                                        <FieldRow :label="$t('companions.date_of_birth')" :value="formatDate(companion?.date_of_birth)" />
                                        <FieldRow :label="$t('companions.gender')" :value="formatGender(companion?.gender)" />
                                        <FieldRow :label="$t('companions.marital_status')" :value="formatMaritalStatus(companion?.marital_status)" />
                                        <FieldRow :label="$t('companions.nationality')" :value="companion?.nationality" />
                                        <FieldRow :label="$t('companions.iuc')" :value="companion?.iuc" />
                                    </div>
                                </section>

                                <!-- Contact -->
                                <section class="border-t border-gray-100 dark:border-gray-800 pt-5">
                                    <h4 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">
                                        {{ $t('clients.contact_information') }}
                                    </h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                                        <FieldRow :label="$t('clients.email')" :value="companion?.email" />
                                        <FieldRow
                                            :label="$t('clients.phone')"
                                            :value="companion?.phone ? `${companion.phone_country_code || ''} ${companion.phone}`.trim() : null"
                                        />
                                    </div>
                                </section>

                                <!-- Immigration Status -->
                                <section class="border-t border-gray-100 dark:border-gray-800 pt-5">
                                    <h4 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">
                                        {{ $t('companions.section_immigration') }}
                                    </h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                                        <FieldRow
                                            :label="$t('clients.canada_status')"
                                            :value="companion?.canada_status_label || formatCanadaStatus(companion?.canada_status)"
                                        />
                                        <FieldRow
                                            v-if="companion?.canada_status === 'other'"
                                            :label="$t('clients.canada_status_other')"
                                            :value="companion?.canada_status_other"
                                        />
                                        <FieldRow :label="$t('clients.arrival_date')" :value="formatDate(companion?.arrival_date)" />
                                    </div>
                                </section>

                                <!-- Notes -->
                                <section v-if="companion?.notes" class="border-t border-gray-100 dark:border-gray-800 pt-5">
                                    <h4 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">
                                        {{ $t('companions.notes') }}
                                    </h4>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ companion.notes }}</p>
                                </section>

                                <!-- Legal Documents -->
                                <section class="border-t border-gray-100 dark:border-gray-800 pt-5">
                                    <h4 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">
                                        {{ $t('documents.legal_documents') }}
                                    </h4>

                                    <div v-if="isLoadingDocs" class="flex items-center gap-2 text-sm text-gray-500">
                                        <span class="animate-spin border-2 border-primary border-l-transparent rounded-full w-4 h-4 inline-block"></span>
                                        {{ $t('companions.loading_documents') }}
                                    </div>

                                    <p v-else-if="!legalDocuments.length" class="text-sm text-gray-400 italic">
                                        {{ $t('companions.no_documents') }}
                                    </p>

                                    <div v-else class="space-y-3">
                                        <div
                                            v-for="doc in legalDocuments"
                                            :key="doc.id"
                                            class="rounded-lg border border-gray-200 dark:border-gray-700 p-3 grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-2"
                                        >
                                            <FieldRow :label="$t('documents.document_type')" :value="doc.display_name || formatDocType(doc.document_type)" span />
                                            <FieldRow v-if="doc.document_number" :label="$t('documents.document_number')" :value="doc.document_number" />
                                            <FieldRow v-if="doc.issuing_country" :label="$t('documents.issuing_country')" :value="doc.issuing_country" />
                                            <FieldRow v-if="doc.issue_date" :label="$t('documents.issue_date')" :value="formatDate(doc.issue_date)" />
                                            <FieldRow v-if="doc.expiry_date" :label="$t('documents.expiry_date')" :value="formatDate(doc.expiry_date)">
                                                <template #value>
                                                    <span :class="getExpiryClass(doc)">{{ formatDate(doc.expiry_date) }}</span>
                                                </template>
                                            </FieldRow>
                                            <FieldRow v-if="doc.notes" :label="$t('companions.notes')" :value="doc.notes" span />
                                        </div>
                                    </div>
                                </section>
                            </div>

                            <!-- Footer -->
                            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end gap-3">
                                <button
                                    v-if="canEdit"
                                    v-can="'clients.update'"
                                    type="button"
                                    class="btn btn-primary btn-sm gap-2"
                                    @click="$emit('edit', companion)"
                                >
                                    <icon-pencil class="w-4 h-4" />
                                    {{ $t('companions.edit_companion') }}
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" @click="$emit('close')">
                                    {{ $t('companions.close') }}
                                </button>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script lang="ts" setup>
import { ref, watch } from 'vue'
import { Dialog, DialogPanel, DialogTitle, TransitionRoot, TransitionChild } from '@headlessui/vue'
import { useI18n } from 'vue-i18n'
import { legalDocumentService } from '@/services/legalDocumentService'
import { formatDate } from '@/utils/formatters'
import { DOCUMENT_TYPES } from '@/types/legal-document'
import type { Companion } from '@/types/companion'
import type { LegalDocument } from '@/types/legal-document'
import IconX from '@/components/icon/icon-x.vue'
import IconPencil from '@/components/icon/icon-pencil.vue'
import FieldRow from '@/components/companions/CompanionFieldRow.vue'

const props = withDefaults(defineProps<{
    show: boolean
    companion: Companion | null
    canEdit?: boolean
}>(), {
    canEdit: true,
})

const emit = defineEmits<{
    (e: 'close'): void
    (e: 'edit', companion: Companion | null): void
}>()

const { t } = useI18n()
const legalDocuments = ref<LegalDocument[]>([])
const isLoadingDocs = ref(false)

// ---- Helpers ----
const getInitials = (first?: string | null, last?: string | null): string =>
    ((first?.[0] || '') + (last?.[0] || '')).toUpperCase()

const formatRelationship = (rel?: string | null): string => {
    if (!rel) return '—'
    const key = `companions.${rel}`
    const translated = t(key)
    return translated !== key ? translated : rel
}

const formatGender = (gender?: string | null): string => {
    if (!gender) return '—'
    const map: Record<string, string> = {
        male: t('companions.male'),
        female: t('companions.female'),
        other: t('companions.gender_other'),
    }
    return map[gender] ?? gender
}

const formatMaritalStatus = (status?: string | null): string => {
    if (!status) return '—'
    const key = `clients.${status}`
    const translated = t(key)
    return translated !== key ? translated : status
}

const formatCanadaStatus = (status?: string | null): string => {
    if (!status) return '—'
    const key = `clients.status_${status}`
    const translated = t(key)
    return translated !== key ? translated : status
}

const formatDocType = (type: string): string => {
    const found = DOCUMENT_TYPES.find(d => d.value === type)
    if (!found) return type
    const translated = t(found.labelKey)
    return translated !== found.labelKey ? translated : type
}

const getExpiryClass = (doc: LegalDocument): string => {
    if (!doc.expiry_date) return ''
    const status = doc.alert_status
    if (status === 'overdue') return 'text-danger font-semibold'
    if (status === 'critical') return 'text-danger'
    if (status === 'warning') return 'text-warning'
    return 'text-gray-800 dark:text-gray-200'
}

// ---- Data loading ----
watch(() => props.show, async (opened) => {
    if (opened && props.companion?.id) {
        isLoadingDocs.value = true
        legalDocuments.value = []
        try {
            legalDocuments.value = await legalDocumentService.getForCompanion(props.companion.id)
        } catch {
            // non-critical
        } finally {
            isLoadingDocs.value = false
        }
    } else {
        legalDocuments.value = []
    }
})
</script>
