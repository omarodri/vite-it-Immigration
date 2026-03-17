<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'created_by',
        'assigned_to_id',
        'client_id',
        'case_id',
        'client_name_snapshot',
        'title',
        'description',
        'start_date',
        'end_date',
        'all_day',
        'location',
        'category',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date'   => 'datetime',
        'all_day'    => 'boolean',
    ];

    public static array $categories = [
        'primera_sesion',
        'seguimiento',
        'importante',
        'personal',
    ];

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function immigrationCase(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class, 'case_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_participants')
                    ->withPivot('confirmed')
                    ->withTimestamps();
    }

    public function getCategoryColor(): string
    {
        return match ($this->category) {
            'primera_sesion' => 'info',
            'seguimiento'    => 'warning',
            'importante'     => 'danger',
            'personal'       => 'success',
            default          => 'primary',
        };
    }

    public function getCategoryHex(): string
    {
        return match ($this->category) {
            'primera_sesion' => '#2196f3',
            'seguimiento'    => '#e2a03f',
            'importante'     => '#e7515a',
            'personal'       => '#00ab55',
            default          => '#4361ee',
        };
    }

    public function scopeInDateRange(Builder $query, ?string $start, ?string $end): Builder
    {
        return $query
            ->when($start, fn ($q, $s) => $q->where('start_date', '>=', $s))
            ->when($end,   fn ($q, $e) => $q->where('start_date', '<=', $e));
    }
}
