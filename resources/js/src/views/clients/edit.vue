<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/clients" class="text-primary hover:underline">{{ $t('clients.clients') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <router-link :to="`/clients/${clientId}`" class="text-primary hover:underline">
                    {{ clientType === 'company' ? (form.company_name || $t('clients.type.company')) : formatName(form.first_name, form.last_name) }}
                </router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('clients.edit') }}</span>
            </li>
        </ul>

        <!-- Loading State -->
        <div v-if="isLoadingClient" class="panel">
            <div class="animate-pulse space-y-4">
                <div class="h-6 w-48 bg-gray-200 dark:bg-gray-700 rounded"></div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded"></div>
                </div>
            </div>
        </div>

        <div v-else class="panel">
            <!-- Form -->
            <form @submit.prevent="handleSubmit" class="space-y-6">

                <!-- Header -->
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-3">
                        <h5 class="font-semibold text-lg dark:text-white-light">{{ $t('clients.edit_client') }}</h5>
                        <span :class="clientType === 'company' ? 'badge badge-outline-success' : 'badge badge-outline-info'">
                            {{ clientType === 'company' ? $t('clients.type.company') : $t('clients.type.person') }}
                        </span>
                        <span class="text-xs text-gray-400" :title="$t('clients.validation.cannot_change_type')">
                            <icon-info-circle class="inline w-3.5 h-3.5" />
                        </span>
                    </div>
                    <!-- header/actions -->
                    <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <router-link to="/clients" class="btn btn-outline-primary gap-2">
                            <icon-arrow-left class="w-5 h-5" />
                            {{ $t('clients.back_to_list') }}
                        </router-link>
                        <router-link :to="`/clients/${clientId}`" class="btn btn-outline-secondary gap-2">
                            <icon-arrow-left class="w-4 h-4" />
                            {{ $t('clients.back_to_profile') }}
                        </router-link>
                        <router-link :to="`/clients/${clientId}`" class="btn btn-outline-danger">
                            {{ $t('clients.cancel') }}
                        </router-link>
                        <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                            <template v-if="isSubmitting">
                                <span class="animate-spin border-2 border-white border-l-transparent rounded-full w-5 h-5 inline-block mr-2"></span>
                                {{ $t('clients.saving') }}
                            </template>
                            <template v-else>
                                <icon-save class="w-5 h-5 mr-2" />
                                {{ $t('clients.save_changes') }}
                            </template>
                        </button>
                    </div>
                </div>

                <!-- Error Alert -->
                <div v-if="errorMessage" role="alert" class="flex items-center p-3.5 rounded text-danger bg-danger-light dark:bg-danger-dark-light">
                    <span class="ltr:pr-2 rtl:pl-2">{{ errorMessage }}</span>
                    <button type="button" class="ltr:ml-auto rtl:mr-auto hover:opacity-80" aria-label="Dismiss error" @click="errorMessage = ''">
                        <icon-x class="w-4 h-4" />
                    </button>
                </div>

                <!-- Company Information Section (Spec 64 — company only) -->
                <div v-if="clientType === 'company'" class="border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                    <h6 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <icon-building class="w-5 h-5" />
                        {{ $t('clients.company_information') }}
                    </h6>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <div class="md:col-span-2">
                            <label for="company_name" class="mb-2 block">
                                {{ $t('clients.fields.company_name') }} <span class="text-danger">*</span>
                            </label>
                            <input
                                id="company_name"
                                v-model="form.company_name"
                                type="text"
                                class="form-input"
                                :class="{ 'border-danger': companyErrors.company_name }"
                            />
                            <p v-if="companyErrors.company_name" class="text-danger mt-1 text-sm">{{ companyErrors.company_name }}</p>
                        </div>
                        <div>
                            <label for="trade_name" class="mb-2 block">{{ $t('clients.fields.trade_name') }}</label>
                            <input id="trade_name" v-model="form.trade_name" type="text" class="form-input" />
                        </div>
                        <div>
                            <label for="tax_id" class="mb-2 block">
                                {{ $t('clients.fields.tax_id') }} <span class="text-danger">*</span>
                            </label>
                            <input
                                id="tax_id"
                                v-model="form.tax_id"
                                type="text"
                                class="form-input"
                                :class="{ 'border-danger': companyErrors.tax_id }"
                            />
                            <p v-if="companyErrors.tax_id" class="text-danger mt-1 text-sm">{{ companyErrors.tax_id }}</p>
                        </div>
                        <div>
                            <label for="industry" class="mb-2 block">{{ $t('clients.fields.industry') }}</label>
                            <input id="industry" v-model="form.industry" type="text" class="form-input" />
                        </div>
                        <div>
                            <label for="website" class="mb-2 block">{{ $t('clients.fields.website') }}</label>
                            <input id="website" v-model="form.website" type="url" class="form-input" placeholder="https://" />
                        </div>
                        <div>
                            <label for="legal_rep_name" class="mb-2 block">{{ $t('clients.fields.legal_rep_name') }}</label>
                            <input id="legal_rep_name" v-model="form.legal_rep_name" type="text" class="form-input" />
                        </div>
                        <div>
                            <label for="legal_rep_title" class="mb-2 block">{{ $t('clients.fields.legal_rep_title') }}</label>
                            <input id="legal_rep_title" v-model="form.legal_rep_title" type="text" class="form-input" />
                        </div>
                    </div>
                </div>

                <!-- Personal Information Section (Spec 64 — person only) -->
                <div v-if="clientType === 'person'" class="border border-gray-200 dark:border-gray-700 rounded-lg p-5">
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
                                class="form-input"
                                :class="{ 'border-danger': v$.last_name.$error }"
                            />
                            <p v-if="v$.last_name.$error" class="text-danger mt-1 text-sm">{{ v$.last_name.$errors[0]?.$message }}</p>
                        </div>

                        <!-- Date of Birth -->
                        <div>
                            <label for="date_of_birth" class="mb-2 block">{{ $t('clients.date_of_birth') }}</label>
                            <AppDatePicker
                                v-model="form.date_of_birth"
                                :max-date="maxBirthDate.toISOString().split('T')[0]"
                                :placeholder="$t('clients.select_date')"
                            />
                        </div>

                        <!-- Gender -->
                        <div>
                            <label for="gender" class="mb-2 block">{{ $t('clients.gender') }}</label>
                            <select id="gender" v-model="form.gender" class="form-select">
                                <option value="">{{ $t('clients.select_gender') }}</option>
                                <option v-for="opt in GenderOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>

                        <!-- Nationality -->
                        <div>
                            <label for="nationality" class="mb-2 block">{{ $t('clients.nationality') }}</label>
                            <CountrySelect
                                id="nationality"
                                v-model="form.nationality"
                                :placeholder="$t('clients.select_nationality')"
                            />
                        </div>

                        <!-- Marital Status -->
                        <div>
                            <label for="marital_status" class="mb-2 block">{{ $t('clients.marital_status') }}</label>
                            <select id="marital_status" v-model="form.marital_status" class="form-select">
                                <option value="">{{ $t('clients.select_status') }}</option>
                                <option v-for="opt in MaritalStatusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>

                        <!-- Profession -->
                        <div>
                            <label for="profession" class="mb-2 block">{{ $t('clients.profession') }}</label>
                            <input id="profession" v-model="form.profession" type="text" class="form-input" />
                        </div>

                        <!-- Language -->
                        <div>
                            <label for="language" class="mb-2 block">{{ $t('clients.language') }}</label>
                            <select id="language" v-model="form.language" class="form-select">
                                <option value="">{{ $t('clients.enter_language') }}</option>
                                <option v-for="opt in languageOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select> 
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
                            <label for="email" class="mb-2 block">{{ $t('clients.email') }}
                                <span class="text-danger">*</span>
                            </label>
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
                            <label for="phone" class="mb-2 block">{{ $t('clients.phone') }}
                                <span class="text-danger">*</span>
                            </label>
                            <PhoneInput
                                v-model="form.phone"
                                v-model:country-code="form.phone_country_code"
                                :error="v$.phone.$error"
                                required
                            />
                            <p v-if="v$.phone.$error" class="text-danger mt-1 text-sm">{{ v$.phone.$errors[0]?.$message }}</p>
                        </div>

                        <!-- Secondary Phone -->
                        <div>
                            <label for="secondary_phone" class="mb-2 block">{{ $t('clients.secondary_phone') }}</label>
                            <PhoneInput
                                v-model="form.secondary_phone"
                                v-model:country-code="form.secondary_phone_country_code"
                            />
                        </div>

                        <!-- Residential Address -->
                        <div class="md:col-span-2">
                            <label for="residential_address" class="mb-2 block">{{ $t('clients.residential_address') }}</label>
                            <input id="residential_address" v-model="form.residential_address" type="text" class="form-input" />
                        </div>

                        <!-- City -->
                        <div>
                            <label for="city" class="mb-2 block">{{ $t('clients.city') }}</label>
                            <input id="city" v-model="form.city" type="text" class="form-input" />
                        </div>

                        <!-- Province -->
                        <div>
                            <label for="province" class="mb-2 block">{{ $t('clients.province') }}</label>
                            <input id="province" v-model="form.province" type="text" class="form-input" />
                        </div>

                        <!-- Postal Code -->
                        <div>
                            <label for="postal_code" class="mb-2 block">{{ $t('clients.postal_code') }}</label>
                            <input id="postal_code" v-model="form.postal_code" type="text" class="form-input" />
                        </div>

                        <!-- Country -->
                        <div>
                            <label for="country" class="mb-2 block">{{ $t('clients.country') }}</label>
                            <CountrySelect
                                id="country"
                                v-model="form.country"
                                :placeholder="$t('clients.select_country')"
                            />
                        </div>
                    </div>
                </div>

                <!-- Canada Status Section (Spec 64 — person only) -->
                <div v-if="clientType === 'person'" class="border border-gray-200 dark:border-gray-700 rounded-lg p-5">
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
                                <option v-for="opt in CanadaStatusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>

                        <!-- Canada Status Other -->
                        <div v-if="form.canada_status === 'other'">
                            <label class="mb-2 block">{{ $t('clients.canada_status_other') }}</label>
                            <input
                                v-model="form.canada_status_other"
                                type="text"
                                class="form-input"
                                :placeholder="$t('clients.canada_status_other_placeholder')"
                                required
                            />
                        </div>

                        <!-- Entry Point -->
                        <div>
                            <label for="entry_point" class="mb-2 block">{{ $t('clients.entry_point') }}</label>
                            <select id="entry_point" v-model="form.entry_point" class="form-select">
                                <option value="">{{ $t('clients.select_entry_point') }}</option>
                                <option v-for="opt in EntryPointOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>

                        <!-- Arrival Date -->
                        <div>
                            <label for="arrival_date" class="mb-2 block">{{ $t('clients.arrival_date') }}</label>
                            <AppDatePicker v-model="form.arrival_date" max-date="today" />
                        </div>

                        <!-- IUC -->
                        <div>
                            <label for="iuc" class="mb-2 block">{{ $t('clients.iuc') }}</label>
                            <input id="iuc" v-model="form.iuc" type="text" class="form-input" />
                        </div>
                    </div>
                </div>

                <!-- Legal Documents Section -->
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                    <h6 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <icon-file class="w-5 h-5" />
                        {{ $t('documents.legal_documents') }}
                    </h6>
                    <DocumentRepeater
                        v-model="legalDocs"
                        entity-type="client"
                        :entity-id="clientId"
                    />
                </div>

                <!-- Client Status Section -->
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                    <h6 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <icon-settings class="w-5 h-5" />
                        {{ $t('clients.client_status') }}
                    </h6>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label for="status" class="mb-2 block">{{ $t('clients.status') }}</label>
                            <select id="status" v-model="form.status" class="form-select">
                                <option v-for="opt in ClientStatusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>

                    </div>

                    <div class="mt-5">
                        <label for="description" class="mb-2 block">{{ $t('clients.notes') }}</label>
                        <div class="markdown-editor">
                            <textarea
                                id="description"
                                :placeholder="$t('clients.enter_notes')"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <router-link :to="`/clients/${clientId}`" class="btn btn-outline-danger">
                        {{ $t('clients.cancel') }}
                    </router-link>
                    <button type="submit" class="btn btn-primary" :disabled="isSubmitting">
                        <template v-if="isSubmitting">
                            <span class="animate-spin border-2 border-white border-l-transparent rounded-full w-5 h-5 inline-block mr-2"></span>
                            {{ $t('clients.saving') }}
                        </template>
                        <template v-else>
                            <icon-save class="w-5 h-5 mr-2" />
                            {{ $t('clients.save_changes') }}
                        </template>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useVuelidate } from '@vuelidate/core';
