<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Client extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    public const TYPE_PERSON  = 'person';
    public const TYPE_COMPANY = 'company';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'promoted_from_companion_id',
        // STI discriminator
        'type',
        // Company Information
        'company_name',
        'trade_name',
        'tax_id',
        'industry',
        'website',
        'legal_rep_name',
        'legal_rep_title',
        // Personal Information
        'first_name',
        'last_name',
        'nationality',
        'second_nationality',
        'language',
        'second_language',
        'date_of_birth',
        'gender',
        'marital_status',
        'profession',
        'description',
        // Contact Information
        'email',
        'residential_address',
        'mailing_address',
        'city',
        'province',
        'postal_code',
        'country',
        'phone',
        'phone_country_code',
        'secondary_phone',
        'secondary_phone_country_code',
        // Legal Status in Canada
        'canada_status',
        'status_date',
        'arrival_date',
        'entry_point',
        'iuc',
        'other_status_1',
        'other_status_2',
        // Status
        'status',
    ];

    protected $appends = ['full_name', 'sort_name', 'initials'];

    protected $casts = [
        'date_of_birth' => 'date',
        'status_date' => 'date',
        'arrival_date' => 'date',
    ];

    /**
     * Get the user account associated with this client.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The companion this client was promoted from (if applicable).
     */
    public function originatedFromCompanion(): BelongsTo
    {
        return $this->belongsTo(Companion::class, 'promoted_from_companion_id');
    }

    /**
     * Get the companions for this client.
     */
    public function companions(): HasMany
    {
        return $this->hasMany(Companion::class);
    }

    /**
     * Get the cases for this client.
     */
    public function cases(): HasMany
    {
        return $this->hasMany(ImmigrationCase::class);
    }

    /**
     * Get the legal documents for this client.
     */
    public function legalDocuments(): MorphMany
    {
        return $this->morphMany(LegalDocument::class, 'documentable')
                    ->orderBy('sort_order');
    }

    // Note: FollowUp model will be implemented in future epics
    // /**
    //  * Get the follow-ups for this client.
    //  */
    // public function followUps(): HasMany
    // {
    //     return $this->hasMany(FollowUp::class);
    // }

    /**
     * Get the client's full name, respecting the tenant's name format preference.
     *
     * For company-type clients, returns the trade name when set, otherwise the
     * legal company name. The persona branch keeps its existing behaviour.
     */
    public function getFullNameAttribute(): string
    {
        if ($this->type === self::TYPE_COMPANY) {
            return $this->trade_name ?? $this->company_name ?? '';
        }

        $format = $this->getNameFormat();

        return $format === 'last_first'
            ? "{$this->last_name}, {$this->first_name}"
            : "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get the client's name in "Last, First" format (always, for sorting).
     *
     * For company-type clients, sorts by the legal name (company_name) to keep
     * an "official register" style ordering, falling back to trade_name.
     */
    public function getSortNameAttribute(): string
    {
        if ($this->type === self::TYPE_COMPANY) {
            return $this->company_name ?? $this->trade_name ?? '';
        }

        return "{$this->last_name}, {$this->first_name}";
    }

    /**
     * Resolve the tenant's name format preference (cached).
     */
    protected function getNameFormat(): string
    {
        return Cache::remember(
            "tenant:{$this->tenant_id}:name_format",
            300,
            fn () => optional(Tenant::find($this->tenant_id))->getSetting('name_format', 'first_last') ?? 'first_last'
        );
    }

    /**
     * Get the client's initials.
     *
     * Companies use the first two letters of the trade/legal name; persons keep
     * the legacy "first letter of first + first letter of last" rule. Both
     * branches are NULL-safe now that name columns are nullable.
     */
    public function getInitialsAttribute(): string
    {
        if ($this->type === self::TYPE_COMPANY) {
            $name = $this->trade_name ?? $this->company_name ?? '';
            return strtoupper(substr($name, 0, 2));
        }

        return strtoupper(
            substr($this->first_name ?? '', 0, 1) . substr($this->last_name ?? '', 0, 1)
        );
    }

    /**
     * Scope a query to only include active clients.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include prospects.
     */
    public function scopeProspects($query)
    {
        return $query->where('status', 'prospect');
    }

    /**
     * Scope to search clients by name (persona or company), email, phone or tax id.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")
                ->orWhere('trade_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('tax_id', 'like', "%{$search}%");
        });
    }

    /**
     * Scope filter by client type ('person' | 'company'). 'all' or null = no filter.
     */
    public function scopeOfType($query, ?string $type)
    {
        if (is_null($type) || $type === 'all') {
            return $query;
        }

        return $query->where('type', $type);
    }

    /**
     * Scope to persona-only rows.
     */
    public function scopePersons($query)
    {
        return $query->where('type', self::TYPE_PERSON);
    }

    /**
     * Scope to company-only rows.
     */
    public function scopeCompanies($query)
    {
        return $query->where('type', self::TYPE_COMPANY);
    }

    /**
     * Register model event hooks.
     */
    protected static function booted(): void
    {
        // Cascade logic moved to ClientObserver (handles both soft and hard delete)
    }

    /**
     * Get the activity log options.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'first_name', 'last_name', 'email', 'phone',
                'status', 'canada_status',
                'type', 'company_name', 'trade_name',
            ])
            ->logOnlyDirty()
            ->useLogName('clients')
            ->dontSubmitEmptyLogs();
    }
}
