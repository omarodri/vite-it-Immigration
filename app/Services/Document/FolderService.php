<?php

namespace App\Services\Document;

use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\ImmigrationCase;
use App\Models\Tenant;
use App\Services\Case\BlockResolvers\NameBlockResolver;
use App\Services\Document\FolderNameValidator;
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
        // Prevent duplicate folder names in the same parent
        $exists = DocumentFolder::where('case_id', $case->id)
            ->where('parent_id', $data['parent_id'] ?? null)
            ->where('name', $data['name'])
            ->exists();

        if ($exists) {
            throw new \RuntimeException("A folder named \"{$data['name']}\" already exists in this location.");
        }

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
        $isCloud = $tenant && in_array($tenant->storage_type, ['onedrive', 'google_drive', 'sharepoint'], true);

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

        // Sync rename to cloud if folder has an external_id
        if ($folder->external_id) {
            try {
                $tenant = Tenant::find($folder->tenant_id);
                if ($tenant && in_array($tenant->storage_type, ['onedrive', 'google_drive', 'sharepoint'], true)) {
                    $provider = $this->providerFactory->makeForTenant($tenant);
                    $provider->renameItem($folder->external_id, $name);
                }
            } catch (\Throwable $e) {
                Log::warning('FolderService: Failed to rename folder in cloud, local rename succeeded.', [
                    'folder_id' => $folder->id,
                    'external_id' => $folder->external_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

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
        // Check for active documents only (soft-deleted should not block deletion)
        $documentCount = Document::where('folder_id', $folder->id)->count();

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
                if ($tenant && in_array($tenant->storage_type, ['onedrive', 'google_drive', 'sharepoint'], true)) {
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
     * Backward-compatible alias — callers (tests, imports) continue working.
     */
    public function createDefaultStructure(ImmigrationCase $case): void
    {
        $this->createSelectedStructure($case, null);
    }

    /**
     * Create folder structure for a case based on wizard selection.
     * If $folders is null, falls back to the full default catalog (backward compatibility).
     *
     * @param  array<int,array{name:string,category:?string,is_custom?:bool}>|null  $folders
     */
    public function createSelectedStructure(ImmigrationCase $case, ?array $folders = null): void
    {
        if (DocumentFolder::where('case_id', $case->id)->exists()) {
            Log::info('FolderService: Folders already exist, skipping', ['case_id' => $case->id]);
            return;
        }

        $selection = $folders !== null
            ? $this->normalizeSelection($folders)
            : $this->defaultCatalogSelection();

        if (empty($selection)) {
            Log::info('FolderService: Empty folder selection, case created without folders', ['case_id' => $case->id]);
            // Mark the case as processed so the Documents tab does not auto-create
            // default folders later — distinguishes "user chose no folders" from
            // "legacy case that was never initialized."
            $case->update(['folder_sync_status' => 'synced']);
            return;
        }

        $tenant    = $case->tenant ?? Tenant::find($case->tenant_id);
        $isLocal   = !$tenant || !in_array($tenant->storage_type ?? 'local', ['onedrive', 'google_drive', 'sharepoint'], true);

        $folderLabel = $case->case_number;
        if ($tenant && $tenant->caseCodeIncludeName()) {
            $applicant   = $case->primaryApplicant();
            $fullName    = '';
            if ($applicant) {
                $firstName = method_exists($applicant, 'first_name') ? ($applicant->first_name ?? '') : ($applicant->first_name ?? '');
                $lastName  = $applicant->last_name ?? '';
                $fullName  = trim($firstName . ' ' . $lastName);
            }
            if ($fullName) {
                $sep         = $tenant->caseCodeSeparator() !== '' ? $tenant->caseCodeSeparator() : '-';
                $nameSegment = app(NameBlockResolver::class)->resolveFromString($fullName);
                if ($nameSegment) {
                    $folderLabel .= $sep . $nameSegment;
                }
            }
        }

        $rootPath  = $tenant && $tenant->base_folder_path
            ? "tenants/{$case->tenant_id}/{$tenant->base_folder_path}/cases/{$folderLabel}"
            : "tenants/{$case->tenant_id}/cases/{$folderLabel}";

        Log::info('FolderService: Creating selected folder structure', [
            'case_id'      => $case->id,
            'case_number'  => $case->case_number,
            'tenant_id'    => $case->tenant_id,
            'storage_type' => $tenant?->storage_type ?? 'local',
            'is_local'     => $isLocal,
            'folder_count' => count($selection),
        ]);

        if ($isLocal) {
            Storage::disk('local')->makeDirectory($rootPath);
        }

        $created    = 0;
        $seenLower  = [];

        foreach ($selection as $index => $folderData) {
            $name  = trim($folderData['name']);
            $lower = mb_strtolower($name);

            if (isset($seenLower[$lower])) {
                Log::warning('FolderService: Duplicate folder name in selection, skipping', [
                    'case_id' => $case->id, 'name' => $name,
                ]);
                continue;
            }
            $seenLower[$lower] = true;

            if (!FolderNameValidator::isValid($name)) {
                Log::warning('FolderService: Invalid folder name in selection, skipping', [
                    'case_id' => $case->id, 'name' => $name,
                ]);
                continue;
            }

            $folderPath = $rootPath . '/' . $name;

            if ($isLocal) {
                Storage::disk('local')->makeDirectory($folderPath);
            }

            DocumentFolder::create([
                'tenant_id'   => $case->tenant_id,
                'case_id'     => $case->id,
                'parent_id'   => null,
                'name'        => $name,
                'sort_order'  => $index,
                'is_default'  => !($folderData['is_custom'] ?? false),
                'category'    => $folderData['category'] ?? null,
                'external_id' => $isLocal ? $folderPath : null,
                'sync_status' => $isLocal ? 'synced' : 'pending',
                'synced_at'   => $isLocal ? now() : null,
            ]);
            $created++;
        }

        if ($isLocal && $created > 0) {
            $case->update([
                'root_external_folder_id' => $rootPath,
                'folder_sync_status'      => 'synced',
                'folder_synced_at'        => now(),
            ]);
        }

        Log::info('FolderService: Folder structure created', [
            'case_id'         => $case->id,
            'folders_created' => $created,
        ]);
    }

    /**
     * @param  array<int,array<string,mixed>>  $folders
     * @return array<int,array{name:string,category:?string,is_custom:bool}>
     */
    private function normalizeSelection(array $folders): array
    {
        $maxTotal = (int) config('case_folders.validation.max_total_per_case', 30);

        return collect($folders)
            ->take($maxTotal)
            ->map(fn ($f) => [
                'name'      => (string) ($f['name'] ?? ''),
                'category'  => isset($f['category']) ? (string) $f['category'] : null,
                'is_custom' => (bool) ($f['is_custom'] ?? false),
            ])
            ->filter(fn ($f) => $f['name'] !== '')
            ->values()
            ->all();
    }

    /**
     * Build a selection from the full catalog using Spanish names (backward-compatible behavior).
     *
     * @return array<int,array{name:string,category:string,is_custom:bool}>
     */
    private function defaultCatalogSelection(): array
    {
        $defaults = config('case_folders.defaults', []);

        $spanish = [
            'admission'     => 'Admision',
            'archive'       => 'Archivo',
            'hearing'       => 'Audiencias',
            'letters'       => 'Cartas',
            'communication' => 'Comunicaciones',
            'accounting'    => 'Contabilidad',
            'contract'      => 'Contrato',
            'documents'     => 'Documentos',
            'links'         => 'Enlaces',
            'evidence'      => 'Evidencia',
            'forms'         => 'Formularios',
            'history'       => 'Historial',
            'other'         => 'Otros',
            'questionary'   => 'Questionarios',
        ];

        return collect($defaults)
            ->filter(fn ($d) => ($d['enabled_by_default'] ?? true) === true)
            ->map(fn ($d) => [
                'name'      => $spanish[$d['key']] ?? ucfirst((string) $d['key']),
                'category'  => $d['category'] ?? null,
                'is_custom' => false,
            ])
            ->values()
            ->all();
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

        if (!$tenant || !in_array($tenant->storage_type, ['onedrive', 'google_drive', 'sharepoint'], true)) {
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
