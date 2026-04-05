<script lang="ts" setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppDatePicker from '@/components/AppDatePicker.vue';
import CountrySelect from '@/components/CountrySelect.vue';
import ExpirationAlertBadge from '@/components/ExpirationAlertBadge.vue';
import { legalDocumentService } from '@/services/legalDocumentService';
import { useNotification } from '@/composables/useNotification';
import type { LegalDocument, AlertStatus } from '@/types/legal-document';
import { DOCUMENT_TYPES } from '@/types/legal-document';

const props = withDefaults(defineProps<{
    modelValue: LegalDocument[];
    entityType: 'client' | 'companion';
    entityId: number;
    readonly?: boolean;
}>(), {
    readonly: false,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: LegalDocument[]): void;
}>();

const { t } = useI18n();
const { success, error } = useNotification();

const localDocs = ref<LegalDocument[]>([...props.modelValue]);

watch(() => props.modelValue, (val) => {
    localDocs.value = [...val];
}, { deep: true });

function getDocumentTypeLabel(type: string): string {
    const found = DOCUMENT_TYPES.find(dt => dt.value === type);
    return found ? t(found.labelKey) : type;
}

function addDocument() {
    localDocs.value.push({
        document_type: 'passport',
        document_name: null,
        document_number: null,
        issuing_country: null,
        issue_date: null,
        expiry_date: null,
        alert_days_before: 60,
        alert_active: true,
        notes: null,
        sort_order: localDocs.value.length,
    });
    emit('update:modelValue', [...localDocs.value]);
}

function removeDocument(idx: number) {
    localDocs.value.splice(idx, 1);
    localDocs.value.forEach((d, i) => { d.sort_order = i; });
    emit('update:modelValue', [...localDocs.value]);
}

function onUpdate() {
    emit('update:modelValue', [...localDocs.value]);
}

async function syncToCalendar(doc: LegalDocument) {
    if (!doc.id) return;
    try {
        await legalDocumentService.syncToCalendar(doc.id);
        success(t('documents.calendar_sync_success'));
    } catch {
        error(t('documents.calendar_sync_failed'));
    }
}

function getAlertStatus(doc: LegalDocument): AlertStatus {
    if (doc.alert_status) return doc.alert_status;
    if (!doc.expiry_date) return 'none';

    const now = new Date();
    now.setHours(0, 0, 0, 0);
    const expiry = new Date(doc.expiry_date + 'T00:00:00');
    const diffDays = Math.floor((expiry.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));

    if (diffDays < 0) return 'overdue';
    if (diffDays < 60) return 'critical';
    if (diffDays < 90) return 'warning';
    return 'ok';
}

