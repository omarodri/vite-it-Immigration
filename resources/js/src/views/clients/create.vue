<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/clients" class="text-primary hover:underline">{{ $t('clients.clients') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('clients.create_client') }}</span>
            </li>
        </ul>

        <div class="panel">
            <!-- Header -->
            <div class="flex items-center justify-between mb-5">
                <h5 class="font-semibold text-lg dark:text-white-light">{{ $t('clients.create_new_client') }}</h5>
                <router-link to="/clients" class="btn btn-outline-secondary gap-2">
                    <icon-arrow-left class="w-4 h-4" />
                    {{ $t('clients.back_to_list') }}
                </router-link>
            </div>

            <!-- Form -->
            <form @submit.prevent="handleSubmit" class="space-y-6">
                <!-- Error Alert -->
                <div v-if="errorMessage" role="alert" class="flex items-center p-3.5 rounded text-danger bg-danger-light dark:bg-danger-dark-light">
                    <span class="ltr:pr-2 rtl:pl-2">{{ errorMessage }}</span>
                    <button type="button" class="ltr:ml-auto rtl:mr-auto hover:opacity-80" aria-label="Dismiss error" @click="errorMessage = ''">
                        <icon-x class="w-4 h-4" />
                    </button>
                </div>

                <!-- Personal Information Section -->
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                    <h6 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <icon-user class="w-5 h-5" />
                        {{ $t('clients.personal_information') }}
                    </h6>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <!-- First Name -->
                        <div>
                            <label for="first_name" class="mb-2 block">
                                {{ $t('clients.first_name') }} <span class="text-danger">*</span>
                            </label>
                            <input
                                id="first_name"
                                v-model="form.first_name"
                                type="text"
                                :placeholder="$t('clients.enter_first_name')"
                                class="form-input"
                                :class="{ 'border-danger': v$.first_name.$error }"
                            />
                            <p v-if="v$.first_name.$error" class="text-danger mt-1 text-sm">{{ v$.first_name.$errors[0]?.$message }}</p>
                        </div>

                        <!-- Last Name -->
                        <div>
                            <label for="last_name" class="mb-2 block">
                                {{ $t('clients.last_name') }} <span class="text-danger">*</span>
                            </label>
                            <input
                                id="last_name"
                                v-model="form.last_name"
                                type="text"
                                :placeholder="$t('clients.enter_last_name')"
                                class="form-input"
                                :class="{ 'border-danger': v$.last_name.$error }"
                            />
                            <p v-if="v$.last_name.$error" class="text-danger mt-1 text-sm">{{ v$.last_name.$errors[0]?.$message }}</p>
                        </div>

                        <!-- Date of Birth -->
                        <div>
                            <label for="date_of_birth" class="mb-2 block">{{ $t('clients.date_of_birth') }}</label>
                            <flat-pickr
                                v-model="form.date_of_birth"
                                :config="dateConfig"
                                class="form-input"
                                :placeholder="$t('clients.select_date')"
                            />
                        </div>

                        <!-- Gender -->
                        <div>
                            <label for="gender" class="mb-2 block">{{ $t('clients.gender') }}</label>
                            <select id="gender" v-model="form.gender" class="form-select">
                                <option value="">{{ $t('clients.select_gender') }}</option>
                                <option v-for="opt in GENDER_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>

                        <!-- Nationality -->
                        <div>
                            <label for="nationality" class="mb-2 block">{{ $t('clients.nationality') }}</label>
                            <input
                                id="nationality"
                                v-model="form.nationality"
                                type="text"
                                :placeholder="$t('clients.enter_nationality')"
                                class="form-input"
                            />
                        </div>

                        <!-- Marital Status -->
                        <div>
                            <label for="marital_status" class="mb-2 block">{{ $t('clients.marital_status') }}</label>
                            <select id="marital_status" v-model="form.marital_status" class="form-select">
                                <option value="">{{ $t('clients.select_status') }}</option>
                                <option v-for="opt in MARITAL_STATUS_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>

                        <!-- Profession -->
                        <div>
                            <label for="profession" class="mb-2 block">{{ $t('clients.profession') }}</label>
                            <input
                                id="profession"
                                v-model="form.profession"
                                type="text"
                                :placeholder="$t('clients.enter_profession')"
                                class="form-input"
                            />
                        </div>

                        <!-- Language -->
                        <div>
                            <label for="language" class="mb-2 block">{{ $t('clients.language') }}</label>
                            <input
                                id="language"
                                v-model="form.language"
                                type="text"
                                :placeholder="$t('clients.enter_language')"
                                class="form-input"
                            />
                        </div>
                    </div>
                </div>

                <!-- Contact Information Section -->
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                    <h6 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <icon-mail class="w-5 h-5" />
                        {{ $t('clients.contact_information') }}
                    </h6>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <!-- Email -->
                        <div>
                            <label for="email" class="mb-2 block">{{ $t('clients.email') }}</label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                :placeholder="$t('clients.enter_email')"
                                class="form-input"
                                :class="{ 'border-danger': v$.email.$error }"
                            />
                            <p v-if="v$.email.$error" class="text-danger mt-1 text-sm">{{ v$.email.$errors[0]?.$message }}</p>
                        </div>

                        <!-- Phone -->
                        <div>
                            <label for="phone" class="mb-2 block">{{ $t('clients.phone') }}</label>
                            <input
                                id="phone"
                                v-model="form.phone"
                                type="text"
                                :placeholder="$t('clients.enter_phone')"
                                class="form-input"
                            />
                        </div>

                        <!-- Secondary Phone -->
                        <div>
                            <label for="secondary_phone" class="mb-2 block">{{ $t('clients.secondary_phone') }}</label>
                            <input
                                id="secondary_phone"
                                v-model="form.secondary_phone"
                                type="text"
                                :placeholder="$t('clients.enter_secondary_phone')"
                                class="form-input"
                            />
                        </div>

                        <!-- Residential Address -->
                        <div class="md:col-span-2">
                            <label for="residential_address" class="mb-2 block">{{ $t('clients.residential_address') }}</label>
                            <input
                                id="residential_address"
                                v-model="form.residential_address"
                                type="text"
                                :placeholder="$t('clients.enter_address')"
                                class="form-input"
                            />
                        </div>

                        <!-- City -->
                        <div>
                            <label for="city" class="mb-2 block">{{ $t('clients.city') }}</label>
                            <input
                                id="city"
                                v-model="form.city"
                                type="text"
                                :placeholder="$t('clients.enter_city')"
                                class="form-input"
                            />
                        </div>

                        <!-- Province -->
                        <div>
                            <label for="province" class="mb-2 block">{{ $t('clients.province') }}</label>
                            <input
                                id="province"
                                v-model="form.province"
                                type="text"
                                :placeholder="$t('clients.enter_province')"
                                class="form-input"
                            />
                        </div>

                        <!-- Postal Code -->
                        <div>
                            <label for="postal_code" class="mb-2 block">{{ $t('clients.postal_code') }}</label>
                            <input
                                id="postal_code"
                                v-model="form.postal_code"
                                type="text"
                                :placeholder="$t('clients.enter_postal_code')"
                                class="form-input"
                            />
                        </div>

                        <!-- Country -->
                        <div>
                            <label for="country" class="mb-2 block">{{ $t('clients.country') }}</label>
                            <input
                                id="country"
                                v-model="form.country"
                                type="text"
                                :placeholder="$t('clients.enter_country')"
                                class="form-input"
                            />
                        </div>
                    </div>
                </div>

                <!-- Canada Status Section -->
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                    <h6 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <icon-home class="w-5 h-5" />
                        {{ $t('clients.canada_legal_status') }}
                    </h6>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <!-- Canada Status -->
                        <div>
                            <label for="canada_status" class="mb-2 block">{{ $t('clients.status_in_canada') }}</label>
                            <select id="canada_status" v-model="form.canada_status" class="form-select">
                                <option value="">{{ $t('clients.select_status') }}</option>
                                <option v-for="opt in CANADA_STATUS_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>

                        <!-- Entry Point -->
                        <div>
                            <label for="entry_point" class="mb-2 block">{{ $t('clients.entry_point') }}</label>
                            <select id="entry_point" v-model="form.entry_point" class="form-select">
                                <option value="">{{ $t('clients.select_entry_point') }}</option>
                                <option v-for="opt in ENTRY_POINT_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>

                        <!-- Arrival Date -->
                        <div>
                            <label for="arrival_date" class="mb-2 block">{{ $t('clients.arrival_date') }}</label>
                            <flat-pickr
                                v-model="form.arrival_date"
                                :config="dateConfig"
                                class="form-input"
                                :placeholder="$t('clients.select_date')"
                            />
                        </div>

                        <!-- Passport Number -->
                        <div>
                            <label for="passport_number" class="mb-2 block">{{ $t('clients.passport_number') }}</label>
                            <input
                                id="passport_number"
                                v-model="form.passport_number"
                                type="text"
                                :placeholder="$t('clients.enter_passport_number')"
                                class="form-input"
                            />
                        </div>

                        <!-- Passport Country -->
                        <div>
                            <label for="passport_country" class="mb-2 block">{{ $t('clients.passport_country') }}</label>
                            <input
                                id="passport_country"
                                v-model="form.passport_country"
                                type="text"
                                :placeholder="$t('clients.enter_country')"
                                class="form-input"
                            />
                        </div>

                        <!-- Passport Expiry -->
                        <div>
                            <label for="passport_expiry_date" class="mb-2 block">{{ $t('clients.passport_expiry') }}</label>
                            <flat-pickr
                                v-model="form.passport_expiry_date"
                                :config="dateConfig"
                                class="form-input"
                                :placeholder="$t('clients.select_date')"
                            />
                        </div>

                        <!-- IUC -->
                        <div>
                            <label for="iuc" class="mb-2 block">{{ $t('clients.iuc') }}</label>
                            <input
                                id="iuc"
                                v-model="form.iuc"
                                type="text"
                                :placeholder="$t('clients.enter_iuc')"
                                class="form-input"
                            />
                        </div>

                        <!-- Work Permit Number -->
                        <div>
                            <label for="work_permit_number" class="mb-2 block">{{ $t('clients.work_permit_number') }}</label>
                            <input
                                id="work_permit_number"
                                v-model="form.work_permit_number"
                                type="text"
                                :placeholder="$t('clients.enter_permit_number')"
                                class="form-input"
                            />
                        </div>

                        <!-- Study Permit Number -->
                        <div>
                            <label for="study_permit_number" class="mb-2 block">{{ $t('clients.study_permit_number') }}</label>
                            <input
                                id="study_permit_number"
                                v-model="form.study_permit_number"
                                type="text"
                                :placeholder="$t('clients.enter_permit_number')"
                                class="form-input"
                            />
                        </div>
                    </div>
                </div>

                <!-- Client Status Section -->
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                    <h6 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <icon-settings class="w-5 h-5" />
                        {{ $t('clients.client_status') }}
                    </h6>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Status -->
                        <div>
                            <label for="status" class="mb-2 block">{{ $t('clients.status') }}</label>
                            <select id="status" v-model="form.status" class="form-select">
                                <option v-for="opt in CLIENT_STATUS_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>

                        <!-- Is Primary Applicant -->
                        <div class="flex items-center pt-7">
                            <label class="flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="form.is_primary_applicant"
                                    class="form-checkbox text-primary"
                                />
                                <span class="ml-2">{{ $t('clients.is_primary_applicant') }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mt-5">
                        <label for="description" class="mb-2 block">{{ $t('clients.notes') }}</label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            :placeholder="$t('clients.enter_notes')"
                            class="form-textarea"
                        ></textarea>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <router-link to="/clients" class="btn btn-outline-danger">
                        {{ $t('clients.cancel') }}
                    </router-link>
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="isSubmitting"
                    >
                        <template v-if="isSubmitting">
                            <span class="animate-spin border-2 border-white border-l-transparent rounded-full w-5 h-5 inline-block mr-2"></span>
                            {{ $t('clients.creating') }}
                        </template>
                        <template v-else>
                            <icon-save class="w-5 h-5 mr-2" />
                            {{ $t('clients.create_client') }}
                        </template>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useVuelidate } from '@vuelidate/core';
