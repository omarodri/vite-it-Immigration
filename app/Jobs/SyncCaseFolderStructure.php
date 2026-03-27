<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DocumentFolder;
use App\Models\ImmigrationCase;
use App\Models\Tenant;
use App\Services\Document\CaseFolderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SyncCaseFolderStructure implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retrying (exponential backoff: 10s, 30s, 90s).
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 90];

    public function __construct(
        private readonly int $caseId
    ) {}

    public function handle(CaseFolderSyncService $syncService): void
    {
        $case = ImmigrationCase::find($this->caseId);

        if (!$case) {
            Log::warning('SyncCaseFolderStructure: Case not found.', [
                'case_id' => $this->caseId,
            ]);

            return;
        }

        $tenant = $case->tenant ?? Tenant::find($case->tenant_id);

        if (!$tenant) {
            Log::warning('SyncCaseFolderStructure: Tenant not found.', [
                'case_id' => $this->caseId,
                'tenant_id' => $case->tenant_id,
            ]);

            return;
        }

        $storageType = $tenant->storage_type ?? 'local';

        // For local storage, create physical directories and mark as synced
        if ($storageType === 'local' || !in_array($storageType, ['onedrive', 'google_drive', 'sharepoint'], true)) {
            $rootPath = "tenants/{$tenant->id}/cases/{$case->case_number}";

            // Create root case directory
            Storage::disk('local')->makeDirectory($rootPath);

            // Create subdirectory for each folder and update sync status
            $folders = DocumentFolder::byCase($case->id)->orderBy('sort_order')->get();

            foreach ($folders as $folder) {
                $folderPath = $rootPath . '/' . $folder->name;
                Storage::disk('local')->makeDirectory($folderPath);

                $folder->update([
                    'external_id' => $folderPath,
                    'sync_status' => 'synced',
                    'synced_at' => now(),
                ]);
            }

            $case->update([
                'root_external_folder_id' => $rootPath,
                'folder_sync_status' => 'synced',
                'folder_synced_at' => now(),
            ]);

            Log::info('SyncCaseFolderStructure: Local storage - created directories and marked as synced.', [
                'case_id' => $case->id,
                'root_path' => $rootPath,
                'folders_count' => $folders->count(),
            ]);

            return;
        }

        // Cloud storage: perform actual sync
        Log::info('SyncCaseFolderStructure: Starting cloud folder sync.', [
            'case_id' => $case->id,
            'storage_type' => $storageType,
        ]);

        $syncService->syncFolderStructure($case);

        Log::info('SyncCaseFolderStructure: Cloud folder sync completed.', [
            'case_id' => $case->id,
            'sync_status' => $case->fresh()->folder_sync_status,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SyncCaseFolderStructure: Job failed permanently.', [
            'case_id' => $this->caseId,
            'error' => $exception->getMessage(),
        ]);

        $case = ImmigrationCase::find($this->caseId);
        if ($case) {
            $case->update([
                'folder_sync_status' => 'failed',
            ]);
        }
    }
}
