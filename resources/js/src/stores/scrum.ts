/**
 * Scrum Board Store
 * Manages board state (columns, tasks, drag-and-drop operations) and the UI state shared
 * by the board and list views (Spec 71): view mode, filters, collapsed sections.
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

export type ScrumViewMode = 'board' | 'list';

interface ScrumState {
    columns: ScrumColumn[];
    assignees: Assignee[];
    isLoading: boolean;
    error: string | null;

    // UI state (Spec 71) — never sent to the backend
    viewMode: ScrumViewMode;
    /** Assignee filter; null = everyone */
    filterUserId: number | null;
    /** "Show completed" filter — completed tasks are hidden by default */
    showCompleted: boolean;
    collapsedColumnIds: number[];
    /** Tasks just marked completed, kept visible during the grace period before hiding */
    pendingHideTaskIds: number[];
    /**
     * Tasks just created/edited that do not match the assignee filter: kept visible so a saved
     * task never seems to vanish. Cleared when the filter changes, on reload and on logout.
     */
    pinnedTaskIds: number[];
    /** User whose localStorage preferences are loaded (keys are per user) */
    prefsUserId: number | null;
}

export interface ScrumColumnView {
    /** Tasks shown with the current filters, in board order */
    visible: ScrumTask[];
    /** All tasks in the column */
    total: number;
    /** Tasks passing the assignee filter (visible + hidden as completed) */
    matching: number;
    /** Tasks passing the assignee filter but hidden because they are completed */
    hiddenCompleted: number;
}

const EMPTY_TASKS: ScrumTask[] = [];

const viewModeKey = (userId: number) => `scrum.viewMode.${userId}`;
const collapsedKey = (userId: number) => `scrum.collapsed.${userId}`;

function readStorage(key: string): string | null {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function writeStorage(key: string, value: string): void {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Storage unavailable (private mode / blocked): keep working in memory
    }
}

/** Below Tailwind's `md` breakpoint the list view is the default (Spec 71 R9.3). */
export function isSmallScreen(): boolean {
    try {
        return window.matchMedia('(max-width: 767px)').matches;
    } catch {
        return false;
    }
}

