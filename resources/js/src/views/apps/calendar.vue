<template>
    <div>
        <div class="panel">
            <div class="mb-5">
                <!-- Header: title + legend + filter + create button -->
                <div class="mb-4 flex items-center sm:flex-row flex-col sm:justify-between justify-center gap-3">
                    <div class="sm:mb-0 mb-4">
                        <div class="flex items-center gap-2 text-lg font-semibold ltr:sm:text-left rtl:sm:text-right text-center">
                            {{ $t('calendar.title') }}
                            <span
                                v-if="isSavingDrag"
                                class="animate-spin border-2 border-primary border-l-transparent rounded-full w-4 h-4 inline-block shrink-0"
                            ></span>
                        </div>
                        <div class="flex items-center mt-2 flex-wrap sm:justify-start justify-center gap-4">
                            <div v-for="cat in CATEGORIES" :key="cat.value" class="flex items-center gap-1.5">
                                <div class="h-2.5 w-2.5 rounded-sm" :class="`bg-${cat.color}`"></div>
                                <span class="text-sm">{{ $t(cat.i18nKey) }}</span>
                            </div>
                        </div>
                        <!-- Filtro "Asignado a" -->
                        <div class="mt-3">
                            <select
                                v-model="filterAssigneeId"
                                class="form-select text-sm py-1 w-auto min-w-[160px]"
                                @change="refreshCalendar"
                            >
                                <option :value="null">{{ $t('calendar.filter_all') }}</option>
                                <!-- <option :value="currentUserId">{{ $t('calendar.filter_me') }}</option> -->
                                <option
                                    v-for="a in assigneesInView"
                                    :key="a.id"
                                    :value="a.id"
                                >{{ a.name }}</option>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" @click="editEvent()">
                        <icon-plus class="ltr:mr-2 rtl:ml-2" />
                        {{ $t('calendar.create_event') }}
                    </button>
                </div>

                <!-- FullCalendar -->
                <div class="calendar-wrapper">
                    <FullCalendar ref="calendarRef" :options="calendarOptions">
                        <template v-slot:eventContent="arg">
                            <div class="fc-event-main-frame flex items-center px-1 py-0.5 text-white">
                                <div class="fc-event-time font-semibold px-0.5">{{ arg.timeText }}</div>
                                <div class="fc-event-title-container">
                                    <div class="fc-event-title fc-sticky !font-medium px-0.5">{{ arg.event.title }}</div>
                                </div>
                            </div>
                        </template>
                    </FullCalendar>
                </div>
            </div>
        </div>

        <EventFormModal
            v-model:show="isAddEventModal"
            :event-data="editingEvent"
            @saved="refreshCalendar"
            @deleted="refreshCalendar"
        />
    </div>
</template>

<script lang="ts" setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import '@fullcalendar/core/vdom';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';

import { useAppStore } from '@/stores/index';
import { useUserStore } from '@/stores/user';
import { useMeta } from '@/composables/use-meta';
import api from '@/services/api';

import EventFormModal, { type EventEditPayload } from '@/components/EventFormModal.vue';
import IconPlus from '@/components/icon/icon-plus.vue';

useMeta({ title: 'Calendar' });

const { t } = useI18n();
const store = useAppStore();
const userStore = useUserStore();

// ─── Constants ───────────────────────────────────────────────────────────────

const CATEGORIES = [
    { value: 'primera_sesion', color: 'info',    i18nKey: 'calendar.category_primera_sesion' },
    { value: 'seguimiento',    color: 'warning',  i18nKey: 'calendar.category_seguimiento' },
    { value: 'importante',     color: 'danger',   i18nKey: 'calendar.category_importante' },
    { value: 'personal',       color: 'success',  i18nKey: 'calendar.category_personal' },
] as const;

// ─── State ────────────────────────────────────────────────────────────────────

const calendarRef = ref<InstanceType<typeof FullCalendar>>();
const isAddEventModal = ref(false);
const editingEvent = ref<EventEditPayload | null>(null);

const events = ref<any[]>([]);
const filterAssigneeId = ref<number | null>(null);
const isSavingDrag = ref(false);
// Persists the full assignee list from the last unfiltered fetch so the
// dropdown does not lose options when a filter is active.
const knownAssignees = ref<{ id: number; name: string }[]>([]);

// ─── Computed ─────────────────────────────────────────────────────────────────

const currentUserId = computed(() => userStore.currentUser?.id ?? -1);

const assigneesInView = computed(() => knownAssignees.value);

// ─── FullCalendar Options ─────────────────────────────────────────────────────

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    locale: store.locale,
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay',
    },
    editable: true,
    dayMaxEvents: true,
    selectable: true,
    droppable: true,
    events: fetchCalendarEvents,
    eventClick:  (e: any) => editEvent(e),
    select:      (e: any) => editDate(e),
    eventDrop:   (info: any) => handleReschedule(info),
    eventResize: (info: any) => handleReschedule(info),
}));

