<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders for roles/permissions
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_user_only_sees_own_tenant_data(): void
    {
        // Create two tenants
        $tenant1 = Tenant::factory()->create(['name' => 'Tenant 1']);
        $tenant2 = Tenant::factory()->create(['name' => 'Tenant 2']);

        // Create users for each tenant
        $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);

        // Create clients for each tenant
        Client::factory()->count(3)->create(['tenant_id' => $tenant1->id]);
        Client::factory()->count(5)->create(['tenant_id' => $tenant2->id]);

        // Acting as user1 should only see tenant1's clients
        $this->actingAs($user1);
        $this->assertEquals(3, Client::count());

        // Acting as user2 should only see tenant2's clients
        $this->actingAs($user2);
        $this->assertEquals(5, Client::count());
    }

    public function test_super_admin_sees_all_tenant_data(): void
    {
        // Create two tenants
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        // Create a super admin (no tenant)
        $superAdmin = User::factory()->create(['tenant_id' => null]);
        $superAdmin->assignRole('super-admin');

        // Create clients for each tenant
        Client::factory()->count(3)->create(['tenant_id' => $tenant1->id]);
        Client::factory()->count(5)->create(['tenant_id' => $tenant2->id]);

        // Acting as super admin should see all clients
        $this->actingAs($superAdmin);
        $this->assertEquals(8, Client::count());
    }

    public function test_tenant_id_auto_assigned_on_create(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user);

        // Create a client without specifying tenant_id
        $client = Client::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'status' => 'active',
        ]);

        // Tenant ID should be auto-assigned
        $this->assertEquals($tenant->id, $client->tenant_id);
    }

    public function test_without_tenant_scope_returns_all_data(): void
    {
        // Create two tenants
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        // Create a regular user
        $user = User::factory()->create(['tenant_id' => $tenant1->id]);

        // Create clients for each tenant
        Client::factory()->count(3)->create(['tenant_id' => $tenant1->id]);
        Client::factory()->count(5)->create(['tenant_id' => $tenant2->id]);

        $this->actingAs($user);

        // Without scope should return all
        $this->assertEquals(8, Client::withoutTenantScope()->count());

        // With scope (default) should return only tenant1's
        $this->assertEquals(3, Client::count());
    }

    public function test_for_tenant_scope_queries_specific_tenant(): void
    {
        // Create two tenants
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        // Create clients for each tenant
        Client::factory()->count(3)->create(['tenant_id' => $tenant1->id]);
        Client::factory()->count(5)->create(['tenant_id' => $tenant2->id]);

        // forTenant should query specific tenant regardless of current user
        $this->assertEquals(3, Client::forTenant($tenant1->id)->count());
        $this->assertEquals(5, Client::forTenant($tenant2->id)->count());
    }

    public function test_user_belongs_to_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($user->belongsToTenant($tenant->id));
        $this->assertFalse($user->belongsToTenant(999));
    }

    public function test_tenant_middleware_blocks_user_without_tenant(): void
    {
        $user = User::factory()->create(['tenant_id' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/user');

        $response->assertStatus(403)
            ->assertJson(['error' => 'no_tenant']);
    }

    public function test_tenant_middleware_blocks_inactive_tenant(): void
    {
        $tenant = Tenant::factory()->inactive()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/user');

        $response->assertStatus(403)
            ->assertJson(['error' => 'tenant_inactive']);
    }

    public function test_tenant_middleware_allows_super_admin_without_tenant(): void
    {
        $superAdmin = User::factory()->create(['tenant_id' => null]);
        $superAdmin->assignRole('super-admin');

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/user');

        $response->assertStatus(200);
    }
}
