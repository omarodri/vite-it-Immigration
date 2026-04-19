<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CalendarSyncStatus;
use App\Services\Calendar\CalendarSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PullCalendarEventsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function handle(CalendarSyncService $syncService): void
    {
        $activeConnections = CalendarSyncStatus::where('status', 'active')
            ->with('user')
            ->get();

        foreach ($activeConnections as $syncStatus) {
            try {
                if ($syncStatus->user) {
                    $syncService->pullEvents($syncStatus->user);
                }
            } catch (\Exception $e) {
                Log::error('Pull failed for user', [
                    'user_id' => $syncStatus->user_id,
                    'provider' => $syncStatus->provider,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
