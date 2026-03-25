<template>
    <div>
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-3 mb-4">
            <button type="button" class="btn btn-primary btn-sm gap-1" @click="openAddModal">
                <icon-plus class="w-4 h-4 shrink-0" />
                {{ $t('cases.event_add') }}
            </button>
            <div class="relative flex-1 min-w-[160px]">
                <input
                    v-model="searchQuery"
                    type="text"
                    class="form-input text-sm py-1.5 ltr:pl-8 rtl:pr-8"
                    :placeholder="$t('calendar.search_case')"
                    @input="onSearch"
                />
                <icon-search class="absolute ltr:left-2 rtl:right-2 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
            </div>
            <select v-model="filterAssigneeId" class="form-select text-sm py-1.5 w-auto">
                <option :value="null">{{ $t('todo_filter_all_assignees') }}</option>
                <option v-for="a in assignees" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
        </div>

        <!-- Category filter chips -->
        <div class="flex gap-2 flex-wrap mb-4">
            <button
                type="button"
                class="px-3 py-1 rounded-full text-sm font-medium transition-colors"
                :class="filterCategory === null ? 'bg-primary text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 hover:bg-primary/10 hover:text-primary'"
                @click="filterCategory = null"
            >
                {{ $t('todo_filter_all_tags') }}
            </button>
            <button
                v-for="cat in CATEGORIES"
                :key="cat.value"
                type="button"
                class="px-3 py-1 rounded-full text-sm font-medium transition-colors"
                :class="filterCategory === cat.value ? cat.solidClass : 'bg-gray-100 dark:bg-gray-800 text-gray-600 hover:bg-primary/10 hover:text-primary'"
                @click="filterCategory = cat.value"
            >
                {{ $t(cat.i18nKey) }}
            </button>
        </div>

        <!-- Loading -->
        <div v-if="isLoading" class="text-center py-10">
            <span class="animate-spin border-4 border-primary border-l-transparent rounded-full w-8 h-8 inline-block align-middle"></span>
        </div>

        <!-- Empty state -->
        <div v-else-if="filteredEvents.length === 0" class="text-center py-10 text-gray-500">
            <p>{{ $t('calendar.no_events') }}</p>
        </div>

        <!-- Events table -->
        <div v-else class="table-responsive">
            <table class="table-hover">
                <thead>
                    <tr>
                        <th>{{ $t('calendar.event_title') }}</th>
                        <th>{{ $t('calendar.badge') }}</th>
                        <th>{{ $t('calendar.from') }}</th>
                        <th>{{ $t('calendar.assigned_to') }}</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="ev in filteredEvents" :key="ev.id" class="group cursor-pointer">
                        <td @click="openEditModal(ev)">
                            <div class="font-semibold group-hover:text-primary">{{ ev.title }}</div>
                            <div v-if="ev.extendedProps.description" class="text-white-dark text-sm line-clamp-1">
                                {{ ev.extendedProps.description }}
                            </div>
                        </td>
                        <td>
                            <span class="badge rounded-full" :class="categoryBadgeClass(ev.extendedProps.category)">
                                {{ $t('calendar.category_' + ev.extendedProps.category) }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap text-sm text-white-dark">
                            {{ formatEventDate(ev.start, ev.allDay) }}
                        </td>
                        <td>
                            <div
                                v-if="ev.extendedProps.assigned_to"
                                class="grid place-content-center h-7 w-7 rounded-full bg-primary text-white text-xs font-semibold"
                                :title="ev.extendedProps.assigned_to.name"
                            >
                                {{ getInitials(ev.extendedProps.assigned_to.name) }}
                            </div>
                        </td>
                        <td>
                            <Popper placement="bottom-end" offsetDistance="0">
                                <a href="javascript:;">
                                    <icon-horizontal-dots class="rotate-90 opacity-70" />
                                </a>
                                <template #content="{ close }">
                                    <ul class="dropdown-menu" @click="close()">
                                        <li>
                                            <a href="javascript:;" @click="openEditModal(ev)">
                                                <icon-pencil-paper class="w-4.5 h-4.5 ltr:mr-2 rtl:ml-2 shrink-0" />
                                                {{ $t('todo_edit') }}
                                            </a>
                                        </li>
                                        <li>
                                            <a href="javascript:;" @click="cloneEvent(ev)">
                                                <icon-copy class="w-4.5 h-4.5 ltr:mr-2 rtl:ml-2 shrink-0" />
                                                {{ $t('cases.event_clone') }}
                                            </a>
                                        </li>
                                        <li>
                                            <a href="javascript:;" @click="confirmDelete(ev)">
                                                <icon-trash-lines class="w-4.5 h-4.5 ltr:mr-2 rtl:ml-2 shrink-0" />
                                                {{ $t('todo_delete') }}
                                            </a>
                                        </li>
                                    </ul>
                                </template>
                            </Popper>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Event Form Modal (contextual mode) -->
        <EventFormModal
            v-model:show="showModal"
            :event-data="editingEvent"
            :locked-case-id="props.caseId"
            :locked-case-number="props.caseNumber"
            :locked-client-name="props.clientName"
            @saved="refreshList"
            @deleted="refreshList"
        />
    </div>
</template>

<script lang="ts" setup>
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';

import api from '@/services/api';
import EventFormModal, { type EventEditPayload } from '@/components/EventFormModal.vue';

import IconPlus from '@/components/icon/icon-plus.vue';
import IconSearch from '@/components/icon/icon-search.vue';
import IconHorizontalDots from '@/components/icon/icon-horizontal-dots.vue';
import IconPencilPaper from '@/components/icon/icon-pencil-paper.vue';
import IconCopy from '@/components/icon/icon-copy.vue';
import IconTrashLines from '@/components/icon/icon-trash-lines.vue';

const { t } = useI18n();

// ─── Constants ───────────────────────────────────────────────────────────────

const CATEGORIES = [
    { value: 'primera_sesion', i18nKey: 'calendar.category_primera_sesion', solidClass: 'bg-info text-white' },
    { value: 'seguimiento',    i18nKey: 'calendar.category_seguimiento',    solidClass: 'bg-warning text-white' },
    { value: 'importante',     i18nKey: 'calendar.category_importante',     solidClass: 'bg-danger text-white' },
    { value: 'personal',       i18nKey: 'calendar.category_personal',       solidClass: 'bg-success text-white' },
] as const;

const CATEGORY_BADGE_OUTLINE: Record<string, string> = {
    primera_sesion: 'badge-outline-info',
    seguimiento:    'badge-outline-warning',
    importante:     'badge-outline-danger',
    personal:       'badge-outline-success',
};

// ─── Props ────────────────────────────────────────────────────────────────────

const props = defineProps<{
    caseId: number;
    caseNumber: string;
    clientName: string;
}>();

// ─── Types ────────────────────────────────────────────────────────────────────

interface ApiEvent {
    id: number;
    title: string;
    start: string;
    end: string;
    allDay: boolean;
    className: string;
    extendedProps: {
        description: string;
        category: string;
        location: string;
        assigned_to: { id: number; name: string } | null;
        case_id: number | null;
        case_number: string | null;
        client_name: string;
    };
}

interface Assignee { id: number; name: string }

// ─── State ────────────────────────────────────────────────────────────────────

const isLoading = ref(false);
const events = ref<ApiEvent[]>([]);
const assignees = ref<Assignee[]>([]);
const showModal = ref(false);
const editingEvent = ref<EventEditPayload | null>(null);

const searchQuery = ref('');
const filterAssigneeId = ref<number | null>(null);
const filterCategory = ref<string | null>(null);

let searchDebounce: ReturnType<typeof setTimeout> | null = null;

// ─── Computed ─────────────────────────────────────────────────────────────────

const filteredEvents = computed(() => {
    let list = events.value;

    if (filterCategory.value) {
        list = list.filter(ev => ev.extendedProps.category === filterCategory.value);
    }

    if (filterAssigneeId.value) {
        list = list.filter(ev => ev.extendedProps.assigned_to?.id === filterAssigneeId.value);
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(ev =>
            ev.title.toLowerCase().includes(q) ||
            (ev.extendedProps.description ?? '').toLowerCase().includes(q)
        );
    }

    return list;
});

// ─── Lifecycle ────────────────────────────────────────────────────────────────

onMounted(async () => {
    await Promise.all([fetchEvents(), fetchAssignees()]);
});

// ─── API ──────────────────────────────────────────────────────────────────────

async function fetchEvents() {
    isLoading.value = true;
    try {
        const res = await api.get('/events', { params: { case_id: props.caseId } });
        events.value = res.data.data ?? [];
    } catch {
        events.value = [];
    } finally {
        isLoading.value = false;
    }
}

async function fetchAssignees() {
    try {
        const res = await api.get('/events/assignees');
        assignees.value = res.data.data ?? [];
    } catch {
        assignees.value = [];
    }
}

function refreshList() {
    fetchEvents();
}

// ─── Search ───────────────────────────────────────────────────────────────────

function onSearch() {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {}, 400);
}

