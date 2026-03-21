<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\ImmigrationCase;
use App\Models\User;

class DocumentPolicy
{
    /**
     * Determine whether the user can view any documents for a case.
     */
    public function viewAny(User $user, ImmigrationCase $case): bool
    {
        return $user->can('documents.view') && $case->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can view the document.
     */
    public function view(User $user, Document $document): bool
    {
        return $user->can('documents.view') && $document->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can create documents.
     */
    public function create(User $user, ImmigrationCase $case): bool
    {
        return $user->can('documents.create') && $case->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can update the document.
     */
    public function update(User $user, Document $document): bool
    {
        return $user->can('documents.update') && $document->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can delete the document.
     */
    public function delete(User $user, Document $document): bool
    {
        return $user->can('documents.delete') && $document->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can download the document.
     */
    public function download(User $user, Document $document): bool
    {
        return $user->can('documents.view') && $document->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can move the document.
     */
    public function move(User $user, Document $document): bool
    {
        return $user->can('documents.update') && $document->tenant_id === $user->tenant_id;
    }

    /**
     * Determine whether the user can replace the document file.
     */
    public function replace(User $user, Document $document): bool
    {
        return $user->can('documents.update') && $document->tenant_id === $user->tenant_id;
    }
}
