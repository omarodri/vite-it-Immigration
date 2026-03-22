<?php

namespace App\Services\Document;

use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\ImmigrationCase;
use App\Models\Tenant;
use App\Services\Storage\StorageProviderFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FolderService
{
    public function __construct(
        private readonly StorageProviderFactory $providerFactory,
        private readonly CaseFolderSyncService $caseFolderSyncService,
    ) {}

    /**
     * Get the folder tree for a case (root folders with nested children).
     */
    public function getFolderTree(ImmigrationCase $case): Collection
    {
        return DocumentFolder::byCase($case->id)
            ->roots()
            ->with(['children' => function ($query) {
                $query->withCount('documents')->orderBy('name');
            }])
            ->withCount('documents')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a new folder for a case.
     *
     * If the tenant uses cloud storage and the case already has a synced root
     * folder, the new folder is also created in the cloud synchronously.
     */
    public function createFolder(ImmigrationCase $case, array $data): DocumentFolder
    {
        $folder = DocumentFolder::create([
            'tenant_id' => $case->tenant_id,
            'case_id' => $case->id,
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_default' => false,
            'category' => $data['category'] ?? null,
        ]);

        activity()
            ->causedBy(Auth::user())
            ->performedOn($folder)
            ->withProperties([
                'case_id' => $case->id,
                'folder_name' => $folder->name,
            ])
            ->log('Created folder: ' . $folder->name);

        // Sync to storage: cloud async or local immediate
        $tenant = Tenant::find($case->tenant_id);
        $isCloud = $tenant && in_array($tenant->storage_type, ['onedrive', 'google_drive'], true);

        if ($isCloud) {
            $this->trySyncFolderToCloud($folder, $case);
        } elseif ($case->root_external_folder_id) {
            // Local storage: create physical directory immediately
            $folderPath = $case->root_external_folder_id . '/' . $folder->name;
            Storage::disk('local')->makeDirectory($folderPath);
            $folder->update([
                'external_id' => $folderPath,
                'sync_status' => 'synced',
                'synced_at' => now(),
            ]);
        }

        return $folder->loadCount('documents');
    }

    /**
     * Rename a folder.
     */
    public function renameFolder(DocumentFolder $folder, string $name): DocumentFolder
    {
        $oldName = $folder->name;

        $folder->update(['name' => $name]);

        activity()
            ->causedBy(Auth::user())
            ->performedOn($folder)
            ->withProperties([
                'old_name' => $oldName,
                'new_name' => $name,
            ])
            ->log('Renamed folder from "' . $oldName . '" to "' . $name . '"');

        return $folder;
    }

    /**
     * Delete a folder. Throws exception if not empty.
     *
     * If the folder has an external_id, attempts to delete it from the cloud
     * provider as well. Cloud deletion failures are logged but do not prevent
     * the local deletion.
     */
    public function deleteFolder(DocumentFolder $folder): bool
    {
        // Check for documents (including soft-deleted)
        $documentCount = Document::withTrashed()
            ->where('folder_id', $folder->id)
            ->count();

        if ($documentCount > 0) {
            throw new \RuntimeException('Cannot delete folder that contains documents.');
        }

        // Check for child folders
        if ($folder->children()->count() > 0) {
            throw new \RuntimeException('Cannot delete folder that contains subfolders.');
        }

        // Try to delete from cloud if the folder has an external_id
        if ($folder->external_id) {
            try {
                $tenant = Tenant::find($folder->tenant_id);
                if ($tenant && in_array($tenant->storage_type, ['onedrive', 'google_drive'], true)) {
                    $provider = $this->providerFactory->makeForTenant($tenant);
                    $provider->deleteFolder($folder->external_id);
                }
            } catch (\Throwable $e) {
                Log::warning('FolderService: Failed to delete cloud folder, proceeding with local deletion.', [
                    'folder_id' => $folder->id,
                    'external_id' => $folder->external_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        activity()
            ->causedBy(Auth::user())
            ->performedOn($folder)
            ->withProperties([
                'case_id' => $folder->case_id,
                'folder_name' => $folder->name,
            ])
            ->log('Deleted folder: ' . $folder->name);

        return $folder->delete();
    }

    /**
     * Create default folder structure for a case.
     *
     * For local storage, also creates physical directories synchronously
     * so they are available immediately without depending on a queue worker.
     */
    public function createDefaultStructure(ImmigrationCase $case): void
    {
        $defaultFolders = [
            ['name' => 'Archivo', 'category' => Document::CATEGORY_ARCHIVE, 'sort_order' => 0],
            ['name' => 'Cartas', 'category' => Document::CATEGORY_LETTERS, 'sort_order' => 1],
            ['name' => 'Comunicaciones', 'category' => Document::CATEGORY_COMMUNICATION, 'sort_order' => 2],
            ['name' => 'Contrato', 'category' => Document::CATEGORY_CONTRACT, 'sort_order' => 3],
            ['name' => 'Contabilidad', 'category' => Document::CATEGORY_ACCOUNTING, 'sort_order' => 4],
            ['name' => 'Documentos', 'category' => Document::CATEGORY_DOCUMENTS, 'sort_order' => 5],
            ['name' => 'Enlaces', 'category' => Document::CATEGORY_LINKS, 'sort_order' => 6],
            ['name' => 'Questionarios', 'category' => Document::CATEGORY_QUESTIONARY, 'sort_order' => 7],
            ['name' => 'Formularios', 'category' => Document::CATEGORY_FORMS, 'sort_order' => 8],
            ['name' => 'Admision', 'category' => Document::CATEGORY_ADMISSION, 'sort_order' => 9],
            ['name' => 'Historial', 'category' => Document::CATEGORY_HISTORY, 'sort_order' => 10],
            ['name' => 'Evidencia', 'category' => Document::CATEGORY_EVIDENCE, 'sort_order' => 11],
            ['name' => 'Audiencias', 'category' => Document::CATEGORY_HEARING, 'sort_order' => 12],
            ['name' => 'Otros', 'category' => Document::CATEGORY_OTHER, 'sort_order' => 13],
        ];

        // Determine if local storage — create physical dirs synchronously
        $tenant = $case->tenant ?? Tenant::find($case->tenant_id);
        $isLocal = !$tenant || !in_array($tenant->storage_type ?? 'local', ['onedrive', 'google_drive'], true);
        $rootPath = "tenants/{$case->tenant_id}/cases/{$case->case_number}";

        if ($isLocal) {
            Storage::disk('local')->makeDirectory($rootPath);
        }

        foreach ($defaultFolders as $folderData) {
            $folderPath = $rootPath . '/' . $folderData['name'];

            if ($isLocal) {
                Storage::disk('local')->makeDirectory($folderPath);
            }

            DocumentFolder::create([
                'tenant_id' => $case->tenant_id,
                'case_id' => $case->id,
                'parent_id' => null,
                'name' => $folderData['name'],
                'sort_order' => $folderData['sort_order'],
                'is_default' => true,
                'category' => $folderData['category'],
                'external_id' => $isLocal ? $folderPath : null,
                'sync_status' => $isLocal ? 'synced' : 'pending',
                'synced_at' => $isLocal ? now() : null,
            ]);
        }

        if ($isLocal) {
            $case->update([
                'root_external_folder_id' => $rootPath,
                'folder_sync_status' => 'synced',
                'folder_synced_at' => now(),
            ]);
        }
    }

    /**
     * Attempt to sync a newly created folder to the cloud provider.
     *
     * Only runs if the tenant uses a cloud provider AND the case already has
     * a root_external_folder_id (meaning the initial sync has already run).
     * Failures set sync_status to 'failed' but do not throw.
     */
    private function trySyncFolderToCloud(DocumentFolder $folder, ImmigrationCase $case): void
    {
        $tenant = Tenant::find($case->tenant_id);

        if (!$tenant || !in_array($tenant->storage_type, ['onedrive', 'google_drive'], true)) {
            return;
        }

        if (!$case->root_external_folder_id) {
            return;
        }

        try {
            $this->caseFolderSyncService->syncSingleFolder($folder);
        } catch (\Throwable $e) {
            Log::warning('FolderService: Cloud sync failed for new folder, marked as failed.', [
                'folder_id' => $folder->id,
                'folder_name' => $folder->name,
                'case_id' => $case->id,
                'error' => $e->getMessage(),
            ]);

            $folder->update(['sync_status' => 'failed']);
        }
    }
}
