<?php

namespace App\Services\Ircc;

use App\Models\CaseIrccCredential;
use App\Models\User;

/**
 * Spec 60 — Dedicated audit trail for IRCC credentials.
 *
 * Reads and writes are recorded separately under distinct activity log
 * names (`ircc_access` / `ircc_changes`) so they can be retrieved and
 * audited without polluting the general activity feed.
 *
 * SECURITY: never log the credential values themselves — only metadata
 * (IP, user agent, which fields changed). The activity log is queryable
 * by tenant admins, so leaking plaintext here would defeat encryption.
 */
class IrccAuditService
{
    public function logRead(CaseIrccCredential $cred, User $user): void
    {
        activity('ircc_access')
            ->causedBy($user)
            ->performedOn($cred)
            ->withProperties([
                'ip'         => request()->ip(),
                'user_agent' => request()->userAgent(),
                'case_id'    => $cred->case_id,
                'tenant_id'  => $cred->tenant_id,
            ])
            ->event('viewed')
            ->log('IRCC credentials viewed');
    }

    /**
     * @param  array<int, string>  $changedFields  field names that were submitted
     */
    public function logWrite(CaseIrccCredential $cred, User $user, array $changedFields): void
    {
        activity('ircc_changes')
            ->causedBy($user)
            ->performedOn($cred)
            ->withProperties([
                'ip'             => request()->ip(),
                'user_agent'     => request()->userAgent(),
                'case_id'        => $cred->case_id,
                'tenant_id'      => $cred->tenant_id,
                'changed_fields' => $changedFields,
            ])
            ->event('updated')
            ->log('IRCC credentials updated');
    }
}
