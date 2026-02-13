/**
 * User Service
 * Handles all user-related API calls
 */

import api from './api';
import type { User, CreateUserData, UpdateUserData } from '@/types/user';
import type { PaginatedResponse, PaginationParams } from '@/types/pagination';
import type { StaffMember } from '@/types/wizard';

export interface UserFilters extends PaginationParams {
    search?: string;
    role?: string;
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
}

export interface UserResponse {
    message: string;
    user: User;
}

const userService = {
    /**
     * Get paginated list of users
     */
    async getUsers(filters: UserFilters = {}): Promise<PaginatedResponse<User>> {
        const params = new URLSearchParams();

        if (filters.search) params.append('search', filters.search);
        if (filters.role) params.append('role', filters.role);
        if (filters.sort_by) params.append('sort_by', filters.sort_by);
        if (filters.sort_direction) params.append('sort_direction', filters.sort_direction);
        if (filters.per_page) params.append('per_page', filters.per_page.toString());
        if (filters.page) params.append('page', filters.page.toString());

        const response = await api.get<PaginatedResponse<User>>(`/users?${params.toString()}`);
        return response.data;
    },

    /**
     * Get a single user by ID
     */
    async getUser(id: number): Promise<User> {
        const response = await api.get<User>(`/users/${id}`);
        return response.data;
    },

    /**
     * Create a new user
     */
    async createUser(data: CreateUserData): Promise<UserResponse> {
        const response = await api.post<UserResponse>('/users', data);
        return response.data;
    },

    /**
     * Update an existing user
     */
    async updateUser(id: number, data: UpdateUserData): Promise<UserResponse> {
        const response = await api.put<UserResponse>(`/users/${id}`, data);
        return response.data;
    },

    /**
     * Delete a user
     */
    async deleteUser(id: number): Promise<{ message: string }> {
        const response = await api.delete<{ message: string }>(`/users/${id}`);
        return response.data;
    },

    /**
     * Bulk delete users
     */
    async bulkDeleteUsers(ids: number[]): Promise<{ message: string }> {
        const response = await api.delete<{ message: string }>('/users/bulk', {
            data: { ids },
        });
        return response.data;
    },

    /**
     * Get staff members available for case assignment
     */
    async getStaff(): Promise<StaffMember[]> {
        const response = await api.get<{ data: StaffMember[] }>('/users/staff');
        return response.data.data;
    },
};

export default userService;
