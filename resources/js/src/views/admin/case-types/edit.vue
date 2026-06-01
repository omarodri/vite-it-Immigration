<template>
    <div class="max-w-2xl mx-auto">
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <a href="javascript:;" class="text-primary hover:underline">{{ $t('sidebar.admin') }}</a>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <router-link :to="{ name: 'admin-case-types' }" class="text-primary hover:underline">
                    {{ $t('case_types.admin_title') }}
                </router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ isEdit ? $t('case_types.edit_title') : $t('case_types.create_title') }}</span>
            </li>
        </ul>

        <!-- Clone review banner: inactive type that hasn't had its code edited yet -->
        <div
            v-if="isEdit && !caseType?.is_active && !codeEdited"
            class="flex items-center gap-2 p-3.5 rounded mb-4 text-warning bg-warning-light dark:bg-warning-dark-light"
        >
            <icon-info-triangle class="w-5 h-5 shrink-0" />
            <span>{{ $t('case_types.clone_review_hint') }}</span>
        </div>

        <!-- Loading skeleton -->
        <div v-if="loading" class="panel">
            <div class="animate-pulse space-y-4">
                <div v-for="n in 5" :key="n" class="h-10 bg-gray-200 dark:bg-gray-700 rounded"></div>
            </div>
        </div>

        <form v-else class="panel space-y-5" @submit.prevent="save">
            <h2 class="text-lg font-semibold dark:text-white-light">
                {{ isEdit ? $t('case_types.edit_title') : $t('case_types.create_title') }}
            </h2>

            <!-- Name -->
            <div>
                <label class="form-label" for="ct-name">{{ $t('case_types.field_name') }} *</label>
                <input id="ct-name" v-model="form.name" type="text" class="form-input" required maxlength="120" />
            </div>

            <!-- Code -->
            <div>
                <label class="form-label" for="ct-code">{{ $t('case_types.field_code') }} *</label>
                <input
                    id="ct-code"
                    v-model="form.code"
                    type="text"
                    class="form-input font-mono uppercase"
                    required
                    maxlength="10"
                    pattern="[A-Za-z0-9_]+"
                    @input="onCodeInput"
                />
                <p class="text-xs text-gray-500 mt-1">{{ $t('case_types.code_hint') }}</p>
            </div>

            <!-- Category -->
            <div>
                <label class="form-label" for="ct-category">{{ $t('case_types.field_category') }} *</label>
                <select id="ct-category" v-model="form.category" class="form-select" required>
                    <option value="" disabled>{{ $t('case_types.select_category') }}</option>
                    <option v-for="opt in categoryOptions" :key="opt.value" :value="opt.value">
                        {{ $t(opt.label) }}
                    </option>
                </select>
            </div>

            <!-- Description -->
            <div>
                <label class="form-label" for="ct-description">{{ $t('case_types.field_description') }}</label>
                <textarea id="ct-description" v-model="form.description" class="form-textarea" rows="3" maxlength="1000" />
            </div>

            <!-- Active -->
            <div class="flex items-center gap-3 flex-wrap">
                <label class="inline-flex items-center cursor-pointer mb-0">
                    <input v-model="form.is_active" type="checkbox" class="form-checkbox" :disabled="!canActivate" />
                    <span class="ltr:ml-2 rtl:mr-2">{{ $t('case_types.field_is_active') }}</span>
                </label>
                <span v-if="!canActivate" class="text-xs text-warning">
                    {{ $t('case_types.activate_after_code_review') }}
                </span>
            </div>

            <!-- Actions -->
            <div class="flex gap-3 justify-end pt-2">
                <router-link :to="{ name: 'admin-case-types' }" class="btn btn-outline-secondary">
                    {{ $t('common.cancel') }}
                </router-link>
                <button type="submit" class="btn btn-primary gap-2" :disabled="saving">
                    <span v-if="saving" class="animate-spin w-4 h-4 border-2 border-white rounded-full border-t-transparent inline-block" />
                    {{ $t('common.save') }}
                </button>
            </div>
        </form>
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import Swal from 'sweetalert2';
import { useMeta } from '@/composables/use-meta';
import caseTypeService from '@/services/caseTypeService';
import { CASE_TYPE_CATEGORY_OPTIONS } from '@/types/case';
import type { CaseType, CaseTypeForm } from '@/types';

import IconInfoTriangle from '@/components/icon/icon-info-triangle.vue';

const route = useRoute();
const router = useRouter();
const { t } = useI18n();

const isEdit = computed(() => !!route.params.id);
const caseType = ref<CaseType | null>(null);
const loading = ref(false);
const saving = ref(false);
const codeEdited = ref(false); // D5: guardrail for the is_active toggle

const categoryOptions = CASE_TYPE_CATEGORY_OPTIONS;

const form = ref<CaseTypeForm>({
    name: '',
    code: '',
    category: 'temporary_residence',
    description: '',
    is_active: false,
});

// D5: keep is_active disabled for freshly-cloned (inactive) types until the code is edited.
const canActivate = computed(
    () => !isEdit.value || codeEdited.value || (caseType.value?.is_active ?? false),
);

useMeta({ title: computed(() => (isEdit.value ? t('case_types.edit_title') : t('case_types.create_title'))) });

function onCodeInput() {
    codeEdited.value = true;
    form.value.code = form.value.code.toUpperCase();
}

onMounted(async () => {
    if (!isEdit.value) return;
    loading.value = true;
    try {
        const data = await caseTypeService.get(Number(route.params.id));
        caseType.value = data;
        form.value = {
            name: data.name,
            code: data.code,
            category: data.category,
            description: data.description ?? '',
            is_active: data.is_active,
        };
    } finally {
        loading.value = false;
    }
});

async function save() {
    saving.value = true;
    try {
        if (isEdit.value) {
            await caseTypeService.update(Number(route.params.id), form.value);
        } else {
            await caseTypeService.create(form.value);
        }
        router.push({ name: 'admin-case-types' });
    } catch (e: any) {
        Swal.fire({
            icon: 'error',
            title: t('case_types.error'),
            text: e?.response?.data?.message,
        });
    } finally {
        saving.value = false;
    }
}
</script>
