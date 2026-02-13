/**
 * Wizard Types
 * Interfaces for the Case Creation Wizard
 */

import type { CasePriority } from './case';

// =============================================
// Wizard State
// =============================================

export interface WizardState {
    currentStep: number;
    caseTypeId: number | null;
    clientId: number | null;
    selectedCompanionIds: number[];
    caseDetails: CaseDetailsForm;
    isSubmitting: boolean;
    errors: Record<string, string[]>;
}

export interface CaseDetailsForm {
    priority: CasePriority;
    language: string;
    description: string;
    hearing_date: string;
    fda_deadline: string;
    brown_sheet_date: string;
    evidence_deadline: string;
    assigned_to: number | null;
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
    hearing_date: '',
    fda_deadline: '',
    brown_sheet_date: '',
    evidence_deadline: '',
    assigned_to: null,
};

export const WIZARD_STEPS: Omit<WizardStep, 'isValid' | 'isCompleted'>[] = [
    { id: 1, key: 'case_type', title: 'wizard.step1.title', icon: 'folder' },
    { id: 2, key: 'client', title: 'wizard.step2.title', icon: 'user' },
    { id: 3, key: 'companions', title: 'wizard.step3.title', icon: 'users' },
    { id: 4, key: 'details', title: 'wizard.step4.title', icon: 'file-text' },
    { id: 5, key: 'summary', title: 'wizard.step5.title', icon: 'check-circle' },
];

// =============================================
// Staff Member (for assignment dropdown)
// =============================================

export interface StaffMember {
    id: number;
    name: string;
    email: string;
}
