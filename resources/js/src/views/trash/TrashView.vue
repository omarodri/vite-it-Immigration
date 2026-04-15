<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <span class="text-primary">{{ $t('sidebar.system') }}</span>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('trash.title') }}</span>
            </li>
        </ul>

        <div class="panel">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
                <div>
                    <h5 class="font-semibold text-lg dark:text-white-light">{{ $t('trash.title') }}</h5>
                    <p class="text-gray-500 text-sm mt-1">
                        {{ $t('trash.subtitle', { days: trashStore.summary?.auto_purge_days ?? 30 }) }}
                    </p>
                </div>
                <button
                    v-can="'trash.purge'"
                    type="button"
                    class="btn btn-outline-danger gap-2"
                    :disabled="trashStore.totalCount === 0 || trashStore.isSummaryLoading"
                    @click="confirmPurge"
                >
                    <icon-trash class="w-4 h-4" />
                    {{ $t('trash.purge_all') }}
                </button>
            </div>

            <!-- Tabs -->
            <ul class="flex flex-wrap border-b border-gray-200 dark:border-gray-700 mb-5">
                <li v-for="tab in tabs" :key="tab.type">
                    <button
                        type="button"
                        class="px-5 py-3 border-b-2 font-medium transition-colors flex items-center gap-2"
                        :class="
                            activeTab === tab.type
                                ? 'border-primary text-primary'
                                : 'border-transparent hover:text-primary'
                        "
                        @click="switchTab(tab.type)"
                    >
                        {{ tab.label }}
                        <span
                            v-if="tabCount(tab.type) > 0"
                            class="badge badge-outline-danger text-xs px-1.5 py-0"
                        >
                            {{ tabCount(tab.type) }}
                        </span>
                    </button>
                </li>
            </ul>

            <!-- Search -->
            <div class="mb-4">
                <input
                    v-model="searchQuery"
                    type="text"
                    :placeholder="$t('trash.search_placeholder')"
                    class="form-input w-full max-w-sm"
                />
            </div>

            <!-- Loading Skeleton -->
            <div v-if="trashStore.isLoading" class="space-y-3">
                <div
                    v-for="i in 5"
                    :key="i"
                    class="animate-pulse h-16 bg-gray-200 dark:bg-gray-700 rounded-lg"
                ></div>
            </div>

            <!-- Empty State -->
            <div v-else-if="trashStore.items.length === 0" class="text-center py-12">
                <icon-trash class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
                <p class="text-gray-500 dark:text-gray-400 font-medium">{{ $t('trash.empty') }}</p>
                <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">
                    {{ $t('trash.empty_hint') }}
                </p>
            </div>

            <!-- Items List -->
            <div v-else class="space-y-2">
                <div
                    v-for="item in trashStore.items"
                    :key="item.id"
                    class="flex items-center justify-between p-3 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                >
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-sm dark:text-white-light truncate">
                            {{ item.display_name }}
                        </p>
                        <p v-if="item.subtitle" class="text-xs text-gray-400 dark:text-gray-500 truncate mt-0.5">
                            {{ item.subtitle }}
                        </p>
                        <div class="flex items-center flex-wrap gap-x-3 mt-0.5">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $t('trash.deleted_on', { date: formatDate(item.deleted_at) }) }}
                            </p>
                            <span
                                class="text-xs"
                                :class="
                                    item.days_until_purge <= 5
                                        ? 'text-danger font-semibold'
                                        : 'text-gray-400 dark:text-gray-500'
                                "
                            >
                                {{ $t('trash.days_until_purge', { days: item.days_until_purge }) }}
                            </span>
                        </div>
                        <div v-if="item.parent_in_trash" class="flex items-center gap-1 mt-1">
                            <icon-info-triangle class="w-3 h-3 text-warning shrink-0" />
                            <span class="text-xs text-warning">
                                {{ $t('trash.parent_in_trash') }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 ml-4 shrink-0">
                        <button
                            v-can="'trash.restore'"
                            type="button"
                            class="btn btn-sm btn-outline-success gap-1"
                            @click="handleRestore(item)"
                        >
                            <icon-restore class="w-3.5 h-3.5" />
                            {{ $t('trash.restore') }}
                        </button>
                        <button
                            v-can="'trash.force-delete'"
                            type="button"
                            class="btn btn-sm btn-outline-danger gap-1"
                            @click="handleForceDelete(item)"
                        >
                            <icon-trash class="w-3.5 h-3.5" />
                            {{ $t('trash.delete_forever') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div
                v-if="trashStore.meta && trashStore.meta.last_page > 1"
                class="flex justify-center mt-5 gap-1"
            >
                <button
                    type="button"
                    class="btn btn-sm btn-outline-primary"
                    :disabled="currentPage === 1"
                    @click="goToPage(currentPage - 1)"
                >
                    {{ $t('common.previous') }}
                </button>
                <span class="flex items-center text-sm text-gray-500 px-3">
                    {{ currentPage }} / {{ trashStore.meta.last_page }}
                </span>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-primary"
                    :disabled="currentPage === trashStore.meta.last_page"
                    @click="goToPage(currentPage + 1)"
                >
                    {{ $t('common.next') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';
import { useMeta } from '@/composables/use-meta';
import { useTrashStore } from '@/stores/trash';
import { useNotification } from '@/composables/useNotification';
import { formatDate } from '@/utils/formatters';
import type { TrashableType, TrashItem } from '@/types/trash';
import IconTrash from '@/components/icon/icon-trash.vue';
import IconRestore from '@/components/icon/icon-restore.vue';
import IconInfoTriangle from '@/components/icon/icon-info-triangle.vue';

useMeta({ title: 'Trash' });

const { t } = useI18n();
const trashStore = useTrashStore();
const { confirm: confirmDialog, success, error: showError } = useNotification();

const activeTab = ref<TrashableType>('clients');
const searchQuery = ref('');
const currentPage = ref(1);
let searchTimeout: ReturnType<typeof setTimeout>;

const tabs = computed(() => [
    { type: 'clients' as TrashableType, label: t('trash.tabs.clients') },
    { type: 'cases' as TrashableType, label: t('trash.tabs.cases') },
    { type: 'companions' as TrashableType, label: t('trash.tabs.companions') },
    { type: 'documents' as TrashableType, label: t('trash.tabs.documents') },
]);

function tabCount(type: TrashableType): number {
    return trashStore.summary?.counts[type] ?? 0;
}

function switchTab(type: TrashableType) {
    activeTab.value = type;
    trashStore.activeTab = type;
    currentPage.value = 1;
    searchQuery.value = '';
    trashStore.fetchItems(type, 1);
}

function goToPage(page: number) {
    currentPage.value = page;
    trashStore.fetchItems(activeTab.value, page, searchQuery.value || undefined);
}

watch(searchQuery, (val) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPage.value = 1;
        trashStore.fetchItems(activeTab.value, 1, val || undefined);
    }, 400);
});

async function handleRestore(item: TrashItem) {
    if (item.parent_in_trash && item.parent) {
        const confirmed = await confirmDialog({
            title: t('trash.confirm_restore_with_parent.title'),
            text: t('trash.confirm_restore_with_parent.text', {
                name: item.display_name,
                parentName: item.parent.name,
            }),
            icon: 'question',
            confirmButtonText: t('trash.confirm_restore_with_parent.confirm'),
            cancelButtonText: t('common.cancel'),
        });
        if (!confirmed) return;

        try {
            await trashStore.restoreWithParents(activeTab.value, item.id);
            success(t('trash.restored_with_parent'));
        } catch (err: any) {
            showError(err.response?.data?.message || t('trash.restore_failed'));
        }
        return;
    }

    const confirmed = await confirmDialog({
        title: t('trash.confirm_restore.title'),
        text: t('trash.confirm_restore.text', { name: item.display_name }),
        icon: 'question',
        confirmButtonText: t('trash.confirm_restore.confirm'),
        cancelButtonText: t('common.cancel'),
    });
    if (!confirmed) return;

    try {
        await trashStore.restoreItem(activeTab.value, item.id);
        success(t('trash.restored_successfully'));
    } catch (err: any) {
        const errData = err.response?.data;
        if (errData?.error === 'parent_in_trash') {
            await trashStore.fetchItems(activeTab.value, currentPage.value);
            showError(t('trash.parent_in_trash_error', { name: errData.parent?.name ?? '' }));
        } else {
            showError(errData?.message || t('trash.restore_failed'));
        }
    }
}

async function handleForceDelete(item: TrashItem) {
    const confirmWord = t('trash.confirm_force_delete.confirm_word');
    const result = await Swal.fire({
        title: t('trash.confirm_force_delete.title'),
        html: `<p class="mb-3">${t('trash.confirm_force_delete.text', { name: item.display_name })}</p>
               <input id="swal-confirm-input" class="swal2-input" placeholder="${confirmWord}" />`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e7515a',
        cancelButtonColor: '#d1d5db',
        confirmButtonText: t('trash.confirm_force_delete.confirm_btn'),
        cancelButtonText: t('common.cancel'),
        reverseButtons: true,
        padding: '2em',
        preConfirm: () => {
            const input = (document.getElementById('swal-confirm-input') as HTMLInputElement)?.value;
            if (input !== confirmWord) {
                Swal.showValidationMessage(t('trash.confirm_force_delete.validation'));
                return false;
            }
            return true;
        },
    });

    if (!result.isConfirmed) return;

    try {
        await trashStore.forceDeleteItem(activeTab.value, item.id);
        success(t('trash.deleted_forever'));
    } catch (err: any) {
        showError(err.response?.data?.message || t('trash.delete_failed'));
    }
}

async function confirmPurge() {
    const days = trashStore.summary?.auto_purge_days ?? 30;
    const confirmed = await confirmDialog({
        title: t('trash.confirm_purge.title'),
        text: t('trash.confirm_purge.text', { days }),
        icon: 'warning',
        confirmButtonText: t('trash.confirm_purge.confirm'),
        cancelButtonText: t('common.cancel'),
    });
    if (!confirmed) return;

    try {
        await trashStore.purgeAll(days);
        await trashStore.fetchItems(activeTab.value, 1);
        success(t('trash.purge_completed'));
    } catch (err: any) {
        showError(err.response?.data?.message || t('trash.purge_failed'));
    }
}

onMounted(async () => {
    await trashStore.fetchSummary();
    await trashStore.fetchItems('clients');
});
</script>
