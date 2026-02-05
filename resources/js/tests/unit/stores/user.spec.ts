import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useUserStore } from '@/stores/user';
import userService from '@/services/userService';
import roleService from '@/services/roleService';
import { mockUser, mockPaginatedResponse, mockAxiosError } from '../../helpers';

vi.mock('@/services/userService', () => ({
    default: {
        getUsers: vi.fn(),
        getUser: vi.fn(),
        createUser: vi.fn(),
        updateUser: vi.fn(),
        deleteUser: vi.fn(),
        bulkDeleteUsers: vi.fn(),
    },
}));

vi.mock('@/services/roleService', () => ({
    default: {
        getRoles: vi.fn(),
    },
}));

describe('User Store', () => {
    let store: ReturnType<typeof useUserStore>;

    beforeEach(() => {
        store = useUserStore();
        vi.clearAllMocks();
    });

    // ==================== Initial State ====================

    describe('initial state', () => {
        it('has correct default values', () => {
            expect(store.users).toEqual([]);
            expect(store.currentUser).toBeNull();
            expect(store.roles).toEqual([]);
            expect(store.meta).toBeNull();
            expect(store.links).toBeNull();
            expect(store.isLoading).toBe(false);
            expect(store.error).toBeNull();
            expect(store.filters).toEqual({
                search: '',
                role: '',
                sort_by: 'created_at',
                sort_direction: 'desc',
                per_page: 15,
                page: 1,
            });
        });
    });

    // ==================== Fetch Users ====================

    describe('fetchUsers', () => {
        it('fetches users and sets state correctly', async () => {
            const users = [mockUser({ id: 1 }), mockUser({ id: 2, name: 'Jane Doe' })];
            const response = mockPaginatedResponse(users, {
                meta: { total: 2 },
            });

            vi.mocked(userService.getUsers).mockResolvedValue(response);

            await store.fetchUsers();

            expect(userService.getUsers).toHaveBeenCalledWith(store.filters);
            expect(store.users).toEqual(users);
            expect(store.meta).toEqual(response.meta);
            expect(store.links).toEqual(response.links);
            expect(store.isLoading).toBe(false);
        });

        it('merges partial filters with existing ones', async () => {
            const response = mockPaginatedResponse([]);
            vi.mocked(userService.getUsers).mockResolvedValue(response);

            await store.fetchUsers({ search: 'test', page: 2 });

            expect(store.filters.search).toBe('test');
            expect(store.filters.page).toBe(2);
            expect(store.filters.sort_by).toBe('created_at'); // unchanged
            expect(userService.getUsers).toHaveBeenCalledWith(
                expect.objectContaining({ search: 'test', page: 2 })
            );
        });

        it('handles Laravel flat pagination format', async () => {
            const flatResponse = {
                data: [mockUser()],
                current_page: 1,
                from: 1,
                last_page: 3,
                per_page: 15,
                to: 15,
                total: 45,
                path: '/api/users',
                first_page_url: '/api/users?page=1',
                last_page_url: '/api/users?page=3',
                prev_page_url: null,
                next_page_url: '/api/users?page=2',
            };

            vi.mocked(userService.getUsers).mockResolvedValue(flatResponse as any);

            await store.fetchUsers();

            expect(store.meta).toEqual({
                current_page: 1,
                from: 1,
                last_page: 3,
                per_page: 15,
                to: 15,
                total: 45,
                path: '/api/users',
            });
            expect(store.links).toEqual({
                first: '/api/users?page=1',
                last: '/api/users?page=3',
                prev: null,
                next: '/api/users?page=2',
            });
        });
    });

    // ==================== Fetch Single User ====================

    describe('fetchUser', () => {
        it('fetches a single user and sets currentUser', async () => {
            const user = mockUser({ id: 5, name: 'Specific User' });
            vi.mocked(userService.getUser).mockResolvedValue(user);

            const result = await store.fetchUser(5);

            expect(userService.getUser).toHaveBeenCalledWith(5);
            expect(store.currentUser).toEqual(user);
            expect(result).toEqual(user);
            expect(store.isLoading).toBe(false);
        });
    });

    // ==================== Create User ====================

    describe('createUser', () => {
        it('creates a user and refreshes the list', async () => {
            const newUserData = {
                name: 'New User',
                email: 'new@example.com',
                password: 'password123',
                password_confirmation: 'password123',
            };
            const createResponse = { message: 'User created', user: mockUser({ id: 10, name: 'New User' }) };

            vi.mocked(userService.createUser).mockResolvedValue(createResponse);
            vi.mocked(userService.getUsers).mockResolvedValue(mockPaginatedResponse([]));

            await store.createUser(newUserData);

            expect(userService.createUser).toHaveBeenCalledWith(newUserData);
            expect(userService.getUsers).toHaveBeenCalled(); // refresh
        });
    });

    // ==================== Delete User (EMPHASIS) ====================

    describe('deleteUser', () => {
        it('removes user from list on success', async () => {
            // Pre-populate store
            store.users = [
                mockUser({ id: 1, name: 'Alice' }),
                mockUser({ id: 2, name: 'Bob' }),
                mockUser({ id: 3, name: 'Charlie' }),
            ];
            store.meta = {
                current_page: 1, from: 1, last_page: 1,
                per_page: 15, to: 3, total: 10, path: '/api/users',
            };

            vi.mocked(userService.deleteUser).mockResolvedValue({ message: 'User deleted' });

            await store.deleteUser(2);

            expect(userService.deleteUser).toHaveBeenCalledWith(2);
            expect(store.users).toHaveLength(2);
            expect(store.users.map(u => u.id)).toEqual([1, 3]);
        });

        it('filters the correct user from the list', async () => {
            store.users = [
                mockUser({ id: 1, name: 'Alice' }),
                mockUser({ id: 2, name: 'Bob' }),
                mockUser({ id: 3, name: 'Charlie' }),
            ];
            store.meta = {
                current_page: 1, from: 1, last_page: 1,
                per_page: 15, to: 3, total: 3, path: '/api/users',
            };

            vi.mocked(userService.deleteUser).mockResolvedValue({ message: 'Deleted' });

            await store.deleteUser(2);

            expect(store.users.find(u => u.id === 2)).toBeUndefined();
            expect(store.users.find(u => u.id === 1)).toBeDefined();
            expect(store.users.find(u => u.id === 3)).toBeDefined();
        });

        it('decrements meta.total after successful deletion', async () => {
            store.users = [mockUser({ id: 1 })];
            store.meta = {
                current_page: 1, from: 1, last_page: 1,
                per_page: 15, to: 1, total: 10, path: '/api/users',
            };

            vi.mocked(userService.deleteUser).mockResolvedValue({ message: 'Deleted' });

            await store.deleteUser(1);

            expect(store.meta!.total).toBe(9);
        });

        it('does not modify list on failure and throws', async () => {
            const originalUsers = [
                mockUser({ id: 1 }),
                mockUser({ id: 2 }),
            ];
            store.users = [...originalUsers];
            store.meta = {
                current_page: 1, from: 1, last_page: 1,
                per_page: 15, to: 2, total: 2, path: '/api/users',
            };

            const error = mockAxiosError(403, { message: 'Forbidden' });
            vi.mocked(userService.deleteUser).mockRejectedValue(error);

            await expect(store.deleteUser(1)).rejects.toThrow();

            expect(store.users).toHaveLength(2);
            expect(store.error).toBe('Forbidden');
            expect(store.meta!.total).toBe(2);
        });

        it('clears isLoading in both success and error cases', async () => {
            store.users = [mockUser({ id: 1 })];
            store.meta = {
                current_page: 1, from: 1, last_page: 1,
                per_page: 15, to: 1, total: 1, path: '/api/users',
            };

            // Success case
            vi.mocked(userService.deleteUser).mockResolvedValue({ message: 'Deleted' });
            await store.deleteUser(1);
            expect(store.isLoading).toBe(false);

            // Error case
            const error = mockAxiosError(500, { message: 'Server error' });
            vi.mocked(userService.deleteUser).mockRejectedValue(error);
            await expect(store.deleteUser(999)).rejects.toThrow();
            expect(store.isLoading).toBe(false);
        });
    });

    // ==================== Bulk Delete Users (EMPHASIS) ====================

    describe('bulkDeleteUsers', () => {
        it('calls service and refreshes list on success', async () => {
            vi.mocked(userService.bulkDeleteUsers).mockResolvedValue({ message: 'Users deleted' });
            vi.mocked(userService.getUsers).mockResolvedValue(mockPaginatedResponse([]));

            await store.bulkDeleteUsers([1, 2, 3]);

            expect(userService.bulkDeleteUsers).toHaveBeenCalledWith([1, 2, 3]);
            expect(userService.getUsers).toHaveBeenCalled(); // refresh
        });

        it('sets error and throws on failure', async () => {
            const error = mockAxiosError(500, { message: 'Bulk delete failed' });
            vi.mocked(userService.bulkDeleteUsers).mockRejectedValue(error);

            await expect(store.bulkDeleteUsers([1, 2])).rejects.toThrow();

            expect(store.error).toBe('Bulk delete failed');
            expect(store.isLoading).toBe(false);
        });
    });

    // ==================== Filters and Pagination ====================

    describe('setSearch', () => {
        it('updates search and resets page to 1', () => {
            store.filters.page = 5;

            store.setSearch('john');

            expect(store.filters.search).toBe('john');
            expect(store.filters.page).toBe(1);
        });
    });

    describe('setRoleFilter', () => {
        it('updates role and resets page to 1', () => {
            store.filters.page = 3;

            store.setRoleFilter('admin');

            expect(store.filters.role).toBe('admin');
            expect(store.filters.page).toBe(1);
        });
    });

    describe('setSort', () => {
        it('updates sort_by and sort_direction', () => {
            store.setSort('name', 'asc');

            expect(store.filters.sort_by).toBe('name');
            expect(store.filters.sort_direction).toBe('asc');
        });
    });

    describe('resetFilters', () => {
        it('restores all filters to defaults', () => {
            store.filters.search = 'test';
            store.filters.role = 'admin';
            store.filters.page = 5;
            store.filters.sort_by = 'name';
            store.filters.sort_direction = 'asc';

            store.resetFilters();

            expect(store.filters).toEqual({
                search: '',
                role: '',
                sort_by: 'created_at',
                sort_direction: 'desc',
                per_page: 15,
                page: 1,
            });
        });
    });

    // ==================== Getters ====================

    describe('getUserById', () => {
        it('returns the correct user', () => {
            store.users = [
                mockUser({ id: 1, name: 'Alice' }),
                mockUser({ id: 2, name: 'Bob' }),
            ];

            expect(store.getUserById(2)?.name).toBe('Bob');
        });

        it('returns undefined for non-existent id', () => {
            store.users = [mockUser({ id: 1 })];

            expect(store.getUserById(999)).toBeUndefined();
        });
    });
});
