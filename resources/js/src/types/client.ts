/**
 * Client Types
 * Interfaces for client-related data structures
 */

export type ClientStatus = 'prospect' | 'active' | 'inactive' | 'archived';
export type Gender = 'male' | 'female' | 'other';
export type MaritalStatus = 'single' | 'married' | 'divorced' | 'widowed' | 'common_law' | 'separated';
export type CanadaStatus = 'asylum_seeker' | 'refugee' | 'temporary_resident' | 'permanent_resident' | 'citizen' | 'visitor' | 'student' | 'worker' | 'other';
export type EntryPoint = 'airport' | 'land_border' | 'green_path';
export type Language = 'es' | 'en' | 'fr';

export interface Client {
    id: number;
    tenant_id: number;
    user_id: number | null;

    // Personal Information
    first_name: string;
    last_name: string;
    full_name?: string;
    initials?: string;
    nationality: string | null;
    second_nationality: string | null;
    language: Language | null;
    second_language: Language | null;
    date_of_birth: string | null;
    gender: Gender | null;
    passport_number: string | null;
    passport_country: string | null;
    passport_expiry_date: string | null;
    marital_status: MaritalStatus | null;
    profession: string | null;
    description: string | null;

    // Contact Information
    email: string | null;
    residential_address: string | null;
    mailing_address: string | null;
    city: string | null;
    province: string | null;
    postal_code: string | null;
    country: string | null;
    phone: string | null;
    secondary_phone: string | null;

    // Legal Status in Canada
    canada_status: CanadaStatus | null;
    status_date: string | null;
    arrival_date: string | null;
    entry_point: EntryPoint | null;
    iuc: string | null;
    work_permit_number: string | null;
    study_permit_number: string | null;
    permit_expiry_date: string | null;
    other_status_1: string | null;
    other_status_2: string | null;

    // Status
    status: ClientStatus;
    is_primary_applicant: boolean;

    // Timestamps
    created_at: string;
    updated_at: string;
    deleted_at: string | null;

    // Relations (optional)
    user?: {
        id: number;
        name: string;
        email: string;
    };
    companions?: Client[];
    cases?: any[];
}

export interface CreateClientData {
    // Personal Information (required)
    first_name: string;
    last_name: string;

    // Personal Information (optional)
    nationality?: string;
    second_nationality?: string;
    language?: string;
    second_language?: string;
    date_of_birth?: string;
    gender?: Gender;
    passport_number?: string;
    passport_country?: string;
    passport_expiry_date?: string;
    marital_status?: MaritalStatus;
    profession?: string;
    description?: string;

    // Contact Information
    email?: string;
    residential_address?: string;
    mailing_address?: string;
    city?: string;
    province?: string;
    postal_code?: string;
    country?: string;
    phone?: string;
    secondary_phone?: string;

    // Legal Status in Canada
    canada_status?: CanadaStatus;
    status_date?: string;
    arrival_date?: string;
    entry_point?: EntryPoint;
    iuc?: string;
    work_permit_number?: string;
    study_permit_number?: string;
    permit_expiry_date?: string;
    other_status_1?: string;
    other_status_2?: string;

    // Status
    status?: ClientStatus;
    is_primary_applicant?: boolean;
}

export interface UpdateClientData {
    // Personal Information
    first_name?: string;
    last_name?: string;
    nationality?: string;
    second_nationality?: string;
    language?: string;
    second_language?: string;
    date_of_birth?: string;
    gender?: Gender;
    passport_number?: string;
    passport_country?: string;
    passport_expiry_date?: string;
    marital_status?: MaritalStatus;
    profession?: string;
    description?: string;

    // Contact Information
    email?: string;
    residential_address?: string;
    mailing_address?: string;
    city?: string;
    province?: string;
    postal_code?: string;
    country?: string;
    phone?: string;
    secondary_phone?: string;

    // Legal Status in Canada
    canada_status?: CanadaStatus;
    status_date?: string;
    arrival_date?: string;
    entry_point?: EntryPoint;
    iuc?: string;
    work_permit_number?: string;
    study_permit_number?: string;
    permit_expiry_date?: string;
    other_status_1?: string;
    other_status_2?: string;

    // Status
    status?: ClientStatus;
    is_primary_applicant?: boolean;
}

export interface ClientStatistics {
    total: number;
    prospect: number;
    active: number;
    inactive: number;
    archived: number;
}

export interface ClientFilters {
    search?: string;
    status?: ClientStatus;
    nationality?: string;
    canada_status?: CanadaStatus;
    date_from?: string;
    date_to?: string;
    is_primary_applicant?: boolean;
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
    per_page?: number;
    page?: number;
}

// Status options for select dropdowns
export const CLIENT_STATUS_OPTIONS: Array<{ value: ClientStatus; label: string; color: string }> = [
    { value: 'prospect', label: 'Prospect', color: 'info' },
    { value: 'active', label: 'Active', color: 'success' },
    { value: 'inactive', label: 'Inactive', color: 'warning' },
    { value: 'archived', label: 'Archived', color: 'secondary' },
];

export const GENDER_OPTIONS: Array<{ value: Gender; label: string }> = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
    { value: 'other', label: 'Other' },
];

export const MARITAL_STATUS_OPTIONS: Array<{ value: MaritalStatus; label: string }> = [
    { value: 'single', label: 'Single' },
    { value: 'married', label: 'Married' },
    { value: 'divorced', label: 'Divorced' },
    { value: 'widowed', label: 'Widowed' },
    { value: 'common_law', label: 'Common Law' },
    { value: 'separated', label: 'Separated' },
];

export const CANADA_STATUS_OPTIONS: Array<{ value: CanadaStatus; label: string }> = [
    { value: 'asylum_seeker', label: 'Asylum Seeker' },
    { value: 'refugee', label: 'Refugee' },
    { value: 'temporary_resident', label: 'Temporary Resident' },
    { value: 'permanent_resident', label: 'Permanent Resident' },
    { value: 'citizen', label: 'Citizen' },
    { value: 'visitor', label: 'Visitor' },
    { value: 'student', label: 'Student' },
    { value: 'worker', label: 'Worker' },
    { value: 'other', label: 'Other' },
];

export const ENTRY_POINT_OPTIONS: Array<{ value: EntryPoint; label: string }> = [
    { value: 'airport', label: 'Airport' },
    { value: 'land_border', label: 'Land Border' },
    { value: 'green_path', label: 'Green Path (Irregular)' },
];

export const LANGUAGE_OPTIONS: Array<{ value: Language; label: string }> = [
    { value: 'es', label: 'Spanish' },
    { value: 'en', label: 'English' },
    { value: 'fr', label: 'French' },
];