/**
 * Companion Types
 * Interfaces for companion-related data structures
 */

import type { Gender, CanadaStatus } from './client';

export type RelationshipType = 'beneficiary' | 'spouse' | 'child' | 'common-law partner' | 'dependent child' | 'grandchild' | 'parent' | 'grandparent' | 'sibling' | 'half-sibling' | 'step-sibling' | 'aunt / uncle' | 'niece / nephew' | 'cousin' | 'child-in-law' | 'parent-in-law' | 'employee' | 'other';

export interface Companion {
    id: number;
    client_id: number;
    tenant_id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    sort_name: string;
    initials: string;
    relationship: RelationshipType;
    relationship_other: string | null;
    relationship_label?: string;
    date_of_birth: string | null;
    age?: number | null;
    gender: Gender | null;
    marital_status?: string | null;
    nationality: string | null;
    notes: string | null;
    iuc?: string | null;
    email: string | null;
    phone: string | null;
    phone_country_code: string | null;
    canada_status: CanadaStatus | null;
    canada_status_other: string | null;
    canada_status_label?: string | null;
    arrival_date: string | null;
    created_at: string;
    updated_at: string;
    already_promoted?: boolean;
    promoted_to_client_id?: number | null;
    parent_companion_id: number | null;
    is_employee: boolean;
    is_family_member: boolean;
    family_count?: number;
    family_members?: Companion[];
}

export interface CompanionListParams {
    tier?: 'employees' | 'all';
    parent_id?: number | 'null';
    with_family?: boolean;
    with_family_count?: boolean;
}

export interface CreateCompanionData {
    first_name: string;
    last_name: string;
    relationship: RelationshipType;
    relationship_other?: string;
    date_of_birth?: string;
    gender?: Gender;
    marital_status?: string;
    nationality?: string;
    notes?: string;
    iuc?: string;
    email?: string;
    phone?: string;
    phone_country_code?: string;
    canada_status?: string;
    canada_status_other?: string;
    arrival_date?: string;
    parent_companion_id?: number | null;
}

export interface UpdateCompanionData {
    first_name?: string;
    last_name?: string;
    relationship?: RelationshipType;
    relationship_other?: string;
    date_of_birth?: string;
    gender?: Gender;
    marital_status?: string;
    nationality?: string;
    notes?: string;
    iuc?: string;
    email?: string;
    phone?: string;
    phone_country_code?: string;
    canada_status?: string;
    canada_status_other?: string;
    arrival_date?: string;
}

// Relationship type options for select dropdowns (excludes 'beneficiary' and 'employee' — set via presetRelationship or legacy data only)
export const RELATIONSHIP_TYPE_OPTIONS: Array<{ value: RelationshipType; label: string }> = [
    { value: 'spouse', label: 'Spouse' },
    { value: 'child', label: 'Child' },
    { value: 'dependent child', label: 'Dependent child' },
    { value: 'parent', label: 'Parent' },
    { value: 'sibling', label: 'Sibling' },
    { value: 'half-sibling', label: 'Half-sibling' },
    { value: 'step-sibling', label: 'Step-sibling' },
    { value: 'common-law partner', label: 'Common-law partner' },
    { value: 'grandchild', label: 'Grandchild' },
    { value: 'grandparent', label: 'Grandparent' },
    { value: 'aunt / uncle', label: 'Aunt / Uncle' },
    { value: 'niece / nephew', label: 'Niece / Nephew' },
    { value: 'cousin', label: 'Cousin' },
    { value: 'child-in-law', label: 'Child-in-law' },
    { value: 'parent-in-law', label: 'Parent-in-law' },
    { value: 'other', label: 'Other' },
];

// Spanish translations for relationship types
export const RELATIONSHIP_TYPE_LABELS_ES: Record<RelationshipType, string> = {
    beneficiary: 'Empleado Beneficiario',
    spouse: 'Cónyuge',
    child: 'Hijo/a',
    'dependent child': 'Hijo/a dependiente',
    parent: 'Padre/Madre',
    sibling: 'Hermano/a',
    'half-sibling': 'Medio hermano/a',
    'step-sibling': 'Hermanastro/a',
    'common-law partner': 'Pareja de hecho',
    grandchild: 'Nieto/a',
    grandparent: 'Abuelo/a',
    'aunt / uncle': 'Tía/Tío',
    'niece / nephew': 'Sobrino/a',
    cousin: 'Primo/a',
    'child-in-law': 'Yerno/Nuera',
    'parent-in-law': 'Suegro/a',
    employee: 'Empleado',
    other: 'Otro',
};