import { required, email, helpers } from '@vuelidate/validators';
import { useMeta } from '@/composables/use-meta';
import { useFormatName } from '@/composables/useFormatName';
import { useClientStore } from '@/stores/client';
import { useNotification } from '@/composables/useNotification';
import { useI18n } from 'vue-i18n';
import AppDatePicker from '@/components/AppDatePicker.vue';
import {
    GENDER_OPTIONS,
    MARITAL_STATUS_OPTIONS,
    CANADA_STATUS_OPTIONS,
    ENTRY_POINT_OPTIONS,
    CLIENT_STATUS_OPTIONS,
} from '@/types/client';
import type { ClientType } from '@/types/client';
import type { LegalDocument } from '@/types/legal-document';
import DocumentRepeater from '@/components/DocumentRepeater.vue';
import { legalDocumentService } from '@/services/legalDocumentService';

// Icons
import IconArrowLeft from '@/components/icon/icon-arrow-left.vue';
import IconUser from '@/components/icon/icon-user.vue';
import IconBuilding from '@/components/icon/icon-building.vue';
import IconInfoCircle from '@/components/icon/icon-info-circle.vue';
import IconMail from '@/components/icon/icon-mail.vue';
import IconHome from '@/components/icon/icon-home.vue';
import IconSettings from '@/components/icon/icon-settings.vue';
import IconFile from '@/components/icon/icon-file.vue';
import IconSave from '@/components/icon/icon-save.vue';
import IconX from '@/components/icon/icon-x.vue';
import CountrySelect from '@/components/CountrySelect.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import EasyMDE from 'easymde';
import 'easymde/dist/easymde.min.css';

