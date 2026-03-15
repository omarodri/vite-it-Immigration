/**
 * Scrum Board Store
 * Manages kanban board state: columns, tasks, drag-and-drop operations
 */

import { defineStore } from 'pinia';
import scrumService from '@/services/scrumService';
import type {
    ScrumColumn,
    ScrumTask,
    Assignee,
    CreateScrumTaskData,
    UpdateScrumTaskData,
} from '@/types/scrum';

interface ScrumState {
    columns: ScrumColumn[];
    assignees: Assignee[];
    isLoading: boolean;
    error: string | null;
}

export const useScrumStore = defineStore('scrum', {
    state: (): ScrumState => ({
        columns: [],
        assignees: [],
        isLoading: false,
        error: null,
    }),

    getters: {
        getColumnById: (state) => (id: number) => state.columns.find((c) => c.id === id),

        getTasksByColumn: (state) => (columnId: number) => {
            return state.columns.find((c) => c.id === columnId)?.tasks ?? [];
        },
    },

    actions: {
        // ===============================
        // FETCH OPERATIONS
        // ===============================

        async fetchBoard() {
            this.isLoading = true;
            this.error = null;
            try {
                const res = await scrumService.getBoard();
                this.columns = res.data;
            } catch (e: any) {
                this.error = e?.response?.data?.message ?? 'Error loading board';
            } finally {
                this.isLoading = false;
            }
        },

        async fetchAssignees() {
            if (this.assignees.length > 0) return; // cached
            try {
                const res = await scrumService.getAssignees();
                this.assignees = res.data;
            } catch {
                // silently fail - assignees are optional
            }
        },

        // ===============================
        // COLUMN OPERATIONS
        // ===============================

        async createColumn(title: string): Promise<ScrumColumn | null> {
            try {
                const res = await scrumService.createColumn(title);
                const col = res.data;
                col.tasks = col.tasks ?? [];
                this.columns.push(col);
                return col;
            } catch (e: any) {
                throw e;
            }
        },

        async updateColumn(id: number, title: string): Promise<void> {
            await scrumService.updateColumn(id, title);
            const col = this.columns.find((c) => c.id === id);
            if (col) col.title = title;
        },

        async reorderColumns(columns: ScrumColumn[]): Promise<void> {
            this.columns = columns;
            const payload = columns.map((c, i) => ({
                id: c.id,
                order_index: (i + 1) * 1000,
            }));
            await scrumService.reorderColumns(payload);
        },

        async deleteColumn(id: number): Promise<void> {
            await scrumService.deleteColumn(id);
            this.columns = this.columns.filter((c) => c.id !== id);
        },

        // ===============================
        // TASK OPERATIONS
        // ===============================

        async createTask(data: CreateScrumTaskData): Promise<ScrumTask | null> {
            const res = await scrumService.createTask(data);
            const task = res.data;
            const col = this.columns.find((c) => c.id === task.scrum_column_id);
            if (col) col.tasks.push(task);
            return task;
        },

        async updateTask(id: number, data: UpdateScrumTaskData): Promise<ScrumTask | null> {
            const res = await scrumService.updateTask(id, data);
            const task = res.data;
            // Update task in state
            for (const col of this.columns) {
                const idx = col.tasks.findIndex((t) => t.id === id);
                if (idx !== -1) {
                    col.tasks[idx] = { ...col.tasks[idx], ...task, description_preview: null };
                    break;
                }
            }
            return task;
        },

        async cloneTask(task: ScrumTask): Promise<ScrumTask | null> {
            // Fetch full task to get complete description (board only has description_preview)
            let fullDescription = task.description;
            try {
                const detail = await scrumService.getTask(task.id);
                fullDescription = (detail.data as unknown as ScrumTask).description;
            } catch {}

            const data: CreateScrumTaskData = {
                scrum_column_id: task.scrum_column_id,
                title: `${task.title} (copia)`,
                description: fullDescription ?? undefined,
                tags: task.tags?.length ? [...task.tags] : undefined,
                category: task.category ?? undefined,
                due_date: task.due_date ?? undefined,
                assigned_to_id: task.assigned_to?.id ?? undefined,
                case_id: task.case?.id ?? undefined,
            };
            const res = await scrumService.createTask(data);
            const cloned = res.data;
            const col = this.columns.find((c) => c.id === cloned.scrum_column_id);
            if (col) {
                const sourceIdx = col.tasks.findIndex((t) => t.id === task.id);
                col.tasks.splice(sourceIdx + 1, 0, cloned);
            }
            return cloned;
        },

        async deleteTask(id: number): Promise<void> {
            await scrumService.deleteTask(id);
            for (const col of this.columns) {
                const idx = col.tasks.findIndex((t) => t.id === id);
                if (idx !== -1) {
                    col.tasks.splice(idx, 1);
                    break;
                }
            }
        },

        async toggleTask(taskId: number): Promise<void> {
            // Optimistic update
            for (const col of this.columns) {
                const task = col.tasks.find((t) => t.id === taskId);
                if (task) {
                    task.is_completed = !task.is_completed;
                    break;
                }
            }
            try {
                const res = await scrumService.toggleTask(taskId);
                // Sync with server value
                for (const col of this.columns) {
                    const task = col.tasks.find((t) => t.id === taskId);
                    if (task) {
                        task.is_completed = res.data.is_completed;
                        break;
                    }
                }
            } catch {
                // Revert optimistic update
                for (const col of this.columns) {
                    const task = col.tasks.find((t) => t.id === taskId);
                    if (task) {
                        task.is_completed = !task.is_completed;
                        break;
                    }
                }
            }
        },

        async moveTask(taskId: number, targetColumnId: number, position: number): Promise<void> {
            // Find task and source column
            let task: ScrumTask | null = null;
            let sourceCol: ScrumColumn | null = null;

            for (const col of this.columns) {
                const idx = col.tasks.findIndex((t) => t.id === taskId);
                if (idx !== -1) {
                    task = col.tasks[idx];
                    sourceCol = col;
                    break;
                }
            }
            if (!task || !sourceCol) return;

            // Snapshot for revert on failure
            const snapshot = JSON.parse(JSON.stringify(this.columns)) as ScrumColumn[];

            // Optimistic: move in state
            sourceCol.tasks = sourceCol.tasks.filter((t) => t.id !== taskId);
            const targetCol = this.columns.find((c) => c.id === targetColumnId);
            if (targetCol) {
                task = { ...task, scrum_column_id: targetColumnId };
                targetCol.tasks.splice(position, 0, task);
            }

            try {
                await scrumService.moveTask(taskId, {
                    scrum_column_id: targetColumnId,
                    position,
                });
            } catch {
                // Revert on failure
                this.columns = snapshot;
            }
        },
    },
});
