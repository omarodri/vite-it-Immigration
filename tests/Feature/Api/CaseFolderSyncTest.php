<?php

namespace Tests\Feature\Api;

use App\Jobs\SyncCaseFolderStructure;
use App\Models\CaseType;
use App\Models\Client;
use App\Models\DocumentFolder;
use App\Models\ImmigrationCase;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CaseFolderSyncTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Client $client;
    private CaseType $caseType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::factory()->create(['storage_type' => 'local']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('admin');
        $this->client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->caseType = CaseType::factory()->create();
    }

    public function test_creating_case_creates_default_folders(): void
    {
        // Arrange
        Queue::fake();

        // Act
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cases', [
                'client_id' => $this->client->id,
                'case_type_id' => $this->caseType->id,
            ]);

        // Assert
        $response->assertStatus(201);

        $caseId = $response->json('data.id');
        $this->assertNotNull($caseId);

        $folders = DocumentFolder::withoutGlobalScopes()
            ->where('case_id', $caseId)
            ->get();

        // The default structure has 14 folders
        $this->assertGreaterThanOrEqual(10, $folders->count(), 'Default folder structure should have at least 10 folders');

        $folderNames = $folders->pluck('name')->toArray();
        $this->assertContains('Archivo', $folderNames);
        $this->assertContains('Documentos', $folderNames);
        $this->assertContains('Cartas', $folderNames);

        // All folders should be default
        foreach ($folders as $folder) {
            $this->assertTrue($folder->is_default, "Folder '{$folder->name}' should be marked as default");
        }
    }

    public function test_creating_local_case_does_not_dispatch_sync_job(): void
    {
        // Arrange - local tenant (the default in setUp)
        Queue::fake();

        // Act
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cases', [
                'client_id' => $this->client->id,
                'case_type_id' => $this->caseType->id,
            ]);

        // Assert - local storage creates folders synchronously, no job needed
        $response->assertStatus(201);
        Queue::assertNotPushed(SyncCaseFolderStructure::class);
    }

    public function test_creating_case_syncs_folders_synchronously_for_cloud_tenant(): void
    {
        // Arrange — cloud sync runs synchronously now (no queue dependency)
        $cloudTenant = Tenant::factory()->create(['storage_type' => 'onedrive']);
        $cloudUser = User::factory()->create(['tenant_id' => $cloudTenant->id]);
        $cloudUser->assignRole('admin');
        $cloudClient = Client::factory()->create(['tenant_id' => $cloudTenant->id]);

        // Act
        $response = $this->actingAs($cloudUser, 'sanctum')
            ->postJson('/api/cases', [
                'client_id' => $cloudClient->id,
                'case_type_id' => $this->caseType->id,
            ]);

        // Assert — case is created, folders exist in DB
        $response->assertStatus(201);
        $caseId = $response->json('data.id');
        $this->assertGreaterThan(0, \App\Models\DocumentFolder::where('case_id', $caseId)->count());
    }

    public function test_creating_local_case_creates_physical_directories(): void
    {
        // Arrange
        Queue::fake();
        \Illuminate\Support\Facades\Storage::fake('local');

        // Act
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/cases', [
                'client_id' => $this->client->id,
                'case_type_id' => $this->caseType->id,
            ]);

        // Assert - local storage creates directories synchronously
        $response->assertStatus(201);

        $caseId = $response->json('data.id');
        $case = ImmigrationCase::find($caseId);

        $this->assertEquals('synced', $case->folder_sync_status);
        $this->assertNotNull($case->root_external_folder_id);

        // Verify physical root directory was created
        \Illuminate\Support\Facades\Storage::disk('local')
            ->assertExists("tenants/{$this->tenant->id}/cases/{$case->case_number}");
    }

    public function test_sync_job_marks_local_folders_as_synced(): void
    {
        // Arrange
        $case = ImmigrationCase::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
        ]);

        // Create some folders
        foreach (['Archivo', 'Cartas', 'Documentos'] as $i => $name) {
            DocumentFolder::withoutGlobalScopes()->forceCreate([
                'tenant_id' => $this->tenant->id,
                'case_id' => $case->id,
                'parent_id' => null,
                'name' => $name,
                'sort_order' => $i,
                'is_default' => true,
                'sync_status' => 'pending',
            ]);
        }

        // Act - execute the job synchronously
        $job = new SyncCaseFolderStructure($case->id);
        $job->handle(app(\App\Services\Document\CaseFolderSyncService::class));

        // Assert
        $case->refresh();
        $this->assertEquals('synced', $case->folder_sync_status);
        $this->assertNotNull($case->folder_synced_at);

        $folders = DocumentFolder::withoutGlobalScopes()
            ->where('case_id', $case->id)
            ->get();

        foreach ($folders as $folder) {
            $this->assertEquals('synced', $folder->sync_status, "Folder '{$folder->name}' should be synced");
            $this->assertNotNull($folder->synced_at, "Folder '{$folder->name}' should have synced_at");
        }
    }
}
