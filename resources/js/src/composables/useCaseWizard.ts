/**
 * Case Wizard Composable
 * Manages state and navigation for the case creation wizard
 */

import { reactive, ref, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import caseService from '@/services/caseService';
import { useNotification } from './useNotification';
import type { WizardState, WizardStep, CaseDetailsForm, WizardTaskItem } from '@/types/wizard';
import { WIZARD_STEPS } from '@/types/wizard';
import type { ImmigrationCase, CreateCaseData, CaseFolderInput } from '@/types/case';

const STORAGE_KEY = 'case_wizard_state';

// Default state factory
const createDefaultState = (): WizardState => ({
    currentStep: 1,
    caseTypeId: null,
    clientId: null,
    selectedCompanionIds: [],
    primaryApplicantType: 'client' as 'client' | 'companion',
    primaryApplicantCompanionId: null as number | null,
    clientIsDependent: true,
    selectedTasks: [] as WizardTaskItem[],
    excludedTemplateIds: [] as number[],
    caseDetails: {
        priority: 'medium',
        language: 'es',
        description: '',
        important_dates: [
            { label: 'Fecha limite legal',  due_date: null, sort_order: 0 },
            { label: 'Fecha de envio IRCC', due_date: null, sort_order: 1 },
            { label: 'Fecha de decision',   due_date: null, sort_order: 2 },
        ],
        assigned_to: null,
        service_type: 'fee_based',
        contract_number: '',
        fees: null,
    },
    isSubmitting: false,
    errors: {},
    folders: {
        selected: [] as CaseFolderInput[],
    },
});

export function useCaseWizard() {
    const router = useRouter();
    const { t } = useI18n();
    const notification = useNotification();

    // Reactive state
    const state = reactive<WizardState>(createDefaultState());

    // Todo Core confirmation modal
    const showTodoCoreModal = ref(false);

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
        return isStepValid(state.currentStep) && state.currentStep < 7;
    });

    const canGoPrev = computed(() => {
        return state.currentStep > 1;
    });

    const isLastStep = computed(() => {
        return state.currentStep === 7;
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
                return (
                    state.primaryApplicantType === 'client' ||
                    (state.primaryApplicantType === 'companion' && state.primaryApplicantCompanionId !== null)
                );
            case 4:
                // Details are optional
                return true;
            case 5:
                // Folders — empty selection is allowed (user warned but can continue)
                return true;
            case 6:
                // Checklist is optional
                return true;
            case 7:
                // Summary - all previous steps must be valid
                return isStepValid(1) && isStepValid(2);
            default:
                return false;
        }
    }

    // Navigation methods
    function goToStep(step: number): void {
        if (step >= 1 && step <= 7) {
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
        state.primaryApplicantType = 'client';
        state.primaryApplicantCompanionId = null;
        state.clientIsDependent = true;
        state.errors = {};
        saveToSession();
    }

    function toggleCompanion(id: number): void {
        const index = state.selectedCompanionIds.indexOf(id);
        if (index === -1) {
            state.selectedCompanionIds.push(id);
        } else {
            state.selectedCompanionIds.splice(index, 1);
            // If the removed companion was the primary applicant, revert to client
            if (state.primaryApplicantType === 'companion' && state.primaryApplicantCompanionId === id) {
                state.primaryApplicantType = 'client';
                state.primaryApplicantCompanionId = null;
                state.clientIsDependent = true;
            }
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
                primaryApplicantType: state.primaryApplicantType,
                primaryApplicantCompanionId: state.primaryApplicantCompanionId,
                clientIsDependent: state.clientIsDependent,
                selectedTasks: state.selectedTasks,
                caseDetails: state.caseDetails,
                excludedTemplateIds: state.excludedTemplateIds,
                folders: state.folders,
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
                state.primaryApplicantType = data.primaryApplicantType || 'client';
                state.primaryApplicantCompanionId = data.primaryApplicantCompanionId ?? null;
                state.clientIsDependent = data.clientIsDependent ?? true;
                state.selectedTasks = data.selectedTasks || [];
                state.excludedTemplateIds = data.excludedTemplateIds || [];
                if (data.caseDetails) {
                    Object.assign(state.caseDetails, data.caseDetails);
                }
                if (data.folders) {
                    Object.assign(state.folders, data.folders);
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

    // Determine whether the wizard will create operational todos for the consultant.
    // Conditions: the consultant is assigned AND there is at least one template-based
    // task selected (i.e. not excluded).
    function hasOperationalTasksForConsultant(): boolean {
        if (!state.caseDetails.assigned_to) return false;
        const includedTemplates = state.selectedTasks.filter(
            (task) => !task.is_custom && task.key && !state.excludedTemplateIds.some((id) => String(id) === task.key),
        );
        return includedTemplates.length > 0;
    }

    // Submit case
    async function submit(): Promise<ImmigrationCase | null> {
        if (!isStepValid(1) || !isStepValid(2)) {
            notification.error(t('wizard.errors.select_client'));
            return null;
        }

        // Show the Todo Core confirmation modal before actually submitting,
        // when the wizard is going to create operational todos for a consultant.
        if (!showTodoCoreModal.value && hasOperationalTasksForConsultant()) {
            showTodoCoreModal.value = true;
            return null;
        }

        state.isSubmitting = true;
        state.errors = {};

        try {
            const payload: CreateCaseData = {
                client_id: state.clientId!,
                case_type_id: state.caseTypeId!,
                companion_ids: state.selectedCompanionIds.length > 0 ? state.selectedCompanionIds : undefined,
                primary_applicant_type: state.primaryApplicantType,
                primary_applicant_companion_id: state.primaryApplicantCompanionId ?? undefined,
                client_is_participant: state.clientIsDependent,
                priority: state.caseDetails.priority,
                language: state.caseDetails.language || undefined,
                description: state.caseDetails.description || undefined,
                important_dates: state.caseDetails.important_dates.map(d => ({
                    label: d.label,
                    due_date: d.due_date,
                    sort_order: d.sort_order,
                })),
                assigned_to: state.caseDetails.assigned_to || undefined,
                service_type: state.caseDetails.service_type,
                contract_number: state.caseDetails.contract_number || undefined,
                fees: state.caseDetails.fees ?? undefined,
                excluded_template_ids: state.excludedTemplateIds.length > 0
                    ? state.excludedTemplateIds
                    : undefined,
                folders: state.folders.selected,
            };

            const response = await caseService.createCase(payload);

            // Reset state first to prevent "unsaved changes" dialog
            reset();
            notification.success(t('wizard.success.case_created'));

            // Navigate to the created case
            router.push({ name: 'cases-show', params: { id: response.data.id }, query: { just_created: '1' } });

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

    // Confirm the Todo Core modal and proceed with the actual submission.
    async function confirmTodoCoreAndSubmit(): Promise<ImmigrationCase | null> {
        showTodoCoreModal.value = false;
        return submit();
    }

    function cancelTodoCoreConfirmation(): void {
        showTodoCoreModal.value = false;
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

        // Todo Core confirmation
        showTodoCoreModal,
        confirmTodoCoreAndSubmit,
        cancelTodoCoreConfirmation,
    };
}

export default useCaseWizard;
