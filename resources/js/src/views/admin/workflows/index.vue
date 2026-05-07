<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <a href="javascript:;" class="text-primary hover:underline">{{ $t('sidebar.administration') }}</a>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('workflow.title') }}</span>
            </li>
        </ul>

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-semibold dark:text-white-light">{{ $t('workflow.title') }}</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $t('workflow.subtitle') }}</p>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="isLoading" class="panel">
            <div class="animate-pulse space-y-3">
                <div v-for="n in 4" :key="n" class="h-12 bg-gray-200 dark:bg-gray-700 rounded"></div>
            </div>
        </div>

        <!-- List -->
        <div v-else class="panel">
            <div v-if="caseTypes.length === 0" class="text-center py-12 text-gray-400">
                {{ $t('workflow.no_case_types') }}
            </div>
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <router-link
                    v-for="ct in caseTypes"
                    :key="ct.id"
                    :to="`/admin/workflows/${ct.id}`"
                    class="border border-[#e0e6ed] dark:border-[#1b2e4b] rounded-lg p-4 hover:border-primary transition cursor-pointer block"
                >
                    <div class="flex items-start justify-between mb-2">
                        <h3 class="font-semibold text-base dark:text-white-light">{{ ct.name }}</h3>
                        <span class="badge badge-outline-secondary text-xs shrink-0">{{ ct.code }}</span>
                    </div>
                    <p class="text-xs text-gray-500 mb-3">
                        {{ $t(`case_types.${ct.category}`) }}
                    </p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-400">
                            {{ stagesCountFor(ct.id) }} {{ $t('workflow.stages').toLowerCase() }}
                        </span>
                        <span class="text-primary text-sm font-medium">
                            {{ $t('workflow.configure_workflow') }} &rarr;
                        </span>
                    </div>
                </router-link>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useMeta } from '@/composables/use-meta';
import caseService from '@/services/caseService';
import { workflowService } from '@/services/workflowService';
import type { CaseType } from '@/types/case';

const { t } = useI18n();
useMeta({ title: t('workflow.title') });

const caseTypes = ref<CaseType[]>([]);
const stageCounts = ref<Record<number, number>>({});
const isLoading = ref(true);

function stagesCountFor(id: number): number {
    return stageCounts.value[id] ?? 0;
}

onMounted(async () => {
    isLoading.value = true;
    try {
        caseTypes.value = await caseService.getCaseTypes();
        // Best-effort fetch of stage counts (parallel)
        const counts = await Promise.all(
            caseTypes.value.map(async ct => {
                try {
                    const { data } = await workflowService.listStages(ct.id);
                    return [ct.id, data.data.length] as const;
                } catch {
                    return [ct.id, 0] as const;
                }
            }),
        );
        stageCounts.value = Object.fromEntries(counts);
    } finally {
        isLoading.value = false;
    }
});
</script>
