<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseImportantDate extends Model
{
    use HasFactory;

    protected $fillable = ['case_id', 'label', 'due_date', 'sort_order'];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'sort_order' => 'integer',
    ];

    /**
     * Get the case that owns this important date.
     */
    public function immigrationCase(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class, 'case_id');
    }
}
