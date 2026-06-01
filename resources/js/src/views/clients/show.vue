<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/clients" class="text-primary hover:underline">{{ $t('clients.clients') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ displayName }}</span>
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
                            <icon-building v-if="client.type === 'company'" class="w-10 h-10" />
                            <template v-else>{{ client.initials || getInitials(client.first_name, client.last_name) }}</template>
                        </div>
                        <div>
                            <h4 class="text-xl font-bold dark:text-white-light">
                                {{ displayName }}
                            </h4>
                            <p v-if="client.type === 'company'" class="text-gray-500">
                                {{ client.industry || $t('clients.no_industry') }}
                            </p>
                            <p v-else class="text-gray-500">
                                {{ client.profession || $t('clients.no_profession') }}
                            </p>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="badge" :class="getStatusBadgeClass(client.status)">
                                    {{ $t(`clients.${client.status}`) }}
                                </span>
                                <span :class="client.type === 'company' ? 'badge badge-outline-success' : 'badge badge-outline-info'">
                                    {{ client.type === 'company' ? $t('clients.type.company') : $t('clients.type.person') }}
                                </span>
                                <span v-if="client.type === 'person' && client.canada_status" class="badge badge-outline-primary">
                                    {{ formatCanadaStatus(client.canada_status) }}
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
                        <button
                            v-can="'clients.delete'"
                            type="button"
                            class="btn btn-danger gap-2"
                            :disabled="!canDelete"
                            :title="!canDelete ? $t('clients.delete_blocked_tooltip') : ''"
                            @click="confirmDeleteClient"
                        >
                            <icon-trash class="w-5 h-5" />
                            {{ $t('clients.delete') }}
                        </button>
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
                            {{ client.type === 'company' ? $t('clients.company_information') : $t('clients.personal_information') }}
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
                    <li v-if="client.type !== 'company'">
                        <button
                            type="button"
                            class="px-5 py-3 border-b-2 font-medium transition-colors"
                            :class="activeTab === 'canada' ? 'border-primary text-primary' : 'border-transparent hover:text-primary'"
                            @click="activeTab = 'canada'"
                        >
                            {{ $t('clients.canada_legal_status1') }}
                        </button>
                    </li>
                    <li>
                        <button
                            type="button"
                            class="px-5 py-3 border-b-2 font-medium transition-colors"
                            :class="activeTab === 'documents' ? 'border-primary text-primary' : 'border-transparent hover:text-primary'"
                            @click="activeTab = 'documents'"
                        >
                            {{ $t('documents.legal_documents') }}
                            <span v-if="legalDocs.length" class="badge badge-outline-primary ml-2">{{ legalDocs.length }}</span>
                        </button>
                    </li>
                    <li>
                        <button
                            type="button"
                            class="px-5 py-3 border-b-2 font-medium transition-colors"
                            :class="activeTab === 'companions' ? 'border-primary text-primary' : 'border-transparent hover:text-primary'"
                            @click="activeTab = 'companions'"
                        >
                            {{ client.type === 'company' ? $t('clients.tabs.sponsored_employees') : $t('clients.tabs.companions') }}
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
                    <!-- Personal / Company Information Tab -->
                    <div v-if="activeTab === 'personal' && client.type === 'company'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.fields.company_name') }}</label>
                            <p class="font-semibold">{{ client.company_name || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.fields.trade_name') }}</label>
                            <p class="font-semibold">{{ client.trade_name || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.fields.tax_id') }}</label>
                            <p class="font-semibold">{{ client.tax_id || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.fields.industry') }}</label>
                            <p class="font-semibold">{{ client.industry || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.fields.website') }}</label>
                            <p class="font-semibold">
                                <a v-if="client.website" :href="client.website" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">
                                    {{ client.website }}
                                </a>
                                <span v-else>-</span>
                            </p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.fields.legal_rep_name') }}</label>
                            <p class="font-semibold">{{ client.legal_rep_name || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.fields.legal_rep_title') }}</label>
                            <p class="font-semibold">{{ client.legal_rep_title || '-' }}</p>
                        </div>
                        <div class="md:col-span-2 lg:col-span-3">
                            <label class="text-gray-500 text-sm">{{ $t('clients.notes') }}</label>
                            <p class="font-semibold whitespace-pre-wrap">{{ client.description || '-' }}</p>
                        </div>
                    </div>

                    <div v-else-if="activeTab === 'personal'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
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
                            <p class="font-semibold capitalize">{{ $t(`clients.${client.marital_status}`) || '-'}}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.profession') }}</label>
                            <p class="font-semibold">{{ client.profession || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.language') }}</label>
                            <p class="font-semibold">{{ $t(`clients.${client.language}`) || '-' }}</p>
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
                                <a v-if="client.phone" :href="`tel:${client.phone_country_code || '+1'}${client.phone}`" class="text-primary hover:underline">
                                    {{ client.phone_country_code || '+1' }} {{ client.phone }}
                                </a>
                                <span v-else>-</span>
                            </p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.secondary_phone') }}</label>
                            <p class="font-semibold">
                                <template v-if="client.secondary_phone">{{ client.secondary_phone_country_code || '+1' }} {{ client.secondary_phone }}</template>
                                <template v-else>-</template>
                            </p>
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
                            <p class="font-semibold capitalize">{{ $t(`clients.${client.entry_point}`) || '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.arrival_date') }}</label>
                            <p class="font-semibold">{{ client.arrival_date ? formatDate(client.arrival_date) : '-' }}</p>
                        </div>
                        <div>
                            <label class="text-gray-500 text-sm">{{ $t('clients.iuc') }}</label>
                            <p class="font-semibold">{{ client.iuc || '-' }}</p>
                        </div>
                    </div>

                    <!-- Legal Documents Tab -->
                    <div v-else-if="activeTab === 'documents'">
                        <div v-if="isLoadingDocs" class="text-center py-10">
                            <div class="animate-spin inline-block w-8 h-8 border-4 border-primary border-t-transparent rounded-full"></div>
                        </div>
                        <DocumentRepeater
                            v-else
                            v-model="legalDocs"
                            entity-type="client"
                            :entity-id="client!.id"
                            :readonly="true"
                        />
                    </div>

                    <!-- Companions / Sponsored Employees Tab -->
                    <div v-else-if="activeTab === 'companions'">

                        <!-- ===== EMPRESA: accordion de empleados con familiares ===== -->
                        <template v-if="client.type === 'company'">
                            <div class="flex justify-between items-center mb-4">
                                <h5 class="text-lg font-semibold">
                                    {{ $t('clients.tabs.sponsored_employees') }}
                                </h5>
                                <button
                                    v-can="'companions.create'"
                                    type="button"
                                    class="btn btn-primary btn-sm gap-2"
                                    @click="openCompanionModal()"
                                >
                                    <icon-plus class="w-4 h-4" />
                                    {{ $t('clients.add_employee') }}
                                </button>
                            </div>

                            <div v-if="isLoadingCompanions" class="text-center py-10">
                                <div class="animate-spin inline-block w-8 h-8 border-4 border-primary border-t-transparent rounded-full"></div>
                            </div>

                            <div v-else-if="!companions.length" class="text-center py-10">
                                <icon-users class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
                                <h3 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">{{ $t('companions.no_companions') }}</h3>
                                <p class="text-gray-500 mb-4">{{ $t('clients.no_employees_yet') }}</p>
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    v-for="employee in companions"
                                    :key="employee.id"
                                    class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden"
                                >
                                    <!-- Header del empleado -->
                                    <div
                                        class="flex items-center gap-3 p-4 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                                        @click="toggleEmployee(employee.id)"
                                    >
                                        <div class="w-11 h-11 rounded-full bg-success/10 text-success flex items-center justify-center text-sm font-bold shrink-0">
                                            {{ employee.initials || getInitials(employee.first_name, employee.last_name) }}
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h6 class="font-semibold truncate">{{ employee.full_name }}</h6>
                                            <p class="text-xs text-gray-500">
                                                {{ $t('companions.beneficiary_employee') }}
                                                <span v-if="employee.iuc" class="ml-2">· IUC: {{ employee.iuc }}</span>
                                            </p>
                                        </div>
                                        <span class="badge badge-outline-primary text-xs shrink-0">
                                            {{ employee.family_count ?? (employee.family_members?.length ?? 0) }}
                                            {{ $t('clients.family_members_count') }}
                                        </span>
                                        <div class="flex gap-1 shrink-0" @click.stop>
                                            <button
                                                v-can="'clients.update'"
                                                type="button"
                                                class="btn btn-sm btn-outline-primary p-1.5"
                                                :title="$t('companions.edit_companion')"
                                                @click="openCompanionModal(employee)"
                                            >
                                                <icon-pencil class="w-3.5 h-3.5" />
                                            </button>
                                            <button
                                                v-can="'companions.delete'"
                                                type="button"
                                                class="btn btn-sm btn-outline-danger p-1.5"
                                                :title="$t('companions.delete_companion')"
                                                @click="confirmDeleteEmployee(employee)"
                                            >
                                                <icon-trash class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                        <icon-caret-down
                                            class="w-5 h-5 text-gray-400 shrink-0 transition-transform duration-200"
                                            :class="{ 'rotate-180': expandedEmployees.has(employee.id) }"
                                        />
                                    </div>

                                    <!-- Panel familia (expandible) -->
                                    <div
                                        v-show="expandedEmployees.has(employee.id)"
                                        class="border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/30 p-4"
                                    >
                                        <div class="flex justify-between items-center mb-3">
                                            <h6 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                {{ $t('clients.family_of', { name: employee.first_name }) }}
                                            </h6>
                                            <button
                                                v-can="'companions.create'"
                                                type="button"
                                                class="btn btn-outline-primary btn-xs gap-1"
                                                @click="openFamilyMemberModal(employee)"
                                            >
                                                <icon-plus class="w-3 h-3" />
                                                {{ $t('clients.add_family_member') }}
                                            </button>
                                        </div>

                                        <p v-if="!employee.family_members?.length" class="text-sm text-gray-500 text-center py-4">
                                            {{ $t('clients.no_family_yet') }}
                                        </p>

                                        <div v-else class="grid sm:grid-cols-2 gap-2">
                                            <div
                                                v-for="fm in employee.family_members"
                                                :key="fm.id"
                                                class="flex items-center gap-2 p-2.5 bg-white dark:bg-gray-900 rounded-lg border border-gray-100 dark:border-gray-700"
                                            >
                                                <div class="w-8 h-8 rounded-full bg-secondary/10 text-secondary flex items-center justify-center text-xs font-bold shrink-0">
                                                    {{ fm.initials || getInitials(fm.first_name, fm.last_name) }}
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium truncate">{{ fm.full_name }}</p>
                                                    <p class="text-xs text-gray-500">{{ formatRelationship(fm.relationship) }}</p>
                                                </div>
                                                <div class="flex gap-1 shrink-0">
                                                    <button
                                                        v-can="'clients.update'"
                                                        type="button"
                                                        class="btn btn-xs btn-outline-primary p-1"
                                                        @click="openFamilyMemberModal(employee, fm)"
                                                    >
                                                        <icon-pencil class="w-3 h-3" />
                                                    </button>
                                                    <button
                                                        v-can="'companions.delete'"
                                                        type="button"
                                                        class="btn btn-xs btn-outline-danger p-1"
                                                        @click="confirmDeleteCompanion(fm)"
                                                    >
                                                        <icon-trash class="w-3 h-3" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- ===== PERSONA: render plano original ===== -->
                        <template v-else>
                        <div class="flex justify-between items-center mb-4">
                            <h5 class="text-lg font-semibold">
                                {{ $t('clients.family_companions') }}
                            </h5>
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
                                            {{ companion.initials || getInitials(companion.first_name, companion.last_name) }}
                                        </div>
                                        <div>
                                            <h6 class="font-semibold">{{ companion.full_name }}</h6>
                                            <p class="text-sm text-gray-500">{{ formatRelationship(companion.relationship) }}</p>
                                            <p v-if="companion.age" class="text-xs text-gray-400">{{ companion.age }} {{ $t('companions.years_old') }}</p>
                                            <p v-if="companion.email" class="text-xs text-gray-400">{{ companion.email }}</p>
                                            <p v-if="companion.canada_status_label" class="text-xs text-gray-400">{{ companion.canada_status_label }}</p>
                                        </div>
                                    </div>
                                    <div class="flex gap-1">
                                        <!-- Already promoted → link to promoted client -->
                                        <router-link
                                            v-if="computeLocalEligibility(companion).alreadyPromoted && companion.promoted_to_client_id"
                                            :to="{ name: 'clients-show', params: { id: companion.promoted_to_client_id } }"
                                            class="btn btn-sm p-1"
                                            :title="$t('companion.promote.already_promoted_tooltip')"
                                        >
                                            <icon-user-plus class="w-4 h-4 text-info" />
                                        </router-link>
                                        <!-- Eligible → promote button -->
                                        <button
                                            v-else-if="computeLocalEligibility(companion).eligible"
                                            v-can="'clients.promote_from_companion'"
                                            type="button"
                                            class="btn btn-sm btn-outline-success p-1"
                                            :title="$t('companion.promote.promote_tooltip')"
                                            @click="handlePromote(companion)"
                                        >
                                            <icon-user-plus class="w-4 h-4" />
                                        </button>
                                        <!-- Not eligible → disabled with reason tooltip -->
                                        <button
                                            v-else
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary p-1 opacity-40 cursor-not-allowed"
                                            :title="$t(`companion.promote.tooltip_${computeLocalEligibility(companion).reasons[0] ?? 'unknown'}`)"
                                            disabled
                                        >
                                            <icon-user-plus class="w-4 h-4" />
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-info p-1"
                                            :title="$t('companions.view_companion')"
                                            @click="openViewModal(companion)"
                                        >
                                            <icon-eye class="w-4 h-4" />
                                        </button>
                                        <button
                                            v-can="'clients.update'"
                                            type="button"
                                            class="btn btn-sm btn-outline-primary p-1"
                                            @click="openCompanionModal(companion)"
                                        >
                                            <icon-pencil class="w-4 h-4" />
                                        </button>
                                        <button
                                            v-can="'companions.delete'"
                                            type="button"
                                            class="btn btn-sm btn-outline-danger p-1"
                                            @click="confirmDeleteCompanion(companion)"
                                        >
                                            <icon-trash class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                                <div v-if="companion.iuc" class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        IUC: {{ companion.iuc }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        </template>
                    </div>

                    <!-- Cases Tab -->
                    <div v-else-if="activeTab === 'cases'">
                        <div class="flex justify-between items-center mb-4">
                            <h5 class="text-lg font-semibold">{{ $t('clients.assigned_cases') }}</h5>
                            <router-link
                                v-can="'cases.create'"
                                :to="`/cases/wizard?client_id=${client.id}&client_name=${encodeURIComponent(displayName)}`"
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
                                        <p class="text-sm text-gray-500">{{ $t(`case_types.${caseItem.case_type?.name}`) || '-' }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="badge" :class="getCaseBadgeClass(caseItem.status)">
                                            {{ $t(`cases.${caseItem.status}`) }}
                                        </span>
                                        <span class="badge" :class="getUrgencyBadgeClass(caseItem.priority)">
                                        <!-- v-if="caseItem.priority === 'urgent' || caseItem.priority === 'high'" class="badge" :class="caseItem.priority === 'urgent' ? 'badge-danger' : 'badge-warning'"> -->
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

        <!-- Companion Edit Modal -->
        <CompanionFormModal
            v-model:show="showCompanionModal"
            :client-id="client?.id ?? 0"
            :companion="editingCompanion"
            :preset-relationship="presetRelationshipForModal"
            @saved="onCompanionSaved"
            @close="editingCompanion = null; presetRelationshipForModal = null"
        />

        <!-- Spec 69 — Family member modal (children of an employee, company clients) -->
        <CompanionFormModal
            v-if="showFamilyMemberModal && employeeToAddFamily"
            :show="showFamilyMemberModal"
            :client-id="client?.id ?? 0"
            :companion="editingFamilyMember"
            :parent-companion-id="employeeToAddFamily.id"
            @update:show="showFamilyMemberModal = $event"
            @saved="onFamilyMemberSaved"
            @close="showFamilyMemberModal = false; editingFamilyMember = null; employeeToAddFamily = null"
        />

        <!-- Companion View Modal -->
        <CompanionViewModal
            :show="showViewModal"
            :companion="viewingCompanion"
            @close="showViewModal = false; viewingCompanion = null"
            @edit="openCompanionModalFromView"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useMeta } from '@/composables/use-meta';
import { useClientStore } from '@/stores/client';
import { useCompanionStore } from '@/stores/companion';
import { useNotification } from '@/composables/useNotification';
import { useI18n } from 'vue-i18n';
import { formatDate } from '@/utils/formatters';
import type { Client, ClientStatus } from '@/types/client';
import type { Companion, RelationshipType } from '@/types/companion';
import type { LegalDocument } from '@/types/legal-document';
import DocumentRepeater from '@/components/DocumentRepeater.vue';
import { legalDocumentService } from '@/services/legalDocumentService';

// Icons
import IconArrowLeft from '@/components/icon/icon-arrow-left.vue';
import IconBuilding from '@/components/icon/icon-building.vue';
import IconPencil from '@/components/icon/icon-pencil.vue';
import IconArrowForward from '@/components/icon/icon-arrow-forward.vue';
import IconUsers from '@/components/icon/icon-users.vue';
import IconFolder from '@/components/icon/icon-folder.vue';
import IconPlus from '@/components/icon/icon-plus.vue';
import IconTrash from '@/components/icon/icon-trash.vue';
import IconEye from '@/components/icon/icon-eye.vue';
import IconUserPlus from '@/components/icon/icon-user-plus.vue';
import IconCaretDown from '@/components/icon/icon-caret-down.vue';
import CompanionFormModal from '@/components/companions/CompanionFormModal.vue';
import CompanionViewModal from '@/components/companions/CompanionViewModal.vue';
import companionPromotionService from '@/services/companionPromotionService';
import companionService from '@/services/companionService';
import Swal from 'sweetalert2';

useMeta({ title: 'Client Profile' });

const route = useRoute();
const router = useRouter();
const clientStore = useClientStore();
const companionStore = useCompanionStore();
const { confirm: confirmDialog, success, error } = useNotification();
const { t } = useI18n();

const client = ref<Client | null>(null);
const isLoading = ref(true);
const activeTab = ref('personal');

// Spec 64 + Spec 44 — display_name (MySQL column) is always first_last for persons — not format-aware.
// Persons must use full_name (backend accessor that respects tenant name_format).
const displayName = computed<string>(() => {
    const c = client.value;
    if (!c) return '';
    if (c.type === 'company') {
        return c.display_name || c.trade_name || c.company_name || `#${c.id}`;
    }
    return c.full_name || `${c.first_name ?? ''} ${c.last_name ?? ''}`.trim() || `#${c.id}`;
});

// Companion state
const companions = ref<Companion[]>([]);
const isLoadingCompanions = ref(false);
const showCompanionModal = ref(false);
const editingCompanion = ref<Companion | null>(null);
const presetRelationshipForModal = ref<RelationshipType | null>(null);
const showViewModal = ref(false);
const viewingCompanion = ref<Companion | null>(null);

// Spec 69 — hierarchical companions (company clients)
const expandedEmployees = ref(new Set<number>());
const employeeToAddFamily = ref<Companion | null>(null);
const editingFamilyMember = ref<Companion | null>(null);
const showFamilyMemberModal = ref(false);

// Legal documents state
const legalDocs = ref<LegalDocument[]>([]);
const isLoadingDocs = ref(false);
const docsLoaded = ref(false);

const loadLegalDocs = async () => {
    if (!client.value) return;
    isLoadingDocs.value = true;
    try {
        legalDocs.value = await legalDocumentService.getForClient(client.value.id);
        docsLoaded.value = true;
    } catch (err) {
        console.error('Failed to load legal documents:', err);
    } finally {
        isLoadingDocs.value = false;
    }
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
    const key = `clients.status_${status}`;
    const translated = t(key);
    return translated !== key ? translated : status.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
};

const getCaseBadgeClass = (status: string): string => {
    const classes: Record<string, string> = {
        active: 'badge-outline-success',
        pending: 'badge-outline-warning',
        closed: 'badge-outline-secondary',
    };
    return classes[status] || 'badge-outline-primary';
};

const getUrgencyBadgeClass = (status: string): string => {
    const classes: Record<string, string> = {
        urgent: 'badge-outline-danger',
        high: 'badge-outline-warning',
        medium: 'badge-outline-info',
        low: 'badge-outline-secondary',
    };
    return classes[status] || 'badge-outline-primary';
};

const formatRelationship = (relationship: string): string => {
    const key = `companions.${relationship}`;
    const translated = t(key);
    return translated !== key ? translated : relationship;
};

const loadEmployees = async () => {
    if (!client.value) return;
    isLoadingCompanions.value = true;
    try {
        // Employees only (tier=employees) + eager-load family members for the accordion
        companions.value = await companionService.list(client.value.id, {
            tier: 'employees',
            with_family: true,
            with_family_count: true,
        });
    } catch (err) {
        console.error('Failed to load employees:', err);
    } finally {
        isLoadingCompanions.value = false;
    }
};

const loadCompanions = async () => {
    if (!client.value) return;
    if (client.value.type === 'company') {
        return loadEmployees();
    }
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

const toggleEmployee = (id: number) => {
    if (expandedEmployees.value.has(id)) {
        expandedEmployees.value.delete(id);
    } else {
        expandedEmployees.value.add(id);
    }
};

const openFamilyMemberModal = (employee: Companion, familyMember?: Companion) => {
    employeeToAddFamily.value = employee;
    editingFamilyMember.value = familyMember ?? null;
    showFamilyMemberModal.value = true;
};

const onFamilyMemberSaved = async () => {
    showFamilyMemberModal.value = false;
    editingFamilyMember.value = null;
    employeeToAddFamily.value = null;
    await loadEmployees();
};

const confirmDeleteEmployee = async (companion: Companion) => {
    const result = await Swal.fire({
        icon: 'warning',
        title: t('companions.confirm_delete_employee_title'),
        text: t('companions.confirm_delete_employee_text'),
        showCancelButton: true,
        confirmButtonColor: '#e7515a',
        confirmButtonText: t('companions.delete'),
        cancelButtonText: t('companions.cancel'),
    });
    if (result.isConfirmed) {
        await confirmDeleteCompanion(companion);
    }
};

const openCompanionModal = (companion?: Companion) => {
    editingCompanion.value = companion ?? null;
    const isCompany = client.value?.type === 'company';
    // Lock to 'beneficiary' for: (a) new companions of company clients, and
    // (b) editing an existing beneficiary — the relationship is immutable.
    presetRelationshipForModal.value = isCompany && (!companion || companion.relationship === 'beneficiary')
        ? 'beneficiary'
        : null;
    showCompanionModal.value = true;
};

const openViewModal = (companion: Companion) => {
    viewingCompanion.value = companion;
    showViewModal.value = true;
};

const openCompanionModalFromView = (companion: Companion | null) => {
    showViewModal.value = false;
    viewingCompanion.value = null;
    if (companion) {
        editingCompanion.value = companion;
        const isCompany = client.value?.type === 'company';
        presetRelationshipForModal.value = isCompany && companion.relationship === 'beneficiary'
            ? 'beneficiary'
            : null;
        showCompanionModal.value = true;
    }
};

const onCompanionSaved = async () => {
    showCompanionModal.value = false;
    editingCompanion.value = null;
    await loadCompanions();
};

interface EligibilityResult {
    eligible: boolean;
    reasons: string[];
    alreadyPromoted: boolean;
}

const computeLocalEligibility = (c: Companion): EligibilityResult => {
    if (c.already_promoted) {
        return { eligible: false, reasons: [], alreadyPromoted: true };
    }
    const reasons: string[] = [];
    if (!c.first_name?.trim())  reasons.push('missing_first_name');
    if (!c.last_name?.trim())   reasons.push('missing_last_name');
    if (!c.email?.trim())       reasons.push('missing_email');
    if (!c.phone?.trim())       reasons.push('missing_phone');
    if (!c.date_of_birth) {
        reasons.push('missing_date_of_birth');
    } else if ((c.age ?? 0) < 18) {
        reasons.push('underage');
    }
    return { eligible: reasons.length === 0, reasons, alreadyPromoted: false };
};

const handlePromote = async (companion: Companion) => {
    try {
        const { data } = await companionPromotionService.checkEligibility(companion.id);
        if (!data.data.eligible) {
            const firstReason = data.data.reasons[0] ?? 'unknown';
            await Swal.fire({
                icon: 'warning',
                title: t('companion.promote.not_eligible_title'),
                text: t(`companion.promote.tooltip_${firstReason}`),
            });
            return;
        }
    } catch {
        return;
    }

    const { isConfirmed } = await Swal.fire({
        icon: 'question',
        title: t('companion.promote.modal.title', { fullName: companion.full_name }),
        html: `<p>${t('companion.promote.modal.body_html')}</p>
               <p class="mt-2 text-sm text-gray-500">${t('companion.promote.modal.fields_to_copy')}</p>`,
        showCancelButton: true,
        confirmButtonText: t('companion.promote.modal.confirm'),
        cancelButtonText:  t('companion.promote.modal.cancel'),
        confirmButtonColor: '#00ab55',
    });

    if (!isConfirmed) return;

    try {
        const { data } = await companionPromotionService.promote(companion.id);
        await Swal.fire({
            icon: 'success',
            title: t('companion.promote.success'),
            timer: 2000,
            showConfirmButton: false,
        });
        router.push({ name: 'clients-show', params: { id: data.data.id } });
    } catch (err: any) {
        const code = err.response?.data?.code ?? 'unknown';
        Swal.fire({
            icon: 'error',
            title: t('companion.promote.error_title'),
            text: t(`companion.promote.error.${code}`),
        });
    }
};

const confirmDeleteCompanion = async (companion: Companion) => {
    if (!client.value) return;

    const confirmed = await confirmDialog({
        title: t('companions.confirm_delete'),
        text: t('companions.delete_warning', { name: companion.full_name }),
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
        title: t('clients.confirm_convert', { name: displayName.value }),
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

const canDelete = computed(() => {
    if (!client.value) return false;
    const companionCount = client.value.companions_count ?? (client.value.companions?.length ?? 0);
    const caseCount = client.value.cases_count ?? (client.value.cases?.length ?? 0);
    return companionCount === 0 && caseCount === 0;
});

const confirmDeleteClient = async () => {
    if (!client.value) return;

    const companions = client.value.companions_count ?? 0;
    const cases = client.value.cases_count ?? 0;
    const warningText = (companions > 0 || cases > 0)
        ? t('clients.delete_warning_cascade', { companions, cases })
        : t('clients.delete_warning');

    const confirmed = await confirmDialog({
        title: t('clients.confirm_delete', { name: displayName.value }),
        text: warningText,
        icon: 'warning',
        confirmButtonText: t('clients.yes_delete'),
        cancelButtonText: t('clients.cancel'),
    });

    if (confirmed) {
        try {
            await clientStore.deleteClient(client.value.id);
            success(t('clients.moved_to_trash'));
            router.push('/clients');
        } catch (err: any) {
            error(err.response?.data?.message || t('clients.delete_failed'));
        }
    }
};

// Load companions when tab becomes active
watch(activeTab, (newTab) => {
    if (newTab === 'companions' && client.value && !companions.value.length) {
        loadCompanions();
    }
    if (newTab === 'documents' && client.value && !docsLoaded.value) {
        loadLegalDocs();
    }
});

// Spec 64 — Companies don't have a Canada Legal Status tab; redirect to personal/company info
watch(client, (newClient) => {
    if (newClient?.type === 'company' && activeTab.value === 'canada') {
        activeTab.value = 'personal';
    }
});

onMounted(async () => {
    try {
        const id = parseInt(route.params.id as string);
        client.value = await clientStore.fetchClient(id);
        // Preload companions if we might show them
        if (client.value) {
            loadCompanions();
            loadLegalDocs();
        }
    } catch (err) {
        error(t('clients.failed_to_load'));
    } finally {
        isLoading.value = false;
    }
});
</script>
