/**
 * Wizard Types
 * Interfaces for the Case Creation Wizard
 */

import type { CasePriority, ImportantDate, ServiceType, CaseFolderInput } from './case';
import type { ClientType } from './client';

// =============================================
// Wizard Task Item
// =============================================

export interface WizardTaskItem {
    key: string | null;   // null for custom tasks; stable key for base tasks
    label: string;        // translated label stored in the language of the case
    is_custom: boolean;
    sort_order: number;
}

// =============================================
// Wizard State
// =============================================

export interface WizardState {
    currentStep: number;
    caseTypeId: number | null;
    clientId: number | null;
    /**
     * Type of the currently-selected client (Spec 64).
     * When 'company', the wizard requires a mandatory beneficiary companion (DT-09).
     */
    clientType: ClientType | null;
    selectedCompanionIds: number[];
    primaryApplicantType: 'client' | 'companion';
    primaryApplicantCompanionId: number | null;
    clientIsDependent: boolean;
    selectedTasks: WizardTaskItem[];
    excludedTemplateIds: number[];
    caseDetails: CaseDetailsForm;
    isSubmitting: boolean;
    errors: Record<string, string[]>;
    folders: {
        selected: CaseFolderInput[];
    };
}

export interface CaseDetailsForm {
    priority: CasePriority;
    language: string;
    description: string;
    important_dates: ImportantDate[];
    assigned_to: number | null;
    service_type: ServiceType;
    contract_number: string;
    fees: number | null;
}

// =============================================
// Wizard Step Definition
// =============================================

export interface WizardStep {
    id: number;
    key: string;
    title: string;
    icon: string;
    isValid: boolean;
    isCompleted: boolean;
}

// =============================================
// Default Values
// =============================================

export const DEFAULT_CASE_DETAILS: CaseDetailsForm = {
    priority: 'medium',
    language: 'es',
    description: '',
    important_dates: [
        { label: 'Fecha límite legal',  due_date: null, sort_order: 0 },
        { label: 'Fecha de envío IRCC', due_date: null, sort_order: 1 },
        { label: 'Fecha de decisión',   due_date: null, sort_order: 2 },
    ],
    assigned_to: null,
    service_type: 'fee_based' as ServiceType,
    contract_number: '',
    fees: null,
};

export const WIZARD_STEPS: Omit<WizardStep, 'isValid' | 'isCompleted'>[] = [
    { id: 1, key: 'case_type',  title: 'wizard.step1.title',           icon: 'folder' },
    { id: 2, key: 'client',     title: 'wizard.step2.title',           icon: 'user' },
    { id: 3, key: 'companions', title: 'wizard.step3.title',           icon: 'users' },
    { id: 4, key: 'details',    title: 'wizard.step4.title',           icon: 'file-text' },
    { id: 5, key: 'folders',    title: 'wizard.step_folders.title',    icon: 'folder' },
    { id: 6, key: 'checklist',  title: 'wizard.step5_checklist.title', icon: 'list' },
    { id: 7, key: 'summary',    title: 'wizard.step6.title',           icon: 'check-circle' },
];

// =============================================
// Staff Member (for assignment dropdown)
// =============================================

export interface StaffMember {
    id: number;
    name: string;
    email: string;
    is_current_assignment?: boolean;
}
