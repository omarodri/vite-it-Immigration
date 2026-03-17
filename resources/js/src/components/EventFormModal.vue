<template>
    <TransitionRoot appear :show="show" as="template">
        <Dialog as="div" @close="emit('update:show', false)" class="relative z-[51]">
            <TransitionChild
                as="template"
                enter="duration-300 ease-out" enter-from="opacity-0" enter-to="opacity-100"
                leave="duration-200 ease-in" leave-from="opacity-100" leave-to="opacity-0"
            >
                <DialogOverlay class="fixed inset-0 bg-[black]/60" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center px-4 py-8">
                    <TransitionChild
                        as="template"
                        enter="duration-300 ease-out" enter-from="opacity-0 scale-95" enter-to="opacity-100 scale-100"
                        leave="duration-200 ease-in" leave-from="opacity-100 scale-100" leave-to="opacity-0 scale-95"
                    >
                        <DialogPanel class="panel border-0 p-0 rounded-lg overflow-hidden w-full max-w-lg text-black dark:text-white-dark">
                            <button
                                type="button"
                                class="absolute top-4 ltr:right-4 rtl:left-4 text-gray-400 hover:text-gray-800 dark:hover:text-gray-600 outline-none"
                                @click="emit('update:show', false)"
                            >
                                <icon-x />
                            </button>
                            <div class="text-lg font-medium bg-[#fbfbfb] dark:bg-[#121c2c] ltr:pl-5 rtl:pr-5 py-3 ltr:pr-[50px] rtl:pl-[50px]">
                                {{ params.id ? $t('calendar.edit_event') : $t('calendar.add_event') }}
                            </div>
                            <div class="p-5">
                                <form @submit.prevent="saveEvent">
                                    <!-- Title -->
                                    <div class="mb-5">
                                        <label for="modal-cal-title">{{ $t('calendar.event_title') }} *</label>
                                        <input
                                            id="modal-cal-title"
                                            type="text"
                                            class="form-input"
                                            :placeholder="$t('calendar.event_title_placeholder')"
                                            v-model="params.title"
                                            required
                                        />
                                    </div>

                                    <!-- Assigned to -->
                                    <div class="mb-5">
                                        <label for="modal-cal-assignee">{{ $t('calendar.assigned_to') }} *</label>
                                        <select id="modal-cal-assignee" class="form-select" v-model="params.assigned_to_id" required>
                                            <option :value="null" disabled>{{ $t('calendar.assigned_to_placeholder') }}</option>
                                            <option v-for="a in assignees" :key="a.id" :value="a.id">{{ a.name }}</option>
                                        </select>
                                    </div>

                                    <!-- Related case -->
                                    <div class="mb-5">
                                        <label>{{ $t('calendar.related_case') }}</label>
                                        <template v-if="isContextualMode">
                                            <div class="flex gap-2 items-center">
                                                <div class="form-input flex-1 bg-gray-50 dark:bg-gray-900 cursor-not-allowed text-gray-500">
                                                    #{{ props.lockedCaseNumber }}{{ props.lockedClientName ? ' — ' + props.lockedClientName : '' }}
                                                </div>
                                                <router-link
                                                    :to="`/cases/${props.lockedCaseId}`"
                                                    target="_blank"
                                                    class="flex items-center justify-center w-9 h-9 rounded border border-gray-200 dark:border-gray-700 text-gray-400 hover:text-primary hover:border-primary transition-colors shrink-0"
                                                    :title="$t('calendar.open_case')"
                                                >
                                                    <icon-link class="w-4 h-4" />
                                                </router-link>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div class="flex gap-2 items-start">
                                            <div class="flex-1">
                                            <Multiselect
                                                v-model="selectedCase"
                                                :options="caseOptions"
                                                :loading="isLoadingCases"
                                                :internal-search="false"
                                                label="label"
                                                track-by="id"
                                                :placeholder="$t('calendar.search_case')"
                                                :allow-empty="true"
                                                :show-labels="false"
                                                :clear-on-select="false"
                                                class="custom-multiselect dark:bg-gray-900 dark:text-white"
                                                @search-change="onCaseSearch"
                                                @select="onCaseSelected"
                                                @remove="onCaseCleared"
                                            >
                                                <template #noResult>
                                                    <span class="text-sm text-gray-400">{{ $t('calendar.no_cases_found') }}</span>
                                                </template>
                                                <template #noOptions>
                                                    <span class="text-sm text-gray-400">{{ $t('calendar.search_case') }}</span>
                                                </template>
                                            </Multiselect>
                                            </div>
                                            <router-link
                                                v-if="params.case_id"
                                                :to="`/cases/${params.case_id}`"
                                                target="_blank"
                                                class="mt-1 flex items-center justify-center w-9 h-9 rounded border border-gray-200 dark:border-gray-700 text-gray-400 hover:text-primary hover:border-primary transition-colors shrink-0"
                                                :title="$t('calendar.open_case')"
                                            >
                                                <icon-link class="w-4 h-4" />
                                            </router-link>
                                            </div>
                                        </template>
                                    </div>

                                    <!-- Client name (readonly, global mode only) -->
                                    <div class="mb-5" v-if="!isContextualMode && params.client_name">
                                        <label>{{ $t('calendar.client_name') }}</label>
                                        <div class="flex gap-2 items-center">
                                            <input
                                                type="text"
                                                class="form-input flex-1 bg-gray-50 dark:bg-gray-900 cursor-not-allowed text-gray-500"
                                                :value="params.client_name"
                                                readonly
                                            />
                                            <router-link
                                                v-if="params.client_id"
                                                :to="`/clients/${params.client_id}`"
                                                target="_blank"
                                                class="flex items-center justify-center w-9 h-9 rounded border border-gray-200 dark:border-gray-700 text-gray-400 hover:text-primary hover:border-primary transition-colors shrink-0"
                                                :title="$t('calendar.open_client')"
                                            >
                                                <icon-link class="w-4 h-4" />
                                            </router-link>
                                        </div>
                                    </div>

                                    <!-- Dates -->
                                    <div class="mb-5 grid grid-cols-2 gap-4">
                                        <div>
                                            <label for="modal-cal-start">{{ $t('calendar.from') }} *</label>
                                            <input
                                                id="modal-cal-start"
                                                type="datetime-local"
                                                class="form-input"
                                                v-model="params.start"
                                                :min="minStart"
                                                :disabled="params.all_day"
                                                :required="!params.all_day"
                                                @change="onStartDateChange"
                                            />
                                        </div>
                                        <div>
                                            <label for="modal-cal-end">{{ $t('calendar.to') }} *</label>
                                            <input
                                                id="modal-cal-end"
                                                type="datetime-local"
                                                class="form-input"
                                                v-model="params.end"
                                                :min="minEnd"
                                                :disabled="params.all_day"
                                                :required="!params.all_day"
                                            />
                                        </div>
                                    </div>

                                    <!-- All day -->
                                    <div class="mb-5">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" class="form-checkbox" v-model="params.all_day" />
                                            <span>{{ $t('calendar.all_day') }}</span>
                                        </label>
                                    </div>

                                    <!-- Description -->
                                    <div class="mb-5">
                                        <label for="modal-cal-desc">{{ $t('calendar.description') }}</label>
                                        <textarea
                                            id="modal-cal-desc"
                                            class="form-textarea min-h-[100px]"
                                            :placeholder="$t('calendar.description_placeholder')"
                                            v-model="params.description"
                                        ></textarea>
                                    </div>

                                    <!-- Location -->
                                    <div class="mb-5">
                                        <label for="modal-cal-location">{{ $t('calendar.location') }}</label>
                                        <input
                                            id="modal-cal-location"
                                            type="text"
                                            class="form-input"
                                            :placeholder="$t('calendar.location_placeholder')"
                                            v-model="params.location"
                                        />
                                    </div>

                                    <!-- Category -->
                                    <div class="mb-5">
                                        <label>{{ $t('calendar.badge') }} *</label>
                                        <div class="mt-3 flex flex-wrap gap-4">
                                            <label
                                                v-for="cat in CATEGORIES"
                                                :key="cat.value"
                                                class="inline-flex items-center gap-2 cursor-pointer"
                                            >
                                                <input
                                                    type="radio"
                                                    class="form-radio"
                                                    :class="`text-${cat.color}`"
                                                    :value="cat.value"
                                                    v-model="params.category"
                                                />
                                                <span>{{ $t(cat.i18nKey) }}</span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="flex justify-between items-center mt-8">
                                        <button
                                            v-if="params.id"
                                            type="button"
                                            class="btn btn-outline-danger"
                                            @click="deleteEvent"
                                        >
                                            {{ $t('calendar.delete') }}
                                        </button>
                                        <div class="flex gap-3 ltr:ml-auto rtl:mr-auto">
                                            <button type="button" class="btn btn-outline-secondary" @click="emit('update:show', false)">
                                                {{ $t('calendar.cancel') }}
                                            </button>
                                            <button type="submit" class="btn btn-primary" :disabled="isSaving">
                                                <span v-if="isSaving" class="animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4 inline-block align-middle ltr:mr-2 rtl:ml-2"></span>
                                                {{ params.id ? $t('calendar.update_event') : $t('calendar.create_event') }}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script lang="ts" setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { TransitionRoot, TransitionChild, Dialog, DialogPanel, DialogOverlay } from '@headlessui/vue';
