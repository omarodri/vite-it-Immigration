/**
 * Storage Utility
 * Wrapper for localStorage and sessionStorage with type safety
 */

/**
 * Storage keys used in the application
 */
export const STORAGE_KEYS = {
    AUTH_TOKEN: 'auth_token',
    USER: 'user',
    THEME: 'theme',
    LOCALE: 'locale',
    SIDEBAR_COLLAPSED: 'sidebar_collapsed',
    RECENT_SEARCHES: 'recent_searches',
    TABLE_PREFERENCES: 'table_preferences',
} as const;

export type StorageKey = typeof STORAGE_KEYS[keyof typeof STORAGE_KEYS];

/**
 * Check if storage is available
 */
function isStorageAvailable(type: 'localStorage' | 'sessionStorage'): boolean {
    try {
        const storage = window[type];
        const testKey = '__storage_test__';
        storage.setItem(testKey, testKey);
        storage.removeItem(testKey);
        return true;
    } catch {
        return false;
    }
}

/**
 * LocalStorage wrapper with JSON serialization
 */
export const storage = {
    /**
     * Get an item from localStorage
     */
    get<T>(key: string, defaultValue?: T): T | null {
        if (!isStorageAvailable('localStorage')) {
            return defaultValue ?? null;
        }
        try {
            const item = localStorage.getItem(key);
            if (item === null) {
                return defaultValue ?? null;
            }
            return JSON.parse(item) as T;
        } catch {
            return defaultValue ?? null;
        }
    },

    /**
     * Set an item in localStorage
     */
    set<T>(key: string, value: T): boolean {
        if (!isStorageAvailable('localStorage')) {
            return false;
        }
        try {
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch {
            return false;
        }
    },

    /**
     * Remove an item from localStorage
     */
    remove(key: string): boolean {
        if (!isStorageAvailable('localStorage')) {
            return false;
        }
        try {
            localStorage.removeItem(key);
            return true;
        } catch {
            return false;
        }
    },

    /**
     * Clear all items from localStorage
     */
    clear(): boolean {
        if (!isStorageAvailable('localStorage')) {
            return false;
        }
        try {
            localStorage.clear();
            return true;
        } catch {
            return false;
        }
    },

    /**
     * Check if a key exists in localStorage
     */
    has(key: string): boolean {
        if (!isStorageAvailable('localStorage')) {
            return false;
        }
        return localStorage.getItem(key) !== null;
    },

    /**
     * Get all keys from localStorage
     */
    keys(): string[] {
        if (!isStorageAvailable('localStorage')) {
            return [];
        }
        return Object.keys(localStorage);
    },
};

/**
 * SessionStorage wrapper with JSON serialization
 */
export const sessionStorage = {
    /**
     * Get an item from sessionStorage
     */
    get<T>(key: string, defaultValue?: T): T | null {
        if (!isStorageAvailable('sessionStorage')) {
            return defaultValue ?? null;
        }
        try {
            const item = window.sessionStorage.getItem(key);
            if (item === null) {
                return defaultValue ?? null;
            }
            return JSON.parse(item) as T;
        } catch {
            return defaultValue ?? null;
        }
    },

    /**
     * Set an item in sessionStorage
     */
    set<T>(key: string, value: T): boolean {
        if (!isStorageAvailable('sessionStorage')) {
            return false;
        }
        try {
            window.sessionStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch {
            return false;
        }
    },

    /**
     * Remove an item from sessionStorage
     */
    remove(key: string): boolean {
        if (!isStorageAvailable('sessionStorage')) {
            return false;
        }
        try {
            window.sessionStorage.removeItem(key);
            return true;
        } catch {
            return false;
        }
    },

    /**
     * Clear all items from sessionStorage
     */
    clear(): boolean {
        if (!isStorageAvailable('sessionStorage')) {
            return false;
        }
        try {
            window.sessionStorage.clear();
            return true;
        } catch {
            return false;
        }
    },

    /**
     * Check if a key exists in sessionStorage
     */
    has(key: string): boolean {
        if (!isStorageAvailable('sessionStorage')) {
            return false;
        }
        return window.sessionStorage.getItem(key) !== null;
    },
};

/**
 * Cookie utilities
 */
export const cookies = {
    /**
     * Get a cookie value
     */
    get(name: string): string | null {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) {
            return parts.pop()?.split(';').shift() ?? null;
        }
        return null;
    },

    /**
     * Set a cookie
     */
    set(
        name: string,
        value: string,
        options: {
            days?: number;
            path?: string;
            domain?: string;
            secure?: boolean;
            sameSite?: 'Strict' | 'Lax' | 'None';
        } = {}
    ): void {
        const {
            days = 7,
            path = '/',
            domain,
            secure = true,
            sameSite = 'Lax',
        } = options;

        let cookie = `${encodeURIComponent(name)}=${encodeURIComponent(value)}`;

        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
            cookie += `; expires=${date.toUTCString()}`;
        }

        cookie += `; path=${path}`;
        if (domain) cookie += `; domain=${domain}`;
        if (secure) cookie += '; secure';
        cookie += `; samesite=${sameSite}`;

        document.cookie = cookie;
    },

    /**
     * Remove a cookie
     */
    remove(name: string, path = '/'): void {
        document.cookie = `${encodeURIComponent(name)}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=${path}`;
    },
};

export default storage;
