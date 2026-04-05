<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'triggered_by',
        'trigger_source',
        'status',
        'filename',
        'file_path',
        'file_size_bytes',
        'row_count',
        'checksum',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'row_count'       => 'integer',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function getFileSizeMbAttribute(): float
    {
        return $this->file_size_bytes
            ? round($this->file_size_bytes / (1024 * 1024), 2)
            : 0.0;
    }

    public function getDurationSecondsAttribute(): ?int
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        return (int) $this->started_at->diffInSeconds($this->completed_at);
    }
}
