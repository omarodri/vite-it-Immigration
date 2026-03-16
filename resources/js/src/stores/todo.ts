/**
 * Todo Store
 * Manages todo list state: tasks, filtering, pagination
 */

import { defineStore } from 'pinia';
import todoService from '@/services/todoService';
import api from '@/services/api';
import type { Todo, CreateTodoData, UpdateTodoData, CaseOption } from '@/types/todo';
import type { Assignee } from '@/types/scrum';

interface TodoState {
    todos: Todo[];
    total: number;
    currentPage: number;
    perPage: number;
    isLoading: boolean;
    assignees: Assignee[];
    cases: CaseOption[];
}

export const useTodoStore = defineStore('todo', {
    state: (): TodoState => ({
        todos: [],
        total: 0,
        currentPage: 1,
        perPage: 10,
        isLoading: false,
        assignees: [],
        cases: [],
    }),

    actions: {
        async fetchTodos(params?: Record<string, any>) {
            this.isLoading = true;
            try {
                const res = await todoService.getAll({
                    per_page: this.perPage,
                    page: this.currentPage,
                    ...params,
                });
                this.todos = res.data;
                this.total = res.meta?.total ?? res.data.length;
            } finally {
                this.isLoading = false;
            }
        },

        async fetchAssignees() {
            if (this.assignees.length > 0) return;
            try {
                const res = await api.get('/scrum/assignees');
                this.assignees = res.data.data;
            } catch {
                // silently fail - assignees are optional
            }
        },

        async fetchCases(search?: string) {
            try {
                const res = await api.get('/cases', {
                    params: { status: 'active', per_page: 100, search },
                });
                this.cases = (res.data.data ?? []).map((c: any) => ({
                    id: c.id,
                    case_number: c.case_number ?? c.id,
                    client_name: c.client?.full_name ?? c.client_name ?? null,
                }));
            } catch {
                // silently fail - cases are optional
            }
        },

        async createTodo(data: CreateTodoData): Promise<Todo> {
            const res = await todoService.create(data);
            return res.data;
        },

        async updateTodo(id: number, data: UpdateTodoData): Promise<Todo> {
            const res = await todoService.update(id, data);
            return res.data;
        },

        async deleteTodo(id: number): Promise<void> {
            await todoService.remove(id);
        },

        async updateStatus(id: number, status: string): Promise<Todo> {
            const res = await todoService.updateStatus(id, status);
            const idx = this.todos.findIndex((t) => t.id === id);
            if (idx !== -1) this.todos[idx] = res.data;
            return res.data;
        },

        async bulkDelete(ids: number[]): Promise<void> {
            await todoService.bulkDelete(ids);
        },
    },
});
