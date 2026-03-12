<template>
    <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
            {{ $t('wizard.step1.title') }}
        </h3>
        <p class="text-gray-500 dark:text-gray-400 mb-6">
            {{ $t('wizard.step1.description') }}
        </p>

        <!-- Loading State -->
        <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="i in 6" :key="i" class="animate-pulse">
                <div class="h-32 bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
            </div>
        </div>

        <!-- Case Types Grid -->
        <div v-else>
            <template v-for="category in categories" :key="category">
                <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-3 mt-6 first:mt-0">
                    {{ $t(`case_types.${category}`) }}
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <CaseTypeCard
                        v-for="caseType in typesByCategory[category]"
                        :key="caseType.id"
                        :case-type="caseType"
                        :is-selected="wizard.state.caseTypeId === caseType.id"
                        @select="selectCaseType"
                    />
                </div>
            </template>
        </div>

        <!-- Empty State -->
        <div v-if="!loading && caseTypes.length === 0" class="text-center py-10">
            <icon-folder class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
            <p class="text-gray-500 dark:text-gray-400">
                {{ $t('wizard.step1.no_case_types') }}
            </p>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, onMounted, inject } from 'vue';
import caseService from '@/services/caseService';
import type { CaseType, CaseTypeCategory } from '@/types/case';
import CaseTypeCard from '../components/CaseTypeCard.vue';
import IconFolder from '@/components/icon/icon-folder.vue';

// Get wizard from parent
const wizard = inject<ReturnType<typeof import('@/composables/useCaseWizard').useCaseWizard>>('wizard')!;

const caseTypes = ref<CaseType[]>([]);
const loading = ref(true);

// Get unique categories
const categories = computed<CaseTypeCategory[]>(() => {
    const uniqueCategories = new Set(caseTypes.value.map((ct) => ct.category));
    return Array.from(uniqueCategories) as CaseTypeCategory[];
});

// Group case types by category
const typesByCategory = computed(() => {
    const grouped: Record<string, CaseType[]> = {};
    for (const category of categories.value) {
        grouped[category] = caseTypes.value.filter((ct) => ct.category === category);
    }
    return grouped;
});

// Select case type
function selectCaseType(id: number) {
    wizard.setCaseType(id);
}

// Fetch case types on mount
onMounted(async () => {
    try {
        caseTypes.value = await caseService.getCaseTypes();
    } catch (error) {
        console.error('Failed to load case types:', error);
    } finally {
        loading.value = false;
    }
});
</script>
