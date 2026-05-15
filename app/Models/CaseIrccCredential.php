<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Spec 60 — Encrypted IRCC portal credentials per case.
 *
 * All sensitive fields are encrypted at rest using Laravel's "encrypted" cast,
 * which uses APP_KEY (AES-256-CBC). The application_number is treated as
 * sensitive too. security_questions is encrypted as a JSON array.
 *
 * Multi-tenant isolation is enforced via the BelongsToTenant trait
 * (global TenantScope on every query).
 */
class CaseIrccCredential extends Model
{
    use BelongsToTenant;

    protected $table = 'case_ircc_credentials';

    protected $fillable = [
        'tenant_id',
        'case_id',
        'ircc_username',
        'ircc_password',
        'email_address',
        'email_password',
        'application_number',
        'notes',
        'security_questions',
        'key_version',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'ircc_username'      => 'encrypted',
        'ircc_password'      => 'encrypted',
        'email_address'      => 'encrypted',
        'email_password'     => 'encrypted',
        'application_number' => 'encrypted',
        'notes'              => 'encrypted',
        'security_questions' => 'encrypted:array',
        'key_version'        => 'integer',
    ];

    /**
     * Never expose security_questions through default array/JSON serialization.
     * The controller-bound Resource decides explicitly when to surface them.
     */
    protected $hidden = [
        'security_questions',
    ];

    public function immigrationCase(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCase::class, 'case_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
