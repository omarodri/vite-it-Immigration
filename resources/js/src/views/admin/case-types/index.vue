<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <a href="javascript:;" class="text-primary hover:underline">{{ $t('sidebar.admin') }}</a>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('case_types.admin_title') }}</span>
            </li>
        </ul>

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold dark:text-white-light">{{ $t('case_types.admin_title') }}</h1>
            <router-link v-can="'case_types.create'" :to="{ name: 'admin-case-types-create' }" class="btn btn-primary gap-2">
                <icon-plus class="w-5 h-5" />
                {{ $t('case_types.create_btn') }}
            </router-link>
        </div>

        <!-- Table -->
        <div class="panel">
            <div class="datatable">
                <vue3-datatable
                    :rows="items"
                    :loading="loading"
                    :columns="columns"
                    :sortable="true"
                    skin="whitespace-nowrap bh-table-hover"
                    :noDataContent="$t('case_types.admin_title')"
                >
                    <!-- Name -->
                    <template #name="{ value }">
                        <span class="font-semibold dark:text-white-light">{{ value.name }}</span>
                    </template>

                    <!-- Code -->
                    <template #code="{ value }">
                        <span class="badge badge-outline-primary font-mono text-xs">{{ value.code }}</span>
                        <span v-if="value.is_global" class="badge badge-outline-secondary text-xs ltr:ml-1 rtl:mr-1">
                            {{ $t('case_types.global_badge') }}
                        </span>
                    </template>

                    <!-- Category -->
                    <template #category="{ value }">
                        <span>{{ $t(`case_types.cat_${value.category}`) }}</span>
                    </template>

                    <!-- Stages count -->
                    <template #workflow_stages_count="{ value }">
                        <span class="text-sm">{{ value.workflow_stages_count }}</span>
                    </template>

                    <!-- Tasks count -->
                    <template #tasks_count="{ value }">
                        <span class="text-sm">{{ value.tasks_count }}</span>
                    </template>

                    <!-- Status -->
                    <template #is_active="{ value }">
                        <span :class="value.is_active ? 'badge badge-outline-success' : 'badge badge-outline-danger'">
                            {{ value.is_active ? $t('case_types.status_active') : $t('case_types.status_inactive') }}
                        </span>
                    </template>

                    <!-- Actions -->
                    <template #actions="{ value }">
                        <div class="flex items-center gap-2">
                            <tippy v-can="'case_types.update'" :content="$t('case_types.edit_title')">
                                <router-link
                                    :to="{ name: 'admin-case-types-edit', params: { id: value.id } }"
                                    class="btn btn-sm btn-outline-primary p-1.5"
                                >
                                    <icon-pencil class="w-4 h-4" />
                                </router-link>
                            </tippy>

                            <tippy v-can="'case_types.clone'" :content="$t('case_types.clone_title')">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-info p-1.5"
                                    :disabled="cloning === value.id"
                                    @click="confirmClone(value)"
                                >
                                    <icon-copy v-if="cloning !== value.id" class="w-4 h-4" />
                                    <span v-else class="animate-spin w-4 h-4 border-2 border-info rounded-full border-t-transparent inline-block" />
                                </button>
                            </tippy>

                            <tippy v-can="'case_types.delete'" :content="$t('case_types.delete_title')">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger p-1.5"
                                    @click="confirmDelete(value)"
                                >
                                    <icon-trash class="w-4 h-4" />
                                </button>
                            </tippy>
                        </div>
                    </template>
                </vue3-datatable>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';
import Vue3Datatable from '@bhplugin/vue3-datatable';
import { useMeta } from '@/composables/use-meta';
import { sanitizeHtml } from '@/utils/sanitize';
import caseTypeService from '@/services/caseTypeService';
import type { CaseType } from '@/types';

import IconPlus from '@/components/icon/icon-plus.vue';
import IconPencil from '@/components/icon/icon-pencil.vue';
import IconCopy from '@/components/icon/icon-copy.vue';
import IconTrash from '@/components/icon/icon-trash.vue';

const { t } = useI18n();
const router = useRouter();

useMeta({ title: t('case_types.admin_title') });

const items = ref<CaseType[]>([]);
const loading = ref(false);
const cloning = ref<number | null>(null);

const columns = computed(() => [
    { field: 'name', title: t('case_types.col_name') },
    { field: 'code', title: t('case_types.col_code') },
    { field: 'category', title: t('case_types.col_category') },
    { field: 'workflow_stages_count', title: t('case_types.col_stages') },
    { field: 'tasks_count', title: t('case_types.col_tasks') },
    { field: 'is_active', title: t('case_types.col_status') },
    { field: 'actions', title: t('case_types.actions'), sort: false },
]);

onMounted(load);

async function load() {
    loading.value = true;
    try {
        items.value = await caseTypeService.list();
    } finally {
        loading.value = false;
    }
}

async function confirmClone(ct: CaseType) {
    const result = await Swal.fire({
        title: t('case_types.clone_title'),
        // clone_confirm intentionally contains HTML (<strong>) — rendered by SweetAlert2's html option.
        // ct.name comes from the DB but is sanitized to neutralize any XSS (R8).
        html: t('case_types.clone_confirm', {
            name: sanitizeHtml(ct.name),
            stages: ct.workflow_stages_count,
            tasks: ct.tasks_count,
        }),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: t('case_types.clone_confirm_btn'),
        cancelButtonText: t('common.cancel'),
        confirmButtonColor: '#4361ee',
    });

    if (!result.isConfirmed) return;

    cloning.value = ct.id;
    try {
        const clone = await caseTypeService.clone(ct.id);
        await Swal.fire({
            icon: 'success',
            title: t('case_types.clone_success_title'),
            text: t('case_types.clone_success_desc'),
            timer: 1500,
            showConfirmButton: false,
        });
        // D5: redirect to the clone's edit form to force review of name and code.
        router.push({ name: 'admin-case-types-edit', params: { id: clone.id } });
    } catch {
        Swal.fire({ icon: 'error', title: t('case_types.error'), text: t('case_types.clone_error') });
    } finally {
        cloning.value = null;
    }
}

async function confirmDelete(ct: CaseType) {
    // Query the active-cases count for the informative warning (never blocks — D7).
    let activeCasesCount = 0;
    try {
        activeCasesCount = await caseTypeService.getActiveCasesCount(ct.id);
    } catch {
        // Graceful degradation: if the query fails, show the modal without the warning.
    }

    const warningHtml = activeCasesCount > 0
        ? `<p class="text-warning mt-2 text-sm">${sanitizeHtml(t('case_types.delete_warning_active_cases', { count: activeCasesCount }))}</p>`
        : '';

    const result = await Swal.fire({
        title: t('case_types.delete_title'),
        html: `<p>${sanitizeHtml(t('case_types.delete_confirm', { name: ct.name }))}</p>${warningHtml}`,
        icon: activeCasesCount > 0 ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonText: t('case_types.delete_confirm_btn'),
        cancelButtonText: t('common.cancel'),
        confirmButtonColor: '#e7515a',
    });

    if (!result.isConfirmed) return;

    try {
        await caseTypeService.remove(ct.id);
        items.value = items.value.filter((i) => i.id !== ct.id);
        Swal.fire({ icon: 'success', title: t('case_types.deleted'), timer: 1500, showConfirmButton: false });
    } catch {
        Swal.fire({ icon: 'error', title: t('case_types.error') });
    }
}
</script>
