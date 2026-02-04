/**
 * Role Store
 * Manages role list state, pagination, and CRUD operations
 */

import { defineStore } from 'pinia';
import roleService, { type RoleFilters, type RoleWithPermissions, type PermissionsResponse } from '@/services/roleService';
import type { Permission, CreateRoleData, UpdateRoleData } from '@/types/role';
import { PROTECTED_ROLES } from '@/types/role';
import type { PaginationMeta, PaginationLinks } from '@/types/pagination';

interface RoleState {
    roles: RoleWithPermissions[];
    currentRole: RoleWithPermissions | null;
    permissions: Permission[];
    permissionsGrouped: Record<string, Permission[]>;
    meta: PaginationMeta | null;
    links: PaginationLinks | null;
    filters: RoleFilters;
    isLoading: boolean;
    error: string | null;
}

export const useRoleStore = defineStore('role', {
    state: (): RoleState => ({
        roles: [],
        currentRole: null,
        permissions: [],
        permissionsGrouped: {},
        meta: null,
        links: null,
        filters: {
            search: '',
            sort_by: 'name',
            sort_direction: 'asc',
            per_page: 15,
            page: 1,
        },
        isLoading: false,
        error: null,
    }),

    getters: {
        getRoleById: (state) => (id: number): RoleWithPermissions | undefined => {
            return state.roles.find((role) => role.id === id);
        },

        totalRoles: (state): number => {
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

        permissionGroups: (state): Array<{ name: string; display_name: string; permissions: Permission[] }> => {
            return Object.entries(state.permissionsGrouped).map(([name, permissions]) => ({
                name,
                display_name: name.charAt(0).toUpperCase() + name.slice(1),
                permissions,
            }));
        },
    },

    actions: {
        isProtectedRole(roleName: string): boolean {
            return PROTECTED_ROLES.includes(roleName);
        },

        async fetchRoles(filters?: Partial<RoleFilters>) {
            this.isLoading = true;
            this.error = null;

            if (filters) {
                this.filters = { ...this.filters, ...filters };
            }

            try {
                const response = await roleService.getRolesPaginated(this.filters);
                this.roles = response.data;

                // Map Laravel's flat pagination format to our meta/links structure
                this.meta = {
                    current_page: response.current_page,
                    from: response.from,
                    last_page: response.last_page,
                    per_page: response.per_page,
                    to: response.to,
                    total: response.total,
                    path: response.path || '',
                };
                this.links = {
                    first: response.first_page_url || null,
                    last: response.last_page_url || null,
                    prev: response.prev_page_url || null,
                    next: response.next_page_url || null,
                };
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to fetch roles';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async fetchPermissions() {
            try {
                const response: PermissionsResponse = await roleService.getPermissions();
                this.permissions = response.data;
                this.permissionsGrouped = response.grouped;
            } catch (error: any) {
                console.error('Failed to fetch permissions:', error);
            }
        },

        async fetchRole(id: number) {
            this.isLoading = true;
            this.error = null;

            try {
                this.currentRole = await roleService.getRole(id);
                return this.currentRole;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to fetch role';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async createRole(data: CreateRoleData) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await roleService.createRole(data);
                await this.fetchRoles();
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to create role';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async updateRole(id: number, data: UpdateRoleData) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await roleService.updateRole(id, data);
                const index = this.roles.findIndex((r) => r.id === id);
                if (index !== -1) {
                    this.roles[index] = response.role;
                }
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to update role';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async deleteRole(id: number) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await roleService.deleteRole(id);
                this.roles = this.roles.filter((r) => r.id !== id);
                if (this.meta) {
                    this.meta.total--;
                }
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to delete role';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        setSearch(search: string) {
            this.filters.search = search;
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
            this.filters.page = 1;
        },

        resetFilters() {
            this.filters = {
                search: '',
                sort_by: 'name',
                sort_direction: 'asc',
                per_page: 15,
                page: 1,
            };
        },

        clearCurrentRole() {
            this.currentRole = null;
        },

        clearError() {
            this.error = null;
        },
    },
});