function getDaysRemaining(doc: LegalDocument): number | null {
    if (doc.days_remaining !== undefined && doc.days_remaining !== null) return doc.days_remaining;
    if (!doc.expiry_date) return null;

    const now = new Date();
    now.setHours(0, 0, 0, 0);
    const expiry = new Date(doc.expiry_date + 'T00:00:00');
    return Math.floor((expiry.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
}
</script>

<template>
    <div>
        <!-- READONLY MODE -->
        <template v-if="readonly">
            <div v-if="modelValue.length === 0" class="text-sm text-gray-400 italic py-2">
                {{ $t('documents.no_documents') }}
            </div>
            <div v-else class="space-y-2">
                <div
                    v-for="doc in modelValue"
                    :key="doc.id ?? doc.sort_order"
                    class="flex flex-wrap items-center gap-3 rounded p-3 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800"
                >
                    <!-- Type/Name -->
                    <span class="badge badge-outline-primary text-xs shrink-0">
                        {{ doc.document_type === 'other' && doc.display_name ? doc.display_name : getDocumentTypeLabel(doc.document_type) }}
                    </span>
                    <!-- Number -->
                    <span v-if="doc.document_number" class="text-sm font-medium">
                        {{ doc.document_number }}
                    </span>
                    <!-- Issuing Country -->
                    <span v-if="doc.issuing_country" class="text-sm text-gray-500 dark:text-gray-400">
                        {{ doc.issuing_country }}
                    </span>
                    <!-- Issue Date -->
                    <span v-if="doc.issue_date" class="text-sm text-gray-500 dark:text-gray-400">
                        {{ doc.issue_date }}
                    </span>
                    <!-- Separator -->
                    <span v-if="doc.issue_date && doc.expiry_date" class="text-gray-300 dark:text-gray-600">-</span>
                    <!-- Expiry Date + Badge -->
                    <span v-if="doc.expiry_date" class="text-sm text-gray-500 dark:text-gray-400">
                        {{ doc.expiry_date }}
                    </span>
                    <ExpirationAlertBadge
                        v-if="doc.expiry_date"
                        :alert-status="getAlertStatus(doc)"
                        :days-remaining="getDaysRemaining(doc)"
                    />
                    <!-- Spacer -->
                    <span class="flex-1"></span>
                    <!-- Sync to Calendar -->
                    <button
                        v-if="doc.id && doc.expiry_date"
                        type="button"
                        class="p-1 text-gray-400 hover:text-primary transition-colors shrink-0"
                        :title="$t('documents.sync_to_calendar')"
                        @click.stop="syncToCalendar(doc)"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
                        </svg>
                    </button>
                </div>
            </div>
        </template>

        <!-- EDIT MODE -->
        <template v-else>
            <div class="space-y-3">
                <div
                    v-for="(doc, idx) in localDocs"
                    :key="doc.id ?? idx"
                    class="rounded border border-[#e0e6ed] dark:border-[#191e3a] p-3 space-y-2"
                >
                    <!-- Row 1: Type + Name (if other) -->
                    <div class="flex flex-wrap items-center gap-2">
                        <select
                            v-model="doc.document_type"
                            class="form-select flex-1 min-w-[180px] text-sm"
                            @change="onUpdate"
                        >
                            <option v-for="dt in DOCUMENT_TYPES" :key="dt.value" :value="dt.value">
                                {{ $t(dt.labelKey) }}
                            </option>
                        </select>
                        <input
                            v-if="doc.document_type === 'other'"
                            v-model="doc.document_name"
                            type="text"
                            class="form-input flex-1 min-w-[150px] text-sm"
                            :placeholder="$t('documents.document_name')"
                            @input="onUpdate"
                        />
                    </div>

                    <!-- Row 2: Number + Dates + Alert config -->
                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            v-model="doc.document_number"
                            type="text"
                            class="form-input w-40 shrink-0 text-sm"
                            :placeholder="$t('documents.document_number')"
                            @input="onUpdate"
                        />
                        <div class="flex-1 basis-0 min-w-[10rem]">
                            <CountrySelect
                                :model-value="doc.issuing_country ?? null"
                                :placeholder="$t('documents.issuing_country')"
                                class="text-sm"
                                @update:model-value="doc.issuing_country = $event; onUpdate()"
                            />
                        </div>
                        <AppDatePicker
                            v-model="doc.issue_date"
                            input-class="form-input w-36 text-sm"
                            :placeholder="$t('documents.issue_date')"
                            @update:model-value="onUpdate"
                        />
                        <AppDatePicker
                            v-model="doc.expiry_date"
                            input-class="form-input w-36 text-sm"
                            :placeholder="$t('documents.expiry_date')"
                            @update:model-value="onUpdate"
                        />
                        <div class="flex items-center gap-1">
                            <input
                                v-model.number="doc.alert_days_before"
                                type="number"
                                min="1"
                                max="365"
                                class="form-input w-20 text-sm text-center"
                                :title="$t('documents.alert_days_before')"
                                @input="onUpdate"
                            />
                            <span class="text-xs text-gray-500 whitespace-nowrap">{{ $t('documents.alert_days_before') }}</span>
                        </div>
                        <label class="flex items-center cursor-pointer shrink-0">
                            <input
                                type="checkbox"
                                v-model="doc.alert_active"
                                class="form-checkbox text-primary"
                                @change="onUpdate"
                            />
                            <span class="ml-1 text-xs text-gray-500">{{ $t('documents.alert_active') }}</span>
                        </label>
                        <!-- Remove button -->
                        <button
                            type="button"
                            class="text-danger hover:text-danger/80 shrink-0 ml-auto"
                            :title="$t('remove')"
                            @click="removeDocument(idx)"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="localDocs.length === 0" class="text-sm text-gray-400 italic py-2 text-center">
                    {{ $t('documents.no_documents') }}
                </div>
            </div>

            <!-- Add button -->
            <button
                type="button"
                class="btn btn-outline-primary btn-sm mt-3 gap-1"
                @click="addDocument"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ $t('documents.add_document') }}
            </button>
        </template>
    </div>
</template>