import { required, email, helpers } from '@vuelidate/validators';
import { useMeta } from '@/composables/use-meta';
import { useClientStore } from '@/stores/client';
import { useNotification } from '@/composables/useNotification';
import { useI18n } from 'vue-i18n';
import flatPickr from 'vue-flatpickr-component';
import 'flatpickr/dist/flatpickr.css';
import {
    GENDER_OPTIONS,
    MARITAL_STATUS_OPTIONS,
    CANADA_STATUS_OPTIONS,
    ENTRY_POINT_OPTIONS,
    CLIENT_STATUS_OPTIONS,
} from '@/types/client';

// Icons
import IconArrowLeft from '@/components/icon/icon-arrow-left.vue';
import IconUser from '@/components/icon/icon-user.vue';
import IconMail from '@/components/icon/icon-mail.vue';
import IconHome from '@/components/icon/icon-home.vue';
import IconSettings from '@/components/icon/icon-settings.vue';
import IconSave from '@/components/icon/icon-save.vue';
import IconX from '@/components/icon/icon-x.vue';

useMeta({ title: 'Create Client' });

const router = useRouter();
const clientStore = useClientStore();
const { success, error } = useNotification();
const { t } = useI18n();

// State
const isSubmitting = ref(false);
const errorMessage = ref('');

