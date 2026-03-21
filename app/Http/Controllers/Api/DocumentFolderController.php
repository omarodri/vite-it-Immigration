<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentFolderResource;
use App\Models\DocumentFolder;
use App\Models\ImmigrationCase;
use App\Services\Document\FolderService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DocumentFolderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly FolderService $folderService
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

        $folder = $this->folderService->createFolder($case, $validated);

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
