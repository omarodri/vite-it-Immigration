import { describe, it, expect } from 'vitest';
import { resolveDropPosition, parseTaskDragEnd } from '@/utils/scrumDragPosition';
import type { ScrumTask } from '@/types/scrum';

function task(id: number, assigneeId: number | null = null, completed = false): ScrumTask {
    return {
        id,
        scrum_column_id: 1,
        title: `T${id}`,
        tags: [],
        order_index: id * 1000,
        is_completed: completed,
        created_at: '2026-01-01T00:00:00Z',
        assigned_to: assigneeId ? { id: assigneeId, name: `U${assigneeId}`, avatar_url: null } : null,
    };
}

const all = () => true;
const mine = (t: ScrumTask) => t.assigned_to?.id === 7;

describe('resolveDropPosition', () => {
    it('without filter, the visible index is the full-list position', () => {
        const tasks = [task(1), task(2), task(3)];
        // Move task 9 (from another column) to visible index 1 → before task 2
        expect(resolveDropPosition(tasks, 9, 1, all)).toBe(1);
    });

    it('reordering within the same column excludes the moved task', () => {
        const tasks = [task(1), task(2), task(3)];
        // Drag task 1 to the end (visible index 2 among [2,3] + itself)
        expect(resolveDropPosition(tasks, 1, 2, all)).toBe(2);
        // Drag task 3 to the top
        expect(resolveDropPosition(tasks, 3, 0, all)).toBe(0);
    });

    it('with a filter, inserts before the visible anchor, skipping hidden tasks', () => {
        // Hidden tasks (assignee 8) interleaved with visible ones (assignee 7)
        const tasks = [task(1, 8), task(2, 7), task(3, 8), task(4, 7)];
        // Visible rest = [2, 4]. Drop at visible index 1 → before task 4 → full index 3
        expect(resolveDropPosition(tasks, 99, 1, mine)).toBe(3);
        // Drop at visible index 0 → before task 2 → full index 1 (hidden task 1 stays first)
        expect(resolveDropPosition(tasks, 99, 0, mine)).toBe(1);
    });

    it('dropping past the last visible task goes to the end, after hidden tasks', () => {
        const tasks = [task(1, 7), task(2, 8), task(3, 8)];
        expect(resolveDropPosition(tasks, 99, 1, mine)).toBe(3);
    });

    it('a column emptied by the filter receives the task at the end', () => {
        const tasks = [task(1, 8), task(2, 8)];
        expect(resolveDropPosition(tasks, 99, 0, mine)).toBe(2);
    });

    it('an empty column receives the task at position 0', () => {
        expect(resolveDropPosition([], 99, 0, all)).toBe(0);
    });

    it("'first' is always 0, ahead of hidden tasks", () => {
        const tasks = [task(1, 8), task(2, 7)];
        expect(resolveDropPosition(tasks, 99, 'first', mine)).toBe(0);
    });

    it("'last' is the end of the column without the moved task", () => {
        const tasks = [task(1), task(2), task(3)];
        expect(resolveDropPosition(tasks, 99, 'last', all)).toBe(3);
        expect(resolveDropPosition(tasks, 2, 'last', all)).toBe(2);
    });

    it('treats hidden completed tasks like any other hidden task', () => {
        const visibleIfOpen = (t: ScrumTask) => !t.is_completed;
        const tasks = [task(1), task(2, null, true), task(3)];
        // Visible rest = [1, 3]; drop at visible index 1 → before task 3 → full index 2
        expect(resolveDropPosition(tasks, 99, 1, visibleIfOpen)).toBe(2);
    });
});

describe('parseTaskDragEnd', () => {
    const evt = (o: Record<string, unknown>) => ({
        item: { dataset: { taskId: '5' } },
        from: { dataset: { columnId: '1' } },
        to: { dataset: { columnId: '2' } },
        oldIndex: 0,
        newIndex: 1,
        ...o,
    });

    it('builds a move payload from data attributes', () => {
        expect(parseTaskDragEnd(evt({}))).toEqual({ taskId: 5, targetColumnId: 2, target: 1 });
    });

    it('returns null when nothing moved (same column, same index)', () => {
        expect(parseTaskDragEnd(evt({ to: { dataset: { columnId: '1' } }, oldIndex: 1, newIndex: 1 }))).toBeNull();
    });

    it("maps a drop on a collapsed-section header to 'first'", () => {
        expect(parseTaskDragEnd(evt({ to: { dataset: { columnId: '3', dropFirst: '1' } } }))).toEqual({
            taskId: 5,
            targetColumnId: 3,
            target: 'first',
        });
    });

    it('returns null without a task id or target column', () => {
        expect(parseTaskDragEnd(evt({ item: { dataset: {} } }))).toBeNull();
        expect(parseTaskDragEnd(evt({ to: { dataset: {} } }))).toBeNull();
    });
});
