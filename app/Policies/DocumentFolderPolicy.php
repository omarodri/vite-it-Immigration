<?php

namespace App\Policies;

use App\Models\DocumentFolder;
use App\Models\ImmigrationCase;
use App\Models\User;

class DocumentFolderPolicy
{
    /**
     * Determine whether the user can view any folders for a case.
     */
    public function viewAny(User $user, ImmigrationCase $case): bool
    {
        return $user->can('documents.view') && $case->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can create folders.
     */
    public function create(User $user, ImmigrationCase $case): bool
    {
        return $user->can('documents.create') && $case->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can update the folder.
     */
    public function update(User $user, DocumentFolder $folder): bool
    {
        return $user->can('documents.update') && $folder->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can delete the folder.
     */
    public function delete(User $user, DocumentFolder $folder): bool
    {
        return $user->can('documents.delete') && $folder->tenant_id === $user->tenant_id;
    }
}
