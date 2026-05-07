<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Task extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'tasks';

    protected $fillable = [
        'tenant_id',
        'case_id',
        'workflow_stage_id',
        'case_stage_id',
        'task_template_id',
        'cloned_from_task_id',
        'requester_id',
        'assigned_to',
        'subject',
        'description',
        'type',
        'priority',
        'status',
        'sort_order',
        'due_date',
        'estimated_hours',
        'actual_hours',
        'document_id',
    ];

    protected $casts = [
        'due_date'        => 'datetime',
        'estimated_hours' => 'decimal:2',
        'actual_hours'    => 'decimal:2',
        'sort_order'      => 'integer',
    ];

    public function immigrationCase(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class, 'case_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class, 'task_template_id');
    }

    public function clonedFrom(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'cloned_from_task_id');
    }

    public function workflowStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class);
    }

    public function caseStage(): BelongsTo
    {
        return $this->belongsTo(CaseStage::class, 'case_stage_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'priority', 'assigned_to', 'subject', 'due_date'])
            ->logOnlyDirty()
            ->useLogName('tasks')
            ->dontSubmitEmptyLogs();
    }
}