import Multiselect from '@suadelabs/vue3-multiselect';
import '@suadelabs/vue3-multiselect/dist/vue3-multiselect.css';
import Swal from 'sweetalert2';

import api from '@/services/api';
import IconX from '@/components/icon/icon-x.vue';
import IconLink from '@/components/icon/icon-link.vue';

const { t } = useI18n();

// ─── Constants ───────────────────────────────────────────────────────────────

const CATEGORIES = [
    { value: 'primera_sesion', color: 'info',    i18nKey: 'calendar.category_primera_sesion' },
    { value: 'seguimiento',    color: 'warning',  i18nKey: 'calendar.category_seguimiento' },
    { value: 'importante',     color: 'danger',   i18nKey: 'calendar.category_importante' },
    { value: 'personal',       color: 'success',  i18nKey: 'calendar.category_personal' },
] as const;

// ─── Types ────────────────────────────────────────────────────────────────────

export interface EventEditPayload {
    id: number | null;
    title: string;
    start: string;
    end: string;
    description: string;
    category: string;
    assigned_to_id: number | null;
    case_id: number | null;
    case_number: string | null;
    client_id: number | null;
    client_name: string;
    location: string;
    all_day: boolean;
}

interface Assignee { id: number; name: string }
interface CaseOption { id: number; case_number: string; client_name: string; label: string }
interface FormParams {
    id: number | null;
    title: string;
    start: string;
    end: string;
    description: string;
    category: string;
    assigned_to_id: number | null;
    case_id: number | null;
    client_id: number | null;
    client_name: string;
    location: string;
    all_day: boolean;
}

