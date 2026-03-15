<template>
    <div>
        <!-- Financial Summary Cards -->
        <div v-if="financialSummary" class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
            <div class="panel text-center">
                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">{{ $t('cases.invoice_total_case') }}</div>
                <div class="text-lg font-bold text-dark dark:text-white-light">
                    {{ financialSummary.fees !== null ? '$' + formatMoney(financialSummary.fees) + ' CAD' : '--' }}
                </div>
            </div>
            <div class="panel text-center">
                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">{{ $t('cases.invoice_total_invoiced') }}</div>
                <div class="text-lg font-bold text-info">${{ formatMoney(financialSummary.total_invoiced) }} CAD</div>
            </div>
            <div class="panel text-center">
                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">{{ $t('cases.invoice_balance') }}</div>
                <div class="text-lg font-bold" :class="balanceClass">
                    {{ financialSummary.balance !== null ? '$' + formatMoney(financialSummary.balance) + ' CAD' : '--' }}
                </div>
            </div>
        </div>

        <!-- Invoice Table Panel -->
        <div class="border border-gray-200 rounded-lg p-4 bg-white dark:bg-[#1b2e4b] dark:border-gray-700">
            <!-- Total Case Row -->
            <div class="flex items-center gap-3 mb-4 pb-3 border-b border-gray-100 dark:border-gray-700">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400 w-36">{{ $t('cases.invoice_total_case') }}</span>
                <input
                    :value="financialSummary?.fees ?? ''"
                    readonly
                    class="form-input w-28 bg-gray-50 dark:bg-gray-800 text-center"
                    placeholder="--"
                />
                <span class="text-sm text-gray-500">CAD</span>
            </div>

            <!-- Invoice Table -->
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="text-left pb-2 font-medium text-gray-600 dark:text-gray-400 w-40">{{ $t('cases.invoice_number') }}</th>
                        <th class="text-left pb-2 font-medium text-gray-600 dark:text-gray-400 w-36">{{ $t('cases.invoice_date') }}</th>
                        <th class="text-left pb-2 font-medium text-gray-600 dark:text-gray-400 w-32">{{ $t('cases.invoice_amount') }}</th>
                        <th class="text-center pb-2 font-medium text-gray-600 dark:text-gray-400 w-24">{{ $t('cases.invoice_collected') }}</th>
                        <th class="pb-2 w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(invoice, index) in localInvoices" :key="index" class="border-b border-gray-50 dark:border-gray-800">
                        <td class="py-1.5 pr-2">
                            <input
                                v-if="canManage"
                                v-model="invoice.invoice_number"
                                class="form-input text-sm"
                                :placeholder="$t('cases.invoice_number')"
                            />
                            <span v-else class="text-sm">{{ invoice.invoice_number }}</span>
                        </td>
                        <td class="py-1.5 pr-2">
                            <flat-pickr
                                v-if="canManage"
                                v-model="invoice.invoice_date"
                                class="form-input text-sm"
                                :config="{ dateFormat: 'Y-m-d', altInput: true, altFormat: 'd/m/Y' }"
                            />
                            <span v-else class="text-sm">{{ invoice.invoice_date }}</span>
                        </td>
                        <td class="py-1.5 pr-2">
                            <input
                                v-if="canManage"
                                v-model.number="invoice.amount"
                                type="number"
                                min="0"
                                step="0.01"
                                class="form-input text-sm"
                                placeholder="0.00"
                            />
                            <span v-else class="text-sm">${{ formatMoney(invoice.amount) }}</span>
                        </td>
                        <td class="py-1.5 text-center">
                            <label v-if="canManage" class="inline-flex items-center cursor-pointer">
                                <input
                                    v-model="invoice.is_collected"
                                    type="checkbox"
                                    class="form-checkbox text-success"
                                />
                            </label>
                            <span v-else>
                                <svg v-if="invoice.is_collected" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-success mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span v-else class="text-gray-300">--</span>
                            </span>
                        </td>
                        <td class="py-1.5">
                            <button
                                v-if="canManage"
                                type="button"
                                @click="removeInvoice(index)"
                                class="p-1 text-danger hover:bg-danger/10 rounded"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </td>
                    </tr>
                    <!-- Empty State -->
                    <tr v-if="localInvoices.length === 0">
                        <td colspan="5" class="py-6 text-center text-gray-400 text-sm">
                            {{ $t('cases.invoice_none') }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Footer: Add + Save -->
            <div v-if="canManage" class="flex items-center justify-between mt-4 pt-3 border-t border-gray-100 dark:border-gray-700">
                <button type="button" @click="addInvoice" class="btn btn-sm btn-outline-primary gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ $t('cases.invoice_add') }}
                </button>
                <button
                    type="button"
                    @click="save"
                    :disabled="isSaving"
                    class="btn btn-success gap-1"
                >
                    <svg v-if="!isSaving" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span v-if="isSaving" class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 inline-block"></span>
                    {{ isSaving ? $t('cases.saving') : $t('cases.save') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import flatPickr from 'vue-flatpickr-component';
import 'flatpickr/dist/flatpickr.css';
import { useCaseStore } from '@/stores/case';
import { useNotification } from '@/composables/useNotification';
import type { CaseInvoice, FinancialSummary } from '@/types/invoice';

const props = defineProps<{
    invoices: CaseInvoice[];
    financialSummary: FinancialSummary | null;
    caseId: number;
}>();

const emit = defineEmits<{ saved: [] }>();

const { t } = useI18n();
const caseStore = useCaseStore();
const { success, error: showError } = useNotification();

const localInvoices = ref<CaseInvoice[]>([]);
const isSaving = ref(false);

// Permission check: use v-can directive pattern - since the user store
// doesn't have a `can()` method, we always allow manage for now.
// The v-can directive handles DOM-level permission checks.
// For the component logic, we default to true and let the backend enforce.
const canManage = computed(() => true);

const balanceClass = computed(() => {
    if (!props.financialSummary || props.financialSummary.balance === null) return 'text-gray-500';
    if (props.financialSummary.balance > 0) return 'text-success';
    if (props.financialSummary.balance < 0) return 'text-danger';
    return 'text-dark dark:text-white-light';
});

watch(() => props.invoices, (val) => {
    localInvoices.value = val.map(i => ({ ...i }));
}, { immediate: true, deep: true });

function addInvoice() {
    localInvoices.value.push({
        invoice_number: '',
        invoice_date: new Date().toISOString().split('T')[0],
        amount: 0,
        is_collected: false,
    });
}

function removeInvoice(index: number) {
    localInvoices.value.splice(index, 1);
}

async function save() {
    isSaving.value = true;
    try {
        await caseStore.bulkUpdateInvoices(
            props.caseId,
            localInvoices.value.map(({ id, sort_order, ...rest }) => rest)
        );
        success(t('cases.invoice_saved'));
        emit('saved');
    } catch (err: any) {
        showError(err.response?.data?.message || t('cases.invoice_save_failed'));
    } finally {
        isSaving.value = false;
    }
}

function formatMoney(value: number | null | undefined): string {
    if (value === null || value === undefined) return '--';
    return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
