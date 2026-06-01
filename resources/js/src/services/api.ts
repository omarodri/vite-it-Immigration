import axios, { AxiosInstance, AxiosError, InternalAxiosRequestConfig, AxiosResponse } from 'axios';
import { useNotification } from '@/composables/useNotification';
import router from '@/router';
import i18n from '@/i18n';

const t = (key: string) => i18n.global.t(key);

// Error response types
interface ApiErrorResponse {
    message?: string;
    error?: string;
    errors?: Record<string, string[]>;
    reason?: string;
    revoked_at?: string;
    locked_until?: string;
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

// Flag to prevent multiple simultaneous 401 redirects
let isRedirectingToLogin = false;

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
            notification.error(t('api_errors.network'));
            return Promise.reject(error);
        }

        const status = error.response.status;
        const data = error.response.data;

        switch (status) {
            case 401: {
                // Unauthorized - session expired, revoked, or not authenticated.
                // Suppress silently when this is the initial session-restore probe
                // (GET /api/user) and no prior session exists — avoids console noise on
                // login-page load and post-logout redirect.
                const isUserProbe = error.config?.method === 'get'
                    && error.config?.url === '/user'
                    && !localStorage.getItem('has_session');
                if (isUserProbe) {
                    return Promise.reject(error);
                }
                if (!isRedirectingToLogin) {
                    const currentRoute = router.currentRoute.value.name;
                    if (currentRoute !== 'boxed-signin' && currentRoute !== 'cover-login') {
                        isRedirectingToLogin = true;

                        const reason = data?.reason;

                        // Snapshot active timer before clearing state (defensive — backend already
                        // stopped it, but this guards against race conditions)
                        try {
                            const { useTimesheetStore } = await import('@/stores/useTimesheetStore');
                            const timesheetStore = useTimesheetStore();
                            if (timesheetStore.activeTimer) {
                                timesheetStore.persistOfflineSnapshot();
                            }
                            timesheetStore.clearLocal();
                        } catch { /* ignore */ }

                        // Clear auth state FIRST so the router guest-guard
                        // doesn't bounce us back to home (isAuthenticated && to.meta.guest)
                        const { useAuthStore } = await import('@/stores/auth');
                        const authStore = useAuthStore();
                        authStore.$patch({ user: null, isAuthenticated: false });

                        if (reason === 'session_revoked') {
                            // Store kick info for the login page banner and broadcast to other tabs
                            const { useSessionStore, broadcastSessionRevoked } = await import('@/stores/useSessionStore');
                            const sessionStore = useSessionStore();
                            sessionStore.setKickedOut({ revokedAt: data?.revoked_at ?? null });
                            broadcastSessionRevoked(data?.revoked_at ?? null);
                        } else if (reason === 'account_security_locked') {
                            // Account temporarily locked due to kicking abuse detection
                            const { useSessionStore } = await import('@/stores/useSessionStore');
                            const sessionStore = useSessionStore();
                            sessionStore.setAccountLocked({ lockedUntil: data?.locked_until ?? null });
                        } else {
                            notification.warning(t('api_errors.session_expired'));
                        }

                        router.push({ name: 'boxed-signin' }).finally(() => {
                            isRedirectingToLogin = false;
                        });
                    }
                }
                return Promise.reject(error);
            }


            case 403:
                // Forbidden - user doesn't have permission
                if (data?.error === 'email_not_verified') {
                    notification.warning(t('api_errors.verify_email'));
                    router.push({ name: 'email-verification-notice' });
                } else {
                    notification.error(data?.message || t('api_errors.forbidden'));
                }
                break;

            case 404:
                // Not found
                notification.error(data?.message || t('api_errors.not_found'));
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
                        notification.error(t('api_errors.session_expired'));
                        return Promise.reject(csrfError);
                    }
                } else {
                    notification.error(t('api_errors.session_expired'));
                }
                break;

            case 422:
                // Validation error - don't show global notification
                // Let the component handle it
                break;

            case 429:
                // Rate limit exceeded
                notification.warning(
                    data?.message || t('api_errors.too_many_requests')
                );
                break;

            case 500:
                // Server error
                notification.error(t('api_errors.server_error'));
                break;

            case 502:
            case 503:
            case 504:
                // Server unavailable
                notification.error(t('api_errors.service_unavailable'));
                break;

            default:
                // Other errors
                if (status >= 400) {
                    notification.error(data?.message || t('api_errors.unexpected'));
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
