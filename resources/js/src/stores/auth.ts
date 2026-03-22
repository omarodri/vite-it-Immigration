import { defineStore } from 'pinia';
import authService, {
    User,
    Role,
    LoginCredentials,
    RegisterData,
    ForgotPasswordData,
    ResetPasswordData,
    VerifyEmailParams
} from '@/services/authService';
import twoFactorService from '@/services/twoFactorService';
import type { TwoFactorChallengeData } from '@/services/twoFactorService';

interface AuthState {
    user: User | null;
    isAuthenticated: boolean;
    isLoading: boolean;
    error: string | null;
    passwordResetMessage: string | null;
    emailVerificationMessage: string | null;
    twoFactorRequired: boolean;
}

export const useAuthStore = defineStore('auth', {
    state: (): AuthState => ({
        user: null,
        isAuthenticated: false,
        isLoading: false,
        error: null,
        passwordResetMessage: null,
        emailVerificationMessage: null,
        twoFactorRequired: false,
    }),

    getters: {
        getUser: (state) => state.user,
        isLoggedIn: (state) => state.isAuthenticated,

        avatarUrl: (state): string | null => state.user?.profile?.avatar_url || null,

        initials: (state): string => {
            if (!state.user?.name) return '';
            return state.user.name
                .split(' ')
                .map((n) => n[0])
                .join('')
                .toUpperCase()
                .slice(0, 2);
        },

        // Email verification getters
        isEmailVerified: (state) => !!state.user?.email_verified_at,

        // Role getters
        roles: (state): string[] => {
            return state.user?.roles?.map(role => role.name) || [];
        },

        permissions: (state): string[] => {
            return state.user?.permissions || [];
        },

        hasRole: (state) => (role: string): boolean => {
            const roles = state.user?.roles?.map(r => r.name) || [];
            return roles.includes(role);
        },

        hasAnyRole: (state) => (roles: string[]): boolean => {
            const userRoles = state.user?.roles?.map(r => r.name) || [];
            return roles.some(role => userRoles.includes(role));
        },

        hasPermission: (state) => (permission: string): boolean => {
            // Admin has all permissions
            const roles = state.user?.roles?.map(r => r.name) || [];
            if (roles.includes('admin')) return true;

            const permissions = state.user?.permissions || [];
            return permissions.includes(permission);
        },

        hasAnyPermission: (state) => (permissions: string[]): boolean => {
            // Admin has all permissions
            const roles = state.user?.roles?.map(r => r.name) || [];
            if (roles.includes('admin')) return true;

            const userPermissions = state.user?.permissions || [];
            return permissions.some(perm => userPermissions.includes(perm));
        },

        hasAllPermissions: (state) => (permissions: string[]): boolean => {
            // Admin has all permissions
            const roles = state.user?.roles?.map(r => r.name) || [];
            if (roles.includes('admin')) return true;

            const userPermissions = state.user?.permissions || [];
            return permissions.every(perm => userPermissions.includes(perm));
        },

        isAdmin: (state): boolean => {
            const roles = state.user?.roles?.map(r => r.name) || [];
            return roles.includes('admin');
        },
    },

    actions: {
        async login(credentials: LoginCredentials) {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await authService.login(credentials);

                // Check if 2FA is required
                if (response.two_factor_required) {
                    this.twoFactorRequired = true;
                    return response;
                }

                this.user = response.user ?? null;
                this.isAuthenticated = true;

                // Fetch tenant data after successful login
                const { useTenantStore } = await import('@/stores/tenant');
                const tenantStore = useTenantStore();
                await tenantStore.fetchTenant();

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
                this.user = response.user ?? null;
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
                this.twoFactorRequired = false;

                // Clear tenant data on logout
                const { useTenantStore } = await import('@/stores/tenant');
                const tenantStore = useTenantStore();
                tenantStore.clearTenant();
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

                // Fetch tenant data if not already loaded
                const { useTenantStore } = await import('@/stores/tenant');
                const tenantStore = useTenantStore();
                if (!tenantStore.isLoaded) {
                    await tenantStore.fetchTenant();
                }
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

        clearEmailVerificationMessage() {
            this.emailVerificationMessage = null;
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

        // ==================== Email Verification Actions ====================

        async sendVerificationEmail() {
            this.isLoading = true;
            this.error = null;
            this.emailVerificationMessage = null;
            try {
                const response = await authService.sendVerificationEmail();
                this.emailVerificationMessage = response.message;
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to send verification email';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async verifyEmail(params: VerifyEmailParams) {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await authService.verifyEmail(params);
                // Refresh user data after verification
                if (response.verified && this.user) {
                    this.user.email_verified_at = new Date().toISOString();
                }
                this.emailVerificationMessage = response.message;
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Failed to verify email';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async checkVerificationStatus() {
            try {
                const response = await authService.getVerificationStatus();
                if (this.user) {
                    this.user.email_verified_at = response.verified ? new Date().toISOString() : null;
                }
                return response;
            } catch (error: any) {
                console.error('Failed to check verification status:', error);
                throw error;
            }
        },

        // ==================== Two-Factor Authentication Actions ====================

        async verifyTwoFactor(data: TwoFactorChallengeData) {
            this.isLoading = true;
            this.error = null;
            try {
                const response = await twoFactorService.challenge(data);
                if (response.user) {
                    this.user = response.user;
                    this.isAuthenticated = true;
                    this.twoFactorRequired = false;

                    // Fetch tenant data after successful 2FA verification
                    const { useTenantStore } = await import('@/stores/tenant');
                    const tenantStore = useTenantStore();
                    await tenantStore.fetchTenant();
                }
                return response;
            } catch (error: any) {
                this.error = error.response?.data?.message || 'Two-factor verification failed';
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        cancelTwoFactor() {
            this.twoFactorRequired = false;
            this.error = null;
        },
    },
});