// ─── Props & Emits ────────────────────────────────────────────────────────────

const props = defineProps<{
    show: boolean;
    eventData?: EventEditPayload | null;
    lockedCaseId?: number | null;
    lockedCaseNumber?: string;
    lockedClientName?: string;
}>();

const emit = defineEmits<{
    'update:show': [boolean];
    'saved': [];
    'deleted': [];
}>();

// ─── Computed ─────────────────────────────────────────────────────────────────

const isContextualMode = computed(() => props.lockedCaseId != null);

const minStart = computed(() =>
    params.value.id ? '' : dateFormat(new Date())
);

const minEnd = computed(() => params.value.start || '');

// ─── State ────────────────────────────────────────────────────────────────────

const isSaving = ref(false);
const assignees = ref<Assignee[]>([]);
const caseOptions = ref<CaseOption[]>([]);
const selectedCase = ref<CaseOption | null>(null);
const isLoadingCases = ref(false);
let caseSearchDebounce: ReturnType<typeof setTimeout> | null = null;

const defaultParams: FormParams = {
    id: null, title: '', start: '', end: '',
    description: '', category: 'primera_sesion',
    assigned_to_id: null, case_id: null, client_id: null,
    client_name: '', location: '', all_day: false,
};

const params = ref<FormParams>({ ...defaultParams });

// ─── Lifecycle ────────────────────────────────────────────────────────────────

onMounted(async () => {
    await loadAssignees();
});

watch(() => props.show, (visible) => {
    if (visible) populateForm();
});

// ─── Form Population ──────────────────────────────────────────────────────────

function populateForm() {
    params.value = { ...defaultParams };
    selectedCase.value = null;
    caseOptions.value = [];

    if (props.eventData) {
        const d = props.eventData;
        params.value = {
            id:             d.id,
            title:          d.title,
            start:          d.start,
            end:            d.end,
            description:    d.description,
            category:       d.category || 'primera_sesion',
            assigned_to_id: d.assigned_to_id,
            case_id:        d.case_id,
            client_id:      d.client_id,
            client_name:    d.client_name,
            location:       d.location,
            all_day:        d.all_day,
        };

        // Pre-fill case multiselect in global mode
        if (!isContextualMode.value && d.case_id && d.case_number) {
            const opt: CaseOption = {
                id: d.case_id,
                case_number: d.case_number,
                client_name: d.client_name ?? '',
                label: `#${d.case_number}${d.client_name ? ' — ' + d.client_name : ''}`,
            };
            caseOptions.value = [opt];
            selectedCase.value = opt;
        }
    }

    // In contextual mode, always lock case_id from prop
    if (isContextualMode.value) {
        params.value.case_id = props.lockedCaseId ?? null;
    }
}

