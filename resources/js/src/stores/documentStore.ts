import { defineStore } from 'pinia';
import documentService from '@/services/documentService';
import type { DocumentFolder, Document } from '@/types/document';

interface DocumentState {
    folders: DocumentFolder[];
    currentFolderId: number | null;
    documents: Document[];
    isLoading: boolean;
    viewMode: 'grid' | 'list';
    selectedDocuments: number[];
}

export const useDocumentStore = defineStore('document', {
    state: (): DocumentState => ({
        folders: [],
        currentFolderId: null,
        documents: [],
        isLoading: false,
        viewMode: 'grid',
        selectedDocuments: [],
    }),

    getters: {
        currentFolder(state): DocumentFolder | null {
            if (state.currentFolderId === null) return null;
            return findFolderInTree(state.folders, state.currentFolderId);
        },

        documentCount(state): number {
            return state.documents.length;
        },

        hasSelection(state): boolean {
            return state.selectedDocuments.length > 0;
        },

        folderTree(state): DocumentFolder[] {
            return buildTree(state.folders);
        },
    },

    actions: {
        async fetchFolders(caseId: number) {
            try {
                this.folders = await documentService.getFolders(caseId);
            } catch {
                // Error handled by api interceptor
            }
        },

        async fetchDocuments(caseId: number, folderId?: number | null) {
            this.isLoading = true;
            try {
                const params = folderId !== undefined && folderId !== null
                    ? { folder_id: folderId }
                    : undefined;
                this.documents = await documentService.getDocuments(caseId, params);
            } catch {
                // Error handled by api interceptor
            } finally {
                this.isLoading = false;
            }
        },

        setCurrentFolder(folderId: number | null) {
            this.currentFolderId = folderId;
        },

        setViewMode(mode: 'grid' | 'list') {
            this.viewMode = mode;
        },

        toggleDocumentSelection(docId: number) {
            const index = this.selectedDocuments.indexOf(docId);
            if (index === -1) {
                this.selectedDocuments.push(docId);
            } else {
                this.selectedDocuments.splice(index, 1);
            }
        },

        clearSelection() {
            this.selectedDocuments = [];
        },

        reset() {
            this.folders = [];
            this.currentFolderId = null;
            this.documents = [];
            this.isLoading = false;
            this.viewMode = 'grid';
            this.selectedDocuments = [];
        },
    },
});

/**
 * Recursively find a folder by ID in a tree structure
 */
function findFolderInTree(tree: DocumentFolder[], id: number): DocumentFolder | null {
    for (const folder of tree) {
        if (folder.id === id) return folder;
        if (folder.children && folder.children.length > 0) {
            const found = findFolderInTree(folder.children, id);
            if (found) return found;
        }
    }
    return null;
}

/**
 * Build a tree from a flat or already-nested folders array.
 * If the API already returns nested data, this returns it as-is.
 * If the data is flat, it nests children under their parents.
 */
function buildTree(folders: DocumentFolder[]): DocumentFolder[] {
    // Check if already nested (children arrays are populated)
    const hasNestedChildren = folders.some(f => f.children && f.children.length > 0);
    if (hasNestedChildren) return folders;

    // Build tree from flat array
    const map = new Map<number, DocumentFolder>();
    const roots: DocumentFolder[] = [];

    for (const folder of folders) {
        map.set(folder.id, { ...folder, children: [] });
    }

    for (const folder of folders) {
        const node = map.get(folder.id)!;
        if (folder.parent_id !== null && map.has(folder.parent_id)) {
            map.get(folder.parent_id)!.children.push(node);
        } else {
            roots.push(node);
        }
    }

    return roots;
}
