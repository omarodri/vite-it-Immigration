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
        'email',
        'phone',
        'phone_country_code',
        'canada_status',
        'canada_status_other',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'passport_expiry_date' => 'date',
    ];

    /**
     * Relationship types with Spanish labels.
     */
    public const RELATIONSHIP_TYPES = [
        'spouse'             => 'Cónyuge',
        'common-law partner' => 'Pareja de hecho',
        'dependent child'    => 'Hijo/a dependiente',
        'grandchild'         => 'Nieto/a',
        'parent'             => 'Padre/Madre',
        'grandparent'        => 'Abuelo/a',
        'sibling'            => 'Hermano/a',
        'half-sibling'       => 'Medio hermano/a',
        'step-sibling'       => 'Hermanastro/a',
        'aunt / uncle'       => 'Tío/a',
        'niece / nephew'     => 'Sobrino/a',
        'cousin'             => 'Primo/a',
        'child-in-law'       => 'Yerno/Nuera',
        'parent-in-law'      => 'Suegro/a',
        'other'              => 'Otro',
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
     * Get the canada status label in Spanish.
     */
    public function getCanadaStatusLabelAttribute(): ?string
    {
        return match($this->canada_status) {
            'asylum_seeker'      => 'Solicitante de Asilo',
            'refugee'            => 'Refugiado',
            'temporary_resident' => 'Residente Temporal',
            'permanent_resident' => 'Residente Permanente',
            'citizen'            => 'Ciudadano',
            'visitor'            => 'Visitante',
            'student'            => 'Estudiante',
            'worker'             => 'Trabajador',
            'other'              => $this->canada_status_other ?? 'Otro',
            default              => null,
        };
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
                'email', 'phone', 'canada_status',
            ])
            ->logOnlyDirty()
            ->useLogName('companions')
            ->dontSubmitEmptyLogs();
    }
}
