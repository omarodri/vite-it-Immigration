/**
 * Scrum Board Service
 * Handles all scrum board-related API calls
 */

import api from './api';
import type {
    ScrumColumn,
    ScrumTask,
    Assignee,
    CreateScrumTaskData,
    UpdateScrumTaskData,
    MoveScrumTaskData,
} from '@/types/scrum';

const scrumService = {
    // ===============================
    // BOARD
    // ===============================

    /**
     * Get full board with columns and tasks
     */
    async getBoard(): Promise<{ data: ScrumColumn[] }> {
        const response = await api.get<{ data: ScrumColumn[] }>('/scrum/board');
        return response.data;
    },

    /**
     * Get available assignees
     */
    async getAssignees(): Promise<{ data: Assignee[] }> {
        const response = await api.get<{ data: Assignee[] }>('/scrum/assignees');
        return response.data;
    },

    // ===============================
    // COLUMNS
    // ===============================

    /**
     * Create a new column
     */
    async createColumn(title: string): Promise<{ data: ScrumColumn }> {
        const response = await api.post<{ data: ScrumColumn }>('/scrum/columns', { title });
        return response.data;
    },

    /**
     * Update column title
     */
    async updateColumn(id: number, title: string): Promise<{ data: ScrumColumn }> {
        const response = await api.patch<{ data: ScrumColumn }>(`/scrum/columns/${id}`, { title });
        return response.data;
    },

    /**
     * Reorder columns
     */
    async reorderColumns(columns: { id: number; order_index: number }[]): Promise<void> {
        await api.patch('/scrum/columns/reorder', { columns });
    },

    /**
     * Delete a column (must be empty)
     */
    async deleteColumn(id: number): Promise<void> {
        await api.delete(`/scrum/columns/${id}`);
    },

    // ===============================
    // TASKS
    // ===============================

    /**
     * Create a new task
     */
    async createTask(data: CreateScrumTaskData): Promise<{ data: ScrumTask }> {
        const response = await api.post<{ data: ScrumTask }>('/scrum/tasks', data);
        return response.data;
    },

    /**
     * Get a single task by ID
     */
    async getTask(id: number): Promise<{ data: ScrumTask }> {
        const response = await api.get<{ data: ScrumTask }>(`/scrum/tasks/${id}`);
        return response.data;
    },

    /**
     * Update an existing task
     */
    async updateTask(id: number, data: UpdateScrumTaskData): Promise<{ data: ScrumTask }> {
        const response = await api.put<{ data: ScrumTask }>(`/scrum/tasks/${id}`, data);
        return response.data;
    },

    /**
     * Delete a task
     */
    async deleteTask(id: number): Promise<void> {
        await api.delete(`/scrum/tasks/${id}`);
    },

    /**
     * Move a task to a different column and/or position
     */
    async moveTask(id: number, data: MoveScrumTaskData): Promise<void> {
        await api.patch(`/scrum/tasks/${id}/move`, data);
    },

    /**
     * Toggle is_completed status
     */
    async toggleTask(id: number): Promise<{ data: { id: number; is_completed: boolean } }> {
        const response = await api.patch<{ data: { id: number; is_completed: boolean } }>(`/scrum/tasks/${id}/toggle`);
        return response.data;
    },
};

export default scrumService;
