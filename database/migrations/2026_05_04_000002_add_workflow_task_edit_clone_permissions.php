<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $perms = ['workflow_tasks.update_core', 'workflow_tasks.clone'];

        foreach ($perms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        foreach (['super-admin', 'admin', 'attorney', 'paralegal'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($perms);
            }
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', ['workflow_tasks.update_core', 'workflow_tasks.clone'])
                  ->delete();
    }
};
