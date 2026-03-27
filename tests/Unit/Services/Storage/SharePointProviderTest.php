<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Storage;

use App\Models\Tenant;
use App\Services\OAuthCredentialService;
use App\Services\OAuthTokenService;
use App\Services\Storage\SharePointProvider;
use App\Models\Document;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for SharePointProvider.
 *
 * Validates that the provider correctly resolves drive IDs (explicit, from tenant,
 * or throws when missing) and that all inherited MicrosoftGraphBaseProvider
 * methods build URLs using the SharePoint /drives/{driveId} base path.
 */
class SharePointProviderTest extends TestCase
{
    use RefreshDatabase;

    private OAuthTokenService $tokenService;
    private OAuthCredentialService $credentialService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->tokenService = Mockery::mock(OAuthTokenService::class);
        $this->credentialService = Mockery::mock(OAuthCredentialService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // getDriveBasePath() resolution
    // ---------------------------------------------------------------

    public function test_get_drive_base_path_with_explicit_drive_id(): void
    {
        $driveId = 'explicit-drive-abc123';
        $tenant = Tenant::factory()->create();

        $this->tokenService
            ->shouldReceive('getValidTenantToken')
            ->with($tenant->id, 'microsoft')
            ->andReturn('fake-access-token');

        $provider = new SharePointProvider(
            $this->tokenService,
            $this->credentialService,
            $tenant->id,
            $driveId,
        );

        Http::fake([
            "graph.microsoft.com/v1.0/drives/{$driveId}/*" => Http::response([
                'id' => 'item-upload-001',
                'webUrl' => 'https://sharepoint.com/item-upload-001',
            ], 200),
        ]);

        $file = UploadedFile::fake()->create('report.pdf', 100);
        $result = $provider->upload($file, 'tenants/1/cases/report.pdf');

        $this->assertSame('item-upload-001', $result['external_id']);

        Http::assertSent(function ($request) use ($driveId) {
            return str_contains($request->url(), "/drives/{$driveId}/");
        });
    }

    public function test_get_drive_base_path_resolves_from_tenant(): void
    {
        $driveId = 'tenant-sp-drive-xyz';

        $tenant = Tenant::factory()->create();
        $tenant->sharepoint_drive_id = $driveId;
        $tenant->save();

        $this->tokenService
            ->shouldReceive('getValidTenantToken')
            ->with($tenant->id, 'microsoft')
            ->andReturn('fake-access-token');

        // No explicit driveId -- should resolve from tenant
        $provider = new SharePointProvider(
            $this->tokenService,
            $this->credentialService,
            $tenant->id,
        );

        Http::fake([
            "graph.microsoft.com/v1.0/drives/{$driveId}/*" => Http::response([
                'id' => 'item-resolved-001',
                'webUrl' => 'https://sharepoint.com/item-resolved-001',
            ], 200),
        ]);

        $file = UploadedFile::fake()->create('contract.docx', 50);
        $result = $provider->upload($file, 'tenants/1/cases/contract.docx');

        $this->assertSame('item-resolved-001', $result['external_id']);

        Http::assertSent(function ($request) use ($driveId) {
            return str_contains($request->url(), "/drives/{$driveId}/");
        });
    }

    public function test_get_drive_base_path_throws_when_no_drive_id(): void
    {
        $tenant = Tenant::factory()->create();
        // Ensure sharepoint_drive_id is null
        $tenant->sharepoint_drive_id = null;
        $tenant->save();

        $this->tokenService
            ->shouldReceive('getValidTenantToken')
            ->with($tenant->id, 'microsoft')
            ->andReturn('fake-access-token');

        $provider = new SharePointProvider(
            $this->tokenService,
            $this->credentialService,
            $tenant->id,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SharePoint drive ID is not configured');

        $file = UploadedFile::fake()->create('test.pdf', 10);
        $provider->upload($file, 'tenants/1/cases/test.pdf');
    }

    // ---------------------------------------------------------------
    // Inherited methods use correct SharePoint URLs
    // ---------------------------------------------------------------

    public function test_upload_uses_correct_sharepoint_url(): void
    {
        $driveId = 'sp-drive-upload';
        $tenant = Tenant::factory()->create();

        $this->tokenService
            ->shouldReceive('getValidTenantToken')
            ->with($tenant->id, 'microsoft')
            ->andReturn('fake-access-token');

        $provider = new SharePointProvider(
            $this->tokenService,
            $this->credentialService,
            $tenant->id,
            $driveId,
        );

        Http::fake([
            "graph.microsoft.com/v1.0/drives/{$driveId}/*" => Http::response([
                'id' => 'uploaded-item-id',
                'webUrl' => 'https://sharepoint.com/uploaded-item',
            ], 200),
        ]);

        $file = UploadedFile::fake()->create('invoice.pdf', 200);
        $result = $provider->upload($file, 'documents/invoices/invoice.pdf');

        $this->assertSame('uploaded-item-id', $result['external_id']);
        $this->assertSame('https://sharepoint.com/uploaded-item', $result['external_url']);
        $this->assertSame('documents/invoices/invoice.pdf', $result['storage_path']);

        Http::assertSent(function ($request) use ($driveId) {
            return str_contains($request->url(), "graph.microsoft.com/v1.0/drives/{$driveId}/root:/");
        });
    }

    public function test_create_folder_uses_correct_sharepoint_url(): void
    {
        $driveId = 'sp-drive-folder';
        $tenant = Tenant::factory()->create();

        $this->tokenService
            ->shouldReceive('getValidTenantToken')
            ->with($tenant->id, 'microsoft')
            ->andReturn('fake-access-token');

        $provider = new SharePointProvider(
            $this->tokenService,
            $this->credentialService,
            $tenant->id,
            $driveId,
        );

        Http::fake([
            "graph.microsoft.com/v1.0/drives/{$driveId}/root/children" => Http::response([
                'id' => 'folder-id-001',
                'webUrl' => 'https://sharepoint.com/folder-001',
            ], 201),
        ]);

        $result = $provider->createFolder('New Folder');

        $this->assertSame('folder-id-001', $result['external_id']);
        $this->assertSame('https://sharepoint.com/folder-001', $result['external_url']);

        Http::assertSent(function ($request) use ($driveId) {
            return $request->method() === 'POST'
                && str_contains($request->url(), "graph.microsoft.com/v1.0/drives/{$driveId}/root/children");
        });
    }

    public function test_create_folder_with_parent_uses_correct_sharepoint_url(): void
    {
        $driveId = 'sp-drive-subfolder';
        $tenant = Tenant::factory()->create();

        $this->tokenService
            ->shouldReceive('getValidTenantToken')
            ->with($tenant->id, 'microsoft')
            ->andReturn('fake-access-token');

        $provider = new SharePointProvider(
            $this->tokenService,
            $this->credentialService,
            $tenant->id,
            $driveId,
        );

        $parentId = 'parent-folder-ext-id';

        Http::fake([
            "graph.microsoft.com/v1.0/drives/{$driveId}/items/{$parentId}/children" => Http::response([
                'id' => 'subfolder-id-002',
                'webUrl' => 'https://sharepoint.com/subfolder-002',
            ], 201),
        ]);

        $result = $provider->createFolder('Sub Folder', $parentId);

        $this->assertSame('subfolder-id-002', $result['external_id']);

        Http::assertSent(function ($request) use ($driveId, $parentId) {
            return $request->method() === 'POST'
                && str_contains($request->url(), "drives/{$driveId}/items/{$parentId}/children");
        });
    }

    public function test_delete_uses_correct_sharepoint_url(): void
    {
        $driveId = 'sp-drive-delete';
        $tenant = Tenant::factory()->create();

        $this->tokenService
            ->shouldReceive('getValidTenantToken')
            ->with($tenant->id, 'microsoft')
            ->andReturn('fake-access-token');

        $provider = new SharePointProvider(
            $this->tokenService,
            $this->credentialService,
            $tenant->id,
            $driveId,
        );

        $externalId = 'item-to-delete-123';

        Http::fake([
            "graph.microsoft.com/v1.0/drives/{$driveId}/items/{$externalId}" => Http::response(null, 204),
        ]);

        $document = new Document();
        $document->external_id = $externalId;
        $document->storage_path = 'documents/old-file.pdf';

        $result = $provider->delete($document);

        $this->assertTrue($result);

        Http::assertSent(function ($request) use ($driveId, $externalId) {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), "drives/{$driveId}/items/{$externalId}");
        });
    }

