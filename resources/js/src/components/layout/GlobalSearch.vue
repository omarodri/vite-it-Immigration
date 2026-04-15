<template>
    <div class="global-search-container relative sm:w-80 lg:w-96">
        <div class="relative">
            <input
                ref="inputRef"
                v-model="query"
                type="text"
                role="combobox"
                :aria-expanded="isOpen"
                aria-autocomplete="list"
                aria-controls="global-search-listbox"
                class="form-input ltr:pl-9 rtl:pr-9 peer sm:bg-transparent bg-gray-100 placeholder:tracking-widest w-full"
                :placeholder="$t('search.placeholder')"
                @keydown.arrow-down.prevent="navigateDown()"
                @keydown.arrow-up.prevent="navigateUp()"
                @keydown.enter.prevent="selectActive()"
                @keydown.escape="close()"
                @focus="results.length > 0 && (isOpen = true)"
            />
            <button
                type="button"
                class="absolute w-9 h-9 inset-0 ltr:right-auto rtl:left-auto appearance-none peer-focus:text-primary"
                tabindex="-1"
            >
                <icon-search class="mx-auto" />
            </button>
        </div>

        <!-- Dropdown -->
        <div
            v-show="isOpen && (results.length > 0 || isLoading || query.length >= 2)"
            id="global-search-listbox"
            role="listbox"
            aria-live="polite"
            class="absolute top-full mt-1 ltr:left-0 rtl:right-0 w-full min-w-[320px] bg-white dark:bg-[#1b2e4b] border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50 overflow-hidden"
        >
            <!-- Loading -->
            <div v-if="isLoading" class="flex items-center justify-center gap-2 px-4 py-6 text-gray-500 dark:text-gray-400">
                <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <span class="text-sm">{{ $t('search.loading') }}</span>
            </div>

            <!-- No results -->
            <div
                v-else-if="!isLoading && results.length === 0 && query.length >= 2"
                class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400"
            >
                {{ $t('search.no_results') }}
            </div>

            <!-- Results list -->
            <div v-else class="max-h-[320px] overflow-y-auto">
                <div
                    v-for="(item, i) in results"
                    :key="`${item.type}-${item.id}`"
                    role="option"
                    :aria-selected="activeIndex === i"
                    class="flex items-center gap-3 px-4 py-2.5 cursor-pointer transition-colors duration-150"
                    :class="activeIndex === i ? 'bg-primary/10 dark:bg-primary/20' : 'hover:bg-gray-50 dark:hover:bg-[#1a2941]'"
                    @click="selectResult(item)"
                    @mouseenter="activeIndex = i"
                >
                    <!-- Type badge -->
                    <span
                        class="inline-flex items-center shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="typeBadgeClass(item.type)"
                    >
                        {{ $t(`search.types.${item.type}`) }}
                    </span>

                    <!-- Content -->
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-semibold text-gray-800 dark:text-white truncate">{{ item.label }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ item.description }}</div>
                    </div>
                </div>
            </div>

            <!-- Footer hint -->
            <div class="border-t border-gray-200 dark:border-gray-700 px-4 py-2 text-xs text-gray-400 dark:text-gray-500 text-center">
                {{ $t('search.hint_keyboard') }}
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
    import { ref, watch, onMounted, onUnmounted } from 'vue';
    import { useRouter } from 'vue-router';
    import { useI18n } from 'vue-i18n';
    import searchService from '@/services/searchService';
    import type { SearchResult } from '@/types/search';
    import IconSearch from '@/components/icon/icon-search.vue';

    const { t } = useI18n();
    const router = useRouter();

    const query = ref('');
    const results = ref<SearchResult[]>([]);
    const isLoading = ref(false);
    const isOpen = ref(false);
    const activeIndex = ref(-1);
    const inputRef = ref<HTMLInputElement | null>(null);

    let debounceTimer: ReturnType<typeof setTimeout>;

    const badgeClasses: Record<string, string> = {
        client: 'bg-primary/10 text-primary',
        case: 'bg-success/10 text-success',
        companion: 'bg-warning/10 text-warning dark:text-warning',
        event: 'bg-info/10 text-info',
        todo: 'bg-secondary/10 text-secondary',
        trash: 'bg-danger/10 text-danger',
    };

    function typeBadgeClass(type: string): string {
        return badgeClasses[type] || 'bg-gray-100 text-gray-600';
    }

    watch(query, (val) => {
        clearTimeout(debounceTimer);
        activeIndex.value = -1;
        if (val.length < 2) {
            results.value = [];
            isOpen.value = false;
            return;
        }
        isLoading.value = true;
        isOpen.value = true;
        debounceTimer = setTimeout(async () => {
            try {
                const response = await searchService.search(val);
                results.value = response.results;
            } catch {
                results.value = [];
            } finally {
                isLoading.value = false;
            }
        }, 300);
    });

    function navigateDown() {
        if (!isOpen.value) return;
        activeIndex.value = Math.min(activeIndex.value + 1, results.value.length - 1);
    }

    function navigateUp() {
        if (!isOpen.value) return;
        activeIndex.value = Math.max(activeIndex.value - 1, 0);
    }

    function selectActive() {
        if (activeIndex.value >= 0 && results.value[activeIndex.value]) {
            selectResult(results.value[activeIndex.value]);
        }
    }

    function selectResult(item: SearchResult) {
        router.push(item.route);
        close();
    }

    function close() {
        isOpen.value = false;
        activeIndex.value = -1;
        query.value = '';
    }

    function handleGlobalKeydown(e: KeyboardEvent) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            inputRef.value?.focus();
            isOpen.value = true;
        }
    }

    function handleClickOutside(e: MouseEvent) {
        const target = e.target as HTMLElement;
        if (!target.closest('.global-search-container')) {
            close();
        }
    }

    onMounted(() => {
        window.addEventListener('keydown', handleGlobalKeydown);
        document.addEventListener('click', handleClickOutside);
    });

    onUnmounted(() => {
        window.removeEventListener('keydown', handleGlobalKeydown);
        document.removeEventListener('click', handleClickOutside);
        clearTimeout(debounceTimer);
    });
</script>
