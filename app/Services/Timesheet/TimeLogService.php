<?php

namespace App\Services\Timesheet;

use App\Models\TimeLog;
use App\Models\Todo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimeLogService
{
    /**
     * Start a timer for the user on the given case.
     * Throws if the user already has a running timer.
     */
    public function startTimer(User $user, int $caseId, ?int $todoId = null): TimeLog
    {
        return DB::transaction(function () use ($user, $caseId, $todoId) {
            $existing = TimeLog::byUser($user->id)
                ->running()
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'timer' => __('timesheet.error_active_timer_exists'),
                ]);
            }

            $this->validateTodoBelongsToCase($caseId, $todoId);

            return TimeLog::create([
                'tenant_id'        => $user->tenant_id,
                'user_id'          => $user->id,
                'case_id'          => $caseId,
                'todo_id'          => $todoId,
                'work_date'        => now()->toDateString(),
                'started_at'       => now(),
                'ended_at'         => null,
                'duration_seconds' => 0,
                'source'           => 'timer',
            ]);
        });
    }

    /**
     * Stop a running timer; computes elapsed seconds and persists.
     */
    public function stopTimer(int $timeLogId, User $user): TimeLog
    {
        return DB::transaction(function () use ($timeLogId, $user) {
            $log = TimeLog::lockForUpdate()->findOrFail($timeLogId);

            abort_if($log->user_id !== $user->id, 403, "Cannot stop another user's timer.");
            abort_if(! $log->is_running, 422, 'Timer is not running.');

            $endedAt  = now();
            $duration = max(0, $endedAt->getTimestamp() - $log->started_at->getTimestamp());

            $log->update([
                'ended_at'         => $endedAt,
                'duration_seconds' => $duration,
            ]);

            return $log->fresh();
        });
    }

    /**
     * Create a manual time-log entry.
     */
    public function logManual(User $user, array $data): TimeLog
    {
        $todoId = isset($data['todo_id']) ? (int) $data['todo_id'] : null;
        $this->validateTodoBelongsToCase((int) $data['case_id'], $todoId);

        if (! empty($data['work_date']) && Carbon::parse($data['work_date'])->isFuture()) {
            throw ValidationException::withMessages([
                'work_date' => __('timesheet.error_future_date'),
            ]);
        }

        return TimeLog::create([
            'tenant_id'        => $user->tenant_id,
            'user_id'          => $user->id,
            'case_id'          => $data['case_id'],
            'todo_id'          => $todoId,
            'work_date'        => $data['work_date'] ?? now()->toDateString(),
            'duration_seconds' => $data['duration_seconds'],
            'description'      => $data['description'] ?? null,
            'source'           => 'manual',
        ]);
    }

    /**
     * Update an existing time log. Owners can edit their own; users with
     * `time_logs.edit_others` can edit anyone's.
     */
    public function updateLog(int $timeLogId, User $user, array $data): TimeLog
    {
        $log = TimeLog::findOrFail($timeLogId);

        abort_if(
            $log->user_id !== $user->id && ! $user->can('time_logs.edit_others'),
            403,
            'Cannot edit another user\'s time log.'
        );

        $payload = array_filter([
            'work_date'        => $data['work_date'] ?? null,
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'description'      => $data['description'] ?? null,
            'todo_id'          => array_key_exists('todo_id', $data) ? $data['todo_id'] : $log->todo_id,
        ], fn ($v) => $v !== null);

        $log->update($payload);

        return $log->fresh();
    }

    /**
     * Delete a time log (soft delete).
     */
    public function deleteLog(int $timeLogId, User $user): void
    {
        $log = TimeLog::findOrFail($timeLogId);

        abort_if(
            $log->user_id !== $user->id && ! $user->can('time_logs.edit_others'),
            403,
            'Cannot delete another user\'s time log.'
        );

        $log->delete();
    }

    /**
     * Returns the user's active running timer (if any).
     */
    public function getActiveTimerForUser(User $user): ?TimeLog
    {
        return TimeLog::byUser($user->id)
            ->running()
            ->with(['immigrationCase', 'todo'])
            ->first();
    }

    /**
     * Returns paginated time logs for a case, with optional filters.
     */
    public function getLogsForCase(int $caseId, array $filters = []): LengthAwarePaginator
    {
        $query = TimeLog::forCase($caseId)
            ->with(['user', 'todo'])
            ->orderByDesc('work_date')
            ->orderByDesc('created_at');

        if (! empty($filters['user_id'])) {
            $query->byUser((int) $filters['user_id']);
        }

        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $query->forDateRange($filters['date_from'], $filters['date_to']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    private function validateTodoBelongsToCase(int $caseId, ?int $todoId): void
    {
        if (! $todoId) return;

        $todo = Todo::find($todoId);
        if ($todo && $todo->case_id !== null && (int) $todo->case_id !== $caseId) {
            throw ValidationException::withMessages([
                'todo_id' => 'Todo does not belong to this case.',
            ]);
        }
    }
}
