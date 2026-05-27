<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\App;

return new class extends Migration
{
    /**
     * Spec 66 — Granular RBAC for workflow task templates and case-code settings.
     *
     * Splits the legacy `settings.case_code.manage` into `case_code.view` /
     * `case_code.update`, and introduces fine-grained `workflow_tasks.*`
     * permissions so the existing `workflows.*` block isn't reused for task
     * template CRUD.
     *
     * G4: permissions are defined in a migration (not just a seeder) so they
     * survive `migrate:fresh` in staging / production.
     */
    public function up(): void
    {
        App::make(PermissionRegistrar::class)->forgetCachedPermissions();

        $newPerms = [
            'workflow_tasks.view',
            'workflow_tasks.create',
            'workflow_tasks.clone',
            'workflow_tasks.update',
            'workflow_tasks.delete',
            'case_code.view',
            'case_code.update',
        ];

        foreach ($newPerms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $matrix = [
            'super-admin' => $newPerms,
            'admin'       => $newPerms,
            'consultor'   => [
                'workflow_tasks.view',
                'workflow_tasks.create',
                'workflow_tasks.clone',
                'workflow_tasks.update',
            ],
            'apoyo'       => ['workflow_tasks.view'],
        ];

        foreach ($matrix as $roleName => $perms) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($perms);
            }
        }

        // Transitional migration: propagate the new case_code.* permissions
        // to whichever roles previously held the legacy `settings.case_code.manage`.
        // Guard: Role::permission() throws PermissionDoesNotExist if the permission
        // never existed in this environment (e.g. prod that never ran the old seeder).
        $legacyExists = Permission::where('name', 'settings.case_code.manage')
            ->where('guard_name', 'web')
            ->exists();

        if ($legacyExists) {
            $legacyRoles = Role::permission('settings.case_code.manage')->get();
            foreach ($legacyRoles as $role) {
                $role->givePermissionTo(['case_code.view', 'case_code.update']);
            }
        }

        App::make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        App::make(PermissionRegistrar::class)->forgetCachedPermissions();

        $perms = [
            'workflow_tasks.view',
            'workflow_tasks.create',
            'workflow_tasks.clone',
            'workflow_tasks.update',
            'workflow_tasks.delete',
            'case_code.view',
            'case_code.update',
        ];

        Permission::whereIn('name', $perms)->where('guard_name', 'web')->delete();

        App::make(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
