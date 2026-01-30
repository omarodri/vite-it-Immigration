/**
 * API Types
 * Interfaces for API responses and error handling
 */

export interface ApiError {
    message: string;
    error?: string;
    status?: number;
}

export interface ValidationError {
    message: string;
    errors: Record<string, string[]>;
}

export interface ValidationErrors {
    [field: string]: string[];
}

export interface ApiResponse<T = unknown> {
    data: T;
    message?: string;
    status?: number;
}

export interface ApiErrorResponse {
    message: string;
    errors?: ValidationErrors;
    error?: string;
    exception?: string;
    file?: string;
    line?: number;
    trace?: Array<{
        file: string;
        line: number;
        function: string;
        class: string;
    }>;
}

export interface RateLimitError {
    message: string;
    error: 'rate_limit_exceeded';
    retry_after?: number;
}

/**
 * HTTP Status codes commonly used
 */
export const HTTP_STATUS = {
    OK: 200,
    CREATED: 201,
    NO_CONTENT: 204,
    BAD_REQUEST: 400,
    UNAUTHORIZED: 401,
    FORBIDDEN: 403,
    NOT_FOUND: 404,
    UNPROCESSABLE_ENTITY: 422,
    TOO_MANY_REQUESTS: 429,
    INTERNAL_SERVER_ERROR: 500,
    SERVICE_UNAVAILABLE: 503,
} as const;

export type HttpStatus = typeof HTTP_STATUS[keyof typeof HTTP_STATUS];

/**
 * Check if error is a validation error
 */
export function isValidationError(error: unknown): error is { response: { data: ValidationError; status: 422 } } {
    return (
        typeof error === 'object' &&
        error !== null &&
        'response' in error &&
        typeof (error as any).response === 'object' &&
        (error as any).response?.status === 422 &&
        'errors' in ((error as any).response?.data || {})
    );
}

/**
 * Check if error is a rate limit error
 */
export function isRateLimitError(error: unknown): error is { response: { data: RateLimitError; status: 429 } } {
    return (
        typeof error === 'object' &&
        error !== null &&
        'response' in error &&
        (error as any).response?.status === 429
    );
}

/**
 * Check if error is an authentication error
 */
export function isAuthError(error: unknown): boolean {
    return (
        typeof error === 'object' &&
        error !== null &&
        'response' in error &&
        ((error as any).response?.status === 401 || (error as any).response?.status === 419)
    );
}

/**
 * Extract error message from API error response
 */
export function getErrorMessage(error: unknown, defaultMessage = 'An error occurred'): string {
    if (typeof error === 'object' && error !== null) {
        if ('response' in error && typeof (error as any).response?.data === 'object') {
            return (error as any).response.data.message || defaultMessage;
        }
        if ('message' in error) {
            return (error as any).message;
        }
    }
    return defaultMessage;
}

/**
 * Extract validation errors from API error response
 */
export function getValidationErrors(error: unknown): ValidationErrors {
    if (isValidationError(error)) {
        return error.response.data.errors;
    }
    return {};
}
