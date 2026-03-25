<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\ImmigrationCase;
use App\Models\Tenant;
use App\Services\Storage\StorageProviderFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CloudDocumentSyncService
{
    public function __construct(
        private readonly StorageProviderFactory $providerFactory,
    ) {}

    /**
     * Pull folders and documents from cloud storage that don't exist in the database,
     * and prune local folders that no longer exist in cloud.
     *
     * @return array{folders_added: int, folders_removed: int, documents_added: int, documents_removed: int}
     */
    public function syncFromCloud(ImmigrationCase $case): array
    {
        $tenant = $case->tenant ?? Tenant::findOrFail($case->tenant_id);
        $storageType = $tenant->storage_type ?? 'local';

        if (!in_array($storageType, ['onedrive', 'google_drive'], true)) {
            return ['folders_added' => 0, 'folders_removed' => 0, 'documents_added' => 0, 'documents_removed' => 0];
        }

        $provider = $this->providerFactory->makeForTenant($tenant);

        // Step 0: Clean up duplicate documents (same external_id in same case)
        $this->deduplicateDocuments($case);

        // Step 1: Sync folders (add new, collect cloud inventory)
        [$foldersAdded, $cloudFolderExternalIds] = $this->pullFolders($case, $provider);

        // Step 2: Prune folders deleted from cloud
        $foldersRemoved = $this->pruneFolders($case, $cloudFolderExternalIds);

        // Step 3: Sync documents (add new, collect cloud inventory)
        [$docsAdded, $cloudDocExternalIds] = $this->pullDocuments($case, $provider, $storageType);

        // Step 4: Prune documents deleted from cloud
        $docsRemoved = $this->pruneDocuments($case, $cloudDocExternalIds);

        return [
            'folders_added' => $foldersAdded,
            'folders_removed' => $foldersRemoved,
            'documents_added' => $docsAdded,
            'documents_removed' => $docsRemoved,
        ];
    }

    /**
     * Backwards-compatible wrapper: pull only documents.
     */
    public function syncDocumentsFromCloud(ImmigrationCase $case): int
    {
        $result = $this->syncFromCloud($case);

        return $result['documents_added'];
    }

    /**
     * Pull folders from cloud that don't exist in the database.
     * Scans the root case folder and all known subfolders recursively.
     * Also updates local folder names when they differ from cloud (mirrors renames).
     *
     * @return array{0: int, 1: array<string>} [imported count, all cloud folder external_ids]
     */
    private function pullFolders(ImmigrationCase $case, $provider): array
    {
        if (!$case->root_external_folder_id) {
            return [0, []];
        }

        // Existing folder external_ids for this case (keyed by local ID)
        $existingExternalIds = DocumentFolder::where('case_id', $case->id)
            ->whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->pluck('external_id', 'id')
            ->toArray();

        $imported = 0;
        $cloudFolderExternalIds = []; // Collect all folder IDs seen in cloud
        $maxSortOrder = DocumentFolder::where('case_id', $case->id)->max('sort_order') ?? -1;

        // Queue of [cloud_external_id, local_parent_folder_id]
        $queue = [[$case->root_external_folder_id, null]];

        while (!empty($queue)) {
            [$parentExternalId, $parentFolderId] = array_shift($queue);

            try {
                $cloudItems = $provider->listFolder($parentExternalId);
            } catch (\Throwable $e) {
                Log::warning('CloudDocumentSyncService: Failed to list folder for folder sync', [
                    'case_id' => $case->id,
                    'parent_external_id' => $parentExternalId,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            foreach ($cloudItems as $item) {
                if ($item['type'] !== 'folder') {
                    continue;
                }

                $cloudFolderExternalIds[] = $item['external_id'];

                // Already tracked?
                if (in_array($item['external_id'], $existingExternalIds, true)) {
                    $localId = array_search($item['external_id'], $existingExternalIds, true);

                    // Update local name if it was renamed in cloud
                    $localFolder = DocumentFolder::find($localId);
                    if ($localFolder && $localFolder->name !== $item['name']) {
                        $localFolder->update(['name' => $item['name']]);
                    }

                    $queue[] = [$item['external_id'], $localId];
                    continue;
                }

                // New folder — create it
                $maxSortOrder++;
                $folder = DocumentFolder::create([
                    'tenant_id' => $case->tenant_id,
                    'case_id' => $case->id,
                    'parent_id' => $parentFolderId,
                    'name' => $item['name'],
                    'sort_order' => $maxSortOrder,
                    'is_default' => false,
                    'external_id' => $item['external_id'],
                    'sync_status' => 'synced',
                    'synced_at' => now(),
                ]);

                $existingExternalIds[$folder->id] = $item['external_id'];
                $imported++;

                // Queue this new folder to scan its children too
                $queue[] = [$item['external_id'], $folder->id];
            }
        }

        if ($imported > 0) {
            Log::info('CloudDocumentSyncService: Imported folders from cloud', [
                'case_id' => $case->id,
                'imported_count' => $imported,
            ]);
        }

        return [$imported, $cloudFolderExternalIds];
    }

    /**
     * Remove DB folders whose external_id no longer exists in cloud.
     * Only deletes folders that are empty (no documents, no children).
     * Processes leaf folders first (deepest nesting) so parent folders
     * become eligible for deletion after their children are removed.
     */
    private function pruneFolders(ImmigrationCase $case, array $cloudFolderExternalIds): int
    {
        // Find local folders with external_id that are NOT in the cloud set
        $staleFolders = DocumentFolder::where('case_id', $case->id)
            ->whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->whereNotIn('external_id', $cloudFolderExternalIds)
            ->get();

        if ($staleFolders->isEmpty()) {
            return 0;
        }

        $removed = 0;

        // Sort by depth (deepest first) so children are deleted before parents.
        // We approximate depth by counting parent_id chain via a simple sort:
        // folders with parent_id come after those without, and we reverse.
        $orderedIds = $this->sortByDepthDesc($staleFolders);

        foreach ($orderedIds as $folderId) {
            $folder = DocumentFolder::find($folderId);
            if (!$folder) {
                continue;
            }

            // Only delete if empty (no documents AND no child folders)
            $hasDocuments = Document::where('folder_id', $folder->id)->exists();
            $hasChildren = DocumentFolder::where('parent_id', $folder->id)->exists();

            if ($hasDocuments || $hasChildren) {
                continue;
            }

            $folder->delete();
            $removed++;
        }

        if ($removed > 0) {
            Log::info('CloudDocumentSyncService: Pruned folders deleted from cloud', [
                'case_id' => $case->id,
                'removed_count' => $removed,
            ]);
        }

        return $removed;
    }

    /**
     * Sort folder IDs by nesting depth (deepest first).
     */
    private function sortByDepthDesc($folders): array
    {
        $parentMap = $folders->pluck('parent_id', 'id')->toArray();
        $allFolderIds = array_keys($parentMap);

        // Calculate depth for each folder
        $depths = [];
        foreach ($allFolderIds as $id) {
            $depth = 0;
            $current = $id;
            $visited = [];
            while (isset($parentMap[$current]) && $parentMap[$current] !== null && !in_array($current, $visited)) {
                $visited[] = $current;
                $current = $parentMap[$current];
                $depth++;
            }
            $depths[$id] = $depth;
        }

        // Sort by depth descending
        arsort($depths);

        return array_keys($depths);
    }

    /**
     * Pull documents from cloud that don't exist in the database.
     *
     * @return array{0: int, 1: array<string>} [imported count, all cloud document external_ids]
     */
    private function pullDocuments(ImmigrationCase $case, $provider, string $storageType): array
    {
        $imported = 0;
        $cloudDocExternalIds = [];

        $uploadedBy = Auth::id();
        if (!$uploadedBy) {
            $uploadedBy = \App\Models\User::where('tenant_id', $case->tenant_id)->first()?->id;
        }

        // Build scan targets: root folder + all subfolders with external_id
        $foldersToScan = [];

        if ($case->root_external_folder_id) {
            $foldersToScan[] = [
                'external_id' => $case->root_external_folder_id,
                'folder_id' => null,
            ];
        }

        $folders = DocumentFolder::where('case_id', $case->id)
            ->whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->get();

        foreach ($folders as $folder) {
            $foldersToScan[] = [
                'external_id' => $folder->external_id,
                'folder_id' => $folder->id,
            ];
        }

        $existingExternalIds = Document::where('case_id', $case->id)
            ->whereNotNull('external_id')
            ->pluck('external_id')
            ->toArray();

        $storageTypeConstant = match ($storageType) {
            'onedrive' => Document::STORAGE_ONEDRIVE,
            'google_drive' => Document::STORAGE_GOOGLE_DRIVE,
            default => Document::STORAGE_LOCAL,
        };

        foreach ($foldersToScan as $scanTarget) {
            try {
                $cloudItems = $provider->listFolder($scanTarget['external_id']);

                foreach ($cloudItems as $item) {
                    if ($item['type'] === 'folder') {
                        continue;
                    }

                    $cloudDocExternalIds[] = $item['external_id'];

                    if (in_array($item['external_id'], $existingExternalIds, true)) {
                        // Update local name/folder if renamed or moved in cloud
                        $existingDoc = Document::where('case_id', $case->id)
                            ->where('external_id', $item['external_id'])
                            ->first();
                        if ($existingDoc) {
                            $updates = [];
                            if ($existingDoc->original_name !== $item['name']) {
                                $updates['name'] = $item['name'];
                                $updates['original_name'] = $item['name'];
                            }
                            if ($existingDoc->folder_id !== $scanTarget['folder_id']) {
                                $updates['folder_id'] = $scanTarget['folder_id'];
                            }
                            if (!empty($updates)) {
                                $existingDoc->update($updates);
                            }
                        }
                        continue;
                    }

                    $doc = Document::firstOrCreate(
                        [
                            'case_id' => $case->id,
                            'external_id' => $item['external_id'],
                        ],
                        [
                            'tenant_id' => $case->tenant_id,
                            'folder_id' => $scanTarget['folder_id'],
                            'uploaded_by' => $uploadedBy,
                            'name' => $item['name'],
                            'original_name' => $item['name'],
                            'mime_type' => $item['mime_type'] ?? 'application/octet-stream',
                            'size' => $item['size'] ?? 0,
                            'category' => Document::CATEGORY_OTHER,
                            'storage_type' => $storageTypeConstant,
                            'storage_path' => '',
                            'external_url' => $item['web_url'] ?? '',
                            'version' => 1,
                            'checksum' => null,
                        ]
                    );

                    $existingExternalIds[] = $item['external_id'];
                    if ($doc->wasRecentlyCreated) {
                        $imported++;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('CloudDocumentSyncService: Failed to scan folder for documents', [
                    'case_id' => $case->id,
                    'folder_external_id' => $scanTarget['external_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($imported > 0) {
            Log::info('CloudDocumentSyncService: Imported documents from cloud', [
                'case_id' => $case->id,
                'imported_count' => $imported,
            ]);
        }

        return [$imported, $cloudDocExternalIds];
    }

    /**
     * Remove duplicate document records that share the same external_id within a case.
     * Keeps the oldest record (lowest id) and deletes the rest.
     */
    private function deduplicateDocuments(ImmigrationCase $case): void
    {
        $duplicates = Document::where('case_id', $case->id)
            ->whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->selectRaw('external_id, MIN(id) as keep_id, COUNT(*) as cnt')
            ->groupBy('external_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            return;
        }

        $removed = 0;
        foreach ($duplicates as $dup) {
            $deleted = Document::where('case_id', $case->id)
                ->where('external_id', $dup->external_id)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
            $removed += $deleted;
        }

        Log::info('CloudDocumentSyncService: Removed duplicate documents', [
            'case_id' => $case->id,
            'duplicates_removed' => $removed,
        ]);
    }

    /**
     * Remove DB documents whose external_id no longer exists in cloud.
     */
    private function pruneDocuments(ImmigrationCase $case, array $cloudDocExternalIds): int
    {
        $staleDocuments = Document::where('case_id', $case->id)
            ->whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->whereNotIn('external_id', $cloudDocExternalIds)
            ->get();

        if ($staleDocuments->isEmpty()) {
            return 0;
        }

        $removed = 0;

        foreach ($staleDocuments as $document) {
            $document->delete();
            $removed++;
        }

        if ($removed > 0) {
            Log::info('CloudDocumentSyncService: Pruned documents deleted from cloud', [
                'case_id' => $case->id,
                'removed_count' => $removed,
            ]);
        }

        return $removed;
    }
}
