<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Companion;
use App\Models\User;

class CompanionPolicy
{
    /**
     * Determine whether the user can view any companions.
     */
    public function viewAny(User $user, Client $client): bool
    {
        return $user->can('companions.view') && $client->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can view the companion.
     */
    public function view(User $user, Companion $companion): bool
    {
        return $user->can('companions.view') && $companion->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can create companions.
     */
    public function create(User $user, Client $client): bool
    {
        return $user->can('companions.create') && $client->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can update the companion.
     */
    public function update(User $user, Companion $companion): bool
    {
        return $user->can('companions.update') && $companion->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can delete the companion.
     */
    public function delete(User $user, Companion $companion): bool
    {
        return $user->can('companions.delete') && $companion->tenant_id === $user->tenant_id;
    }
}
