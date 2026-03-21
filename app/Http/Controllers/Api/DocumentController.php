<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Http\Requests\Document\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\ImmigrationCase;
use App\Services\Document\DocumentAuditService;
use App\Services\Document\DocumentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DocumentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly DocumentService $documentService,
        private readonly DocumentAuditService $auditService,
    ) {}

    /**
     * List documents for a case.
     */
    public function index(Request $request, ImmigrationCase $case): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Document::class, $case]);

        $this->validateCaseTenant($case);

        $folderId = $request->has('folder_id') ? $request->integer('folder_id') : null;

        $query = Document::byCase($case->id)
            ->with('uploader:id,name');

        if ($folderId !== null) {
            $query->byFolder($folderId);
        }

        if ($request->filled('category')) {
            $query->byCategory($request->input('category'));
        }

        $documents = $query->orderBy('created_at', 'desc')->get();

        return DocumentResource::collection($documents);
    }

    /**
     * Upload a document to a case.
     */
    public function store(StoreDocumentRequest $request, ImmigrationCase $case): JsonResponse
    {
        $this->authorize('create', [Document::class, $case]);

        $this->validateCaseTenant($case);

        $document = $this->documentService->uploadDocument(
            $case,
            $request->file('file'),
            $request->only(['folder_id', 'category'])
        );

        return (new DocumentResource($document))
            ->additional(['message' => 'Document uploaded successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show document metadata.
     */
    public function show(ImmigrationCase $case, Document $document): DocumentResource
    {
        $this->authorize('view', $document);

        $this->validateDocumentBelongsToCase($document, $case);

        $this->auditService->logAccess($document, 'viewed');

        return new DocumentResource($document->load('uploader:id,name'));
    }

    /**
     * Download a document file.
     */
    public function download(ImmigrationCase $case, Document $document)
    {
        $this->authorize('download', $document);

        $this->validateDocumentBelongsToCase($document, $case);

        $this->auditService->logAccess($document, 'downloaded');

        return $this->documentService->downloadDocument($document);
    }

    /**
     * Preview a document inline (PDF/images).
     */
    public function preview(ImmigrationCase $case, Document $document)
    {
        $this->authorize('view', $document);

        $this->validateDocumentBelongsToCase($document, $case);

        $this->auditService->logAccess($document, 'previewed');

        return $this->documentService->previewDocument($document);
    }

    /**
     * Update document metadata.
     */
    public function update(UpdateDocumentRequest $request, ImmigrationCase $case, Document $document): DocumentResource
    {
        $this->authorize('update', $document);

        $this->validateDocumentBelongsToCase($document, $case);

        $document->update($request->validated());

        return (new DocumentResource($document->fresh('uploader:id,name')))
            ->additional(['message' => 'Document updated successfully.']);
    }

    /**
     * Replace the file of an existing document.
     */
    public function replace(Request $request, ImmigrationCase $case, Document $document): DocumentResource
    {
        $this->authorize('replace', $document);

        $this->validateDocumentBelongsToCase($document, $case);

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:51200',
                'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,txt,csv,zip',
            ],
        ]);

        $document = $this->documentService->replaceDocument($document, $request->file('file'));

        return (new DocumentResource($document))
            ->additional(['message' => 'Document replaced successfully.']);
    }

    /**
     * Move a document to a different folder.
     */
    public function move(Request $request, ImmigrationCase $case, Document $document): DocumentResource
    {
        $this->authorize('move', $document);

        $this->validateDocumentBelongsToCase($document, $case);

        $request->validate([
            'folder_id' => ['required', 'integer', 'exists:document_folders,id'],
        ]);

        $document = $this->documentService->moveDocument($document, $request->integer('folder_id'));

        return (new DocumentResource($document))
            ->additional(['message' => 'Document moved successfully.']);
    }

    /**
     * Delete a document (soft delete).
     */
    public function destroy(ImmigrationCase $case, Document $document): JsonResponse
    {
        $this->authorize('delete', $document);

        $this->validateDocumentBelongsToCase($document, $case);

        $this->documentService->deleteDocument($document);

        return response()->json(['message' => 'Document deleted successfully.']);
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
     * Validate that the document belongs to the given case.
     */
    private function validateDocumentBelongsToCase(Document $document, ImmigrationCase $case): void
    {
        $this->validateCaseTenant($case);

        if ($document->case_id !== $case->id) {
            abort(404, 'Document not found for this case.');
        }
    }
}
