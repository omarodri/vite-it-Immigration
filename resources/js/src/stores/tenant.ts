import { defineStore } from 'pinia';
import api from '@/services/api';
import tenantService, {
    type CaseCodeBlockKey,
    type CaseCodeSeparator,
    type CaseCodePatternPayload,
} from '@/services/tenantService';

interface TenantBranding {
    logo_url: string | null;
    primary_color: string;
    secondary_color: string;
}

interface TenantCompany {
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    website: string | null;
}

interface TenantPreferences {
    timezone: string;
    date_format: string;
    language: string;
    name_format: 'first_last' | 'last_first';
    max_timer_duration: number | null;
}

interface TenantIntegrations {
    microsoft_configured: boolean;
    google_configured: boolean;
}

interface TenantTheme {
    mode: string;
    menu: string;
    layout: string;
    rtl_class: string;
    animation: string;
    navbar: string;
    semidark: boolean;
    show_customizer: boolean;
}

// Spec 65 — Case Code Pattern
interface TenantCaseCodePattern {
    blocks: CaseCodeBlockKey[];
    separator: CaseCodeSeparator;
    includeName: boolean;
    updatedAt: string | null;
}

interface Tenant {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
    branding: TenantBranding;
    company: TenantCompany;
    preferences: TenantPreferences;
    integrations: TenantIntegrations;
    theme: TenantTheme | null;
    case_code?: TenantCaseCodePattern | null;
    created_at: string;
    updated_at: string;
}

interface TenantState {
    tenant: Tenant | null;
    loading: boolean;
    error: string | null;
}

