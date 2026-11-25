import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { useScrumStore } from '@/stores/scrum';
import scrumService from '@/services/scrumService';
import type { ScrumColumn, ScrumTask } from '@/types/scrum';

vi.mock('@/services/scrumService', () => ({
    default: {
        getBoard: vi.fn(),
        getAssignees: vi.fn(),
        deleteColumn: vi.fn(),
        moveTask: vi.fn(),
        toggleTask: vi.fn(),
        reorderColumns: vi.fn(),
    },
}));

function task(id: number, columnId: number, assigneeId: number | null = null, completed = false): ScrumTask {
    return {
        id,
        scrum_column_id: columnId,
        title: `T${id}`,
        tags: [],
        order_index: id * 1000,
        is_completed: completed,
        created_at: '2026-01-01T00:00:00Z',
        assigned_to: assigneeId ? { id: assigneeId, name: `U${assigneeId}`, avatar_url: null } : null,
    };
}

function column(id: number, tasks: ScrumTask[]): ScrumColumn {
    return { id, title: `C${id}`, order_index: id * 1000, tasks, tasks_count: tasks.length };
}

describe('Scrum Store — UI state (Spec 71)', () => {
    let store: ReturnType<typeof useScrumStore>;

    beforeEach(() => {
        localStorage.clear();
        vi.clearAllMocks();
        store = useScrumStore();
        store.columns = [
            column(1, [task(1, 1, 7), task(2, 1, 8), task(3, 1, 7, true), task(4, 1, 8, true)]),
            column(2, []),
        ];
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    describe('defaults', () => {
        it('starts with board view, no assignee filter and completed tasks hidden', () => {
            const fresh = useScrumStore();
            fresh.$reset();
            expect(fresh.viewMode).toBe('board');
            expect(fresh.filterUserId).toBeNull();
            expect(fresh.showCompleted).toBe(false);
            expect(fresh.collapsedColumnIds).toEqual([]);
        });
    });

    describe('isTaskVisible / visibleTasks', () => {
        it('hides completed tasks by default', () => {
            expect(store.visibleTasks(1).map((t) => t.id)).toEqual([1, 2]);
        });

        it('shows completed tasks when showCompleted is on', () => {
            store.setShowCompleted(true);
            expect(store.visibleTasks(1).map((t) => t.id)).toEqual([1, 2, 3, 4]);
        });

        it('combines assignee filter and completed filter', () => {
            store.setFilterUserId(7);
            expect(store.visibleTasks(1).map((t) => t.id)).toEqual([1]);
            store.setShowCompleted(true);
            expect(store.visibleTasks(1).map((t) => t.id)).toEqual([1, 3]);
        });

        it('keeps a completed task visible while it is pending hide', () => {
            store.holdTaskVisible(3);
            expect(store.visibleTasks(1).map((t) => t.id)).toEqual([1, 2, 3]);
            store.releaseTaskVisible(3);
            expect(store.visibleTasks(1).map((t) => t.id)).toEqual([1, 2]);
        });

        it('returns [] for an unknown column', () => {
            expect(store.visibleTasks(999)).toEqual([]);
        });
    });

    describe('hidden counters', () => {
        it('counts completed tasks hidden by the filter, respecting the assignee filter', () => {
            expect(store.hiddenCompletedCount(1)).toBe(2);
            store.setFilterUserId(7);
            expect(store.hiddenCompletedCount(1)).toBe(1);
        });

        it('is 0 when completed tasks are shown or pending', () => {
            store.holdTaskVisible(3);
            expect(store.hiddenCompletedCount(1)).toBe(1);
            store.setShowCompleted(true);
            expect(store.hiddenCompletedCount(1)).toBe(0);
        });

        it('assigneeMatchCount uses the same base as hiddenCompletedCount', () => {
            // Column 1: tasks 1 (u7), 2 (u8), 3 (u7, done), 4 (u8, done)
            expect(store.assigneeMatchCount(1)).toBe(4);
            store.setFilterUserId(7);
            expect(store.assigneeMatchCount(1)).toBe(2);
            expect(store.hiddenCompletedCount(1)).toBe(1);
        });

        it('all matching tasks completed → hiddenCompleted equals matching even with other users\' open tasks', () => {
            store.columns = [column(9, [task(10, 9, 7, true), task(11, 9, 7, true), task(12, 9, 8)])];
            store.setFilterUserId(7);
            expect(store.visibleTasks(9)).toEqual([]);
            expect(store.hiddenCompletedCount(9)).toBe(2);
            expect(store.assigneeMatchCount(9)).toBe(2);
        });

        it('visibleTasks returns a stable array between reads (cached)', () => {
            expect(store.visibleTasks(1)).toBe(store.visibleTasks(1));
            expect(store.visibleTasks(999)).toBe(store.visibleTasks(998));
        });

        it('findTask locates a task in any column', () => {
            expect(store.findTask(4)?.title).toBe('T4');
            expect(store.findTask(999)).toBeUndefined();
        });

        it('hiddenTasksCount counts tasks hidden for any reason', () => {
            store.setFilterUserId(7);
            // Visible: task 1 → hidden: 2, 3, 4
            expect(store.hiddenTasksCount(1)).toBe(3);
        });
    });

    describe('initUiPreferences', () => {
        it('defaults to list on small screens and board on large ones', () => {
            store.initUiPreferences(10, true);
            expect(store.viewMode).toBe('list');

            const other = useScrumStore();
            other.$reset();
            other.initUiPreferences(11, false);
            expect(other.viewMode).toBe('board');
        });

        it('a saved view wins over the screen-size default', () => {
            localStorage.setItem('scrum.viewMode.10', 'board');
            store.initUiPreferences(10, true);
            expect(store.viewMode).toBe('board');
        });

        it('ignores an invalid saved view and corrupt collapsed state', () => {
            localStorage.setItem('scrum.viewMode.10', 'grid');
            localStorage.setItem('scrum.collapsed.10', '{not json');
            store.initUiPreferences(10, false);
            expect(store.viewMode).toBe('board');
            expect(store.collapsedColumnIds).toEqual([]);
        });

        it('restores collapsed sections, keeping only integers', () => {
            localStorage.setItem('scrum.collapsed.10', JSON.stringify([1, 'x', 2]));
            store.initUiPreferences(10, false);
            expect(store.collapsedColumnIds).toEqual([1, 2]);
        });

        it('keeps working when localStorage throws', () => {
            vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
                throw new Error('blocked');
            });
            vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
                throw new Error('blocked');
            });
            expect(() => store.initUiPreferences(10, false)).not.toThrow();
            expect(store.viewMode).toBe('board');
            expect(() => store.setViewMode('list')).not.toThrow();
            expect(store.viewMode).toBe('list');
        });

        it('preferences are per user', () => {
            store.initUiPreferences(10, false);
            store.setViewMode('list');
            expect(localStorage.getItem('scrum.viewMode.10')).toBe('list');
            expect(localStorage.getItem('scrum.viewMode.11')).toBeNull();
        });
    });

    describe('collapsed sections', () => {
        it('toggles and persists', () => {
            store.initUiPreferences(10, false);
            store.toggleColumnCollapsed(1);
            expect(store.collapsedColumnIds).toEqual([1]);
            expect(JSON.parse(localStorage.getItem('scrum.collapsed.10')!)).toEqual([1]);
            store.toggleColumnCollapsed(1);
            expect(store.collapsedColumnIds).toEqual([]);
        });

        it('purges a deleted column from the collapsed list', async () => {
            store.initUiPreferences(10, false);
            store.toggleColumnCollapsed(2);
            vi.mocked(scrumService.deleteColumn).mockResolvedValue();
            await store.deleteColumn(2);
            expect(store.collapsedColumnIds).toEqual([]);
            expect(JSON.parse(localStorage.getItem('scrum.collapsed.10')!)).toEqual([]);
        });

        it('purges collapsed ids of columns missing from a fresh board', async () => {
            localStorage.setItem('scrum.collapsed.10', JSON.stringify([1, 42]));
            store.initUiPreferences(10, false);
            vi.mocked(scrumService.getBoard).mockResolvedValue({ data: [column(1, [])] });
            await store.fetchBoard();
            expect(store.collapsedColumnIds).toEqual([1]);
        });
    });

    describe('$reset (logout)', () => {
        it('clears filters, data and loaded preferences', () => {
            store.initUiPreferences(10, false);
            store.setFilterUserId(7);
            store.setShowCompleted(true);
            store.$reset();
            expect(store.filterUserId).toBeNull();
            expect(store.showCompleted).toBe(false);
            expect(store.columns).toEqual([]);
            expect(store.prefsUserId).toBeNull();
        });
    });
});

