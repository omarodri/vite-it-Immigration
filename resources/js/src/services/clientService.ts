/**
 * Client Service
 * Handles all client-related API calls
 */

import api from './api';
import type { Client, CreateClientData, UpdateClientData, ClientFilters, ClientStatistics } from '@/types/client';
import type { PaginatedResponse } from '@/types/pagination';

export interface ClientResponse {
    message: string;
    client: Client;
}

const clientService = {
    /**
     * Get paginated list of clients
     */
    async getClients(filters: ClientFilters = {}): Promise<PaginatedResponse<Client>> {
        const params = new URLSearchParams();

        if (filters.search) params.append('search', filters.search);
        if (filters.status) params.append('status', filters.status);
        if (filters.nationality) params.append('nationality', filters.nationality);
        if (filters.canada_status) params.append('canada_status', filters.canada_status);
        if (filters.date_from) params.append('date_from', filters.date_from);
        if (filters.date_to) params.append('date_to', filters.date_to);
        if (filters.is_primary_applicant !== undefined) {
            params.append('is_primary_applicant', filters.is_primary_applicant ? '1' : '0');
        }
        if (filters.sort_by) params.append('sort_by', filters.sort_by);
        if (filters.sort_direction) params.append('sort_direction', filters.sort_direction);
        if (filters.per_page) params.append('per_page', filters.per_page.toString());
        if (filters.page) params.append('page', filters.page.toString());

        const response = await api.get<PaginatedResponse<Client>>(`/clients?${params.toString()}`);
        return response.data;
    },

    /**
     * Get a single client by ID
     */
    async getClient(id: number): Promise<Client> {
        const response = await api.get<Client>(`/clients/${id}`);
        return response.data;
    },

    /**
     * Create a new client
     */
    async createClient(data: CreateClientData): Promise<ClientResponse> {
        const response = await api.post<ClientResponse>('/clients', data);
        return response.data;
    },

    /**
     * Update an existing client
     */
    async updateClient(id: number, data: UpdateClientData): Promise<ClientResponse> {
        const response = await api.put<ClientResponse>(`/clients/${id}`, data);
        return response.data;
    },

    /**
     * Delete a client
     */
    async deleteClient(id: number): Promise<{ message: string }> {
        const response = await api.delete<{ message: string }>(`/clients/${id}`);
        return response.data;
    },

    /**
     * Bulk delete clients
     */
    async bulkDeleteClients(ids: number[]): Promise<{ message: string }> {
        const response = await api.delete<{ message: string }>('/clients/bulk', {
            data: { ids },
        });
        return response.data;
    },

    /**
     * Convert prospect to active client
     */
    async convertProspect(id: number): Promise<ClientResponse> {
        const response = await api.post<ClientResponse>(`/clients/${id}/convert`);
        return response.data;
    },

    /**
     * Get client statistics
     */
    async getStatistics(): Promise<ClientStatistics> {
        const response = await api.get<ClientStatistics>('/clients/statistics');
        return response.data;
    },
};

export default clientService;
