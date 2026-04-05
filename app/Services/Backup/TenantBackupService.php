<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Models\BackupLog;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TenantBackupService
{
    /**
     * Tables that have a direct tenant_id column.
     */
    private const TENANT_TABLES = [
        'users',
        'clients',
        'companions',
        'legal_documents',
        'case_types',
        'cases',
        'document_folders',
        'documents',
        'tasks',
        'follow_ups',
        'events',
        'oauth_tokens',
        'task_time_entries',
        'invitation_codes',
        'todos',
        'scrum_columns',
        'scrum_tasks',
        'user_case_history',
    ];

    /**
     * Pivot tables without tenant_id — resolved via JOIN.
     * Format: table => [join_table, local_key, join_condition]
     */
    private const PIVOT_TABLES = [
        'case_companions'      => ['cases', 'case_id', 'cases.id'],
        'case_important_dates' => ['cases', 'case_id', 'cases.id'],
        'case_tasks'           => ['cases', 'case_id', 'cases.id'],
        'case_invoices'        => ['cases', 'case_id', 'cases.id'],
        'event_participants'   => ['events', 'event_id', 'events.id'],
        'user_profiles'        => ['users', 'user_id', 'users.id'],
    ];

    /**
     * Generate a tenant backup and persist a BackupLog record.
     */
    public function generate(Tenant $tenant, ?int $triggeredBy = null, string $triggerSource = 'ui', ?BackupLog $existingLog = null): BackupLog
    {
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '512M');

        if ($existingLog) {
            $existingLog->update(['status' => 'running', 'started_at' => now()]);
            $log = $existingLog;
        } else {
            $log = BackupLog::create([
                'tenant_id'      => $tenant->id,
                'triggered_by'   => $triggeredBy,
                'trigger_source' => $triggerSource,
                'status'         => 'running',
                'started_at'     => now(),
            ]);
        }

        try {
            [$sql, $rowCount] = $this->buildSql($tenant);

            $compressed = gzencode($sql, 6);
            if ($compressed === false) {
                throw new \RuntimeException('gzencode compression failed.');
            }

            $filename = $this->buildFilename($tenant);
            $path     = "{$tenant->slug}/{$filename}";

            Storage::disk('backups')->put($path, $compressed);

            $checksum = hash('sha256', $compressed);

            $log->update([
                'status'          => 'completed',
                'filename'        => $filename,
                'file_path'       => $path,
                'file_size_bytes' => strlen($compressed),
                'row_count'       => $rowCount,
                'checksum'        => $checksum,
                'completed_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
            throw $e;
        }

        return $log->fresh();
    }

    /**
     * Verify integrity of a stored backup file.
     */
    public function verifyIntegrity(BackupLog $log): bool
    {
        if (! $log->file_path || ! $log->checksum) {
            return false;
        }

        if (! Storage::disk('backups')->exists($log->file_path)) {
            return false;
        }

        $content  = Storage::disk('backups')->get($log->file_path);
        $checksum = hash('sha256', $content);

        return hash_equals($log->checksum, $checksum);
    }

    /**
     * Delete backup file and mark log as cleaned up.
     */
    public function deleteFile(BackupLog $log): void
    {
        if ($log->file_path && Storage::disk('backups')->exists($log->file_path)) {
            Storage::disk('backups')->delete($log->file_path);
        }

        $log->delete();
    }

    // -- Private helpers ----------------------------------------------------

    private function buildFilename(Tenant $tenant): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $tenant->name);
        $date = now()->format('Y-m-d_Hi');

        return "{$tenant->slug}_{$name}_{$date}.sql.gz";
    }

    /**
     * Build the complete SQL dump string.
     *
     * @return array{0: string, 1: int}  [sql, total_rows]
     */
    private function buildSql(Tenant $tenant): array
    {
        $tenantId  = $tenant->id;
        $tenantName = addslashes($tenant->name);
        $lines     = [];
        $totalRows = 0;

        $lines[] = "-- VITE-IT Immigration Backup";
        $lines[] = "-- Tenant: {$tenantName} ({$tenant->slug})";
        $lines[] = "-- Generated: " . now()->toDateTimeString() . " UTC";
        $lines[] = "-- -------------------------------------------------------";
        $lines[] = "";
        $lines[] = "SET FOREIGN_KEY_CHECKS=0;";
        $lines[] = "SET NAMES utf8mb4;";
        $lines[] = "SET time_zone='+00:00';";
        $lines[] = "";

        // Tenant-scoped tables
        foreach (self::TENANT_TABLES as $table) {
            [$tableLines, $rows] = $this->dumpTenantTable($table, 'tenant_id', $tenantId);
            $lines    = array_merge($lines, $tableLines);
            $totalRows += $rows;
        }

        // Pivot tables
        foreach (self::PIVOT_TABLES as $table => [$joinTable, $localKey, $joinOn]) {
            [$tableLines, $rows] = $this->dumpPivotTable($table, $localKey, $joinTable, $joinOn, $tenantId);
            $lines    = array_merge($lines, $tableLines);
            $totalRows += $rows;
        }

        // Activity log (nullable tenant_id)
        [$tableLines, $rows] = $this->dumpTenantTable('activity_log', 'tenant_id', $tenantId);
        $lines    = array_merge($lines, $tableLines);
        $totalRows += $rows;

        $lines[] = "";
        $lines[] = "SET FOREIGN_KEY_CHECKS=1;";
        $lines[] = "";
        $lines[] = "-- End of backup. Total rows exported: {$totalRows}";

        return [implode("\n", $lines), $totalRows];
    }

    /**
     * Dump a table filtered by a direct tenant_id column.
     *
     * @return array{0: string[], 1: int}
     */
    private function dumpTenantTable(string $table, string $tenantCol, int $tenantId): array
    {
        $lines = [];
        $rows  = 0;

        // Check table exists
        if (! $this->tableExists($table)) {
            return [$lines, 0];
        }

        $lines[] = "";
        $lines[] = "-- Table: `{$table}`";
        $lines[] = $this->getCreateTableSql($table);
        $lines[] = "";

        DB::table($table)
            ->where($tenantCol, $tenantId)
            ->orderBy('id')
            ->chunk(500, function ($chunkRows) use ($table, &$lines, &$rows) {
                if ($chunkRows->isEmpty()) {
                    return;
                }

                $inserts = $this->buildInsertStatements($table, $chunkRows->toArray());
                $lines   = array_merge($lines, $inserts);
                $rows   += $chunkRows->count();
            });

        return [$lines, $rows];
    }

    /**
     * Dump a pivot table via JOIN to get only tenant rows.
     *
     * @return array{0: string[], 1: int}
     */
    private function dumpPivotTable(string $table, string $localKey, string $joinTable, string $joinOn, int $tenantId): array
    {
        $lines = [];
        $rows  = 0;

        if (! $this->tableExists($table) || ! $this->tableExists($joinTable)) {
            return [$lines, 0];
        }

        $lines[] = "";
        $lines[] = "-- Table: `{$table}` (via {$joinTable})";
        $lines[] = $this->getCreateTableSql($table);
        $lines[] = "";

        DB::table($table)
            ->join($joinTable, "{$table}.{$localKey}", '=', $joinOn)
            ->where("{$joinTable}.tenant_id", $tenantId)
            ->select("{$table}.*")
            ->orderBy("{$table}.id")
            ->chunk(500, function ($chunkRows) use ($table, &$lines, &$rows) {
                if ($chunkRows->isEmpty()) {
                    return;
                }

                $inserts = $this->buildInsertStatements($table, $chunkRows->toArray());
                $lines   = array_merge($lines, $inserts);
                $rows   += $chunkRows->count();
            });

        return [$lines, $rows];
    }

    private function getCreateTableSql(string $table): string
    {
        $result = DB::select("SHOW CREATE TABLE `{$table}`");
        $create = $result[0]->{'Create Table'};

        // Convert CREATE TABLE to CREATE TABLE IF NOT EXISTS
        return str_replace('CREATE TABLE `', 'CREATE TABLE IF NOT EXISTS `', $create) . ';';
    }

    /**
     * Build INSERT statements for a chunk of rows.
     *
     * @return string[]
     */
    private function buildInsertStatements(string $table, array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $lines   = [];
        $columns = array_keys((array) $rows[0]);
        $colList = implode('`, `', $columns);

        $valueGroups = [];
        foreach ($rows as $row) {
            $rowArray = (array) $row;
            $values   = array_map(fn ($v) => $this->escapeValue($v), $rowArray);
            $valueGroups[] = '(' . implode(', ', $values) . ')';
        }

        $lines[] = "INSERT INTO `{$table}` (`{$colList}`) VALUES";
        $chunks  = array_chunk($valueGroups, 100);
        foreach ($chunks as $chunk) {
            $lines[] = implode(",\n", $chunk) . ';';
        }

        return $lines;
    }

    private function escapeValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'" . addslashes((string) $value) . "'";
    }

    private function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }
}