describe('Scrum Store — moveTask', () => {
    let store: ReturnType<typeof useScrumStore>;

    beforeEach(() => {
        vi.clearAllMocks();
        store = useScrumStore();
        store.columns = [column(1, [task(1, 1), task(2, 1)]), column(2, [task(3, 2)])];
    });

    it('moves optimistically and calls the API with the position', async () => {
        vi.mocked(scrumService.moveTask).mockResolvedValue();
        await store.moveTask(1, 2, 0);
        expect(store.columns[0].tasks.map((t) => t.id)).toEqual([2]);
        expect(store.columns[1].tasks.map((t) => t.id)).toEqual([1, 3]);
        expect(store.columns[1].tasks[0].scrum_column_id).toBe(2);
        expect(scrumService.moveTask).toHaveBeenCalledWith(1, { scrum_column_id: 2, position: 0 });
    });

    it('reverts to the previous state when the API fails', async () => {
        vi.mocked(scrumService.moveTask).mockRejectedValue(new Error('422'));
        await store.moveTask(1, 2, 0);
        expect(store.columns[0].tasks.map((t) => t.id)).toEqual([1, 2]);
        expect(store.columns[1].tasks.map((t) => t.id)).toEqual([3]);
    });

    it('reorders within the same column', async () => {
        vi.mocked(scrumService.moveTask).mockResolvedValue();
        await store.moveTask(1, 1, 1);
        expect(store.columns[0].tasks.map((t) => t.id)).toEqual([2, 1]);
    });
});
