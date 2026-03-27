<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Document extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'case_id',
        'folder_id',
        'uploaded_by',
        'name',
        'original_name',
        'mime_type',
        'size',
        'category',
        'storage_type',
        'storage_path',
        'external_id',
        'external_url',
        'version',
        'checksum',
        'scanned_at',
        'scan_status',
    ];

    protected $casts = [
        'size' => 'integer',
        'version' => 'integer',
        'scanned_at' => 'datetime',
    ];

    /**
     * Category constants.
     */
    public const CATEGORY_ARCHIVE = 'archive';

    public const CATEGORY_LETTERS = 'letters';

    public const CATEGORY_COMMUNICATION = 'communication';

    public const CATEGORY_CONTRACT = 'contract';

    public const CATEGORY_ACCOUNTING = 'accounting';

    public const CATEGORY_DOCUMENTS = 'documents';

    public const CATEGORY_LINKS = 'links';

    public const CATEGORY_QUESTIONARY = 'questionary';

    public const CATEGORY_FORMS = 'forms';

    public const CATEGORY_ADMISSION = 'admission';

    public const CATEGORY_HISTORY = 'history';

    public const CATEGORY_EVIDENCE = 'evidence';

    public const CATEGORY_HEARING = 'hearing';

    public const CATEGORY_OTHER = 'other';

    /**
     * Category labels in Spanish.
     */
    public const CATEGORY_LABELS = [
        self::CATEGORY_ARCHIVE => 'Archivo',
        self::CATEGORY_LETTERS => 'Cartas',
        self::CATEGORY_COMMUNICATION => 'Comunicaciones',
        self::CATEGORY_CONTRACT => 'Contrato',
        self::CATEGORY_ACCOUNTING => 'Contabilidad',
        self::CATEGORY_DOCUMENTS => 'Documentos',
        self::CATEGORY_LINKS => 'Enlaces',
        self::CATEGORY_QUESTIONARY => 'Questionarios',
        self::CATEGORY_FORMS => 'Formularios',
        self::CATEGORY_ADMISSION => 'Admision',
        self::CATEGORY_HISTORY => 'Historial',
        self::CATEGORY_EVIDENCE => 'Evidencia',
        self::CATEGORY_HEARING => 'Audiencias',
        self::CATEGORY_OTHER => 'Otros',
    ];

    /**
     * Storage type constants.
     */
    public const STORAGE_LOCAL = 'local';

    public const STORAGE_ONEDRIVE = 'onedrive';

    public const STORAGE_GOOGLE_DRIVE = 'google_drive';

    public const STORAGE_SHAREPOINT = 'sharepoint';

    /**
     * Get the tenant that owns this document.
     * (Provided by BelongsToTenant trait, but explicit for clarity.)
     */

    /**
     * Get the case that owns this document.
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class, 'case_id');
    }

    /**
     * Get the folder this document belongs to.
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, 'folder_id');
    }

    /**
     * Get the user who uploaded this document.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope a query to filter by case.
     */
    public function scopeByCase($query, int $caseId)
    {
        return $query->where('case_id', $caseId);
    }

    /**
     * Scope a query to filter by folder.
     */
    public function scopeByFolder($query, ?int $folderId)
    {
        return $query->where('folder_id', $folderId);
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get the activity log options.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'category',
                'folder_id',
            ])
            ->logOnlyDirty()
            ->useLogName('documents')
            ->dontSubmitEmptyLogs();
    }
}
