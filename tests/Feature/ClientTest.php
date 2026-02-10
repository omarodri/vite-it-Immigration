<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $adminUser;

    protected User $regularUser;

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
    }

    // ==================== List Clients ====================

    public function test_admin_can_list_clients(): void
    {
        Client::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/clients');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    public function test_clients_are_paginated(): void
    {
        Client::factory()->count(20)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/clients?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('total', 20);
    }

    public function test_can_search_clients_by_name(): void
    {
        Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
        Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/clients?search=John');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.first_name', 'John');
    }

    public function test_can_search_clients_by_email(): void
    {
        Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'specific@example.com',
        ]);
        Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'other@example.com',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/clients?search=specific@example.com');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_clients_by_status(): void
    {
        Client::factory()->count(3)->prospect()->create(['tenant_id' => $this->tenant->id]);
        Client::factory()->count(2)->active()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/clients?status=prospect');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_unauthorized_user_cannot_list_clients(): void
    {
        // The 'cliente' role doesn't have clients.view permission
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/clients');

        $response->assertStatus(403);
    }

    // ==================== Create Client ====================

    public function test_admin_can_create_client(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/clients', [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@example.com',
                'phone' => '+1234567890',
                'status' => 'prospect',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('client.first_name', 'John')
            ->assertJsonPath('client.last_name', 'Doe')
            ->assertJsonPath('client.status', 'prospect');

        $this->assertDatabaseHas('clients', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_client_defaults_to_prospect_status(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/clients', [
                'first_name' => 'John',
                'last_name' => 'Doe',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('client.status', 'prospect');
    }

    public function test_create_client_requires_first_name(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/clients', [
                'last_name' => 'Doe',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('first_name');
    }

    public function test_create_client_requires_last_name(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/clients', [
                'first_name' => 'John',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('last_name');
    }

    public function test_unauthorized_user_cannot_create_client(): void
    {
        // The 'cliente' role doesn't have clients.create permission
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->postJson('/api/clients', [
                'first_name' => 'John',
                'last_name' => 'Doe',
            ]);

        $response->assertStatus(403);
    }

    // ==================== Show Client ====================

    public function test_admin_can_view_client(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/clients/{$client->id}");

        $response->assertStatus(200)
            ->assertJsonPath('first_name', $client->first_name)
            ->assertJsonPath('last_name', $client->last_name);
    }

    public function test_cannot_view_client_from_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $client = Client::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/clients/{$client->id}");

        $response->assertStatus(404);
    }

    // ==================== Update Client ====================

    public function test_admin_can_update_client(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'John',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/clients/{$client->id}", [
                'first_name' => 'Jane',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('client.first_name', 'Jane');

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'first_name' => 'Jane',
        ]);
    }

    public function test_can_update_client_status(): void
    {
        $client = Client::factory()->prospect()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/clients/{$client->id}", [
                'status' => 'active',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('client.status', 'active');
    }

    public function test_unauthorized_user_cannot_update_client(): void
    {
        // The 'cliente' role doesn't have clients.update permission
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->putJson("/api/clients/{$client->id}", [
                'first_name' => 'Jane',
            ]);

        $response->assertStatus(403);
    }

    // ==================== Delete Client ====================

    public function test_admin_can_delete_client(): void
    {
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/clients/{$client->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Client deleted successfully');

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_admin_can_bulk_delete_clients(): void
    {
        $clients = Client::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        $ids = $clients->pluck('id')->toArray();

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson('/api/clients/bulk', ['ids' => $ids]);

        $response->assertStatus(200);

        foreach ($ids as $id) {
            $this->assertSoftDeleted('clients', ['id' => $id]);
        }
    }

    public function test_unauthorized_user_cannot_delete_client(): void
    {
        // The 'cliente' role doesn't have clients.delete permission
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->deleteJson("/api/clients/{$client->id}");

        $response->assertStatus(403);
    }

    // ==================== Prospect Conversion ====================

    public function test_can_convert_prospect_to_active(): void
    {
        $client = Client::factory()->prospect()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/clients/{$client->id}/convert");

        $response->assertStatus(200)
            ->assertJsonPath('client.status', 'active')
            ->assertJsonPath('message', 'Prospect converted to active client successfully');

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'status' => 'active',
        ]);
    }

    public function test_cannot_convert_non_prospect_client(): void
    {
        $client = Client::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/clients/{$client->id}/convert");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Only prospects can be converted to active clients');
    }

    // ==================== Duplicate Prevention ====================

    public function test_cannot_create_client_with_duplicate_email_in_same_tenant(): void
    {
        Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'duplicate@example.com',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/clients', [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'duplicate@example.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_cannot_create_client_with_duplicate_phone_in_same_tenant(): void
    {
        Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '+1234567890',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/clients', [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'phone' => '+1234567890',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }

    public function test_can_create_client_with_same_email_in_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        Client::factory()->create([
            'tenant_id' => $otherTenant->id,
            'email' => 'shared@example.com',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/clients', [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'shared@example.com',
            ]);

        $response->assertStatus(201);
    }

    public function test_cannot_update_client_with_duplicate_email(): void
    {
        Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'existing@example.com',
        ]);

        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'original@example.com',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/clients/{$client->id}", [
                'email' => 'existing@example.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_can_update_client_keeping_same_email(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'unchanged@example.com',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/clients/{$client->id}", [
                'first_name' => 'Updated',
                'email' => 'unchanged@example.com',
            ]);

        $response->assertStatus(200);
    }

    // ==================== Tenant Isolation ====================

    public function test_client_auto_assigned_to_user_tenant(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/clients', [
                'first_name' => 'John',
                'last_name' => 'Doe',
            ]);

        $response->assertStatus(201);

        $clientId = $response->json('client.id');
        $this->assertDatabaseHas('clients', [
            'id' => $clientId,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_user_only_sees_own_tenant_clients(): void
    {
        Client::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $otherTenant = Tenant::factory()->create();
        Client::factory()->count(5)->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/clients');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    // ==================== Statistics ====================

    public function test_can_get_client_statistics(): void
    {
        Client::factory()->count(2)->prospect()->create(['tenant_id' => $this->tenant->id]);
        Client::factory()->count(3)->active()->create(['tenant_id' => $this->tenant->id]);
        Client::factory()->count(1)->inactive()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/clients/statistics');

        $response->assertStatus(200)
            ->assertJsonPath('prospect', 2)
            ->assertJsonPath('active', 3)
            ->assertJsonPath('inactive', 1)
            ->assertJsonPath('total', 6);
    }

    // ==================== Activity Logging ====================

    public function test_creating_client_logs_activity(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/clients', [
                'first_name' => 'John',
                'last_name' => 'Doe',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'clients',
            'causer_id' => $this->adminUser->id,
        ]);
    }
}
