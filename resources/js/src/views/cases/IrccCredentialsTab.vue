<template>
    <div class="space-y-6">
        <!-- Loading state -->
        <div v-if="store.isLoading" class="animate-pulse space-y-4">
            <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/3"></div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded"></div>
                <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded"></div>
            </div>
            <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded"></div>
            <div class="h-32 bg-gray-200 dark:bg-gray-700 rounded"></div>
        </div>

        <template v-else>
            <!-- Section: Credenciales IRCC -->
            <section>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white-light mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                    {{ $t('ircc.section_credentials') }}
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-white-light mb-1">
                            {{ $t('ircc.field_username') }}
                        </label>
                        <input
                            v-model="form.ircc_username"
                            type="text"
                            autocomplete="off"
                            data-lpignore="true"
                            data-form-type="other"
                            class="form-input"
                            :placeholder="$t('ircc.field_username')"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-white-light mb-1">
                            {{ $t('ircc.field_password') }}
                        </label>
                        <div class="relative">
                            <input
                                v-model="form.ircc_password"
                                type="text"
                                autocomplete="new-password"
                                data-lpignore="true"
                                data-form-type="other"
                                class="form-input pr-10"
                                :placeholder="$t('ircc.field_password')"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section: Correo Electrónico -->
            <section>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white-light mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                    {{ $t('ircc.section_email') }}
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-white-light mb-1">
                            {{ $t('ircc.field_email') }}
                        </label>
                        <input
                            v-model="form.email_address"
                            type="text"
                            autocomplete="off"
                            data-lpignore="true"
                            data-form-type="other"
                            class="form-input"
                            :placeholder="$t('ircc.field_email')"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-white-light mb-1">
                            {{ $t('ircc.field_email_password') }}
                        </label>
                        <div class="relative">
                            <input
                                v-model="form.email_password"
                                type="text"
                                autocomplete="new-password"
                                data-lpignore="true"
                                data-form-type="other"
                                class="form-input pr-10"
                                :placeholder="$t('ircc.field_email_password')"
                            />
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-white-light mb-1">
                            {{ $t('ircc.field_application_number') }}
                        </label>
                        <input
                            v-model="form.application_number"
                            type="text"
                            autocomplete="off"
                            data-lpignore="true"
                            data-form-type="other"
                            class="form-input"
                            :placeholder="$t('ircc.field_application_number')"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-white-light mb-1">
                            {{ $t('ircc.field_notes') }}
                        </label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            autocomplete="off"
                            data-lpignore="true"
                            class="form-textarea"
                            :placeholder="$t('ircc.field_notes')"
                        ></textarea>
                    </div>
                </div>
            </section>

            <!-- Section: Preguntas de Seguridad -->
            <section>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white-light mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                    {{ $t('ircc.section_security_questions') }}
                </h3>
                <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-[#1b2e4b]">
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-white-light uppercase w-12">#</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-white-light uppercase">
                                    {{ $t('ircc.field_question') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-white-light uppercase">
                                    {{ $t('ircc.field_answer') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(q, i) in form.security_questions"
                                :key="i"
                                class="border-t border-gray-100 dark:border-gray-700"
                            >
                                <td class="px-4 py-2 text-gray-400 align-middle">{{ i + 1 }}</td>
                                <td class="px-4 py-2">
                                    <input
                                        v-model="q.pregunta"
                                        type="text"
                                        autocomplete="off"
                                        data-lpignore="true"
                                        data-form-type="other"
                                        class="form-input w-full"
                                        :placeholder="$t('ircc.field_question')"
                                    />
                                </td>
                                <td class="px-4 py-2">
                                    <div class="relative">
                                        <input
                                            v-model="q.respuesta"
                                            type="text"
                                            autocomplete="off"
                                            data-lpignore="true"
                                            data-form-type="other"
                                            class="form-input w-full pr-10"
                                            :placeholder="$t('ircc.field_answer')"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Footer -->
            <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="text-sm text-gray-500 dark:text-gray-400 space-x-4">
                    <span v-if="store.credentials?.created_by_name">
                        {{ $t('ircc.field_created_by') }}: <span class="font-medium">{{ store.credentials.created_by_name }}</span>
                    </span>
                    <span v-if="store.credentials?.updated_at">
                        {{ formatDateTime(store.credentials.updated_at) }}
                    </span>
                </div>
                <button
                    type="button"
                    class="btn btn-primary gap-2"
                    :disabled="store.isSaving"
                    @click="saveCredentials"
                >
                    <span
                        v-if="store.isSaving"
                        class="inline-block animate-spin border-2 border-white border-l-transparent rounded-full w-4 h-4"
                    ></span>
                    <span>{{ store.isSaving ? '...' : $t('ircc.save') }}</span>
                </button>
            </div>
        </template>
    </div>
</template>

<script lang="ts" setup>
import { onMounted, onUnmounted, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { useIrccStore } from '@/stores/useIrccStore';
import { useNotification } from '@/composables/useNotification';
import irccCredentialsService, {
    type IrccCredentialsPayload,
    type IrccSecurityQuestion,
} from '@/services/irccCredentialsService';
import IconEye from '@/components/icon/icon-eye.vue';

const props = defineProps<{ caseId: number }>();

const { t } = useI18n();
const store = useIrccStore();
const { success, error } = useNotification();

interface FormState {
    ircc_username: string;
    ircc_password: string;
    email_address: string;
    email_password: string;
    application_number: string;
    notes: string;
    security_questions: IrccSecurityQuestion[];
}

function emptyQuestions(): IrccSecurityQuestion[] {
    return Array.from({ length: 5 }, () => ({ pregunta: '', respuesta: '' }));
}

const form = reactive<FormState>({
    ircc_username: '',
    ircc_password: '',
    email_address: '',
    email_password: '',
    application_number: '',
    notes: '',
    security_questions: emptyQuestions(),
});

function formatDateTime(iso: string): string {
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return iso;
    }
}

function syncFormFromStore(): void {
    const c = store.credentials;
    if (!c) return;
    form.ircc_username = c.ircc_username ?? '';
    form.ircc_password = c.ircc_password ?? '';
    form.email_address = c.email_address ?? '';
    form.email_password = c.email_password ?? '';
    form.application_number = c.application_number ?? '';
    form.notes = c.notes ?? '';

    const incoming = Array.isArray(c.security_questions) ? c.security_questions.slice(0, 5) : [];
    const next = emptyQuestions();
    incoming.forEach((q, i) => {
        next[i] = { pregunta: q.pregunta ?? '', respuesta: q.respuesta ?? '' };
    });
    form.security_questions = next;
}

async function loadData(): Promise<void> {
    if (store.isLoading) return;
    store.isLoading = true;
    try {
        const { data } = await irccCredentialsService.get(props.caseId);
        store.setCredentials(props.caseId, data.data);
        syncFormFromStore();
    } catch {
        error(t('ircc.no_access'));
    } finally {
        store.isLoading = false;
    }
}

async function saveCredentials(): Promise<void> {
    if (store.isSaving) return;
    store.isSaving = true;
    try {
        const payload: IrccCredentialsPayload = {
            ircc_username: form.ircc_username || null,
            ircc_password: form.ircc_password || null,
            email_address: form.email_address || null,
            email_password: form.email_password || null,
            application_number: form.application_number || null,
            notes: form.notes || null,
            security_questions: form.security_questions.map((q) => ({
                pregunta: q.pregunta ?? '',
                respuesta: q.respuesta ?? '',
            })),
        };

        const { data } = await irccCredentialsService.save(props.caseId, payload);

        // Refresh store with the new timestamp and current values
        if (store.credentials) {
            store.credentials.ircc_username = form.ircc_username || null;
            store.credentials.ircc_password = form.ircc_password || null;
            store.credentials.email_address = form.email_address || null;
            store.credentials.email_password = form.email_password || null;
            store.credentials.application_number = form.application_number || null;
            store.credentials.notes = form.notes || null;
            store.credentials.security_questions = form.security_questions.map((q) => ({ ...q }));
            store.credentials.updated_at = data.updated_at;
            store.credentials.exists = true;
        }

        success(t('ircc.saved_successfully'));
    } catch (e: any) {
        const msg = e?.response?.data?.message || t('ircc.save_failed');
        error(msg);
    } finally {
        store.isSaving = false;
    }
}

onMounted(() => {
    if (store.isLoaded && store.loadedForCaseId === props.caseId) {
        syncFormFromStore();
    } else {
        void loadData();
    }
});

onUnmounted(() => {
    // Wipe plaintext from memory when the tab is unmounted
    form.ircc_username = '';
    form.ircc_password = '';
    form.email_address = '';
    form.email_password = '';
    form.application_number = '';
    form.notes = '';
    form.security_questions = emptyQuestions();
    store.clear();
});
</script>
