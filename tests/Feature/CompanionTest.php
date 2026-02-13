<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Companion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $adminUser;

    protected User $regularUser;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders for roles/permissions
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        // Create tenant and users
        $this->tenant = Tenant::factory()->create();
        $this->adminUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->adminUser->assignRole('admin');

        $this->regularUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->regularUser->assignRole('cliente'); // Only has profile permissions, no clients access

        // Create client for companion tests
        $this->client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    // ==================== List Companions ====================

    public function test_admin_can_list_client_companions(): void
    {
        Companion::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/clients/{$this->client->id}/companions");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_unauthorized_user_cannot_list_companions(): void
    {
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson("/api/clients/{$this->client->id}/companions");

        $response->assertStatus(403);
    }

    public function test_companions_list_only_shows_client_companions(): void
    {
        Companion::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
        ]);

        $otherClient = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        Companion::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $otherClient->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/clients/{$this->client->id}/companions");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    // ==================== Create Companion ====================

    public function test_admin_can_create_companion(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/clients/{$this->client->id}/companions", [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'relationship' => 'spouse',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.first_name', 'John')
            ->assertJsonPath('data.last_name', 'Doe')
            ->assertJsonPath('data.relationship', 'spouse');

        $this->assertDatabaseHas('companions', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'client_id' => $this->client->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_create_companion_requires_first_name(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/clients/{$this->client->id}/companions", [
                'last_name' => 'Doe',
                'relationship' => 'spouse',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('first_name');
    }

    public function test_create_companion_requires_last_name(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/clients/{$this->client->id}/companions", [
                'first_name' => 'John',
                'relationship' => 'spouse',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('last_name');
    }

    public function test_create_companion_requires_relationship(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/clients/{$this->client->id}/companions", [
                'first_name' => 'John',
                'last_name' => 'Doe',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('relationship');
    }

    public function test_create_companion_requires_relationship_other_when_other(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/clients/{$this->client->id}/companions", [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'relationship' => 'other',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('relationship_other');
    }

    public function test_create_companion_with_other_relationship(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/clients/{$this->client->id}/companions", [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'relationship' => 'other',
                'relationship_other' => 'Nephew',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.relationship', 'other')
            ->assertJsonPath('data.relationship_other', 'Nephew');
    }

    public function test_create_companion_validates_relationship_type(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/clients/{$this->client->id}/companions", [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'relationship' => 'invalid_relationship',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('relationship');
    }

    public function test_create_companion_validates_date_of_birth(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/clients/{$this->client->id}/companions", [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'relationship' => 'child',
                'date_of_birth' => now()->addDay()->format('Y-m-d'),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('date_of_birth');
    }

    public function test_unauthorized_user_cannot_create_companion(): void
    {
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->postJson("/api/clients/{$this->client->id}/companions", [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'relationship' => 'spouse',
            ]);

        $response->assertStatus(403);
    }

    // ==================== Show Companion ====================

    public function test_admin_can_view_companion(): void
    {
        $companion = Companion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/clients/{$this->client->id}/companions/{$companion->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.first_name', $companion->first_name)
            ->assertJsonPath('data.last_name', $companion->last_name);
    }

    public function test_cannot_view_companion_from_another_client(): void
    {
        $otherClient = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $companion = Companion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $otherClient->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/clients/{$this->client->id}/companions/{$companion->id}");

        $response->assertStatus(404);
    }

    public function test_cannot_view_companion_from_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);
        $companion = Companion::factory()->create([
            'tenant_id' => $otherTenant->id,
            'client_id' => $otherClient->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/clients/{$otherClient->id}/companions/{$companion->id}");

        $response->assertStatus(404);
    }

    // ==================== Update Companion ====================

    public function test_admin_can_update_companion(): void
    {
        $companion = Companion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'first_name' => 'John',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/clients/{$this->client->id}/companions/{$companion->id}", [
                'first_name' => 'Jane',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.first_name', 'Jane');

        $this->assertDatabaseHas('companions', [
            'id' => $companion->id,
            'first_name' => 'Jane',
        ]);
    }

    public function test_can_update_companion_relationship(): void
    {
        $companion = Companion::factory()->spouse()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/clients/{$this->client->id}/companions/{$companion->id}", [
                'relationship' => 'sibling',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.relationship', 'sibling');
    }

    public function test_unauthorized_user_cannot_update_companion(): void
    {
        $companion = Companion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->putJson("/api/clients/{$this->client->id}/companions/{$companion->id}", [
                'first_name' => 'Jane',
            ]);

        $response->assertStatus(403);
    }

    // ==================== Delete Companion ====================

    public function test_admin_can_delete_companion(): void
    {
        $companion = Companion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/clients/{$this->client->id}/companions/{$companion->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Companion deleted successfully');

        $this->assertSoftDeleted('companions', ['id' => $companion->id]);
    }

    public function test_unauthorized_user_cannot_delete_companion(): void
    {
        $companion = Companion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->deleteJson("/api/clients/{$this->client->id}/companions/{$companion->id}");

        $response->assertStatus(403);
    }

    // ==================== Tenant Isolation ====================

    public function test_companion_auto_assigned_to_user_tenant(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/clients/{$this->client->id}/companions", [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'relationship' => 'child',
            ]);

        $response->assertStatus(201);

        $companionId = $response->json('data.id');
        $this->assertDatabaseHas('companions', [
            'id' => $companionId,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    // ==================== Activity Logging ====================

    public function test_creating_companion_logs_activity(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/clients/{$this->client->id}/companions", [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'relationship' => 'spouse',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'companions',
            'causer_id' => $this->adminUser->id,
        ]);
    }

    // ==================== Relationship Types ====================

    public function test_can_create_spouse_companion(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/clients/{$this->client->id}/companions", [
                'first_name' => 'Mary',
                'last_name' => 'Doe',
                'relationship' => 'spouse',
                'date_of_birth' => '1985-05-15',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.relationship', 'spouse');
    }

    public function test_can_create_child_companion(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/clients/{$this->client->id}/companions", [
                'first_name' => 'Jimmy',
                'last_name' => 'Doe',
                'relationship' => 'child',
                'date_of_birth' => '2015-03-20',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.relationship', 'child');
    }

    public function test_can_create_parent_companion(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/clients/{$this->client->id}/companions", [
                'first_name' => 'Robert',
                'last_name' => 'Doe',
                'relationship' => 'parent',
                'date_of_birth' => '1955-08-10',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.relationship', 'parent');
    }

    public function test_can_create_sibling_companion(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/clients/{$this->client->id}/companions", [
                'first_name' => 'Sarah',
                'last_name' => 'Doe',
                'relationship' => 'sibling',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.relationship', 'sibling');
    }

    // ==================== Passport Information ====================

    public function test_can_create_companion_with_passport_info(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/clients/{$this->client->id}/companions", [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'relationship' => 'child',
                'passport_number' => 'AB123456',
                'passport_country' => 'Canada',
                'passport_expiry_date' => '2030-12-31',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.passport_number', 'AB123456')
            ->assertJsonPath('data.passport_country', 'Canada')
            ->assertJsonPath('data.passport_expiry_date', '2030-12-31');
    }

    // ==================== Granular Permission Tests ====================

    public function test_user_with_clients_view_but_not_companions_view_cannot_list_companions(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $user->givePermissionTo('clients.view');
        // NOT giving companions.view

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/clients/{$this->client->id}/companions");

        $response->assertStatus(403);
    }

    public function test_user_with_companions_view_can_list_companions(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $user->givePermissionTo('companions.view');

        Companion::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/clients/{$this->client->id}/companions");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_user_with_companions_create_can_add_companion(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $user->givePermissionTo('companions.create');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/clients/{$this->client->id}/companions", [
                'first_name' => 'Test',
                'last_name' => 'Companion',
                'relationship' => 'spouse',
            ]);

        $response->assertStatus(201);
    }

    public function test_user_without_companions_create_cannot_add_companion(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $user->givePermissionTo('companions.view'); // Only view, no create

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/clients/{$this->client->id}/companions", [
                'first_name' => 'Test',
                'last_name' => 'Companion',
                'relationship' => 'spouse',
            ]);

        $response->assertStatus(403);
    }

    public function test_user_with_companions_update_can_edit_companion(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $user->givePermissionTo('companions.update');

        $companion = Companion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'first_name' => 'Original',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/clients/{$this->client->id}/companions/{$companion->id}", [
                'first_name' => 'Updated',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.first_name', 'Updated');
    }

    public function test_user_without_companions_delete_cannot_remove_companion(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $user->givePermissionTo(['companions.view', 'companions.update']);
        // NOT giving companions.delete

        $companion = Companion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/clients/{$this->client->id}/companions/{$companion->id}");

        $response->assertStatus(403);
    }

    public function test_user_with_companions_delete_can_remove_companion(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $user->givePermissionTo('companions.delete');

        $companion = Companion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/clients/{$this->client->id}/companions/{$companion->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('companions', ['id' => $companion->id]);
    }
}
