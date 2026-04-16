import { useTenantStore } from '@/stores/tenant';

export function useFormatName() {
    const tenantStore = useTenantStore();

    /**
     * Format a name according to the tenant's name_format preference.
     * Use this as a fallback when the API object doesn't include `full_name`.
     * Prefer consuming `item.full_name` directly from API responses.
     */
    function formatName(firstName: string, lastName: string): string {
        return tenantStore.nameFormat === 'last_first'
            ? `${lastName}, ${firstName}`
            : `${firstName} ${lastName}`;
    }

    return { formatName };
}
