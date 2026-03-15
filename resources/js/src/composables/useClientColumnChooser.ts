/**
 * Client Column Chooser Composable
 * Manages column visibility preferences for the clients datatable
 * Persists user preferences to localStorage
 */

import { ref, computed } from 'vue';

const STORAGE_KEY = 'vite-it:clients:columns';

export interface ColumnConfig {
    field: string;
    titleKey: string;
    visible: boolean;
    locked: boolean;
    width?: string;
}

const DEFAULT_COLUMNS: ColumnConfig[] = [
    { field: 'first_name',     titleKey: 'clients.name',          visible: true,  locked: true,  width: '250px' },
    { field: 'email',          titleKey: 'clients.email',         visible: true,  locked: false, width: '200px' },
    { field: 'phone',          titleKey: 'clients.phone',         visible: true,  locked: false, width: '150px' },
    { field: 'status',         titleKey: 'clients.status',        visible: true,  locked: false, width: '120px' },
    { field: 'canada_status',  titleKey: 'clients.canada_status', visible: true,  locked: false, width: '160px' },
    { field: 'nationality',    titleKey: 'clients.nationality',   visible: false, locked: false, width: '140px' },
    { field: 'marital_status', titleKey: 'clients.marital_status',visible: false, locked: false, width: '140px' },
    { field: 'profession',     titleKey: 'clients.profession',    visible: false, locked: false, width: '140px' },
    { field: 'arrival_date',   titleKey: 'clients.arrival_date',  visible: false, locked: false, width: '140px' },
    { field: 'created_at',     titleKey: 'clients.created',       visible: true,  locked: false, width: '150px' },
    { field: 'actions',        titleKey: 'clients.actions',       visible: true,  locked: true,  width: '180px' },
];

export function useClientColumnChooser() {
    function loadColumns(): ColumnConfig[] {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (!saved) return DEFAULT_COLUMNS.map(c => ({ ...c }));

        try {
            const savedMap: Record<string, boolean> = JSON.parse(saved);
            return DEFAULT_COLUMNS.map(col => ({
                ...col,
                visible: col.locked ? true : (savedMap[col.field] ?? col.visible),
            }));
        } catch {
            return DEFAULT_COLUMNS.map(c => ({ ...c }));
        }
    }

    const columns = ref<ColumnConfig[]>(loadColumns());

    const visibleOptions = computed(() =>
        columns.value.filter(col => !col.locked)
    );

    function toggleColumn(field: string) {
        const col = columns.value.find(c => c.field === field);
        if (col && !col.locked) {
            col.visible = !col.visible;
            saveColumns();
        }
    }

    function saveColumns() {
        const map: Record<string, boolean> = {};
        columns.value.forEach(col => { map[col.field] = col.visible; });
        localStorage.setItem(STORAGE_KEY, JSON.stringify(map));
    }

    function resetColumns() {
        localStorage.removeItem(STORAGE_KEY);
        columns.value = DEFAULT_COLUMNS.map(c => ({ ...c }));
    }

    function isVisible(field: string): boolean {
        const col = columns.value.find(c => c.field === field);
        if (!col) return true;
        return col.visible;
    }

    return { columns, visibleOptions, toggleColumn, resetColumns, isVisible };
}

export default useClientColumnChooser;
