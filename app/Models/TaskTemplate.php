<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TaskTemplate extends Model
{
    use BelongsToTenant, HasFactory, HasTranslations, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'workflow_stage_id',
        'code',
        'sort_order',
        'is_required',
        'blocks_stage_completion',
        'default_type',
        'default_priority',
        'due_offset_days',
        'is_active',
    ];

    protected $casts = [
        'is_required'             => 'boolean',
        'blocks_stage_completion' => 'boolean',
        'is_active'               => 'boolean',
        'sort_order'              => 'integer',
        'due_offset_days'         => 'integer',
    ];

    public function workflowStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'code', 'sort_order', 'is_required', 'blocks_stage_completion',
                'default_type', 'default_priority', 'due_offset_days', 'is_active',
            ])
            ->logOnlyDirty()
            ->useLogName('task_templates')
            ->dontSubmitEmptyLogs();
    }
}
