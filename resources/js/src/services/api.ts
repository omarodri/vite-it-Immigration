import axios, { AxiosInstance, AxiosError, InternalAxiosRequestConfig, AxiosResponse } from 'axios';
import { useNotification } from '@/composables/useNotification';
import router from '@/router';

// Error response types
interface ApiErrorResponse {
    message?: string;
    error?: string;
    errors?: Record<string, string[]>;
}

// Helper to get CSRF token from cookie
function getCsrfTokenFromCookie(): string | null {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    if (match) {
        return decodeURIComponent(match[1]);
    }
    return null;
}

// Flag to prevent multiple simultaneous CSRF fetches
let csrfFetchPromise: Promise<void> | null = null;

// Ensure CSRF cookie exists
async function ensureCsrfCookie(): Promise<void> {
    if (getCsrfTokenFromCookie()) {
        return; // Already have the cookie
    }

    // If already fetching, wait for that
    if (csrfFetchPromise) {
        return csrfFetchPromise;
    }

    // Fetch new CSRF cookie
    csrfFetchPromise = axios.get('/sanctum/csrf-cookie', { withCredentials: true })
        .then(() => { csrfFetchPromise = null; })
        .catch(() => { csrfFetchPromise = null; });

    return csrfFetchPromise;
}

// Create axios instance
const api: AxiosInstance = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
});

// Request interceptor to add CSRF token
api.interceptors.request.use(
    async (config: InternalAxiosRequestConfig) => {
        // For mutation requests (POST, PUT, PATCH, DELETE), ensure CSRF cookie exists
        const method = config.method?.toUpperCase();
        if (method && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
            await ensureCsrfCookie();
        }

        // Try cookie first (Sanctum SPA style), fallback to meta tag
        const cookieToken = getCsrfTokenFromCookie();
        const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        if (cookieToken) {
            config.headers['X-XSRF-TOKEN'] = cookieToken;
        } else if (metaToken) {
            config.headers['X-CSRF-TOKEN'] = metaToken;
        }
        return config;
    },
    (error: AxiosError) => Promise.reject(error)
);

// Response interceptor for centralized error handling
api.interceptors.response.use(
    (response: AxiosResponse) => response,
    async (error: AxiosError<ApiErrorResponse>) => {
        const originalRequest = error.config;
        const notification = useNotification();

        // No response - network error
        if (!error.response) {
            notification.error('Network error. Please check your connection.');
            return Promise.reject(error);
        }

        const status = error.response.status;
        const data = error.response.data;

        switch (status) {
            case 401:
                // Unauthorized - session expired or not authenticated
                // Redirect to login; reject so fetchUser() and the router guard can complete
                if (router.currentRoute.value.name !== 'boxed-signin' &&
                    router.currentRoute.value.name !== 'cover-login') {
                    router.push({ name: 'boxed-signin' });
                }
                return Promise.reject(error);


            case 403:
                // Forbidden - user doesn't have permission
                if (data?.error === 'email_not_verified') {
                    notification.warning('Please verify your email address.');
                    router.push({ name: 'email-verification-notice' });
                } else {
                    notification.error(data?.message || 'You do not have permission to perform this action.');
                }
                break;

            case 404:
                // Not found
                notification.error(data?.message || 'The requested resource was not found.');
                break;

            case 419:
                // CSRF token expired - refresh and retry once
                if (originalRequest && !(originalRequest as any)._retry) {
                    (originalRequest as any)._retry = true;
                    try {
                        await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
                        // Update the token in the original request headers
                        const newToken = getCsrfTokenFromCookie();
                        if (newToken && originalRequest.headers) {
                            originalRequest.headers['X-XSRF-TOKEN'] = newToken;
                        }
                        return api.request(originalRequest);
                    } catch (csrfError) {
                        notification.error('Session expired. Please refresh the page.');
                        return Promise.reject(csrfError);
                    }
                } else {
                    notification.error('Session expired. Please refresh the page.');
                }
                break;

            case 422:
                // Validation error - don't show global notification
                // Let the component handle it
                break;

            case 429:
                // Rate limit exceeded
                notification.warning(
                    data?.message || 'Too many requests. Please wait a moment and try again.'
                );
                break;

            case 500:
                // Server error
                notification.error('Server error. Please try again later.');
                break;

            case 502:
            case 503:
            case 504:
                // Server unavailable
                notification.error('Service temporarily unavailable. Please try again later.');
                break;

            default:
                // Other errors
                if (status >= 400) {
                    notification.error(data?.message || 'An unexpected error occurred.');
                }
        }

        return Promise.reject(error);
    }
);

export default api;

// Export a function to get fresh CSRF cookie
export async function refreshCsrfToken(): Promise<void> {
    await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}
