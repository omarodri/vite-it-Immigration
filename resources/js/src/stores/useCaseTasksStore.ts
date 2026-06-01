/**
 * useCaseTasksStore — Pinia store for the case tasks board (Spec 47, updated by Spec 48).
 *
 * Optimistic updates: mutate local state first, then call the API.
 * On error, rollback the mutation and surface a notification.
 *
 * Tasks are grouped by case_stage_id (Spec 48). Tasks with case_stage_id === null
 * (or workflow_stage_id === null on legacy data) are grouped under key 0 (ungrouped).
 */

import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { WorkflowTask, DeleteStagePolicy, UpdateWorkflowTaskCorePayload } from '@/types/workflow';
import caseTaskService, {
    type CreateWorkflowTaskPayload,
} from '@/services/caseTaskService';
import { useNotification } from '@/composables/useNotification';

export const UNGROUPED_KEY = 0;

function taskStageId(task: WorkflowTask): number | null {
    // Prefer case_stage_id (Spec 48), fall back to workflow_stage_id during transition.
    return task.case_stage_id ?? task.workflow_stage_id ?? null;
}

function stageKey(stageId: number | null | undefined): number {
    return stageId == null ? UNGROUPED_KEY : stageId;
}

export const useCaseTasksStore = defineStore('caseTasks', () => {
    const tasksByStage = ref<Record<number, WorkflowTask[]>>({});
    const trashedTasks = ref<WorkflowTask[]>([]);
    const pendingTaskIds = ref<Set<number>>(new Set());
    const isLoadingTrash = ref(false);
    const recentlyClonedTaskId = ref<number | null>(null);
    const { error: notifyError, success: notifySuccess } = useNotification();

    function loadTasks(tasks: WorkflowTask[]) {
        const map: Record<number, WorkflowTask[]> = {};
        for (const t of tasks) {
            const k = stageKey(taskStageId(t));
            if (!map[k]) map[k] = [];
            map[k].push(t);
        }
        for (const k of Object.keys(map)) {
            map[Number(k)].sort((a, b) => a.sort_order - b.sort_order);
        }
        tasksByStage.value = map;
    }

    function reset() {
        tasksByStage.value = {};
        trashedTasks.value = [];
        pendingTaskIds.value = new Set();
    }

    function findTaskLocation(taskId: number): { stageKey: number; index: number; task: WorkflowTask } | null {
        for (const [k, tasks] of Object.entries(tasksByStage.value)) {
            const idx = tasks.findIndex((t) => t.id === taskId);
            if (idx >= 0) {
                return { stageKey: Number(k), index: idx, task: tasks[idx] };
            }
        }
        return null;
    }

    function markPending(taskId: number, pending: boolean) {
        const next = new Set(pendingTaskIds.value);
        if (pending) next.add(taskId);
        else next.delete(taskId);
        pendingTaskIds.value = next;
    }

    async function moveTask(
        caseId: number,
        taskId: number,
        sourceStageId: number,
        targetStageId: number,
        targetIndex: number,
    ): Promise<void> {
        const snapshot: Record<number, WorkflowTask[]> = {};
        for (const [k, arr] of Object.entries(tasksByStage.value)) {
            snapshot[Number(k)] = [...arr];
        }

        const sourceKey = stageKey(sourceStageId);
        const targetKey = stageKey(targetStageId);

        let location = findTaskLocation(taskId);
        if (!location) {
            return;
        }

        const alreadyInTarget = location.stageKey === targetKey;
        if (!alreadyInTarget || location.index !== targetIndex) {
            const fromArr = [...(tasksByStage.value[location.stageKey] ?? [])];
            const [moved] = fromArr.splice(location.index, 1);
            if (!moved) return;
            const newStageId = targetStageId === UNGROUPED_KEY ? null : targetStageId;
            moved.case_stage_id = newStageId;
            tasksByStage.value = {
                ...tasksByStage.value,
                [location.stageKey]: fromArr,
            };
            const toArr = [...(tasksByStage.value[targetKey] ?? [])];
            const insertAt = Math.min(Math.max(targetIndex, 0), toArr.length);
            toArr.splice(insertAt, 0, moved);
            tasksByStage.value = {
                ...tasksByStage.value,
                [targetKey]: toArr,
            };
        }

        markPending(taskId, true);
        try {
            const updated = await caseTaskService.moveWorkflowTask(caseId, taskId, {
                source_stage_id: sourceStageId,
                target_stage_id: targetStageId,
                target_index: targetIndex,
            });
            const loc = findTaskLocation(taskId);
            if (loc) {
                Object.assign(loc.task, updated);
            }
        } catch (e: any) {
            tasksByStage.value = snapshot;
            void sourceKey;
            const msg = e?.response?.data?.message || 'No se pudo mover la tarea';
            notifyError(msg);
            throw e;
        } finally {
            markPending(taskId, false);
        }
    }

    async function createTask(caseId: number, payload: CreateWorkflowTaskPayload): Promise<WorkflowTask | null> {
        try {
            const task = await caseTaskService.createWorkflowTask(caseId, payload);
            const k = stageKey(taskStageId(task));
            const arr = [...(tasksByStage.value[k] ?? []), task];
            tasksByStage.value = { ...tasksByStage.value, [k]: arr };
            return task;
        } catch (e: any) {
            if (e?.response?.status === 422) {
                throw e;
            }
            const msg = e?.response?.data?.message || 'No se pudo crear la tarea';
            notifyError(msg);
            return null;
        }
    }

    async function deleteTask(caseId: number, taskId: number): Promise<void> {
        const location = findTaskLocation(taskId);
        if (!location) return;
        const snapshot = [...(tasksByStage.value[location.stageKey] ?? [])];

        const arr = snapshot.filter((t) => t.id !== taskId);
        tasksByStage.value = { ...tasksByStage.value, [location.stageKey]: arr };
        markPending(taskId, true);

        try {
            await caseTaskService.deleteWorkflowTask(caseId, taskId);
        } catch (e: any) {
            tasksByStage.value = { ...tasksByStage.value, [location.stageKey]: snapshot };
            const msg = e?.response?.data?.message || 'No se pudo eliminar la tarea';
            notifyError(msg);
            throw e;
        } finally {
            markPending(taskId, false);
        }
    }

    async function restoreTask(caseId: number, taskId: number): Promise<void> {
        try {
            const task = await caseTaskService.restoreWorkflowTask(caseId, taskId);
            trashedTasks.value = trashedTasks.value.filter((t) => t.id !== taskId);
            const k = stageKey(taskStageId(task));
            const arr = [...(tasksByStage.value[k] ?? []), task];
            tasksByStage.value = { ...tasksByStage.value, [k]: arr };
            notifySuccess('Tarea restaurada');
        } catch (e: any) {
            const msg = e?.response?.data?.message || 'No se pudo restaurar la tarea';
            notifyError(msg);
        }
    }

    async function loadTrash(caseId: number): Promise<void> {
        isLoadingTrash.value = true;
        try {
            trashedTasks.value = await caseTaskService.listTrashedTasks(caseId);
        } catch (e: any) {
            const msg = e?.response?.data?.message || 'No se pudo cargar la papelera';
            notifyError(msg);
        } finally {
            isLoadingTrash.value = false;
        }
    }

    async function updateTaskCore(
        caseId: number,
        taskId: number,
        payload: UpdateWorkflowTaskCorePayload,
    ): Promise<WorkflowTask | null> {
        const location = findTaskLocation(taskId);
        if (!location) return null;

        markPending(taskId, true);
        try {
            const updated = await caseTaskService.updateWorkflowTaskCore(caseId, taskId, payload);
            // Update in place within the store
            Object.assign(location.task, updated);
            return location.task;
        } catch (e: any) {
            const msg = e?.response?.data?.message || 'No se pudo actualizar la tarea';
            notifyError(msg);
            return null;
        } finally {
            markPending(taskId, false);
        }
    }

    async function cloneTask(caseId: number, taskId: number): Promise<WorkflowTask | null> {
        const location = findTaskLocation(taskId);
        if (!location) return null;

        try {
            const { task: clone } = await caseTaskService.cloneWorkflowTask(caseId, taskId);
            // Insert immediately after the original
            const stageArr = [...(tasksByStage.value[location.stageKey] ?? [])];
            stageArr.splice(location.index + 1, 0, clone);
            tasksByStage.value = { ...tasksByStage.value, [location.stageKey]: stageArr };
            // Ephemeral highlight
            recentlyClonedTaskId.value = clone.id;
            setTimeout(() => {
                if (recentlyClonedTaskId.value === clone.id) {
                    recentlyClonedTaskId.value = null;
                }
            }, 3000);
            return clone;
        } catch (e: any) {
            const msg = e?.response?.data?.message || 'No se pudo clonar la tarea';
            notifyError(msg);
            return null;
        }
    }

    /**
     * Spec 48: when a stage is deleted, locally rebind tasks according to policy.
     * - 'move': move tasks to moveToId at the end of that column
     * - 'trash': move tasks to trashedTasks
     */
    function rebindAfterStageDelete(
        stageId: number,
        policy: DeleteStagePolicy,
        moveToId?: number,
    ) {
        const removed = tasksByStage.value[stageId] ?? [];
        if (policy === 'move' && moveToId) {
            const dest = [...(tasksByStage.value[moveToId] ?? [])];
            for (const t of removed) {
                t.case_stage_id = moveToId;
                dest.push(t);
            }
            const next = { ...tasksByStage.value, [moveToId]: dest };
            delete next[stageId];
            tasksByStage.value = next;
        } else if (policy === 'trash') {
            trashedTasks.value = [...trashedTasks.value, ...removed];
            const next = { ...tasksByStage.value };
            delete next[stageId];
            tasksByStage.value = next;
        } else {
            const next = { ...tasksByStage.value };
            delete next[stageId];
            tasksByStage.value = next;
        }
    }

    return {
        tasksByStage,
        trashedTasks,
        pendingTaskIds,
        isLoadingTrash,
        recentlyClonedTaskId,
        loadTasks,
        moveTask,
        createTask,
        deleteTask,
        restoreTask,
        loadTrash,
        rebindAfterStageDelete,
        updateTaskCore,
        cloneTask,
        reset,
    };
});
