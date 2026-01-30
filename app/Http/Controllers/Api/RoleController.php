<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of roles.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('roles.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $roles = Role::with('permissions')->get();

        return response()->json([
            'data' => $roles,
        ]);
    }

    /**
     * Display the specified role.
     */
    public function show(Request $request, Role $role): JsonResponse
    {
        if (!$request->user()->can('roles.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $role->load('permissions');

        return response()->json($role);
    }

    /**
     * Display a listing of permissions.
     */
    public function permissions(Request $request): JsonResponse
    {
        if (!$request->user()->can('roles.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $permissions = Permission::all();

        // Group permissions by resource
        $grouped = $permissions->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        });

        return response()->json([
            'data' => $permissions,
            'grouped' => $grouped,
        ]);
    }

    /**
     * Store a newly created role.
     */
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

        $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        $role->load('permissions');

        return response()->json([
            'message' => 'Role created successfully',
            'role' => $role,
        ], 201);
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        if (!$request->user()->can('roles.update')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Prevent updating protected roles
        if (in_array($role->name, ['admin', 'user', 'editor'])) {
            return response()->json([
                'message' => 'Cannot modify protected roles',
            ], 422);
        }

        $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:roles,name,' . $role->id],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        if ($request->has('name')) {
            $role->update(['name' => $request->name]);
        }

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        $role->load('permissions');

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => $role,
        ]);
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        if (!$request->user()->can('roles.delete')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Prevent deleting protected roles
        if (in_array($role->name, ['admin', 'user', 'editor'])) {
            return response()->json([
                'message' => 'Cannot delete protected roles',
            ], 422);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }
}
