<?php

namespace App\Jobs;

use App\Models\BackupLog;
use App\Models\Tenant;
use App\Services\Backup\TenantBackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateTenantBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;  // 1 hour max
    public int $tries   = 1;     // No retry on backup failure

    public function __construct(
        private readonly int $tenantId,
        private readonly ?int $triggeredBy,
        private readonly string $triggerSource,
        private readonly ?int $logId = null,
    ) {}

    public function handle(TenantBackupService $service): void
    {
        $tenant     = Tenant::findOrFail($this->tenantId);
        $existingLog = $this->logId ? BackupLog::find($this->logId) : null;

        try {
            $log = $service->generate($tenant, $this->triggeredBy, $this->triggerSource, $existingLog);
            Log::info("Backup completed for tenant [{$tenant->slug}]", [
                'log_id'    => $log->id,
                'filename'  => $log->filename,
                'size_mb'   => $log->file_size_mb,
                'rows'      => $log->row_count,
                'duration'  => $log->duration_seconds . 's',
            ]);
        } catch (\Throwable $e) {
            Log::error("Backup failed for tenant [{$tenant->slug}]: " . $e->getMessage());
            throw $e;
        }
    }
}
