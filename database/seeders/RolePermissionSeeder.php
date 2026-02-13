<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Users
            ['name' => 'users.view', 'display_name' => 'View Users'],
            ['name' => 'users.create', 'display_name' => 'Create Users'],
            ['name' => 'users.update', 'display_name' => 'Update Users'],
            ['name' => 'users.delete', 'display_name' => 'Delete Users'],

            // Roles
            ['name' => 'roles.view', 'display_name' => 'View Roles'],
            ['name' => 'roles.create', 'display_name' => 'Create Roles'],
            ['name' => 'roles.update', 'display_name' => 'Update Roles'],
            ['name' => 'roles.delete', 'display_name' => 'Delete Roles'],

            // Profile
            ['name' => 'profile.view', 'display_name' => 'View Profile'],
            ['name' => 'profile.update', 'display_name' => 'Update Profile'],

            // Settings
            ['name' => 'settings.view', 'display_name' => 'View Settings'],
            ['name' => 'settings.update', 'display_name' => 'Update Settings'],

            // Activity Logs
            ['name' => 'activity-logs.view', 'display_name' => 'View Activity Logs'],

            // Tenants (super-admin only)
            ['name' => 'tenants.view', 'display_name' => 'View Tenants'],
            ['name' => 'tenants.create', 'display_name' => 'Create Tenants'],
            ['name' => 'tenants.update', 'display_name' => 'Update Tenants'],
            ['name' => 'tenants.delete', 'display_name' => 'Delete Tenants'],

            // Clients
            ['name' => 'clients.view', 'display_name' => 'View Clients'],
            ['name' => 'clients.create', 'display_name' => 'Create Clients'],
            ['name' => 'clients.update', 'display_name' => 'Update Clients'],
            ['name' => 'clients.delete', 'display_name' => 'Delete Clients'],

            // Companions
            ['name' => 'companions.view', 'display_name' => 'View Companions'],
            ['name' => 'companions.create', 'display_name' => 'Create Companions'],
            ['name' => 'companions.update', 'display_name' => 'Update Companions'],
            ['name' => 'companions.delete', 'display_name' => 'Delete Companions'],

            // Cases
            ['name' => 'cases.view', 'display_name' => 'View Cases'],
            ['name' => 'cases.create', 'display_name' => 'Create Cases'],
            ['name' => 'cases.update', 'display_name' => 'Update Cases'],
            ['name' => 'cases.delete', 'display_name' => 'Delete Cases'],
            ['name' => 'cases.assign', 'display_name' => 'Assign Cases'],

            // Tasks
            ['name' => 'tasks.view', 'display_name' => 'View Tasks'],
            ['name' => 'tasks.create', 'display_name' => 'Create Tasks'],
            ['name' => 'tasks.update', 'display_name' => 'Update Tasks'],
            ['name' => 'tasks.delete', 'display_name' => 'Delete Tasks'],

            // Follow-ups
            ['name' => 'follow-ups.view', 'display_name' => 'View Follow-ups'],
            ['name' => 'follow-ups.create', 'display_name' => 'Create Follow-ups'],
            ['name' => 'follow-ups.update', 'display_name' => 'Update Follow-ups'],
            ['name' => 'follow-ups.delete', 'display_name' => 'Delete Follow-ups'],

            // Documents
            ['name' => 'documents.view', 'display_name' => 'View Documents'],
            ['name' => 'documents.create', 'display_name' => 'Create Documents'],
            ['name' => 'documents.update', 'display_name' => 'Update Documents'],
            ['name' => 'documents.delete', 'display_name' => 'Delete Documents'],

            // Events
            ['name' => 'events.view', 'display_name' => 'View Events'],
            ['name' => 'events.create', 'display_name' => 'Create Events'],
            ['name' => 'events.update', 'display_name' => 'Update Events'],
            ['name' => 'events.delete', 'display_name' => 'Delete Events'],

            // Reports
            ['name' => 'reports.view', 'display_name' => 'View Reports'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                ['name' => $permission['name'], 'guard_name' => 'web']
            );
        }

        // Create roles and assign permissions
        $roles = [
            'super-admin' => [
                'display_name' => 'Super Administrator',
                'permissions' => '*', // All permissions
            ],
            'admin' => [
                'display_name' => 'Tenant Administrator',
                'permissions' => [
                    'users.view', 'users.create', 'users.update', 'users.delete',
                    'roles.view', 'roles.create', 'roles.update', 'roles.delete',
                    'profile.view', 'profile.update',
                    'settings.view', 'settings.update',
                    'activity-logs.view',
                    'clients.view', 'clients.create', 'clients.update', 'clients.delete',
                    'companions.view', 'companions.create', 'companions.update', 'companions.delete',
                    'cases.view', 'cases.create', 'cases.update', 'cases.delete', 'cases.assign',
                    'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete',
                    'follow-ups.view', 'follow-ups.create', 'follow-ups.update', 'follow-ups.delete',
                    'documents.view', 'documents.create', 'documents.update', 'documents.delete',
                    'events.view', 'events.create', 'events.update', 'events.delete',
                    'reports.view',
                ],
            ],
            'consultor' => [
                'display_name' => 'Consultor (RCIC)',
                'permissions' => [
                    'profile.view', 'profile.update',
                    'clients.view', 'clients.create', 'clients.update',
                    'companions.view', 'companions.create', 'companions.update',
                    'cases.view', 'cases.create', 'cases.update', 'cases.assign',
                    'tasks.view', 'tasks.create', 'tasks.update',
                    'follow-ups.view', 'follow-ups.create', 'follow-ups.update',
                    'documents.view', 'documents.create', 'documents.update',
                    'events.view', 'events.create', 'events.update',
                    'reports.view',
                ],
            ],
            'apoyo' => [
                'display_name' => 'Case Support Staff',
                'permissions' => [
                    'profile.view', 'profile.update',
                    'clients.view', 'clients.create', 'clients.update',
                    'companions.view', 'companions.create', 'companions.update',
                    'cases.view', 'cases.update',
                    'tasks.view', 'tasks.create', 'tasks.update',
                    'follow-ups.view', 'follow-ups.create', 'follow-ups.update',
                    'documents.view', 'documents.create', 'documents.update',
                    'events.view', 'events.create', 'events.update',
                ],
            ],
            'contador' => [
                'display_name' => 'Accountant',
                'permissions' => [
                    'profile.view', 'profile.update',
                    'clients.view',
                    'companions.view',
                    'cases.view',
                    'reports.view',
                ],
            ],
            'cliente' => [
                'display_name' => 'Client Portal User',
                'permissions' => [
                    'profile.view', 'profile.update',
                ],
            ],
        ];

        foreach ($roles as $roleName => $roleData) {
            $role = Role::firstOrCreate(
                ['name' => $roleName],
                ['name' => $roleName, 'guard_name' => 'web']
            );

            // Super-admin gets all permissions
            if ($roleData['permissions'] === '*') {
                $role->syncPermissions(Permission::all());
            } else {
                $role->syncPermissions($roleData['permissions']);
            }
        }
    }
}
