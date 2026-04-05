import api from '@/services/api';
import type { LegalDocument } from '@/types/legal-document';

export const legalDocumentService = {
    async getForClient(clientId: number): Promise<LegalDocument[]> {
        const response = await api.get<{ data: LegalDocument[] }>(`/clients/${clientId}/documents`);
        return response.data.data;
    },

    async getForCompanion(companionId: number): Promise<LegalDocument[]> {
        const response = await api.get<{ data: LegalDocument[] }>(`/companions/${companionId}/documents`);
        return response.data.data;
    },

    async createForClient(clientId: number, data: Partial<LegalDocument>): Promise<LegalDocument> {
        const response = await api.post<{ data: LegalDocument }>(`/clients/${clientId}/documents`, data);
        return response.data.data;
    },

    async createForCompanion(companionId: number, data: Partial<LegalDocument>): Promise<LegalDocument> {
        const response = await api.post<{ data: LegalDocument }>(`/companions/${companionId}/documents`, data);
        return response.data.data;
    },

    async update(id: number, data: Partial<LegalDocument>): Promise<LegalDocument> {
        const response = await api.put<{ data: LegalDocument }>(`/legal-documents/${id}`, data);
        return response.data.data;
    },

    async remove(id: number): Promise<void> {
        await api.delete(`/legal-documents/${id}`);
    },

    async syncToCalendar(id: number): Promise<any> {
        const response = await api.post(`/legal-documents/${id}/sync-event`);
        return response.data;
    },

    async getExpirationAlerts(params?: Record<string, string>): Promise<any> {
        const query = new URLSearchParams();
        if (params) {
            Object.entries(params).forEach(([key, value]) => {
                if (value) query.append(key, value);
            });
        }
        const qs = query.toString();
        const url = `/expiration-alerts${qs ? `?${qs}` : ''}`;
        const response = await api.get(url);
        return response.data;
    },
};
