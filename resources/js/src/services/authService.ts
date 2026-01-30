import api from './api';
import axios from 'axios';

// Role interface
export interface Role {
    id: number;
    name: string;
    guard_name: string;
}

// User interface with roles and permissions
export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    roles?: Role[];
    permissions?: string[];
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
    user?: User;
    two_factor_required?: boolean;
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

export interface EmailVerificationStatus {
    verified: boolean;
    email: string;
}

export interface VerifyEmailParams {
    id: string;
    hash: string;
    expires: string;
    signature: string;
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
     * Get current authenticated user with roles and permissions
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

    // ==================== Email Verification ====================

    /**
     * Send email verification notification
     */
    async sendVerificationEmail(): Promise<MessageResponse> {
        const response = await api.post<MessageResponse>('/email/verification-notification');
        return response.data;
    },

    /**
     * Verify email with signed URL parameters
     */
    async verifyEmail(params: VerifyEmailParams): Promise<MessageResponse & { verified: boolean }> {
        const queryString = new URLSearchParams({
            expires: params.expires,
            signature: params.signature,
        }).toString();

        const response = await api.get<MessageResponse & { verified: boolean }>(
            `/email/verify/${params.id}/${params.hash}?${queryString}`
        );
        return response.data;
    },

    /**
     * Get email verification status
     */
    async getVerificationStatus(): Promise<EmailVerificationStatus> {
        const response = await api.get<EmailVerificationStatus>('/email/verification-status');
        return response.data;
    },
};

export default authService;
