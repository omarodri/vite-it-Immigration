<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ScrumTask extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'scrum_column_id',
        'title',
        'description',
        'tags',
        'category',
        'due_date',
        'assigned_to_id',
        'case_id',
        'order_index',
        'is_completed',
    ];

    protected $casts = [
        'tags'         => 'array',
        'due_date'     => 'date',
        'is_completed' => 'boolean',
    ];

    public function column(): BelongsTo
    {
        return $this->belongsTo(ScrumColumn::class, 'scrum_column_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function immigrationCase(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class, 'case_id');
    }

    public function getDescriptionPreviewAttribute(): ?string
    {
        if (! $this->description) {
            return null;
        }

        return Str::limit($this->description, 200);
    }
}
