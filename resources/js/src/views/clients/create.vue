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
            <!-- Step 1: Type Selector (Spec 64) -->
            <div v-if="selectedType === null" class="flex flex-col items-center justify-center min-h-[400px] py-10">
                <h5 class="font-semibold text-xl dark:text-white-light mb-2 text-center">
                    {{ $t('clients.type_selector.title') }}
                </h5>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-8 text-center max-w-md">
                    {{ $t('clients.type_selector.subtitle') }}
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 w-full max-w-2xl px-4">
                    <button
                        type="button"
                        class="panel flex flex-col items-center gap-3 p-8 cursor-pointer hover:border-primary hover:shadow-md transition-all border-2 border-transparent"
                        @click="selectType('person')"
                    >
                        <div class="w-16 h-16 rounded-full bg-info/10 text-info flex items-center justify-center">
                            <icon-user class="w-9 h-9" />
                        </div>
                        <span class="font-semibold text-base dark:text-white-light">{{ $t('clients.type.person') }}</span>
                        <span class="text-xs text-gray-500 text-center">{{ $t('clients.type_selector.person_description') }}</span>
                    </button>
                    <button
                        type="button"
                        class="panel flex flex-col items-center gap-3 p-8 cursor-pointer hover:border-success hover:shadow-md transition-all border-2 border-transparent"
                        @click="selectType('company')"
                    >
                        <div class="w-16 h-16 rounded-full bg-success/10 text-success flex items-center justify-center">
                            <icon-building class="w-9 h-9" />
                        </div>
                        <span class="font-semibold text-base dark:text-white-light">{{ $t('clients.type.company') }}</span>
                        <span class="text-xs text-gray-500 text-center">{{ $t('clients.type_selector.company_description') }}</span>
                    </button>
                </div>
                <router-link to="/clients" class="btn btn-outline-secondary mt-8 gap-2">
                    <icon-arrow-left class="w-4 h-4" />
                    {{ $t('clients.cancel') }}
                </router-link>
            </div>

            <!-- Step 2: Form (after type selected) -->
            <form v-else @submit.prevent="handleSubmit" class="space-y-6">

                <!-- Header -->
                <div class="flex items-center justify-between mb-5">
                    <div class="flex items-center gap-3">
                        <h5 class="font-semibold text-lg dark:text-white-light">{{ $t('clients.create_new_client') }}</h5>
                        <span :class="selectedType === 'company' ? 'badge badge-outline-success' : 'badge badge-outline-info'">
                            {{ selectedType === 'company' ? $t('clients.type.company') : $t('clients.type.person') }}
                        </span>
                        <button
                            type="button"
                            class="text-xs text-gray-500 hover:text-primary underline"
                            @click="changeType"
                        >
                            {{ $t('common.change') }}
                        </button>
                    </div>
                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-4">
                        <!-- Back to List Button -->
                        <router-link to="/clients" class="btn btn-outline-secondary gap-2">
                            <icon-arrow-left class="w-4 h-4" />
                            {{ $t('clients.back_to_list') }}
                        </router-link>
                        <!-- Cancel Button -->
                        <router-link to="/clients" class="btn btn-outline-danger">
                            {{ $t('clients.cancel') }}
                        </router-link>
                        <!-- Create Client Button -->
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
                </div>

                <!-- Error Alert -->
                <div v-if="errorMessage" role="alert" class="flex items-center p-3.5 rounded text-danger bg-danger-light dark:bg-danger-dark-light">
                    <span class="ltr:pr-2 rtl:pl-2">{{ errorMessage }}</span>
                    <button type="button" class="ltr:ml-auto rtl:mr-auto hover:opacity-80" aria-label="Dismiss error" @click="errorMessage = ''">
                        <icon-x class="w-4 h-4" />
                    </button>
                </div>

                <!-- Company Information Section (Spec 64) -->
                <div v-if="selectedType === 'company'" class="border border-gray-200 dark:border-gray-700 rounded-lg p-5">
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

                <!-- Personal Information Section (Spec 64 — only for person) -->
                <template v-if="selectedType === 'person'">
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
                            <select id="language" v-model="form.language" class="form-select">
                                <option value="">{{ $t('clients.enter_language') }}</option>
                                <option v-for="opt in languageOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>
                    </div>
                </div>
                </template>

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
                            <CountrySelect
                                id="country"
                                v-model="form.country"
                                :placeholder="$t('clients.select_country')"
                            />
                        </div>
                    </div>
                </div>

                <!-- Canada Status Section (Person only — Spec 64) -->
                <div v-if="selectedType === 'person'" class="border border-gray-200 dark:border-gray-700 rounded-lg p-5">
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
                            <AppDatePicker
                                v-model="form.arrival_date"
                                max-date="today"
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
                        :entity-id="0"
                    />
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
                                <option v-for="opt in ClientStatusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                            </select>
                        </div>

                    </div>

                    <!-- Description -->
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
import { ref, reactive, computed, onMounted, nextTick, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useVuelidate } from '@vuelidate/core';
import { required, email, helpers } from '@vuelidate/validators';
import type { ClientType } from '@/types/client';
import { useMeta } from '@/composables/use-meta';
import { useClientStore } from '@/stores/client';
import { useNotification } from '@/composables/useNotification';
import { useI18n } from 'vue-i18n';
import AppDatePicker from '@/components/AppDatePicker.vue';
import DocumentRepeater from '@/components/DocumentRepeater.vue';
import { legalDocumentService } from '@/services/legalDocumentService';
import type { LegalDocument } from '@/types/legal-document';

