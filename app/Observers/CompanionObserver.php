<?php

namespace App\Observers;

use App\Models\Companion;

class CompanionObserver
{
    public function deleting(Companion $companion): void
    {
        if ($companion->isForceDeleting()) {
            return;
        }

        $companion->legalDocuments()->each(fn ($doc) => $doc->delete());
    }

    public function restoring(Companion $companion): void
    {
        $companion->legalDocuments()->onlyTrashed()
            ->where('deleted_at', '>=', $companion->deleted_at)
            ->each(fn ($doc) => $doc->restore());
    }
}
