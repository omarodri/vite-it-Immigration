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

class CaseStage extends Model
{
    use BelongsToTenant, HasFactory, HasTranslations, LogsActivity, SoftDeletes;

    protected $table = 'case_stages';

    protected $fillable = [
        'tenant_id',
        'case_id',
        'workflow_stage_id',
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

    public function case(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class, 'case_id');
    }

    public function originTemplate(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'workflow_stage_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'case_stage_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'sort_order', 'is_active', 'is_terminal', 'color'])
            ->logOnlyDirty()
            ->useLogName('case_stages')
            ->dontSubmitEmptyLogs();
    }
}
