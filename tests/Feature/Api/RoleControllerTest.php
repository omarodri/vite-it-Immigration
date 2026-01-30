<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $permissions = [
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            'profile.view', 'profile.update',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo($permissions);

        $editorRole = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $userRole = Role::create(['name' => 'user', 'guard_name' => 'web']);
        $userRole->givePermissionTo(['profile.view', 'profile.update']);

        // Create users
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->user = User::factory()->create();
        $this->user->assignRole('user');
    }

    public function test_admin_can_list_roles(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'permissions'],
                ],
            ]);
    }

    public function test_regular_user_cannot_list_roles(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/roles');

        $response->assertStatus(403);
    }

    public function test_admin_can_view_single_role(): void
    {
        $role = Role::findByName('admin');

        $response = $this->actingAs($this->admin)
            ->getJson("/api/roles/{$role->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'name', 'permissions']);
    }

    public function test_admin_can_list_permissions(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/permissions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'grouped',
            ]);
    }

    public function test_admin_can_create_role(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/roles', [
                'name' => 'custom-role',
                'permissions' => ['profile.view', 'profile.update'],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'role' => ['id', 'name', 'permissions'],
            ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'custom-role',
        ]);
    }

    public function test_regular_user_cannot_create_role(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/roles', [
                'name' => 'custom-role',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_cannot_update_protected_roles(): void
    {
        $adminRole = Role::findByName('admin');

        $response = $this->actingAs($this->admin)
            ->putJson("/api/roles/{$adminRole->id}", [
                'name' => 'new-admin-name',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot modify protected roles',
            ]);
    }

    public function test_admin_cannot_delete_protected_roles(): void
    {
        $adminRole = Role::findByName('admin');

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/roles/{$adminRole->id}");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Cannot delete protected roles',
            ]);
    }

    public function test_admin_can_update_custom_role(): void
    {
        $customRole = Role::create(['name' => 'custom-role', 'guard_name' => 'web']);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/roles/{$customRole->id}", [
                'name' => 'updated-custom-role',
                'permissions' => ['profile.view'],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Role updated successfully',
            ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'updated-custom-role',
        ]);
    }

    public function test_admin_can_delete_custom_role(): void
    {
        $customRole = Role::create(['name' => 'custom-role', 'guard_name' => 'web']);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/roles/{$customRole->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Role deleted successfully',
            ]);

        $this->assertDatabaseMissing('roles', [
            'name' => 'custom-role',
        ]);
    }

    public function test_unauthenticated_user_cannot_access_roles(): void
    {
        $response = $this->getJson('/api/roles');

        $response->assertStatus(401);
    }

    public function test_create_role_validates_unique_name(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/roles', [
                'name' => 'admin',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_role_validates_permissions_exist(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/roles', [
                'name' => 'new-role',
                'permissions' => ['nonexistent.permission'],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['permissions.0']);
    }
}
