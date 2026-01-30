/**
 * Formatters Utility
 * Functions for formatting dates, numbers, and strings
 */

/**
 * Format a date to a localized string
 */
export function formatDate(
    date: string | Date | null | undefined,
    options: Intl.DateTimeFormatOptions = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    },
    locale = 'en-US'
): string {
    if (!date) return '-';
    const d = typeof date === 'string' ? new Date(date) : date;
    if (isNaN(d.getTime())) return '-';
    return d.toLocaleDateString(locale, options);
}

/**
 * Format a date to include time
 */
export function formatDateTime(
    date: string | Date | null | undefined,
    options: Intl.DateTimeFormatOptions = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    },
    locale = 'en-US'
): string {
    return formatDate(date, options, locale);
}

/**
 * Format a date as relative time (e.g., "2 hours ago")
 */
export function formatRelativeTime(date: string | Date | null | undefined, locale = 'en-US'): string {
    if (!date) return '-';
    const d = typeof date === 'string' ? new Date(date) : date;
    if (isNaN(d.getTime())) return '-';

    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - d.getTime()) / 1000);

    const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });

    if (diffInSeconds < 60) {
        return rtf.format(-diffInSeconds, 'second');
    } else if (diffInSeconds < 3600) {
        return rtf.format(-Math.floor(diffInSeconds / 60), 'minute');
    } else if (diffInSeconds < 86400) {
        return rtf.format(-Math.floor(diffInSeconds / 3600), 'hour');
    } else if (diffInSeconds < 2592000) {
        return rtf.format(-Math.floor(diffInSeconds / 86400), 'day');
    } else if (diffInSeconds < 31536000) {
        return rtf.format(-Math.floor(diffInSeconds / 2592000), 'month');
    } else {
        return rtf.format(-Math.floor(diffInSeconds / 31536000), 'year');
    }
}

/**
 * Format a number with locale-specific formatting
 */
export function formatNumber(
    value: number | null | undefined,
    options: Intl.NumberFormatOptions = {},
    locale = 'en-US'
): string {
    if (value === null || value === undefined) return '-';
    return new Intl.NumberFormat(locale, options).format(value);
}

/**
 * Format a number as currency
 */
export function formatCurrency(
    value: number | null | undefined,
    currency = 'USD',
    locale = 'en-US'
): string {
    if (value === null || value === undefined) return '-';
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
    }).format(value);
}

/**
 * Format a number as percentage
 */
export function formatPercentage(
    value: number | null | undefined,
    decimals = 0,
    locale = 'en-US'
): string {
    if (value === null || value === undefined) return '-';
    return new Intl.NumberFormat(locale, {
        style: 'percent',
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(value / 100);
}

/**
 * Format bytes to human readable size
 */
export function formatBytes(bytes: number, decimals = 2): string {
    if (bytes === 0) return '0 Bytes';

    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB'];

    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

/**
 * Truncate a string to a maximum length
 */
export function truncate(str: string, maxLength: number, suffix = '...'): string {
    if (!str) return '';
    if (str.length <= maxLength) return str;
    return str.substring(0, maxLength - suffix.length) + suffix;
}

/**
 * Capitalize the first letter of a string
 */
export function capitalize(str: string): string {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

/**
 * Convert a string to title case
 */
export function titleCase(str: string): string {
    if (!str) return '';
    return str
        .toLowerCase()
        .split(' ')
        .map(word => capitalize(word))
        .join(' ');
}

/**
 * Convert camelCase or snake_case to human readable
 */
export function humanize(str: string): string {
    if (!str) return '';
    return str
        .replace(/([A-Z])/g, ' $1')
        .replace(/_/g, ' ')
        .replace(/^\s+/, '')
        .toLowerCase()
        .replace(/^\w/, c => c.toUpperCase());
}

/**
 * Generate initials from a name
 */
export function getInitials(name: string | null | undefined, maxLength = 2): string {
    if (!name) return '';
    return name
        .split(' ')
        .map(word => word[0])
        .join('')
        .toUpperCase()
        .substring(0, maxLength);
}

/**
 * Format a phone number
 */
export function formatPhone(phone: string | null | undefined): string {
    if (!phone) return '-';
    const cleaned = phone.replace(/\D/g, '');
    if (cleaned.length === 10) {
        return `(${cleaned.slice(0, 3)}) ${cleaned.slice(3, 6)}-${cleaned.slice(6)}`;
    }
    return phone;
}
