/**
 * Companion Types
 * Interfaces for companion-related data structures
 */

import type { Gender } from './client';

export type RelationshipType = 'spouse' | 'child' | 'parent' | 'sibling' | 'other';

export interface Companion {
    id: number;
    client_id: number;
    tenant_id: number;
    first_name: string;
    last_name: string;
    full_name?: string;
    relationship: RelationshipType;
    relationship_other: string | null;
    relationship_label?: string;
    date_of_birth: string | null;
    age?: number | null;
    gender: Gender | null;
    passport_number: string | null;
    passport_country: string | null;
    passport_expiry_date: string | null;
    nationality: string | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

export interface CreateCompanionData {
    first_name: string;
    last_name: string;
    relationship: RelationshipType;
    relationship_other?: string;
    date_of_birth?: string;
    gender?: Gender;
    passport_number?: string;
    passport_country?: string;
    passport_expiry_date?: string;
    nationality?: string;
    notes?: string;
}

export interface UpdateCompanionData {
    first_name?: string;
    last_name?: string;
    relationship?: RelationshipType;
    relationship_other?: string;
    date_of_birth?: string;
    gender?: Gender;
    passport_number?: string;
    passport_country?: string;
    passport_expiry_date?: string;
    nationality?: string;
    notes?: string;
}

// Relationship type options for select dropdowns
export const RELATIONSHIP_TYPE_OPTIONS: Array<{ value: RelationshipType; label: string }> = [
    { value: 'spouse', label: 'Spouse' },
    { value: 'child', label: 'Child' },
    { value: 'parent', label: 'Parent' },
    { value: 'sibling', label: 'Sibling' },
    { value: 'other', label: 'Other' },
];

// Spanish translations for relationship types
export const RELATIONSHIP_TYPE_LABELS_ES: Record<RelationshipType, string> = {
    spouse: 'Cónyuge',
    child: 'Hijo/a',
    parent: 'Padre/Madre',
    sibling: 'Hermano/a',
    other: 'Otro',
};
