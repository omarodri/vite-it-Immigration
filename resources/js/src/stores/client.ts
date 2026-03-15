/**
 * Client Store
 * Manages client list state, pagination, and CRUD operations
 */

import { defineStore } from 'pinia';
import clientService from '@/services/clientService';
import type { Client, CreateClientData, UpdateClientData, ClientFilters, ClientStatistics, ClientStatus } from '@/types/client';
import type { PaginationMeta, PaginationLinks } from '@/types/pagination';

interface ClientState {
    clients: Client[];
    currentClient: Client | null;
    statistics: ClientStatistics | null;
    meta: PaginationMeta | null;
    links: PaginationLinks | null;
    filters: ClientFilters;
    isLoading: boolean;
    error: string | null;
}

export const useClientStore = defineStore('client', {
    state: (): ClientState => ({
        clients: [],
        currentClient: null,
        statistics: null,
        meta: null,
        links: null,
        filters: {
            search: '',
            status: undefined,
            nationality: undefined,
            canada_status: undefined,
            sort_by: 'created_at',
            sort_direction: 'desc',
            per_page: 15,
            page: 1,
        },
        isLoading: false,
        error: null,
    }),

    getters: {
        getClientById: (state) => (id: number): Client | undefined => {
            return state.clients.find((client) => client.id === id);
        },

        totalClients: (state): number => {
            return state.meta?.total ?? 0;
        },

        currentPage: (state): number => {
            return state.meta?.current_page ?? 1;
        },

        lastPage: (state): number => {
            return state.meta?.last_page ?? 1;
        },

        hasNextPage: (state): boolean => {
            return state.links?.next !== null;
        },

        hasPrevPage: (state): boolean => {
            return state.links?.prev !== null;
        },

        statusOptions: (): Array<{ value: ClientStatus | ''; label: string }> => {
            return [
                { value: '', label: 'All Statuses' },
                { value: 'prospect', label: 'Prospect' },
                { value: 'active', label: 'Active' },
                { value: 'inactive', label: 'Inactive' },
                { value: 'archived', label: 'Archived' },
            ];
        },

        prospectCount: (state): number => {
            return state.statistics?.prospect ?? 0;
        },

        activeCount: (state): number => {
            return state.statistics?.active ?? 0;
        },
    },

    actions: {
        async fetchClients(filters?: Partial<ClientFilters>) {
            this.isLoading = true;
            this.error = null;

            // Merge filters
            if (filters) {
                this.filters = { ...this.filters, ...filters };
            }

            try {
                const response = await clientService.getClients(this.filters);
                this.clients = response.data;

                // Handle Laravel's pagination format (flat structure)
                if ('meta' in response) {
                    this.meta = response.meta;
                    this.links = response.links;
                } else {
                    // Map Laravel's flat format to our meta structure
                    this.meta = {
                        current_page: (response as any).current_page,
                        from: (response as any).from,
                        last_page: (response as any).last_page,
                        per_page: (response as any).per_page,
                        to: (response as any).to,
                        total: (response as any).total,
                        path: (response as any).path || '',
                    };
                    this.links = {
                        first: (response as any).first_page_url || null,
                        last: (response as any).last_page_url || null,
                        prev: (response as any).prev_page_url || null,
                        next: (response as any).next_page_url || null,
                    };
                }
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to fetch clients';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async fetchStatistics() {
            try {
                this.statistics = await clientService.getStatistics();
            } catch (error: any) {
                console.error('Failed to fetch statistics:', error);
            }
        },

        async fetchClient(id: number) {
            this.isLoading = true;
            this.error = null;

            try {
                this.currentClient = await clientService.getClient(id);
                return this.currentClient;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to fetch client';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async createClient(data: CreateClientData) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await clientService.createClient(data);
                // Refresh the list
                await this.fetchClients();
                await this.fetchStatistics();
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to create client';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async updateClient(id: number, data: UpdateClientData) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await clientService.updateClient(id, data);
                // Update client in list
                const index = this.clients.findIndex((c) => c.id === id);
                if (index !== -1) {
                    this.clients[index] = response.client;
                }
                // Update currentClient if it's the same
                if (this.currentClient?.id === id) {
                    this.currentClient = response.client;
                }
                await this.fetchStatistics();
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to update client';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async deleteClient(id: number) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await clientService.deleteClient(id);
                // Remove from list
                this.clients = this.clients.filter((c) => c.id !== id);
                // Update total count
                if (this.meta) {
                    this.meta.total--;
                }
                await this.fetchStatistics();
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to delete client';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async bulkDeleteClients(ids: number[]) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await clientService.bulkDeleteClients(ids);
                // Refresh the list
                await this.fetchClients();
                await this.fetchStatistics();
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to delete clients';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async convertProspect(id: number) {
            this.error = null;

            try {
                const response = await clientService.convertProspect(id);
                // Update in-place so the datatable gets a normal reactive
                // data change (status field only) instead of a structural DOM
                // change triggered by v-if removal.
                const idx = this.clients.findIndex((c) => c.id === id);
                if (idx !== -1) {
                    this.clients[idx] = { ...this.clients[idx], ...response.client };
                }
                if (this.currentClient?.id === id) {
                    this.currentClient = response.client;
                }
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to convert prospect';
                throw error;
            }
        },

        setSearch(search: string) {
            this.filters.search = search;
            this.filters.page = 1; // Reset to first page
        },

        setStatusFilter(status: ClientStatus | undefined) {
            this.filters.status = status;
            this.filters.page = 1; // Reset to first page
        },

        setNationalityFilter(nationality: string | undefined) {
            this.filters.nationality = nationality;
            this.filters.page = 1;
        },

        setCanadaStatusFilter(canadaStatus: string | undefined) {
            this.filters.canada_status = canadaStatus as any;
            this.filters.page = 1;
        },

        setSort(sortBy: string, direction: 'asc' | 'desc') {
            this.filters.sort_by = sortBy;
            this.filters.sort_direction = direction;
        },

        setPage(page: number) {
            this.filters.page = page;
        },

        setPerPage(perPage: number) {
            this.filters.per_page = perPage;
            this.filters.page = 1; // Reset to first page
        },

        resetFilters() {
            this.filters = {
                search: '',
                status: undefined,
                nationality: undefined,
                canada_status: undefined,
                sort_by: 'created_at',
                sort_direction: 'desc',
                per_page: 15,
                page: 1,
            };
        },

        clearCurrentClient() {
            this.currentClient = null;
        },

        clearError() {
            this.error = null;
        },
    },
});
