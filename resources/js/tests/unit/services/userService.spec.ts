import { describe, it, expect, vi, beforeEach } from 'vitest';
import userService from '@/services/userService';
import api from '@/services/api';

describe('User Service', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('getUsers', () => {
        it('calls GET /users with default params', async () => {
            const responseData = { data: [], meta: {}, links: {} };
            vi.mocked(api.get).mockResolvedValue({ data: responseData });

            const result = await userService.getUsers();

            expect(api.get).toHaveBeenCalledWith('/users?');
            expect(result).toEqual(responseData);
        });

        it('builds query params correctly from filters', async () => {
            const responseData = { data: [], meta: {}, links: {} };
            vi.mocked(api.get).mockResolvedValue({ data: responseData });

            await userService.getUsers({
                search: 'john',
                role: 'admin',
                sort_by: 'name',
                sort_direction: 'asc',
                per_page: 10,
                page: 2,
            });

            const calledUrl = vi.mocked(api.get).mock.calls[0][0];
            expect(calledUrl).toContain('search=john');
            expect(calledUrl).toContain('role=admin');
            expect(calledUrl).toContain('sort_by=name');
            expect(calledUrl).toContain('sort_direction=asc');
            expect(calledUrl).toContain('per_page=10');
            expect(calledUrl).toContain('page=2');
        });
    });

    describe('getUser', () => {
        it('calls GET /users/{id}', async () => {
            const user = { id: 5, name: 'John' };
            vi.mocked(api.get).mockResolvedValue({ data: user });

            const result = await userService.getUser(5);

            expect(api.get).toHaveBeenCalledWith('/users/5');
            expect(result).toEqual(user);
        });
    });

    describe('createUser', () => {
        it('calls POST /users with data', async () => {
            const userData = {
                name: 'New User',
                email: 'new@example.com',
                password: 'password123',
                password_confirmation: 'password123',
            };
            const responseData = { message: 'Created', user: { id: 10, ...userData } };
            vi.mocked(api.post).mockResolvedValue({ data: responseData });

            const result = await userService.createUser(userData);

            expect(api.post).toHaveBeenCalledWith('/users', userData);
            expect(result).toEqual(responseData);
        });
    });

    describe('updateUser', () => {
        it('calls PUT /users/{id} with data', async () => {
            const updateData = { name: 'Updated Name' };
            const responseData = { message: 'Updated', user: { id: 1, name: 'Updated Name' } };
            vi.mocked(api.put).mockResolvedValue({ data: responseData });

            const result = await userService.updateUser(1, updateData);

            expect(api.put).toHaveBeenCalledWith('/users/1', updateData);
            expect(result).toEqual(responseData);
        });
    });

    // ==================== Delete (EMPHASIS) ====================

    describe('deleteUser', () => {
        it('calls DELETE /users/{id} and returns message', async () => {
            vi.mocked(api.delete).mockResolvedValue({ data: { message: 'User deleted' } });

            const result = await userService.deleteUser(3);

            expect(api.delete).toHaveBeenCalledWith('/users/3');
            expect(result).toEqual({ message: 'User deleted' });
        });
    });

    describe('bulkDeleteUsers', () => {
        it('calls DELETE /users/bulk with ids in data', async () => {
            vi.mocked(api.delete).mockResolvedValue({ data: { message: 'Users deleted' } });

            const result = await userService.bulkDeleteUsers([1, 2, 3]);

            expect(api.delete).toHaveBeenCalledWith('/users/bulk', {
                data: { ids: [1, 2, 3] },
            });
            expect(result).toEqual({ message: 'Users deleted' });
        });

        it('passes the complete array of IDs', async () => {
            vi.mocked(api.delete).mockResolvedValue({ data: { message: 'Deleted' } });

            const ids = [10, 20, 30, 40, 50];
            await userService.bulkDeleteUsers(ids);

            const callArgs = vi.mocked(api.delete).mock.calls[0];
            expect(callArgs[1]).toEqual({ data: { ids: [10, 20, 30, 40, 50] } });
        });
    });
});
