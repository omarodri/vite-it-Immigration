<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\DocumentStorageInterface;
use App\Models\Client;
use App\Models\DocumentFolder;
use App\Models\ImmigrationCase;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Document\CaseFolderSyncService;
use App\Services\Storage\StorageProviderFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BaseFolderResolutionTest extends TestCase
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

    // ---------------------------------------------------------------
    // resolveBaseFolderExternalId() - tested through syncFolderStructure()
    // ---------------------------------------------------------------

    public function test_sync_creates_case_folder_at_root_when_no_base_folder(): void
    {
        // Arrange: tenant has no base_folder_path
        $tenant = Tenant::factory()->create([
            'storage_type' => 'onedrive',
            'base_folder_path' => null,
            'base_folder_external_id' => null,
        ]);
        $case = $this->createCaseWithFolders($tenant);

        $createFolderCalls = [];

        $this->mockProvider->shouldReceive('createFolder')
            ->andReturnUsing(function (string $name, ?string $parentId = null) use (&$createFolderCalls) {
                $createFolderCalls[] = ['name' => $name, 'parent_id' => $parentId];
                return [
                    'external_id' => 'ext-' . $name,
                    'external_url' => 'https://example.com/' . $name,
                ];
            });

        // Act
        $this->syncService->syncFolderStructure($case);

        // Assert: the first call (root case folder) should have null parent
        $this->assertNotEmpty($createFolderCalls, 'createFolder should have been called');
        $rootCall = $createFolderCalls[0];
        $this->assertStringStartsWith('Case-', $rootCall['name']);
        $this->assertNull($rootCall['parent_id'], 'Root case folder should be created at drive root (null parent)');
    }

    public function test_sync_creates_case_folder_under_base_folder_when_configured(): void
    {
        // Arrange: tenant has base_folder_path AND base_folder_external_id already resolved
        $tenant = Tenant::factory()->create([
            'storage_type' => 'onedrive',
            'base_folder_path' => 'Immigration Files',
            'base_folder_external_id' => 'ext-bf-123',
        ]);
        $case = $this->createCaseWithFolders($tenant);

        $createFolderCalls = [];

        $this->mockProvider->shouldReceive('createFolder')
            ->andReturnUsing(function (string $name, ?string $parentId = null) use (&$createFolderCalls) {
                $createFolderCalls[] = ['name' => $name, 'parent_id' => $parentId];
                return [
                    'external_id' => 'ext-' . $name,
                    'external_url' => 'https://example.com/' . $name,
                ];
            });

        // Act
        $this->syncService->syncFolderStructure($case);

        // Assert: the first call (root case folder) should have 'ext-bf-123' as parent
        $this->assertNotEmpty($createFolderCalls, 'createFolder should have been called');
        $rootCall = $createFolderCalls[0];
        $this->assertStringStartsWith('Case-', $rootCall['name']);
        $this->assertEquals('ext-bf-123', $rootCall['parent_id'], 'Root case folder should be created under the base folder');
    }

    public function test_sync_creates_base_folder_lazily_when_no_external_id(): void
    {
        // Arrange: tenant has base_folder_path but NO base_folder_external_id
        $tenant = Tenant::factory()->create([
            'storage_type' => 'onedrive',
            'base_folder_path' => 'Immigration Files',
            'base_folder_external_id' => null,
        ]);
        $case = $this->createCaseWithFolders($tenant);

        $createFolderCalls = [];

        $this->mockProvider->shouldReceive('createFolder')
            ->andReturnUsing(function (string $name, ?string $parentId = null) use (&$createFolderCalls) {
                $createFolderCalls[] = ['name' => $name, 'parent_id' => $parentId];

                if ($name === 'Immigration Files') {
                    return [
                        'external_id' => 'new-bf-id',
                        'external_url' => 'https://example.com/immigration-files',
                    ];
                }

                return [
                    'external_id' => 'ext-' . $name,
                    'external_url' => 'https://example.com/' . $name,
                ];
            });

        // Act
        $this->syncService->syncFolderStructure($case);

        // Assert step (a): first call should create the base folder at root
        $this->assertGreaterThanOrEqual(2, count($createFolderCalls), 'Should call createFolder at least twice');
        $baseFolderCall = $createFolderCalls[0];
        $this->assertEquals('Immigration Files', $baseFolderCall['name']);
        $this->assertNull($baseFolderCall['parent_id'], 'Base folder should be created at drive root');

        // Assert step (b): second call should create the case folder under the new base folder
        $caseFolderCall = $createFolderCalls[1];
        $this->assertStringStartsWith('Case-', $caseFolderCall['name']);
        $this->assertEquals('new-bf-id', $caseFolderCall['parent_id'], 'Case folder should be created under the newly created base folder');

        // Assert step (c): tenant should be updated with the base_folder_external_id
        $tenant->refresh();
        $this->assertEquals('new-bf-id', $tenant->base_folder_external_id, 'Tenant base_folder_external_id should be persisted');
    }

    public function test_sync_uses_cached_base_folder_external_id(): void
    {
        // Arrange: tenant has both base_folder_path and base_folder_external_id
        $tenant = Tenant::factory()->create([
            'storage_type' => 'onedrive',
            'base_folder_path' => 'Immigration Files',
            'base_folder_external_id' => 'cached-bf-id',
        ]);
        $case = $this->createCaseWithFolders($tenant);

        $createFolderCalls = [];

        $this->mockProvider->shouldReceive('createFolder')
            ->andReturnUsing(function (string $name, ?string $parentId = null) use (&$createFolderCalls) {
                $createFolderCalls[] = ['name' => $name, 'parent_id' => $parentId];
                return [
                    'external_id' => 'ext-' . $name,
                    'external_url' => 'https://example.com/' . $name,
                ];
            });

        // Act
        $this->syncService->syncFolderStructure($case);

        // Assert: createFolder should NOT be called for 'Immigration Files'
        $baseFolderCalls = array_filter($createFolderCalls, fn ($call) => $call['name'] === 'Immigration Files');
        $this->assertEmpty($baseFolderCalls, 'Provider should NOT create base folder when external_id is already cached');

        // The first call should be the case folder, with cached-bf-id as parent
        $this->assertNotEmpty($createFolderCalls, 'createFolder should have been called for the case folder');
        $rootCall = $createFolderCalls[0];
        $this->assertStringStartsWith('Case-', $rootCall['name']);
        $this->assertEquals('cached-bf-id', $rootCall['parent_id'], 'Case folder should use cached base folder external ID');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Helper to create a case with document folders for testing.
     */
    private function createCaseWithFolders(Tenant $tenant): ImmigrationCase
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($user);

        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
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
                'sync_status' => 'pending',
                'external_id' => null,
                'synced_at' => null,
            ]);
        }

        return $case;
    }
}
