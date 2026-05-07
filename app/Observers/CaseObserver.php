<?php

namespace App\Observers;

use App\Models\ImmigrationCase;
use App\Services\Todo\TodoCoreService;

class CaseObserver
{
    /**
     * Note: Default folder creation is handled explicitly in CaseService::createCase()
     * to avoid duplication. Do NOT add createDefaultStructure here.
     */
    public function updated(ImmigrationCase $case): void
    {
        if ($case->wasChanged('assigned_to') && $case->assigned_to) {
            app(TodoCoreService::class)->reassignPendingForCase($case, $case->assigned_to);
        }
    }
}
