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

// Case types
export type {
    CaseStatus,
    CasePriority,
    CaseTypeCategory,
    CaseType,
    ImmigrationCase,
    CreateCaseData,
    UpdateCaseData,
    AssignCaseData,
    CaseFilters,
    CaseStatistics,
    CaseActivityLog,
    CaseResponse,
    CaseDeleteResponse,
} from './case';
export {
    CASE_STATUS_OPTIONS,
    CASE_PRIORITY_OPTIONS,
    CASE_TYPE_CATEGORY_OPTIONS,
    LANGUAGE_OPTIONS,
    CASE_STATUS_LABELS_ES,
    CASE_PRIORITY_LABELS_ES,
    CASE_TYPE_CATEGORY_LABELS_ES,
} from './case';