// ─── Modal ────────────────────────────────────────────────────────────────────

function openAddModal() {
    editingEvent.value = null;
    showModal.value = true;
}

function openEditModal(ev: ApiEvent) {
    editingEvent.value = {
        id:             ev.id,
        title:          ev.title,
        start:          toLocalDatetimeInput(ev.start),
        end:            toLocalDatetimeInput(ev.end),
        description:    ev.extendedProps.description ?? '',
        category:       ev.extendedProps.category ?? 'primera_sesion',
        assigned_to_id: ev.extendedProps.assigned_to?.id ?? null,
        case_id:        ev.extendedProps.case_id ?? null,
        case_number:    ev.extendedProps.case_number ?? null,
        client_name:    ev.extendedProps.client_name ?? '',
        location:       ev.extendedProps.location ?? '',
        all_day:        ev.allDay ?? false,
    };
    showModal.value = true;
}

// ─── Clone ────────────────────────────────────────────────────────────────────

async function cloneEvent(ev: ApiEvent) {
    try {
        await api.post(`/events/${ev.id}/clone`);
        showMessage(t('calendar.event_saved'));
        refreshList();
    } catch {
        showMessage(t('calendar.save_failed'), 'error');
    }
}

// ─── Delete ───────────────────────────────────────────────────────────────────

