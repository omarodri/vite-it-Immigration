<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $perms = ['workflows.view', 'workflows.create', 'workflows.update', 'workflows.delete'];

        $consultor = Role::where('name', 'consultor')->first();
        if ($consultor) {
            $consultor->givePermissionTo($perms);
        }
    }

    public function down(): void
    {
        $perms = ['workflows.view', 'workflows.create', 'workflows.update', 'workflows.delete'];

        $consultor = Role::where('name', 'consultor')->first();
        if ($consultor) {
            $consultor->revokePermissionTo($perms);
        }
    }
};
