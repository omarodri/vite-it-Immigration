<template>
    <div>
        <!-- Breadcrumb -->
        <ul class="flex space-x-2 rtl:space-x-reverse mb-5">
            <li>
                <router-link to="/cases" class="text-primary hover:underline">
                    {{ $t('cases.title') }}
                </router-link>
            </li>
            <li class="before:content-['/'] ltr:before:mr-2 rtl:before:ml-2">
                <span>{{ $t('wizard.title') }}</span>
            </li>
        </ul>

        <!-- Page Title -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold dark:text-white-light">
                {{ wizardTitle }}
            </h1>
        </div>

        <!-- Wizard Progress -->
        <WizardProgress :steps="steps" :current-step="state.currentStep" @navigate="goToStep" />

        <!-- Wizard Content -->
        <div class="panel">
            <KeepAlive>
                <component :is="currentStepComponent" />
            </KeepAlive>

            <!-- Navigation Buttons -->
            <nav class="flex justify-between items-center mt-8 pt-6 border-t border-gray-200 dark:border-gray-700" aria-label="Wizard navigation">
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    :disabled="!canGoPrev"
                    @click="prevStep"
                >
                    <icon-arrow-left class="w-4 h-4 ltr:mr-2 rtl:ml-2" aria-hidden="true" />
                    {{ $t('wizard.previous') }}
                </button>

                <div class="flex gap-3">
                    <router-link to="/cases" class="btn btn-outline-danger">
                        {{ $t('common.cancel') }}
                    </router-link>

                    <button
                        v-if="!isLastStep"
                        type="button"
                        class="btn btn-primary"
                        :disabled="!canGoNext"
                        @click="nextStep"
                    >
                        {{ $t('wizard.next') }}
                        <icon-arrow-forward class="w-4 h-4 ltr:ml-2 rtl:mr-2" aria-hidden="true" />
                    </button>

                    <button
                        v-else
                        type="button"
                        class="btn btn-success"
                        :disabled="state.isSubmitting"
                        :aria-busy="state.isSubmitting"
                        @click="handleSubmit"
                    >
                        <icon-loader v-if="state.isSubmitting" class="w-4 h-4 animate-spin ltr:mr-2 rtl:ml-2" aria-hidden="true" />
                        <icon-circle-check v-else class="w-4 h-4 ltr:mr-2 rtl:ml-2" aria-hidden="true" />
                        {{ state.isSubmitting ? $t('wizard.creating') : $t('wizard.create_case') }}
                    </button>
                </div>
            </nav>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { computed, onMounted, defineAsyncComponent, provide } from 'vue';
import { onBeforeRouteLeave, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useMeta } from '@/composables/use-meta';
import { useCaseWizard } from '@/composables/useCaseWizard';
import { useNotification } from '@/composables/useNotification';
import WizardProgress from './components/WizardProgress.vue';
import IconArrowLeft from '@/components/icon/icon-arrow-left.vue';
import IconArrowForward from '@/components/icon/icon-arrow-forward.vue';
import IconCircleCheck from '@/components/icon/icon-circle-check.vue';
import IconLoader from '@/components/icon/icon-loader.vue';

// Lazy load step components
const StepCaseType = defineAsyncComponent(() => import('./steps/StepCaseType.vue'));
const StepClient = defineAsyncComponent(() => import('./steps/StepClient.vue'));
const StepCompanions = defineAsyncComponent(() => import('./steps/StepCompanions.vue'));
const StepDetails = defineAsyncComponent(() => import('./steps/StepDetails.vue'));
const StepChecklist = defineAsyncComponent(() => import('./steps/StepChecklist.vue'));
const StepSummary = defineAsyncComponent(() => import('./steps/StepSummary.vue'));

const { t } = useI18n();
const route = useRoute();

const clientNameFromRoute = computed(() => {
    const name = route.query.client_name as string | undefined;
    return name ? decodeURIComponent(name) : null;
});

const wizardTitle = computed(() =>
    clientNameFromRoute.value
        ? `${t('wizard.title')} — ${clientNameFromRoute.value}`
        : t('wizard.title')
);

useMeta({ title: t('wizard.title') });

const notification = useNotification();
const wizard = useCaseWizard();
const { state, steps, canGoNext, canGoPrev, isLastStep, goToStep, nextStep, prevStep, submit, loadFromSession, hasUnsavedChanges } = wizard;

// Provide wizard to child components
provide('wizard', wizard);

// Current step component mapping
const currentStepComponent = computed(() => {
    switch (state.currentStep) {
        case 1:
            return StepCaseType;
        case 2:
            return StepClient;
        case 3:
            return StepCompanions;
        case 4:
            return StepDetails;
        case 5:
            return StepChecklist;
        case 6:
            return StepSummary;
        default:
            return StepCaseType;
    }
});

// Load saved state on mount
onMounted(() => {
    loadFromSession();
});

// Warn before leaving with unsaved changes
onBeforeRouteLeave(async (to, from, next) => {
    if (hasUnsavedChanges.value) {
        const confirmed = await notification.confirm({
            title: t('wizard.unsaved_changes_title'),
            text: t('wizard.unsaved_changes_message'),
            confirmButtonText: t('common.leave'),
            cancelButtonText: t('common.stay'),
        });

        if (confirmed) {
            wizard.clearSession();
            next();
        } else {
            next(false);
        }
    } else {
        next();
    }
});

// Submit handler
async function handleSubmit() {
    await submit();
}
</script>
