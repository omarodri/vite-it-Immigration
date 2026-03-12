/**
 * User Store
 * Manages user list state, pagination, and CRUD operations
 */

import { defineStore } from 'pinia';
import userService, { type UserFilters } from '@/services/userService';
import roleService, { type RoleWithPermissions } from '@/services/roleService';
import type { User, CreateUserData, UpdateUserData } from '@/types/user';
import type { PaginationMeta, PaginationLinks } from '@/types/pagination';

interface UserState {
    users: User[];
    currentUser: User | null;
    roles: RoleWithPermissions[];
    meta: PaginationMeta | null;
    links: PaginationLinks | null;
    filters: UserFilters;
    isLoading: boolean;
    error: string | null;
}

export const useUserStore = defineStore('user', {
    state: (): UserState => ({
        users: [],
        currentUser: null,
        roles: [],
        meta: null,
        links: null,
        filters: {
            search: '',
            role: '',
            sort_by: 'created_at',
            sort_direction: 'desc',
            per_page: 15,
            page: 1,
        },
        isLoading: false,
        error: null,
    }),

    getters: {
        getUserById: (state) => (id: number): User | undefined => {
            return state.users.find((user) => user.id === id);
        },

        totalUsers: (state): number => {
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

        roleOptions: (state): Array<{ value: string; label: string }> => {
            return state.roles.map((role) => ({
                value: role.name,
                label: role.name.charAt(0).toUpperCase() + role.name.slice(1),
            }));
        },
    },

    actions: {
        async fetchUsers(filters?: Partial<UserFilters>) {
            this.isLoading = true;
            this.error = null;

            // Merge filters
            if (filters) {
                this.filters = { ...this.filters, ...filters };
            }

            try {
                const response = await userService.getUsers(this.filters);
                this.users = response.data;

                // Handle Laravel's pagination format (flat structure)
                // Laravel returns: { data, current_page, per_page, total, last_page, from, to, ... }
                // Our type expects: { data, meta: {...}, links: {...} }
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
                if (error?.response?.status === 401) throw error; // auth redirect, suppress notification
                this.error = error.response?.data?.message || 'Failed to fetch users';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async fetchRoles() {
            try {
                this.roles = await roleService.getRoles();
            } catch (error: any) {
                console.error('Failed to fetch roles:', error);
            }
        },

        async fetchUser(id: number) {
            this.isLoading = true;
            this.error = null;

            try {
                this.currentUser = await userService.getUser(id);
                return this.currentUser;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to fetch user';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async createUser(data: CreateUserData) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await userService.createUser(data);
                // Refresh the list
                await this.fetchUsers();
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to create user';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async updateUser(id: number, data: UpdateUserData) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await userService.updateUser(id, data);
                // Update user in list
                const index = this.users.findIndex((u) => u.id === id);
                if (index !== -1) {
                    this.users[index] = response.user;
                }
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to update user';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async deleteUser(id: number) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await userService.deleteUser(id);
                // Remove from list
                this.users = this.users.filter((u) => u.id !== id);
                // Update total count
                if (this.meta) {
                    this.meta.total--;
                }
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to delete user';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async bulkDeleteUsers(ids: number[]) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await userService.bulkDeleteUsers(ids);
                // Refresh the list
                await this.fetchUsers();
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to delete users';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        setSearch(search: string) {
            this.filters.search = search;
            this.filters.page = 1; // Reset to first page
        },

        setRoleFilter(role: string) {
            this.filters.role = role;
            this.filters.page = 1; // Reset to first page
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
                role: '',
                sort_by: 'created_at',
                sort_direction: 'desc',
                per_page: 15,
                page: 1,
            };
        },

        clearCurrentUser() {
            this.currentUser = null;
        },

        clearError() {
            this.error = null;
        },
    },
});
