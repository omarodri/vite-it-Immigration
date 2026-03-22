<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'code',
        'email',
        'uses_remaining',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'uses_remaining' => 'integer',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the tenant this invitation code belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user who created this invitation code.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if the invitation code is valid, optionally for a specific email.
     */
    public function isValid(?string $email = null): bool
    {
        // Check uses remaining
        if ($this->uses_remaining <= 0) {
            return false;
        }

        // Check expiration
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        // Check email restriction
        if ($this->email !== null && $email !== null && strtolower($this->email) !== strtolower($email)) {
            return false;
        }

        // If the code has an email restriction but no email was provided for validation
        if ($this->email !== null && $email === null) {
            return false;
        }

        return true;
    }

    /**
     * Redeem the invitation code by decrementing uses_remaining.
     */
    public function redeem(): void
    {
        $this->decrement('uses_remaining');
    }
}
