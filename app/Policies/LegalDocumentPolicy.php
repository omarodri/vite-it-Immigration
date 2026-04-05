<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LegalDocument;
use App\Models\User;

class LegalDocumentPolicy
{
    /**
     * Determine whether the user can view any legal documents.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('clients.view');
    }

    /**
     * Determine whether the user can view the legal document.
     */
    public function view(User $user, LegalDocument $document): bool
    {
        return $user->tenant_id === $document->tenant_id;
    }

    /**
     * Determine whether the user can create legal documents.
     */
    public function create(User $user): bool
    {
        return $user->can('clients.update');
    }

    /**
     * Determine whether the user can update the legal document.
     */
    public function update(User $user, LegalDocument $document): bool
    {
        return $user->tenant_id === $document->tenant_id && $user->can('clients.update');
    }

    /**
     * Determine whether the user can delete the legal document.
     */
    public function delete(User $user, LegalDocument $document): bool
    {
        return $user->tenant_id === $document->tenant_id && $user->can('clients.update');
    }
}
