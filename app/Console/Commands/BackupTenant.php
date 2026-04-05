<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Backup\TenantBackupService;
use Illuminate\Console\Command;

class BackupTenant extends Command
{
    protected $signature = 'tenant:backup
                            {tenant : Tenant ID or slug}
                            {--source=cli : Trigger source label for audit log}';

    protected $description = 'Generate a compressed SQL backup (.sql.gz) for a tenant';

    public function handle(TenantBackupService $service): int
    {
        $input  = $this->argument('tenant');
        $tenant = is_numeric($input)
            ? Tenant::find((int) $input)
            : Tenant::where('slug', $input)->first();

        if (! $tenant) {
            $this->error("Tenant not found: {$input}");
            return self::FAILURE;
        }

        $this->info("Starting backup for tenant: [{$tenant->slug}] {$tenant->name}");

        try {
            $log = $service->generate($tenant, null, $this->option('source'));

            $this->info("Backup completed.");
            $this->table(
                ['Field', 'Value'],
                [
                    ['Filename',  $log->filename],
                    ['Size',      $log->file_size_mb . ' MB'],
                    ['Rows',      number_format($log->row_count)],
                    ['Duration',  $log->duration_seconds . 's'],
                    ['Checksum',  substr($log->checksum, 0, 16) . '...'],
                ]
            );
        } catch (\Throwable $e) {
            $this->error("Backup failed: " . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
