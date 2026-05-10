<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'time_logs.view',
            'time_logs.view_others',
            'time_logs.create',
            'time_logs.edit',
            'time_logs.edit_others',
            'time_logs.delete',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $rolePermissions = [
            'admin' => $permissions,
            'attorney' => [
                'time_logs.view',
                'time_logs.view_others',
                'time_logs.create',
                'time_logs.edit',
                'time_logs.delete',
            ],
            'paralegal' => [
                'time_logs.view',
                'time_logs.create',
                'time_logs.edit',
                'time_logs.delete',
            ],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($perms);
            }
        }

        // super-admin always gets all permissions
        $superAdmin = Role::where('name', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions(Permission::all());
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', [
            'time_logs.view',
            'time_logs.view_others',
            'time_logs.create',
            'time_logs.edit',
            'time_logs.edit_others',
            'time_logs.delete',
        ])->delete();
    }
};
