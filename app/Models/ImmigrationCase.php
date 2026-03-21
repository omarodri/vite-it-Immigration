<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'stage',
        'ircc_status',
        'final_result',
        'ircc_code',
        'description',
        'archive_box_number',
        'contract_number',
        'service_type',
        'fees',
        'closed_at',
        'closure_notes',
    ];

    protected $casts = [
        'closed_at' => 'date',
        'progress' => 'integer',
        'fees' => 'decimal:2',
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
     * Stage constants.
     */
    public const STAGE_INITIAL_CONSULTATION = 'initial_consultation';

    public const STAGE_DOCUMENT_COLLECTION = 'document_collection';

    public const STAGE_APPLICATION_PREPARATION = 'application_preparation';

    public const STAGE_SUBMITTED = 'submitted';

    public const STAGE_UNDER_REVIEW = 'under_review';

    public const STAGE_ADDITIONAL_INFO_REQUESTED = 'additional_info_requested';

    public const STAGE_DECISION_PENDING = 'decision_pending';

    public const STAGE_CLOSED = 'closed';

    /**
     * Stage labels in Spanish.
     */
    public const STAGE_LABELS = [
        self::STAGE_INITIAL_CONSULTATION => 'Consulta Inicial',
        self::STAGE_DOCUMENT_COLLECTION => 'Recolección de Documentos',
        self::STAGE_APPLICATION_PREPARATION => 'Preparación de Solicitud',
        self::STAGE_SUBMITTED => 'Enviada',
        self::STAGE_UNDER_REVIEW => 'En Revisión IRCC',
        self::STAGE_ADDITIONAL_INFO_REQUESTED => 'Información Adicional Solicitada',
        self::STAGE_DECISION_PENDING => 'Decisión Pendiente',
        self::STAGE_CLOSED => 'Cerrada',
    ];

    /**
     * IRCC Status constants.
     */
    public const IRCC_NOT_SUBMITTED = 'not_submitted';

    public const IRCC_RECEIVED = 'received';

    public const IRCC_IN_PROCESS = 'in_process';

    public const IRCC_APPROVED = 'approved';

    public const IRCC_REFUSED = 'refused';

    public const IRCC_WITHDRAWN = 'withdrawn';

    public const IRCC_CANCELLED = 'cancelled';

    /**
     * IRCC Status labels in Spanish.
     */
    public const IRCC_STATUS_LABELS = [
        self::IRCC_NOT_SUBMITTED => 'No Enviada',
        self::IRCC_RECEIVED => 'Recibida',
        self::IRCC_IN_PROCESS => 'En Proceso',
        self::IRCC_APPROVED => 'Aprobada',
        self::IRCC_REFUSED => 'Rechazada',
        self::IRCC_WITHDRAWN => 'Retirada',
        self::IRCC_CANCELLED => 'Cancelada',
    ];

    /**
     * Final Result constants.
     */
    public const FINAL_RESULT_APPROVED = 'approved';

    public const FINAL_RESULT_DENIED = 'denied';

    /**
     * Final Result labels in Spanish.
     */
    public const FINAL_RESULT_LABELS = [
        self::FINAL_RESULT_APPROVED => 'Aprobado',
        self::FINAL_RESULT_DENIED => 'Denegado',
    ];

    /**
     * Service Type constants.
     */
    public const SERVICE_TYPE_PRO_BONO = 'pro_bono';

    public const SERVICE_TYPE_FEE_BASED = 'fee_based';

    /**
     * Service Type labels in Spanish.
     */
    public const SERVICE_TYPE_LABELS = [
        self::SERVICE_TYPE_PRO_BONO => 'Pro Bono',
        self::SERVICE_TYPE_FEE_BASED => 'Con Honorarios',
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
     * Get the important dates for this case.
     */
    public function importantDates(): HasMany
    {
        return $this->hasMany(CaseImportantDate::class, 'case_id')->orderBy('sort_order');
    }

    /**
     * Get the tasks for this case.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(CaseTask::class, 'case_id')->orderBy('sort_order');
    }

    /**
     * Get the invoices for this case.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(CaseInvoice::class, 'case_id')->orderBy('sort_order');
    }

    /**
     * Get the documents for this case.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'case_id');
    }

    /**
     * Get the document folders for this case.
     */
    public function documentFolders(): HasMany
    {
        return $this->hasMany(DocumentFolder::class, 'case_id');
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
     * Get the stage label in Spanish.
     */
    public function getStageLabelAttribute(): ?string
    {
        return $this->stage ? (self::STAGE_LABELS[$this->stage] ?? $this->stage) : null;
    }

    /**
     * Get the IRCC status label in Spanish.
     */
    public function getIrccStatusLabelAttribute(): ?string
    {
        return $this->ircc_status ? (self::IRCC_STATUS_LABELS[$this->ircc_status] ?? $this->ircc_status) : null;
    }

    /**
     * Get the final result label in Spanish.
     */
    public function getFinalResultLabelAttribute(): ?string
    {
        return $this->final_result ? (self::FINAL_RESULT_LABELS[$this->final_result] ?? $this->final_result) : null;
    }

    /**
     * Get the service type label in Spanish.
     */
    public function getServiceTypeLabelAttribute(): ?string
    {
        return $this->service_type ? (self::SERVICE_TYPE_LABELS[$this->service_type] ?? $this->service_type) : null;
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
     * Scope a query to filter by stage.
     */
    public function scopeByStage($query, string $stage)
    {
        return $query->where('stage', $stage);
    }

    /**
     * Scope a query to filter by IRCC status.
     */
    public function scopeByIrccStatus($query, string $irccStatus)
    {
        return $query->where('ircc_status', $irccStatus);
    }

    /**
     * Scope a query to filter by service type.
     */
    public function scopeByServiceType($query, string $serviceType)
    {
        return $query->where('service_type', $serviceType);
    }

    /**
     * Scope a query to get cases with important dates in the next N days.
     */
    public function scopeUpcoming($query, int $days = 30)
    {
        return $query->whereHas('importantDates', function ($q) use ($days) {
            $q->whereNotNull('due_date')
              ->whereDate('due_date', '>=', now())
              ->whereDate('due_date', '<=', now()->addDays($days));
        });
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
                'description',
                'stage',
                'ircc_status',
                'final_result',
                'fees',
                'service_type',
            ])
            ->logOnlyDirty()
            ->useLogName('cases')
            ->dontSubmitEmptyLogs();
    }
}
