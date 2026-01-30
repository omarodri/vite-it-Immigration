/**
 * Validators Utility
 * Common validation functions for form fields
 */

/**
 * Check if a value is empty (null, undefined, empty string, or empty array)
 */
export function isEmpty(value: unknown): boolean {
    if (value === null || value === undefined) return true;
    if (typeof value === 'string') return value.trim() === '';
    if (Array.isArray(value)) return value.length === 0;
    if (typeof value === 'object') return Object.keys(value).length === 0;
    return false;
}

/**
 * Check if a value is a valid email address
 */
export function isValidEmail(email: string): boolean {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Check if a value is a strong password
 * Requirements: min 8 chars, at least 1 uppercase, 1 lowercase, 1 number
 */
export function isStrongPassword(password: string): boolean {
    const strongPasswordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/;
    return strongPasswordRegex.test(password);
}

/**
 * Get password strength score (0-4)
 */
export function getPasswordStrength(password: string): {
    score: number;
    label: string;
    color: string;
} {
    let score = 0;

    if (!password) {
        return { score: 0, label: 'Empty', color: 'gray' };
    }

    // Length checks
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;

    // Character type checks
    if (/[a-z]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/\d/.test(password)) score++;
    if (/[^a-zA-Z\d]/.test(password)) score++;

    // Normalize score to 0-4
    const normalizedScore = Math.min(4, Math.floor(score / 1.5));

    const labels = ['Very Weak', 'Weak', 'Fair', 'Strong', 'Very Strong'];
    const colors = ['red', 'orange', 'yellow', 'lime', 'green'];

    return {
        score: normalizedScore,
        label: labels[normalizedScore],
        color: colors[normalizedScore],
    };
}

/**
 * Check if a value is a valid phone number
 */
export function isValidPhone(phone: string): boolean {
    const phoneRegex = /^[\d\s\-\(\)\+]{10,}$/;
    return phoneRegex.test(phone);
}

/**
 * Check if a value is a valid URL
 */
export function isValidUrl(url: string): boolean {
    try {
        new URL(url);
        return true;
    } catch {
        return false;
    }
}

/**
 * Check if a value meets minimum length requirement
 */
export function minLength(value: string, min: number): boolean {
    return value.length >= min;
}

/**
 * Check if a value meets maximum length requirement
 */
export function maxLength(value: string, max: number): boolean {
    return value.length <= max;
}

/**
 * Check if a value is within a length range
 */
export function lengthBetween(value: string, min: number, max: number): boolean {
    return value.length >= min && value.length <= max;
}

/**
 * Check if a number is within a range
 */
export function numberBetween(value: number, min: number, max: number): boolean {
    return value >= min && value <= max;
}

/**
 * Check if two values match (useful for password confirmation)
 */
export function matches(value: string, compareTo: string): boolean {
    return value === compareTo;
}

/**
 * Check if a value is alphanumeric
 */
export function isAlphanumeric(value: string): boolean {
    return /^[a-zA-Z0-9]+$/.test(value);
}

/**
 * Check if a value is alphabetic only
 */
export function isAlpha(value: string): boolean {
    return /^[a-zA-Z]+$/.test(value);
}

/**
 * Check if a value is numeric
 */
export function isNumeric(value: string): boolean {
    return /^\d+$/.test(value);
}

/**
 * Check if a value is a valid date
 */
export function isValidDate(dateString: string): boolean {
    const date = new Date(dateString);
    return !isNaN(date.getTime());
}

/**
 * Check if a date is in the past
 */
export function isDateInPast(dateString: string): boolean {
    const date = new Date(dateString);
    return date < new Date();
}

/**
 * Check if a date is in the future
 */
export function isDateInFuture(dateString: string): boolean {
    const date = new Date(dateString);
    return date > new Date();
}

/**
 * Validation result interface
 */
export interface ValidationResult {
    valid: boolean;
    message?: string;
}

/**
 * Create a validator function
 */
export function createValidator(
    validatorFn: (value: unknown) => boolean,
    errorMessage: string
): (value: unknown) => ValidationResult {
    return (value: unknown): ValidationResult => {
        const valid = validatorFn(value);
        return {
            valid,
            message: valid ? undefined : errorMessage,
        };
    };
}

/**
 * Combine multiple validators
 */
export function combineValidators(
    ...validators: Array<(value: unknown) => ValidationResult>
): (value: unknown) => ValidationResult {
    return (value: unknown): ValidationResult => {
        for (const validator of validators) {
            const result = validator(value);
            if (!result.valid) {
                return result;
            }
        }
        return { valid: true };
    };
}

/**
 * Common validators
 */
export const validators = {
    required: createValidator(
        (value) => !isEmpty(value),
        'This field is required'
    ),
    email: createValidator(
        (value) => typeof value === 'string' && isValidEmail(value),
        'Please enter a valid email address'
    ),
    minLength: (min: number) =>
        createValidator(
            (value) => typeof value === 'string' && minLength(value, min),
            `Must be at least ${min} characters`
        ),
    maxLength: (max: number) =>
        createValidator(
            (value) => typeof value === 'string' && maxLength(value, max),
            `Must be no more than ${max} characters`
        ),
    strongPassword: createValidator(
        (value) => typeof value === 'string' && isStrongPassword(value),
        'Password must be at least 8 characters with uppercase, lowercase, and numbers'
    ),
};
