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
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                ['name' => $permission['name'], 'guard_name' => 'web']
            );
        }

        // Create roles and assign permissions
        $roles = [
            'admin' => [
                'display_name' => 'Administrator',
                'permissions' => [
                    'users.view', 'users.create', 'users.update', 'users.delete',
                    'roles.view', 'roles.create', 'roles.update', 'roles.delete',
                    'profile.view', 'profile.update',
                    'settings.view', 'settings.update',
                    'activity-logs.view',
                ],
            ],
            'editor' => [
                'display_name' => 'Editor',
                'permissions' => [
                    'users.view',
                    'profile.view', 'profile.update',
                ],
            ],
            'user' => [
                'display_name' => 'User',
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

            $role->syncPermissions($roleData['permissions']);
        }
    }
}
