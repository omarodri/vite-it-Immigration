/**
 * Document Types
 * Interfaces for case document management
 */

export type FolderSyncStatus = 'pending' | 'synced' | 'failed';

export interface DocumentFolder {
    id: number;
    name: string;
    parent_id: number | null;
    sort_order: number;
    is_default: boolean;
    category: string | null;
    children: DocumentFolder[];
    documents_count: number;
    sync_status: FolderSyncStatus;
    synced_at: string | null;
    external_id: string | null;
    created_at: string;
}

export interface Document {
    id: number;
    name: string;
    original_name: string;
    mime_type: string | null;
    size: number;
    category: string;
    storage_type: string;
    external_url: string | null;
    version: number;
    folder_id: number | null;
    uploaded_by: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
}

export interface UploadItem {
    file: File;
    progress: number;
    status: 'pending' | 'uploading' | 'done' | 'error';
    error?: string;
}

export interface SharePointSite {
    id: string;
    displayName: string;
    webUrl: string;
}

export interface SharePointDrive {
    id: string;
    name: string;
    driveType: string;
    webUrl: string;
}
