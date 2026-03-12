<template>
    <TransitionRoot appear :show="open" as="template">
        <Dialog as="div" class="relative z-50" @close="close">
            <TransitionChild
                as="template"
                enter="duration-300 ease-out"
                enter-from="opacity-0"
                enter-to="opacity-100"
                leave="duration-200 ease-in"
                leave-from="opacity-100"
                leave-to="opacity-0"
            >
                <div class="fixed inset-0 bg-black/50" />
            </TransitionChild>

            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <TransitionChild
                        as="template"
                        enter="duration-300 ease-out"
                        enter-from="opacity-0 scale-95"
                        enter-to="opacity-100 scale-100"
                        leave="duration-200 ease-in"
                        leave-from="opacity-100 scale-100"
                        leave-to="opacity-0 scale-95"
                    >
                        <DialogPanel
                            class="w-full max-w-xl transform overflow-hidden rounded-2xl bg-white dark:bg-gray-900 p-6 text-left align-middle shadow-xl transition-all"
                        >
                            <DialogTitle
                                as="h3"
                                class="text-lg font-semibold leading-6 text-gray-900 dark:text-white mb-4"
                            >
                                {{ $t('wizard.step2.create_new') }}
                            </DialogTitle>

                            <form @submit.prevent="submit" class="space-y-4">
                                <!-- Name Row -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium mb-1 dark:text-white">
                                            {{ $t('clients.first_name') }} *
                                        </label>
                                        <input
                                            v-model="form.first_name"
                                            type="text"
                                            class="form-input"
                                            :class="{ 'border-danger': errors.first_name }"
                                            required
                                        />
                                        <p v-if="errors.first_name" class="text-danger text-xs mt-1">
                                            {{ errors.first_name[0] }}
                                        </p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1 dark:text-white">
                                            {{ $t('clients.last_name') }} *
                                        </label>
                                        <input
                                            v-model="form.last_name"
                                            type="text"
                                            class="form-input"
                                            :class="{ 'border-danger': errors.last_name }"
                                            required
                                        />
                                        <p v-if="errors.last_name" class="text-danger text-xs mt-1">
                                            {{ errors.last_name[0] }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Contact Row -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium mb-1 dark:text-white">
                                            {{ $t('clients.email') }} *
                                        </label>
                                        <input
                                            v-model="form.email"
                                            type="email"
                                            class="form-input"
                                            :class="{ 'border-danger': errors.email }"
                                            required
                                        />
                                        <p v-if="errors.email" class="text-danger text-xs mt-1">
                                            {{ errors.email[0] }}
                                        </p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1 dark:text-white">
                                            {{ $t('clients.phone') }} *
                                        </label>
                                        <input
                                            v-model="form.phone"
                                            type="tel"
                                            class="form-input"
                                            :placeholder="'(___) ___-____'"
                                            :class="{ 'border-danger': errors.phone }"
                                            v-maska="'(###) ###-####'"
                                            required
                                        />
                                        <p v-if="errors.phone" class="text-danger text-xs mt-1">
                                            {{ errors.phone[0] }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Extra Info Row -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium mb-1 dark:text-white">
                                            {{ $t('clients.nationality') }}
                                        </label>
                                        <CountrySelect
                                            v-model="form.nationality as string | null" 
                                            :placeholder="$t('clients.select_nationality')"
                                            class="dark:bg-gray-900 dark:text-white"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium mb-1 dark:text-white">
                                            {{ $t('clients.language') }}
                                        </label>
                                        <select v-model="form.language" class="form-select">
                                            <option value="es">{{ $t('common.spanish') }}</option>
                                            <option value="en">{{ $t('common.english') }}</option>
                                            <option value="fr">{{ $t('common.french') }}</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Buttons -->
                                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <button type="button" class="btn btn-outline-secondary" @click="close">
                                        {{ $t('common.cancel') }}
                                    </button>
                                    <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                                        <icon-loader v-if="isSubmitting" class="w-4 h-4 animate-spin ltr:mr-2 rtl:ml-2" />
                                        {{ isSubmitting ? $t('common.saving') : $t('common.create') }}
                                    </button>
                                </div>
                            </form>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script lang="ts" setup>
import { ref, reactive, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { vMaska } from 'maska/vue';
import { Dialog, DialogPanel, DialogTitle, TransitionRoot, TransitionChild } from '@headlessui/vue';
import clientService from '@/services/clientService';
import { useNotification } from '@/composables/useNotification';
import type { Client, CreateClientData } from '@/types/client';
import IconLoader from '@/components/icon/icon-loader.vue';
import CountrySelect from '@/components/CountrySelect.vue';

interface Props {
    open: boolean;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'created', client: Client): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const { t } = useI18n();
const notification = useNotification();

const isSubmitting = ref(false);
const errors = ref<Record<string, string[]>>({});

const form = reactive<CreateClientData>({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    nationality: '',
    language: 'es',
});

// Reset form when modal opens
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            resetForm();
        }
    }
);

function resetForm() {
    form.first_name = '';
    form.last_name = '';
    form.email = '';
    form.phone = '';
    form.nationality = '';
    form.language = 'es';
    errors.value = {};
}

function close() {
    emit('update:open', false);
}

async function submit() {
    isSubmitting.value = true;
    errors.value = {};

    try {
        const response = await clientService.createClient(form);
        notification.success(t('wizard.success.client_created'));
        emit('created', response.client);
        close();
    } catch (error: any) {
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        } else {
            notification.showApiError(error, t('common.error_creating'));
        }
    } finally {
        isSubmitting.value = false;
    }
}
</script>
