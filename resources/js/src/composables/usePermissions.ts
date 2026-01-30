import { computed } from 'vue';
import { useAuthStore } from '@/stores/auth';

/**
 * Composable for permission and role checks
 *
 * Usage:
 * ```typescript
 * const { can, hasRole, isAdmin, roles, permissions } = usePermissions();
 *
 * if (can('users.view')) {
 *   // User has permission
 * }
 *
 * if (hasRole('admin')) {
 *   // User has role
 * }
 * ```
 */
export function usePermissions() {
    const authStore = useAuthStore();

    // Computed properties
    const roles = computed(() => authStore.roles);
    const permissions = computed(() => authStore.permissions);
    const isAdmin = computed(() => authStore.isAdmin);
    const isAuthenticated = computed(() => authStore.isAuthenticated);
    const isEmailVerified = computed(() => authStore.isEmailVerified);

    /**
     * Check if user has a specific permission
     */
    const can = (permission: string): boolean => {
        return authStore.hasPermission(permission);
    };

    /**
     * Check if user has any of the specified permissions
     */
    const canAny = (permissions: string[]): boolean => {
        return authStore.hasAnyPermission(permissions);
    };

    /**
     * Check if user has all of the specified permissions
     */
    const canAll = (permissions: string[]): boolean => {
        return authStore.hasAllPermissions(permissions);
    };

    /**
     * Check if user has a specific role
     */
    const hasRole = (role: string): boolean => {
        return authStore.hasRole(role);
    };

    /**
     * Check if user has any of the specified roles
     */
    const hasAnyRole = (roles: string[]): boolean => {
        return authStore.hasAnyRole(roles);
    };

    /**
     * Check if user is authenticated and has verified email
     */
    const isVerifiedUser = computed(() => {
        return authStore.isAuthenticated && authStore.isEmailVerified;
    });

    return {
        // Computed
        roles,
        permissions,
        isAdmin,
        isAuthenticated,
        isEmailVerified,
        isVerifiedUser,

        // Methods
        can,
        canAny,
        canAll,
        hasRole,
        hasAnyRole,
    };
}

export default usePermissions;
