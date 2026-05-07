<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $todoCorePermissions = [
            ['name' => 'todo_core.view',     'display_name' => 'View Core Todos'],
            ['name' => 'todo_core.create',   'display_name' => 'Create Core Todos'],
            ['name' => 'todo_core.complete', 'display_name' => 'Complete Core Todos'],
            ['name' => 'todo_core.delete',   'display_name' => 'Delete Core Todos'],
        ];

        foreach ($todoCorePermissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                ['display_name' => $perm['display_name']]
            );
        }

        $rolePermissions = [
            'admin'     => ['todo_core.view', 'todo_core.create', 'todo_core.complete', 'todo_core.delete'],
            'attorney'  => ['todo_core.view', 'todo_core.create', 'todo_core.complete', 'todo_core.delete'],
            'paralegal' => ['todo_core.view', 'todo_core.create', 'todo_core.complete', 'todo_core.delete'],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($permissions);
            }
        }

        // super-admin always has all
        $superAdmin = Role::where('name', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions(Permission::all());
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', [
            'todo_core.view',
            'todo_core.create',
            'todo_core.complete',
            'todo_core.delete',
        ])->delete();
    }
};