export const useScrumStore = defineStore('scrum', {
    state: (): ScrumState => ({
        columns: [],
        assignees: [],
        isLoading: false,
        error: null,

        viewMode: 'board',
        filterUserId: null,
        showCompleted: false,
        collapsedColumnIds: [],
        pendingHideTaskIds: [],
        pinnedTaskIds: [],
        prefsUserId: null,
    }),

    getters: {
        getColumnById: (state) => (id: number) => state.columns.find((c) => c.id === id),

        getTasksByColumn: (state) => (columnId: number) => {
            return state.columns.find((c) => c.id === columnId)?.tasks ?? [];
        },

        findTask: (state) => (taskId: number): ScrumTask | undefined => {
            for (const col of state.columns) {
                const task = col.tasks.find((t) => t.id === taskId);
                if (task) return task;
            }
            return undefined;
        },

        /** Assignee filter predicate. */
        matchesAssignee: (state) => (task: ScrumTask): boolean =>
            state.filterUserId === null || task.assigned_to?.id === state.filterUserId || state.pinnedTaskIds.includes(task.id),

        /** The task is excluded by the assignee filter itself (ignoring pins). */
        isFilteredOutByAssignee: (state) => (task: ScrumTask): boolean =>
            state.filterUserId !== null && task.assigned_to?.id !== state.filterUserId,

        /** Completed filter predicate: completed, filter on, and not in its grace period. */
        isHiddenAsCompleted: (state) => (task: ScrumTask): boolean =>
            !!task.is_completed && !state.showCompleted && !state.pendingHideTaskIds.includes(task.id),

        /**
         * Single visibility predicate. Used both to render the lists and to compute drop
         * positions, so they can never diverge.
         */
        isTaskVisible(): (task: ScrumTask) => boolean {
            return (task: ScrumTask) => this.matchesAssignee(task) && !this.isHiddenAsCompleted(task);
        },

        /**
         * Per-column view data, computed once per state change (cached getter). The
         * visible arrays keep a stable identity between renders.
         */
        columnViews(): Record<number, ScrumColumnView> {
            const views: Record<number, ScrumColumnView> = {};
            for (const column of this.columns) {
                const visible: ScrumTask[] = [];
                let matching = 0;
                let hiddenCompleted = 0;
                for (const task of column.tasks) {
                    if (!this.matchesAssignee(task)) continue;
                    matching++;
                    if (this.isHiddenAsCompleted(task)) hiddenCompleted++;
                    else visible.push(task);
                }
                views[column.id] = { visible, total: column.tasks.length, matching, hiddenCompleted };
            }
            return views;
        },

        visibleTasks(): (columnId: number) => ScrumTask[] {
            return (columnId: number) => this.columnViews[columnId]?.visible ?? EMPTY_TASKS;
        },

        /** Completed tasks hidden by the "show completed" filter (assignee filter still applies). */
        hiddenCompletedCount(): (columnId: number) => number {
            return (columnId: number) => this.columnViews[columnId]?.hiddenCompleted ?? 0;
        },

        /** Tasks that pass the assignee filter, visible or hidden as completed. */
        assigneeMatchCount(): (columnId: number) => number {
            return (columnId: number) => this.columnViews[columnId]?.matching ?? 0;
        },

        /** Tasks in the column not currently shown, for any reason. */
        hiddenTasksCount(): (columnId: number) => number {
            return (columnId: number) => {
                const view = this.columnViews[columnId];
                return view ? view.total - view.visible.length : 0;
            };
        },
    },

    actions: {
        // ===============================
        // UI STATE (Spec 71)
        // ===============================

        /**
         * Loads the per-user view preferences. Without a saved view, defaults to the list
         * on small screens and to the board elsewhere.
         */
        initUiPreferences(userId: number, smallScreen: boolean = isSmallScreen()) {
            if (this.prefsUserId === userId) return;
            this.prefsUserId = userId;
            // "Asignado a" defaults to @me on each load / login; SPA navigation keeps the user's choice (R11)
            this.filterUserId = userId;

            const savedView = readStorage(viewModeKey(userId));
            this.viewMode = savedView === 'board' || savedView === 'list' ? savedView : smallScreen ? 'list' : 'board';

            let collapsed: number[] = [];
            try {
                const parsed = JSON.parse(readStorage(collapsedKey(userId)) ?? '[]');
                if (Array.isArray(parsed)) collapsed = parsed.filter((id) => Number.isInteger(id));
            } catch {
                // Corrupt value: ignore
            }
            this.collapsedColumnIds = collapsed;
        },

        setViewMode(mode: ScrumViewMode) {
            this.viewMode = mode;
            if (this.prefsUserId !== null) writeStorage(viewModeKey(this.prefsUserId), mode);
        },

        setFilterUserId(userId: number | null) {
            this.filterUserId = userId;
            // Choosing a filter explicitly applies it to every task again
            this.pinnedTaskIds = [];
        },

        pinTaskVisible(taskId: number) {
            if (!this.pinnedTaskIds.includes(taskId)) this.pinnedTaskIds.push(taskId);
        },

        setShowCompleted(value: boolean) {
            this.showCompleted = value;
        },

        toggleColumnCollapsed(columnId: number) {
            this.collapsedColumnIds = this.collapsedColumnIds.includes(columnId)
                ? this.collapsedColumnIds.filter((id) => id !== columnId)
                : [...this.collapsedColumnIds, columnId];
            this.persistCollapsed();
        },

        persistCollapsed() {
            if (this.prefsUserId !== null) {
                writeStorage(collapsedKey(this.prefsUserId), JSON.stringify(this.collapsedColumnIds));
            }
        },

        /** Drops collapsed ids of columns that no longer exist. */
        purgeCollapsed() {
            const existing = this.collapsedColumnIds.filter((id) => this.columns.some((c) => c.id === id));
            if (existing.length !== this.collapsedColumnIds.length) {
                this.collapsedColumnIds = existing;
                this.persistCollapsed();
            }
        },

        holdTaskVisible(taskId: number) {
            if (!this.pendingHideTaskIds.includes(taskId)) this.pendingHideTaskIds.push(taskId);
        },

        releaseTaskVisible(taskId: number) {
            this.pendingHideTaskIds = this.pendingHideTaskIds.filter((id) => id !== taskId);
        },

        // ===============================
        // FETCH OPERATIONS
        // ===============================

        async fetchBoard() {
            this.isLoading = true;
            this.error = null;
            try {
                const res = await scrumService.getBoard();
                this.columns = res.data;
                this.purgeCollapsed();
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
            const res = await scrumService.createColumn(title);
            const col = res.data;
            col.tasks = col.tasks ?? [];
            this.columns.push(col);
            return col;
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
            this.purgeCollapsed();
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
