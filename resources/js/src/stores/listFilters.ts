import { defineStore } from 'pinia';

type FilterState = Record<string, unknown>;

export const useListFilterStore = defineStore('listFilters', {
    state: () => ({
        states: {} as Record<string, FilterState>,
    }),
    actions: {
        getState(key: string): FilterState | null {
            return this.states[key] ?? null;
        },
        setState(key: string, data: FilterState): void {
            this.states[key] = { ...data };
        },
        clearState(key: string): void {
            delete this.states[key];
        },
    },
});
