/**
 * Role Service
 * Handles all role and permission-related API calls
 */

import api from './api';
import type { Role } from '@/types/user';

export interface Permission {
    id: number;
    name: string;
    guard_name: string;
    created_at: string;
    updated_at: string;
}

export interface RoleWithPermissions extends Role {
    permissions: Permission[];
}

export interface RolesResponse {
    data: RoleWithPermissions[];
}

export interface PermissionsResponse {
    data: Permission[];
    grouped: Record<string, Permission[]>;
}

const roleService = {
    /**
     * Get all roles with their permissions
     */
    async getRoles(): Promise<RoleWithPermissions[]> {
        const response = await api.get<RolesResponse>('/roles');
        return response.data.data;
    },

    /**
     * Get a single role by ID
     */
    async getRole(id: number): Promise<RoleWithPermissions> {
        const response = await api.get<RoleWithPermissions>(`/roles/${id}`);
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
