<?php

namespace Tests\Unit\Services;

use App\Models\Document;
use App\Services\Storage\LocalStorageProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests for LocalStorageProvider path traversal protection.
 *
 * Verifies that the validatePath() guard rejects directory traversal
 * patterns, null bytes, and paths outside the tenants/ prefix.
 */
class LocalStorageProviderTest extends TestCase
{
    private LocalStorageProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new LocalStorageProvider();
        Storage::fake('local');
    }

    // ---------------------------------------------------------------
    // upload() - path traversal protection
    // ---------------------------------------------------------------

    public function test_upload_rejects_double_dot_traversal(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('directory traversal');

        $this->provider->upload($file, 'tenants/../etc/passwd');
    }

    public function test_upload_rejects_dot_slash_prefix(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('directory traversal');

        $this->provider->upload($file, './tenants/1/docs/file.pdf');
    }

    public function test_upload_rejects_null_bytes(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('null bytes');

        $this->provider->upload($file, "tenants/1/docs/file.pdf\0.jpg");
    }

    public function test_upload_rejects_path_without_tenants_prefix(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tenants/ prefix');

        $this->provider->upload($file, 'private/secrets/file.pdf');
    }

    public function test_upload_accepts_valid_tenant_path(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100);

        $result = $this->provider->upload($file, 'tenants/1/cases/42/doc.pdf');

        $this->assertArrayHasKey('storage_path', $result);
        $this->assertArrayHasKey('size', $result);
        Storage::disk('local')->assertExists('tenants/1/cases/42/doc.pdf');
    }

    // ---------------------------------------------------------------
    // download() - path traversal protection
    // ---------------------------------------------------------------

    public function test_download_rejects_traversal_in_storage_path(): void
    {
        $document = new Document();
        $document->storage_path = 'tenants/1/../../etc/passwd';
        $document->original_name = 'passwd';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('directory traversal');

        $this->provider->download($document);
    }

    public function test_download_rejects_path_without_tenants_prefix(): void
    {
        $document = new Document();
        $document->storage_path = 'app/private/secret.pdf';
        $document->original_name = 'secret.pdf';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tenants/ prefix');

        $this->provider->download($document);
    }

    // ---------------------------------------------------------------
    // delete() - path traversal protection
    // ---------------------------------------------------------------

    public function test_delete_rejects_traversal_in_storage_path(): void
    {
        $document = new Document();
        $document->storage_path = 'tenants/../../../etc/important';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('directory traversal');

        $this->provider->delete($document);
    }

    public function test_delete_returns_true_for_null_storage_path(): void
    {
        $document = new Document();
        $document->storage_path = null;

        $result = $this->provider->delete($document);

        $this->assertTrue($result);
    }

    // ---------------------------------------------------------------
    // move() - path traversal protection
    // ---------------------------------------------------------------

    public function test_move_rejects_traversal_in_current_path(): void
    {
        $document = new Document();
        $document->storage_path = 'tenants/1/../../../etc/passwd';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('directory traversal');

        $this->provider->move($document, 'tenants/1/new-location/file.pdf');
    }

    public function test_move_rejects_traversal_in_new_path(): void
    {
        Storage::disk('local')->put('tenants/1/cases/42/file.pdf', 'content');

        $document = new Document();
        $document->storage_path = 'tenants/1/cases/42/file.pdf';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('directory traversal');

        $this->provider->move($document, 'tenants/1/../../etc/exploit');
    }

    public function test_move_rejects_new_path_without_tenants_prefix(): void
    {
        Storage::disk('local')->put('tenants/1/cases/42/file.pdf', 'content');

        $document = new Document();
        $document->storage_path = 'tenants/1/cases/42/file.pdf';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tenants/ prefix');

        $this->provider->move($document, 'private/exploit.pdf');
    }

    public function test_move_succeeds_with_valid_paths(): void
    {
        Storage::disk('local')->put('tenants/1/cases/42/file.pdf', 'content');

        $document = new Document();
        $document->storage_path = 'tenants/1/cases/42/file.pdf';

        $result = $this->provider->move($document, 'tenants/1/cases/99/file.pdf');

        $this->assertTrue($result);
        Storage::disk('local')->assertMissing('tenants/1/cases/42/file.pdf');
        Storage::disk('local')->assertExists('tenants/1/cases/99/file.pdf');
    }

    // ---------------------------------------------------------------
    // Edge cases for traversal patterns
    // ---------------------------------------------------------------

    public function test_rejects_backslash_traversal(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100);

        $this->expectException(\RuntimeException::class);

        $this->provider->upload($file, 'tenants\\..\\etc\\passwd');
    }

    public function test_rejects_double_dot_at_end_of_path(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('directory traversal');

        $this->provider->upload($file, 'tenants/1/cases/..');
    }

    public function test_allows_double_dots_in_filenames(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 100);

        // "my..file.pdf" is a valid filename -- no traversal
        $result = $this->provider->upload($file, 'tenants/1/cases/my..file.pdf');

        $this->assertArrayHasKey('storage_path', $result);
    }
}
