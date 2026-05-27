<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/cases" class="text-primary hover:underline">{{ $t('sidebar.cases') }}</router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ currentCase?.case_number || '...' }}</span>
            </li>
        </ul>

        <!-- Loading State -->
        <div v-if="isLoading" class="panel">
            <div class="animate-pulse space-y-4">
                <div class="h-8 w-48 bg-gray-200 dark:bg-gray-700 rounded"></div>
                <div class="h-4 w-full bg-gray-200 dark:bg-gray-700 rounded"></div>
                <div class="h-4 w-3/4 bg-gray-200 dark:bg-gray-700 rounded"></div>
            </div>
        </div>

        <!-- Case Details -->
        <div v-else-if="currentCase" class="space-y-5">
            <!-- Header Card -->
            <div class="panel">
                <h2
                    class="text-2xl font-bold dark:text-white-light cursor-default select-none"
                    @click="irccActivation.onTriggerClick()"
                >{{ currentCase.case_number }}</h2>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-4 p-4 border border-gray-200 dark:border-gray-700 rounded-lg mt-4">
                            <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                                <span class="text-lg font-semibold text-primary">
                                    {{ primaryApplicant?.initials || getInitials(primaryApplicant?.first_name ?? '', primaryApplicant?.last_name ?? '') }}
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-xl text-gray-900 dark:text-white truncate">
                                    {{ primaryApplicant?.full_name }}
                                    <span class="badge badge-outline-primary text-xs ml-2 align-middle font-normal">
                                        {{ $t('cases.primary_applicant') }}
                                    </span>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ $t('cases.client') }}:
                                    <router-link :to="`/clients/${currentCase.client.id}`" class="text-primary hover:underline">
                                        {{ currentCase.client.full_name }}
                                    </router-link>
                                    <span v-if="currentCase.primary_applicant_type === 'client'" class="text-xs text-gray-400 ml-1">
                                        ({{ $t('cases.client_is_applicant') }})
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <ul class="list-none space-y-2 text-sm w-full sm:w-auto">
                            <li v-if="currentCase.case_type" class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-x-4 py-0"
                            >
                                <span class="text-gray-500 shrink-0 sm:w-30 dark:text-white-light">{{ $t('cases.case_type') }}:</span>
                                <span class="text-gray-500 min-w-0 sm:flex-1">{{ $t(`case_types.${currentCase.case_type.name}`) }}</span>
                            </li>
                            <li
                                v-if="currentCase.created_at" class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-x-4 py-0"
                            >
                                <span class="text-gray-500 shrink-0 sm:w-30 dark:text-white-light">{{ $t('cases.created_at') }}:</span>
                                <span class="text-gray-500 min-w-0 sm:flex-1">{{ formatDateTime(currentCase.created_at) }}</span>
                            </li>
                            <li
                                v-if="currentCase.updated_at" class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-x-4 py-0"
                            >
                                <span class="text-gray-500 shrink-0 sm:w-30 dark:text-white-light">{{ $t('cases.updated_at') }}:</span>
                                <span class="text-gray-500 min-w-0 sm:flex-1">{{ formatDateTime(currentCase.updated_at) }}</span>
                            </li>
                            <li
                                v-if="currentCase.assigned_user" class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-x-4 py-0"
                            >
                                <span class="text-gray-500 shrink-0 sm:w-30 dark:text-white-light">{{ $t('cases.assigned_to') }}:</span>
                                <span class="text-gray-500 min-w-0 sm:flex-1">{{ currentCase.assigned_user?.name }}</span>
                            </li>
                            <!-- Language -->
                            <li v-if="currentCase.language" class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-x-4 py-0">
                                <span class="text-gray-500 shrink-0 sm:w-30 dark:text-white-light">{{ $t('cases.language') }}:</span>
                                <span class="text-gray-500 min-w-0 sm:flex-1">{{ $t(`clients.${currentCase.language}`) || '-' }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <ul class="list-none space-y-0 text-sm w-full sm:w-auto">

                            <!-- Stage (workflow current_stage takes precedence; legacy `stage` only as fallback) -->
                            <li v-if="currentCase.current_stage" class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-x-4 py-0">
                                <span class="text-gray-500 shrink-0 sm:w-30 dark:text-white-light">{{ $t('cases.stage') }}:</span>
                                <span :class="`badge badge-outline-${currentCase.current_stage.color || 'primary'} shrink-0`">
                                    {{ resolveStageName(currentCase.current_stage.translations) }}
                                </span>
                            </li>
                            <li v-else-if="currentCase.stage" class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-x-4 py-0">
                                <span class="text-gray-500 shrink-0 sm:w-30 dark:text-white-light">{{ $t('cases.stage') }}:</span>
                                <span :class="`badge badge-outline-${stageColor} shrink-0`">{{
                                    $t(CASE_STAGE_OPTIONS.find(o => o.value === currentCase.stage)?.label ?? currentCase.stage ?? '')
                                }}</span>
                            </li>
                            <!-- IRCC Status -->
                            <li v-if="currentCase.ircc_status" class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-x-4 py-0">
                                <span class="text-gray-500 shrink-0 sm:w-30 dark:text-white-light">{{ $t('cases.ircc_status') }}:</span>
                                <span :class="`badge badge-outline-${irccColor} shrink-0`">{{ currentCase.ircc_status_label }}</span>
                            </li>
                            <!-- Final Result -->
                            <li v-if="currentCase.final_result" class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-x-4 py-0">
                                <span class="text-gray-500 shrink-0 sm:w-30 dark:text-white-light">{{ $t('cases.final_result') }}:</span>
                                <span :class="`badge badge-outline-${finalResultColor} shrink-0`">{{ currentCase.final_result_label }}</span>
                            </li>
                            <!-- IRCC Code -->
                            <li v-if="currentCase.ircc_code" class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-x-4 py-0">
                                <span class="text-gray-500 shrink-0 sm:w-30 dark:text-white-light">{{ $t('cases.ircc_code') }}:</span>
                                <span class="text-sm font-mono font-medium min-w-0 sm:flex-1 break-all">{{ currentCase.ircc_code }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge rounded-full" :class="getStatusBadgeClass(currentCase.status)">
                            {{ $t(`cases.${currentCase.status}`) }}
                        </span>
                        <span class="badge rounded-full" :class="getPriorityBadgeClass(currentCase.priority)">
                            {{ $t(`cases.${currentCase.priority}`) }}
                        </span>
                    </div>

                    <!-- Time tracking summary -->
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex items-center gap-2 px-3 py-1.5 rounded-md border border-[#e0e6ed] dark:border-[#1b2e4b] bg-white-light/40 dark:bg-[#0e1726]">
                            <span class="text-xs text-gray-500 dark:text-white-light">{{ $t('timesheet.total_time') }}:</span>
                            <TimeDisplay
                                :seconds="currentCase.total_time_spent_seconds ?? 0"
                                :is-running="!!timesheetStore.activeTimer && timesheetStore.activeTimer.case_id === currentCase.id"
                                size="md"
                            />
                        </div>
                        <button
                            v-can="'time_logs.create'"
                            type="button"
                            class="btn btn-outline-primary gap-1 btn-sm"
                            @click="showTimeModal = true"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" />
                                <polyline points="12 6 12 12 16 14" />
                            </svg>
                            {{ $t('timesheet.log_time') }}
                        </button>
                    </div>
                </div>

                <div class="flex flex-row flex-nowrap items-center gap-4 mt-4 min-w-0">
                    <div class="min-w-0 flex-[1] flex flex-col justify-center">
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-gray-500">{{ $t('cases.progress') }}</span>
                            <span class="text-gray-500">{{ currentCase.progress }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                            <div class="h-3 rounded-full transition-all" :class="getProgressBarClass(currentCase.progress)" :style="{ width: `${currentCase.progress}%` }"></div>
                        </div>
                    </div>
                    <div class="flex-[1] flex flex-nowrap justify-end gap-2 items-center min-w-0">
                        <button
                            v-can="'cases.update'"
                            v-if="currentCase.current_stage_id || currentCase.current_case_stage_id || (currentCase.workflow_snapshot?.stages?.length ?? 0) > 0 || caseStagesStore.orderedStages.length > 0"
                            type="button"
                            class="btn btn-success gap-2 btn-sm"
                            :disabled="isAdvancingStage"
                            @click="confirmAdvanceStage"
                        >
                            <icon-arrow-forward class="w-4 h-4" />
                            {{ $t('workflow.advance_stage') }}
                        </button>
                        <router-link v-can="'cases.update'" :to="`/cases/${currentCase.id}/edit`" class="btn btn-primary gap-2 btn-sm">
                            <icon-pencil class="w-4 h-4" />
                            {{ $t('cases.edit') }}
                        </router-link>
                        <button v-can="'cases.assign'" type="button" class="btn btn-warning gap-2 btn-sm" @click="openAssignDialog">
                            <icon-user-plus class="w-4 h-4" />
                            {{ $t('cases.assign') }}
                        </button>
                        <button v-can="'cases.delete'" type="button" class="btn btn-danger gap-2 btn-sm" @click="confirmDelete">
                            <icon-trash-lines class="w-4 h-4" />
                            {{ $t('cases.delete') }}
                        </button>
                        <router-link v-can="'cases.assign'" :to="`/clients/${currentCase.client.id}`" class="btn btn-secondary gap-2 btn-sm">
                            <icon-pencil class="w-4 h-4" />
                            {{ $t('cases.client') }}
                        </router-link>
                    </div>
                </div>
            </div>

            <!-- Tabs Panel -->
            <div class="panel p-0">
                <!-- Tabs Navigation -->
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="flex -mb-px">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            type="button"
                            class="px-6 py-4 text-sm font-medium border-b-2 transition-colors"
                            :class="activeTab === tab.id
                                ? 'border-primary text-primary'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            @click="activeTab = tab.id"
                        >
                            {{ $t(tab.label) }}
                        </button>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="p-5">
                    <!-- Information Tab -->
                    <div v-if="activeTab === 'info'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- General Information -->
                        <div class="space-y-6">
                            <!-- Información del Cliente -->
                            <div v-if="currentCase.client">
                                <h3 class="font-semibold text-lg dark:text-white-light mb-3">{{ $t('cases.client_info') }}</h3>
                                <div class="flex items-center gap-4 p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                                    <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                                        <span class="text-lg font-semibold text-primary">
                                            {{ currentCase.client.initials || getInitials(currentCase.client.first_name, currentCase.client.last_name) }}
                                        </span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-gray-900 dark:text-white">{{ currentCase.client.full_name }}</div>
                                        <div v-if="currentCase.client.email" class="text-sm text-gray-500">{{ currentCase.client.email }}</div>
                                        <div v-if="currentCase.client.phone" class="text-sm text-gray-500">{{ currentCase.client.phone }}</div>
                                    </div>
                                    <router-link :to="`/clients/${currentCase.client.id}`" class="btn btn-sm btn-outline-primary shrink-0">
                                        {{ $t('cases.view_client') }}
                                    </router-link>
                                </div>
                            </div>

                            <!-- Aplicante Principal (only when a companion heads the application) -->
                            <div v-if="currentCase.primary_applicant_type === 'companion' && primaryApplicantCompanion">
                                <h3 class="font-semibold text-lg dark:text-white-light mb-3">{{ $t('cases.primary_applicant') }}</h3>
                                <div class="flex items-center gap-4 p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                                    <div class="w-12 h-12 rounded-full bg-secondary/10 flex items-center justify-center shrink-0">
                                        <span class="text-lg font-semibold text-secondary">
                                            {{ primaryApplicantCompanion.initials || getInitials(primaryApplicantCompanion.first_name, primaryApplicantCompanion.last_name) }}
                                        </span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-gray-900 dark:text-white">{{ primaryApplicantCompanion.full_name }}</div>
                                        <div class="flex flex-wrap items-center gap-2 mt-1">
                                            <span class="badge badge-outline-secondary text-xs">
                                                {{ isCompanyCase ? $t('cases.beneficiary_employee') : (primaryApplicantCompanion.relationship_label || primaryApplicantCompanion.relationship) }}
                                            </span>
                                            <span v-if="primaryApplicantCompanion.age" class="text-xs text-gray-500">
                                                · {{ $t('cases.companion_age', { age: primaryApplicantCompanion.age }) }}
                                            </span>
                                            <span v-if="primaryApplicantCompanion.nationality" class="text-xs text-gray-500">
                                                · {{ primaryApplicantCompanion.nationality }}
                                            </span>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-info p-1.5 shrink-0"
                                        :title="$t('companions.view_companion')"
                                        @click="openCompanionView(primaryApplicantCompanion)"
                                    >
                                        <icon-eye class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>

                            <!-- Dependientes -->
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="font-semibold text-lg dark:text-white-light">
                                        {{ isCompanyCase ? $t('cases.included_family') : $t('cases.companions') }}
                                    </h3>
                                    <span v-if="dependentsCount > 0" class="badge badge-outline-secondary">
                                        {{ dependentsCount }}
                                    </span>
                                </div>

                                <p v-if="dependentsCount === 0" class="text-sm text-gray-500 italic">
                                    {{ isCompanyCase ? $t('cases.no_family_included') : $t('cases.no_companions') }}
                                </p>

                                <div v-else class="space-y-3">
                                    <!-- Client as dependent (only when companion is primary, client is a person, AND was marked as participant) -->
                                    <div
                                        v-if="currentCase.primary_applicant_type === 'companion' && currentCase.client_is_participant && currentCase.client && currentCase.client.type !== 'company'"
                                        class="flex items-center gap-4 p-4 border border-gray-200 dark:border-gray-700 rounded-lg"
                                    >
                                        <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                                            <span class="text-lg font-semibold text-primary">
                                                {{ currentCase.client.initials || getInitials(currentCase.client.first_name, currentCase.client.last_name) }}
                                            </span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="font-semibold text-gray-900 dark:text-white truncate">{{ currentCase.client.full_name }}</div>
                                            <div class="mt-1">
                                                <span class="badge badge-outline-primary text-xs">{{ $t('wizard.step3.client_label') }}</span>
                                            </div>
                                        </div>
                                        <router-link
                                            :to="`/clients/${currentCase.client.id}`"
                                            class="btn btn-sm btn-outline-info p-1.5 shrink-0"
                                            :title="$t('cases.view_client')"
                                        >
                                            <icon-eye class="w-4 h-4" />
                                        </router-link>
                                    </div>

                                    <!-- Companion dependents (excludes the primary applicant companion) -->
                                    <div
                                        v-for="companion in dependentCompanions"
                                        :key="companion.id"
                                        class="flex items-center gap-4 p-4 border border-gray-200 dark:border-gray-700 rounded-lg"
                                    >
                                        <div class="w-12 h-12 rounded-full bg-secondary/10 flex items-center justify-center shrink-0">
                                            <span class="text-lg font-semibold text-secondary">
                                                {{ companion.initials || getInitials(companion.first_name, companion.last_name) }}
                                            </span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="font-semibold text-gray-900 dark:text-white truncate">{{ companion.full_name }}</div>
                                            <div class="flex flex-wrap items-center gap-2 mt-1">
                                                <span class="badge badge-outline-secondary text-xs">
                                                    {{ companion.relationship_label || companion.relationship }}
                                                </span>
                                                <span v-if="companion.age" class="text-xs text-gray-500">
                                                    · {{ $t('cases.companion_age', { age: companion.age }) }}
                                                </span>
                                                <span v-if="companion.nationality" class="text-xs text-gray-500">
                                                    · {{ companion.nationality }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1 shrink-0">
                                            <router-link
                                                v-if="companion.already_promoted && companion.promoted_to_client_id"
                                                :to="{ name: 'clients-show', params: { id: companion.promoted_to_client_id } }"
                                                class="btn btn-sm btn-outline-info p-1.5"
                                                :title="$t('companion.promote.tooltip_already_promoted')"
                                            >
                                                <icon-user-plus class="w-4 h-4 text-info" />
                                            </router-link>

                                            <button
                                                v-else-if="computeLocalEligibility(companion).eligible"
                                                v-can="'clients.promote_from_companion'"
                                                type="button"
                                                class="btn btn-sm btn-outline-success p-1.5"
                                                :title="$t('companion.promote.tooltip_eligible')"
                                                @click="handlePromote(companion)"
                                            >
                                                <icon-user-plus class="w-4 h-4" />
                                            </button>

                                            <button
                                                v-else-if="!companion.already_promoted"
                                                v-can="'clients.promote_from_companion'"
                                                type="button"
                                                class="btn btn-sm p-1.5 opacity-40 cursor-not-allowed"
                                                :title="$t('companion.promote.tooltip_' + (computeLocalEligibility(companion).reasons[0] ?? 'missing_fields'))"
                                                disabled
                                            >
                                                <icon-user-plus class="w-4 h-4 text-gray-400" />
                                            </button>

                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-info p-1.5"
                                                :title="$t('companions.view_companion')"
                                                @click="openCompanionView(companion)"
                                            >
                                                <icon-eye class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Descripción -->
                            <div v-if="currentCase.description">
                                <h3 class="font-semibold text-lg dark:text-white-light mb-2">{{ $t('cases.description') }}</h3>
                                <p class="text-gray-600 dark:text-gray-400 whitespace-pre-wrap">{{ currentCase.description }}</p>
                            </div>
                        </div>

                        <!-- Client & Dates -->
                        <div class="space-y-6">
                            <!-- Important Dates -->
                            <div class="space-y-4">
                                <h3 class="font-semibold text-lg dark:text-white-light">{{ $t('cases.important_dates') }}</h3>
                                <DateManager
                                    :model-value="currentCase.important_dates ?? []"
                                    :readonly="true"
                                    :show-quick-event="true"
                                    @quick-event="createQuickEvent"
                                />
                            </div>

                            <!-- Assignment -->
                            <!-- <div class="space-y-4">
                                <h3 class="font-semibold text-lg dark:text-white-light">{{ $t('cases.assignment') }}</h3>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">{{ $t('cases.assigned_to') }}</span>
                                    <span v-if="currentCase.assigned_user">{{ currentCase.assigned_user.name }}</span>
                                    <span v-else class="text-gray-400 italic">{{ $t('cases.unassigned') }}</span>
                                </div>
                            </div> -->
                           
                            <!-- Financial Info -->
                            <div v-if="currentCase.service_type" class="mt-4 pt-4 border-t border-[#e0e6ed] dark:border-[#1b2e4b]">
                                <h3 class="font-semibold text-lg dark:text-white-light">{{ $t('cases.financial_info') }}</h3>
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between py-1">
                                        <span class="text-sm text-gray-500">{{ $t('cases.service_type') }}</span>
                                        <span class="badge" :class="currentCase.service_type === 'pro_bono' ? 'badge-outline-success' : 'badge-outline-primary'">
                                            {{ currentCase.service_type_label }}
                                        </span>
                                    </div>
                                    <div v-if="currentCase.contract_number" class="flex items-center justify-between py-1">
                                        <span class="text-sm text-gray-500">{{ $t('cases.contract_number') }}</span>
                                        <span class="text-sm font-medium">{{ currentCase.contract_number }}</span>
                                    </div>
                                    <div v-if="currentCase.fees !== undefined && currentCase.fees !== null" class="flex items-center justify-between py-1">
                                        <span class="text-sm text-gray-500">{{ $t('cases.fees') }}</span>
                                        <span class="text-sm font-semibold text-success">${{ Number(currentCase.fees).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Lifecycle Tab -->
                    <div v-else-if="activeTab === 'lifecycle'">
                        <!-- Roadmap: workflow-snapshot based (standard cases) -->
                        <StageProgressBar
                            v-if="currentCase.workflow_snapshot?.stages?.length"
                            :snapshot="currentCase.workflow_snapshot"
                            :current-stage-id="currentCase.current_stage_id ?? null"
                        />
                        <!-- Roadmap: ad-hoc case stages -->
                        <StageProgressBar
                            v-else-if="caseStagesStore.orderedStages.length"
                            :snapshot="null"
                            :stages-override="caseStagesStore.orderedStages"
                            :current-stage-id="currentCase.current_case_stage_id ?? null"
                        />
                        <WorkflowTaskChecklist
                            :tasks="currentCase.tasks ?? []"
                            :case-id="currentCase.id"
                            :ad-hoc-stages="currentCase.workflow_snapshot ? undefined : caseStagesStore.orderedStages"
                            @progress-updated="(p: number) => { if (currentCase) currentCase.progress = p }"
                            @task-toggled="onTaskToggled"
                        />
                    </div>

                    <!-- Timeline Tab -->
                    <div v-else-if="activeTab === 'timeline'">
                        <div v-if="caseStore.isLoadingTimeline" class="text-center py-8">
                            <span class="animate-spin border-2 border-primary border-l-transparent rounded-full w-8 h-8 inline-block"></span>
                        </div>
                        <div v-else-if="caseStore.timeline.length === 0" class="text-center py-8 text-gray-500">
                            {{ $t('cases.timeline_empty') }}
                        </div>
                        <div v-else class="space-y-4">
                            <div v-for="activity in caseStore.timeline" :key="activity.id" class="flex gap-4">
                                <div class="w-2 h-2 rounded-full bg-primary mt-2 shrink-0"></div>
                                <div class="flex-1 pb-4 border-b border-gray-100 dark:border-gray-700 last:border-0">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-medium">{{ activity.description }}</p>
                                            <p v-if="activity.causer" class="text-sm text-gray-500">{{ $t('cases.by') }} {{ activity.causer.name }}</p>
                                        </div>
                                        <span class="text-xs text-gray-400">{{ formatDateTime(activity.created_at) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Invoices / Account Statement Tab -->
                    <div v-else-if="activeTab === 'invoices'">
                        <InvoiceTable
                            :invoices="currentCase.invoices ?? []"
                            :financial-summary="currentCase.financial_summary ?? null"
                            :case-id="currentCase.id"
                            @saved="() => caseStore.fetchCase(currentCase!.id)"
                        />
                    </div>

                    <!-- Todos Tab -->
                    <div v-else-if="activeTab === 'todos'">
                        <CaseTodoTab
                            :case-id="currentCase.id"
                            :case-number="currentCase.case_number"
                            @core-status-changed="caseStore.fetchCase(currentCase.id)"
                        />
                    </div>

                    <!-- Events Tab -->
                    <div v-else-if="activeTab === 'events'">
                        <CaseEventTab
                            :case-id="currentCase.id"
                            :case-number="currentCase.case_number"
                            :client-name="currentCase.client?.full_name ?? ''"
                        />
                    </div>

                    <!-- Documents Tab -->
                    <CaseDocumentsTab v-else-if="activeTab === 'documents'" :case-id="currentCase.id" />

                    <!-- Time Tab -->
                    <div v-else-if="activeTab === 'time'">
                        <TimeLogList :case-id="currentCase.id" />
                    </div>

                    <!-- IRCC Credentials Tab (Spec 60 — hidden, triple-click activated) -->
                    <IrccCredentialsTab
                        v-else-if="activeTab === 'ircc'"
                        :case-id="currentCase.id"
                    />
                </div>
            </div>

            <!-- Metadata -->
            <div class="panel">
                <div class="flex flex-wrap gap-6 text-sm text-gray-500">
                    <div>
                        <span class="font-medium">{{ $t('clients.created') }}:</span>
                        {{ formatDate(currentCase.created_at) }}
                    </div>
                    <div>
                        <span class="font-medium">{{ $t('clients.updated') }}:</span>
                        {{ formatDate(currentCase.updated_at) }}
                    </div>
                    <div>
                        <span class="font-medium">ID:</span>
                        #{{ currentCase.id }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Not Found -->
        <div v-else class="panel text-center py-10">
            <icon-folder class="w-16 h-16 mx-auto text-gray-300 mb-4" />
            <h3 class="text-lg font-semibold text-gray-600 mb-2">{{ $t('cases.not_found') }}</h3>
            <router-link to="/cases" class="btn btn-primary mt-4">{{ $t('cases.back_to_list') }}</router-link>
        </div>

        <!-- Companion View Modal -->
        <CompanionViewModal
            :show="showCompanionView"
            :companion="viewingCompanion"
            :can-edit="false"
            @close="showCompanionView = false; viewingCompanion = null"
        />

        <!-- Timesheet Modal -->
        <TimeLogModal
            v-if="currentCase"
            :is-open="showTimeModal"
            :case-id="currentCase.id"
            :todos="timeLogTodoOptions"
            @close="showTimeModal = false"
            @logged="onTimeLogged"
        />
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useMeta } from '@/composables/use-meta';
import Swal from 'sweetalert2';
import { useCaseStore } from '@/stores/case';
import { useTodoStore } from '@/stores/todo';
import { useNotification } from '@/composables/useNotification';
import userService from '@/services/userService';
import { formatDate } from '@/utils/formatters';
import type { CaseStatus, CasePriority, ImportantDate } from '@/types/case';
import { CASE_STAGE_OPTIONS, IRCC_STATUS_OPTIONS, FINAL_RESULT_OPTIONS, SERVICE_TYPE_OPTIONS } from '@/types/case';
import api from '@/services/api';
import DateManager from '@/components/DateManager.vue';
import WorkflowTaskChecklist from '@/components/WorkflowTaskChecklist.vue';
import InvoiceTable from '@/views/cases/components/InvoiceTable.vue';
import CaseTodoTab from '@/views/cases/components/CaseTodoTab.vue';
import CaseEventTab from '@/views/cases/components/CaseEventTab.vue';
import CaseDocumentsTab from '@/views/cases/components/CaseDocumentsTab.vue';
import StageProgressBar from '@/components/cases/StageProgressBar.vue';
import { workflowService } from '@/services/workflowService';
import type { TranslationsByField, WorkflowTask } from '@/types/workflow';
import { useCaseStagesStore } from '@/stores/useCaseStagesStore';

// Icons
import IconFolder from '@/components/icon/icon-folder.vue';
import IconPencil from '@/components/icon/icon-pencil.vue';
import IconTrashLines from '@/components/icon/icon-trash-lines.vue';
import IconUserPlus from '@/components/icon/icon-user-plus.vue';
import IconArrowForward from '@/components/icon/icon-arrow-forward.vue';
import IconEye from '@/components/icon/icon-eye.vue';
import CompanionViewModal from '@/components/companions/CompanionViewModal.vue';
import type { Companion } from '@/types/companion';
import companionPromotionService from '@/services/companionPromotionService';

// Timesheet
import { useTimesheetStore } from '@/stores/useTimesheetStore';
import TimeDisplay from '@/components/timesheet/TimeDisplay.vue';
import TimeLogModal from '@/components/timesheet/TimeLogModal.vue';
import TimeLogList from '@/components/timesheet/TimeLogList.vue';
import type { TimeLog } from '@/types/timesheet';

// IRCC (Spec 60) — hidden tab activated by triple-click
import { onBeforeRouteLeave } from 'vue-router';
import { useExtendedTab } from '@/composables/useIrccActivation';
import { useIrccStore } from '@/stores/useIrccStore';
import IrccCredentialsTab from './IrccCredentialsTab.vue';

useMeta({ title: 'Case Details' });

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const caseStore = useCaseStore();
const todoStore = useTodoStore();
const caseStagesStore = useCaseStagesStore();
const { confirm: confirmDialog, success, error } = useNotification();

const isLoading = ref(true);
const activeTab = ref('info');
const isAdvancingStage = ref(false);

// Timesheet state (definitions that depend on currentCase live below the currentCase computed)
const timesheetStore = useTimesheetStore();
const showTimeModal = ref(false);

function resolveStageName(translations: TranslationsByField | undefined): string {
    if (!translations?.name) return '';
    const loc = (currentCase.value?.language ?? 'es') as 'es' | 'en' | 'fr';
    return translations.name[loc] ?? translations.name.es ?? Object.values(translations.name)[0] ?? '';
}

async function confirmAdvanceStage() {
    if (!currentCase.value) return;
    const ok = await confirmDialog({
        title: t('workflow.confirm_advance_title'),
        text: t('workflow.confirm_advance_text'),
        icon: 'question',
    });
    if (!ok) return;
    isAdvancingStage.value = true;
    try {
        await workflowService.advanceStage(currentCase.value.id);
        success(t('workflow.stage_advanced'));
        await caseStore.fetchCase(currentCase.value.id);
    } catch (e: any) {
        const code = e?.response?.data?.code;
        if (code === 'STAGE_BLOCKED') {
            error(t('workflow.stage_blocked'));
        } else {
            error(e?.response?.data?.message || t('workflow.stage_advance_failed'));
        }
    } finally {
        isAdvancingStage.value = false;
    }
}

function onTaskToggled(updatedTask: WorkflowTask) {
    if (currentCase.value?.tasks) {
        const idx = currentCase.value.tasks.findIndex(t => t.id === updatedTask.id);
        if (idx !== -1) Object.assign(currentCase.value.tasks[idx], updatedTask);
    }
    if (updatedTask.task_template_id && currentCase.value) {
        todoStore.fetchTodos({ case_id: currentCase.value.id });
    }
}

// Companion view modal state
const showCompanionView = ref(false);
const viewingCompanion = ref<Companion | null>(null);

const openCompanionView = (companion: Companion) => {
    viewingCompanion.value = companion;
    showCompanionView.value = true;
};

interface EligibilityResult {
    eligible: boolean;
    reasons: string[];
    alreadyPromoted: boolean;
}

interface PromotableCompanion {
    id: number;
    first_name: string;
    last_name: string;
    full_name?: string;
    email?: string | null;
    phone?: string | null;
    date_of_birth?: string | null;
    age?: number | null;
    already_promoted?: boolean;
    promoted_to_client_id?: number | null;
}

const computeLocalEligibility = (c: PromotableCompanion): EligibilityResult => {
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

const handlePromote = async (companion: PromotableCompanion) => {
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
        title: t('companion.promote.modal.title', { fullName: companion.full_name ?? `${companion.first_name} ${companion.last_name}` }),
        html: `<p>${t('companion.promote.modal.body_html')}</p>
               <p class="mt-2 text-sm text-gray-500">${t('companion.promote.modal.fields_to_copy')}</p>`,
        showCancelButton: true,
        confirmButtonText: t('companion.promote.modal.confirm'),
        cancelButtonText: t('companion.promote.modal.cancel'),
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

const currentCase = computed(() => caseStore.currentCase);

// IRCC (Spec 60) — hidden tab activated by triple-click on case number
const irccTabCaseId = computed(() => currentCase.value?.id);
const irccActivation = useExtendedTab(irccTabCaseId);
const irccStore = useIrccStore();

onBeforeRouteLeave(() => {
    irccStore.clear();
    irccActivation.deactivate();
});

const tabs = computed(() => {
    const baseTabs = [
        { id: 'info', label: 'cases.tab_information' },
        { id: 'lifecycle', label: 'cases.tab_lifecycle' },
        { id: 'documents', label: 'cases.tab_documents' },
        { id: 'events', label: 'cases.tab_events' },
        { id: 'todos', label: 'cases.tab_todos' },
        { id: 'invoices', label: 'cases.tab_invoices' },
        { id: 'time', label: 'timesheet.time_history' },
        { id: 'timeline', label: 'cases.tab_timeline' },
    ];
    if (irccActivation.visible.value) {
        baseTabs.push({ id: 'ircc', label: 'ircc.tab_title' });
    }
    return baseTabs;
});

const primaryApplicant = computed(() => {
    if (!currentCase.value) return null;
    if (currentCase.value.primary_applicant_type === 'companion' && currentCase.value.primary_applicant) {
        return currentCase.value.primary_applicant;
    }
    return currentCase.value.client;
});

// Companion who heads the application (null when client is the primary applicant)
const primaryApplicantCompanion = computed(() => {
    if (!currentCase.value || currentCase.value.primary_applicant_type !== 'companion') return null;
    return currentCase.value.companions?.find(
        c => c.id === currentCase.value!.primary_applicant_companion_id
    ) ?? null;
});

// Spec 69 — Company-case helpers: the beneficiary employee is the case's primary
// applicant; included family members are direct children (parent_companion_id).
const isCompanyCase = computed<boolean>(() => currentCase.value?.client?.type === 'company');

const primaryEmployee = computed(() => {
    if (!isCompanyCase.value) return null;
    return currentCase.value?.companions?.find(
        (c: Companion) => c.id === currentCase.value?.primary_applicant_companion_id
    ) ?? null;
});

const includedFamily = computed<Companion[]>(() => {
    if (!isCompanyCase.value) return [];
    return currentCase.value?.companions?.filter(
        (c: Companion) => c.parent_companion_id === currentCase.value?.primary_applicant_companion_id
    ) ?? [];
});

// All dependents: companions minus the primary-applicant companion
const dependentCompanions = computed(() => {
    if (!currentCase.value?.companions) return [];
    if (currentCase.value.primary_applicant_type !== 'companion') return currentCase.value.companions;
    return currentCase.value.companions.filter(c => c.id !== currentCase.value!.primary_applicant_companion_id);
});

// Total count of dependents shown (client counts only when companion is primary, client is a person, AND client_is_participant)
const dependentsCount = computed(() => {
    if (!currentCase.value) return 0;
    const clientAsDependent =
        currentCase.value.primary_applicant_type === 'companion'
        && currentCase.value.client_is_participant
        && currentCase.value.client?.type !== 'company' ? 1 : 0;
    return dependentCompanions.value.length + clientAsDependent;
});

// Build the task list passed to the time-log modal so the user can attribute time
// to a specific workflow task.
const timeLogTodoOptions = computed(() => {
    const caseId = currentCase.value?.id;
    if (!caseId) return [];
    return todoStore.todos
        .filter((todo) => Number(todo.case_id) === Number(caseId))
        .map((todo) => ({ id: todo.id, title: todo.title }));
});

function onTimeLogged(log: TimeLog) {
    if (currentCase.value) {
        const prev = Number(currentCase.value.total_time_spent_seconds ?? 0);
        currentCase.value.total_time_spent_seconds = prev + (log.duration_seconds ?? 0);
    }
}

// Helper functions
const getInitials = (firstName: string, lastName: string): string => {
    return ((firstName?.[0] || '') + (lastName?.[0] || '')).toUpperCase();
};

const getStatusBadgeClass = (status: CaseStatus): string => {
    const classes: Record<CaseStatus, string> = {
        active: 'badge-outline-success',
        inactive: 'badge-outline-warning',
        archived: 'badge-outline-secondary',
        closed: 'badge-outline-dark',
    };
    return classes[status] || 'badge-outline-primary';
};

const getPriorityBadgeClass = (priority: CasePriority): string => {
    const classes: Record<CasePriority, string> = {
        urgent: 'badge-outline-danger',
        high: 'badge-outline-warning',
        medium: 'badge-outline-info',
        low: 'badge-outline-secondary',
    };
    return classes[priority] || 'badge-outline-primary';
};

const getProgressBarClass = (progress: number): string => {
    if (progress >= 75) return 'bg-success';
    if (progress >= 50) return 'bg-info';
    if (progress >= 25) return 'bg-warning';
    return 'bg-danger';
};

const stageColor = computed(() => CASE_STAGE_OPTIONS.find(o => o.value === currentCase.value?.stage)?.color ?? 'dark');
const irccColor = computed(() => IRCC_STATUS_OPTIONS.find(o => o.value === currentCase.value?.ircc_status)?.color ?? 'dark');
const finalResultColor = computed(() => FINAL_RESULT_OPTIONS.find(o => o.value === currentCase.value?.final_result)?.color ?? 'dark');

const formatDateTime = (date: string): string => {
    return new Date(date).toLocaleString();
};

const openAssignDialog = async () => {
    if (!currentCase.value) return;

    try {
        const staff = await userService.getStaff(currentCase.value.assigned_user?.id);
        const options: Record<string, string> = {};
        for (const member of staff) {
            options[member.id.toString()] = member.name;
        }

        const { value: userId } = await Swal.fire({
            title: t('cases.assign'),
            input: 'select',
            inputOptions: options,
            inputValue: currentCase.value.assigned_user?.id?.toString() || '',
            inputPlaceholder: t('cases.unassigned'),
            showCancelButton: true,
            confirmButtonText: t('cases.assign'),
            cancelButtonText: t('cases.cancel'),
        });

        if (userId) {
            await caseStore.assignCase(currentCase.value.id, parseInt(userId));
            success(t('cases.assigned_successfully'));
            await caseStore.fetchCase(currentCase.value.id);
        }
    } catch (err: any) {
        error(err.response?.data?.message || t('cases.assign_failed'));
    }
};

const confirmDelete = async () => {
    if (!currentCase.value) return;

    const confirmed = await confirmDialog({
        title: t('cases.confirm_delete', { number: currentCase.value.case_number }),
        text: t('cases.delete_warning'),
        icon: 'warning',
        confirmButtonText: t('cases.yes_delete'),
        cancelButtonText: t('cases.cancel'),
    });

    if (confirmed) {
        try {
            await caseStore.deleteCase(currentCase.value.id);
            success(t('cases.moved_to_trash'));
            router.push('/cases');
        } catch (err: any) {
            error(err.response?.data?.message || t('cases.delete_failed'));
        }
    }
};

// Watch for tab changes to load timeline or ad-hoc stages
watch(activeTab, async (newTab) => {
    if (newTab === 'timeline' && currentCase.value) {
        await caseStore.fetchTimeline(currentCase.value.id);
    }
    if (newTab === 'lifecycle' && currentCase.value && !currentCase.value.workflow_snapshot) {
        await caseStagesStore.load(currentCase.value.id);
    }
});

// Quick event from important date
const isCreatingEvent = ref(false);
async function createQuickEvent(date: ImportantDate) {
    if (!date.due_date || isCreatingEvent.value || !currentCase.value) return;
    isCreatingEvent.value = true;
    try {
        await api.post('/events', {
            title: `${date.label} - ${currentCase.value.case_number}`,
            start_date: date.due_date,
            end_date: date.due_date,
            all_day: true,
            category: 'importante',
            case_id: currentCase.value.id,
            assigned_to_id: currentCase.value.assigned_to ?? undefined,
        });
        success(t('cases.event_created_from_date'));
    } catch {
        error(t('cases.event_creation_failed'));
    } finally {
        isCreatingEvent.value = false;
    }
}

// Initialize
onMounted(async () => {
    const caseId = parseInt(route.params.id as string);
    try {
        await caseStore.fetchCase(caseId);
        // For ad-hoc cases (no workflow_snapshot), load the case stages for the roadmap
        if (!caseStore.currentCase?.workflow_snapshot) {
            caseStagesStore.load(caseId);
        }
        // Load todos for this case so TimeLogModal can list them as options
        todoStore.fetchTodos({ case_id: caseId });
        // Load active timer (may belong to this case or another) to keep the header in sync
        timesheetStore.fetchActiveTimer();
    } catch (err) {
        error(t('cases.failed_to_load'));
    } finally {
        isLoading.value = false;
    }
});
</script>
