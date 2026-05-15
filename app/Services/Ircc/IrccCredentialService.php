<?php

namespace App\Services\Ircc;

use App\Models\CaseIrccCredential;
use App\Models\ImmigrationCase;
use App\Models\User;

/**
 * Spec 60 — Application service for IRCC credentials.
 *
 * Encapsulates the upsert lifecycle (create-or-update) for the single
 * encrypted credential record bound to an ImmigrationCase. All field
 * encryption is handled transparently by the Eloquent "encrypted" casts
 * on the model.
 */
class IrccCredentialService
{
    /**
     * Return the credential record for a case, or null if none exists yet.
     */
    public function getForCase(ImmigrationCase $case): ?CaseIrccCredential
    {
        return $case->irccCredentials;
    }

    /**
     * Create or update the credential record for a case.
     *
     * Audit columns (created_by / updated_by) are stamped from $by.
     * tenant_id is sourced from the parent case to keep multi-tenant
     * isolation airtight (never trust client-provided tenant_id).
     */
    public function upsert(ImmigrationCase $case, array $data, User $by): CaseIrccCredential
    {
        $credential = $case->irccCredentials ?? new CaseIrccCredential();

        if (! $credential->exists) {
            $credential->tenant_id  = $case->tenant_id;
            $credential->case_id    = $case->id;
            $credential->created_by = $by->id;
        }

        $credential->updated_by = $by->id;
        $credential->fill($data);
        $credential->save();

        return $credential->refresh();
    }
}
