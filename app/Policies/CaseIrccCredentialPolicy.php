<?php

namespace App\Policies;

use App\Models\ImmigrationCase;
use App\Models\User;

/**
 * Spec 60 — IRCC Credentials authorization policy.
 *
 * NOTE: The active authorization path for this resource is the dedicated
 * Gate abilities registered in AuthServiceProvider (`ircc_credentials.view`,
 * `ircc_credentials.update`). They guarantee that Gate::before for
 * super-admin/admin transparently bypasses everything.
 *
 * This Policy class is kept for completeness and IDE discoverability but
 * MUST NOT be registered in AuthServiceProvider::$policies, because
 * ImmigrationCase is already mapped to CasePolicy.
 *
 * Triple gate enforced: tenant match + role/permission ownership.
 */
class CaseIrccCredentialPolicy
{
    public function view(User $user, ImmigrationCase $case): bool
    {
        return $user->tenant_id === $case->tenant_id
            && $user->hasPermissionTo('ircc_credentials.view');
    }

    public function create(User $user, ImmigrationCase $case): bool
    {
        return $user->tenant_id === $case->tenant_id
            && $user->hasPermissionTo('ircc_credentials.edit');
    }

    public function update(User $user, ImmigrationCase $case): bool
    {
        return $user->tenant_id === $case->tenant_id
            && $user->hasPermissionTo('ircc_credentials.edit');
    }
}
