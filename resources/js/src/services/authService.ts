import api from './api';
import axios from 'axios';

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface LoginCredentials {
    email: string;
    password: string;
}

export interface RegisterData {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export interface AuthResponse {
    message: string;
    user: User;
}

export interface ForgotPasswordData {
    email: string;
}

export interface ResetPasswordData {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export interface MessageResponse {
    message: string;
}

export interface TokenVerifyResponse {
    valid: boolean;
    message: string;
}

const authService = {
    /**
     * Get CSRF cookie from Sanctum
     */
    async getCsrfCookie(): Promise<void> {
        await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
    },

    /**
     * Login user
     */
    async login(credentials: LoginCredentials): Promise<AuthResponse> {
        await this.getCsrfCookie();
        const response = await api.post<AuthResponse>('/login', credentials);
        return response.data;
    },

    /**
     * Register new user
     */
    async register(data: RegisterData): Promise<AuthResponse> {
        await this.getCsrfCookie();
        const response = await api.post<AuthResponse>('/register', data);
        return response.data;
    },

    /**
     * Logout user
     */
    async logout(): Promise<void> {
        await api.post('/logout');
    },

    /**
     * Get current authenticated user
     */
    async getUser(): Promise<User> {
        const response = await api.get<User>('/user');
        return response.data;
    },

    /**
     * Send password reset link to email
     */
    async forgotPassword(data: ForgotPasswordData): Promise<MessageResponse> {
        await this.getCsrfCookie();
        const response = await api.post<MessageResponse>('/forgot-password', data);
        return response.data;
    },

    /**
     * Reset password with token
     */
    async resetPassword(data: ResetPasswordData): Promise<MessageResponse> {
        await this.getCsrfCookie();
        const response = await api.post<MessageResponse>('/reset-password', data);
        return response.data;
    },

    /**
     * Verify password reset token
     */
    async verifyResetToken(token: string, email: string): Promise<TokenVerifyResponse> {
        const response = await api.get<TokenVerifyResponse>(`/verify-token/${token}/${encodeURIComponent(email)}`);
        return response.data;
    },
};

export default authService;
