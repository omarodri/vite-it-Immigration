<?php

namespace Tests\Unit\Storage;

use App\Services\Storage\LocalStorageProvider;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests for LocalStorageProvider folder operations:
 * createFolder, deleteFolder, listFolder.
 */
class LocalStorageProviderFolderTest extends TestCase
{
    private LocalStorageProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new LocalStorageProvider();
        Storage::fake('local');
    }

    // ---------------------------------------------------------------
    // createFolder()
    // ---------------------------------------------------------------

    public function test_can_create_folder(): void
    {
        $result = $this->provider->createFolder('Documentos', 'tenants/1/cases/42');

        $this->assertArrayHasKey('external_id', $result);
        $this->assertArrayHasKey('external_url', $result);
        Storage::disk('local')->assertExists('tenants/1/cases/42/Documentos');
    }

    public function test_create_folder_returns_external_id_as_path(): void
    {
        $result = $this->provider->createFolder('Cartas', 'tenants/1/cases/10');

        $this->assertEquals('tenants/1/cases/10/Cartas', $result['external_id']);
        $this->assertEquals('', $result['external_url']);
    }

    public function test_create_folder_without_parent_uses_name_as_path(): void
    {
        $result = $this->provider->createFolder('tenants/1/root-folder');

        $this->assertEquals('tenants/1/root-folder', $result['external_id']);
        Storage::disk('local')->assertExists('tenants/1/root-folder');
    }

    public function test_create_folder_rejects_path_without_tenants_prefix(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tenants/ prefix');

        $this->provider->createFolder('Documentos', 'invalid/path');
    }

    public function test_create_folder_rejects_traversal(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->provider->createFolder('Documentos', 'tenants/../etc');
    }

    // ---------------------------------------------------------------
    // deleteFolder()
    // ---------------------------------------------------------------

    public function test_can_delete_folder(): void
    {
        Storage::disk('local')->makeDirectory('tenants/1/cases/42/Documentos');

        $result = $this->provider->deleteFolder('tenants/1/cases/42/Documentos');

        $this->assertTrue($result);
        Storage::disk('local')->assertMissing('tenants/1/cases/42/Documentos');
    }

    public function test_delete_folder_returns_true_when_folder_does_not_exist(): void
    {
        $result = $this->provider->deleteFolder('tenants/1/cases/42/nonexistent');

        $this->assertTrue($result);
    }

    public function test_delete_folder_rejects_path_without_tenants_prefix(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tenants/ prefix');

        $this->provider->deleteFolder('invalid/path');
    }

    // ---------------------------------------------------------------
    // listFolder()
    // ---------------------------------------------------------------

    public function test_can_list_folder_contents(): void
    {
        // Arrange: create directories and files inside a parent folder
        Storage::disk('local')->makeDirectory('tenants/1/cases/42/SubfolderA');
        Storage::disk('local')->makeDirectory('tenants/1/cases/42/SubfolderB');
        Storage::disk('local')->put('tenants/1/cases/42/document.pdf', 'content');

        // Act
        $items = $this->provider->listFolder('tenants/1/cases/42');

        // Assert
        $this->assertCount(3, $items);

        $folders = array_filter($items, fn ($item) => $item['type'] === 'folder');
        $files = array_filter($items, fn ($item) => $item['type'] === 'file');

        $this->assertCount(2, $folders);
        $this->assertCount(1, $files);

        $folderNames = array_column($folders, 'name');
        $this->assertContains('SubfolderA', $folderNames);
        $this->assertContains('SubfolderB', $folderNames);

        $fileItem = array_values($files)[0];
        $this->assertEquals('document.pdf', $fileItem['name']);
        $this->assertArrayHasKey('external_id', $fileItem);
    }

    public function test_list_folder_returns_empty_array_for_empty_folder(): void
    {
        Storage::disk('local')->makeDirectory('tenants/1/cases/42/empty');

        $items = $this->provider->listFolder('tenants/1/cases/42/empty');

        $this->assertIsArray($items);
        $this->assertCount(0, $items);
    }

    public function test_list_folder_rejects_path_without_tenants_prefix(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tenants/ prefix');

        $this->provider->listFolder('invalid/path');
    }
}
