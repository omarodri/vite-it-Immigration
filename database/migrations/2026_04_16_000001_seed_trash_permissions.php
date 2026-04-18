<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $trashPermissions = [
            ['name' => 'trash.view',         'display_name' => 'View Trash'],
            ['name' => 'trash.restore',      'display_name' => 'Restore from Trash'],
            ['name' => 'trash.force-delete', 'display_name' => 'Permanently Delete from Trash'],
            ['name' => 'trash.purge',        'display_name' => 'Purge Trash'],
        ];

        foreach ($trashPermissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                ['display_name' => $perm['display_name']]
            );
        }

        // Grant trash permissions to roles that should have them
        $rolePermissions = [
            'admin'     => ['trash.view', 'trash.restore', 'trash.force-delete', 'trash.purge'],
            'consultor' => ['trash.view', 'trash.restore'],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($permissions);
            }
        }

        // super-admin gets all permissions
        $superAdmin = Role::where('name', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions(Permission::all());
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', [
            'trash.view',
            'trash.restore',
            'trash.force-delete',
            'trash.purge',
        ])->delete();
    }
};
