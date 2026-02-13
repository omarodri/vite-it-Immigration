<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/clients" class="text-primary hover:underline">{{ $t('clients.clients') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ client?.first_name }} {{ client?.last_name }}</span>
            </li>
        </ul>

        <!-- Loading State -->
        <div v-if="isLoading" class="panel">
            <div class="animate-pulse space-y-4">
                <div class="flex items-center gap-4">
                    <div class="w-20 h-20 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                    <div class="space-y-2">
                        <div class="h-6 w-48 bg-gray-200 dark:bg-gray-700 rounded"></div>
                        <div class="h-4 w-32 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Client Profile -->
        <div v-else-if="client" class="space-y-5">
            <!-- Header Card -->
            <div class="panel">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-20 h-20 rounded-full flex items-center justify-center text-2xl font-bold" :class="getStatusAvatarClass(client.status)">
                            {{ getInitials(client.first_name, client.last_name) }}
                        </div>
                        <div>
                            <h4 class="text-xl font-bold dark:text-white-light">
                                {{ client.first_name }} {{ client.last_name }}
                            </h4>
                            <p class="text-gray-500">{{ client.profession || $t('clients.no_profession') }}</p>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="badge" :class="getStatusBadgeClass(client.status)">
                                    {{ $t(`clients.${client.status}`) }}
                                </span>
                                <span v-if="client.canada_status" class="badge badge-outline-primary">
                                    {{ formatCanadaStatus(client.canada_status) }}
                                </span>
                                <span v-if="client.is_primary_applicant" class="badge badge-outline-success">
                                    {{ $t('clients.primary_applicant') }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button
                            v-if="client.status === 'prospect'"
                            v-can="'clients.update'"
                            type="button"
                            class="btn btn-success gap-2"
                            @click="confirmConvert"
                        >
                            <icon-arrow-forward class="w-5 h-5" />
                            {{ $t('clients.convert_to_active') }}
                        </button>
                        <router-link
                            v-can="'clients.update'"
                            :to="`/clients/${client.id}/edit`"
                            class="btn btn-primary gap-2"
                        >
                            <icon-pencil class="w-5 h-5" />
                            {{ $t('clients.edit') }}
                        </router-link>
                        <router-link to="/clients" class="btn btn-outline-secondary gap-2">
                            <icon-arrow-left class="w-5 h-5" />
                            {{ $t('clients.back') }}
                        </router-link>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="panel p-0">
                <ul class="flex flex-wrap border-b border-gray-200 dark:border-gray-700">
                    <li>
                        <button
                            type="button"
                            class="px-5 py-3 border-b-2 font-medium transition-colors"
                            :class="activeTab === 'personal' ? 'border-primary text-primary' : 'border-transparent hover:text-primary'"
                            @click="activeTab = 'personal'"
                        >
                            {{ $t('clients.personal_information') }}
                        </button>
                    </li>
                    <li>
                        <button
                            type="button"
                            class="px-5 py-3 border-b-2 font-medium transition-colors"
                            :class="activeTab === 'contact' ? 'border-primary text-primary' : 'border-transparent hover:text-primary'"
                            @click="activeTab = 'contact'"
                        >
                            {{ $t('clients.contact_information') }}
                        </button>
                    </li>
                    <li>
                        <button
                            type="button"
                            class="px-5 py-3 border-b-2 font-medium transition-colors"
                            :class="activeTab === 'canada' ? 'border-primary text-primary' : 'border-transparent hover:text-primary'"
                            @click="activeTab = 'canada'"
                        >
                            {{ $t('clients.canada_legal_status') }}
                        </button>
                    </li>
                    <li>
                        <button
                            type="button"
                            class="px-5 py-3 border-b-2 font-medium transition-colors"
                            :class="activeTab === 'companions' ? 'border-primary text-primary' : 'border-transparent hover:text-primary'"
                            @click="activeTab = 'companions'"
                        >
                            {{ $t('clients.companions') }}
                            <span v-if="companions.length" class="badge badge-outline-primary ml-2">{{ companions.length }}</span>
                        </button>
                    </li>
                    <li>
                        <button
                            type="button"
                            class="px-5 py-3 border-b-2 font-medium transition-colors"
                            :class="activeTab === 'cases' ? 'border-primary text-primary' : 'border-transparent hover:text-primary'"
                            @click="activeTab = 'cases'"
                        >
                            {{ $t('clients.cases') }}
                            <span v-if="client?.cases?.length" class="badge badge-outline-primary ml-2">{{ client.cases.length }}</span>
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="p-5">
                    <!-- Personal Information Tab -->
                    <div v-if="activeTab === 'personal'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.first_name') }}</label>
                            <p class="font-semibold">{{ client.first_name }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.last_name') }}</label>
                            <p class="font-semibold">{{ client.last_name }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.date_of_birth') }}</label>
                            <p class="font-semibold">{{ client.date_of_birth ? formatDate(client.date_of_birth) : '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.gender') }}</label>
                            <p class="font-semibold capitalize">{{ client.gender || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.nationality') }}</label>
                            <p class="font-semibold">{{ client.nationality || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.marital_status') }}</label>
                            <p class="font-semibold capitalize">{{ client.marital_status?.replace('_', ' ') || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.profession') }}</label>
                            <p class="font-semibold">{{ client.profession || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.language') }}</label>
                            <p class="font-semibold">{{ client.language || '-' }}</p>
                        </div>
                        <div class="md:col-span-2 lg:col-span-3">
                            <label class="text-gray-500 text-sm">{{ $t('clients.notes') }}</label>
                            <p class="font-semibold whitespace-pre-wrap">{{ client.description || '-' }}</p>
                        </div>
                    </div>

                    <!-- Contact Information Tab -->
                    <div v-else-if="activeTab === 'contact'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.email') }}</label>
                            <p class="font-semibold">
                                <a v-if="client.email" :href="`mailto:${client.email}`" class="text-primary hover:underline">
                                    {{ client.email }}
                                </a>
                                <span v-else>-</span>
                            </p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.phone') }}</label>
                            <p class="font-semibold">
                                <a v-if="client.phone" :href="`tel:${client.phone}`" class="text-primary hover:underline">
                                    {{ client.phone }}
                                </a>
                                <span v-else>-</span>
                            </p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.secondary_phone') }}</label>
                            <p class="font-semibold">{{ client.secondary_phone || '-' }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-gray-500 text-sm">{{ $t('clients.residential_address') }}</label>
                            <p class="font-semibold">{{ client.residential_address || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.city') }}</label>
                            <p class="font-semibold">{{ client.city || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.province') }}</label>
                            <p class="font-semibold">{{ client.province || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.postal_code') }}</label>
                            <p class="font-semibold">{{ client.postal_code || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.country') }}</label>
                            <p class="font-semibold">{{ client.country || '-' }}</p>
                        </div>
                    </div>

                    <!-- Canada Legal Status Tab -->
                    <div v-else-if="activeTab === 'canada'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.status_in_canada') }}</label>
                            <p class="font-semibold">{{ client.canada_status ? formatCanadaStatus(client.canada_status) : '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.entry_point') }}</label>
                            <p class="font-semibold capitalize">{{ client.entry_point?.replace('_', ' ') || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.arrival_date') }}</label>
                            <p class="font-semibold">{{ client.arrival_date ? formatDate(client.arrival_date) : '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.passport_number') }}</label>
                            <p class="font-semibold">{{ client.passport_number || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.passport_country') }}</label>
                            <p class="font-semibold">{{ client.passport_country || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.passport_expiry') }}</label>
                            <p class="font-semibold">{{ client.passport_expiry_date ? formatDate(client.passport_expiry_date) : '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.iuc') }}</label>
                            <p class="font-semibold">{{ client.iuc || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.work_permit_number') }}</label>
                            <p class="font-semibold">{{ client.work_permit_number || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.study_permit_number') }}</label>
                            <p class="font-semibold">{{ client.study_permit_number || '-' }}</p>
                        </div>
                    </div>

                    <!-- Companions Tab -->
                    <div v-else-if="activeTab === 'companions'">
                        <div class="flex justify-between items-center mb-4">
                            <h5 class="text-lg font-semibold">{{ $t('clients.family_companions') }}</h5>
                            <button
                                v-can="'clients.update'"
                                type="button"
                                class="btn btn-primary btn-sm gap-2"
                                @click="openCompanionModal()"
                            >
                                <icon-plus class="w-4 h-4" />
                                {{ $t('companions.add') }}
                            </button>
                        </div>

                        <div v-if="isLoadingCompanions" class="text-center py-10">
                            <div class="animate-spin inline-block w-8 h-8 border-4 border-primary border-t-transparent rounded-full"></div>
                        </div>

                        <div v-else-if="!companions.length" class="text-center py-10">
                            <icon-users class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
                            <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">{{ $t('companions.no_companions') }}</h3>
                            <p class="text-gray-500 mb-4">{{ $t('companions.add_family_members') }}</p>
                        </div>

                        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div
                                v-for="companion in companions"
                                :key="companion.id"
                                class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded-full flex items-center justify-center text-sm font-bold bg-primary/10 text-primary">
                                            {{ getInitials(companion.first_name, companion.last_name) }}
                                        </div>
                                        <div>
                                            <h6 class="font-semibold">{{ companion.first_name }} {{ companion.last_name }}</h6>
                                            <p class="text-sm text-gray-500">{{ companion.relationship_label || formatRelationship(companion.relationship) }}</p>
                                            <p v-if="companion.age" class="text-xs text-gray-400">{{ companion.age }} {{ $t('companions.years_old') }}</p>
                                        </div>
                                    </div>
                                    <div class="flex gap-1">
                                        <button
                                            v-can="'clients.update'"
                                            type="button"
                                            class="btn btn-sm btn-outline-primary p-1"
                                            @click="openCompanionModal(companion)"
                                        >
                                            <icon-pencil class="w-4 h-4" />
                                        </button>
                                        <button
                                            v-can="'clients.delete'"
                                            type="button"
                                            class="btn btn-sm btn-outline-danger p-1"
                                            @click="confirmDeleteCompanion(companion)"
                                        >
                                            <icon-trash class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                                <div v-if="companion.passport_number" class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                    <p class="text-xs text-gray-500">
                                        <span class="font-medium">{{ $t('companions.passport') }}:</span>
                                        {{ companion.passport_number }}
                                        <span v-if="companion.passport_country">({{ companion.passport_country }})</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cases Tab -->
                    <div v-else-if="activeTab === 'cases'">
                        <div class="flex justify-between items-center mb-4">
                            <h5 class="text-lg font-semibold">{{ $t('clients.assigned_cases') }}</h5>
                            <router-link
                                v-can="'cases.create'"
                                :to="`/cases/wizard?client_id=${client.id}`"
                                class="btn btn-primary btn-sm gap-2"
                            >
                                <icon-plus class="w-4 h-4" />
                                {{ $t('cases.add_case') }}
                            </router-link>
                        </div>

                        <div v-if="!client.cases?.length" class="text-center py-10">
                            <icon-folder class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
                            <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">{{ $t('clients.no_cases_yet') }}</h3>
                            <p class="text-gray-500 mb-4">{{ $t('clients.cases_will_appear_here') }}</p>
                        </div>
                        <div v-else class="space-y-3">
                            <router-link
                                v-for="caseItem in client.cases"
                                :key="caseItem.id"
                                :to="`/cases/${caseItem.id}`"
                                class="block border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md hover:border-primary/50 transition-all"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h5 class="font-semibold text-primary">{{ caseItem.case_number }}</h5>
                                        <p class="text-sm text-gray-500">{{ caseItem.case_type?.name || '-' }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="badge" :class="getCaseBadgeClass(caseItem.status)">
                                            {{ $t(`cases.${caseItem.status}`) }}
                                        </span>
                                        <span v-if="caseItem.priority === 'urgent' || caseItem.priority === 'high'" class="badge" :class="caseItem.priority === 'urgent' ? 'badge-danger' : 'badge-warning'">
                                            {{ $t(`cases.${caseItem.priority}`) }}
                                        </span>
                                    </div>
                                </div>
                            </router-link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Metadata -->
            <div class="panel">
                <div class="flex flex-wrap gap-6 text-sm text-gray-500">
                    <div>
                        <span class="font-medium">{{ $t('clients.created') }}:</span>
                        {{ formatDate(client.created_at) }}
                    </div>
                    <div>
                        <span class="font-medium">{{ $t('clients.updated') }}:</span>
                        {{ formatDate(client.updated_at) }}
                    </div>
                    <div>
                        <span class="font-medium">ID:</span>
                        #{{ client.id }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Not Found -->
        <div v-else class="panel text-center py-10">
            <icon-users class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
            <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">{{ $t('clients.not_found') }}</h3>
            <router-link to="/clients" class="btn btn-primary mt-4">
                {{ $t('clients.back_to_list') }}
            </router-link>
        </div>

        <!-- Companion Modal -->
        <TransitionRoot appear :show="showCompanionModal" as="template">
            <Dialog as="div" class="relative z-50" @close="closeCompanionModal">
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
                            <DialogPanel class="w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white dark:bg-gray-900 p-6 text-left align-middle shadow-xl transition-all">
                                <DialogTitle as="h3" class="text-lg font-medium leading-6 text-gray-900 dark:text-white mb-4">
                                    {{ editingCompanion ? $t('companions.edit_companion') : $t('companions.add_companion') }}
                                </DialogTitle>

                                <form @submit.prevent="saveCompanion" class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium mb-1">{{ $t('companions.first_name') }} *</label>
                                            <input
                                                v-model="companionForm.first_name"
                                                type="text"
                                                class="form-input"
                                                :class="{ 'border-danger': companionErrors.first_name }"
                                                required
                                            />
                                            <p v-if="companionErrors.first_name" class="text-danger text-xs mt-1">{{ companionErrors.first_name[0] }}</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium mb-1">{{ $t('companions.last_name') }} *</label>
                                            <input
                                                v-model="companionForm.last_name"
                                                type="text"
                                                class="form-input"
                                                :class="{ 'border-danger': companionErrors.last_name }"
                                                required
                                            />
                                            <p v-if="companionErrors.last_name" class="text-danger text-xs mt-1">{{ companionErrors.last_name[0] }}</p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium mb-1">{{ $t('companions.relationship') }} *</label>
                                            <select
                                                v-model="companionForm.relationship"
                                                class="form-select"
                                                :class="{ 'border-danger': companionErrors.relationship }"
                                                required
                                            >
                                                <option value="">{{ $t('companions.select_relationship') }}</option>
                                                <option value="spouse">{{ $t('companions.spouse') }}</option>
                                                <option value="child">{{ $t('companions.child') }}</option>
                                                <option value="parent">{{ $t('companions.parent') }}</option>
                                                <option value="sibling">{{ $t('companions.sibling') }}</option>
                                                <option value="other">{{ $t('companions.other') }}</option>
                                            </select>
                                            <p v-if="companionErrors.relationship" class="text-danger text-xs mt-1">{{ companionErrors.relationship[0] }}</p>
                                        </div>
                                        <div v-if="companionForm.relationship === 'other'">
                                            <label class="block text-sm font-medium mb-1">{{ $t('companions.specify_relationship') }} *</label>
                                            <input
                                                v-model="companionForm.relationship_other"
                                                type="text"
                                                class="form-input"
                                                :class="{ 'border-danger': companionErrors.relationship_other }"
                                            />
                                            <p v-if="companionErrors.relationship_other" class="text-danger text-xs mt-1">{{ companionErrors.relationship_other[0] }}</p>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium mb-1">{{ $t('companions.date_of_birth') }}</label>
                                            <!-- <input
                                                v-model="companionForm.date_of_birth"
                                                type="date"
                                                class="form-input"
                                                :max="today"
                                            /> -->
                                            <flat-pickr
                                                v-model="companionForm.date_of_birth"
                                                :config="dateConfig"
                                                class="form-input"
                                                :placeholder="$t('clients.select_date')"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium mb-1">{{ $t('companions.gender') }}</label>
                                            <select v-model="companionForm.gender" class="form-select">
                                                <option value="">{{ $t('companions.select_gender') }}</option>
                                                <option value="male">{{ $t('companions.male') }}</option>
                                                <option value="female">{{ $t('companions.female') }}</option>
                                                <option value="other">{{ $t('companions.gender_other') }}</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium mb-1">{{ $t('companions.nationality') }}</label>
                                            <!-- <input
                                                v-model="companionForm.nationality"
                                                type="text"
                                                class="form-input"
                                            /> -->
                                            <CountrySelect
                                                id="nationality"
                                                v-model="companionForm.nationality"
                                                :placeholder="$t('clients.select_nationality')"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium mb-1">{{ $t('companions.passport_number') }}</label>
                                            <input
                                                v-model="companionForm.passport_number"
                                                type="text"
                                                class="form-input"
                                            />
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium mb-1">{{ $t('companions.passport_country') }}</label>
                                            <!-- <input
                                                v-model="companionForm.passport_country"
                                                type="text"
                                                class="form-input"
                                            /> -->
                                            <CountrySelect
                                                id="passport_country"
                                                v-model="companionForm.passport_country"
                                                :placeholder="$t('clients.select_nationality')"
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium mb-1">{{ $t('companions.passport_expiry') }}</label>
                                            <!-- <input
                                                v-model="companionForm.passport_expiry_date"
                                                type="date"
                                                class="form-input"
                                            /> -->
                                            <flat-pickr
                                                v-model="companionForm.passport_expiry_date"
                                                :config="passportExpiryDateConfig"
                                                class="form-input"
                                                :placeholder="$t('clients.select_date')"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium mb-1">{{ $t('companions.notes') }}</label>
                                        <textarea
                                            v-model="companionForm.notes"
                                            rows="2"
                                            class="form-textarea"
                                        ></textarea>
                                    </div>

                                    <div class="flex justify-end gap-3 mt-6">
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary"
                                            @click="closeCompanionModal"
                                        >
                                            {{ $t('companions.cancel') }}
                                        </button>
                                        <button
                                            type="submit"
                                            class="btn btn-primary"
                                            :disabled="isSavingCompanion"
                                        >
                                            <span v-if="isSavingCompanion" class="animate-spin border-2 border-white border-t-transparent rounded-full w-4 h-4 mr-2 inline-block"></span>
                                            {{ editingCompanion ? $t('companions.update') : $t('companions.save') }}
                                        </button>
                                    </div>
                                </form>
                            </DialogPanel>
                        </TransitionChild>
                    </div>
                </div>
            </Dialog>
        </TransitionRoot>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useMeta } from '@/composables/use-meta';
import { useClientStore } from '@/stores/client';
import { useCompanionStore } from '@/stores/companion';
import { useNotification } from '@/composables/useNotification';
import { useI18n } from 'vue-i18n';
import { formatDate } from '@/utils/formatters';
import { Dialog, DialogPanel, DialogTitle, TransitionRoot, TransitionChild } from '@headlessui/vue';
import type { Client, ClientStatus } from '@/types/client';
import type { Companion, CreateCompanionData, UpdateCompanionData, RelationshipType } from '@/types/companion';
import CountrySelect from '@/components/CountrySelect.vue';
import flatPickr from 'vue-flatpickr-component';
import 'flatpickr/dist/flatpickr.css';

// Icons
import IconArrowLeft from '@/components/icon/icon-arrow-left.vue';
import IconPencil from '@/components/icon/icon-pencil.vue';
import IconArrowForward from '@/components/icon/icon-arrow-forward.vue';
import IconUsers from '@/components/icon/icon-users.vue';
import IconFolder from '@/components/icon/icon-folder.vue';
import IconPlus from '@/components/icon/icon-plus.vue';
import IconTrash from '@/components/icon/icon-trash.vue';

useMeta({ title: 'Client Profile' });

const route = useRoute();
const clientStore = useClientStore();
const companionStore = useCompanionStore();
const { confirm: confirmDialog, success, error } = useNotification();
const { t } = useI18n();

const client = ref<Client | null>(null);
const isLoading = ref(true);
const activeTab = ref('personal');

// Companion state
const companions = ref<Companion[]>([]);
const isLoadingCompanions = ref(false);
const showCompanionModal = ref(false);
const editingCompanion = ref<Companion | null>(null);
const isSavingCompanion = ref(false);
const companionErrors = ref<Record<string, string[]>>({});
const today = new Date().toISOString().split('T')[0];

const companionForm = ref<CreateCompanionData>({
    first_name: '',
    last_name: '',
    relationship: '' as RelationshipType,
    relationship_other: '',
    date_of_birth: '',
    gender: undefined,
    nationality: '',
    passport_number: '',
    passport_country: '',
    passport_expiry_date: '',
    notes: '',
});

// Date picker config
const maxBirthDate = new Date();
maxBirthDate.setDate(maxBirthDate.getDate() - 1);

const dateConfig = {
    dateFormat: 'Y-m-d',
    allowInput: true,
    maxDate: maxBirthDate.toISOString().split('T')[0],
};
const passportExpiryDateConfig = {
    dateFormat: 'Y-m-d',
    allowInput: true,
};

const getInitials = (firstName: string, lastName: string): string => {
    return ((firstName?.[0] || '') + (lastName?.[0] || '')).toUpperCase();
};

const getStatusBadgeClass = (status: ClientStatus): string => {
    const classes: Record<ClientStatus, string> = {
        prospect: 'badge-outline-info',
        active: 'badge-outline-success',
        inactive: 'badge-outline-warning',
        archived: 'badge-outline-secondary',
    };
    return classes[status] || 'badge-outline-primary';
};

const getStatusAvatarClass = (status: ClientStatus): string => {
    const classes: Record<ClientStatus, string> = {
        prospect: 'bg-info/10 text-info',
        active: 'bg-success/10 text-success',
        inactive: 'bg-warning/10 text-warning',
        archived: 'bg-secondary/10 text-secondary',
    };
    return classes[status] || 'bg-primary/10 text-primary';
};

const formatCanadaStatus = (status: string): string => {
    return status.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
};

const getCaseBadgeClass = (status: string): string => {
    const classes: Record<string, string> = {
        active: 'badge-outline-success',
        pending: 'badge-outline-warning',
        closed: 'badge-outline-secondary',
    };
    return classes[status] || 'badge-outline-primary';
};

const formatRelationship = (relationship: string): string => {
    const labels: Record<string, string> = {
        spouse: 'Cónyuge',
        child: 'Hijo/a',
        parent: 'Padre/Madre',
        sibling: 'Hermano/a',
        other: 'Otro',
    };
    return labels[relationship] || relationship;
};

const loadCompanions = async () => {
    if (!client.value) return;
    isLoadingCompanions.value = true;
    try {
        await companionStore.fetchCompanions(client.value.id);
        companions.value = companionStore.companions;
    } catch (err) {
        console.error('Failed to load companions:', err);
    } finally {
        isLoadingCompanions.value = false;
    }
};

const resetCompanionForm = () => {
    companionForm.value = {
        first_name: '',
        last_name: '',
        relationship: '' as RelationshipType,
        relationship_other: '',
        date_of_birth: '',
        gender: undefined,
        nationality: '',
        passport_number: '',
        passport_country: '',
        passport_expiry_date: '',
        notes: '',
    };
    companionErrors.value = {};
};

const openCompanionModal = (companion?: Companion) => {
    resetCompanionForm();
    if (companion) {
        editingCompanion.value = companion;
        companionForm.value = {
            first_name: companion.first_name,
            last_name: companion.last_name,
            relationship: companion.relationship,
            relationship_other: companion.relationship_other || '',
            date_of_birth: companion.date_of_birth || '',
            gender: companion.gender || undefined,
            nationality: companion.nationality || '',
            passport_number: companion.passport_number || '',
            passport_country: companion.passport_country || '',
            passport_expiry_date: companion.passport_expiry_date || '',
            notes: companion.notes || '',
        };
    } else {
        editingCompanion.value = null;
    }
    showCompanionModal.value = true;
};

const closeCompanionModal = () => {
    showCompanionModal.value = false;
    editingCompanion.value = null;
    resetCompanionForm();
};

const saveCompanion = async () => {
    if (!client.value) return;
    isSavingCompanion.value = true;
    companionErrors.value = {};

    try {
        if (editingCompanion.value) {
            await companionStore.updateCompanion(
                client.value.id,
                editingCompanion.value.id,
                companionForm.value as UpdateCompanionData
            );
            success(t('companions.updated_successfully'));
        } else {
            await companionStore.createCompanion(
                client.value.id,
                companionForm.value as CreateCompanionData
            );
            success(t('companions.created_successfully'));
        }
        companions.value = companionStore.companions;
        closeCompanionModal();
    } catch (err: any) {
        if (err.response?.status === 422 && err.response?.data?.errors) {
            companionErrors.value = err.response.data.errors;
        } else {
            error(err.response?.data?.message || t('companions.save_failed'));
        }
    } finally {
        isSavingCompanion.value = false;
    }
};

const confirmDeleteCompanion = async (companion: Companion) => {
    if (!client.value) return;

    const confirmed = await confirmDialog({
        title: t('companions.confirm_delete'),
        text: t('companions.delete_warning', { name: `${companion.first_name} ${companion.last_name}` }),
        icon: 'warning',
        confirmButtonText: t('companions.yes_delete'),
        cancelButtonText: t('companions.cancel'),
    });

    if (confirmed) {
        try {
            await companionStore.deleteCompanion(client.value.id, companion.id);
            companions.value = companionStore.companions;
            success(t('companions.deleted_successfully'));
        } catch (err: any) {
            error(err.response?.data?.message || t('companions.delete_failed'));
        }
    }
};

const confirmConvert = async () => {
    if (!client.value) return;

    const confirmed = await confirmDialog({
        title: t('clients.confirm_convert', { name: `${client.value.first_name} ${client.value.last_name}` }),
        text: t('clients.convert_description'),
        icon: 'info',
        confirmButtonText: t('clients.yes_convert'),
        cancelButtonText: t('clients.cancel'),
    });

    if (confirmed) {
        try {
            await clientStore.convertProspect(client.value.id);
            // Re-fetch the full client with all relations (cases, companions, user)
            // since the convert endpoint returns the client without relations loaded
            client.value = await clientStore.fetchClient(client.value.id);
            success(t('clients.converted_successfully'));
        } catch (err: any) {
            error(err.response?.data?.message || t('clients.convert_failed'));
        }
    }
};

// Load companions when tab becomes active
watch(activeTab, (newTab) => {
    if (newTab === 'companions' && client.value && !companions.value.length) {
        loadCompanions();
    }
});

onMounted(async () => {
    try {
        const id = parseInt(route.params.id as string);
        client.value = await clientStore.fetchClient(id);
        // Preload companions if we might show them
        if (client.value) {
            loadCompanions();
        }
    } catch (err) {
        error(t('clients.failed_to_load'));
    } finally {
        isLoading.value = false;
    }
});
</script>
