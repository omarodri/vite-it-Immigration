<script lang="ts" setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useImportantDatesStore } from '@/stores/useImportantDatesStore';
import importantDateAlertService from '@/services/importantDateAlertService';
import ImportantDatesFilters from '@/components/alerts/ImportantDatesFilters.vue';
import ImportantDatesTable from '@/components/alerts/ImportantDatesTable.vue';
import EventFormModal, { type EventEditPayload } from '@/components/EventFormModal.vue';
import type { ImportantDateAlert } from '@/types/important-date-alert';

const store = useImportantDatesStore();
const router = useRouter();

const modalOpen = ref(false);
const modalItem = ref<ImportantDateAlert | null>(null);
const modalEventData = ref<EventEditPayload | null>(null);

function openAddToCalendar(item: ImportantDateAlert) {
    modalItem.value = item;
    modalEventData.value = {
        id:             null,
        title:          item.label,
        start:          `${item.due_date}T00:00`,
        end:            `${item.due_date}T00:00`,
        description:    '',
        category:       'importante',
        assigned_to_id: item.consultant?.id ?? null,
        case_id:        item.case_id,
        case_number:    item.case_number,
        client_id:      item.client_id,
        client_name:    item.client_name ?? '',
        location:       '',
        all_day:        true,
    };
    modalOpen.value = true;
}

async function onEventSaved(eventId?: number) {
    modalOpen.value = false;
    if (eventId && modalItem.value) {
        try {
            await importantDateAlertService.linkEvent(modalItem.value.id, eventId);
            store.markAsLinkedToEvent(modalItem.value.id, eventId);
        } catch {
            // link failed; next fetch will show correct state
        }
    } else {
        store.fetch(store.meta?.current_page ?? 1);
    }
    modalItem.value = null;
    modalEventData.value = null;
}

function viewInCalendar(eventId: number) {
    router.push(`/apps/calendar?event=${eventId}`);
}

function onVisibilityChange() {
    if (document.visibilityState === 'visible') store.fetch(store.meta?.current_page ?? 1);
}

onMounted(() => {
    store.fetch();
    document.addEventListener('visibilitychange', onVisibilityChange);
});

onUnmounted(() => {
    document.removeEventListener('visibilitychange', onVisibilityChange);
});
</script>

<template>
    <div class="space-y-4">
        <ImportantDatesFilters @change="store.fetch(1)" />

        <ImportantDatesTable
            :items="store.items"
            :meta="store.meta"
            :is-loading="store.isLoading"
            @page-change="(page) => store.fetch(page)"
            @sort-change="() => store.fetch(1)"
            @add-to-calendar="openAddToCalendar"
            @view-in-calendar="viewInCalendar"
        />

        <EventFormModal
            v-if="modalOpen && modalEventData"
            :show="modalOpen"
            :event-data="modalEventData"
            :locked-case-id="modalItem?.case_id ?? null"
            :locked-case-number="modalItem?.case_number ?? ''"
            :locked-client-name="modalItem?.client_name ?? ''"
            @update:show="modalOpen = $event"
            @saved="onEventSaved"
        />
    </div>
</template>
