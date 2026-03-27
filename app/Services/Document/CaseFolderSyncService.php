<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Contracts\DocumentStorageInterface;
use App\Models\DocumentFolder;
use App\Models\ImmigrationCase;
use App\Models\Tenant;
use App\Services\Storage\StorageProviderFactory;
use Illuminate\Support\Facades\Log;

class CaseFolderSyncService
{
    public function __construct(
        private readonly StorageProviderFactory $providerFactory,
    ) {}

    /**
     * Sync the entire folder structure of a case to the cloud provider.
     *
     * Creates a root folder for the case, then creates all subfolders inside it.
     * Idempotent: skips folders that already have an external_id.
     */
    public function syncFolderStructure(ImmigrationCase $case): void
    {
        $tenant = $case->tenant ?? Tenant::findOrFail($case->tenant_id);
        $provider = $this->providerFactory->makeForTenant($tenant);

        // Step 1: Create or reuse the root case folder in the cloud
        $rootExternalId = $case->root_external_folder_id;

        if (!$rootExternalId) {
            $rootFolderName = "Case-{$case->case_number}";
            $baseFolderExternalId = $this->resolveBaseFolderExternalId($tenant, $provider);

            try {
                $rootResult = $provider->createFolder($rootFolderName, $baseFolderExternalId);
                $rootExternalId = $rootResult['external_id'];

                $case->update([
                    'root_external_folder_id' => $rootExternalId,
                    'folder_sync_status' => 'pending',
                ]);
            } catch (\Throwable $e) {
                Log::error('CaseFolderSyncService: failed to create root folder', [
                    'case_id' => $case->id,
                    'error' => $e->getMessage(),
                ]);

                $case->update([
                    'folder_sync_status' => 'failed',
                ]);

                throw $e;
            }
        }

        // Step 2: Sync all root-level folders (parent_id is null)
        $rootFolders = DocumentFolder::byCase($case->id)
            ->roots()
            ->orderBy('sort_order')
            ->get();

        $allSynced = true;

        foreach ($rootFolders as $folder) {
            try {
                $this->syncSingleFolder($folder, $provider, $rootExternalId);
                $this->syncChildFolders($folder, $provider);
            } catch (\Throwable $e) {
                $allSynced = false;
                Log::warning('CaseFolderSyncService: failed to sync folder', [
                    'folder_id' => $folder->id,
                    'folder_name' => $folder->name,
                    'case_id' => $case->id,
                    'error' => $e->getMessage(),
                ]);
                // Continue syncing other folders
            }
        }

        // Step 3: Update case sync status
        $case->update([
            'folder_sync_status' => $allSynced ? 'synced' : 'failed',
            'folder_synced_at' => $allSynced ? now() : $case->folder_synced_at,
        ]);
    }

    /**
     * Sync a single folder to the cloud provider.
     *
     * If the folder already has an external_id, it is considered synced and skipped.
     */
    public function syncSingleFolder(
        DocumentFolder $folder,
        ?DocumentStorageInterface $provider = null,
        ?string $parentExternalId = null
    ): void {
        // Already synced - idempotent check
        if ($folder->external_id) {
            $folder->update([
                'sync_status' => 'synced',
                'synced_at' => $folder->synced_at ?? now(),
            ]);

            return;
        }

        // Resolve provider if not provided
        if (!$provider) {
            $tenant = $folder->case->tenant ?? Tenant::findOrFail($folder->tenant_id);
            $provider = $this->providerFactory->makeForTenant($tenant);
        }

        // Resolve parent external ID if not provided
        if (!$parentExternalId && $folder->parent_id) {
            $parent = $folder->parent;
            $parentExternalId = $parent?->external_id;
        } elseif (!$parentExternalId && !$folder->parent_id) {
            // Root folder: use the case's root_external_folder_id
            $case = $folder->case ?? ImmigrationCase::findOrFail($folder->case_id);
            $parentExternalId = $case->root_external_folder_id;
        }

        try {
            $result = $provider->createFolder($folder->name, $parentExternalId);

            $folder->update([
                'external_id' => $result['external_id'],
                'external_url' => $result['external_url'],
                'sync_status' => 'synced',
                'synced_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $folder->update([
                'sync_status' => 'failed',
            ]);

            throw $e;
        }
    }

    /**
     * Recursively sync child folders.
     */
    private function syncChildFolders(DocumentFolder $parentFolder, DocumentStorageInterface $provider): void
    {
        $children = $parentFolder->children()->orderBy('sort_order')->get();

        foreach ($children as $child) {
            try {
                $this->syncSingleFolder($child, $provider, $parentFolder->external_id);
                $this->syncChildFolders($child, $provider);
            } catch (\Throwable $e) {
                Log::warning('CaseFolderSyncService: failed to sync child folder', [
                    'folder_id' => $child->id,
                    'parent_folder_id' => $parentFolder->id,
                    'error' => $e->getMessage(),
                ]);
                // Continue syncing other children
            }
        }
    }

    private function resolveBaseFolderExternalId(Tenant $tenant, DocumentStorageInterface $provider): ?string
    {
        if (!$tenant->base_folder_path) {
            return null;
        }

        if ($tenant->base_folder_external_id) {
            return $tenant->base_folder_external_id;
        }

        $result = $provider->createFolder($tenant->base_folder_path);
        $tenant->update(['base_folder_external_id' => $result['external_id']]);

        return $result['external_id'];
    }

    /**
     * Get sync status information for a case and all its folders.
     *
     * @return array{case_sync_status: string, case_synced_at: ?string, folders: array}
     */
    public function getSyncStatus(ImmigrationCase $case): array
    {
        $folders = DocumentFolder::byCase($case->id)
            ->select(['id', 'name', 'parent_id', 'external_id', 'sync_status', 'synced_at'])
            ->orderBy('sort_order')
            ->get();

        $totalFolders = $folders->count();
        $syncedFolders = $folders->where('sync_status', 'synced')->count();
        $failedFolders = $folders->where('sync_status', 'failed')->count();
        $pendingFolders = $folders->where('sync_status', 'pending')->count();

        return [
            'case_sync_status' => $case->folder_sync_status ?? 'not_applicable',
            'case_synced_at' => $case->folder_synced_at?->toISOString(),
            'root_external_folder_id' => $case->root_external_folder_id,
            'summary' => [
                'total' => $totalFolders,
                'synced' => $syncedFolders,
                'failed' => $failedFolders,
                'pending' => $pendingFolders,
            ],
            'folders' => $folders->map(fn (DocumentFolder $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'parent_id' => $f->parent_id,
                'external_id' => $f->external_id,
                'sync_status' => $f->sync_status,
                'synced_at' => $f->synced_at?->toISOString(),
            ])->values()->toArray(),
        ];
    }
}
