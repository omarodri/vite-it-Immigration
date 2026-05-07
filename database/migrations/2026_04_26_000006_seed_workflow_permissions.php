<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $perms = ['workflows.view', 'workflows.create', 'workflows.update', 'workflows.delete'];

        foreach ($perms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        foreach (['admin', 'super-admin'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($perms);
            }
        }

        foreach (['attorney', 'paralegal'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo('workflows.view');
            }
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', ['workflows.view', 'workflows.create', 'workflows.update', 'workflows.delete'])
                  ->delete();
    }
};
