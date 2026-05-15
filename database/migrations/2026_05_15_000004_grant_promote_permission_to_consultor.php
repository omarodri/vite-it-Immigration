<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::where('name', 'clients.promote_from_companion')
            ->where('guard_name', 'web')
            ->first();

        if (! $permission) {
            return;
        }

        $role = Role::where('name', 'consultor')->where('guard_name', 'web')->first();
        if ($role && ! $role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::where('name', 'clients.promote_from_companion')
            ->where('guard_name', 'web')
            ->first();

        if (! $permission) {
            return;
        }

        $role = Role::where('name', 'consultor')->where('guard_name', 'web')->first();
        if ($role) {
            $role->revokePermissionTo($permission);
        }
    }
};
