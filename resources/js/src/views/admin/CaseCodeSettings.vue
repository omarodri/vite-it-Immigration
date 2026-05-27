<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-6">
            <li>
                <router-link to="/admin/tenant/settings" class="text-primary hover:underline">
                    {{ $t('sidebar.admin') }}
                </router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('settings.case_code.title') }}</span>
            </li>
        </ul>

        <div class="panel">
            <!-- Header -->
            <div class="mb-5">
                <h5 class="text-lg font-semibold dark:text-white-light">{{ $t('settings.case_code.title') }}</h5>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ $t('settings.case_code.description') }}
                </p>
            </div>

            <!-- Loading -->
            <div v-if="isLoading" class="flex items-center justify-center py-10">
                <div class="animate-spin border-4 border-primary border-l-transparent rounded-full w-10 h-10"></div>
            </div>

            <!-- Builder -->
            <CaseCodeBuilder
                v-else
                :initial-blocks="pattern.blocks"
                :initial-separator="pattern.separator"
                :initial-include-name="pattern.includeName"
                @saved="onSaved"
            />
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

import { useMeta } from '@/composables/use-meta';
import { useTenantStore } from '@/stores/tenant';
import CaseCodeBuilder from '@/components/admin/CaseCodeBuilder.vue';

const { t } = useI18n();
const tenantStore = useTenantStore();

useMeta({ title: t('settings.case_code.title') });

const isLoading = ref(false);

const pattern = computed(() => tenantStore.caseCodePattern);

onMounted(async () => {
    if (!tenantStore.isLoaded) {
        isLoading.value = true;
        try {
            await tenantStore.fetchTenant();
        } finally {
            isLoading.value = false;
        }
    }
});

function onSaved(_payload: { blocks: string[]; separator: string; includeName: boolean }) {
    // Store already updated optimistically inside the action; nothing else required.
}
</script>
