<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentFolder extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'case_id',
        'parent_id',
        'name',
        'sort_order',
        'is_default',
        'category',
        'external_id',
        'external_url',
        'sync_status',
        'synced_at',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_default' => 'boolean',
        'synced_at' => 'datetime',
    ];

    /**
     * Get the case that owns this folder.
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class, 'case_id');
    }

    /**
     * Get the parent folder.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the child folders.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Get the documents in this folder.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'folder_id');
    }

    /**
     * Scope a query to filter by case.
     */
    public function scopeByCase($query, int $caseId)
    {
        return $query->where('case_id', $caseId);
    }

    /**
     * Scope a query to only include root folders (no parent).
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Get the breadcrumb path for this folder.
     */
    public function getPathAttribute(): string
    {
        $segments = [$this->name];
        $current = $this;

        while ($current->parent_id && $current->parent) {
            $current = $current->parent;
            array_unshift($segments, $current->name);
        }

        return implode(' / ', $segments);
    }
}
