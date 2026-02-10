<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Client extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'user_id',
        // Personal Information
        'first_name',
        'last_name',
        'nationality',
        'second_nationality',
        'language',
        'second_language',
        'date_of_birth',
        'gender',
        'passport_number',
        'passport_country',
        'passport_expiry_date',
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
        'secondary_phone',
        // Legal Status in Canada
        'canada_status',
        'status_date',
        'arrival_date',
        'entry_point',
        'iuc',
        'work_permit_number',
        'study_permit_number',
        'permit_expiry_date',
        'other_status_1',
        'other_status_2',
        // Status
        'status',
        'is_primary_applicant',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'passport_expiry_date' => 'date',
        'status_date' => 'date',
        'arrival_date' => 'date',
        'permit_expiry_date' => 'date',
        'is_primary_applicant' => 'boolean',
    ];

    /**
     * Get the user account associated with this client.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Note: Companion, ImmigrationCase, and FollowUp models will be implemented in future epics
    // /**
    //  * Get the companions for this client.
    //  */
    // public function companions(): HasMany
    // {
    //     return $this->hasMany(Companion::class);
    // }

    // /**
    //  * Get the cases for this client.
    //  */
    // public function cases(): HasMany
    // {
    //     return $this->hasMany(ImmigrationCase::class);
    // }

    // /**
    //  * Get the follow-ups for this client.
    //  */
    // public function followUps(): HasMany
    // {
    //     return $this->hasMany(FollowUp::class);
    // }

    /**
     * Get the client's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get the client's initials.
     */
    public function getInitialsAttribute(): string
    {
        return strtoupper(
            substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1)
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
     * Scope to search clients by name, email, or phone.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('passport_number', 'like', "%{$search}%");
        });
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
            ])
            ->logOnlyDirty()
            ->useLogName('clients')
            ->dontSubmitEmptyLogs();
    }
}