useMeta({ title: 'Edit Client' });

const { formatName } = useFormatName();
const route = useRoute();
const router = useRouter();
const clientStore = useClientStore();
const { success, error } = useNotification();
const { t } = useI18n();

const languageOptions = computed(() => [
    { value: 'es', label: t('common.spanish') as string },
    { value: 'en', label: t('common.english') as string },
    { value: 'fr', label: t('common.french') as string },
]);

const ClientStatusOptions = computed(() => [
    { value: 'prospect', label: t('clients.prospect') as string },
    { value: 'active', label: t('clients.active') as string },
    { value: 'inactive', label: t('clients.inactive') as string },
    { value: 'archived', label: t('clients.archived') as string },
]);

const GenderOptions = computed(() => [
    { value: 'male', label: t('clients.male') as string },
    { value: 'female', label: t('clients.female') as string },
    { value: 'other', label: t('clients.other') as string },
]);

const MaritalStatusOptions = computed(() => [
    { value: 'divorced', label: t('clients.divorced') as string },
    { value: 'widowed', label: t('clients.widowed') as string },
    { value: 'annulled_marriage', label: t('clients.annulled_marriage') as string },
    { value: 'common_law', label: t('clients.common_law') as string },
    { value: 'legally_separated', label: t('clients.legally_separated') as string },
    { value: 'married', label: t('clients.married') as string },
    { value: 'single', label: t('clients.single') as string },
    { value: 'unknown', label: t('clients.unknown') as string },
]);

