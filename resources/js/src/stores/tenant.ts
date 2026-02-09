import { defineStore } from 'pinia';
import axios from 'axios';

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
}

interface TenantIntegrations {
    microsoft_configured: boolean;
    google_configured: boolean;
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
        },

        hasMicrosoftIntegration: (state): boolean => state.tenant?.integrations?.microsoft_configured ?? false,

        hasGoogleIntegration: (state): boolean => state.tenant?.integrations?.google_configured ?? false,
    },

    actions: {
        async fetchTenant() {
            this.loading = true;
            this.error = null;

            try {
                const response = await axios.get('/api/tenant');
                this.tenant = response.data.data;
                this.applyBranding();
            } catch (error: any) {
                this.error = error.response?.data?.message ?? 'Failed to load tenant data';
                console.error('Failed to fetch tenant:', error);
            } finally {
                this.loading = false;
            }
        },

        async updateSettings(settings: Partial<TenantCompany & TenantPreferences & { name: string }>) {
            this.loading = true;
            this.error = null;

            try {
                const response = await axios.put('/api/tenant/settings', settings);
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
                const response = await axios.put('/api/tenant/branding', branding);
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

        applyBranding() {
            if (!this.tenant) return;

            const root = document.documentElement;
            const branding = this.tenant.branding;

            // Apply custom CSS variables for theming
            if (branding.primary_color) {
                root.style.setProperty('--tenant-primary', branding.primary_color);
            }
            if (branding.secondary_color) {
                root.style.setProperty('--tenant-secondary', branding.secondary_color);
            }
        },

        clearTenant() {
            this.tenant = null;
            this.error = null;

            // Reset CSS variables
            const root = document.documentElement;
            root.style.removeProperty('--tenant-primary');
            root.style.removeProperty('--tenant-secondary');
        },
    },
});
