import api from './api';

// ---------------------------------------------------------------------------
// Case Code Pattern (Spec 65)
// ---------------------------------------------------------------------------
export type CaseCodeBlockKey = 'YY' | 'TT' | 'AAAA' | 'NNNN';
export type CaseCodeSeparator = '-' | '_' | '';

export interface CaseCodePatternPayload {
    case_code_blocks: CaseCodeBlockKey[];
    case_code_separator: CaseCodeSeparator;
    case_code_include_name: boolean;
}

export interface CaseCodePatternResponse {
    case_code_blocks: CaseCodeBlockKey[];
    case_code_separator: CaseCodeSeparator;
    case_code_include_name: boolean;
    preview: string;
}

const tenantService = {
    async getTenant() {
        const response = await api.get('/tenant');
        return response.data.data;
    },

    async updateSettings(data: Record<string, any>) {
        const response = await api.put('/tenant/settings', data);
        return response.data.data;
    },

    async updateBranding(data: Record<string, any>) {
        const response = await api.put('/tenant/branding', data);
        return response.data.data;
    },

    async uploadLogo(file: File) {
        const formData = new FormData();
        formData.append('logo', file);
        const response = await api.post('/tenant/branding/logo', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data.data;
    },

    async deleteLogo() {
        const response = await api.delete('/tenant/branding/logo');
        return response.data.data;
    },

    async updateStorageType(storageType: string) {
        const response = await api.put('/tenant/storage-type', { storage_type: storageType });
        return response.data.data;
    },

    async updateTheme(themeData: Record<string, any>) {
        const response = await api.put('/tenant/theme', themeData);
        return response.data.data;
    },

    // ---------------------------------------------------------------------
    // Case Code Pattern (Spec 65)
    // ---------------------------------------------------------------------
    async updateCaseCodePattern(payload: CaseCodePatternPayload): Promise<{ data: CaseCodePatternResponse; message: string }> {
        const response = await api.put('/tenant/case-code-pattern', payload);
        return response.data;
    },

    async getCaseCodePattern(): Promise<{ data: CaseCodePatternResponse }> {
        const response = await api.get('/tenant/settings');
        return response.data;
    },
};

export default tenantService;
