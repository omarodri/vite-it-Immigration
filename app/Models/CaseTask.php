<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'label',
        'is_completed',
        'is_custom',
        'sort_order',
        'completed_at',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'is_custom' => 'boolean',
        'sort_order' => 'integer',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the case that owns this task.
     */
    public function immigrationCase(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class, 'case_id');
    }

    /**
     * Get the time logs associated with this task.
     */
    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class, 'case_task_id');
    }
}
