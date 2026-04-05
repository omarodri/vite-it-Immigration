<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LegalDocument\StoreLegalDocumentRequest;
use App\Http\Requests\LegalDocument\UpdateLegalDocumentRequest;
use App\Http\Resources\LegalDocumentResource;
use App\Models\Client;
use App\Models\Companion;
use App\Models\LegalDocument;
use App\Services\LegalDocumentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LegalDocumentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private LegalDocumentService $service
    ) {}

    /**
     * GET /api/clients/{client}/documents
     * List legal documents for a client.
     */
    public function indexForClient(Request $request, Client $client): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LegalDocument::class);

        abort_if($client->tenant_id !== $request->user()->tenant_id, 403);

        $documents = $this->service->getDocumentsForEntity($client);

        return LegalDocumentResource::collection($documents);
    }

    /**
     * GET /api/companions/{companion}/documents
     * List legal documents for a companion.
     */
    public function indexForCompanion(Request $request, Companion $companion): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LegalDocument::class);

        abort_if($companion->tenant_id !== $request->user()->tenant_id, 403);

        $documents = $this->service->getDocumentsForEntity($companion);

        return LegalDocumentResource::collection($documents);
    }

    /**
     * POST /api/clients/{client}/documents
     * Create a legal document for a client.
     */
    public function storeForClient(StoreLegalDocumentRequest $request, Client $client): JsonResponse
    {
        $this->authorize('create', LegalDocument::class);

        abort_if($client->tenant_id !== $request->user()->tenant_id, 403);

        $document = $this->service->createDocument($client, $request->validated());

        return (new LegalDocumentResource($document))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * POST /api/companions/{companion}/documents
     * Create a legal document for a companion.
     */
    public function storeForCompanion(StoreLegalDocumentRequest $request, Companion $companion): JsonResponse
    {
        $this->authorize('create', LegalDocument::class);

        abort_if($companion->tenant_id !== $request->user()->tenant_id, 403);

        $document = $this->service->createDocument($companion, $request->validated());

        return (new LegalDocumentResource($document))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /api/legal-documents/{document}
     * Update an existing legal document.
     */
    public function update(UpdateLegalDocumentRequest $request, LegalDocument $document): LegalDocumentResource
    {
        $this->authorize('update', $document);

        abort_if($document->tenant_id !== $request->user()->tenant_id, 403);

        $document = $this->service->updateDocument($document, $request->validated());

        return new LegalDocumentResource($document);
    }

    /**
     * DELETE /api/legal-documents/{document}
     * Delete a legal document.
     */
    public function destroy(Request $request, LegalDocument $document): \Illuminate\Http\Response
    {
        $this->authorize('delete', $document);

        abort_if($document->tenant_id !== $request->user()->tenant_id, 403);

        $this->service->deleteDocument($document);

        return response()->noContent();
    }

    /**
     * POST /api/legal-documents/{document}/sync-event
     * Create a calendar event from a document's expiry date.
     */
    public function syncToCalendar(Request $request, LegalDocument $document): JsonResponse
    {
        $this->authorize('update', $document);

        abort_if($document->tenant_id !== $request->user()->tenant_id, 403);

        if (! $document->expiry_date) {
            return response()->json([
                'message' => 'Cannot sync to calendar: document has no expiry date.',
            ], 422);
        }

        $document->load('documentable');

        $event = $this->service->syncToCalendar($document, $request->user());

        return response()->json([
            'message' => 'Event created successfully.',
            'data'    => [
                'id'         => $event->id,
                'title'      => $event->title,
                'start_date' => $event->start_date->toISOString(),
                'end_date'   => $event->end_date->toISOString(),
                'all_day'    => $event->all_day,
                'category'   => $event->category,
            ],
        ], 201);
    }
}
