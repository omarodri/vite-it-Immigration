<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LegalDocument extends Model
{
    use BelongsToTenant;

    /**
     * Allowed document types.
     */
    public const DOCUMENT_TYPES = [
        'passport',
        'work_permit',
        'study_permit',
        'visitor_record',
        'pr_card',
        'caq',
        'open_work_permit',
        'eta',
        'other',
    ];

    protected $fillable = [
        'tenant_id',
        'documentable_type',
        'documentable_id',
        'document_type',
        'document_name',
        'document_number',
        'issuing_country',
        'issue_date',
        'expiry_date',
        'alert_days_before',
        'alert_active',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'issue_date'        => 'date',
        'expiry_date'       => 'date',
        'alert_active'      => 'boolean',
        'alert_days_before' => 'integer',
        'sort_order'        => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────

    /**
     * Get the owning documentable model (Client or Companion).
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    /**
     * Documents expiring soon (within their own alert_days_before window).
     */
    public function scopeExpiringSoon(Builder $query): Builder
    {
        return $query
            ->where('alert_active', true)
            ->whereNotNull('expiry_date')
            ->whereRaw('expiry_date <= DATE_ADD(CURDATE(), INTERVAL alert_days_before DAY)');
    }

    /**
     * Documents that are already overdue (expiry_date < today).
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->startOfDay());
    }

    /**
     * Documents with alerts enabled.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('alert_active', true);
    }

    // ── Accessors (Laravel 9+ Attribute syntax) ────────────────────────

    /**
     * Number of days remaining until expiry. Negative if already expired.
     */
    protected function daysRemaining(): Attribute
    {
        return Attribute::get(function (): ?int {
            if (! $this->expiry_date) {
                return null;
            }

            return (int) now()->startOfDay()->diffInDays($this->expiry_date, false);
        });
    }

    /**
     * Alert status: 'overdue' | 'critical' (<=60 days) | 'warning' (<=90) | 'ok' | 'none'.
     */
    protected function alertStatus(): Attribute
    {
        return Attribute::get(function (): string {
            if (! $this->expiry_date) {
                return 'none';
            }

            $daysRemaining = $this->days_remaining;

            if ($daysRemaining < 0) {
                return 'overdue';
            }

            if ($daysRemaining <= 60) {
                return 'critical';
            }

            if ($daysRemaining <= 90) {
                return 'warning';
            }

            return 'ok';
        });
    }

    /**
     * Human-readable display name for the document type.
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->document_type === 'other' && $this->document_name) {
                return $this->document_name;
            }

            return ucwords(str_replace('_', ' ', $this->document_type));
        });
    }
}
