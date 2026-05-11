<?php

namespace App\Listeners;

use App\Events\SessionRevoked;
use App\Models\TimeLog;
use App\Services\Timesheet\TimeLogService;

class StopActiveTimerOnSessionRevoked
{
    public function handle(SessionRevoked $event): void
    {
        $activeTimer = TimeLog::where('user_id', $event->victim->id)
            ->whereNull('ended_at')
            ->first();

        if ($activeTimer) {
            try {
                app(TimeLogService::class)->stopTimer(
                    $activeTimer->id,
                    $event->victim,
                    reason: 'session_revoked'
                );
            } catch (\Throwable $e) {
                logger()->warning(
                    "SessionRevoked: could not stop timer {$activeTimer->id} " .
                    "for user {$event->victim->id}: {$e->getMessage()}"
                );
            }
        }
    }
}
