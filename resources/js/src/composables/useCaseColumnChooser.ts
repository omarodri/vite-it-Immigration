/**
 * Case Column Chooser Composable
 * Manages column visibility preferences for the cases datatable
 * Persists user preferences to localStorage
 */

import { ref, computed } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

const STORAGE_KEY = 'vite-it:cases:columns';

export interface ColumnConfig {
    field: string;
    titleKey: string;
    visible: boolean;
    locked: boolean;
    width?: string;
    requiresPermission?: string;
}

const DEFAULT_COLUMNS: ColumnConfig[] = [
    { field: 'case_number',  titleKey: 'cases.case_number',    visible: true,  locked: true,  width: '140px' },
    { field: 'client',       titleKey: 'cases.client',          visible: true,  locked: true,  width: '200px' },
    { field: 'case_type',    titleKey: 'cases.case_type',       visible: true,  locked: false, width: '150px' },
    { field: 'status',       titleKey: 'cases.status',          visible: true,  locked: true,  width: '110px' },
    { field: 'priority',     titleKey: 'cases.priority',        visible: true,  locked: false, width: '100px' },
    { field: 'stage',        titleKey: 'cases.stage',           visible: true,  locked: false, width: '160px' },
    { field: 'progress',     titleKey: 'cases.progress',        visible: false, locked: false, width: '130px' },
    { field: 'ircc_status',  titleKey: 'cases.ircc_status',     visible: false, locked: false, width: '130px' },
    { field: 'service_type', titleKey: 'cases.service_type',    visible: false, locked: false, width: '120px' },
    { field: 'fees',         titleKey: 'cases.fees',            visible: false, locked: false, width: '100px', requiresPermission: 'cases.view-fees' },
    { field: 'nearest_date', titleKey: 'cases.important_dates', visible: true,  locked: false, width: '180px' },
    { field: 'assigned_to',  titleKey: 'cases.assigned_to',     visible: true,  locked: false, width: '150px' },
    { field: 'actions',      titleKey: 'cases.actions',         visible: true,  locked: true,  width: '120px' },
];

export function useCaseColumnChooser() {
    const { can } = usePermissions();

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

    // Filter out columns requiring permissions the user doesn't have
    const visibleOptions = computed(() =>
        columns.value.filter(col => {
            if (!col.requiresPermission) return true;
            return can(col.requiresPermission);
        })
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
        // Also check permission
        if (col.requiresPermission && !can(col.requiresPermission)) return false;
        return col.visible;
    }

    return { columns, visibleOptions, toggleColumn, resetColumns, isVisible };
}

export default useCaseColumnChooser;
