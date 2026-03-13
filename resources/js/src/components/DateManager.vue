<script lang="ts" setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import flatPickr from 'vue-flatpickr-component'
import 'flatpickr/dist/flatpickr.css'
import type { ImportantDate } from '@/types/case'

const props = withDefaults(defineProps<{
    modelValue: ImportantDate[]
    readonly?: boolean
    maxDates?: number
}>(), {
    readonly: false,
    maxDates: 20,
})

const emit = defineEmits<{
    (e: 'update:modelValue', value: ImportantDate[]): void
}>()

const { t } = useI18n()

const localDates = ref<ImportantDate[]>([...props.modelValue])

watch(() => props.modelValue, (val) => {
    localDates.value = [...val]
}, { deep: true })

const dateConfig = {
    dateFormat: 'Y-m-d',
    allowInput: true,
}

function getDateStatus(dueDate: string | null): 'overdue' | 'upcoming' | 'future' | 'none' {
    if (!dueDate) return 'none'
    const now = new Date()
    now.setHours(0, 0, 0, 0)
    const date = new Date(dueDate + 'T00:00:00')
    const diffDays = Math.floor((date.getTime() - now.getTime()) / (1000 * 60 * 60 * 24))
    if (diffDays < 0) return 'overdue'
    if (diffDays <= 7) return 'upcoming'
    return 'future'
}

const statusBadgeClass: Record<string, string> = {
    overdue:  'badge badge-outline-danger',
    upcoming: 'badge badge-outline-warning',
    future:   'badge badge-outline-success',
    none:     'badge badge-outline-dark',
}

const statusBorderClass: Record<string, string> = {
    overdue:  'border-l-2 border-danger',
    upcoming: 'border-l-2 border-warning',
    future:   'border-l-2 border-success',
    none:     '',
}

const statusLabel: Record<string, string> = {
    overdue:  'Vencida',
    upcoming: 'Proxima',
    future:   'Pendiente',
    none:     'Sin fecha',
}

function addDate() {
    if (localDates.value.length >= props.maxDates) return
    localDates.value.push({ label: '', due_date: null, sort_order: localDates.value.length })
    emit('update:modelValue', [...localDates.value])
}

function removeDate(idx: number) {
    localDates.value.splice(idx, 1)
    localDates.value.forEach((d, i) => { d.sort_order = i })
    emit('update:modelValue', [...localDates.value])
}

function onUpdate() {
    emit('update:modelValue', [...localDates.value])
}
</script>

<template>
  <div>
    <!-- READONLY MODE -->
    <template v-if="readonly">
      <div v-if="modelValue.length === 0" class="text-sm text-gray-400 italic py-2">
        {{ $t('cases.no_dates') }}
      </div>
      <div v-else class="space-y-2">
        <div
          v-for="date in modelValue"
          :key="date.id ?? date.sort_order"
          class="flex items-center gap-3 rounded p-2 bg-white dark:bg-gray-900"
          :class="statusBorderClass[getDateStatus(date.due_date)]"
        >
          <span :class="statusBadgeClass[getDateStatus(date.due_date)]" class="text-xs shrink-0">
            {{ statusLabel[getDateStatus(date.due_date)] }}
          </span>
          <span class="text-sm font-medium flex-1 truncate">{{ date.label }}</span>
          <span class="text-sm text-gray-500 dark:text-gray-400 shrink-0">
            {{ date.due_date ?? '---' }}
          </span>
        </div>
      </div>
    </template>

    <!-- EDIT MODE -->
    <template v-else>
      <div class="space-y-2">
        <div
          v-for="(date, idx) in localDates"
          :key="date.sort_order"
          class="flex items-center gap-2 rounded border border-[#e0e6ed] dark:border-[#191e3a] p-2"
          :class="statusBorderClass[getDateStatus(date.due_date)]"
        >
          <!-- Label input -->
          <input
            v-model="date.label"
            type="text"
            class="form-input flex-1 min-w-0 text-sm"
            :placeholder="$t('cases.date_label')"
            @input="onUpdate"
          />
          <!-- Flatpickr date input -->
          <flat-pickr
            v-model="date.due_date"
            :config="dateConfig"
            class="form-input w-36 text-sm"
            :placeholder="$t('cases.pick_date')"
            @on-change="onUpdate"
          />
          <!-- Status badge -->
          <span :class="statusBadgeClass[getDateStatus(date.due_date)]" class="text-xs shrink-0 hidden sm:inline-flex">
            {{ statusLabel[getDateStatus(date.due_date)] }}
          </span>
          <!-- Remove button -->
          <button
            type="button"
            class="text-danger hover:text-danger/80 shrink-0"
            @click="removeDate(idx)"
            :title="$t('cases.remove_date')"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Empty state -->
        <div v-if="localDates.length === 0" class="text-sm text-gray-400 italic py-2 text-center">
          {{ $t('cases.no_dates') }}
        </div>
      </div>

      <!-- Add button -->
      <button
        v-if="localDates.length < maxDates"
        type="button"
        class="btn btn-outline-primary btn-sm mt-3 gap-1"
        @click="addDate"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        {{ $t('cases.add_date') }}
      </button>

      <!-- Max limit warning -->
      <p v-if="localDates.length >= maxDates" class="text-xs text-warning mt-2">
        {{ $t('cases.max_dates_reached') }}
      </p>
    </template>
  </div>
</template>
