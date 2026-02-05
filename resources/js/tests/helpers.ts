import type { User, Role } from '@/types/user';
import type { PaginatedResponse, PaginationMeta, PaginationLinks } from '@/types/pagination';

/**
 * Create a mock User with sensible defaults
 */
export function mockUser(overrides: Partial<User> = {}): User {
    return {
        id: 1,
        name: 'John Doe',
        email: 'john@example.com',
        email_verified_at: '2024-01-01T00:00:00.000000Z',
        created_at: '2024-01-01T00:00:00.000000Z',
        updated_at: '2024-01-01T00:00:00.000000Z',
        roles: [{ id: 1, name: 'user' }],
        permissions: ['users.view'],
        ...overrides,
    };
}

/**
 * Create a mock admin User
 */
export function mockAdminUser(overrides: Partial<User> = {}): User {
    return mockUser({
        id: 99,
        name: 'Admin User',
        email: 'admin@example.com',
        roles: [{ id: 1, name: 'admin' }],
        permissions: ['users.view', 'users.create', 'users.edit', 'users.delete'],
        ...overrides,
    });
}

/**
 * Create a mock Role
 */
export function mockRole(overrides: Partial<Role> = {}): Role {
    return {
        id: 1,
        name: 'editor',
        ...overrides,
    };
}

/**
 * Create a mock paginated response matching Laravel's format
 */
export function mockPaginatedResponse<T>(
    data: T[],
    overrides: Partial<{ meta: Partial<PaginationMeta>; links: Partial<PaginationLinks> }> = {}
): PaginatedResponse<T> {
    return {
        data,
        meta: {
            current_page: 1,
            from: data.length > 0 ? 1 : null,
            last_page: 1,
            per_page: 15,
            to: data.length > 0 ? data.length : null,
            total: data.length,
            path: '/api/users',
            ...overrides.meta,
        },
        links: {
            first: '/api/users?page=1',
            last: '/api/users?page=1',
            prev: null,
            next: null,
            ...overrides.links,
        },
    };
}

/**
 * Create a mock Axios error
 */
export function mockAxiosError(status: number, data: Record<string, any> = {}): any {
    const error = new Error('Request failed') as any;
    error.response = {
        status,
        data: {
            message: 'Error occurred',
            ...data,
        },
    };
    error.isAxiosError = true;
    return error;
}
