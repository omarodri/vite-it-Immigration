import axios, { AxiosInstance, AxiosError, InternalAxiosRequestConfig } from 'axios';

const api: AxiosInstance = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
});

// Request interceptor to add CSRF token
api.interceptors.request.use(
    (config: InternalAxiosRequestConfig) => {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (token) {
            config.headers['X-CSRF-TOKEN'] = token;
        }
        return config;
    },
    (error: AxiosError) => Promise.reject(error)
);

// Response interceptor for error handling
api.interceptors.response.use(
    (response) => response,
    async (error: AxiosError) => {
        const originalRequest = error.config;

        if (error.response?.status === 401) {
            // Handle unauthorized - will be managed by router guard
            return Promise.reject(error);
        }

        if (error.response?.status === 419 && originalRequest) {
            // CSRF token expired - refresh and retry
            try {
                await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
                return api.request(originalRequest);
            } catch (csrfError) {
                return Promise.reject(csrfError);
            }
        }

        return Promise.reject(error);
    }
);

export default api;
