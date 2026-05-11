<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeLog extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'case_id',
        'case_task_id',
        'todo_id',
        'work_date',
        'duration_seconds',
        'description',
        'started_at',
        'ended_at',
        'stop_reason',
        'source',
    ];

    protected $casts = [
        'work_date'        => 'date',
        'started_at'       => 'datetime',
        'ended_at'         => 'datetime',
        'duration_seconds' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function immigrationCase(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class, 'case_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'case_task_id');
    }

    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }

    /**
     * Accessor: human-friendly duration ("1h 30m" or "45m").
     */
    public function getDurationFormattedAttribute(): string
    {
        $seconds = $this->is_running ? $this->elapsed_seconds : $this->duration_seconds;

        return self::formatSeconds((int) $seconds);
    }

    /**
     * Accessor: timer is currently running.
     */
    public function getIsRunningAttribute(): bool
    {
        return $this->started_at !== null && $this->ended_at === null;
    }

    /**
     * Accessor: elapsed seconds (live for active timers, stored for finished ones).
     */
    public function getElapsedSecondsAttribute(): int
    {
        if (! $this->is_running) {
            return (int) $this->duration_seconds;
        }

        return max(0, now()->getTimestamp() - $this->started_at->getTimestamp());
    }

    /**
     * Format an integer number of seconds as "Xh YYm" or "Ym".
     */
    public static function formatSeconds(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        return $h > 0
            ? "{$h}h " . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . 'm'
            : "{$m}m";
    }

    public function scopeForCase(Builder $query, int $caseId): Builder
    {
        return $query->where('case_id', $caseId);
    }

    public function scopeForDateRange(Builder $query, $start, $end): Builder
    {
        return $query->whereBetween('work_date', [$start, $end]);
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->whereNotNull('started_at')->whereNull('ended_at');
    }
}
