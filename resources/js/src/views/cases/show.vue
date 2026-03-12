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
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-bold dark:text-white-light">{{ currentCase.case_number }}</h2>
                        <p v-if="currentCase.case_type" class="text-gray-500">{{ currentCase.case_type.name }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge text-base px-4 py-2" :class="getStatusBadgeClass(currentCase.status)">
                            {{ $t(`cases.${currentCase.status}`) }}
                        </span>
                        <span class="badge text-base px-4 py-2" :class="getPriorityBadgeClass(currentCase.priority)">
                            {{ $t(`cases.${currentCase.priority}`) }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 mt-4">
                    <router-link v-can="'cases.update'" :to="`/cases/${currentCase.id}/edit`" class="btn btn-primary gap-2">
                        <icon-pencil class="w-4 h-4" />
                        {{ $t('cases.edit') }}
                    </router-link>
                    <button v-can="'cases.assign'" type="button" class="btn btn-warning gap-2" @click="showAssignModal = true">
                        <icon-user-plus class="w-4 h-4" />
                        {{ $t('cases.assign') }}
                    </button>
                    <button v-can="'cases.delete'" type="button" class="btn btn-danger gap-2" @click="confirmDelete">
                        <icon-trash-lines class="w-4 h-4" />
                        {{ $t('cases.delete') }}
                    </button>
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
                            <h3 class="font-semibold text-lg dark:text-white-light">{{ $t('cases.general_info') }}</h3>
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
                            </div>

                            <!-- Progress -->
                            <div class="pt-4">
                                <div class="flex justify-between text-sm mb-2">
                                    <span class="text-gray-500">{{ $t('cases.progress') }}</span>
                                    <span>{{ currentCase.progress }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                                    <div class="h-3 rounded-full transition-all" :class="getProgressBarClass(currentCase.progress)" :style="{ width: `${currentCase.progress}%` }"></div>
                                </div>
                            </div>

                            <!-- Description -->
                            <div v-if="currentCase.description" class="pt-4">
                                <h4 class="font-medium mb-2">{{ $t('cases.description') }}</h4>
                                <p class="text-gray-600 dark:text-gray-400">{{ currentCase.description }}</p>
                            </div>
                        </div>

                        <!-- Client & Dates -->
                        <div class="space-y-6">
                            <!-- Client Information -->
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

                            <!-- Important Dates -->
                            <div class="space-y-4">
                                <h3 class="font-semibold text-lg dark:text-white-light">{{ $t('cases.important_dates') }}</h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">{{ $t('cases.hearing_date') }}</span>
                                        <div v-if="currentCase.hearing_date">
                                            <span>{{ formatDate(currentCase.hearing_date) }}</span>
                                            <span v-if="currentCase.days_until_hearing !== null" class="text-sm ml-2" :class="getDaysUntilClass(currentCase.days_until_hearing)">
                                                ({{ formatDaysUntil(currentCase.days_until_hearing) }})
                                            </span>
                                        </div>
                                        <span v-else class="text-gray-400">-</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">{{ $t('cases.fda_deadline') }}</span>
                                        <span>{{ currentCase.fda_deadline ? formatDate(currentCase.fda_deadline) : '-' }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">{{ $t('cases.evidence_deadline') }}</span>
                                        <span>{{ currentCase.evidence_deadline ? formatDate(currentCase.evidence_deadline) : '-' }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">{{ $t('cases.brown_sheet_date') }}</span>
                                        <span>{{ currentCase.brown_sheet_date ? formatDate(currentCase.brown_sheet_date) : '-' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Assignment -->
                            <div class="space-y-4">
                                <h3 class="font-semibold text-lg dark:text-white-light">{{ $t('cases.assignment') }}</h3>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">{{ $t('cases.assigned_to') }}</span>
                                    <span v-if="currentCase.assigned_user">{{ currentCase.assigned_user.name }}</span>
                                    <span v-else class="text-gray-400 italic">{{ $t('cases.unassigned') }}</span>
                                </div>
                            </div>

                            <!-- Metadata -->
                            <div class="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
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

                    <!-- Documents Tab (Placeholder) -->
                    <div v-else-if="activeTab === 'documents'" class="text-center py-10 text-gray-500">
                        <icon-folder class="w-16 h-16 mx-auto text-gray-300 mb-4" />
                        <p>{{ $t('cases.documents_coming_soon') }}</p>
                    </div>
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
import { useCaseStore } from '@/stores/case';
import { useNotification } from '@/composables/useNotification';
import { formatDate } from '@/utils/formatters';
import type { CaseStatus, CasePriority } from '@/types/case';

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
const showAssignModal = ref(false);

const tabs = [
    { id: 'info', label: 'cases.tab_information' },
    { id: 'timeline', label: 'cases.tab_timeline' },
    { id: 'documents', label: 'cases.tab_documents' },
];

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

const getDaysUntilClass = (days: number): string => {
    if (days < 0) return 'text-danger';
    if (days <= 7) return 'text-warning';
    return 'text-gray-500';
};

const formatDaysUntil = (days: number): string => {
    if (days < 0) return t('cases.past_due');
    if (days === 0) return t('cases.today');
    return t('cases.days_until_hearing', { days });
};

const formatDateTime = (date: string): string => {
    return new Date(date).toLocaleString();
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
