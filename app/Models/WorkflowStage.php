<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WorkflowStage extends Model
{
    use BelongsToTenant, HasFactory, HasTranslations, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'case_type_id',
        'code',
        'sort_order',
        'is_active',
        'is_terminal',
        'color',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'is_terminal' => 'boolean',
        'sort_order'  => 'integer',
    ];

    public function caseType(): BelongsTo
    {
        return $this->belongsTo(CaseType::class);
    }

    public function taskTemplates(): HasMany
    {
        return $this->hasMany(TaskTemplate::class)->orderBy('sort_order');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(ImmigrationCase::class, 'current_stage_id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'sort_order', 'is_active', 'is_terminal'])
            ->logOnlyDirty()
            ->useLogName('workflow_stages')
            ->dontSubmitEmptyLogs();
    }
}
