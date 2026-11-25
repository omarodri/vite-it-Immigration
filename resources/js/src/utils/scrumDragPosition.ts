/**
 * Spec 71 — Scrumboard drag & drop position helpers (shared by board and list views).
 *
 * Both views render filtered task lists (assignee filter + hidden completed tasks), so the
 * index SortableJS reports is an index into the VISIBLE list, not into column.tasks.
 * These helpers translate it into the `position` expected by PATCH /scrum/tasks/{id}/move:
 * the index in the target column's FULL task list, excluding the moved task.
 */
import type { ScrumTask } from '@/types/scrum';

/** A visible drop index, or a special placement. */
export type DropTarget = number | 'first' | 'last';

export interface MoveTaskPayload {
    taskId: number;
    targetColumnId: number;
    target: DropTarget;
    /** true when triggered from the "Move to…" menu (not a drag) */
    viaMenu?: boolean;
}

/**
 * Position in the full list (moved task excluded) for a drop on the target column.
 * - number: insert before the visible task that ends up at that visible index; past the last
 *   visible task → end of the column (after hidden tasks too).
 * - 'first': top of the column, ahead of hidden tasks (drop on a collapsed section).
 * - 'last': end of the column ("Move to…").
 */
export function resolveDropPosition(
    fullTasks: ScrumTask[],
    taskId: number,
    target: DropTarget,
    isVisible: (task: ScrumTask) => boolean,
): number {
    const rest = fullTasks.filter((t) => t.id !== taskId);
    if (target === 'first') return 0;
    if (target === 'last') return rest.length;

    const anchor = rest.filter(isVisible)[target];
    return anchor ? rest.findIndex((t) => t.id === anchor.id) : rest.length;
}

/** Minimal shape of the SortableJS `end` event we rely on. */
export interface SortableEndEvent {
    item?: { dataset?: Record<string, string | undefined> } | null;
    from?: { dataset?: Record<string, string | undefined> } | null;
    to?: { dataset?: Record<string, string | undefined> } | null;
    oldIndex?: number | null;
    newIndex?: number | null;
}

/**
 * Turns a SortableJS `end` event into a move payload, or null when nothing moved.
 * Relies on `data-task-id` on items, `data-column-id` on lists, and `data-drop-first`
 * on the collapsed-section drop zone.
 */
export function parseTaskDragEnd(evt: SortableEndEvent): MoveTaskPayload | null {
    const taskId = Number(evt.item?.dataset?.taskId);
    const targetColumnId = Number(evt.to?.dataset?.columnId);
    const sourceColumnId = Number(evt.from?.dataset?.columnId);
    if (!taskId || !targetColumnId) return null;

    if (evt.to?.dataset?.dropFirst) {
        return { taskId, targetColumnId, target: 'first' };
    }

    const newIndex = evt.newIndex;
    if (newIndex == null || newIndex < 0) return null;
    if (sourceColumnId === targetColumnId && evt.oldIndex === newIndex) return null;

    return { taskId, targetColumnId, target: newIndex };
}
