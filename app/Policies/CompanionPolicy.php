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
        // User must be able to view clients and companion must belong to user's tenant
        return $user->can('clients.view') && $client->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can view the companion.
     */
    public function view(User $user, Companion $companion): bool
    {
        return $user->can('clients.view') && $companion->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can create companions.
     */
    public function create(User $user, Client $client): bool
    {
        return $user->can('clients.update') && $client->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can update the companion.
     */
    public function update(User $user, Companion $companion): bool
    {
        return $user->can('clients.update') && $companion->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can delete the companion.
     */
    public function delete(User $user, Companion $companion): bool
    {
        return $user->can('clients.delete') && $companion->tenant_id === $user->tenant_id;
    }
}
