<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionRevocation extends Model
{
    protected $fillable = [
        'victim_user_id',
        'revoking_ip',
        'revoking_user_agent',
        'stop_reason',
    ];

    public function victim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'victim_user_id');
    }
}
