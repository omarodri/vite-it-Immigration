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

        if ($companion->canHaveFamily()) {
            $companion->familyMembers()->each(fn ($f) => $f->delete());
        }

        $companion->legalDocuments()->each(fn ($doc) => $doc->delete());
    }

    public function restoring(Companion $companion): void
    {
        if ($companion->canHaveFamily()) {
            $companion->familyMembers()
                ->onlyTrashed()
                ->where('deleted_at', '>=', $companion->deleted_at)
                ->each(fn ($f) => $f->restore());
        }

        $companion->legalDocuments()->onlyTrashed()
            ->where('deleted_at', '>=', $companion->deleted_at)
            ->each(fn ($doc) => $doc->restore());
    }
}