async function confirmDelete(ev: ApiEvent) {
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
        await api.delete(`/events/${ev.id}`);
        showMessage(t('calendar.event_deleted'));
        refreshList();
    } catch {
        showMessage(t('calendar.save_failed'), 'error');
    }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function categoryBadgeClass(category: string): string {
    return CATEGORY_BADGE_OUTLINE[category] ?? 'badge-outline-primary';
}

function formatEventDate(isoStr: string, allDay = false): string {
    if (!isoStr) return '-';
    if (allDay) {
        // Parse YYYY-MM-DD without timezone shift
        const [y, m, d] = isoStr.split('T')[0].split('-').map(Number);
        return new Date(y, m - 1, d).toLocaleDateString(undefined, {
            month: 'short', day: 'numeric', year: 'numeric',
        });
    }
    return new Date(isoStr).toLocaleString(undefined, {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function getInitials(name: string): string {
    return name
        .split(' ')
        .slice(0, 2)
        .map(w => w[0] ?? '')
        .join('')
        .toUpperCase();
}

function toLocalDatetimeInput(isoStr: string): string {
    if (!isoStr) return '';
    const d = new Date(isoStr);
    const offset = d.getTimezoneOffset();
    const local = new Date(d.getTime() - offset * 60_000);
    return local.toISOString().slice(0, 16);
}

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
