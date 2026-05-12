<template>
    <div>
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/" class="text-primary hover:underline">{{ $t('home') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('alerts.title') }}</span>
            </li>
        </ul>

        <div class="panel">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
                <h5 class="font-semibold text-lg dark:text-white-light">{{ $t('alerts.title') }}</h5>
            </div>

            <div class="mb-5 border-b border-[#e0e6ed] dark:border-[#1b2e4b]">
                <ul class="flex flex-wrap gap-x-2 font-semibold">
                    <li>
                        <button
                            type="button"
                            class="p-3.5 py-2 text-sm transition"
                            :class="activeTab === 'documents'
                                ? 'border-b-2 border-primary text-primary -mb-px'
                                : 'text-gray-500 hover:text-primary'"
                            @click="activeTab = 'documents'"
                        >
                            {{ $t('alerts.tab_documents') }}
                        </button>
                    </li>
                    <li>
                        <button
                            type="button"
                            class="p-3.5 py-2 text-sm transition"
                            :class="activeTab === 'important-dates'
                                ? 'border-b-2 border-primary text-primary -mb-px'
                                : 'text-gray-500 hover:text-primary'"
                            @click="activeTab = 'important-dates'"
                        >
                            {{ $t('alerts.tab_important_dates') }}
                        </button>
                    </li>
                </ul>
            </div>

            <LegalDocumentsAlertsPanel v-if="activeTab === 'documents'" />
            <ImportantDatesAlertsPanel v-if="activeTab === 'important-dates'" />
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref } from 'vue';
import { useMeta } from '@/composables/use-meta';
import LegalDocumentsAlertsPanel from '@/components/alerts/LegalDocumentsAlertsPanel.vue';
import ImportantDatesAlertsPanel from '@/components/alerts/ImportantDatesAlertsPanel.vue';

useMeta({ title: 'Expiration Alerts' });

const activeTab = ref<'documents' | 'important-dates'>('documents');
</script>
