<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseInvoice extends Model
{
    protected $fillable = [
        'case_id',
        'invoice_number',
        'invoice_date',
        'amount',
        'is_collected',
        'sort_order',
    ];

    protected $casts = [
        'invoice_date' => 'date:Y-m-d',
        'amount' => 'decimal:2',
        'is_collected' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the case that owns this invoice.
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class, 'case_id');
    }
}
