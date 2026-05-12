<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CaseImportantDate;
use App\Models\User;

class CaseImportantDatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cases.view');
    }

    public function update(User $user, CaseImportantDate $date): bool
    {
        return $user->tenant_id === $date->immigrationCase->tenant_id
            && $user->can('cases.update');
    }
}
