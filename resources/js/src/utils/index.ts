/**
 * Utils Index
 * Re-export all utilities from a single entry point
 */

// Formatters
export {
    formatDate,
    formatDateTime,
    formatRelativeTime,
    formatNumber,
    formatCurrency,
    formatPercentage,
    formatBytes,
    truncate,
    capitalize,
    titleCase,
    humanize,
    getInitials,
    formatPhone,
} from './formatters';

// Validators
export {
    isEmpty,
    isValidEmail,
    isStrongPassword,
    getPasswordStrength,
    isValidPhone,
    isValidUrl,
    minLength,
    maxLength,
    lengthBetween,
    numberBetween,
    matches,
    isAlphanumeric,
    isAlpha,
    isNumeric,
    isValidDate,
    isDateInPast,
    isDateInFuture,
    createValidator,
    combineValidators,
    validators,
} from './validators';
export type { ValidationResult } from './validators';

// Storage
export {
    storage,
    sessionStorage,
    cookies,
    STORAGE_KEYS,
} from './storage';
export type { StorageKey } from './storage';

// Permissions
export {
    hasRole,
    hasAnyRole,
    hasAllRoles,
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,
    isAdmin,
    isEditor,
    can,
    canView,
    canCreate,
    canUpdate,
    canDelete,
    getRoleNames,
    getPermissionNames,
    filterByPermission,
    createPermissionChecker,
    ROLES,
    PERMISSIONS,
} from './permissions';
