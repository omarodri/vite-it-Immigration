import api from '@/services/api';
import type { FolderTemplateResponse } from '@/types/case';

let cache: FolderTemplateResponse | null = null;

export const caseFolderTemplateService = {
    async getDefaults(force = false): Promise<FolderTemplateResponse> {
        if (cache && !force) return cache;
        const { data } = await api.get<FolderTemplateResponse>('/case-folders/defaults');
        cache = data;
        return data;
    },

    clearCache(): void {
        cache = null;
    },
};

export default caseFolderTemplateService;
