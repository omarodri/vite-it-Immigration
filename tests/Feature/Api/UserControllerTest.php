<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $editor;

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
        $editorRole->givePermissionTo(['users.view', 'profile.view', 'profile.update']);

        $userRole = Role::create(['name' => 'user', 'guard_name' => 'web']);
        $userRole->givePermissionTo(['profile.view', 'profile.update']);

        // Create users
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->editor = User::factory()->create();
        $this->editor->assignRole('editor');

        $this->user = User::factory()->create();
        $this->user->assignRole('user');
    }

    public function test_admin_can_list_users(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'roles'],
                ],
            ]);
    }

    public function test_editor_can_list_users(): void
    {
        $response = $this->actingAs($this->editor)
            ->getJson('/api/users');

        $response->assertStatus(200);
    }

    public function test_regular_user_cannot_list_users(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_create_user(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/users', [
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'roles' => ['user'],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'roles'],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
        ]);
    }

    public function test_editor_cannot_create_user(): void
    {
        $response = $this->actingAs($this->editor)
            ->postJson('/api/users', [
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_update_user(): void
    {
        $targetUser = User::factory()->create();

        $response = $this->actingAs($this->admin)
            ->putJson("/api/users/{$targetUser->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'User updated successfully',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_admin_can_delete_user(): void
    {
        $targetUser = User::factory()->create();
        $targetUser->assignRole('user');

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$targetUser->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'User deleted successfully',
            ]);

        $this->assertDatabaseMissing('users', [
            'id' => $targetUser->id,
        ]);
    }

    public function test_admin_cannot_delete_last_admin(): void
    {
        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$this->admin->id}");

        $response->assertStatus(403);
    }

    public function test_user_cannot_delete_themselves(): void
    {
        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$this->admin->id}");

        $response->assertStatus(403);
    }

    public function test_admin_can_view_single_user(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/users/{$this->user->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'name', 'email', 'roles']);
    }

    public function test_users_can_be_filtered_by_search(): void
    {
        User::factory()->create(['name' => 'Searchable User', 'email' => 'searchable@example.com']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/users?search=searchable');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_users_can_be_filtered_by_role(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/users?role=admin');

        $response->assertStatus(200);
        $data = $response->json('data');

        foreach ($data as $user) {
            $this->assertContains('admin', array_column($user['roles'], 'name'));
        }
    }

    public function test_unauthenticated_user_cannot_access_users(): void
    {
        $response = $this->getJson('/api/users');

        $response->assertStatus(401);
    }

    public function test_create_user_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/users', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_create_user_validates_unique_email(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/users', [
                'name' => 'New User',
                'email' => $this->user->email,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
