<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Companion extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'client_id',
        'first_name',
        'last_name',
        'relationship',
        'relationship_other',
        'date_of_birth',
        'gender',
        'passport_number',
        'passport_country',
        'passport_expiry_date',
        'nationality',
        'notes',
        'iuc',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'passport_expiry_date' => 'date',
    ];

    /**
     * Relationship types with Spanish labels.
     */
    public const RELATIONSHIP_TYPES = [
        'spouse' => 'Cónyuge',
        'child' => 'Hijo/a',
        'parent' => 'Padre/Madre',
        'sibling' => 'Hermano/a',
        'other' => 'Otro',
    ];

    /**
     * Get the client that owns this companion.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the cases this companion is included in.
     */
    public function cases(): BelongsToMany
    {
        return $this->belongsToMany(ImmigrationCase::class, 'case_companions', 'companion_id', 'case_id')
            ->withTimestamps();
    }

    /**
     * Get the companion's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get the companion's initials.
     */
    public function getInitialsAttribute(): string
    {
        return strtoupper(
            substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1)
        );
    }

    /**
     * Get the relationship label in Spanish.
     */
    public function getRelationshipLabelAttribute(): string
    {
        if ($this->relationship === 'other' && $this->relationship_other) {
            return $this->relationship_other;
        }

        return self::RELATIONSHIP_TYPES[$this->relationship] ?? $this->relationship;
    }

    /**
     * Get the companion's age calculated from date of birth.
     */
    public function getAgeAttribute(): ?int
    {
        if (! $this->date_of_birth) {
            return null;
        }

        return $this->date_of_birth->age;
    }

    /**
     * Get the activity log options.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'first_name', 'last_name', 'relationship',
                'date_of_birth', 'nationality',
            ])
            ->logOnlyDirty()
            ->useLogName('companions')
            ->dontSubmitEmptyLogs();
    }
}
