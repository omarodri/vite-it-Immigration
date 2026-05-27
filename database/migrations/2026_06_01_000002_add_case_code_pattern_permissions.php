<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\App;

return new class extends Migration
{
    /**
     * Spec 65 — Permission to manage the case-code pattern (D10).
     *
     * G4: permissions belong in a migration, never only in a seeder, so they
     * survive `migrate:fresh` in staging / production.
     */
    public function up(): void
    {
        // Reset Spatie's cache so the new permission is discoverable immediately
        App::make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(
            ['name' => 'settings.case_code.manage', 'guard_name' => 'web']
        );

        foreach (['super-admin', 'admin'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role && !$role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        App::make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        App::make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::where('name', 'settings.case_code.manage')
            ->where('guard_name', 'web')
            ->delete();
    }
};
