/**
 * Document Service
 * Handles all document and folder related API calls for cases
 */

import api from './api';
import type { DocumentFolder, Document } from '@/types/document';

interface CloudSyncResult {
    message: string;
    folders_added: number;
    folders_removed: number;
    documents_added: number;
    documents_removed: number;
}

const documentService = {
    // ===============================
    // FOLDERS
    // ===============================

    /**
     * Get all folders for a case
     */
    async getFolders(caseId: number): Promise<DocumentFolder[]> {
        const response = await api.get<{ data: DocumentFolder[] }>(`/cases/${caseId}/folders`);
        return response.data.data;
    },

    /**
     * Create a new folder
     */
    async createFolder(caseId: number, data: { name: string; parent_id?: number }): Promise<DocumentFolder> {
        const response = await api.post<{ data: DocumentFolder }>(`/cases/${caseId}/folders`, data);
        return response.data.data;
    },

    /**
     * Rename a folder
     */
    async renameFolder(caseId: number, folderId: number, name: string): Promise<DocumentFolder> {
        const response = await api.patch<{ data: DocumentFolder }>(`/cases/${caseId}/folders/${folderId}`, { name });
        return response.data.data;
    },

    /**
     * Delete a folder
     */
    async deleteFolder(caseId: number, folderId: number): Promise<void> {
        await api.delete(`/cases/${caseId}/folders/${folderId}`);
    },

    /**
     * Initialize default folder structure for a case that has no folders
     */
    async initializeFolders(caseId: number): Promise<{ message: string; folders_count: number }> {
        const response = await api.post<{ message: string; folders_count: number }>(`/cases/${caseId}/folders/initialize`);
        return response.data;
    },

    // ===============================
    // DOCUMENTS
    // ===============================

    /**
     * Get documents for a case with optional filters
     */
    async getDocuments(caseId: number, params?: { folder_id?: number; category?: string }): Promise<Document[]> {
        const query = new URLSearchParams();
        if (params?.folder_id) query.append('folder_id', params.folder_id.toString());
        if (params?.category) query.append('category', params.category);
        const qs = query.toString();
        const url = `/cases/${caseId}/documents${qs ? `?${qs}` : ''}`;
        const response = await api.get<{ data: Document[] }>(url);
        return response.data.data;
    },

    /**
     * Upload a document (multipart)
     */
    async uploadDocument(
        caseId: number,
        file: File,
        data?: { folder_id?: number; category?: string },
        onProgress?: (progress: number) => void
    ): Promise<Document> {
        const formData = new FormData();
        formData.append('file', file);
        if (data?.folder_id) formData.append('folder_id', data.folder_id.toString());
        if (data?.category) formData.append('category', data.category);

        const response = await api.post<{ data: Document }>(`/cases/${caseId}/documents`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (progressEvent) => {
                if (onProgress && progressEvent.total) {
                    const pct = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                    onProgress(pct);
                }
            },
        });
        return response.data.data;
    },

    /**
     * Get preview URL for a document (PDF/images)
     */
    getPreviewUrl(caseId: number, documentId: number): string {
        return `/api/cases/${caseId}/documents/${documentId}/preview`;
    },

    /**
     * Fetch document preview content as a Blob (for inline display in iframe/img)
     */
    async getPreviewBlob(caseId: number, documentId: number): Promise<Blob> {
        const response = await api.get(`/cases/${caseId}/documents/${documentId}/preview`, {
            responseType: 'blob',
        });
        return response.data;
    },

    /**
     * Get a single document
     */
    async getDocument(caseId: number, documentId: number): Promise<Document> {
        const response = await api.get<{ data: Document }>(`/cases/${caseId}/documents/${documentId}`);
        return response.data.data;
    },

    /**
     * Download a document (triggers browser download)
     */
    async downloadDocument(caseId: number, documentId: number): Promise<void> {
        const response = await api.get(`/cases/${caseId}/documents/${documentId}/download`, {
            responseType: 'blob',
        });

        const contentDisposition = response.headers['content-disposition'];
        let filename = 'download';
        if (contentDisposition) {
            const match = contentDisposition.match(/filename="?(.+?)"?$/);
            if (match) filename = match[1];
        }

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    },

    /**
     * Update document metadata (name, category, folder)
     */
    async updateDocument(caseId: number, documentId: number, data: { original_name?: string; category?: string; folder_id?: number | null }): Promise<Document> {
        const response = await api.patch<{ data: Document }>(`/cases/${caseId}/documents/${documentId}`, data);
        return response.data.data;
    },

    /**
     * Replace document file
     */
    async replaceDocument(caseId: number, documentId: number, file: File): Promise<Document> {
        const formData = new FormData();
        formData.append('file', file);
        const response = await api.post<{ data: Document }>(`/cases/${caseId}/documents/${documentId}/replace`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data.data;
    },

    /**
     * Move document to a different folder
     */
    async moveDocument(caseId: number, documentId: number, targetFolderId: number | null): Promise<Document> {
        const response = await api.post<{ data: Document }>(`/cases/${caseId}/documents/${documentId}/move`, {
            folder_id: targetFolderId,
        });
        return response.data.data;
    },

    /**
     * Delete a document
     */
    async deleteDocument(caseId: number, documentId: number): Promise<void> {
        await api.delete(`/cases/${caseId}/documents/${documentId}`);
    },
    // ===============================
    // CLOUD SYNC
    // ===============================

    /**
     * Trigger folder sync with cloud provider
     */
    async syncFolders(caseId: number): Promise<{ message: string }> {
        const response = await api.post<{ message: string }>(`/cases/${caseId}/folders/sync`);
        return response.data;
    },

    /**
     * Pull folders and documents from cloud storage, prune deleted items
     */
    async syncFromCloud(caseId: number): Promise<CloudSyncResult> {
        const response = await api.post<CloudSyncResult>(`/cases/${caseId}/documents/sync-from-cloud`);
        return response.data;
    },

    /**
     * Get sync status for all folders of a case
     */
    async getSyncStatus(caseId: number): Promise<{ sync_status: string; folders: DocumentFolder[] }> {
        const response = await api.get<{ data: { sync_status: string; folders: DocumentFolder[] } }>(`/cases/${caseId}/folders/sync-status`);
        return response.data.data;
    },
};

export default documentService;
