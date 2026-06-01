<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class CaseType extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope('tenant_or_global', function (Builder $builder) {
            $user = Auth::user();
            if ($user && !$user->hasRole('super-admin')) {
                $builder->where(function ($q) use ($user) {
                    $q->whereNull('tenant_id')
                      ->orWhere('tenant_id', $user->tenant_id);
                });
            }
        });

        static::creating(function ($model) {
            if (!$model->tenant_id && Auth::check()) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'category',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Category constants.
     */
    public const CATEGORY_TEMPORARY   = 'temporary_residence';
    public const CATEGORY_PERMANENT   = 'permanent_residence';
    public const CATEGORY_REFUGEE     = 'refugee';
    public const CATEGORY_CITIZENSHIP = 'citizenship';
    public const CATEGORY_WORK_PERMIT = 'work_permit';
    public const CATEGORY_OTHER       = 'other';

    /**
     * Category labels in Spanish.
     */
    public const CATEGORY_LABELS = [
        self::CATEGORY_TEMPORARY   => 'Residencia Temporal',
        self::CATEGORY_PERMANENT   => 'Residencia Permanente',
        self::CATEGORY_REFUGEE     => 'Refugiado / Asilo',
        self::CATEGORY_CITIZENSHIP => 'Ciudadanía',
        self::CATEGORY_WORK_PERMIT => 'Permiso de Trabajo',
        self::CATEGORY_OTHER       => 'Otro',
    ];

    /**
     * All valid category values — used for validation in Form Requests.
     *
     * @var array<int, string>
     */
    public const CATEGORIES = [
        self::CATEGORY_TEMPORARY,
        self::CATEGORY_PERMANENT,
        self::CATEGORY_REFUGEE,
        self::CATEGORY_CITIZENSHIP,
        self::CATEGORY_WORK_PERMIT,
        self::CATEGORY_OTHER,
    ];

    /**
     * Get the tenant that owns this case type.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the cases for this case type.
     */
    public function cases(): HasMany
    {
        return $this->hasMany(ImmigrationCase::class);
    }

    /**
     * Get the workflow stages defined for this case type, ordered by sort_order.
     */
    public function workflowStages(): HasMany
    {
        return $this->hasMany(WorkflowStage::class, 'case_type_id')
            ->orderBy('sort_order');
    }

    /**
     * Count active (not closed/archived) cases for this type.
     * Informativo para el aviso en UI — no es una restricción de borrado.
     */
    public function activeCasesCount(): int
    {
        return ImmigrationCase::where('case_type_id', $this->id)
            ->whereNotIn('status', ['closed', 'archived'])
            ->count();
    }

    /**
     * Whether this is a system-wide (global) case type.
     */
    public function isGlobal(): bool
    {
        return $this->tenant_id === null;
    }

    /**
     * Get the category label in Spanish.
     */
    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? $this->category;
    }

    /**
     * Scope a query to only include active case types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope a query to include global types (tenant_id is null) or tenant-specific types.
     */
    public function scopeGlobalOrTenant($query, int $tenantId)
    {
        return $query->where(function ($q) use ($tenantId) {
            $q->whereNull('tenant_id')
                ->orWhere('tenant_id', $tenantId);
        });
    }
}
