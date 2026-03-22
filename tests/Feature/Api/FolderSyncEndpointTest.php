<?php

namespace Tests\Feature\Api;

use App\Jobs\SyncCaseFolderStructure;
use App\Models\Client;
use App\Models\DocumentFolder;
use App\Models\ImmigrationCase;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Document\FolderService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FolderSyncEndpointTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private ImmigrationCase $caseA;
    private ImmigrationCase $caseB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        // Tenant A (OneDrive)
        $this->tenantA = Tenant::factory()->create([
            'name' => 'Tenant A',
            'storage_type' => 'onedrive',
        ]);
        $this->userA = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->userA->assignRole('admin');

        $clientA = Client::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->caseA = ImmigrationCase::factory()->active()->create([
            'tenant_id' => $this->tenantA->id,
            'client_id' => $clientA->id,
            'folder_sync_status' => 'pending',
        ]);

        // Create default folders explicitly (CaseObserver no longer creates them)
        app(FolderService::class)->createDefaultStructure($this->caseA);

        // Mark them all as pending.
        DocumentFolder::withoutGlobalScopes()
            ->where('case_id', $this->caseA->id)
            ->update(['sync_status' => 'pending']);

        // Tenant B (Google Drive)
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'storage_type' => 'google_drive',
        ]);
        $this->userB = User::factory()->create(['tenant_id' => $this->tenantB->id]);
        $this->userB->assignRole('admin');

        $clientB = Client::factory()->create(['tenant_id' => $this->tenantB->id]);
        $this->caseB = ImmigrationCase::factory()->active()->create([
            'tenant_id' => $this->tenantB->id,
            'client_id' => $clientB->id,
            'folder_sync_status' => 'synced',
            'folder_synced_at' => now(),
            'root_external_folder_id' => 'gd-root-123',
        ]);

        // Create default folders explicitly (CaseObserver no longer creates them)
        app(FolderService::class)->createDefaultStructure($this->caseB);

        // Mark them all as synced for tenant B.
        $folderIndex = 0;
        DocumentFolder::withoutGlobalScopes()
            ->where('case_id', $this->caseB->id)
            ->get()
            ->each(function ($folder) use (&$folderIndex) {
                $folder->update([
                    'sync_status' => 'synced',
                    'external_id' => 'gd-ext-' . $folderIndex,
                    'synced_at' => now(),
                ]);
                $folderIndex++;
            });
    }

    // ---------------------------------------------------------------
    // POST /api/cases/{case}/folders/sync
    // ---------------------------------------------------------------

    public function test_can_trigger_folder_sync(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->userA, 'sanctum')
            ->postJson("/api/cases/{$this->caseA->id}/folders/sync");

        $response->assertStatus(202);
        $response->assertJson([
            'message' => 'Folder sync has been queued.',
            'folder_sync_status' => 'pending',
        ]);

        Queue::assertPushed(SyncCaseFolderStructure::class);
    }

    public function test_sync_returns_202_accepted(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->userA, 'sanctum')
            ->postJson("/api/cases/{$this->caseA->id}/folders/sync");

        $response->assertStatus(202);
    }

    public function test_sync_sets_status_to_pending(): void
    {
        Queue::fake();

        $this->actingAs($this->userA, 'sanctum')
            ->postJson("/api/cases/{$this->caseA->id}/folders/sync");

        $this->caseA->refresh();
        $this->assertEquals('pending', $this->caseA->folder_sync_status);
    }

    // ---------------------------------------------------------------
    // GET /api/cases/{case}/folders/sync-status
    // ---------------------------------------------------------------

    public function test_can_get_sync_status(): void
    {
        $response = $this->actingAs($this->userB, 'sanctum')
            ->getJson("/api/cases/{$this->caseB->id}/folders/sync-status");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'case_sync_status',
                'case_synced_at',
                'root_external_folder_id',
                'summary' => ['total', 'synced', 'failed', 'pending'],
                'folders',
            ],
        ]);
    }

    public function test_sync_status_returns_correct_data(): void
    {
        $response = $this->actingAs($this->userB, 'sanctum')
            ->getJson("/api/cases/{$this->caseB->id}/folders/sync-status");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertEquals('synced', $data['case_sync_status']);
        $this->assertEquals('gd-root-123', $data['root_external_folder_id']);

        // Default structure has 14 folders
        $this->assertEquals(14, $data['summary']['total']);
        $this->assertEquals(14, $data['summary']['synced']);
        $this->assertEquals(0, $data['summary']['failed']);
        $this->assertEquals(0, $data['summary']['pending']);
    }

    // ---------------------------------------------------------------
    // Authorization / Unauthenticated
    // ---------------------------------------------------------------

    public function test_unauthenticated_user_cannot_trigger_sync(): void
    {
        $response = $this->postJson("/api/cases/{$this->caseA->id}/folders/sync");

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_get_sync_status(): void
    {
        $response = $this->getJson("/api/cases/{$this->caseA->id}/folders/sync-status");

        $response->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // Multi-tenant isolation (6.6)
    // ---------------------------------------------------------------

    public function test_tenant_a_cannot_sync_tenant_b_folders(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->userA, 'sanctum')
            ->postJson("/api/cases/{$this->caseB->id}/folders/sync");

        // The TenantScope will hide the case, resulting in 404,
        // or the controller's validateCaseTenant will return 403.
        $this->assertTrue(
            in_array($response->status(), [403, 404]),
            "Expected 403 or 404, got {$response->status()}"
        );
    }

    public function test_tenant_a_cannot_view_tenant_b_sync_status(): void
    {
        $response = $this->actingAs($this->userA, 'sanctum')
            ->getJson("/api/cases/{$this->caseB->id}/folders/sync-status");

        $this->assertTrue(
            in_array($response->status(), [403, 404]),
            "Expected 403 or 404, got {$response->status()}"
        );
    }

    public function test_tenant_b_cannot_sync_tenant_a_folders(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->userB, 'sanctum')
            ->postJson("/api/cases/{$this->caseA->id}/folders/sync");

        $this->assertTrue(
            in_array($response->status(), [403, 404]),
            "Expected 403 or 404, got {$response->status()}"
        );
    }

    public function test_tenant_b_cannot_view_tenant_a_sync_status(): void
    {
        $response = $this->actingAs($this->userB, 'sanctum')
            ->getJson("/api/cases/{$this->caseA->id}/folders/sync-status");

        $this->assertTrue(
            in_array($response->status(), [403, 404]),
            "Expected 403 or 404, got {$response->status()}"
        );
    }
}
