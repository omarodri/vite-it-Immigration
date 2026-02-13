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
        // Define all permissions that should exist
        $allPermissions = [
            // Tenants (super-admin only)
            'tenants.view',
            'tenants.create',
            'tenants.update',
            'tenants.delete',

            // Clients
            'clients.view',
            'clients.create',
            'clients.update',
            'clients.delete',

            // Cases
            'cases.view',
            'cases.create',
            'cases.update',
            'cases.delete',

            // Tasks
            'tasks.view',
            'tasks.create',
            'tasks.update',
            'tasks.delete',

            // Follow-ups
            'follow-ups.view',
            'follow-ups.create',
            'follow-ups.update',
            'follow-ups.delete',

            // Documents
            'documents.view',
            'documents.create',
            'documents.update',
            'documents.delete',

            // Events
            'events.view',
            'events.create',
            'events.update',
            'events.delete',

            // Reports
            'reports.view',
        ];

        // Create permissions if they don't exist
        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Assign permissions to existing roles
        $this->assignPermissionsToRoles();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = [
            'tenants.view', 'tenants.create', 'tenants.update', 'tenants.delete',
            'clients.view', 'clients.create', 'clients.update', 'clients.delete',
            'cases.view', 'cases.create', 'cases.update', 'cases.delete',
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete',
            'follow-ups.view', 'follow-ups.create', 'follow-ups.update', 'follow-ups.delete',
            'documents.view', 'documents.create', 'documents.update', 'documents.delete',
            'events.view', 'events.create', 'events.update', 'events.delete',
            'reports.view',
        ];

        foreach ($permissions as $permission) {
            Permission::where('name', $permission)->delete();
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Assign permissions to existing roles based on RolePermissionSeeder definitions.
     */
    private function assignPermissionsToRoles(): void
    {
        // Admin: full access to tenant-level resources
        $admin = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo([
                'clients.view', 'clients.create', 'clients.update', 'clients.delete',
                'cases.view', 'cases.create', 'cases.update', 'cases.delete',
                'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete',
                'follow-ups.view', 'follow-ups.create', 'follow-ups.update', 'follow-ups.delete',
                'documents.view', 'documents.create', 'documents.update', 'documents.delete',
                'events.view', 'events.create', 'events.update', 'events.delete',
                'reports.view',
            ]);
        }

        // Super-admin: gets tenant permissions too
        $superAdmin = Role::where('name', 'super-admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo([
                'tenants.view', 'tenants.create', 'tenants.update', 'tenants.delete',
                'clients.view', 'clients.create', 'clients.update', 'clients.delete',
                'cases.view', 'cases.create', 'cases.update', 'cases.delete',
                'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete',
                'follow-ups.view', 'follow-ups.create', 'follow-ups.update', 'follow-ups.delete',
                'documents.view', 'documents.create', 'documents.update', 'documents.delete',
                'events.view', 'events.create', 'events.update', 'events.delete',
                'reports.view',
            ]);
        }

        // Consultor: clients CRU, cases CRU, tasks CRU, follow-ups CRU, documents CRU, events CRU, reports R
        $consultor = Role::where('name', 'consultor')->where('guard_name', 'web')->first();
        if ($consultor) {
            $consultor->givePermissionTo([
                'clients.view', 'clients.create', 'clients.update',
                'cases.view', 'cases.create', 'cases.update',
                'tasks.view', 'tasks.create', 'tasks.update',
                'follow-ups.view', 'follow-ups.create', 'follow-ups.update',
                'documents.view', 'documents.create', 'documents.update',
                'events.view', 'events.create', 'events.update',
                'reports.view',
            ]);
        }

        // Apoyo: clients CRU, cases RU, tasks CRU, follow-ups CRU, documents CRU, events CRU
        $apoyo = Role::where('name', 'apoyo')->where('guard_name', 'web')->first();
        if ($apoyo) {
            $apoyo->givePermissionTo([
                'clients.view', 'clients.create', 'clients.update',
                'cases.view', 'cases.update',
                'tasks.view', 'tasks.create', 'tasks.update',
                'follow-ups.view', 'follow-ups.create', 'follow-ups.update',
                'documents.view', 'documents.create', 'documents.update',
                'events.view', 'events.create', 'events.update',
            ]);
        }

        // Contador: clients R, cases R, reports R
        $contador = Role::where('name', 'contador')->where('guard_name', 'web')->first();
        if ($contador) {
            $contador->givePermissionTo([
                'clients.view',
                'cases.view',
                'reports.view',
            ]);
        }
    }
};