// ─── API ──────────────────────────────────────────────────────────────────────

async function fetchCalendarEvents(fetchInfo: any, successCb: Function, failureCb: Function) {
    try {
        const params: Record<string, any> = {
            start: fetchInfo.startStr,
            end:   fetchInfo.endStr,
        };
        if (filterAssigneeId.value !== null) {
            params.assigned_to_id = filterAssigneeId.value;
        }
        const res = await api.get('/events', { params });
        events.value = res.data.data ?? [];

        // Rebuild assignee list only when unfiltered so the dropdown retains
        // all known assignees while a filter is active.
        if (filterAssigneeId.value === null) {
            const seen = new Map<number, string>();
            events.value.forEach((ev: any) => {
                const a = ev.extendedProps?.assigned_to;
                if (a?.id) seen.set(a.id, a.name);
            });
            knownAssignees.value = [...seen.entries()].map(([id, name]) => ({ id, name }));
        }

        successCb(events.value);
    } catch {
        failureCb(new Error(t('calendar.save_failed')));
    }
}

function refreshCalendar() {
    calendarRef.value?.getApi().refetchEvents();
}

// ─── Drag & Drop Persistence ──────────────────────────────────────────────────

async function handleReschedule(info: any) {
    const id = parseInt(info.event.id);
    const isAllDay = info.event.allDay;

    // For all-day events FullCalendar uses date-only strings ("YYYY-MM-DD").
    // endStr for all-day events is the exclusive next day; use startStr as end
    // so single-day all-day events remain single-day.
    // For timed events use the ISO datetime strings as-is.
    const startDate = info.event.startStr;
    let endDate: string;
    if (isAllDay) {
        endDate = startDate; // same day; backend will normalise
    } else {
        endDate = info.event.endStr || startDate;
    }

    isSavingDrag.value = true;
    try {
        await api.patch(`/events/${id}/reschedule`, {
            start_date: startDate,
            end_date:   endDate,
        });
    } catch {
        info.revert();
        showMessage(t('calendar.save_failed'), 'error');
    } finally {
        isSavingDrag.value = false;
    }
}

// ─── Calendar Interactions ────────────────────────────────────────────────────

function editEvent(data: any = null) {
    if (data) {
        const obj = data.event;
        const ext = obj.extendedProps ?? {};
        const isAllDay = obj.allDay ?? false;
        const rawStart = obj.startStr ?? obj.start ?? '';
        const rawEnd   = obj.endStr   ?? obj.end   ?? rawStart;
        editingEvent.value = {
            id:             obj.id ? parseInt(obj.id) : null,
            title:          obj.title ?? '',
            // All-day: use "YYYY-MM-DDT00:00" so datetime-local input renders correctly.
            // The date part (first 10 chars) is what saveEvent() will send to the API.
            start:          isAllDay ? rawStart.slice(0, 10) + 'T00:00' : toLocalDatetimeInput(rawStart),
            end:            isAllDay ? rawStart.slice(0, 10) + 'T00:00' : toLocalDatetimeInput(rawEnd),
            description:    ext.description ?? '',
            category:       ext.category ?? 'primera_sesion',
            assigned_to_id: ext.assigned_to?.id ?? null,
            case_id:        ext.case_id ?? null,
            case_number:    ext.case_number ?? null,
            client_id:      ext.client_id ?? null,
            client_name:    ext.client_name ?? '',
            location:       ext.location ?? '',
            all_day:        isAllDay,
        };
    } else {
        editingEvent.value = null;
    }
    isAddEventModal.value = true;
}

function editDate(data: any) {
    const isAllDay = data.allDay ?? false;
    const rawStart = data.startStr ?? data.start ?? '';
    editingEvent.value = {
        id:             null,
        title:          '',
        start:          isAllDay ? rawStart.slice(0, 10) + 'T00:00' : toLocalDatetimeInput(rawStart),
        end:            isAllDay ? rawStart.slice(0, 10) + 'T00:00' : toLocalDatetimeInput(data.endStr ?? data.end),
        description:    '',
        category:       'primera_sesion',
        assigned_to_id: null,
        case_id:        null,
        case_number:    null,
        client_id:      null,
        client_name:    '',
        location:       '',
        all_day:        isAllDay,
    };
    isAddEventModal.value = true;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function toLocalDatetimeInput(isoStr: string): string {
    if (!isoStr) return '';
    const d = new Date(isoStr);
    const offset = d.getTimezoneOffset();
    const local = new Date(d.getTime() - offset * 60_000);
    return local.toISOString().slice(0, 16);
}

function showMessage(msg = '', type = 'success') {
    import('sweetalert2').then(({ default: Swal }) => {
        const toast: any = Swal.mixin({
            toast: true,
            position: 'top',
            showConfirmButton: false,
            timer: 3000,
            customClass: { container: 'toast' },
        });
        toast.fire({ icon: type, title: msg, padding: '10px 20px' });
    });
}
</script>
