<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Companion\StoreCompanionRequest;
use App\Http\Requests\Companion\UpdateCompanionRequest;
use App\Http\Resources\CompanionResource;
use App\Models\Client;
use App\Models\Companion;
use App\Services\Companion\CompanionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private CompanionService $companionService
    ) {}

    /**
     * List all companions for a client.
     */
    public function index(Client $client): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Companion::class, $client]);

        $companions = $this->companionService->listCompanions($client);

        return CompanionResource::collection($companions);
    }

    /**
     * Store a new companion for a client.
     */
    public function store(StoreCompanionRequest $request, Client $client): JsonResponse
    {
        $this->authorize('create', [Companion::class, $client]);

        $companion = $this->companionService->createCompanion($client, $request->validated());

        return (new CompanionResource($companion))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display a specific companion.
     */
    public function show(Client $client, Companion $companion): CompanionResource
    {
        $this->authorize('view', $companion);

        // Ensure companion belongs to the client
        abort_if($companion->client_id !== $client->id, 404);

        return new CompanionResource($this->companionService->getCompanion($companion));
    }

    /**
     * Update a companion.
     */
    public function update(UpdateCompanionRequest $request, Client $client, Companion $companion): CompanionResource
    {
        $this->authorize('update', $companion);

        // Ensure companion belongs to the client
        abort_if($companion->client_id !== $client->id, 404);

        $companion = $this->companionService->updateCompanion($companion, $request->validated());

        return new CompanionResource($companion);
    }

    /**
     * Delete a companion.
     */
    public function destroy(Client $client, Companion $companion): JsonResponse
    {
        $this->authorize('delete', $companion);

        // Ensure companion belongs to the client
        abort_if($companion->client_id !== $client->id, 404);

        $this->companionService->deleteCompanion($companion);

        return response()->json(['message' => 'Companion deleted successfully']);
    }
}