const CanadaStatusOptions = computed(() => [
    { value: 'asylum_seeker', label: t('clients.asylum_seeker') as string },
    { value: 'refugee', label: t('clients.refugee_claimant') as string },
    { value: 'protected_person', label: t('clients.protected_person') as string },
    { value: 'temporary_resident', label: t('clients.temporary_resident') as string },
    { value: 'permanent_resident', label: t('clients.permanent_resident') as string },
    { value: 'citizen', label: t('clients.citizen') as string },
    { value: 'visitor', label: t('clients.visitor') as string },
    { value: 'student', label: t('clients.student') as string },
    { value: 'worker', label: t('clients.worker') as string },
    { value: 'other', label: t('clients.other') as string },
]);

const EntryPointOptions = computed(() => [
    { value: 'airport', label: t('clients.airport') as string },
    { value: 'land_border', label: t('clients.land_border') as string },
    { value: 'green_path', label: t('clients.green_path') as string },
]);

const clientId = computed(() => parseInt(route.params.id as string));

// State
const isLoadingClient = ref(true);
const isSubmitting = ref(false);
const errorMessage = ref('');

// Spec 64 — Detected client type (immutable after creation)
const clientType = ref<ClientType>('person');
const companyErrors = reactive<{ company_name: string; tax_id: string }>({
    company_name: '',
    tax_id: '',
});

