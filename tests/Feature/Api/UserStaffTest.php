<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStaffTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Tenant $otherTenant;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();

        // Admin user to authenticate requests
        $this->adminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $this->adminUser->assignRole('admin');
    }

    public function test_staff_returns_only_active_consultants(): void
    {
        // Active consultor - should be returned
        $activeConsultor = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $activeConsultor->assignRole('consultor');

        // Active admin - should NOT be returned (not a consultor)
        $activeAdmin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $activeAdmin->assignRole('admin');

        // Inactive consultor - should NOT be returned
        $inactiveConsultor = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);
        $inactiveConsultor->assignRole('consultor');

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/users/staff');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($activeConsultor->id, $data[0]['id']);
    }

    public function test_staff_with_include_user_id_adds_inactive_as_ghost(): void
    {
        // Inactive consultor (the "phantom")
        $inactiveConsultor = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);
        $inactiveConsultor->assignRole('consultor');

        // Active consultor
        $activeConsultor = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $activeConsultor->assignRole('consultor');

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/users/staff?include_user_id={$inactiveConsultor->id}");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // The inactive one should appear first with is_current_assignment = true
        $this->assertEquals($inactiveConsultor->id, $data[0]['id']);
        $this->assertTrue($data[0]['is_current_assignment']);
    }

    public function test_staff_with_include_user_id_does_not_duplicate_active_consultant(): void
    {
        // Active consultor
        $activeConsultor = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $activeConsultor->assignRole('consultor');

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/users/staff?include_user_id={$activeConsultor->id}");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($activeConsultor->id, $data[0]['id']);
        $this->assertFalse($data[0]['is_current_assignment']);
    }

    public function test_staff_does_not_return_other_tenant_users(): void
    {
        // Consultor in tenant A
        $consultorTenantA = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $consultorTenantA->assignRole('consultor');

        // Consultor in tenant B
        $consultorTenantB = User::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'is_active' => true,
        ]);
        $consultorTenantB->assignRole('consultor');

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/users/staff');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($consultorTenantA->id, $data[0]['id']);
    }
}
