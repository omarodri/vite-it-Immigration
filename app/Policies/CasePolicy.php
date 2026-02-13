<?php

namespace App\Policies;

use App\Models\ImmigrationCase;
use App\Models\User;

class CasePolicy
{
    /**
     * Determine whether the user can view any cases.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('cases.view');
    }

    /**
     * Determine whether the user can view the case.
     */
    public function view(User $user, ImmigrationCase $case): bool
    {
        return $user->can('cases.view') && $case->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can create cases.
     */
    public function create(User $user): bool
    {
        return $user->can('cases.create');
    }

    /**
     * Determine whether the user can update the case.
     */
    public function update(User $user, ImmigrationCase $case): bool
    {
        return $user->can('cases.update') && $case->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can delete the case.
     */
    public function delete(User $user, ImmigrationCase $case): bool
    {
        return $user->can('cases.delete') && $case->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can assign the case to another user.
     */
    public function assign(User $user, ImmigrationCase $case): bool
    {
        return $user->can('cases.assign') && $case->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can view the case timeline.
     */
    public function viewTimeline(User $user, ImmigrationCase $case): bool
    {
        return $user->can('cases.view') && $case->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can close the case.
     */
    public function close(User $user, ImmigrationCase $case): bool
    {
        return $user->can('cases.update') && $case->tenant_id === $user->tenant_id;
    }
}