// Legal documents state
const legalDocs = ref<LegalDocument[]>([]);
const originalDocIds = ref<number[]>([]);

// Date picker config
const maxBirthDate = new Date();
maxBirthDate.setDate(maxBirthDate.getDate() - 1);


// Form
const form = reactive({
    first_name: '',
    last_name: '',
    nationality: '',
    language: '',
    date_of_birth: '',
    gender: '',
    marital_status: '',
    profession: '',
    email: '',
    phone: '',
    phone_country_code: '+1',
    secondary_phone: '',
    secondary_phone_country_code: '+1',
    residential_address: '',
    city: '',
    province: '',
    postal_code: '',
    country: '',
    canada_status: '',
    canada_status_other: '',
    entry_point: '',
    arrival_date: '',
    iuc: '',
    status: 'prospect',
    description: '',
    // Spec 64 — Company-specific fields
    company_name: '',
    trade_name: '',
    tax_id: '',
    industry: '',
    website: '',
    legal_rep_name: '',
    legal_rep_title: '',
});

// Validation rules — Spec 64: first/last name only required for persons
const rules = computed(() => {
    const isPerson = clientType.value === 'person';
    return {
        first_name: isPerson
            ? { required: helpers.withMessage(() => t('clients.first_name_required'), required) }
            : {},
        last_name: isPerson
            ? { required: helpers.withMessage(() => t('clients.last_name_required'), required) }
            : {},
        email: {
            required: helpers.withMessage(() => t('clients.email_required'), required),
            email: helpers.withMessage(() => t('clients.invalid_email'), email),
        },
        phone: {
            required: helpers.withMessage(() => t('clients.phone_required'), required),
        },
    };
});

const v$ = useVuelidate(rules, form);

const validateCompanyFields = (): boolean => {
    companyErrors.company_name = '';
    companyErrors.tax_id = '';
    let valid = true;
    if (!form.company_name?.trim()) {
        companyErrors.company_name = t('clients.validation.company_name_required');
        valid = false;
    }
    if (!form.tax_id?.trim()) {
        companyErrors.tax_id = t('clients.validation.tax_id_required');
        valid = false;
    }
    return valid;
};

