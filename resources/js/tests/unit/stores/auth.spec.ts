import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useAuthStore } from '@/stores/auth';
import authService from '@/services/authService';
import twoFactorService from '@/services/twoFactorService';
import { mockUser, mockAdminUser, mockAxiosError } from '../../helpers';

vi.mock('@/services/authService', () => ({
    default: {
        login: vi.fn(),
        register: vi.fn(),
        logout: vi.fn(),
        getUser: vi.fn(),
        getCsrfCookie: vi.fn(),
        forgotPassword: vi.fn(),
        resetPassword: vi.fn(),
        verifyResetToken: vi.fn(),
        sendVerificationEmail: vi.fn(),
        verifyEmail: vi.fn(),
        getVerificationStatus: vi.fn(),
    },
}));

vi.mock('@/services/twoFactorService', () => ({
    default: {
        enable: vi.fn(),
        confirm: vi.fn(),
        disable: vi.fn(),
        getRecoveryCodes: vi.fn(),
        regenerateRecoveryCodes: vi.fn(),
        challenge: vi.fn(),
    },
}));

describe('Auth Store', () => {
    let store: ReturnType<typeof useAuthStore>;

    beforeEach(() => {
        store = useAuthStore();
        vi.clearAllMocks();
    });

    // ==================== Initial State ====================

    describe('initial state', () => {
        it('has correct default values', () => {
            expect(store.user).toBeNull();
            expect(store.isAuthenticated).toBe(false);
            expect(store.isLoading).toBe(false);
            expect(store.error).toBeNull();
            expect(store.passwordResetMessage).toBeNull();
            expect(store.emailVerificationMessage).toBeNull();
            expect(store.twoFactorRequired).toBe(false);
        });
    });

    // ==================== Login ====================

    describe('login', () => {
        it('sets user and isAuthenticated on success', async () => {
            const user = mockUser();
            vi.mocked(authService.login).mockResolvedValue({
                message: 'Login successful',
                user,
            });

            await store.login({ email: 'john@example.com', password: 'password' });

            expect(authService.login).toHaveBeenCalledWith({
                email: 'john@example.com',
                password: 'password',
            });
            expect(store.user).toEqual(user);
            expect(store.isAuthenticated).toBe(true);
            expect(store.isLoading).toBe(false);
        });

        it('sets twoFactorRequired when 2FA is needed', async () => {
            vi.mocked(authService.login).mockResolvedValue({
                message: 'Two factor required',
                two_factor_required: true,
            });

            await store.login({ email: 'john@example.com', password: 'password' });

            expect(store.twoFactorRequired).toBe(true);
            expect(store.user).toBeNull();
            expect(store.isAuthenticated).toBe(false);
        });

        it('sets error and throws on failure', async () => {
            const error = mockAxiosError(422, { message: 'Invalid credentials' });
            vi.mocked(authService.login).mockRejectedValue(error);

            await expect(
                store.login({ email: 'bad@example.com', password: 'wrong' })
            ).rejects.toThrow();

            expect(store.error).toBe('Invalid credentials');
            expect(store.user).toBeNull();
            expect(store.isAuthenticated).toBe(false);
            expect(store.isLoading).toBe(false);
        });
    });

    // ==================== Logout ====================

    describe('logout', () => {
        it('clears user state', async () => {
            // Set up authenticated state
            store.user = mockUser();
            store.isAuthenticated = true;
            store.twoFactorRequired = true;

            vi.mocked(authService.logout).mockResolvedValue();

            await store.logout();

            expect(authService.logout).toHaveBeenCalled();
            expect(store.user).toBeNull();
            expect(store.isAuthenticated).toBe(false);
            expect(store.twoFactorRequired).toBe(false);
            expect(store.isLoading).toBe(false);
        });
    });

    // ==================== Fetch User ====================

    describe('fetchUser', () => {
        it('sets user and isAuthenticated on success', async () => {
            const user = mockUser();
            vi.mocked(authService.getUser).mockResolvedValue(user);

            await store.fetchUser();

            expect(authService.getUser).toHaveBeenCalled();
            expect(store.user).toEqual(user);
            expect(store.isAuthenticated).toBe(true);
            expect(store.isLoading).toBe(false);
        });

        it('clears user on failure without throwing', async () => {
            vi.mocked(authService.getUser).mockRejectedValue(new Error('Unauthorized'));

            await store.fetchUser(); // Should not throw

            expect(store.user).toBeNull();
            expect(store.isAuthenticated).toBe(false);
            expect(store.isLoading).toBe(false);
        });
    });

    // ==================== Permission Getters ====================

    describe('hasPermission', () => {
        it('returns true when user has the permission', () => {
            store.user = mockUser({ permissions: ['users.view', 'users.create'] });

            expect(store.hasPermission('users.view')).toBe(true);
            expect(store.hasPermission('users.create')).toBe(true);
        });

        it('returns false when user lacks the permission', () => {
            store.user = mockUser({ permissions: ['users.view'] });

            expect(store.hasPermission('users.delete')).toBe(false);
        });

        it('returns true for any permission when user is admin', () => {
            store.user = mockAdminUser();

            expect(store.hasPermission('anything.whatever')).toBe(true);
            expect(store.hasPermission('nonexistent.permission')).toBe(true);
        });
    });

    describe('hasAnyPermission', () => {
        it('returns true if user has at least one matching permission', () => {
            store.user = mockUser({ permissions: ['users.view'] });

            expect(store.hasAnyPermission(['users.view', 'users.delete'])).toBe(true);
        });

        it('returns false if user has none of the permissions', () => {
            store.user = mockUser({ permissions: ['users.view'] });

            expect(store.hasAnyPermission(['users.create', 'users.delete'])).toBe(false);
        });

        it('returns true for admin regardless of permissions', () => {
            store.user = mockAdminUser();

            expect(store.hasAnyPermission(['nonexistent.perm'])).toBe(true);
        });
    });

    describe('isAdmin', () => {
        it('returns true when user has admin role', () => {
            store.user = mockAdminUser();

            expect(store.isAdmin).toBe(true);
        });

        it('returns false when user does not have admin role', () => {
            store.user = mockUser({ roles: [{ id: 2, name: 'editor' }] });

            expect(store.isAdmin).toBe(false);
        });
    });

    // ==================== Two-Factor Authentication ====================

    describe('verifyTwoFactor', () => {
        it('sets user and clears twoFactorRequired on success', async () => {
            const user = mockUser();
            store.twoFactorRequired = true;

            vi.mocked(twoFactorService.challenge).mockResolvedValue({
                message: 'Verified',
                user,
            });

            await store.verifyTwoFactor({ code: '123456' });

            expect(twoFactorService.challenge).toHaveBeenCalledWith({ code: '123456' });
            expect(store.user).toEqual(user);
            expect(store.isAuthenticated).toBe(true);
            expect(store.twoFactorRequired).toBe(false);
            expect(store.isLoading).toBe(false);
        });
    });
});
