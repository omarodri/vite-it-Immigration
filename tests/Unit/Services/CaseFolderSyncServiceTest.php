<?php

namespace Tests\Unit\Services;

use App\Contracts\DocumentStorageInterface;
use App\Models\DocumentFolder;
use App\Models\ImmigrationCase;
use App\Models\Tenant;
use App\Services\Document\CaseFolderSyncService;
use App\Services\Storage\StorageProviderFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CaseFolderSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private CaseFolderSyncService $syncService;
    private StorageProviderFactory $mockFactory;
    private DocumentStorageInterface $mockProvider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->mockProvider = Mockery::mock(DocumentStorageInterface::class);
        $this->mockFactory = Mockery::mock(StorageProviderFactory::class);
        $this->mockFactory->shouldReceive('makeForTenant')
            ->andReturn($this->mockProvider)
            ->byDefault();

        $this->syncService = new CaseFolderSyncService($this->mockFactory);
    }

    public function test_sync_marks_folders_synced_for_local_storage(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create(['storage_type' => 'local']);
        $case = $this->createCaseWithFolders($tenant);

        // The local storage provider creates folders and returns path-based IDs
        $this->mockProvider->shouldReceive('createFolder')
            ->andReturnUsing(function (string $name, ?string $parentId = null) {
                return [
                    'external_id' => ($parentId ? $parentId . '/' : '') . $name,
                    'external_url' => '',
                ];
            });

        // Act
        $this->syncService->syncFolderStructure($case);

        // Assert
        $case->refresh();
        $this->assertEquals('synced', $case->folder_sync_status);
        $this->assertNotNull($case->folder_synced_at);
        $this->assertNotNull($case->root_external_folder_id);

        $folders = DocumentFolder::withoutGlobalScopes()->where('case_id', $case->id)->get();
        foreach ($folders as $folder) {
            $this->assertEquals('synced', $folder->sync_status, "Folder '{$folder->name}' should be synced");
            $this->assertNotNull($folder->external_id, "Folder '{$folder->name}' should have external_id");
            $this->assertNotNull($folder->synced_at, "Folder '{$folder->name}' should have synced_at");
        }
    }

    public function test_sync_skips_already_synced_folders(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create(['storage_type' => 'onedrive']);

        // Create case without triggering observer (which creates default unsynced folders)
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($user);

        $client = \App\Models\Client::factory()->create(['tenant_id' => $tenant->id]);

        // Use withoutEvents to prevent the CaseObserver from creating default folders
        $case = ImmigrationCase::withoutEvents(function () use ($tenant, $client) {
            return ImmigrationCase::factory()->active()->create([
                'tenant_id' => $tenant->id,
                'client_id' => $client->id,
                'root_external_folder_id' => 'existing-root-id',
            ]);
        });

        // Manually create only synced folders
        foreach (['Archivo', 'Cartas', 'Documentos'] as $i => $name) {
            DocumentFolder::withoutGlobalScopes()->forceCreate([
                'tenant_id' => $tenant->id,
                'case_id' => $case->id,
                'parent_id' => null,
                'name' => $name,
                'sort_order' => $i,
                'is_default' => true,
                'sync_status' => 'synced',
                'external_id' => 'ext-' . $name,
                'synced_at' => now(),
            ]);
        }

        // The provider should NOT be called with createFolder since all folders already have external_id.
        $this->mockProvider->shouldNotReceive('createFolder');

        // Act
        $this->syncService->syncFolderStructure($case);

        // Assert
        $case->refresh();
        $this->assertEquals('synced', $case->folder_sync_status);

        $folders = DocumentFolder::withoutGlobalScopes()->where('case_id', $case->id)->get();
        foreach ($folders as $folder) {
            $this->assertEquals('synced', $folder->sync_status);
        }
    }

    public function test_sync_sets_failed_status_when_root_creation_fails(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create(['storage_type' => 'onedrive']);
        $case = $this->createCaseWithFolders($tenant);

        $this->mockProvider->shouldReceive('createFolder')
            ->once()
            ->andThrow(new \RuntimeException('API Error'));

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->syncService->syncFolderStructure($case);

        $case->refresh();
        $this->assertEquals('failed', $case->folder_sync_status);
    }

    public function test_get_sync_status_returns_correct_structure(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create(['storage_type' => 'onedrive']);
        $case = $this->createCaseWithFolders($tenant);
        $case->update([
            'folder_sync_status' => 'synced',
            'folder_synced_at' => now(),
            'root_external_folder_id' => 'root-id-123',
        ]);

        // Mark one folder as synced, one as failed
        $folders = DocumentFolder::withoutGlobalScopes()->where('case_id', $case->id)->get();
        if ($folders->count() >= 2) {
            $folders[0]->update(['sync_status' => 'synced', 'external_id' => 'ext-1', 'synced_at' => now()]);
            $folders[1]->update(['sync_status' => 'failed']);
        }

        // Act
        $status = $this->syncService->getSyncStatus($case);

        // Assert
        $this->assertArrayHasKey('case_sync_status', $status);
        $this->assertArrayHasKey('case_synced_at', $status);
        $this->assertArrayHasKey('root_external_folder_id', $status);
        $this->assertArrayHasKey('summary', $status);
        $this->assertArrayHasKey('folders', $status);

        $this->assertEquals('synced', $status['case_sync_status']);
        $this->assertEquals('root-id-123', $status['root_external_folder_id']);

        $this->assertArrayHasKey('total', $status['summary']);
        $this->assertArrayHasKey('synced', $status['summary']);
        $this->assertArrayHasKey('failed', $status['summary']);
        $this->assertArrayHasKey('pending', $status['summary']);

        $this->assertGreaterThanOrEqual(2, $status['summary']['total']);
        $this->assertGreaterThanOrEqual(1, $status['summary']['synced']);
        $this->assertGreaterThanOrEqual(1, $status['summary']['failed']);
    }

    public function test_get_sync_status_returns_not_applicable_for_null_status(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create(['storage_type' => 'local']);
        $case = $this->createCaseWithFolders($tenant);

        // Act
        $status = $this->syncService->getSyncStatus($case);

        // Assert
        $this->assertEquals('not_applicable', $status['case_sync_status']);
        $this->assertNull($status['case_synced_at']);
    }

    /**
     * Helper to create a case with document folders for testing.
     */
    private function createCaseWithFolders(Tenant $tenant, bool $synced = false): ImmigrationCase
    {
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($user);

        $client = \App\Models\Client::factory()->create(['tenant_id' => $tenant->id]);
        $case = ImmigrationCase::factory()->active()->create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
        ]);

        $folderData = [
            ['name' => 'Archivo', 'sort_order' => 0],
            ['name' => 'Cartas', 'sort_order' => 1],
            ['name' => 'Documentos', 'sort_order' => 2],
        ];

        foreach ($folderData as $data) {
            DocumentFolder::withoutGlobalScopes()->forceCreate([
                'tenant_id' => $tenant->id,
                'case_id' => $case->id,
                'parent_id' => null,
                'name' => $data['name'],
                'sort_order' => $data['sort_order'],
                'is_default' => true,
                'sync_status' => $synced ? 'synced' : 'pending',
                'external_id' => $synced ? 'ext-' . $data['name'] : null,
                'synced_at' => $synced ? now() : null,
            ]);
        }

        return $case;
    }
}
