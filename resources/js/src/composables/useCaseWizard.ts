/**
 * Case Wizard Composable
 * Manages state and navigation for the case creation wizard
 */

import { reactive, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import caseService from '@/services/caseService';
import { useNotification } from './useNotification';
import type { WizardState, WizardStep, CaseDetailsForm } from '@/types/wizard';
import { WIZARD_STEPS } from '@/types/wizard';
import type { ImmigrationCase, CreateCaseData } from '@/types/case';

const STORAGE_KEY = 'case_wizard_state';

// Default state factory
const createDefaultState = (): WizardState => ({
    currentStep: 1,
    caseTypeId: null,
    clientId: null,
    selectedCompanionIds: [],
    caseDetails: {
        priority: 'medium',
        language: 'es',
        description: '',
        hearing_date: '',
        fda_deadline: '',
        brown_sheet_date: '',
        evidence_deadline: '',
        assigned_to: null,
    },
    isSubmitting: false,
    errors: {},
});

export function useCaseWizard() {
    const router = useRouter();
    const { t } = useI18n();
    const notification = useNotification();

    // Reactive state
    const state = reactive<WizardState>(createDefaultState());

    // Computed steps with validation status
    const steps = computed<WizardStep[]>(() => {
        return WIZARD_STEPS.map((step) => ({
            ...step,
            isValid: isStepValid(step.id),
            isCompleted: step.id < state.currentStep,
        }));
    });

    // Navigation computed
    const canGoNext = computed(() => {
        return isStepValid(state.currentStep) && state.currentStep < 5;
    });

    const canGoPrev = computed(() => {
        return state.currentStep > 1;
    });

    const isLastStep = computed(() => {
        return state.currentStep === 5;
    });

    const currentStepKey = computed(() => {
        const step = WIZARD_STEPS.find((s) => s.id === state.currentStep);
        return step?.key || 'case_type';
    });

    // Step validation
    function isStepValid(stepId: number): boolean {
        switch (stepId) {
            case 1:
                return state.caseTypeId !== null;
            case 2:
                return state.clientId !== null;
            case 3:
                // Companions are optional
                return true;
            case 4:
                // Details are optional
                return true;
            case 5:
                // Summary - all previous steps must be valid
                return isStepValid(1) && isStepValid(2);
            default:
                return false;
        }
    }

    // Navigation methods
    function goToStep(step: number): void {
        if (step >= 1 && step <= 5) {
            // Can only go forward if current step is valid
            if (step > state.currentStep && !isStepValid(state.currentStep)) {
                return;
            }
            state.currentStep = step;
            saveToSession();
        }
    }

    function nextStep(): void {
        if (canGoNext.value) {
            state.currentStep++;
            saveToSession();
        }
    }

    function prevStep(): void {
        if (canGoPrev.value) {
            state.currentStep--;
            saveToSession();
        }
    }

    // Data setters
    function setCaseType(id: number): void {
        state.caseTypeId = id;
        state.errors = {};
        saveToSession();
    }

    function setClient(id: number): void {
        state.clientId = id;
        // Clear companions when client changes
        state.selectedCompanionIds = [];
        state.errors = {};
        saveToSession();
    }

    function toggleCompanion(id: number): void {
        const index = state.selectedCompanionIds.indexOf(id);
        if (index === -1) {
            state.selectedCompanionIds.push(id);
        } else {
            state.selectedCompanionIds.splice(index, 1);
        }
        saveToSession();
    }

    function setCompanions(ids: number[]): void {
        state.selectedCompanionIds = [...ids];
        saveToSession();
    }

    function updateDetails(data: Partial<CaseDetailsForm>): void {
        Object.assign(state.caseDetails, data);
        saveToSession();
    }

    // Session persistence
    function saveToSession(): void {
        try {
            const data = {
                currentStep: state.currentStep,
                caseTypeId: state.caseTypeId,
                clientId: state.clientId,
                selectedCompanionIds: state.selectedCompanionIds,
                caseDetails: state.caseDetails,
            };
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        } catch (e) {
            console.warn('Failed to save wizard state to session:', e);
        }
    }

    function loadFromSession(): boolean {
        try {
            const stored = sessionStorage.getItem(STORAGE_KEY);
            if (stored) {
                const data = JSON.parse(stored);
                state.currentStep = data.currentStep || 1;
                state.caseTypeId = data.caseTypeId || null;
                state.clientId = data.clientId || null;
                state.selectedCompanionIds = data.selectedCompanionIds || [];
                if (data.caseDetails) {
                    Object.assign(state.caseDetails, data.caseDetails);
                }
                return true;
            }
        } catch (e) {
            console.warn('Failed to load wizard state from session:', e);
        }
        return false;
    }

    function clearSession(): void {
        sessionStorage.removeItem(STORAGE_KEY);
    }

    // Reset wizard
    function reset(): void {
        const defaultState = createDefaultState();
        Object.assign(state, defaultState);
        clearSession();
    }

    // Submit case
    async function submit(): Promise<ImmigrationCase | null> {
        if (!isStepValid(1) || !isStepValid(2)) {
            notification.error(t('wizard.errors.select_client'));
            return null;
        }

        state.isSubmitting = true;
        state.errors = {};

        try {
            const payload: CreateCaseData = {
                client_id: state.clientId!,
                case_type_id: state.caseTypeId!,
                companion_ids: state.selectedCompanionIds.length > 0 ? state.selectedCompanionIds : undefined,
                priority: state.caseDetails.priority,
                language: state.caseDetails.language || undefined,
                description: state.caseDetails.description || undefined,
                hearing_date: state.caseDetails.hearing_date || undefined,
                fda_deadline: state.caseDetails.fda_deadline || undefined,
                brown_sheet_date: state.caseDetails.brown_sheet_date || undefined,
                evidence_deadline: state.caseDetails.evidence_deadline || undefined,
                assigned_to: state.caseDetails.assigned_to || undefined,
            };

            const response = await caseService.createCase(payload);

            // Reset state first to prevent "unsaved changes" dialog
            reset();
            notification.success(t('wizard.success.case_created'));

            // Navigate to the created case
            router.push({ name: 'cases-show', params: { id: response.data.id } });

            return response.data;
        } catch (error: any) {
            if (error.response?.data?.errors) {
                state.errors = error.response.data.errors;
                notification.showValidationErrors(state.errors);
            } else {
                notification.showApiError(error, t('wizard.errors.create_failed'));
            }
            return null;
        } finally {
            state.isSubmitting = false;
        }
    }

    // Check for unsaved changes
    const hasUnsavedChanges = computed(() => {
        return state.caseTypeId !== null || state.clientId !== null;
    });

    return {
        // State
        state,
        steps,

        // Navigation
        currentStepKey,
        canGoNext,
        canGoPrev,
        isLastStep,
        goToStep,
        nextStep,
        prevStep,

        // Validation
        isStepValid,

        // Data setters
        setCaseType,
        setClient,
        toggleCompanion,
        setCompanions,
        updateDetails,

        // Persistence
        saveToSession,
        loadFromSession,
        clearSession,

        // Actions
        submit,
        reset,
        hasUnsavedChanges,
    };
}

export default useCaseWizard;
