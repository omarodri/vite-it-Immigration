<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseType extends Model
{
    use HasFactory;

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
    public const CATEGORY_TEMPORARY = 'temporary_residence';

    public const CATEGORY_PERMANENT = 'permanent_residence';

    public const CATEGORY_HUMANITARIAN = 'humanitarian';

    /**
     * Category labels in Spanish.
     */
    public const CATEGORY_LABELS = [
        self::CATEGORY_TEMPORARY => 'Residencia Temporal',
        self::CATEGORY_PERMANENT => 'Residencia Permanente',
        self::CATEGORY_HUMANITARIAN => 'Humanitario',
    ];

    /**
     * Get the cases for this case type.
     */
    public function cases(): HasMany
    {
        return $this->hasMany(ImmigrationCase::class);
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
