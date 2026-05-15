<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    /**
     * Spec 60 — Seed IRCC credential permissions.
     *
     * - ircc_credentials.view : read encrypted credentials for a case
     * - ircc_credentials.edit : create / update credentials for a case
     *
     * Granted to: super-admin (already auto-bypassed by Gate::before, but explicit),
     * and consultor. Other roles (apoyo, contador, cliente, user) do NOT receive
     * these permissions by default.
     */
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'ircc_credentials.view',
            'ircc_credentials.edit',
        ];

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name'       => $permissionName,
                'guard_name' => 'web',
            ]);

            foreach (['super-admin', 'consultor'] as $roleName) {
                $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
                if ($role && ! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', [
            'ircc_credentials.view',
            'ircc_credentials.edit',
        ])->where('guard_name', 'web')->delete();
    }
};
