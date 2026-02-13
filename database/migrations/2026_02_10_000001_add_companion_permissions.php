<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Define companion permissions
        $permissions = [
            'companions.view',
            'companions.create',
            'companions.update',
            'companions.delete',
        ];

        // Create permissions if they don't exist
        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Assign permissions to existing roles to maintain functionality
        $this->assignPermissionsToRoles();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = [
            'companions.view',
            'companions.create',
            'companions.update',
            'companions.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::where('name', $permission)->delete();
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Assign companion permissions to existing roles.
     */
    private function assignPermissionsToRoles(): void
    {
        // Admin: all permissions
        $admin = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo([
                'companions.view',
                'companions.create',
                'companions.update',
                'companions.delete',
            ]);
        }

        // Consultor: view, create, update (no delete)
        $consultor = Role::where('name', 'consultor')->where('guard_name', 'web')->first();
        if ($consultor) {
            $consultor->givePermissionTo([
                'companions.view',
                'companions.create',
                'companions.update',
            ]);
        }

        // Apoyo: view, create, update (no delete)
        $apoyo = Role::where('name', 'apoyo')->where('guard_name', 'web')->first();
        if ($apoyo) {
            $apoyo->givePermissionTo([
                'companions.view',
                'companions.create',
                'companions.update',
            ]);
        }

        // Contador: view only
        $contador = Role::where('name', 'contador')->where('guard_name', 'web')->first();
        if ($contador) {
            $contador->givePermissionTo([
                'companions.view',
            ]);
        }
    }
};
