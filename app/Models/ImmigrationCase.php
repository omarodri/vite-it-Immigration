<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ImmigrationCase extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    /**
     * The table associated with the model.
     * Named 'cases' but model is ImmigrationCase because 'case' is a reserved word in PHP.
     */
    protected $table = 'cases';

    protected $fillable = [
        'tenant_id',
        'case_number',
        'client_id',
        'case_type_id',
        'assigned_to',
        'status',
        'priority',
        'progress',
        'language',
        'description',
        'hearing_date',
        'fda_deadline',
        'brown_sheet_date',
        'evidence_deadline',
        'archive_box_number',
        'closed_at',
        'closure_notes',
    ];

    protected $casts = [
        'hearing_date' => 'date',
        'fda_deadline' => 'date',
        'brown_sheet_date' => 'date',
        'evidence_deadline' => 'date',
        'closed_at' => 'date',
        'progress' => 'integer',
    ];

    /**
     * Status constants.
     */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_CLOSED = 'closed';

    /**
     * Status labels in Spanish.
     */
    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Activo',
        self::STATUS_INACTIVE => 'Inactivo',
        self::STATUS_ARCHIVED => 'Archivado',
        self::STATUS_CLOSED => 'Cerrado',
    ];

    /**
     * Priority constants.
     */
    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_LOW = 'low';

    /**
     * Priority labels in Spanish.
     */
    public const PRIORITY_LABELS = [
        self::PRIORITY_URGENT => 'Urgente',
        self::PRIORITY_HIGH => 'Alta',
        self::PRIORITY_MEDIUM => 'Media',
        self::PRIORITY_LOW => 'Baja',
    ];

    /**
     * Get the client that owns this case.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the case type.
     */
    public function caseType(): BelongsTo
    {
        return $this->belongsTo(CaseType::class);
    }

    /**
     * Get the user assigned to this case.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the companions associated with this case.
     */
    public function companions(): BelongsToMany
    {
        return $this->belongsToMany(Companion::class, 'case_companions', 'case_id', 'companion_id')
            ->withTimestamps();
    }

    /**
     * Get the status label in Spanish.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Get the priority label in Spanish.
     */
    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITY_LABELS[$this->priority] ?? $this->priority;
    }

    /**
     * Get the progress as a percentage string.
     */
    public function getProgressPercentageAttribute(): string
    {
        return $this->progress . '%';
    }

    /**
     * Get the days until the hearing date.
     */
    public function getDaysUntilHearingAttribute(): ?int
    {
        if (! $this->hearing_date) {
            return null;
        }

        return now()->startOfDay()->diffInDays($this->hearing_date, false);
    }

    /**
     * Scope a query to only include active cases.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by priority.
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope a query to filter by assigned user.
     */
    public function scopeByAssignee($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope a query to filter by case type.
     */
    public function scopeByCaseType($query, int $caseTypeId)
    {
        return $query->where('case_type_id', $caseTypeId);
    }

    /**
     * Scope a query to filter by client.
     */
    public function scopeByClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * Scope to search cases by case number or description.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('case_number', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('client', function ($clientQuery) use ($search) {
                    $clientQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
        });
    }

    /**
     * Scope a query to get cases with hearings in the next N days.
     */
    public function scopeUpcoming($query, int $days = 30)
    {
        return $query->whereNotNull('hearing_date')
            ->whereDate('hearing_date', '>=', now())
            ->whereDate('hearing_date', '<=', now()->addDays($days));
    }

    /**
     * Scope a query to get unassigned cases.
     */
    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    /**
     * Get the activity log options.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status',
                'priority',
                'progress',
                'assigned_to',
                'hearing_date',
                'description',
            ])
            ->logOnlyDirty()
            ->useLogName('cases')
            ->dontSubmitEmptyLogs();
    }
}
