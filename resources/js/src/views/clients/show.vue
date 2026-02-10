<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/clients" class="text-primary hover:underline">{{ $t('clients.clients') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ client?.first_name }} {{ client?.last_name }}</span>
            </li>
        </ul>

        <!-- Loading State -->
        <div v-if="isLoading" class="panel">
            <div class="animate-pulse space-y-4">
                <div class="flex items-center gap-4">
                    <div class="w-20 h-20 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                    <div class="space-y-2">
                        <div class="h-6 w-48 bg-gray-200 dark:bg-gray-700 rounded"></div>
                        <div class="h-4 w-32 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Client Profile -->
        <div v-else-if="client" class="space-y-5">
            <!-- Header Card -->
            <div class="panel">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center text-2xl font-bold" :class="getStatusAvatarClass(client.status)">
                            {{ getInitials(client.first_name, client.last_name) }}
                        </div>
                        <div>
                            <h4 class="text-xl font-bold dark:text-white-light">
                                {{ client.first_name }} {{ client.last_name }}
                            </h4>
                            <p class="text-gray-500">{{ client.profession || $t('clients.no_profession') }}</p>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="badge" :class="getStatusBadgeClass(client.status)">
                                    {{ $t(`clients.${client.status}`) }}
                                </span>
                                <span v-if="client.canada_status" class="badge badge-outline-primary">
                                    {{ formatCanadaStatus(client.canada_status) }}
                                </span>
                                <span v-if="client.is_primary_applicant" class="badge badge-outline-success">
                                    {{ $t('clients.primary_applicant') }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button
                            v-if="client.status === 'prospect'"
                            v-can="'clients.update'"
                            type="button"
                            class="btn btn-success gap-2"
                            @click="confirmConvert"
                        >
                            <icon-arrow-forward class="w-5 h-5" />
                            {{ $t('clients.convert_to_active') }}
                        </button>
                        <router-link
                            v-can="'clients.update'"
                            :to="`/clients/${client.id}/edit`"
                            class="btn btn-primary gap-2"
                        >
                            <icon-pencil class="w-5 h-5" />
                            {{ $t('clients.edit') }}
                        </router-link>
                        <router-link to="/clients" class="btn btn-outline-secondary gap-2">
                            <icon-arrow-left class="w-5 h-5" />
                            {{ $t('clients.back') }}
                        </router-link>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="panel p-0">
                <ul class="flex flex-wrap border-b border-gray-200 dark:border-gray-700">
                    <li>
                        <button
                            type="button"
                            class="px-5 py-3 border-b-2 font-medium transition-colors"
                            :class="activeTab === 'personal' ? 'border-primary text-primary' : 'border-transparent hover:text-primary'"
                            @click="activeTab = 'personal'"
                        >
                            {{ $t('clients.personal_information') }}
                        </button>
                    </li>
                    <li>
                        <button
                            type="button"
                            class="px-5 py-3 border-b-2 font-medium transition-colors"
                            :class="activeTab === 'contact' ? 'border-primary text-primary' : 'border-transparent hover:text-primary'"
                            @click="activeTab = 'contact'"
                        >
                            {{ $t('clients.contact_information') }}
                        </button>
                    </li>
                    <li>
                        <button
                            type="button"
                            class="px-5 py-3 border-b-2 font-medium transition-colors"
                            :class="activeTab === 'canada' ? 'border-primary text-primary' : 'border-transparent hover:text-primary'"
                            @click="activeTab = 'canada'"
                        >
                            {{ $t('clients.canada_legal_status') }}
                        </button>
                    </li>
                    <li>
                        <button
                            type="button"
                            class="px-5 py-3 border-b-2 font-medium transition-colors"
                            :class="activeTab === 'cases' ? 'border-primary text-primary' : 'border-transparent hover:text-primary'"
                            @click="activeTab = 'cases'"
                        >
                            {{ $t('clients.cases') }}
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="p-5">
                    <!-- Personal Information Tab -->
                    <div v-if="activeTab === 'personal'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.first_name') }}</label>
                            <p class="font-semibold">{{ client.first_name }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.last_name') }}</label>
                            <p class="font-semibold">{{ client.last_name }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.date_of_birth') }}</label>
                            <p class="font-semibold">{{ client.date_of_birth ? formatDate(client.date_of_birth) : '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.gender') }}</label>
                            <p class="font-semibold capitalize">{{ client.gender || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.nationality') }}</label>
                            <p class="font-semibold">{{ client.nationality || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.marital_status') }}</label>
                            <p class="font-semibold capitalize">{{ client.marital_status?.replace('_', ' ') || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.profession') }}</label>
                            <p class="font-semibold">{{ client.profession || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.language') }}</label>
                            <p class="font-semibold">{{ client.language || '-' }}</p>
                        </div>
                        <div class="md:col-span-2 lg:col-span-3">
                            <label class="text-gray-500 text-sm">{{ $t('clients.notes') }}</label>
                            <p class="font-semibold whitespace-pre-wrap">{{ client.description || '-' }}</p>
                        </div>
                    </div>

                    <!-- Contact Information Tab -->
                    <div v-else-if="activeTab === 'contact'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.email') }}</label>
                            <p class="font-semibold">
                                <a v-if="client.email" :href="`mailto:${client.email}`" class="text-primary hover:underline">
                                    {{ client.email }}
                                </a>
                                <span v-else>-</span>
                            </p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.phone') }}</label>
                            <p class="font-semibold">
                                <a v-if="client.phone" :href="`tel:${client.phone}`" class="text-primary hover:underline">
                                    {{ client.phone }}
                                </a>
                                <span v-else>-</span>
                            </p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.secondary_phone') }}</label>
                            <p class="font-semibold">{{ client.secondary_phone || '-' }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-gray-500 text-sm">{{ $t('clients.residential_address') }}</label>
                            <p class="font-semibold">{{ client.residential_address || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.city') }}</label>
                            <p class="font-semibold">{{ client.city || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.province') }}</label>
                            <p class="font-semibold">{{ client.province || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.postal_code') }}</label>
                            <p class="font-semibold">{{ client.postal_code || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.country') }}</label>
                            <p class="font-semibold">{{ client.country || '-' }}</p>
                        </div>
                    </div>

                    <!-- Canada Legal Status Tab -->
                    <div v-else-if="activeTab === 'canada'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.status_in_canada') }}</label>
                            <p class="font-semibold">{{ client.canada_status ? formatCanadaStatus(client.canada_status) : '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.entry_point') }}</label>
                            <p class="font-semibold capitalize">{{ client.entry_point?.replace('_', ' ') || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.arrival_date') }}</label>
                            <p class="font-semibold">{{ client.arrival_date ? formatDate(client.arrival_date) : '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.passport_number') }}</label>
                            <p class="font-semibold">{{ client.passport_number || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.passport_country') }}</label>
                            <p class="font-semibold">{{ client.passport_country || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.passport_expiry') }}</label>
                            <p class="font-semibold">{{ client.passport_expiry_date ? formatDate(client.passport_expiry_date) : '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.iuc') }}</label>
                            <p class="font-semibold">{{ client.iuc || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.work_permit_number') }}</label>
                            <p class="font-semibold">{{ client.work_permit_number || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.study_permit_number') }}</label>
                            <p class="font-semibold">{{ client.study_permit_number || '-' }}</p>
                        </div>
                    </div>

                    <!-- Cases Tab -->
                    <div v-else-if="activeTab === 'cases'">
                        <div v-if="!client.cases?.length" class="text-center py-10">
                            <icon-folder class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
                            <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">{{ $t('clients.no_cases_yet') }}</h3>
                            <p class="text-gray-500 mb-4">{{ $t('clients.cases_will_appear_here') }}</p>
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="caseItem in client.cases"
                                :key="caseItem.id"
                                class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h5 class="font-semibold">{{ caseItem.case_number }}</h5>
                                        <p class="text-sm text-gray-500">{{ caseItem.case_type }}</p>
                                    </div>
                                    <span class="badge" :class="getCaseBadgeClass(caseItem.status)">
                                        {{ caseItem.status }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Metadata -->
            <div class="panel">
                <div class="flex flex-wrap gap-6 text-sm text-gray-500">
                    <div>
                        <span class="font-medium">{{ $t('clients.created') }}:</span>
                        {{ formatDate(client.created_at) }}
                    </div>
                    <div>
                        <span class="font-medium">{{ $t('clients.updated') }}:</span>
                        {{ formatDate(client.updated_at) }}
                    </div>
                    <div>
                        <span class="font-medium">ID:</span>
                        #{{ client.id }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Not Found -->
        <div v-else class="panel text-center py-10">
            <icon-users class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
            <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">{{ $t('clients.not_found') }}</h3>
            <router-link to="/clients" class="btn btn-primary mt-4">
                {{ $t('clients.back_to_list') }}
            </router-link>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useMeta } from '@/composables/use-meta';
import { useClientStore } from '@/stores/client';
import { useNotification } from '@/composables/useNotification';
import { useI18n } from 'vue-i18n';
import { formatDate } from '@/utils/formatters';
import type { Client, ClientStatus } from '@/types/client';

// Icons
import IconArrowLeft from '@/components/icon/icon-arrow-left.vue';
import IconPencil from '@/components/icon/icon-pencil.vue';
import IconArrowForward from '@/components/icon/icon-arrow-forward.vue';
import IconUsers from '@/components/icon/icon-users.vue';
import IconFolder from '@/components/icon/icon-folder.vue';

useMeta({ title: 'Client Profile' });

const route = useRoute();
const clientStore = useClientStore();
const { confirm: confirmDialog, success, error } = useNotification();
const { t } = useI18n();

const client = ref<Client | null>(null);
const isLoading = ref(true);
const activeTab = ref('personal');

const getInitials = (firstName: string, lastName: string): string => {
    return ((firstName?.[0] || '') + (lastName?.[0] || '')).toUpperCase();
};

const getStatusBadgeClass = (status: ClientStatus): string => {
    const classes: Record<ClientStatus, string> = {
        prospect: 'badge-outline-info',
        active: 'badge-outline-success',
        inactive: 'badge-outline-warning',
        archived: 'badge-outline-secondary',
    };
    return classes[status] || 'badge-outline-primary';
};

const getStatusAvatarClass = (status: ClientStatus): string => {
    const classes: Record<ClientStatus, string> = {
        prospect: 'bg-info/10 text-info',
        active: 'bg-success/10 text-success',
        inactive: 'bg-warning/10 text-warning',
        archived: 'bg-secondary/10 text-secondary',
    };
    return classes[status] || 'bg-primary/10 text-primary';
};

const formatCanadaStatus = (status: string): string => {
    return status.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
};

const getCaseBadgeClass = (status: string): string => {
    const classes: Record<string, string> = {
        active: 'badge-outline-success',
        pending: 'badge-outline-warning',
        closed: 'badge-outline-secondary',
    };
    return classes[status] || 'badge-outline-primary';
};

const confirmConvert = async () => {
    if (!client.value) return;

    const confirmed = await confirmDialog({
        title: t('clients.confirm_convert', { name: `${client.value.first_name} ${client.value.last_name}` }),
        text: t('clients.convert_description'),
        icon: 'info',
        confirmButtonText: t('clients.yes_convert'),
        cancelButtonText: t('clients.cancel'),
    });

    if (confirmed) {
        try {
            await clientStore.convertProspect(client.value.id);
            client.value = clientStore.currentClient;
            success(t('clients.converted_successfully'));
        } catch (err: any) {
            error(err.response?.data?.message || t('clients.convert_failed'));
        }
    }
};

onMounted(async () => {
    try {
        const id = parseInt(route.params.id as string);
        client.value = await clientStore.fetchClient(id);
    } catch (err) {
        error(t('clients.failed_to_load'));
    } finally {
        isLoading.value = false;
    }
});
</script>
