/**
 * Todo Service
 * Handles all todo-related API calls
 */

import api from './api';
import type { Todo, CreateTodoData, UpdateTodoData } from '@/types/todo';

const todoService = {
    async getAll(params?: Record<string, any>): Promise<{ data: Todo[]; meta?: { total: number } }> {
        const response = await api.get<{ data: Todo[]; meta?: { total: number } }>('/todos', { params });
        return response.data;
    },

    async get(id: number): Promise<{ data: Todo }> {
        const response = await api.get<{ data: Todo }>(`/todos/${id}`);
        return response.data;
    },

    async create(data: CreateTodoData): Promise<{ data: Todo }> {
        const response = await api.post<{ data: Todo }>('/todos', data);
        return response.data;
    },

    async update(id: number, data: UpdateTodoData): Promise<{ data: Todo }> {
        const response = await api.put<{ data: Todo }>(`/todos/${id}`, data);
        return response.data;
    },

    async remove(id: number): Promise<void> {
        await api.delete(`/todos/${id}`);
    },

    async updateStatus(id: number, status: string): Promise<{ data: Todo }> {
        const response = await api.patch<{ data: Todo }>(`/todos/${id}/status`, { status });
        return response.data;
    },

    async bulkDelete(ids: number[]): Promise<void> {
        await api.delete('/todos/bulk', { data: { ids } });
    },
};

export default todoService;
