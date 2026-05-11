<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class AutoStopExpiredTimersJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable;

    public function uniqueId(): string
    {
        return 'auto-stop-expired-timers';
    }

    public function handle(): void
    {
        $defaultMinutes = (int) config('session.lifetime', 120);

        Tenant::query()->chunkById(100, function ($tenants) use ($defaultMinutes) {
            foreach ($tenants as $tenant) {
                $maxMinutes = (int) data_get($tenant->settings, 'max_timer_duration', $defaultMinutes);

                $count = DB::table('time_logs')
                    ->where('tenant_id', $tenant->id)
                    ->whereNull('ended_at')
                    ->whereNotNull('started_at')
                    ->whereNull('deleted_at')
                    ->where('started_at', '<', now()->subMinutes($maxMinutes))
                    ->update([
                        'ended_at'         => now(),
                        'duration_seconds' => DB::raw('TIMESTAMPDIFF(SECOND, started_at, NOW())'),
                        'stop_reason'      => 'auto_expired',
                        'updated_at'       => now(),
                    ]);

                if ($count > 0) {
                    logger()->info("AutoStopExpiredTimers: stopped {$count} timer(s) for tenant {$tenant->id}");
                }
            }
        });
    }
}
