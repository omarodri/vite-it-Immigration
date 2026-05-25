<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spec 64 — Clientes Empresa.
 *
 * Per Gotcha G4, permissions MUST live in a migration so they survive
 * `migrate:fresh` in any environment, not only in a seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Flush the in-memory permission cache so freshly created entries are visible.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $newPermissions = [
            'clients.view_company',
            'clients.create_company',
            'clients.edit_company',
        ];

        foreach ($newPermissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Full access — super-admin, admin, consultor.
        foreach (['super-admin', 'admin', 'consultor'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($newPermissions);
            }
        }

        // Read-only access for support staff.
        $apoyo = Role::where('name', 'apoyo')->where('guard_name', 'web')->first();
        if ($apoyo) {
            $apoyo->givePermissionTo('clients.view_company');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', [
            'clients.view_company',
            'clients.create_company',
            'clients.edit_company',
        ])->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