    public function test_download_uses_correct_sharepoint_url(): void
    {
        $driveId = 'sp-drive-download';
        $tenant = Tenant::factory()->create();

        $this->tokenService
            ->shouldReceive('getValidTenantToken')
            ->with($tenant->id, 'microsoft')
            ->andReturn('fake-access-token');

        $provider = new SharePointProvider(
            $this->tokenService,
            $this->credentialService,
            $tenant->id,
            $driveId,
        );

        $externalId = 'item-to-download-456';

        // Http::fake does not preserve '@' prefixed JSON keys through json() dot-notation,
        // so the provider falls through to the StreamedResponse branch.
        // We verify the correct Graph URL is called regardless.
        Http::fake([
            "graph.microsoft.com/v1.0/drives/{$driveId}/*" => Http::response([
                'id' => $externalId,
                'name' => 'download-me.pdf',
            ], 200),
        ]);

        $document = new Document();
        $document->external_id = $externalId;
        $document->storage_path = 'documents/download-me.pdf';
        $document->original_name = 'download-me.pdf';
        $document->mime_type = 'application/pdf';

        $result = $provider->download($document);

        // Without @microsoft.graph.downloadUrl, download returns a StreamedResponse
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $result);

        Http::assertSent(function ($request) use ($driveId, $externalId) {
            return $request->method() === 'GET'
                && str_contains($request->url(), "drives/{$driveId}/items/{$externalId}");
        });
    }

    // ---------------------------------------------------------------
    // isAvailable()
    // ---------------------------------------------------------------

    public function test_is_available_returns_true_when_token_exists(): void
    {
        $tenant = Tenant::factory()->create();

        $this->tokenService
            ->shouldReceive('hasTenantToken')
            ->with($tenant->id, 'microsoft')
            ->andReturn(true);

        $provider = new SharePointProvider(
            $this->tokenService,
            $this->credentialService,
            $tenant->id,
            'some-drive-id',
        );

        $this->assertTrue($provider->isAvailable());
    }

    public function test_is_available_returns_false_when_no_token(): void
    {
        $tenant = Tenant::factory()->create();

        $this->tokenService
            ->shouldReceive('hasTenantToken')
            ->with($tenant->id, 'microsoft')
            ->andReturn(false);

        $provider = new SharePointProvider(
            $this->tokenService,
            $this->credentialService,
            $tenant->id,
            'some-drive-id',
        );

        $this->assertFalse($provider->isAvailable());
    }

    public function test_is_available_returns_false_when_no_tenant_id(): void
    {
        $provider = new SharePointProvider(
            $this->tokenService,
            $this->credentialService,
        );

        // No tenant ID and no authenticated user -- should return false
        $this->assertFalse($provider->isAvailable());
    }
}
