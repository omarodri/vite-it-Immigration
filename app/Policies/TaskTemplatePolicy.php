<?php

namespace App\Policies;

use App\Models\TaskTemplate;
use App\Models\User;

class TaskTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('workflows.view');
    }

    public function view(User $user, TaskTemplate $template): bool
    {
        return $user->can('workflows.view') && $template->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('workflows.create');
    }

    public function update(User $user, TaskTemplate $template): bool
    {
        return $user->can('workflows.update') && $template->tenant_id === $user->tenant_id;
    }

    public function delete(User $user, TaskTemplate $template): bool
    {
        return $user->can('workflows.delete') && $template->tenant_id === $user->tenant_id;
    }
}