const handleSubmit = async () => {
    const isValid = await v$.value.$validate();
    if (!isValid) return;

    if (clientType.value === 'company' && !validateCompanyFields()) {
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = '';

    try {
        // Only send changed fields and map canada_status_other to other_status_1
        const { canada_status_other, ...rest } = form;
        const data: Record<string, any> = Object.fromEntries(
            Object.entries(rest).filter(([_, v]) => v !== '' && v !== null)
        );
        if (canada_status_other) {
            data.other_status_1 = canada_status_other;
        }

        // Spec 64 — strip fields that don't belong to the client's actual type
        if (clientType.value === 'company') {
            delete data.first_name;
            delete data.last_name;
            delete data.date_of_birth;
            delete data.gender;
            delete data.marital_status;
            delete data.nationality;
            delete data.profession;
            delete data.canada_status;
            delete data.other_status_1;
            delete data.entry_point;
            delete data.arrival_date;
            delete data.iuc;
        } else {
            delete data.company_name;
            delete data.trade_name;
            delete data.tax_id;
            delete data.industry;
            delete data.website;
            delete data.legal_rep_name;
            delete data.legal_rep_title;
        }
        // Never send type — backend rejects changes and infers it from existing record
        delete data.type;

        await clientStore.updateClient(clientId.value, data as any);

        // Sync legal documents
        const currentIds = legalDocs.value.filter(d => d.id).map(d => d.id as number);
        const deletedIds = originalDocIds.value.filter(id => !currentIds.includes(id));
        await Promise.all([
            ...deletedIds.map(id => legalDocumentService.remove(id)),
            ...legalDocs.value.map(doc =>
                doc.id
                    ? legalDocumentService.update(doc.id, doc)
                    : legalDocumentService.createForClient(clientId.value, doc)
            ),
        ]);

        success(t('clients.updated_successfully'));
        router.push(`/clients/${clientId.value}`);
    } catch (err: any) {
        if (err.response?.data?.errors) {
            const errors = err.response.data.errors;
            const firstError = Object.values(errors)[0];
            errorMessage.value = Array.isArray(firstError) ? firstError[0] : String(firstError);
        } else {
            errorMessage.value = err.response?.data?.message || t('clients.update_failed');
        }
    } finally {
        isSubmitting.value = false;
    }
};

const easyMDE = ref<EasyMDE | null>(null);

const initEasyMDE = () => {
    easyMDE.value = new EasyMDE({
        element: document.getElementById('description') as HTMLElement,
        initialValue: form.description,
        spellChecker: false,
        toolbar: ["bold", "italic", "strikethrough", "|", "heading-3", "|", "quote", "|", "unordered-list", "ordered-list", "|", "horizontal-rule"],
    });
    easyMDE.value.codemirror.on('change', () => {
        form.description = easyMDE.value!.value();
    });
};

const loadClient = async () => {
    try {
        const [client, docs] = await Promise.all([
            clientStore.fetchClient(clientId.value),
            legalDocumentService.getForClient(clientId.value),
        ]);
        if (client) {
            // Spec 64 — detect type first so conditional rendering & rules apply
            clientType.value = ((client as any).type as ClientType) || 'person';
            Object.keys(form).forEach((key) => {
                const value = (client as any)[key];
                (form as any)[key] = value ?? '';
            });
            // Map server fields to form fields
            form.phone_country_code = (client as any).phone_country_code || '+1';
            form.secondary_phone_country_code = (client as any).secondary_phone_country_code || '+1';
            form.canada_status_other = (client as any).other_status_1 || '';
        }
        legalDocs.value = docs;
        originalDocIds.value = docs.filter(d => d.id).map(d => d.id as number);
    } catch (err) {
        error(t('clients.failed_to_load'));
        router.push('/clients');
    } finally {
        isLoadingClient.value = false;
    }
};

onMounted(async () => {
    await loadClient();
    initEasyMDE();
});
</script>
