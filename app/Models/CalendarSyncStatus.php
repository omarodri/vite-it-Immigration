<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarSyncStatus extends Model
{
    use BelongsToTenant;

    protected $table = 'calendar_sync_status';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'provider',
        'last_pull_at',
        'last_push_at',
        'sync_token',
        'status',
        'last_error',
        'error_count',
    ];

    protected $casts = [
        'last_pull_at' => 'datetime',
        'last_push_at' => 'datetime',
        'error_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
