/**
 * Spec 71 — UI helpers shared by the scrumboard board and list views.
 */
import { ref, nextTick, type Ref } from 'vue';
import { parseTaskDragEnd, type MoveTaskPayload, type SortableEndEvent } from '@/utils/scrumDragPosition';
import type { ScrumTask } from '@/types/scrum';

/** How long a just-completed task stays visible before hiding (R16.5). Also drives the CSS fade. */
export const COMPLETE_GRACE_MS = 1500;

// ---------------------------------------------------------------------------
// Due dates — the API sends date-only 'YYYY-MM-DD' strings. Treat them as calendar dates:
// `new Date('YYYY-MM-DD')` is UTC midnight, which shows the previous day west of UTC.
// ---------------------------------------------------------------------------

function pad(n: number): string {
    return String(n).padStart(2, '0');
}

function todayLocal(): string {
    const d = new Date();
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** Due date shown as YYYY-MM-DD. */
export function formatDueDate(date: string): string {
    return date.slice(0, 10);
}

/** Overdue once the due day has passed in the user's local calendar (due today is not overdue). */
export function isOverdue(date?: string | null): boolean {
    if (!date) return false;
    return date.slice(0, 10) < todayLocal();
}

// ---------------------------------------------------------------------------
// Drag & drop interactions shared by ScrumKanbanView and ScrumListView
// ---------------------------------------------------------------------------

/**
 * Some browsers dispatch a `click` on the dragged element right after a drop.
 * The guard swallows that click so ending a drag never opens the task modal (R4.3).
 */
export function useDragClickGuard() {
    let dragging = false;
    let timer: ReturnType<typeof setTimeout> | undefined;

    return {
        start() {
            clearTimeout(timer);
            dragging = true;
        },
        end() {
            clearTimeout(timer);
            timer = setTimeout(() => {
                dragging = false;
            }, 50);
        },
        allowsClick(): boolean {
            return !dragging;
        },
    };
}

/**
 * After "Move to…" the focused task leaves its column: move focus to the task now at the
 * same index in the source column (or the previous one), else to the column header (R8.4).
 */
export function focusAfterMove(root: HTMLElement | null, sourceColumnId: number, index: number): void {
    if (!root) return;
    const items = root.querySelectorAll<HTMLElement>(`[data-column-id="${sourceColumnId}"] > [data-task-id]`);
    const next = items[index] ?? items[index - 1];
    if (next) {
        next.focus();
        return;
    }
    root.querySelector<HTMLElement>(`[data-column-focus="${sourceColumnId}"]`)?.focus();
}

/**
 * DRAG_REMOUNT_NOTE — the task lists are bound one-way (`:model-value`, never `v-model`) because
 * they render a FILTERED list. On a drop, vue-draggable-plus puts the dragged DOM node back into
 * the source list *after* the v-for fragment's end anchor; Vue then unmounts the moved card but
 * never removes that node, leaving a duplicate card in the source column until reload.
 * Both views therefore remount their task lists (`listsKey` bump) after every drag, so the DOM is
 * rebuilt purely from the store. Do not remove without re-testing a cross-column drag (see G27).
 */
export function useScrumViewInteractions(options: {
    rootEl: Ref<HTMLElement | null>;
    visibleTasks: () => (columnId: number) => ScrumTask[];
    emitOpen: (task: ScrumTask) => void;
    emitMove: (payload: MoveTaskPayload) => void;
}) {
    const dragGuard = useDragClickGuard();
    const listsKey = ref(0);
    const isDraggingTask = ref(false);

    function openTask(task: ScrumTask) {
        if (dragGuard.allowsClick()) options.emitOpen(task);
    }

    function onDragStart() {
        dragGuard.start();
        isDraggingTask.value = true;
    }

    function onDragEnd(evt: SortableEndEvent) {
        dragGuard.end();
        isDraggingTask.value = false;
        const payload = parseTaskDragEnd(evt);
        if (payload) options.emitMove(payload);
        listsKey.value++;
    }

    /** "Move to…": append to the target column, then keep keyboard focus in a sensible place. */
    async function moveVia(task: ScrumTask, targetColumnId: number) {
        const sourceColumnId = task.scrum_column_id;
        const index = options.visibleTasks()(sourceColumnId).findIndex((t) => t.id === task.id);
        options.emitMove({ taskId: task.id, targetColumnId, target: 'last', viaMenu: true });
        await nextTick();
        focusAfterMove(options.rootEl.value, sourceColumnId, index);
    }

    return { listsKey, isDraggingTask, openTask, onDragStart, onDragEnd, moveVia };
}
