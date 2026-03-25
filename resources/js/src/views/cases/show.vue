<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/cases" class="text-primary hover:underline">{{ $t('sidebar.cases') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ currentCase?.case_number || '...' }}</span>
            </li>
        </ul>

        <!-- Loading State -->
        <div v-if="isLoading" class="panel">
            <div class="animate-pulse space-y-4">
                <div class="h-8 w-48 bg-gray-200 dark:bg-gray-700 rounded"></div>
                <div class="h-4 w-full bg-gray-200 dark:bg-gray-700 rounded"></div>
                <div class="h-4 w-3/4 bg-gray-200 dark:bg-gray-700 rounded"></div>
            </div>
        </div>

        <!-- Case Details -->
        <div v-else-if="currentCase" class="space-y-5">
            <!-- Header Card -->
            <div class="panel">
                <h2 class="text-2xl font-bold dark:text-white-light">{{ currentCase.case_number }}</h2>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-4 p-4 border border-gray-200 dark:border-gray-700 rounded-lg mt-4">
                            <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center">
                                <span class="text-lg font-semibold text-primary">
                                    {{ getInitials(currentCase.client.first_name, currentCase.client.last_name) }}
                                </span>
                            </div>
                            <div class="flex-1">
                                <div class="font-semibold">
                                    <router-link :to="`/clients/${currentCase.client.id}`" 
                                    class="text-primary font-semibold hover:underline text-xl">
                                    {{ currentCase.client.full_name || `${currentCase.client.first_name} ${currentCase.client.last_name}` }}
                                    </router-link>
                                </div>
                                <div class="text-sm text-gray-500">{{ currentCase.client.email }}</div>
                                <!-- <div v-if="currentCase.client.phone" class="text-sm text-gray-500">{{ currentCase.client.phone }}</div> -->
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <ul class="list-none space-y-2 text-sm w-full sm:w-auto">
                            <li v-if="currentCase.case_type" class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-x-4 py-0"
                            >
                                <span class="text-gray-500 shrink-0 sm:w-30 dark:text-white-light">{{ $t('cases.case_type') }}:</span>
                                <span class="text-gray-500 min-w-0 sm:flex-1">{{ currentCase.case_type.name }}</span>
                            </li>
                            <li
                                v-if="currentCase.created_at" class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-x-4 py-0"
                            >
                                <span class="text-gray-500 shrink-0 sm:w-30 dark:text-white-light">{{ $t('cases.created_at') }}:</span>
                                <span class="text-gray-500 min-w-0 sm:flex-1">{{ formatDateTime(currentCase.created_at) }}</span>
                            </li>
                            <li
                                v-if="currentCase.updated_at" class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-x-4 py-0"
                            >
                                <span class="text-gray-500 shrink-0 sm:w-30 dark:text-white-light">{{ $t('cases.updated_at') }}:</span>
                                <span class="text-gray-500 min-w-0 sm:flex-1">{{ formatDateTime(currentCase.updated_at) }}</span>
                            </li>
                            <li
                                v-if="currentCase.assigned_user" class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-x-4 py-0"
                            >
                                <span class="text-gray-500 shrink-0 sm:w-30 dark:text-white-light">{{ $t('cases.assigned_to') }}:</span>
                                <span class="text-gray-500 min-w-0 sm:flex-1">{{ currentCase.assigned_user?.name }}</span>
                            </li>
                            <!-- Language -->
                            <li v-if="currentCase.language" class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-x-4 py-0">
                                <span class="text-gray-500 shrink-0 sm:w-30 dark:text-white-light">{{ $t('cases.language') }}:</span>
                                <span class="text-gray-500 min-w-0 sm:flex-1">{{ currentCase.language?.toUpperCase() || '-' }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <ul class="list-none space-y-0 text-sm w-full sm:w-auto">

                            <!-- Stage -->
                            <li v-if="currentCase.stage" class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-x-4 py-0">
                                <span class="text-gray-500 shrink-0 sm:w-30 dark:text-white-light">{{ $t('cases.stage') }}:</span>
                                <span :class="`badge badge-outline-${stageColor} shrink-0`">{{
                                    $t(CASE_STAGE_OPTIONS.find(o => o.value === currentCase.stage)?.label ?? currentCase.stage ?? '')
                                }}</span>
                            </li>
                            <!-- IRCC Status -->
                            <li v-if="currentCase.ircc_status" class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-x-4 py-0">
                                <span class="text-gray-500 shrink-0 sm:w-30 dark:text-white-light">{{ $t('cases.ircc_status') }}:</span>
                                <span :class="`badge badge-outline-${irccColor} shrink-0`">{{ currentCase.ircc_status_label }}</span>
                            </li>
                            <!-- Final Result -->
                            <li v-if="currentCase.final_result" class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-x-4 py-0">
                                <span class="text-gray-500 shrink-0 sm:w-30 dark:text-white-light">{{ $t('cases.final_result') }}:</span>
                                <span :class="`badge badge-outline-${finalResultColor} shrink-0`">{{ currentCase.final_result_label }}</span>
                            </li>
                            <!-- IRCC Code -->
                            <li v-if="currentCase.ircc_code" class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-x-4 py-0">
                                <span class="text-gray-500 shrink-0 sm:w-30 dark:text-white-light">{{ $t('cases.ircc_code') }}:</span>
                                <span class="text-sm font-mono font-medium min-w-0 sm:flex-1 break-all">{{ currentCase.ircc_code }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge rounded-full" :class="getStatusBadgeClass(currentCase.status)">
                            {{ $t(`cases.${currentCase.status}`) }}
                        </span>
                        <span class="badge rounded-full" :class="getPriorityBadgeClass(currentCase.priority)">
                            {{ $t(`cases.${currentCase.priority}`) }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-row flex-nowrap items-center gap-4 mt-4 min-w-0">
                    <div class="min-w-0 flex-[2] flex flex-col justify-center">
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-gray-500">{{ $t('cases.progress') }}</span>
                            <span class="text-gray-500">{{ currentCase.progress }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                            <div class="h-3 rounded-full transition-all" :class="getProgressBarClass(currentCase.progress)" :style="{ width: `${currentCase.progress}%` }"></div>
                        </div>
                    </div>
                    <div class="flex-[1] flex flex-nowrap justify-end gap-2 items-center min-w-0">
                        <router-link v-can="'cases.update'" :to="`/cases/${currentCase.id}/edit`" class="btn btn-primary gap-2 btn-sm">
                            <icon-pencil class="w-4 h-4" />
                            {{ $t('cases.edit') }}
                        </router-link>
                        <button v-can="'cases.assign'" type="button" class="btn btn-warning gap-2 btn-sm" @click="openAssignDialog">
                            <icon-user-plus class="w-4 h-4" />
                            {{ $t('cases.assign') }}
                        </button>
                        <button v-can="'cases.delete'" type="button" class="btn btn-danger gap-2 btn-sm" @click="confirmDelete">
                            <icon-trash-lines class="w-4 h-4" />
                            {{ $t('cases.delete') }}
                        </button>
                        <router-link v-can="'cases.assign'" :to="`/clients/${currentCase.client.id}`" class="btn btn-secondary gap-2 btn-sm">
                            <icon-pencil class="w-4 h-4" />
                            {{ $t('cases.client') }}
                        </router-link>
                    </div>
                </div>
            </div>

            <!-- Tabs Panel -->
            <div class="panel p-0">
                <!-- Tabs Navigation -->
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="flex -mb-px">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            type="button"
                            class="px-6 py-4 text-sm font-medium border-b-2 transition-colors"
                            :class="activeTab === tab.id
                                ? 'border-primary text-primary'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            @click="activeTab = tab.id"
                        >
                            {{ $t(tab.label) }}
                        </button>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="p-5">
                    <!-- Information Tab -->
                    <div v-if="activeTab === 'info'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- General Information -->
                        <div class="space-y-4">
                            <!-- <h3 class="font-semibold text-lg dark:text-white-light">{{ $t('cases.general_info') }}</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">{{ $t('cases.case_number') }}</span>
                                    <span class="font-medium">{{ currentCase.case_number }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">{{ $t('cases.case_type') }}</span>
                                    <span>{{ currentCase.case_type?.name || '-' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">{{ $t('cases.status') }}</span>
                                    <span class="badge" :class="getStatusBadgeClass(currentCase.status)">
                                        {{ $t(`cases.${currentCase.status}`) }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">{{ $t('cases.priority') }}</span>
                                    <span class="badge" :class="getPriorityBadgeClass(currentCase.priority)">
                                        {{ $t(`cases.${currentCase.priority}`) }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">{{ $t('cases.language') }}</span>
                                    <span>{{ currentCase.language?.toUpperCase() || '-' }}</span>
                                </div>
                                <!-- Stage -->
                                <!-- <div v-if="currentCase.stage" class="flex items-center justify-between py-1">
                                    <span class="text-gray-500">{{ $t('cases.stage') }}</span>
                                    <span :class="`badge badge-outline-${stageColor}`">{{ $t(CASE_STAGE_OPTIONS.find(o => o.value === currentCase.stage)?.label ?? currentCase.stage ?? '') }}</span>
                                </div> -->
                                <!-- IRCC Status -->
                                <!-- <div v-if="currentCase.ircc_status" class="flex items-center justify-between py-1">
                                    <span class="text-gray-500">{{ $t('cases.ircc_status') }}</span>
                                    <span :class="`badge badge-outline-${irccColor}`">{{ currentCase.ircc_status_label }}</span>
                                </div> -->
                                <!-- Final Result -->
                                <!-- <div v-if="currentCase.final_result" class="flex items-center justify-between py-1">
                                    <span class="text-gray-500">{{ $t('cases.final_result') }}</span>
                                    <span :class="`badge badge-outline-${finalResultColor}`">{{ currentCase.final_result_label }}</span>
                                </div> -->
                                <!-- IRCC Code -->
                                <!-- <div v-if="currentCase.ircc_code" class="flex items-center justify-between py-1">
                                    <span class="text-gray-500">{{ $t('cases.ircc_code') }}</span>
                                    <span class="text-sm font-mono font-medium">{{ currentCase.ircc_code }}</span>
                                </div>
                            </div> -->

                            <!-- Progress -->
                            <!-- <div class="pt-4">
                                <div class="flex justify-between text-sm mb-2">
                                    <span class="text-gray-500">{{ $t('cases.progress') }}</span>
                                    <span>{{ currentCase.progress }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                                    <div class="h-3 rounded-full transition-all" :class="getProgressBarClass(currentCase.progress)" :style="{ width: `${currentCase.progress}%` }"></div>
                                </div>
                            </div> -->

                            <!-- Client Info -->
                            <div v-if="currentCase.client" class="space-y-4">
                                <h3 class="font-semibold text-lg dark:text-white-light">{{ $t('cases.client_info') }}</h3>
                                <div class="flex items-center gap-4 p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                                    <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center">
                                        <span class="text-lg font-semibold text-primary">
                                            {{ getInitials(currentCase.client.first_name, currentCase.client.last_name) }}
                                        </span>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-semibold">{{ currentCase.client.full_name || `${currentCase.client.first_name} ${currentCase.client.last_name}` }}</div>
                                        <div class="text-sm text-gray-500">{{ currentCase.client.email }}</div>
                                        <div v-if="currentCase.client.phone" class="text-sm text-gray-500">{{ currentCase.client.phone }}</div>
                                    </div>
                                    <router-link :to="`/clients/${currentCase.client.id}`" class="btn btn-sm btn-outline-primary">
                                        {{ $t('cases.view_client') }}
                                    </router-link>
                                </div>
                            </div>

                            <!-- Companions -->
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-semibold text-lg dark:text-white-light">
                                        {{ $t('cases.companions') }}
                                    </h3>
                                    <span v-if="currentCase.companions?.length" class="badge badge-outline-secondary">
                                        {{ currentCase.companions.length }}
                                    </span>
                                </div>

                                <p v-if="!currentCase.companions || currentCase.companions.length === 0"
                                   class="text-sm text-gray-500 italic">
                                    {{ $t('cases.no_companions') }}
                                </p>

                                <div v-else class="space-y-3">
                                    <div v-for="companion in currentCase.companions" :key="companion.id"
                                         class="flex items-center gap-4 p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                                        <div class="w-12 h-12 rounded-full bg-secondary/10 flex items-center justify-center shrink-0">
                                            <span class="text-lg font-semibold text-secondary">
                                                {{ companion.initials || getInitials(companion.first_name, companion.last_name) }}
                                            </span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="font-semibold truncate">
                                                {{ companion.full_name || `${companion.first_name} ${companion.last_name}` }}
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2 mt-1">
                                                <span class="badge badge-outline-secondary text-xs">
                                                    {{ companion.relationship_label || companion.relationship }}
                                                </span>
                                                <span v-if="companion.age" class="text-xs text-gray-500">
                                                    · {{ $t('cases.companion_age', { age: companion.age }) }}
                                                </span>
                                                <span v-if="companion.nationality" class="text-xs text-gray-500">
                                                    · {{ companion.nationality }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Description -->
                            <div v-if="currentCase.description" class="pt-4">
                                <h3 class="font-semibold text-lg dark:text-white-light">{{ $t('cases.description') }}</h3>
                                <p class="text-gray-600 dark:text-gray-400">{{ currentCase.description }}</p>
                            </div>
                        </div>

                        <!-- Client & Dates -->
                        <div class="space-y-6">
                            <!-- Important Dates -->
                            <div class="space-y-4">
                                <h3 class="font-semibold text-lg dark:text-white-light">{{ $t('cases.important_dates') }}</h3>
                                <DateManager
                                    :model-value="currentCase.important_dates ?? []"
                                    :readonly="true"
                                    :show-quick-event="true"
                                    @quick-event="createQuickEvent"
                                />
                            </div>

                            <!-- Assignment -->
                            <!-- <div class="space-y-4">
                                <h3 class="font-semibold text-lg dark:text-white-light">{{ $t('cases.assignment') }}</h3>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">{{ $t('cases.assigned_to') }}</span>
                                    <span v-if="currentCase.assigned_user">{{ currentCase.assigned_user.name }}</span>
                                    <span v-else class="text-gray-400 italic">{{ $t('cases.unassigned') }}</span>
                                </div>
                            </div> -->
                           
                            <!-- Financial Info -->
                            <div v-if="currentCase.service_type" class="mt-4 pt-4 border-t border-[#e0e6ed] dark:border-[#1b2e4b]">
                                <h3 class="font-semibold text-lg dark:text-white-light">{{ $t('cases.financial_info') }}</h3>
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between py-1">
                                        <span class="text-sm text-gray-500">{{ $t('cases.service_type') }}</span>
                                        <span class="badge" :class="currentCase.service_type === 'pro_bono' ? 'badge-outline-success' : 'badge-outline-primary'">
                                            {{ currentCase.service_type_label }}
                                        </span>
                                    </div>
                                    <div v-if="currentCase.contract_number" class="flex items-center justify-between py-1">
                                        <span class="text-sm text-gray-500">{{ $t('cases.contract_number') }}</span>
                                        <span class="text-sm font-medium">{{ currentCase.contract_number }}</span>
                                    </div>
                                    <div v-if="currentCase.fees !== undefined && currentCase.fees !== null" class="flex items-center justify-between py-1">
                                        <span class="text-sm text-gray-500">{{ $t('cases.fees') }}</span>
                                        <span class="text-sm font-semibold text-success">${{ Number(currentCase.fees).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Metadata -->
                        <div class="space-y-4 w-full lg:w-auto pt-4 border-t border-gray-200 dark:border-gray-700">
                            <h3 class="font-semibold text-lg dark:text-white-light">{{ $t('cases.metadata') }}</h3>
                            <div class="space-y-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">{{ $t('cases.created') }}</span>
                                    <span>{{ formatDateTime(currentCase.created_at) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">{{ $t('cases.updated') }}</span>
                                    <span>{{ formatDateTime(currentCase.updated_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lifecycle Tab -->
                    <div v-else-if="activeTab === 'lifecycle'">
                        <LifecycleChecklist
                            :model-value="currentCase.tasks ?? []"
                            :readonly="true"
                            :case-id="currentCase.id"
                            @progress-updated="(p: number) => { if (currentCase) currentCase.progress = p }"
                        />
                    </div>

                    <!-- Timeline Tab -->
                    <div v-else-if="activeTab === 'timeline'">
                        <div v-if="caseStore.isLoadingTimeline" class="text-center py-8">
                            <span class="animate-spin border-2 border-primary border-l-transparent rounded-full w-8 h-8 inline-block"></span>
                        </div>
                        <div v-else-if="caseStore.timeline.length === 0" class="text-center py-8 text-gray-500">
                            {{ $t('cases.timeline_empty') }}
                        </div>
                        <div v-else class="space-y-4">
                            <div v-for="activity in caseStore.timeline" :key="activity.id" class="flex gap-4">
                                <div class="w-2 h-2 rounded-full bg-primary mt-2 shrink-0"></div>
                                <div class="flex-1 pb-4 border-b border-gray-100 dark:border-gray-700 last:border-0">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-medium">{{ activity.description }}</p>
                                            <p v-if="activity.causer" class="text-sm text-gray-500">{{ $t('cases.by') }} {{ activity.causer.name }}</p>
                                        </div>
                                        <span class="text-xs text-gray-400">{{ formatDateTime(activity.created_at) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Invoices / Account Statement Tab -->
                    <div v-else-if="activeTab === 'invoices'">
                        <InvoiceTable
                            :invoices="currentCase.invoices ?? []"
                            :financial-summary="currentCase.financial_summary ?? null"
                            :case-id="currentCase.id"
                            @saved="() => caseStore.fetchCase(currentCase!.id)"
                        />
                    </div>

                    <!-- Todos Tab -->
                    <div v-else-if="activeTab === 'todos'">
                        <CaseTodoTab
                            :case-id="currentCase.id"
                            :case-number="currentCase.case_number"
                        />
                    </div>

                    <!-- Events Tab -->
                    <div v-else-if="activeTab === 'events'">
                        <CaseEventTab
                            :case-id="currentCase.id"
                            :case-number="currentCase.case_number"
                            :client-name="currentCase.client?.full_name ?? ''"
                        />
                    </div>

                    <!-- Documents Tab -->
                    <CaseDocumentsTab v-else-if="activeTab === 'documents'" :case-id="currentCase.id" />
                </div>
            </div>
        </div>

        <!-- Not Found -->
        <div v-else class="panel text-center py-10">
            <icon-folder class="w-16 h-16 mx-auto text-gray-300 mb-4" />
            <h3 class="text-lg font-semibold text-gray-600 mb-2">{{ $t('cases.not_found') }}</h3>
            <router-link to="/cases" class="btn btn-primary mt-4">{{ $t('cases.back_to_list') }}</router-link>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useMeta } from '@/composables/use-meta';
import Swal from 'sweetalert2';
import { useCaseStore } from '@/stores/case';
import { useNotification } from '@/composables/useNotification';
import userService from '@/services/userService';
import { formatDate } from '@/utils/formatters';
import type { CaseStatus, CasePriority, ImportantDate } from '@/types/case';
import { CASE_STAGE_OPTIONS, IRCC_STATUS_OPTIONS, FINAL_RESULT_OPTIONS, SERVICE_TYPE_OPTIONS } from '@/types/case';
import api from '@/services/api';
import DateManager from '@/components/DateManager.vue';
import LifecycleChecklist from '@/components/LifecycleChecklist.vue';
import InvoiceTable from '@/views/cases/components/InvoiceTable.vue';
import CaseTodoTab from '@/views/cases/components/CaseTodoTab.vue';
import CaseEventTab from '@/views/cases/components/CaseEventTab.vue';
import CaseDocumentsTab from '@/views/cases/components/CaseDocumentsTab.vue';

// Icons
import IconFolder from '@/components/icon/icon-folder.vue';
import IconPencil from '@/components/icon/icon-pencil.vue';
import IconTrashLines from '@/components/icon/icon-trash-lines.vue';
import IconUserPlus from '@/components/icon/icon-user-plus.vue';

useMeta({ title: 'Case Details' });

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const caseStore = useCaseStore();
const { confirm: confirmDialog, success, error } = useNotification();

const isLoading = ref(true);
const activeTab = ref('info');

const tabs = computed(() => [
    { id: 'info', label: 'cases.tab_information' },
    { id: 'lifecycle', label: 'cases.tab_lifecycle' },
    { id: 'documents', label: 'cases.tab_documents' },
    { id: 'events', label: 'cases.tab_events' },
    { id: 'todos', label: 'cases.tab_todos' },
    { id: 'invoices', label: 'cases.tab_invoices' },
    { id: 'timeline', label: 'cases.tab_timeline' },
]);

const currentCase = computed(() => caseStore.currentCase);

// Helper functions
const getInitials = (firstName: string, lastName: string): string => {
    return ((firstName?.[0] || '') + (lastName?.[0] || '')).toUpperCase();
};

const getStatusBadgeClass = (status: CaseStatus): string => {
    const classes: Record<CaseStatus, string> = {
        active: 'badge-outline-success',
        inactive: 'badge-outline-warning',
        archived: 'badge-outline-secondary',
        closed: 'badge-outline-dark',
    };
    return classes[status] || 'badge-outline-primary';
};

const getPriorityBadgeClass = (priority: CasePriority): string => {
    const classes: Record<CasePriority, string> = {
        urgent: 'badge-outline-danger',
        high: 'badge-outline-warning',
        medium: 'badge-outline-info',
        low: 'badge-outline-secondary',
    };
    return classes[priority] || 'badge-outline-primary';
};

const getProgressBarClass = (progress: number): string => {
    if (progress >= 75) return 'bg-success';
    if (progress >= 50) return 'bg-info';
    if (progress >= 25) return 'bg-warning';
    return 'bg-danger';
};

const stageColor = computed(() => CASE_STAGE_OPTIONS.find(o => o.value === currentCase.value?.stage)?.color ?? 'dark');
const irccColor = computed(() => IRCC_STATUS_OPTIONS.find(o => o.value === currentCase.value?.ircc_status)?.color ?? 'dark');
const finalResultColor = computed(() => FINAL_RESULT_OPTIONS.find(o => o.value === currentCase.value?.final_result)?.color ?? 'dark');

const formatDateTime = (date: string): string => {
    return new Date(date).toLocaleString();
};

const openAssignDialog = async () => {
    if (!currentCase.value) return;

    try {
        const staff = await userService.getStaff(currentCase.value.assigned_user?.id);
        const options: Record<string, string> = {};
        for (const member of staff) {
            options[member.id.toString()] = member.name;
        }

        const { value: userId } = await Swal.fire({
            title: t('cases.assign'),
            input: 'select',
            inputOptions: options,
            inputValue: currentCase.value.assigned_user?.id?.toString() || '',
            inputPlaceholder: t('cases.unassigned'),
            showCancelButton: true,
            confirmButtonText: t('cases.assign'),
            cancelButtonText: t('cases.cancel'),
        });

        if (userId) {
            await caseStore.assignCase(currentCase.value.id, parseInt(userId));
            success(t('cases.assigned_successfully'));
            await caseStore.fetchCase(currentCase.value.id);
        }
    } catch (err: any) {
        error(err.response?.data?.message || t('cases.assign_failed'));
    }
};

const confirmDelete = async () => {
    if (!currentCase.value) return;

    const confirmed = await confirmDialog({
        title: t('cases.confirm_delete', { number: currentCase.value.case_number }),
        text: t('cases.delete_warning'),
        icon: 'warning',
        confirmButtonText: t('cases.yes_delete'),
        cancelButtonText: t('cases.cancel'),
    });

    if (confirmed) {
        try {
            await caseStore.deleteCase(currentCase.value.id);
            success(t('cases.deleted_successfully'));
            router.push('/cases');
        } catch (err: any) {
            error(err.response?.data?.message || t('cases.delete_failed'));
        }
    }
};

// Watch for tab changes to load timeline
watch(activeTab, async (newTab) => {
    if (newTab === 'timeline' && currentCase.value) {
        await caseStore.fetchTimeline(currentCase.value.id);
    }
});

// Quick event from important date
const isCreatingEvent = ref(false);
async function createQuickEvent(date: ImportantDate) {
    if (!date.due_date || isCreatingEvent.value || !currentCase.value) return;
    isCreatingEvent.value = true;
    try {
        await api.post('/events', {
            title: `${date.label} - ${currentCase.value.case_number}`,
            start_date: date.due_date,
            end_date: date.due_date,
            all_day: true,
            category: 'importante',
            case_id: currentCase.value.id,
            assigned_to_id: currentCase.value.assigned_to ?? undefined,
        });
        success(t('cases.event_created_from_date'));
    } catch {
        error(t('cases.event_creation_failed'));
    } finally {
        isCreatingEvent.value = false;
    }
}

// Initialize
onMounted(async () => {
    const caseId = parseInt(route.params.id as string);
    try {
        await caseStore.fetchCase(caseId);
    } catch (err) {
        error(t('cases.failed_to_load'));
    } finally {
        isLoading.value = false;
    }
});
</script>
