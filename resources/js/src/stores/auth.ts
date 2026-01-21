import { defineStore } from 'pinia';
import authService, { User, LoginCredentials, RegisterData, ForgotPasswordData, ResetPasswordData } from '@/services/authService';

interface AuthState {
    user: User | null;
    isAuthenticated: boolean;
    isLoading: boolean;
    error: string | null;
    passwordResetMessage: string | null;
}

export const useAuthStore = defineStore('auth', {
    state: (): AuthState => ({
        user: null,
        isAuthenticated: false,
        isLoading: false,
        error: null,
        passwordResetMessage: null,
    }),

    getters: {
        getUser: (state) => state.user,
        isLoggedIn: (state) => state.isAuthenticated,
    },

    actions: {
        async login(credentials: LoginCredentials) {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await authService.login(credentials);
                this.user = response.user;
                this.isAuthenticated = true;
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Login failed';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async register(data: RegisterData) {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await authService.register(data);
                this.user = response.user;
                this.isAuthenticated = true;
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Registration failed';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async logout() {
            this.isLoading = true;
            try {
                await authService.logout();
                this.user = null;
                this.isAuthenticated = false;
            } catch (error: any) {
                console.error('Logout error:', error);
            } finally {
                this.isLoading = false;
            }
        },

        async fetchUser() {
            this.isLoading = true;
            try {
                const user = await authService.getUser();
                this.user = user;
                this.isAuthenticated = true;
            } catch (error) {
                this.user = null;
                this.isAuthenticated = false;
            } finally {
                this.isLoading = false;
            }
        },

        clearError() {
            this.error = null;
        },

        clearPasswordResetMessage() {
            this.passwordResetMessage = null;
        },

        async forgotPassword(data: ForgotPasswordData) {
            this.isLoading = true;
            this.error = null;
            this.passwordResetMessage = null;
            try {
                const response = await authService.forgotPassword(data);
                this.passwordResetMessage = response.message;
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to send reset link';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async resetPassword(data: ResetPasswordData) {
            this.isLoading = true;
            this.error = null;
            this.passwordResetMessage = null;
            try {
                const response = await authService.resetPassword(data);
                this.passwordResetMessage = response.message;
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to reset password';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async verifyResetToken(token: string, email: string) {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await authService.verifyResetToken(token, email);
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Invalid or expired token';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },
    },
});
