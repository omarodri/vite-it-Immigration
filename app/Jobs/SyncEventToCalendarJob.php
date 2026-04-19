<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Event;
use App\Models\User;
use App\Services\Calendar\CalendarSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncEventToCalendarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly Event $event,
        public readonly string $action,
    ) {}

    public function handle(CalendarSyncService $syncService): void
    {
        $user = $this->event->assignedTo ?? User::find($this->event->created_by);
        if (!$user) return;

        match ($this->action) {
            'create', 'update' => $syncService->pushEvent($this->event, $user),
            'delete' => $syncService->deleteExternalEvent(
                $this->event->external_id,
                $user
            ),
        };
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncEventToCalendarJob failed permanently', [
            'event_id' => $this->event->id,
            'action' => $this->action,
            'error' => $exception->getMessage(),
        ]);
    }
}