// ─── API ──────────────────────────────────────────────────────────────────────

async function loadAssignees() {
    try {
        const res = await api.get('/events/assignees');
        assignees.value = res.data.data ?? [];
    } catch {
        assignees.value = [];
    }
}

// ─── Case Search ──────────────────────────────────────────────────────────────

function onCaseSearch(query: string) {
    if (caseSearchDebounce) clearTimeout(caseSearchDebounce);
    if (query.length < 2) {
        caseOptions.value = selectedCase.value ? [selectedCase.value] : [];
        return;
    }
    isLoadingCases.value = true;
    caseSearchDebounce = setTimeout(async () => {
        try {
            const res = await api.get('/cases', {
                params: { status: 'active', search: query, per_page: 20 },
            });
            caseOptions.value = (res.data.data ?? []).map((c: any) => ({
                id: c.id,
                case_number: c.case_number,
                client_name: c.client?.full_name ?? '',
                label: `#${c.case_number}${c.client?.full_name ? ' — ' + c.client.full_name : ''}`,
            }));
        } catch {
            caseOptions.value = [];
        } finally {
            isLoadingCases.value = false;
        }
    }, 350);
}

function onCaseSelected(option: CaseOption) {
    params.value.case_id     = option.id;
    params.value.client_name = option.client_name;
}

function onCaseCleared() {
    params.value.case_id     = null;
    params.value.client_name = '';
}

// ─── Date Helpers ─────────────────────────────────────────────────────────────

function onStartDateChange(event: Event) {
    const dateStr = (event.target as HTMLInputElement).value;
    if (!dateStr) return;
    if (params.value.end && params.value.end < dateStr) {
        params.value.end = dateStr;
    }
}

function toUtcIso(localStr: string): string {
    if (!localStr) return '';
    return new Date(localStr).toISOString();
}

function dateFormat(dt: Date | string): string {
    const d = new Date(dt);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

// ─── CRUD ─────────────────────────────────────────────────────────────────────

async function saveEvent() {
    if (!params.value.title || !params.value.assigned_to_id || !params.value.category) {
        showMessage(t('calendar.fill_required'), 'error');
        return;
    }
    if (!params.value.all_day && (!params.value.start || !params.value.end)) {
        showMessage(t('calendar.fill_required'), 'error');
        return;
    }

    isSaving.value = true;
    try {
        // For all-day events send only the date part (no time, no timezone) so
        // the backend stores it as UTC midnight and FullCalendar never shifts the day.
        const todayDate = new Date().toISOString().slice(0, 10);
        const payload: Record<string, any> = {
            title:          params.value.title,
            description:    params.value.description || null,
            start_date:     params.value.all_day
                                ? (params.value.start || todayDate).slice(0, 10)
                                : toUtcIso(params.value.start),
            end_date:       params.value.all_day
                                ? (params.value.start || todayDate).slice(0, 10)
                                : toUtcIso(params.value.end),
            assigned_to_id: params.value.assigned_to_id,
            case_id:        params.value.case_id || null,
            category:       params.value.category,
            all_day:        params.value.all_day,
            location:       params.value.location || null,
        };

        if (params.value.id) {
            await api.put(`/events/${params.value.id}`, payload);
        } else {
            await api.post('/events', payload);
        }

        showMessage(t('calendar.event_saved'));
        emit('update:show', false);
        emit('saved');
    } catch (err: any) {
        const msg = err.response?.data?.message ?? t('calendar.save_failed');
        showMessage(msg, 'error');
    } finally {
        isSaving.value = false;
    }
}

async function deleteEvent() {
    if (!params.value.id) return;

    const result = await Swal.fire({
        title: t('calendar.confirm_delete'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: t('calendar.delete'),
        cancelButtonText: t('calendar.cancel'),
        confirmButtonColor: '#e7515a',
    });

    if (!result.isConfirmed) return;

    try {
        await api.delete(`/events/${params.value.id}`);
        showMessage(t('calendar.event_deleted'));
        emit('update:show', false);
        emit('deleted');
    } catch {
        showMessage(t('calendar.save_failed'), 'error');
    }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function showMessage(msg = '', type = 'success') {
    const toast: any = Swal.mixin({
        toast: true,
        position: 'top',
        showConfirmButton: false,
        timer: 3000,
        customClass: { container: 'toast' },
    });
    toast.fire({ icon: type, title: msg, padding: '10px 20px' });
}
</script>
