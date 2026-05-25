<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
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
        'marital_status',
        'nationality',
        'notes',
        'iuc',
        'email',
        'phone',
        'phone_country_code',
        'canada_status',
        'canada_status_other',
        'arrival_date',
    ];

    protected $appends = ['full_name', 'sort_name', 'initials'];

    protected $casts = [
        'date_of_birth' => 'date',
        'arrival_date' => 'date',
    ];

    public const RELATIONSHIP_BENEFICIARY = 'beneficiary';

    /**
     * Relationship types with Spanish labels.
     */
    public const RELATIONSHIP_TYPES = [
        'beneficiary'        => 'Beneficiary / Employee',
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
        'employee'           => 'Empleado',
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
     * Get the legal documents for this companion.
     */
    public function legalDocuments(): MorphMany
    {
        return $this->morphMany(LegalDocument::class, 'documentable')
                    ->orderBy('sort_order');
    }

    /**
     * The client that this companion was promoted to (if any).
     */
    public function promotedClient(): HasOne
    {
        return $this->hasOne(Client::class, 'promoted_from_companion_id');
    }

    /**
     * Scope: only companions flagged as the case beneficiary (e.g. sponsored employee).
     */
    public function scopeBeneficiaries(Builder $query): Builder
    {
        return $query->where('relationship', self::RELATIONSHIP_BENEFICIARY);
    }

    /**
     * Get the companion's full name, respecting the tenant's name format preference.
     */
    public function getFullNameAttribute(): string
    {
        $format = $this->getNameFormat();

        return $format === 'last_first'
            ? "{$this->last_name}, {$this->first_name}"
            : "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get the companion's name in "Last, First" format (always, for sorting).
     */
    public function getSortNameAttribute(): string
    {
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
     * Register model event hooks.
     */
    protected static function booted(): void
    {
        // Cascade logic moved to CompanionObserver (handles both soft and hard delete)
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
            'protected_person'   => 'Persona Protegida',
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
