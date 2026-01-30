/**
 * Role and Permission Types
 * Interfaces for RBAC (Role-Based Access Control)
 */

export interface Role {
    id: number;
    name: string;
    display_name: string;
    description: string | null;
    guard_name: string;
    created_at: string;
    updated_at: string;
    permissions?: Permission[];
}

export interface Permission {
    id: number;
    name: string;
    display_name: string;
    description: string | null;
    guard_name: string;
    created_at: string;
    updated_at: string;
}

export interface CreateRoleData {
    name: string;
    display_name: string;
    description?: string;
    permissions?: number[];
}

export interface UpdateRoleData {
    name?: string;
    display_name?: string;
    description?: string;
    permissions?: number[];
}

export interface AssignRoleData {
    user_id: number;
    role_ids: number[];
}

export interface RoleListResponse {
    data: Role[];
}

export interface PermissionListResponse {
    data: Permission[];
}

/**
 * Permission groups for UI organization
 */
export interface PermissionGroup {
    name: string;
    display_name: string;
    permissions: Permission[];
}

/**
 * Standard permission names used in the system
 */
export const PERMISSIONS = {
    // Users
    USERS_VIEW: 'users.view',
    USERS_CREATE: 'users.create',
    USERS_UPDATE: 'users.update',
    USERS_DELETE: 'users.delete',

    // Roles
    ROLES_VIEW: 'roles.view',
    ROLES_CREATE: 'roles.create',
    ROLES_UPDATE: 'roles.update',
    ROLES_DELETE: 'roles.delete',

    // Profile
    PROFILE_VIEW: 'profile.view',
    PROFILE_UPDATE: 'profile.update',

    // Settings
    SETTINGS_VIEW: 'settings.view',
    SETTINGS_UPDATE: 'settings.update',

    // Activity Logs
    ACTIVITY_LOGS_VIEW: 'activity-logs.view',
} as const;

export type PermissionName = typeof PERMISSIONS[keyof typeof PERMISSIONS];

/**
 * Standard role names
 */
export const ROLES = {
    ADMIN: 'admin',
    EDITOR: 'editor',
    USER: 'user',
} as const;

export type RoleName = typeof ROLES[keyof typeof ROLES];
