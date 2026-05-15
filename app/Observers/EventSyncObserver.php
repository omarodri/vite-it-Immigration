<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\SyncEventToCalendarJob;
use App\Models\Event;
use App\Services\Calendar\CalendarSyncService;

class EventSyncObserver
{
    public function created(Event $event): void
    {
        if ($this->shouldSync($event)) {
            SyncEventToCalendarJob::dispatch($event, 'create');
        }
    }

    public function updated(Event $event): void
    {
        if ($this->shouldSync($event)) {
            SyncEventToCalendarJob::dispatch($event, 'update');
        }
    }

    public function deleted(Event $event): void
    {
        if ($event->external_id && $this->shouldSync($event)) {
            SyncEventToCalendarJob::dispatch($event, 'delete');
        }
    }

    private function shouldSync(Event $event): bool
    {
        if (CalendarSyncService::$isPulling) {
            return false;
        }

        // Block re-pushing events imported from external providers (R7 anti-loop multi-provider).
        // Events with sync_source 'google' or 'outlook' are already in the source system.
        if (in_array($event->sync_source, ['google', 'outlook'], true)) {
            return false;
        }

        return true;
    }
}
