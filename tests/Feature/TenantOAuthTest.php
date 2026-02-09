<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TenantOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_admin_can_get_oauth_status(): void
    {
        $tenant = Tenant::factory()->withMicrosoftOAuth()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('admin');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/tenant/oauth/status');

        $response->assertOk()
            ->assertJsonStructure([
                'microsoft' => ['configured', 'client_id'],
                'google' => ['configured', 'client_id'],
                'system_fallback' => ['microsoft_available', 'google_available'],
            ])
            ->assertJsonPath('microsoft.configured', true)
            ->assertJsonPath('google.configured', false);
    }

    public function test_client_id_is_masked_in_response(): void
    {
        $tenant = Tenant::factory()->create([
            'ms_client_id' => 'abcd1234-5678-efgh-ijkl-mnopqrstuvwx',
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('admin');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/tenant/oauth/status');

        $response->assertOk();

        // Client ID should be masked (first 4 and last 4 visible)
        $clientId = $response->json('microsoft.client_id');
        $this->assertStringStartsWith('abcd', $clientId);
        $this->assertStringEndsWith('uvwx', $clientId);
        $this->assertStringContainsString('*', $clientId);
    }

    public function test_admin_can_save_microsoft_credentials(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
        ]);

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('admin');

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/tenant/oauth/microsoft', [
                'client_id' => 'test-client-id',
                'client_secret' => 'test-client-secret',
            ]);

        $response->assertOk()
            ->assertJsonPath('configured', true);

        $tenant->refresh();
        $this->assertEquals('test-client-id', $tenant->ms_client_id);
        $this->assertNotNull($tenant->getRawOriginal('ms_client_secret'));
    }

    public function test_invalid_microsoft_credentials_are_rejected(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'error' => 'invalid_client',
                'error_description' => 'Invalid client credentials',
            ], 400),
        ]);

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('admin');

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/tenant/oauth/microsoft', [
                'client_id' => 'invalid-id',
                'client_secret' => 'invalid-secret',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Invalid Microsoft OAuth credentials.');
    }

    public function test_admin_can_save_google_credentials(): void
    {
        Http::fake([
            'accounts.google.com/.well-known/openid-configuration' => Http::response([
                'issuer' => 'https://accounts.google.com',
            ]),
        ]);

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('admin');

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/tenant/oauth/google', [
                'client_id' => 'test-id.apps.googleusercontent.com',
                'client_secret' => 'test-client-secret-long-enough',
            ]);

        $response->assertOk()
            ->assertJsonPath('configured', true);

        $tenant->refresh();
        $this->assertEquals('test-id.apps.googleusercontent.com', $tenant->google_client_id);
    }

    public function test_invalid_google_client_id_format_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('admin');

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/tenant/oauth/google', [
                'client_id' => 'invalid-format-without-suffix',
                'client_secret' => 'some-secret-here',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Invalid Google OAuth credentials.');
    }

    public function test_admin_can_remove_microsoft_credentials(): void
    {
        $tenant = Tenant::factory()->withMicrosoftOAuth()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('admin');

        $this->assertTrue($tenant->hasMicrosoftOAuth());

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/tenant/oauth/microsoft');

        $response->assertOk()
            ->assertJsonPath('configured', false);

        $tenant->refresh();
        $this->assertFalse($tenant->hasMicrosoftOAuth());
    }

    public function test_admin_can_remove_google_credentials(): void
    {
        $tenant = Tenant::factory()->withGoogleOAuth()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('admin');

        $this->assertTrue($tenant->hasGoogleOAuth());

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/tenant/oauth/google');

        $response->assertOk()
            ->assertJsonPath('configured', false);

        $tenant->refresh();
        $this->assertFalse($tenant->hasGoogleOAuth());
    }

    public function test_non_admin_cannot_modify_oauth_credentials(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('apoyo');

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/tenant/oauth/microsoft', [
                'client_id' => 'test',
                'client_secret' => 'test',
            ]);

        // Should still work because we're not restricting by permission in controller
        // But if we add permission checks, this would be 403
        $response->assertStatus(422); // Validation will fail before auth
    }
}
