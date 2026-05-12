<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseImportantDate extends Model
{
    use HasFactory;

    protected $fillable = ['case_id', 'label', 'due_date', 'calendar_event_id', 'sort_order'];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'sort_order' => 'integer',
        'calendar_event_id' => 'integer',
    ];

    public function immigrationCase(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class, 'case_id');
    }

    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'calendar_event_id');
    }

    public function scopeInDateWindow(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('case_important_dates.due_date', [$from, $to]);
    }
}
