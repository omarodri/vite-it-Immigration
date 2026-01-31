<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleRepository implements RoleRepositoryInterface
{
    private const PROTECTED_ROLES = ['admin', 'user', 'editor'];

    public function all(): Collection
    {
        return Role::with('permissions')->get();
    }

    public function findById(int $id): ?Role
    {
        return Role::find($id);
    }

    public function create(array $data): Role
    {
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => $data['guard_name'] ?? 'web',
        ]);

        if (!empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        $role->load('permissions');

        return $role;
    }

    public function update(Role $role, array $data): Role
    {
        if (isset($data['name'])) {
            $role->update(['name' => $data['name']]);
        }

        if (array_key_exists('permissions', $data)) {
            $role->syncPermissions($data['permissions']);
        }

        $role->load('permissions');

        return $role;
    }

    public function delete(Role $role): bool
    {
        return $role->delete();
    }

    public function allPermissions(): Collection
    {
        return Permission::all();
    }

    public function permissionsGrouped(): Collection
    {
        return Permission::all()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        });
    }

    public function isProtected(Role $role): bool
    {
        return in_array($role->name, self::PROTECTED_ROLES);
    }
}
