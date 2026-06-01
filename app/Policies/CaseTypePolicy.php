<?php

namespace App\Policies;

use App\Models\CaseType;
use App\Models\User;

class CaseTypePolicy
{
    // Gate::before en AuthServiceProvider ya cubre super-admin y admin → bypass total.

    public function viewAny(User $user): bool
    {
        return $user->can('case_types.view');
    }

    public function view(User $user, CaseType $caseType): bool
    {
        if (! $user->can('case_types.view')) {
            return false;
        }

        // Tipos globales: cualquiera con el permiso puede verlos.
        if ($caseType->isGlobal()) {
            return true;
        }

        return $caseType->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('case_types.create');
    }

    public function update(User $user, CaseType $caseType): bool
    {
        if (! $user->can('case_types.update')) {
            return false;
        }

        // Tipos globales: solo super-admin (cubierto por Gate::before).
        if ($caseType->isGlobal()) {
            return false;
        }

        return $caseType->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, CaseType $caseType): bool
    {
        if (! $user->can('case_types.delete')) {
            return false;
        }

        if ($caseType->isGlobal()) {
            return false;   // globales: intocables
        }

        return $caseType->tenant_id === $user->tenant_id;
    }

    public function clone(User $user, CaseType $caseType): bool
    {
        if (! $user->can('case_types.clone')) {
            return false;
        }

        // Clonar tipos globales al propio tenant: permitido (caso de uso principal).
        if ($caseType->isGlobal()) {
            return true;
        }

        return $caseType->tenant_id === $user->tenant_id;
    }
}
