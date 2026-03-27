<?php

declare(strict_types=1);

namespace Tests\Feature\SharePoint;

use App\Models\OauthToken;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OAuthTokenService;
use App\Services\Storage\StorageProviderFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SharePointEndpointTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('admin');
    }

    // ---------------------------------------------------------------
    // SharePoint Sites Discovery
    // ---------------------------------------------------------------

    public function test_list_sites_requires_auth(): void
    {
        $response = $this->getJson('/api/tenant/sharepoint/sites');

        $response->assertUnauthorized();
    }

    public function test_list_sites_fails_without_oauth(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/tenant/sharepoint/sites');

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Microsoft OAuth not configured');
    }

    public function test_list_sites_returns_sites_from_graph_api(): void
    {
        $this->configureMicrosoftOAuth();

        $fakeSites = [
            ['id' => 'site-1', 'displayName' => 'HR Site', 'webUrl' => 'https://contoso.sharepoint.com/sites/hr'],
            ['id' => 'site-2', 'displayName' => 'Legal Site', 'webUrl' => 'https://contoso.sharepoint.com/sites/legal'],
        ];

        Http::fake([
            'graph.microsoft.com/v1.0/sites*' => Http::response(['value' => $fakeSites]),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/tenant/sharepoint/sites');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', 'site-1')
            ->assertJsonPath('data.0.displayName', 'HR Site')
            ->assertJsonPath('data.1.id', 'site-2');
    }

    // ---------------------------------------------------------------
    // SharePoint Drives Discovery
    // ---------------------------------------------------------------

    public function test_list_drives_requires_auth(): void
    {
        $response = $this->getJson('/api/tenant/sharepoint/sites/some-site-id/drives');

        $response->assertUnauthorized();
    }

    public function test_list_drives_returns_drives_from_graph_api(): void
    {
        $this->configureMicrosoftOAuth();

        $fakeDrives = [
            ['id' => 'drive-1', 'name' => 'Documents', 'driveType' => 'documentLibrary', 'webUrl' => 'https://contoso.sharepoint.com/Shared%20Documents'],
            ['id' => 'drive-2', 'name' => 'Site Assets', 'driveType' => 'documentLibrary', 'webUrl' => 'https://contoso.sharepoint.com/SiteAssets'],
        ];

        Http::fake([
            'graph.microsoft.com/v1.0/sites/site-abc/drives*' => Http::response(['value' => $fakeDrives]),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/tenant/sharepoint/sites/site-abc/drives');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', 'drive-1')
            ->assertJsonPath('data.0.name', 'Documents')
            ->assertJsonPath('data.1.id', 'drive-2');
    }

    // ---------------------------------------------------------------
    // SharePoint Config
    // ---------------------------------------------------------------

    public function test_save_sharepoint_config_requires_auth(): void
    {
        $response = $this->putJson('/api/tenant/sharepoint/config', [
            'sharepoint_site_id' => 'site-1',
            'sharepoint_drive_id' => 'drive-1',
        ]);

        $response->assertUnauthorized();
    }

    public function test_save_sharepoint_config_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/tenant/sharepoint/config', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['sharepoint_site_id', 'sharepoint_drive_id']);
    }

    public function test_save_sharepoint_config_updates_tenant(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/tenant/sharepoint/config', [
                'sharepoint_site_id' => 'contoso.sharepoint.com,site-guid-1,web-guid-1',
                'sharepoint_drive_id' => 'b!drive-encoded-id',
                'sharepoint_site_url' => 'https://contoso.sharepoint.com/sites/immigration',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'SharePoint configuration saved successfully.');

        $this->tenant->refresh();
        $this->assertEquals('contoso.sharepoint.com,site-guid-1,web-guid-1', $this->tenant->sharepoint_site_id);
        $this->assertEquals('b!drive-encoded-id', $this->tenant->sharepoint_drive_id);
        $this->assertEquals('https://contoso.sharepoint.com/sites/immigration', $this->tenant->sharepoint_site_url);
    }

    // ---------------------------------------------------------------
    // Base Folder
    // ---------------------------------------------------------------

    public function test_update_base_folder_saves_path(): void
    {
        // Local storage: just saves path, no cloud folder creation
        $this->tenant->update(['storage_type' => 'local']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/tenant/base-folder', [
                'base_folder_path' => 'Immigration Cases',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Base folder configuration saved successfully.');

        $this->tenant->refresh();
        $this->assertEquals('Immigration Cases', $this->tenant->base_folder_path);
    }

    public function test_update_base_folder_creates_cloud_folder(): void
    {
        $this->tenant->update(['storage_type' => 'onedrive']);

        // Mock the StorageProviderFactory to return a mock provider
        $mockProvider = \Mockery::mock(\App\Contracts\DocumentStorageInterface::class);
        $mockProvider->shouldReceive('createFolder')
            ->once()
            ->with('Immigration Cloud Folder')
            ->andReturn(['external_id' => 'cloud-folder-ext-id-123']);

        $mockFactory = \Mockery::mock(StorageProviderFactory::class);
        $mockFactory->shouldReceive('makeForTenant')
            ->once()
            ->andReturn($mockProvider);

        $this->app->instance(StorageProviderFactory::class, $mockFactory);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/tenant/base-folder', [
                'base_folder_path' => 'Immigration Cloud Folder',
            ]);

        $response->assertOk();

        $this->tenant->refresh();
        $this->assertEquals('Immigration Cloud Folder', $this->tenant->base_folder_path);
        $this->assertEquals('cloud-folder-ext-id-123', $this->tenant->base_folder_external_id);
    }

    public function test_update_base_folder_rejects_invalid_characters(): void
    {
        $invalidPaths = [
            'path/with/slashes',
            'path\\with\\backslashes',
            'path:with:colons',
            'path*with*asterisks',
            'path?with?question',
            'path"with"quotes',
            'path<with>angles',
            'path|with|pipes',
        ];

        foreach ($invalidPaths as $invalidPath) {
            $response = $this->actingAs($this->user, 'sanctum')
                ->putJson('/api/tenant/base-folder', [
                    'base_folder_path' => $invalidPath,
                ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['base_folder_path']);
        }
    }

    public function test_update_base_folder_allows_null_to_clear(): void
    {
        // Pre-set values to confirm they get cleared
        $this->tenant->update([
            'base_folder_path' => 'Old Folder',
            'base_folder_external_id' => 'old-external-id',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/tenant/base-folder', [
                'base_folder_path' => null,
            ]);

        $response->assertOk();

        $this->tenant->refresh();
        $this->assertNull($this->tenant->base_folder_path);
        $this->assertNull($this->tenant->base_folder_external_id);
    }

    // ---------------------------------------------------------------
    // Storage Type - SharePoint acceptance
    // ---------------------------------------------------------------

    public function test_update_storage_type_accepts_sharepoint(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson('/api/tenant/storage-type', [
                'storage_type' => 'sharepoint',
            ]);

        $response->assertOk();

        $this->tenant->refresh();
        $this->assertEquals('sharepoint', $this->tenant->storage_type);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Configure the tenant with Microsoft OAuth credentials and a valid
     * OauthToken so that hasMicrosoftOAuth() returns true and the
     * OAuthTokenService can resolve a valid access token.
     */
    private function configureMicrosoftOAuth(): void
    {
        $this->tenant->ms_client_id = 'test-client-id';
        $this->tenant->ms_client_secret = 'test-client-secret';
        $this->tenant->save();

        OauthToken::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'provider' => 'microsoft',
            'access_token' => 'fake-access-token-for-graph-api',
            'refresh_token' => 'fake-refresh-token',
            'expires_at' => now()->addHour(),
        ]);
    }
}
