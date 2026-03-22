<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    /**
     * Only super-admin can manage tenants.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->hasRole('super-admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->hasRole('super-admin');
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->hasRole('super-admin');
    }
}
