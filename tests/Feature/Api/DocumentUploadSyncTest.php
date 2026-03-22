<?php

namespace Tests\Feature\Api;

use App\Models\Client;
use App\Models\DocumentFolder;
use App\Models\ImmigrationCase;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadSyncTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private ImmigrationCase $case;
    private DocumentFolder $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::factory()->create(['storage_type' => 'local']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->assignRole('admin');

        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->case = ImmigrationCase::factory()->active()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
        ]);

        $this->folder = DocumentFolder::withoutGlobalScopes()->forceCreate([
            'tenant_id' => $this->tenant->id,
            'case_id' => $this->case->id,
            'parent_id' => null,
            'name' => 'Documentos',
            'sort_order' => 0,
            'is_default' => true,
            'sync_status' => 'synced',
            'external_id' => 'tenants/' . $this->tenant->id . '/cases/' . $this->case->id . '/Documentos',
        ]);
    }

    public function test_upload_document_works_with_local_storage(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('contract.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/cases/{$this->case->id}/documents", [
                'file' => $file,
                'folder_id' => $this->folder->id,
                'category' => 'contract',
            ]);

        // Should succeed (201) or accept the upload
        $this->assertTrue(
            in_array($response->status(), [200, 201]),
            "Expected 200 or 201, got {$response->status()}. Body: " . $response->getContent()
        );
    }

    public function test_folder_has_external_id_for_cloud_metadata(): void
    {
        // Verify the folder has metadata that would be used as parent reference for cloud uploads
        $this->assertNotNull($this->folder->external_id);
        $this->assertEquals('synced', $this->folder->sync_status);

        // The external_id can serve as parent_external_id in upload metadata
        $this->assertStringContains(
            'tenants/',
            $this->folder->external_id,
            'External ID for local storage should be a path starting with tenants/'
        );
    }

    public function test_upload_to_folder_without_external_id_still_works(): void
    {
        Storage::fake('local');

        // Create a folder without external_id (not yet synced)
        $unsyncedFolder = DocumentFolder::withoutGlobalScopes()->forceCreate([
            'tenant_id' => $this->tenant->id,
            'case_id' => $this->case->id,
            'parent_id' => null,
            'name' => 'Pendiente',
            'sort_order' => 1,
            'is_default' => false,
            'sync_status' => 'pending',
            'external_id' => null,
        ]);

        $file = UploadedFile::fake()->create('letter.pdf', 200, 'application/pdf');

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/cases/{$this->case->id}/documents", [
                'file' => $file,
                'folder_id' => $unsyncedFolder->id,
                'category' => 'other',
            ]);

        // Upload should still work even if folder is not synced with cloud
        $this->assertTrue(
            in_array($response->status(), [200, 201]),
            "Expected 200 or 201, got {$response->status()}. Body: " . $response->getContent()
        );
    }

    /**
     * Custom assertion for string contains (avoids PHPUnit deprecation).
     */
    private function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            $message ?: "Failed asserting that '{$haystack}' contains '{$needle}'."
        );
    }
}
