<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository
    ) {}

    #[OA\Get(
        path: '/api/roles',
        summary: 'List all roles',
        tags: ['Roles'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of roles'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('roles.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(['data' => $this->roleRepository->all()]);
    }

    #[OA\Get(
        path: '/api/roles/{role}',
        summary: 'Get role details',
        tags: ['Roles'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Role details with permissions'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Request $request, Role $role): JsonResponse
    {
        if (! $request->user()->can('roles.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $role->load('permissions');

        return response()->json($role);
    }

    #[OA\Get(
        path: '/api/permissions',
        summary: 'List all permissions',
        tags: ['Roles'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of all permissions and grouped permissions'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
        ]
    )]
    public function permissions(Request $request): JsonResponse
    {
        if (! $request->user()->can('roles.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $this->roleRepository->allPermissions(),
            'grouped' => $this->roleRepository->permissionsGrouped(),
        ]);
    }

    #[OA\Post(
        path: '/api/roles',
        summary: 'Create a new role',
        tags: ['Roles'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'manager'),
                    new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string'), example: ['users.view', 'users.create']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Role created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->can('roles.create')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $role = $this->roleRepository->create([
            'name' => $request->name,
            'permissions' => $request->permissions ?? [],
        ]);

        return response()->json([
            'message' => 'Role created successfully',
            'role' => $role,
        ], 201);
    }

    #[OA\Put(
        path: '/api/roles/{role}',
        summary: 'Update a role',
        tags: ['Roles'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Role updated'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Cannot modify protected roles or validation error'),
        ]
    )]
    public function update(Request $request, Role $role): JsonResponse
    {
        if (! $request->user()->can('roles.update')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($this->roleRepository->isProtected($role)) {
            return response()->json(['message' => 'Cannot modify protected roles'], 422);
        }

        $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:roles,name,'.$role->id],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $role = $this->roleRepository->update($role, [
            'name' => $request->name,
            'permissions' => $request->permissions,
        ]);

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => $role,
        ]);
    }

    #[OA\Delete(
        path: '/api/roles/{role}',
        summary: 'Delete a role',
        tags: ['Roles'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'role', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Role deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Cannot delete protected roles'),
        ]
    )]
    public function destroy(Request $request, Role $role): JsonResponse
    {
        if (! $request->user()->can('roles.delete')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($this->roleRepository->isProtected($role)) {
            return response()->json(['message' => 'Cannot delete protected roles'], 422);
        }

        $this->roleRepository->delete($role);

        return response()->json(['message' => 'Role deleted successfully']);
    }
}
