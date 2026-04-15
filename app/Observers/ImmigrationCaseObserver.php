<?php

namespace App\Observers;

use App\Models\ImmigrationCase;

class ImmigrationCaseObserver
{
    public function deleting(ImmigrationCase $case): void
    {
        if ($case->isForceDeleting()) {
            return;
        }

        $case->documents()->each(fn ($doc) => $doc->delete());
    }

    public function restoring(ImmigrationCase $case): void
    {
        $case->documents()->onlyTrashed()
            ->where('deleted_at', '>=', $case->deleted_at)
            ->each(fn ($doc) => $doc->restore());
    }
}
