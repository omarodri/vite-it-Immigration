<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Storage;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Document;
use App\Services\OAuthCredentialService;
use App\Services\OAuthTokenService;
use App\Services\Storage\OneDriveProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class OneDriveProviderTest extends TestCase
{
    use RefreshDatabase;

    private OneDriveProvider $provider;
    private OAuthTokenService $mockTokenService;
    private OAuthCredentialService $mockCredentialService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockTokenService = Mockery::mock(OAuthTokenService::class);
        $this->mockCredentialService = Mockery::mock(OAuthCredentialService::class);

        // Default: getValidTenantToken returns a fake token
        $this->mockTokenService->shouldReceive('getValidTenantToken')
            ->andReturn('fake-token')
            ->byDefault();

        // Create a user and authenticate so resolveTenantId works
        $tenant = Tenant::factory()->create(['storage_type' => 'onedrive']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($user);

        $this->provider = new OneDriveProvider(
            $this->mockTokenService,
            $this->mockCredentialService,
        );
    }

    // ---------------------------------------------------------------
    // getDriveBasePath()
    // ---------------------------------------------------------------

    public function test_get_drive_base_path_returns_me_drive(): void
    {
        // Use reflection to access the protected method
        $reflection = new \ReflectionMethod(OneDriveProvider::class, 'getDriveBasePath');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($this->provider);

        $this->assertEquals('/me/drive', $result);
    }

    // ---------------------------------------------------------------
    // upload() - URL correctness
    // ---------------------------------------------------------------

    public function test_upload_uses_me_drive_url(): void
    {
        Http::fake([
            'graph.microsoft.com/*' => Http::response([
                'id' => 'uploaded-file-id',
                'webUrl' => 'https://onedrive.live.com/file/uploaded-file-id',
            ], 200),
        ]);

        $file = UploadedFile::fake()->create('test.pdf', 100); // 100KB, under 4MB

        $result = $this->provider->upload($file, 'cases/42/test.pdf');

        $this->assertEquals('uploaded-file-id', $result['external_id']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/me/drive');
        });
    }

    // ---------------------------------------------------------------
    // createFolder() - URL correctness
    // ---------------------------------------------------------------

    public function test_create_folder_at_root_uses_correct_url(): void
    {
        Http::fake([
            'graph.microsoft.com/*' => Http::response([
                'id' => 'new-folder-id',
                'webUrl' => 'https://onedrive.live.com/folder/new-folder-id',
            ], 200),
        ]);

        $result = $this->provider->createFolder('Case-2024-001');

        $this->assertEquals('new-folder-id', $result['external_id']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/me/drive/root/children')
                && $request->method() === 'POST';
        });
    }

    public function test_create_folder_with_parent_uses_correct_url(): void
    {
        $parentId = 'parent-ext-id-abc';

        Http::fake([
            'graph.microsoft.com/*' => Http::response([
                'id' => 'child-folder-id',
                'webUrl' => 'https://onedrive.live.com/folder/child-folder-id',
            ], 200),
        ]);

        $result = $this->provider->createFolder('Documentos', $parentId);

        $this->assertEquals('child-folder-id', $result['external_id']);

        Http::assertSent(function ($request) use ($parentId) {
            return str_contains($request->url(), "/me/drive/items/{$parentId}/children")
                && $request->method() === 'POST';
        });
    }

    // ---------------------------------------------------------------
    // delete() - URL correctness
    // ---------------------------------------------------------------

    public function test_delete_uses_correct_url(): void
    {
        $externalId = 'doc-ext-id-123';

        Http::fake([
            'graph.microsoft.com/*' => Http::response(null, 204),
        ]);

        $document = new Document();
        $document->external_id = $externalId;

        $result = $this->provider->delete($document);

        $this->assertTrue($result);

        Http::assertSent(function ($request) use ($externalId) {
            return str_contains($request->url(), "/me/drive/items/{$externalId}")
                && $request->method() === 'DELETE';
        });
    }

    // ---------------------------------------------------------------
    // download() - URL correctness
    // ---------------------------------------------------------------

    public function test_download_uses_correct_url(): void
    {
        $externalId = 'doc-ext-id-456';

        Http::fake([
            'graph.microsoft.com/*' => Http::response([
                'id' => $externalId,
                'name' => 'report.pdf',
                '@microsoft.graph.downloadUrl' => 'https://download.example.com/report.pdf',
            ], 200),
        ]);

        $document = new Document();
        $document->id = 1;
        $document->external_id = $externalId;
        $document->original_name = 'report.pdf';
        $document->mime_type = 'application/pdf';

        $result = $this->provider->download($document);

        // When @microsoft.graph.downloadUrl is present, returns the URL string;
        // otherwise falls back to a StreamedResponse. Either way the Graph call
        // must target /me/drive/items/{id}.
        if (is_string($result)) {
            $this->assertEquals('https://download.example.com/report.pdf', $result);
        } else {
            // Fallback StreamedResponse is also acceptable
            $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $result);
        }

        Http::assertSent(function ($request) use ($externalId) {
            return str_contains($request->url(), "/me/drive/items/{$externalId}")
                && $request->method() === 'GET';
        });
    }

    // ---------------------------------------------------------------
    // isAvailable() - token check
    // ---------------------------------------------------------------

    public function test_is_available_checks_microsoft_token(): void
    {
        $this->mockTokenService->shouldReceive('hasTenantToken')
            ->once()
            ->withArgs(function (int $tenantId, string $provider) {
                return $provider === 'microsoft';
            })
            ->andReturn(true);

        $result = $this->provider->isAvailable();

        $this->assertTrue($result);
    }
}
