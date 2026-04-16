import type { Ref } from 'vue';
import { useListFilterStore } from '@/stores/listFilters';

type FilterState = Record<string, unknown>;
type RefMap = Record<string, Ref<any>>;

export function useListPersistence(key: 'clients' | 'cases' | 'todos', refs: RefMap) {
    const store = useListFilterStore();

    /**
     * Restore saved state into refs.
     * Returns true if state was found and applied, false if no saved state.
     */
    function restore(): boolean {
        const saved = store.getState(key);
        if (!saved) return false;
        for (const [field, ref] of Object.entries(refs)) {
            if (saved[field] !== undefined) {
                ref.value = saved[field];
            }
        }
        return true;
    }

    /**
     * Snapshot current ref values into the store.
     * Call this from a watchEffect or after each filter change.
     */
    function persist(): void {
        const snapshot: FilterState = {};
        for (const [field, ref] of Object.entries(refs)) {
            snapshot[field] = ref.value;
        }
        store.setState(key, snapshot);
    }

    /**
     * Clear persisted state. Call when user clicks "Clear Filters".
     */
    function clear(): void {
        store.clearState(key);
    }

    return { restore, persist, clear };
}
