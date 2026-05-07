/**
 * useCaseStagesStore — Pinia store for case stages (Spec 48).
 *
 * Optimistic updates for reorder; rollback on error.
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type {
    CaseStage,
    CreateCaseStagePayload,
    UpdateCaseStagePayload,
    DeleteStagePolicy,
} from '@/types/workflow';
import caseStageService from '@/services/caseStageService';
import { useNotification } from '@/composables/useNotification';
import { useCaseTasksStore } from './useCaseTasksStore';

export const useCaseStagesStore = defineStore('caseStages', () => {
    const stages = ref<CaseStage[]>([]);
    const pendingStageIds = ref<Set<number>>(new Set());
    const isLoading = ref(false);
    const { error: notifyError } = useNotification();

    const orderedStages = computed(() =>
        [...stages.value].sort((a, b) => a.sort_order - b.sort_order),
    );

    function reset() {
        stages.value = [];
        pendingStageIds.value = new Set();
        isLoading.value = false;
    }

    function setStages(list: CaseStage[]) {
        stages.value = [...list].sort((a, b) => a.sort_order - b.sort_order);
    }

    function markPending(id: number, pending: boolean) {
        const next = new Set(pendingStageIds.value);
        if (pending) next.add(id);
        else next.delete(id);
        pendingStageIds.value = next;
    }

    async function load(caseId: number): Promise<void> {
        isLoading.value = true;
        try {
            const list = await caseStageService.listStages(caseId);
            setStages(list);
        } catch (e: any) {
            const msg = e?.response?.data?.message || 'No se pudieron cargar las etapas';
            notifyError(msg);
        } finally {
            isLoading.value = false;
        }
    }

    async function createStage(
        caseId: number,
        payload: CreateCaseStagePayload,
    ): Promise<CaseStage | null> {
        try {
            const stage = await caseStageService.createStage(caseId, payload);
            stages.value = [...stages.value, stage].sort((a, b) => a.sort_order - b.sort_order);
            return stage;
        } catch (e: any) {
            const msg = e?.response?.data?.message || 'No se pudo crear la etapa';
            notifyError(msg);
            return null;
        }
    }

    async function updateStage(
        caseId: number,
        stageId: number,
        payload: UpdateCaseStagePayload,
    ): Promise<CaseStage | null> {
        markPending(stageId, true);
        try {
            const updated = await caseStageService.updateStage(caseId, stageId, payload);
            stages.value = stages.value.map((s) => (s.id === stageId ? updated : s));
            return updated;
        } catch (e: any) {
            const msg = e?.response?.data?.message || 'No se pudo actualizar la etapa';
            notifyError(msg);
            return null;
        } finally {
            markPending(stageId, false);
        }
    }

    async function reorder(caseId: number, orderedIds: number[]): Promise<void> {
        // Snapshot for rollback
        const snapshot = [...stages.value];

        // Optimistically reorder local
        const byId = new Map(stages.value.map((s) => [s.id, s]));
        const next: CaseStage[] = [];
        orderedIds.forEach((id, idx) => {
            const s = byId.get(id);
            if (s) next.push({ ...s, sort_order: idx });
        });
        stages.value = next;

        try {
            const fresh = await caseStageService.reorderStages(caseId, orderedIds);
            setStages(fresh);
        } catch (e: any) {
            stages.value = snapshot;
            const msg = e?.response?.data?.message || 'No se pudo reordenar las etapas';
            notifyError(msg);
            throw e;
        }
    }

    async function deleteStage(
        caseId: number,
        stageId: number,
        policy: DeleteStagePolicy,
        moveToId?: number,
    ): Promise<boolean> {
        markPending(stageId, true);
        try {
            await caseStageService.deleteStage(caseId, stageId, {
                policy,
                ...(policy === 'move' && moveToId ? { move_to_stage_id: moveToId } : {}),
            });
            stages.value = stages.value.filter((s) => s.id !== stageId);

            // Sync tasks store
            const tasksStore = useCaseTasksStore();
            tasksStore.rebindAfterStageDelete(stageId, policy, moveToId);
            return true;
        } catch (e: any) {
            const msg = e?.response?.data?.message || 'No se pudo eliminar la etapa';
            notifyError(msg);
            return false;
        } finally {
            markPending(stageId, false);
        }
    }

    return {
        stages,
        orderedStages,
        pendingStageIds,
        isLoading,
        load,
        setStages,
        createStage,
        updateStage,
        reorder,
        deleteStage,
        reset,
    };
});