export const useTenantStore = defineStore('tenant', {
    state: (): TenantState => ({
        tenant: null,
        loading: false,
        error: null,
    }),

    getters: {
        isLoaded: (state) => state.tenant !== null,

        branding: (state): TenantBranding => state.tenant?.branding ?? {
            logo_url: null,
            primary_color: '#4361ee',
            secondary_color: '#805dca',
        },

        companyName: (state): string => state.tenant?.company?.name ?? state.tenant?.name ?? 'VITE-IT',

        logoUrl: (state): string | null => state.tenant?.branding?.logo_url ?? null,

        primaryColor: (state): string => state.tenant?.branding?.primary_color ?? '#4361ee',

        secondaryColor: (state): string => state.tenant?.branding?.secondary_color ?? '#805dca',

        preferences: (state): TenantPreferences => state.tenant?.preferences ?? {
            timezone: 'America/Toronto',
            date_format: 'Y-m-d',
            language: 'es',
            name_format: 'first_last',
            max_timer_duration: 120,
        },

        nameFormat: (state): 'first_last' | 'last_first' =>
            state.tenant?.preferences?.name_format ?? 'first_last',

        theme: (state): TenantTheme | null => state.tenant?.theme ?? null,

        showCustomizer: (state): boolean => state.tenant?.theme?.show_customizer !== false,

        hasMicrosoftIntegration: (state): boolean => state.tenant?.integrations?.microsoft_configured ?? false,

        hasGoogleIntegration: (state): boolean => state.tenant?.integrations?.google_configured ?? false,

        storageType: (state): string => (state.tenant as any)?.storage_type ?? 'local',

        isCloudStorage(): boolean {
            return this.storageType !== 'local';
        },

        // Spec 65 — Case Code Pattern with safe defaults (Spec 55 originals)
        caseCodePattern: (state): TenantCaseCodePattern => {
            const stored = state.tenant?.case_code;
            return {
                blocks: (stored?.blocks?.length ? stored.blocks : ['YY', 'TT', 'AAAA', 'NNNN']) as CaseCodeBlockKey[],
                separator: (stored?.separator ?? '-') as CaseCodeSeparator,
                includeName: Boolean(stored?.include_name ?? false),
                updatedAt: stored?.updated_at ?? null,
            };
        },

        caseCodeWasUpdated(): boolean {
            return Boolean(this.caseCodePattern.updatedAt);
        },
    },

    actions: {
        async fetchTenant() {
            this.loading = true;
            this.error = null;

            try {
                const response = await api.get('/tenant');
                this.tenant = response.data.data;
                this.applyBranding();
            } catch (error: any) {
                this.error = error.response?.data?.message ?? 'Failed to load tenant data';
                console.error('Failed to fetch tenant:', error);
            } finally {
                this.loading = false;
            }
        },

        async updateSettings(settings: Partial<TenantCompany & TenantPreferences & { name: string; name_format: 'first_last' | 'last_first'; max_timer_duration?: number | null }>) {
            this.loading = true;
            this.error = null;

            try {
                const response = await api.put('/tenant/settings', settings);
                this.tenant = response.data.data;
                return { success: true };
            } catch (error: any) {
                this.error = error.response?.data?.message ?? 'Failed to update settings';
                return { success: false, error: this.error };
            } finally {
                this.loading = false;
            }
        },

        async updateBranding(branding: Partial<TenantBranding>) {
            this.loading = true;
            this.error = null;

            try {
                const response = await api.put('/tenant/branding', branding);
                this.tenant = response.data.data;
                this.applyBranding();
                return { success: true };
            } catch (error: any) {
                this.error = error.response?.data?.message ?? 'Failed to update branding';
                return { success: false, error: this.error };
            } finally {
                this.loading = false;
            }
        },

        async uploadLogo(file: File) {
            try {
                const formData = new FormData();
                formData.append('logo', file);
                const response = await api.post('/tenant/branding/logo', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                this.tenant = response.data.data;
                return { success: true };
            } catch (error: any) {
                return { success: false, error: error.response?.data?.message ?? 'Failed to upload logo' };
            }
        },

        async deleteLogo() {
            try {
                const response = await api.delete('/tenant/branding/logo');
                this.tenant = response.data.data;
                return { success: true };
            } catch (error: any) {
                return { success: false, error: error.response?.data?.message ?? 'Failed to delete logo' };
            }
        },

        async updateTheme(themeData: Record<string, any>) {
            try {
                const response = await api.put('/tenant/theme', themeData);
                this.tenant = response.data.data;
                return { success: true };
            } catch (error: any) {
                return { success: false, error: error.response?.data?.message ?? 'Failed to update theme' };
            }
        },

        async updateCaseCodePattern(payload: CaseCodePatternPayload) {
            try {
                const response = await tenantService.updateCaseCodePattern(payload);
                const data = response.data;

                if (this.tenant) {
                    this.tenant = {
                        ...this.tenant,
                        case_code: {
                            blocks: data.case_code_blocks,
                            separator: data.case_code_separator,
                            includeName: data.case_code_include_name,
                            updatedAt: new Date().toISOString(),
                        },
                    };
                }

                return { success: true, data, message: response.message };
            } catch (error: any) {
                return {
                    success: false,
                    error: error.response?.data?.message ?? 'Failed to update case code pattern',
                    validationErrors: error.response?.data?.errors as Record<string, string[]> | undefined,
                };
            }
        },

        async updateStorageType(storageType: string) {
            try {
                const response = await api.put('/tenant/storage-type', { storage_type: storageType });
                this.tenant = response.data.data;
                return { success: true };
            } catch (error: any) {
                return { success: false, error: error.response?.data?.message ?? 'Failed to update storage type' };
            }
        },

        applyBranding() {
            if (!this.tenant) return;

            const root = document.documentElement;
            const branding = this.tenant.branding;

            // Convert hex color to space-separated RGB channels for Tailwind compatibility
            // e.g., "#4361ee" -> "67 97 238"
            const hexToRgbChannels = (hex: string): string | null => {
                const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
                if (!result) return null;
                return `${parseInt(result[1], 16)} ${parseInt(result[2], 16)} ${parseInt(result[3], 16)}`;
            };

            // Apply custom CSS variables for theming (RGB channels for Tailwind opacity support)
            if (branding.primary_color) {
                const rgb = hexToRgbChannels(branding.primary_color);
                if (rgb) {
                    root.style.setProperty('--tenant-primary-rgb', rgb);
                }
            }
            if (branding.secondary_color) {
                const rgb = hexToRgbChannels(branding.secondary_color);
                if (rgb) {
                    root.style.setProperty('--tenant-secondary-rgb', rgb);
                }
            }
        },

        clearTenant() {
            this.tenant = null;
            this.error = null;

            // Reset CSS variables
            const root = document.documentElement;
            root.style.removeProperty('--tenant-primary-rgb');
            root.style.removeProperty('--tenant-secondary-rgb');
        },
    },
});
