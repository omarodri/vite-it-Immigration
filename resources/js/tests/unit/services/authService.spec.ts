import { describe, it, expect, vi, beforeEach } from 'vitest';
import authService from '@/services/authService';
import api from '@/services/api';
import axios from 'axios';

describe('Auth Service', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('login', () => {
        it('calls getCsrfCookie then POST /login', async () => {
            const credentials = { email: 'john@example.com', password: 'password' };
            const responseData = { message: 'Success', user: { id: 1, name: 'John' } };

            vi.mocked(axios.get).mockResolvedValue({});
            vi.mocked(api.post).mockResolvedValue({ data: responseData });

            const result = await authService.login(credentials);

            expect(axios.get).toHaveBeenCalledWith('/sanctum/csrf-cookie', { withCredentials: true });
            expect(api.post).toHaveBeenCalledWith('/login', credentials);
            expect(result).toEqual(responseData);
        });
    });

    describe('register', () => {
        it('calls getCsrfCookie then POST /register', async () => {
            const data = {
                name: 'John',
                email: 'john@example.com',
                password: 'password',
                password_confirmation: 'password',
            };
            const responseData = { message: 'Registered', user: { id: 1, name: 'John' } };

            vi.mocked(axios.get).mockResolvedValue({});
            vi.mocked(api.post).mockResolvedValue({ data: responseData });

            const result = await authService.register(data);

            expect(axios.get).toHaveBeenCalledWith('/sanctum/csrf-cookie', { withCredentials: true });
            expect(api.post).toHaveBeenCalledWith('/register', data);
            expect(result).toEqual(responseData);
        });
    });

    describe('logout', () => {
        it('calls POST /logout', async () => {
            vi.mocked(api.post).mockResolvedValue({ data: {} });

            await authService.logout();

            expect(api.post).toHaveBeenCalledWith('/logout');
        });
    });

    describe('getUser', () => {
        it('calls GET /user and returns user data', async () => {
            const userData = { id: 1, name: 'John', email: 'john@example.com' };
            vi.mocked(api.get).mockResolvedValue({ data: userData });

            const result = await authService.getUser();

            expect(api.get).toHaveBeenCalledWith('/user');
            expect(result).toEqual(userData);
        });
    });

    describe('forgotPassword', () => {
        it('calls getCsrfCookie then POST /forgot-password', async () => {
            vi.mocked(axios.get).mockResolvedValue({});
            vi.mocked(api.post).mockResolvedValue({ data: { message: 'Link sent' } });

            const result = await authService.forgotPassword({ email: 'john@example.com' });

            expect(axios.get).toHaveBeenCalledWith('/sanctum/csrf-cookie', { withCredentials: true });
            expect(api.post).toHaveBeenCalledWith('/forgot-password', { email: 'john@example.com' });
            expect(result).toEqual({ message: 'Link sent' });
        });
    });

    describe('resetPassword', () => {
        it('calls getCsrfCookie then POST /reset-password', async () => {
            const data = {
                token: 'abc123',
                email: 'john@example.com',
                password: 'newpass',
                password_confirmation: 'newpass',
            };

            vi.mocked(axios.get).mockResolvedValue({});
            vi.mocked(api.post).mockResolvedValue({ data: { message: 'Password reset' } });

            const result = await authService.resetPassword(data);

            expect(axios.get).toHaveBeenCalledWith('/sanctum/csrf-cookie', { withCredentials: true });
            expect(api.post).toHaveBeenCalledWith('/reset-password', data);
            expect(result).toEqual({ message: 'Password reset' });
        });
    });

    describe('sendVerificationEmail', () => {
        it('calls POST /email/verification-notification', async () => {
            vi.mocked(api.post).mockResolvedValue({ data: { message: 'Verification sent' } });

            const result = await authService.sendVerificationEmail();

            expect(api.post).toHaveBeenCalledWith('/email/verification-notification');
            expect(result).toEqual({ message: 'Verification sent' });
        });
    });

    describe('verifyEmail', () => {
        it('calls GET /email/verify/{id}/{hash} with query params', async () => {
            const params = {
                id: '1',
                hash: 'abc123',
                expires: '1234567890',
                signature: 'sig123',
            };

            vi.mocked(api.get).mockResolvedValue({
                data: { message: 'Verified', verified: true },
            });

            const result = await authService.verifyEmail(params);

            expect(api.get).toHaveBeenCalledWith(
                '/email/verify/1/abc123?expires=1234567890&signature=sig123'
            );
            expect(result).toEqual({ message: 'Verified', verified: true });
        });
    });
});
