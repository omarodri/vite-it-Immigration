<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTenantTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::factory()->create(['tenant_id' => null]);
        $this->superAdmin->assignRole('super-admin');
    }

    public function test_super_admin_can_list_tenants(): void
    {
        // Arrange
        Tenant::factory()->count(5)->create();

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/admin/tenants');

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'is_active', 'users_count'],
                ],
                'meta',
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_super_admin_can_create_tenant(): void
    {
        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/admin/tenants', [
                'name' => 'New Tenant LLC',
                'slug' => 'new-tenant-llc',
                'is_active' => true,
                'storage_quota_mb' => 500,
            ]);

        // Assert
        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'New Tenant LLC')
            ->assertJsonPath('data.slug', 'new-tenant-llc')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('tenants', [
            'name' => 'New Tenant LLC',
            'slug' => 'new-tenant-llc',
        ]);
    }

    public function test_super_admin_can_show_tenant(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        User::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/admin/tenants/{$tenant->id}");

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.id', $tenant->id)
            ->assertJsonPath('data.users_count', 3)
            ->assertJsonStructure([
                'data',
                'storage' => ['used_mb', 'quota_mb', 'usage_percent'],
            ]);
    }

    public function test_super_admin_can_update_tenant(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->putJson("/api/admin/tenants/{$tenant->id}", [
                'name' => 'Updated Tenant Name',
            ]);

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Tenant Name');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Updated Tenant Name',
        ]);
    }

    public function test_super_admin_can_deactivate_tenant(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create(['is_active' => true]);

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->deleteJson("/api/admin/tenants/{$tenant->id}");

        // Assert
        $response->assertOk()
            ->assertJson(['message' => 'Tenant deactivated successfully.']);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'is_active' => false,
        ]);
    }

    public function test_super_admin_can_activate_tenant(): void
    {
        // Arrange
        $tenant = Tenant::factory()->inactive()->create();

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/admin/tenants/{$tenant->id}/activate");

        // Assert
        $response->assertOk()
            ->assertJson(['message' => 'Tenant activated successfully.']);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_get_stats(): void
    {
        // Arrange
        Tenant::factory()->count(3)->create(['is_active' => true]);
        Tenant::factory()->count(2)->inactive()->create();

        // Act
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/admin/tenants/stats');

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'total_tenants',
                'active_tenants',
                'total_users',
                'total_storage_used_mb',
            ])
            ->assertJsonPath('total_tenants', 5)
            ->assertJsonPath('active_tenants', 3);
    }

    public function test_non_super_admin_cannot_access_admin_routes(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $adminUser = User::factory()->create(['tenant_id' => $tenant->id]);
        $adminUser->assignRole('admin');

        // Act
        $response = $this->actingAs($adminUser, 'sanctum')
            ->getJson('/api/admin/tenants');

        // Assert
        $response->assertForbidden();
    }

    public function test_slug_validation_on_create_duplicate(): void
    {
        // Arrange
        Tenant::factory()->create(['slug' => 'existing-slug']);

        // Act - Try to create tenant with duplicate slug
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/admin/tenants', [
                'name' => 'Duplicate Slug Tenant',
                'slug' => 'existing-slug',
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_slug_validation_on_create_invalid_chars(): void
    {
        // Act - Try to create tenant with invalid slug characters
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/admin/tenants', [
                'name' => 'Invalid Slug Tenant',
                'slug' => 'Invalid Slug!@#',
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }
}
