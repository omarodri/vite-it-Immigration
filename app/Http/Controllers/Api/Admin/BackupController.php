<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateTenantBackupJob;
use App\Models\BackupLog;
use App\Models\Tenant;
use App\Services\Backup\TenantBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    public function __construct(
        private readonly TenantBackupService $service,
    ) {}

    /**
     * POST /api/admin/tenants/{id}/backup
     * Dispatch backup job for a tenant (async).
     */
    public function run(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $log = \App\Models\BackupLog::create([
            'tenant_id'      => $tenant->id,
            'triggered_by'   => $request->user()?->id,
            'trigger_source' => 'ui',
            'status'         => 'pending',
            'started_at'     => now(),
        ]);

        GenerateTenantBackupJob::dispatch(
            $tenant->id,
            $request->user()?->id,
            'ui',
            $log->id
        );

        return response()->json([
            'message' => "Backup queued for tenant [{$tenant->slug}]. Check backup logs for status.",
            'log_id'  => $log->id,
        ], 202);
    }

    /**
     * POST /api/admin/backup/run  (CRON / external job endpoint)
     * Protected by X-Backup-Key middleware.
     * Accepts tenant_id or slug in request body.
     */
    public function runExternal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant' => ['required'],
        ]);

        $input  = $validated['tenant'];
        $tenant = is_numeric($input)
            ? Tenant::find((int) $input)
            : Tenant::where('slug', $input)->first();

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        GenerateTenantBackupJob::dispatch($tenant->id, null, 'cron');

        return response()->json([
            'message' => "Backup queued for tenant [{$tenant->slug}].",
        ], 202);
    }

    /**
     * GET /api/admin/tenants/{id}/backups
     * List backup logs for a tenant.
     */
    public function index(int $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $logs = BackupLog::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($log) => [
                'id'             => $log->id,
                'status'         => $log->status,
                'trigger_source' => $log->trigger_source,
                'filename'       => $log->filename,
                'file_size_mb'   => $log->file_size_mb,
                'row_count'      => $log->row_count,
                'duration_s'     => $log->duration_seconds,
                'error_message'  => $log->error_message,
                'started_at'     => $log->started_at?->toISOString(),
                'completed_at'   => $log->completed_at?->toISOString(),
                'created_at'     => $log->created_at->toISOString(),
            ]);

        return response()->json(['data' => $logs]);
    }

    /**
     * GET /api/admin/backups/{log}/download
     * Stream the backup file to the client.
     */
    public function download(int $logId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $log = BackupLog::findOrFail($logId);

        abort_if($log->status !== 'completed', 422, 'Backup is not in completed state.');
        abort_if(! $log->file_path, 422, 'Backup file path not recorded.');
        abort_if(! Storage::disk('backups')->exists($log->file_path), 404, 'Backup file not found on disk.');

        return response()->streamDownload(
            function () use ($log) {
                $stream = Storage::disk('backups')->readStream($log->file_path);
                fpassthru($stream);
                fclose($stream);
            },
            $log->filename,
            [
                'Content-Type'        => 'application/gzip',
                'Content-Disposition' => "attachment; filename=\"{$log->filename}\"",
            ]
        );
    }

    /**
     * DELETE /api/admin/backups/{log}
     * Delete a backup file and its log entry.
     */
    public function destroy(int $logId): JsonResponse
    {
        $log = BackupLog::findOrFail($logId);
        $this->service->deleteFile($log);

        return response()->json(['message' => 'Backup deleted successfully.']);
    }
}
