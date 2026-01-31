<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $editor;

    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([]);

        $permissions = [
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            'profile.view', 'profile.update',
            'activity-logs.view',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }

        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo($permissions);

        $editorRole = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $editorRole->givePermissionTo(['users.view', 'profile.view', 'profile.update']);

        $userRole = Role::create(['name' => 'user', 'guard_name' => 'web']);
        $userRole->givePermissionTo(['profile.view', 'profile.update']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->editor = User::factory()->create();
        $this->editor->assignRole('editor');

        $this->regularUser = User::factory()->create();
        $this->regularUser->assignRole('user');
    }

    // ==================== Model Activity Logging ====================

    public function test_user_creation_generates_activity_log(): void
    {
        $this->actingAs($this->admin);

        $this->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'Created user: New User',
            'causer_id' => $this->admin->id,
            'causer_type' => User::class,
        ]);
    }

    public function test_user_update_generates_activity_log(): void
    {
        $this->actingAs($this->admin);

        $user = User::factory()->create();
        $user->assignRole('user');

        $this->putJson("/api/users/{$user->id}", [
            'name' => 'Updated Name',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'description' => 'Updated user: Updated Name',
            'causer_id' => $this->admin->id,
        ]);
    }

    public function test_user_deletion_generates_activity_log(): void
    {
        $this->actingAs($this->admin);

        $user = User::factory()->create();
        $user->assignRole('user');

        $this->deleteJson("/api/users/{$user->id}");

        $this->assertDatabaseHas('activity_log', [
            'description' => 'Deleted user: '.$user->name,
            'causer_id' => $this->admin->id,
        ]);
    }

    public function test_model_changes_are_logged_automatically(): void
    {
        $user = User::factory()->create(['name' => 'Original Name']);

        $user->update(['name' => 'Changed Name']);

        $activity = Activity::where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('Original Name', $activity->properties['old']['name'] ?? null);
        $this->assertEquals('Changed Name', $activity->properties['attributes']['name'] ?? null);
    }

    // ==================== Auth Activity Logging ====================

    public function test_login_generates_activity_log(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'description' => 'User logged in',
            'causer_id' => $user->id,
        ]);
    }

    public function test_logout_generates_activity_log(): void
    {
        $this->actingAs($this->admin);

        $this->postJson('/api/logout');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'description' => 'User logged out',
            'causer_id' => $this->admin->id,
        ]);
    }

    public function test_registration_generates_activity_log(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Registered User',
            'email' => 'registered@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'registered@example.com')->first();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'description' => 'User registered',
            'causer_id' => $user->id,
        ]);
    }

    // ==================== Activity Log Endpoint ====================

    public function test_admin_can_view_activity_logs(): void
    {
        activity('test')->causedBy($this->admin)->log('Test activity');
        activity('test')->causedBy($this->editor)->log('Another activity');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/activity-logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'log_name', 'description', 'created_at'],
                ],
                'current_page',
                'total',
            ]);
    }

    public function test_non_admin_cannot_view_activity_logs(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/activity-logs');

        $response->assertStatus(403);
    }

    public function test_editor_cannot_view_activity_logs(): void
    {
        $response = $this->actingAs($this->editor)
            ->getJson('/api/activity-logs');

        $response->assertStatus(403);
    }

    public function test_activity_logs_can_be_filtered_by_log_name(): void
    {
        activity('auth')->causedBy($this->admin)->log('Auth event');
        activity('users')->causedBy($this->admin)->log('User event');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/activity-logs?log_name=auth');

        $response->assertStatus(200);

        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertEquals('auth', $item['log_name']);
        }
    }

    public function test_activity_logs_can_be_filtered_by_causer(): void
    {
        activity('test')->causedBy($this->admin)->log('Admin action');
        activity('test')->causedBy($this->editor)->log('Editor action');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/activity-logs?causer_id='.$this->admin->id);

        $response->assertStatus(200);

        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertEquals($this->admin->id, $item['causer_id']);
        }
    }

    public function test_activity_logs_can_be_searched(): void
    {
        activity('test')->causedBy($this->admin)->log('Specific searchable event');
        activity('test')->causedBy($this->admin)->log('Other event');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/activity-logs?search=searchable');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($data));
        $this->assertStringContainsString('searchable', $data[0]['description']);
    }

    public function test_activity_logs_are_paginated(): void
    {
        $baseCount = Activity::count();

        for ($i = 0; $i < 25; $i++) {
            activity('test')->causedBy($this->admin)->log("Event {$i}");
        }

        $response = $this->actingAs($this->admin)
            ->getJson('/api/activity-logs?per_page=10');

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
        $this->assertEquals($baseCount + 25, $response->json('total'));
    }

    public function test_admin_can_view_single_activity_log(): void
    {
        activity('test')
            ->causedBy($this->admin)
            ->withProperties(['key' => 'value'])
            ->log('Detailed event');

        $activity = Activity::where('description', 'Detailed event')->first();

        $response = $this->actingAs($this->admin)
            ->getJson("/api/activity-logs/{$activity->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'description' => 'Detailed event',
                'log_name' => 'test',
            ]);
    }

    public function test_unauthenticated_cannot_view_activity_logs(): void
    {
        $response = $this->getJson('/api/activity-logs');

        $response->assertStatus(401);
    }
}
