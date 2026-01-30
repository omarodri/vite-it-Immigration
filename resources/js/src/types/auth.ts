/**
 * Authentication Types
 * Interfaces for authentication-related data structures
 */

import type { User } from './user';

export interface LoginCredentials {
    email: string;
    password: string;
    remember?: boolean;
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

export interface TwoFactorChallengeData {
    code?: string;
    recovery_code?: string;
}

export interface TwoFactorResponse {
    message: string;
    user?: User;
    two_factor_required?: boolean;
}

export interface TwoFactorSetupResponse {
    qr_code: string;
    secret: string;
    recovery_codes: string[];
}

export interface AuthState {
    user: User | null;
    isAuthenticated: boolean;
    isLoading: boolean;
    error: string | null;
    passwordResetMessage: string | null;
    twoFactorRequired: boolean;
}
