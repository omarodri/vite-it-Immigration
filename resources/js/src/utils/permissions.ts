/**
 * Permissions Utility
 * Helper functions for role-based access control
 */

import type { User, Role, Permission } from '@/types';
import { ROLES, PERMISSIONS, type RoleName, type PermissionName } from '@/types';

/**
 * Check if a user has a specific role
 */
export function hasRole(user: User | null, role: RoleName | string): boolean {
    if (!user || !user.roles) return false;
    return user.roles.some((r: Role) => r.name === role);
}

/**
 * Check if a user has any of the specified roles
 */
export function hasAnyRole(user: User | null, roles: Array<RoleName | string>): boolean {
    if (!user || !user.roles) return false;
    return roles.some((role) => hasRole(user, role));
}

/**
 * Check if a user has all of the specified roles
 */
export function hasAllRoles(user: User | null, roles: Array<RoleName | string>): boolean {
    if (!user || !user.roles) return false;
    return roles.every((role) => hasRole(user, role));
}

/**
 * Check if a user has a specific permission
 */
export function hasPermission(user: User | null, permission: PermissionName | string): boolean {
    if (!user) return false;

    // Check direct permissions
    if (user.permissions?.includes(permission)) return true;

    // Check role-based permissions
    if (user.roles) {
        for (const role of user.roles) {
            if (role.permissions?.some((p: Permission) => p.name === permission)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Check if a user has any of the specified permissions
 */
export function hasAnyPermission(user: User | null, permissions: Array<PermissionName | string>): boolean {
    if (!user) return false;
    return permissions.some((permission) => hasPermission(user, permission));
}

/**
 * Check if a user has all of the specified permissions
 */
export function hasAllPermissions(user: User | null, permissions: Array<PermissionName | string>): boolean {
    if (!user) return false;
    return permissions.every((permission) => hasPermission(user, permission));
}

/**
 * Check if a user is an admin
 */
export function isAdmin(user: User | null): boolean {
    return hasRole(user, ROLES.ADMIN);
}

/**
 * Check if a user is an editor
 */
export function isEditor(user: User | null): boolean {
    return hasRole(user, ROLES.EDITOR);
}

/**
 * Check if a user can perform an action on a resource
 */
export function can(
    user: User | null,
    action: 'view' | 'create' | 'update' | 'delete',
    resource: string
): boolean {
    const permission = `${resource}.${action}` as PermissionName;
    return hasPermission(user, permission);
}

/**
 * Check if a user can view a resource
 */
export function canView(user: User | null, resource: string): boolean {
    return can(user, 'view', resource);
}

/**
 * Check if a user can create a resource
 */
export function canCreate(user: User | null, resource: string): boolean {
    return can(user, 'create', resource);
}

/**
 * Check if a user can update a resource
 */
export function canUpdate(user: User | null, resource: string): boolean {
    return can(user, 'update', resource);
}

/**
 * Check if a user can delete a resource
 */
export function canDelete(user: User | null, resource: string): boolean {
    return can(user, 'delete', resource);
}

/**
 * Get all role names from a user
 */
export function getRoleNames(user: User | null): string[] {
    if (!user || !user.roles) return [];
    return user.roles.map((role: Role) => role.name);
}

/**
 * Get all permission names from a user
 */
export function getPermissionNames(user: User | null): string[] {
    if (!user) return [];

    const permissions = new Set<string>();

    // Add direct permissions
    if (user.permissions) {
        user.permissions.forEach((p) => permissions.add(p));
    }

    // Add role-based permissions
    if (user.roles) {
        for (const role of user.roles) {
            if (role.permissions) {
                role.permissions.forEach((p: Permission) => permissions.add(p.name));
            }
        }
    }

    return Array.from(permissions);
}

/**
 * Filter a list of items based on user permissions
 */
export function filterByPermission<T extends { permission?: string }>(
    user: User | null,
    items: T[]
): T[] {
    return items.filter((item) => {
        if (!item.permission) return true;
        return hasPermission(user, item.permission);
    });
}

/**
 * Permission checker object for use in templates
 */
export function createPermissionChecker(user: User | null) {
    return {
        can: (action: 'view' | 'create' | 'update' | 'delete', resource: string) =>
            can(user, action, resource),
        hasRole: (role: RoleName | string) => hasRole(user, role),
        hasAnyRole: (roles: Array<RoleName | string>) => hasAnyRole(user, roles),
        hasPermission: (permission: PermissionName | string) => hasPermission(user, permission),
        hasAnyPermission: (permissions: Array<PermissionName | string>) =>
            hasAnyPermission(user, permissions),
        isAdmin: () => isAdmin(user),
        isEditor: () => isEditor(user),
    };
}

export { ROLES, PERMISSIONS };
