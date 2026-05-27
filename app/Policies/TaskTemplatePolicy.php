<?php

namespace App\Policies;

use App\Models\TaskTemplate;
use App\Models\User;

class TaskTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('workflow_tasks.view');
    }

    public function view(User $user, TaskTemplate $template): bool
    {
        return $user->can('workflow_tasks.view')
            && $template->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('workflow_tasks.create');
    }

    public function update(User $user, TaskTemplate $template): bool
    {
        return $user->can('workflow_tasks.update')
            && $template->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, TaskTemplate $template): bool
    {
        return $user->can('workflow_tasks.delete')
            && $template->tenant_id === $user->tenant_id;
    }

    public function clone(User $user, TaskTemplate $template): bool
    {
        return $user->can('workflow_tasks.clone')
            && $template->tenant_id === $user->tenant_id;
    }
}
