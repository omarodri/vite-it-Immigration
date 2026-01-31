<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('roles.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(['data' => $this->roleRepository->all()]);
    }

    public function show(Request $request, Role $role): JsonResponse
    {
        if (!$request->user()->can('roles.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $role->load('permissions');

        return response()->json($role);
    }

    public function permissions(Request $request): JsonResponse
    {
        if (!$request->user()->can('roles.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $this->roleRepository->allPermissions(),
            'grouped' => $this->roleRepository->permissionsGrouped(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->can('roles.create')) {
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

    public function update(Request $request, Role $role): JsonResponse
    {
        if (!$request->user()->can('roles.update')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($this->roleRepository->isProtected($role)) {
            return response()->json(['message' => 'Cannot modify protected roles'], 422);
        }

        $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:roles,name,' . $role->id],
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

    public function destroy(Request $request, Role $role): JsonResponse
    {
        if (!$request->user()->can('roles.delete')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($this->roleRepository->isProtected($role)) {
            return response()->json(['message' => 'Cannot delete protected roles'], 422);
        }

        $this->roleRepository->delete($role);

        return response()->json(['message' => 'Role deleted successfully']);
    }
}
