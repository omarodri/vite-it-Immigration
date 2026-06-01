<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = collect([
            'case_types.view',
            'case_types.create',
            'case_types.update',
            'case_types.delete',
            'case_types.clone',
        ])->map(fn ($name) => Permission::firstOrCreate(['name' => $name]));

        // super-admin y admin: acceso total
        foreach (['super-admin', 'admin'] as $roleName) {
            $role = Role::findByName($roleName);
            $role?->givePermissionTo($permissions);
        }

        // consultor: solo lectura
        Role::findByName('consultor')?->givePermissionTo(
            Permission::findByName('case_types.view')
        );

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        collect([
            'case_types.view',
            'case_types.create',
            'case_types.update',
            'case_types.delete',
            'case_types.clone',
        ])->each(fn ($name) => Permission::findByName($name)?->delete());
    }
};
