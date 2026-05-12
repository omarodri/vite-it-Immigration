<script lang="ts" setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { storeToRefs } from 'pinia';
import { useImportantDatesStore } from '@/stores/useImportantDatesStore';
import api from '@/services/api';
import userService from '@/services/userService';

const emit = defineEmits<{ change: [] }>();
const { t } = useI18n();
const store = useImportantDatesStore();
const { filters, meta } = storeToRefs(store);

const consultants = ref<{ id: number; name: string }[]>([]);
const caseTypes = ref<{ id: number; name: string }[]>([]);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

onMounted(async () => {
    try {
        const [staffRes, typesRes] = await Promise.all([
            userService.getStaff(),
            api.get('/case-types'),
        ]);
        consultants.value = (staffRes ?? []).map((u: any) => ({
            id: u.id,
            name: u.name ?? u.full_name ?? `${u.first_name ?? ''} ${u.last_name ?? ''}`.trim(),
        }));
        const typesPayload = typesRes.data?.data ?? typesRes.data ?? [];
        caseTypes.value = (typesPayload as any[]).map((c) => ({ id: c.id, name: c.name }));
    } catch {
        // handled by API interceptor
    }
});

const urgencyOptions = [
    { value: 'all',       labelKey: 'alerts.filter.urgency_all' },
    { value: 'overdue',   labelKey: 'alerts.filter.urgency_overdue' },
    { value: 'today',     labelKey: 'alerts.urgency.today' },
    { value: 'this_week', labelKey: 'alerts.filter.urgency_this_week' },
    { value: 'upcoming',  labelKey: 'alerts.filter.urgency_upcoming' },
] as const;

function countFor(value: string): number {
    const counts = meta.value?.counts_by_urgency;
    if (!counts) return 0;
    switch (value) {
        case 'overdue':   return (counts.overdue_critical ?? 0) + (counts.overdue_recent ?? 0);
        case 'today':     return counts.today ?? 0;
        case 'this_week': return counts.upcoming_week ?? 0;
        case 'upcoming':  return counts.upcoming_month ?? 0;
        default:          return 0;
    }
}

const totalCount = computed(() => {
    const c = meta.value?.counts_by_urgency;
    if (!c) return 0;
    return (c.overdue_critical ?? 0)
         + (c.overdue_recent ?? 0)
         + (c.today ?? 0)
         + (c.upcoming_week ?? 0)
         + (c.upcoming_month ?? 0);
});

function onFilterChange() {
    emit('change');
}

function onSearchInput() {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => emit('change'), 400);
}

function selectUrgency(value: typeof urgencyOptions[number]['value']) {
    filters.value.urgency = value;
    emit('change');
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-3 mb-5">
        <select v-model="filters.consultant_id" class="form-select w-48" @change="onFilterChange">
            <option :value="null">{{ t('alerts.filter.all_consultants') }}</option>
            <option v-for="c in consultants" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>

        <select v-model="filters.case_type_id" class="form-select w-48" @change="onFilterChange">
            <option :value="null">{{ t('alerts.filter.all_case_types') }}</option>
            <option v-for="ct in caseTypes" :key="ct.id" :value="ct.id">{{ ct.name }}</option>
        </select>

        <div class="relative flex-1 max-w-xs">
            <input
                v-model="filters.search"
                type="text"
                class="form-input pl-9 peer"
                :placeholder="t('alerts.search_placeholder')"
                @input="onSearchInput"
            />
            <div class="absolute ltr:left-3 rtl:right-3 top-1/2 -translate-y-1/2 peer-focus:text-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="11.5" cy="11.5" r="9.5" stroke="currentColor" stroke-width="1.5" opacity="0.5" />
                    <path d="M18.5 18.5L22 22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                </svg>
            </div>
        </div>

        <div class="flex flex-wrap gap-1">
            <button
                v-for="opt in urgencyOptions"
                :key="opt.value"
                type="button"
                class="btn btn-sm"
                :class="filters.urgency === opt.value ? 'btn-primary' : 'btn-outline-primary'"
                @click="selectUrgency(opt.value)"
            >
                {{ t(opt.labelKey) }}
                <span
                    v-if="opt.value === 'all' ? totalCount > 0 : countFor(opt.value) > 0"
                    class="ml-1 rounded-full bg-white/20 px-1.5 py-0 text-[10px] font-bold bg-blue-500 text-white"
                >
                    {{ opt.value === 'all' ? totalCount : countFor(opt.value) }}
                </span>
            </button>
        </div>
    </div>
</template>
