/**
 * Companion Store
 * Manages companion state for a client
 */

import { defineStore } from 'pinia';
import companionService from '@/services/companionService';
import type { Companion, CreateCompanionData, UpdateCompanionData, RelationshipType } from '@/types/companion';

interface CompanionState {
    companions: Companion[];
    currentCompanion: Companion | null;
    clientId: number | null;
    isLoading: boolean;
    error: string | null;
}

export const useCompanionStore = defineStore('companion', {
    state: (): CompanionState => ({
        companions: [],
        currentCompanion: null,
        clientId: null,
        isLoading: false,
        error: null,
    }),

    getters: {
        getCompanionById: (state) => (id: number): Companion | undefined => {
            return state.companions.find((companion) => companion.id === id);
        },

        companionCount: (state): number => {
            return state.companions.length;
        },

        spouses: (state): Companion[] => {
            return state.companions.filter((c) => c.relationship === 'spouse');
        },

        children: (state): Companion[] => {
            return state.companions.filter((c) => c.relationship === 'child');
        },

        parents: (state): Companion[] => {
            return state.companions.filter((c) => c.relationship === 'parent');
        },

        siblings: (state): Companion[] => {
            return state.companions.filter((c) => c.relationship === 'sibling');
        },

        others: (state): Companion[] => {
            return state.companions.filter((c) => c.relationship === 'other');
        },

        relationshipOptions: (): Array<{ value: RelationshipType; label: string }> => {
            return [
                { value: 'spouse', label: 'Cónyuge' },
                { value: 'child', label: 'Hijo/a' },
                { value: 'parent', label: 'Padre/Madre' },
                { value: 'sibling', label: 'Hermano/a' },
                { value: 'other', label: 'Otro' },
            ];
        },
    },

    actions: {
        async fetchCompanions(clientId: number) {
            this.isLoading = true;
            this.error = null;
            this.clientId = clientId;

            try {
                this.companions = await companionService.getCompanions(clientId);
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to fetch companions';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async fetchCompanion(clientId: number, companionId: number) {
            this.isLoading = true;
            this.error = null;

            try {
                this.currentCompanion = await companionService.getCompanion(clientId, companionId);
                return this.currentCompanion;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to fetch companion';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async createCompanion(clientId: number, data: CreateCompanionData) {
            this.isLoading = true;
            this.error = null;

            try {
                const companion = await companionService.createCompanion(clientId, data);
                this.companions.push(companion);
                return companion;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to create companion';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async updateCompanion(clientId: number, companionId: number, data: UpdateCompanionData) {
            this.isLoading = true;
            this.error = null;

            try {
                const companion = await companionService.updateCompanion(clientId, companionId, data);
                // Update in list
                const index = this.companions.findIndex((c) => c.id === companionId);
                if (index !== -1) {
                    this.companions[index] = companion;
                }
                // Update current companion if same
                if (this.currentCompanion?.id === companionId) {
                    this.currentCompanion = companion;
                }
                return companion;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to update companion';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async deleteCompanion(clientId: number, companionId: number) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await companionService.deleteCompanion(clientId, companionId);
                // Remove from list
                this.companions = this.companions.filter((c) => c.id !== companionId);
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to delete companion';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        clearCompanions() {
            this.companions = [];
            this.currentCompanion = null;
            this.clientId = null;
        },

        clearCurrentCompanion() {
            this.currentCompanion = null;
        },

        clearError() {
            this.error = null;
        },
    },
});
