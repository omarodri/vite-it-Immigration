<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentFolderResource;
use App\Jobs\SyncCaseFolderStructure;
use App\Models\DocumentFolder;
use App\Models\ImmigrationCase;
use App\Services\Document\CaseFolderSyncService;
use App\Services\Document\FolderService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DocumentFolderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly FolderService $folderService,
        private readonly CaseFolderSyncService $caseFolderSyncService,
    ) {}

    /**
     * Get the folder tree for a case.
     */
    public function index(ImmigrationCase $case): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [DocumentFolder::class, $case]);

        $this->validateCaseTenant($case);

        $folders = $this->folderService->getFolderTree($case);

        return DocumentFolderResource::collection($folders);
    }

    /**
     * Create a new folder for a case.
     */
    public function store(Request $request, ImmigrationCase $case): JsonResponse
    {
        $this->authorize('create', [DocumentFolder::class, $case]);

        $this->validateCaseTenant($case);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:document_folders,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'category' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $folder = $this->folderService->createFolder($case, $validated);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new DocumentFolderResource($folder))
            ->additional(['message' => 'Folder created successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Rename a folder.
     */
    public function update(Request $request, ImmigrationCase $case, DocumentFolder $folder): DocumentFolderResource
    {
        $this->authorize('update', $folder);

        $this->validateFolderBelongsToCase($folder, $case);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $folder = $this->folderService->renameFolder($folder, $validated['name']);

        return (new DocumentFolderResource($folder))
            ->additional(['message' => 'Folder renamed successfully.']);
    }

    /**
     * Delete a folder (must be empty).
     */
    public function destroy(ImmigrationCase $case, DocumentFolder $folder): JsonResponse
    {
        $this->authorize('delete', $folder);

        $this->validateFolderBelongsToCase($folder, $case);

        try {
            $this->folderService->deleteFolder($folder);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Folder deleted successfully.']);
    }

    /**
     * Initialize default folder structure for a case that has no folders,
     * and sync to cloud if applicable. Runs synchronously — no queue needed.
     */
    public function initialize(ImmigrationCase $case): JsonResponse
    {
        $this->authorize('create', [DocumentFolder::class, $case]);

        $this->validateCaseTenant($case);

        $existingCount = DocumentFolder::where('case_id', $case->id)->count();

        // Step 1: Create DB folders if none exist
        if ($existingCount === 0) {
            $this->folderService->createDefaultStructure($case);
            $existingCount = DocumentFolder::where('case_id', $case->id)->count();
        }

        // Step 2: If cloud storage and folders are pending, sync now (synchronously)
        $case->refresh();
        if (!$case->root_external_folder_id || $case->folder_sync_status === 'pending' || $case->folder_sync_status === 'failed') {
            $tenant = $case->tenant ?? \App\Models\Tenant::find($case->tenant_id);
            if ($tenant && in_array($tenant->storage_type, ['onedrive', 'google_drive', 'sharepoint'], true)) {
                try {
                    $this->caseFolderSyncService->syncFolderStructure($case);
                } catch (\Throwable $e) {
                    return response()->json([
                        'message' => "Created {$existingCount} folders. Cloud sync failed: " . $e->getMessage(),
                        'folders_count' => $existingCount,
                    ], 201);
                }
            }
        }

        return response()->json([
            'message' => "Initialized {$existingCount} folders.",
            'folders_count' => $existingCount,
        ], 201);
    }

    /**
     * Dispatch a job to sync the folder structure of a case to the cloud provider.
     *
     * Returns 202 Accepted since the sync happens asynchronously.
     */
    public function sync(ImmigrationCase $case): JsonResponse
    {
        $this->authorize('viewAny', [DocumentFolder::class, $case]);

        $this->validateCaseTenant($case);

        $case->update(['folder_sync_status' => 'pending']);

        SyncCaseFolderStructure::dispatch($case->id);

        return response()->json([
            'message' => 'Folder sync has been queued.',
            'folder_sync_status' => 'pending',
        ], 202);
    }

    /**
     * Get the current sync status for a case and all its folders.
     */
    public function syncStatus(ImmigrationCase $case): JsonResponse
    {
        $this->authorize('viewAny', [DocumentFolder::class, $case]);

        $this->validateCaseTenant($case);

        $status = $this->caseFolderSyncService->getSyncStatus($case);

        return response()->json(['data' => $status]);
    }

    /**
     * Validate that the case belongs to the authenticated user's tenant.
     */
    private function validateCaseTenant(ImmigrationCase $case): void
    {
        if ($case->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'Unauthorized access to this case.');
        }
    }

    /**
     * Validate that the folder belongs to the given case.
     */
    private function validateFolderBelongsToCase(DocumentFolder $folder, ImmigrationCase $case): void
    {
        $this->validateCaseTenant($case);

        if ($folder->case_id !== $case->id) {
            abort(404, 'Folder not found for this case.');
        }
    }
}
