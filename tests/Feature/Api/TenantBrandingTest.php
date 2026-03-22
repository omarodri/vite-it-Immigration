<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantBrandingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::factory()->create([
            'slug' => 'test-branding-co',
            'settings' => [
                'logo_url' => null,
                'primary_color' => '#4361ee',
                'secondary_color' => '#805dca',
                'company_name' => 'Test Branding Co',
            ],
        ]);

        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->admin->assignRole('admin');
    }

    public function test_tenant_branding_returns_correct_colors(): void
    {
        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/tenant');

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.branding.primary_color', '#4361ee')
            ->assertJsonPath('data.branding.secondary_color', '#805dca');
    }

    public function test_update_branding_colors(): void
    {
        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/tenant/branding', [
                'primary_color' => '#ff5733',
                'secondary_color' => '#33ff57',
            ]);

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.branding.primary_color', '#ff5733')
            ->assertJsonPath('data.branding.secondary_color', '#33ff57');

        // Verify persistence
        $this->tenant->refresh();
        $this->assertEquals('#ff5733', $this->tenant->settings['primary_color']);
        $this->assertEquals('#33ff57', $this->tenant->settings['secondary_color']);
    }

    public function test_upload_logo_success(): void
    {
        Storage::fake('public');

        // Arrange
        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tenant/branding/logo', [
                'logo' => $file,
            ]);

        // Assert
        $response->assertOk();
        $logoUrl = $response->json('data.branding.logo_url');
        $this->assertNotNull($logoUrl, 'Logo URL should not be null after upload');
    }

    public function test_upload_logo_invalid_format(): void
    {
        Storage::fake('public');

        // Arrange - Upload a text file instead of image
        $file = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tenant/branding/logo', [
                'logo' => $file,
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_upload_logo_too_large(): void
    {
        Storage::fake('public');

        // Arrange - Create image larger than 2MB (2048 KB)
        $file = UploadedFile::fake()->image('large-logo.png')->size(3000);

        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tenant/branding/logo', [
                'logo' => $file,
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_delete_logo(): void
    {
        Storage::fake('public');

        // Arrange - First upload a logo
        $file = UploadedFile::fake()->image('logo.png', 200, 200);
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tenant/branding/logo', ['logo' => $file]);

        // Act - Delete the logo
        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/tenant/branding/logo');

        // Assert
        $response->assertOk();
        $this->assertNull($response->json('data.branding.logo_url'));
    }

    public function test_update_theme_settings(): void
    {
        // Act
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/tenant/theme', [
                'mode' => 'dark',
                'menu' => 'horizontal',
                'layout' => 'boxed-layout',
            ]);

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.theme.mode', 'dark')
            ->assertJsonPath('data.theme.menu', 'horizontal')
            ->assertJsonPath('data.theme.layout', 'boxed-layout');

        // Verify persistence
        $this->tenant->refresh();
        $this->assertEquals('dark', data_get($this->tenant->settings, 'theme.mode'));
        $this->assertEquals('horizontal', data_get($this->tenant->settings, 'theme.menu'));
    }

    public function test_public_branding_endpoint(): void
    {
        // Act - No auth required
        $response = $this->getJson('/api/tenant/test-branding-co/branding');

        // Assert
        $response->assertOk()
            ->assertJsonPath('slug', 'test-branding-co')
            ->assertJsonPath('primary_color', '#4361ee')
            ->assertJsonPath('company_name', 'Test Branding Co');
    }
}
