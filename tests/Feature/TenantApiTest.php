<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_user_can_get_current_tenant(): void
    {
        $tenant = Tenant::factory()->create([
            'settings' => [
                'logo_url' => 'https://example.com/logo.png',
                'primary_color' => '#ff0000',
                'company_name' => 'Test Company',
            ],
        ]);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('admin');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/tenant');

        $response->assertOk()
            ->assertJsonPath('data.id', $tenant->id)
            ->assertJsonPath('data.name', $tenant->name)
            ->assertJsonPath('data.branding.logo_url', 'https://example.com/logo.png')
            ->assertJsonPath('data.branding.primary_color', '#ff0000')
            ->assertJsonPath('data.company.name', 'Test Company');
    }

    public function test_admin_can_update_tenant_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('admin');

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/tenant/settings', [
                'name' => 'Updated Tenant Name',
                'company_name' => 'Updated Company',
                'company_email' => 'contact@updated.com',
                'timezone' => 'America/New_York',
                'language' => 'fr',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Tenant Name')
            ->assertJsonPath('data.company.name', 'Updated Company')
            ->assertJsonPath('data.company.email', 'contact@updated.com')
            ->assertJsonPath('data.preferences.timezone', 'America/New_York')
            ->assertJsonPath('data.preferences.language', 'fr');
    }

    public function test_non_admin_cannot_update_tenant_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('apoyo'); // Support staff - no settings.update permission

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/tenant/settings', [
                'name' => 'Hacked Name',
            ]);

        $response->assertForbidden();
    }

    public function test_admin_can_update_tenant_branding(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('admin');

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/tenant/branding', [
                'logo_url' => 'https://newlogo.com/logo.svg',
                'primary_color' => '#00ff00',
                'secondary_color' => '#0000ff',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.branding.logo_url', 'https://newlogo.com/logo.svg')
            ->assertJsonPath('data.branding.primary_color', '#00ff00')
            ->assertJsonPath('data.branding.secondary_color', '#0000ff');
    }

    public function test_branding_validation_rejects_invalid_color(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('admin');

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/tenant/branding', [
                'primary_color' => 'not-a-color',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['primary_color']);
    }

    public function test_public_branding_endpoint_returns_tenant_branding(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'test-company',
            'settings' => [
                'logo_url' => 'https://example.com/logo.png',
                'primary_color' => '#123456',
                'company_name' => 'Test Company Inc',
            ],
        ]);

        $response = $this->getJson('/api/tenant/test-company/branding');

        $response->assertOk()
            ->assertJsonPath('slug', 'test-company')
            ->assertJsonPath('logo_url', 'https://example.com/logo.png')
            ->assertJsonPath('primary_color', '#123456')
            ->assertJsonPath('company_name', 'Test Company Inc');
    }

    public function test_public_branding_returns_404_for_unknown_tenant(): void
    {
        $response = $this->getJson('/api/tenant/unknown-slug/branding');

        $response->assertNotFound();
    }

    public function test_public_branding_returns_404_for_inactive_tenant(): void
    {
        Tenant::factory()->inactive()->create(['slug' => 'inactive-tenant']);

        $response = $this->getJson('/api/tenant/inactive-tenant/branding');

        $response->assertNotFound();
    }

    public function test_tenant_resource_shows_oauth_status(): void
    {
        $tenant = Tenant::factory()
            ->withMicrosoftOAuth()
            ->create();

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('admin');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/tenant');

        $response->assertOk()
            ->assertJsonPath('data.integrations.microsoft_configured', true)
            ->assertJsonPath('data.integrations.google_configured', false);
    }
}
