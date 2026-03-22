<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    /**
     * Auto-populate tenant_id from the causer's tenant when creating a new log entry.
     */
    protected static function booted(): void
    {
        static::creating(function (self $activity) {
            if (! $activity->tenant_id && $activity->causer) {
                $activity->tenant_id = $activity->causer->tenant_id ?? null;
            }
        });
    }

    /**
     * Get the tenant that owns this activity log entry.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
