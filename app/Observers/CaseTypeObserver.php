<?php

namespace App\Observers;

use App\Models\CaseType;

class CaseTypeObserver
{
    /**
     * Cascade soft-delete towards workflow stages and their task templates.
     */
    public function deleting(CaseType $caseType): void
    {
        // No tocar en forceDelete (protegido por restrictOnDelete de BD).
        if ($caseType->isForceDeleting()) {
            return;
        }

        $stages = $caseType->workflowStages()->withTrashed()->get();
        foreach ($stages as $stage) {
            $stage->taskTemplates()->delete();   // soft-delete en cascada
            $stage->delete();                    // soft-delete
        }
    }

    /**
     * Restore stages and tasks soft-deleted in the same delete event.
     */
    public function restoring(CaseType $caseType): void
    {
        foreach ($caseType->workflowStages()->onlyTrashed()->get() as $stage) {
            $stage->taskTemplates()->onlyTrashed()
                ->where('deleted_at', '>=', $caseType->deleted_at)
                ->restore();
            $stage->restore();
        }
    }
}
