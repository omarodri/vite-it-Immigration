<?php

namespace App\Observers;

use App\Models\TimeLog;
use Illuminate\Support\Facades\DB;

class TimeLogObserver
{
    public function created(TimeLog $log): void
    {
        $this->applyDelta((int) $log->case_id, (int) $log->duration_seconds);
    }

    public function updated(TimeLog $log): void
    {
        if ($log->wasChanged('duration_seconds')) {
            $delta = (int) $log->duration_seconds - (int) $log->getOriginal('duration_seconds');
            $this->applyDelta((int) $log->case_id, $delta);
        }

        if ($log->wasChanged('case_id')) {
            $this->applyDelta(
                (int) $log->getOriginal('case_id'),
                -((int) $log->getOriginal('duration_seconds'))
            );
            $this->applyDelta((int) $log->case_id, (int) $log->duration_seconds);
        }
    }

    public function deleted(TimeLog $log): void
    {
        $this->applyDelta((int) $log->case_id, -((int) $log->duration_seconds));
    }

    public function restored(TimeLog $log): void
    {
        $this->applyDelta((int) $log->case_id, (int) $log->duration_seconds);
    }

    private function applyDelta(int $caseId, int $delta): void
    {
        if ($delta === 0 || $caseId === 0) {
            return;
        }

        DB::statement(
            'UPDATE cases SET total_time_spent_seconds = GREATEST(0, CAST(total_time_spent_seconds AS SIGNED) + ?) WHERE id = ?',
            [$delta, $caseId]
        );
    }
}