// Icons
import IconArrowLeft from '@/components/icon/icon-arrow-left.vue';
import IconFile from '@/components/icon/icon-file.vue';
import IconUser from '@/components/icon/icon-user.vue';
import IconBuilding from '@/components/icon/icon-building.vue';
import IconMail from '@/components/icon/icon-mail.vue';
import IconHome from '@/components/icon/icon-home.vue';
import IconSettings from '@/components/icon/icon-settings.vue';
import IconSave from '@/components/icon/icon-save.vue';
import IconX from '@/components/icon/icon-x.vue';
import CountrySelect from '@/components/CountrySelect.vue';
import PhoneInput from '@/components/PhoneInput.vue';
import EasyMDE from 'easymde';
import 'easymde/dist/easymde.min.css';

useMeta({ title: 'Create Client' });

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
    { value: 'prospect', label: t('clients.prospect') as string, color: 'info' },
    { value: 'active', label: t('clients.active') as string, color: 'success' },
    { value: 'inactive', label: t('clients.inactive') as string, color: 'warning' },
    { value: 'archived', label: t('clients.archived') as string, color: 'secondary' },
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

// State
const isSubmitting = ref(false);
const errorMessage = ref('');
const legalDocs = ref<LegalDocument[]>([]);
const createdClientId = ref<number | null>(null);

// Spec 64 — Client type selector state
const selectedType = ref<ClientType | null>(null);
const companyErrors = reactive<{ company_name: string; tax_id: string }>({
    company_name: '',
    tax_id: '',
});

const selectType = (type: ClientType) => {
    selectedType.value = type;
    errorMessage.value = '';
};

const changeType = () => {
    selectedType.value = null;
    errorMessage.value = '';
    companyErrors.company_name = '';
    companyErrors.tax_id = '';
};

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
    const isPerson = selectedType.value === 'person';
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
    if (!selectedType.value) {
        errorMessage.value = t('clients.validation.type_required');
        return;
    }

    const isValid = await v$.value.$validate();
    if (!isValid) return;

    if (selectedType.value === 'company' && !validateCompanyFields()) {
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = '';

    try {
        // Filter out empty string values and map canada_status_other to other_status_1
        const { canada_status_other, ...rest } = form;
        const data: Record<string, any> = Object.fromEntries(
            Object.entries(rest).filter(([_, v]) => v !== '' && v !== null)
        );
        if (canada_status_other) {
            data.other_status_1 = canada_status_other;
        }

        // Spec 64 — always include type, strip fields that don't apply to the selected type
        data.type = selectedType.value;
        if (selectedType.value === 'company') {
            // Remove person-only fields
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
            // Remove company-only fields
            delete data.company_name;
            delete data.trade_name;
            delete data.tax_id;
            delete data.industry;
            delete data.website;
            delete data.legal_rep_name;
            delete data.legal_rep_title;
        }

        const newClient = await clientStore.createClient(data as any);
        const clientId = (newClient as any)?.client?.id;

        // Save legal documents after client is created
        if (clientId && legalDocs.value.length > 0) {
            await Promise.all(
                legalDocs.value.map(doc => legalDocumentService.createForClient(clientId, doc))
            );
        }

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


const easyMDE = ref<EasyMDE | null>(null);

const initEasyMDE = async () => {
    await nextTick();
    const el = document.getElementById('description') as HTMLElement | null;
    if (!el || easyMDE.value) return;
    easyMDE.value = new EasyMDE({
        element: el,
        initialValue: form.description || '',
        spellChecker: false,
        toolbar: ['bold', 'italic', 'strikethrough', '|', 'heading-3', '|', 'quote', '|', 'unordered-list', 'ordered-list', '|', 'horizontal-rule'],
    });
    easyMDE.value.codemirror.on('change', () => {
        form.description = easyMDE.value!.value();
    });
};

// Init markdown editor only once the form (with #description textarea) is rendered
watch(selectedType, async (value) => {
    if (value !== null) {
        await initEasyMDE();
    } else {
        // When user goes back to the type selector, dispose the editor so it can re-init cleanly
        if (easyMDE.value) {
            try { easyMDE.value.toTextArea(); } catch { /* no-op */ }
            easyMDE.value = null;
        }
    }
});

onMounted(() => {
    // Form is hidden until type is chosen; the watcher above initializes EasyMDE
});

</script>
