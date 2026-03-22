<?php

namespace Tests\Feature\Api;

use App\Models\Activity;
use App\Models\Client;
use App\Models\ImmigrationCase;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

        $this->userA = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->userA->assignRole('admin');

        $this->userB = User::factory()->create(['tenant_id' => $this->tenantB->id]);
        $this->userB->assignRole('admin');
    }

    public function test_user_cannot_see_other_tenant_clients(): void
    {
        // Arrange
        Client::factory()->count(3)->create(['tenant_id' => $this->tenantA->id]);
        Client::factory()->count(5)->create(['tenant_id' => $this->tenantB->id]);

        // Act - User A requests clients
        $response = $this->actingAs($this->userA, 'sanctum')
            ->getJson('/api/clients');

        // Assert - Only sees Tenant A's 3 clients
        $response->assertOk();
        $responseData = $response->json('data');
        $this->assertCount(3, $responseData);

        // Verify all returned clients belong to tenant A
        foreach ($responseData as $client) {
            $this->assertEquals($this->tenantA->id, $client['tenant_id']);
        }
    }

    public function test_user_cannot_access_other_tenant_case(): void
    {
        // Arrange - Create a case in tenant B
        $clientB = Client::factory()->create(['tenant_id' => $this->tenantB->id]);
        $caseB = ImmigrationCase::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'client_id' => $clientB->id,
        ]);

        // Act - User A tries to access tenant B's case
        $response = $this->actingAs($this->userA, 'sanctum')
            ->getJson("/api/cases/{$caseB->id}");

        // Assert - Should get 404 (TenantScope hides it) or 403
        $this->assertTrue(
            in_array($response->status(), [403, 404]),
            "Expected 403 or 404, got {$response->status()}"
        );
    }

    public function test_user_without_tenant_gets_403(): void
    {
        // Arrange - User with no tenant
        $userNoTenant = User::factory()->create(['tenant_id' => null]);
        $userNoTenant->assignRole('admin');

        // Act
        $response = $this->actingAs($userNoTenant, 'sanctum')
            ->getJson('/api/clients');

        // Assert - TenantMiddleware blocks access
        $response->assertStatus(403)
            ->assertJson(['error' => 'no_tenant']);
    }

    public function test_activity_logs_scoped_to_tenant(): void
    {
        // Arrange - Create activity logs for both tenants
        Activity::forceCreate([
            'log_name' => 'default',
            'description' => 'Tenant A activity',
            'causer_type' => User::class,
            'causer_id' => $this->userA->id,
            'tenant_id' => $this->tenantA->id,
            'event' => 'created',
        ]);

        Activity::forceCreate([
            'log_name' => 'default',
            'description' => 'Tenant B activity',
            'causer_type' => User::class,
            'causer_id' => $this->userB->id,
            'tenant_id' => $this->tenantB->id,
            'event' => 'created',
        ]);

        // Act - User A requests activity logs
        $response = $this->actingAs($this->userA, 'sanctum')
            ->getJson('/api/activity-logs');

        // Assert - Only sees Tenant A's logs
        $response->assertOk();
        $logs = $response->json('data');

        foreach ($logs as $log) {
            $this->assertEquals(
                $this->tenantA->id,
                $log['tenant_id'],
                'Activity log from another tenant should not be visible'
            );
        }
    }
}