// Date picker config
const dateConfig = {
    dateFormat: 'Y-m-d',
    allowInput: true,
};

// Form
const form = reactive({
    first_name: '',
    last_name: '',
    nationality: '',
    language: 'es',
    date_of_birth: '',
    gender: '',
    marital_status: '',
    profession: '',
    email: '',
    phone: '',
    secondary_phone: '',
    residential_address: '',
    city: '',
    province: '',
    postal_code: '',
    country: '',
    canada_status: '',
    entry_point: '',
    arrival_date: '',
    passport_number: '',
    passport_country: '',
    passport_expiry_date: '',
    iuc: '',
    work_permit_number: '',
    study_permit_number: '',
    status: 'prospect',
    is_primary_applicant: true,
    description: '',
});

// Validation rules
const rules = computed(() => ({
    first_name: {
        required: helpers.withMessage(() => t('clients.first_name_required'), required),
    },
    last_name: {
        required: helpers.withMessage(() => t('clients.last_name_required'), required),
    },
    email: {
        email: helpers.withMessage(() => t('clients.invalid_email'), email),
    },
}));

const v$ = useVuelidate(rules, form);

const handleSubmit = async () => {
    const isValid = await v$.value.$validate();
    if (!isValid) return;

    isSubmitting.value = true;
    errorMessage.value = '';

    try {
        // Filter out empty string values
        const data = Object.fromEntries(
            Object.entries(form).filter(([_, v]) => v !== '' && v !== null)
        );

        await clientStore.createClient(data as any);

        success(t('clients.created_successfully'));
        router.push('/clients');
    } catch (err: any) {
        if (err.response?.data?.errors) {
            const errors = err.response.data.errors;
            const firstError = Object.values(errors)[0];
            errorMessage.value = Array.isArray(firstError) ? firstError[0] : String(firstError);
        } else {
            errorMessage.value = err.response?.data?.message || t('clients.create_failed');
        }
    } finally {
        isSubmitting.value = false;
    }
};
</script>
