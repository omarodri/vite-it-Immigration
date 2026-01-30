/**
 * Types Index
 * Re-export all types from a single entry point
 */

// User types
export type {
    User,
    UserProfile,
    UserWithProfile,
    CreateUserData,
    UpdateUserData,
    UpdateProfileData,
    ChangePasswordData,
} from './user';

// Auth types
export type {
    LoginCredentials,
    RegisterData,
    AuthResponse,
    ForgotPasswordData,
    ResetPasswordData,
    MessageResponse,
    TokenVerifyResponse,
    TwoFactorChallengeData,
    TwoFactorResponse,
    TwoFactorSetupResponse,
    AuthState,
} from './auth';

// Pagination types
export type {
    PaginationParams,
    PaginationMeta,
    PaginationLinks,
    PaginatedResponse,
    SimplePaginatedResponse,
    PaginationState,
} from './pagination';
export { DEFAULT_PAGINATION } from './pagination';

// Role and Permission types
export type {
    Role,
    Permission,
    CreateRoleData,
    UpdateRoleData,
    AssignRoleData,
    RoleListResponse,
    PermissionListResponse,
    PermissionGroup,
    PermissionName,
    RoleName,
} from './role';
export { PERMISSIONS, ROLES } from './role';

// API types
export type {
    ApiError,
    ValidationError,
    ValidationErrors,
    ApiResponse,
    ApiErrorResponse,
    RateLimitError,
    HttpStatus,
} from './api';
export {
    HTTP_STATUS,
    isValidationError,
    isRateLimitError,
    isAuthError,
    getErrorMessage,
    getValidationErrors,
} from './api';
