<?php

namespace App\Console\Commands;

use App\Models\BackupLog;
use App\Services\Backup\TenantBackupService;
use Illuminate\Console\Command;

class BackupCleanup extends Command
{
    protected $signature = 'backup:cleanup {--days=7 : Delete backups older than N days}';

    protected $description = 'Delete backup files and log entries older than the retention period';

    public function handle(TenantBackupService $service): int
    {
        $days  = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $logs = BackupLog::where('status', 'completed')
            ->where('completed_at', '<', $cutoff)
            ->get();

        if ($logs->isEmpty()) {
            $this->info('No old backups to clean up.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($logs as $log) {
            try {
                $service->deleteFile($log);
                $count++;
            } catch (\Throwable $e) {
                $this->warn("Could not delete backup [{$log->id}]: " . $e->getMessage());
            }
        }

        $this->info("Cleaned up {$count} backup(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
