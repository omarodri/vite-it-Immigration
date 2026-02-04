/**
 * Role Service
 * Handles all role and permission-related API calls
 */

import api from './api';
import type { Role, Permission, CreateRoleData, UpdateRoleData } from '@/types/role';
import type { PaginationParams } from '@/types/pagination';

export interface RoleWithPermissions extends Role {
    permissions: Permission[];
}

export interface RoleFilters extends PaginationParams {
    search?: string;
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
}

export interface PaginatedRolesResponse {
    data: RoleWithPermissions[];
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number | null;
    to: number | null;
    first_page_url: string | null;
    last_page_url: string | null;
    prev_page_url: string | null;
    next_page_url: string | null;
    path: string;
}

export interface RolesResponse {
    data: RoleWithPermissions[];
}

export interface PermissionsResponse {
    data: Permission[];
    grouped: Record<string, Permission[]>;
}

export interface RoleResponse {
    message: string;
    role: RoleWithPermissions;
}

const roleService = {
    /**
     * Get all roles with their permissions (non-paginated)
     */
    async getRoles(): Promise<RoleWithPermissions[]> {
        const response = await api.get<PaginatedRolesResponse>('/roles');
        return response.data.data;
    },

    /**
     * Get paginated list of roles
     */
    async getRolesPaginated(filters: RoleFilters = {}): Promise<PaginatedRolesResponse> {
        const params = new URLSearchParams();

        if (filters.search) params.append('search', filters.search);
        if (filters.sort_by) params.append('sort_by', filters.sort_by);
        if (filters.sort_direction) params.append('sort_direction', filters.sort_direction);
        if (filters.per_page) params.append('per_page', filters.per_page.toString());
        if (filters.page) params.append('page', filters.page.toString());

        const response = await api.get<PaginatedRolesResponse>(`/roles?${params.toString()}`);
        return response.data;
    },

    /**
     * Get a single role by ID
     */
    async getRole(id: number): Promise<RoleWithPermissions> {
        const response = await api.get<RoleWithPermissions>(`/roles/${id}`);
        return response.data;
    },

    /**
     * Create a new role
     */
    async createRole(data: CreateRoleData): Promise<RoleResponse> {
        const response = await api.post<RoleResponse>('/roles', data);
        return response.data;
    },

    /**
     * Update an existing role
     */
    async updateRole(id: number, data: UpdateRoleData): Promise<RoleResponse> {
        const response = await api.put<RoleResponse>(`/roles/${id}`, data);
        return response.data;
    },

    /**
     * Delete a role
     */
    async deleteRole(id: number): Promise<{ message: string }> {
        const response = await api.delete<{ message: string }>(`/roles/${id}`);
        return response.data;
    },

    /**
     * Get all permissions
     */
    async getPermissions(): Promise<PermissionsResponse> {
        const response = await api.get<PermissionsResponse>('/permissions');
        return response.data;
    },
};

export default roleService;
