/**
 * Companion Service
 * Handles all companion-related API calls
 */

import api from './api';
import type { Companion, CreateCompanionData, UpdateCompanionData } from '@/types/companion';

export interface CompanionResponse {
    data: Companion;
}

export interface CompanionsListResponse {
    data: Companion[];
}

const companionService = {
    /**
     * Get all companions for a client
     */
    async getCompanions(clientId: number): Promise<Companion[]> {
        const response = await api.get<CompanionsListResponse>(`/clients/${clientId}/companions`);
        return response.data.data;
    },

    /**
     * Get a single companion by ID
     */
    async getCompanion(clientId: number, companionId: number): Promise<Companion> {
        const response = await api.get<CompanionResponse>(`/clients/${clientId}/companions/${companionId}`);
        return response.data.data;
    },

    /**
     * Create a new companion for a client
     */
    async createCompanion(clientId: number, data: CreateCompanionData): Promise<Companion> {
        const response = await api.post<CompanionResponse>(`/clients/${clientId}/companions`, data);
        return response.data.data;
    },

    /**
     * Update an existing companion
     */
    async updateCompanion(clientId: number, companionId: number, data: UpdateCompanionData): Promise<Companion> {
        const response = await api.put<CompanionResponse>(`/clients/${clientId}/companions/${companionId}`, data);
        return response.data.data;
    },

    /**
     * Delete a companion
     */
    async deleteCompanion(clientId: number, companionId: number): Promise<{ message: string }> {
        const response = await api.delete<{ message: string }>(`/clients/${clientId}/companions/${companionId}`);
        return response.data;
    },
};

export default companionService;
