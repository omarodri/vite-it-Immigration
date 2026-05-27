<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        $perm = Permission::firstOrCreate([
            'name'       => 'companions.manage_family',
            'guard_name' => 'web',
        ]);

        foreach (['super-admin', 'admin', 'consultor', 'apoyo'] as $roleName) {
            Role::findByName($roleName)?->givePermissionTo($perm);
        }
    }

    public function down(): void
    {
        Permission::where('name', 'companions.manage_family')->delete();
    }
};
