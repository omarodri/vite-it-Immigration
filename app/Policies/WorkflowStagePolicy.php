<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowStage;

class WorkflowStagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('workflows.view');
    }

    public function view(User $user, WorkflowStage $stage): bool
    {
        return $user->can('workflows.view') && $stage->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('workflows.create');
    }

    public function update(User $user, WorkflowStage $stage): bool
    {
        return $user->can('workflows.update') && $stage->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, WorkflowStage $stage): bool
    {
        return $user->can('workflows.delete') && $stage->tenant_id === $user->tenant_id;
    }
}
