<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Models\Client;
use App\Services\Client\ClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ClientController extends Controller
{
    public function __construct(
        private ClientService $clientService
    ) {}

    #[OA\Get(
        path: '/api/clients',
        summary: 'List clients (paginated)',
        tags: ['Clients'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['prospect', 'active', 'inactive', 'archived'])),
            new OA\Parameter(name: 'nationality', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'canada_status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'created_at')),
            new OA\Parameter(name: 'sort_direction', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'desc')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated client list'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        $clients = $this->clientService->listClients(
            $request->only([
                'search', 'status', 'nationality', 'canada_status',
                'date_from', 'date_to', 'is_primary_applicant',
                'sort_by', 'sort_direction',
            ]),
            (int) $request->get('per_page', 15)
        );

        return response()->json($clients);
    }

    #[OA\Post(
        path: '/api/clients',
        summary: 'Create a new client',
        tags: ['Clients'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['first_name', 'last_name'],
                properties: [
                    new OA\Property(property: 'first_name', type: 'string', example: 'John'),
                    new OA\Property(property: 'last_name', type: 'string', example: 'Doe'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'phone', type: 'string'),
                    new OA\Property(property: 'status', type: 'string', enum: ['prospect', 'active', 'inactive', 'archived']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Client created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreClientRequest $request): JsonResponse
    {
        try {
            $client = $this->clientService->createClient($request->validated());

            return response()->json([
                'message' => 'Client created successfully',
                'client' => $client,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    #[OA\Get(
        path: '/api/clients/{client}',
        summary: 'Get client details',
        tags: ['Clients'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'client', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Client details'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Client $client): JsonResponse
    {
        $this->authorize('view', $client);

        return response()->json($this->clientService->getClient($client));
    }

    #[OA\Put(
        path: '/api/clients/{client}',
        summary: 'Update client',
        tags: ['Clients'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'client', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'first_name', type: 'string'),
                    new OA\Property(property: 'last_name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'phone', type: 'string'),
                    new OA\Property(property: 'status', type: 'string', enum: ['prospect', 'active', 'inactive', 'archived']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Client updated'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        try {
            $client = $this->clientService->updateClient($client, $request->validated());

            return response()->json([
                'message' => 'Client updated successfully',
                'client' => $client,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    #[OA\Delete(
        path: '/api/clients/{client}',
        summary: 'Delete client',
        tags: ['Clients'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'client', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Client deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function destroy(Client $client): JsonResponse
    {
        $this->authorize('delete', $client);

        $this->clientService->deleteClient($client);

        return response()->json(['message' => 'Client deleted successfully']);
    }

    #[OA\Delete(
        path: '/api/clients/bulk',
        summary: 'Bulk delete clients',
        tags: ['Clients'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ids'],
                properties: [
                    new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Clients deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function bulkDestroy(Request $request): JsonResponse
    {
        $this->authorize('delete', Client::class);

        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:clients,id'],
        ]);

        $count = $this->clientService->bulkDeleteClients($request->ids);

        return response()->json([
            'message' => $count.' clients deleted successfully',
        ]);
    }

    #[OA\Post(
        path: '/api/clients/{client}/convert',
        summary: 'Convert prospect to active client',
        tags: ['Clients'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'client', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Prospect converted to active client'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Client is not a prospect'),
        ]
    )]
    public function convert(Client $client): JsonResponse
    {
        $this->authorize('convert', $client);

        try {
            $client = $this->clientService->convertProspectToActive($client);

            return response()->json([
                'message' => 'Prospect converted to active client successfully',
                'client' => $client,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    #[OA\Get(
        path: '/api/clients/statistics',
        summary: 'Get client statistics',
        tags: ['Clients'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Client statistics'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
        ]
    )]
    public function statistics(): JsonResponse
    {
        $this->authorize('viewAny', Client::class);

        return response()->json($this->clientService->getStatistics());
    }
}
