<?php

namespace App\Observers;

use App\Models\ImmigrationCase;
use App\Services\Document\FolderService;

class CaseObserver
{
    public function __construct(
        protected FolderService $folderService
    ) {}

    /**
     * Handle the ImmigrationCase "created" event.
     * Creates the default folder structure for document management.
     */
    public function created(ImmigrationCase $case): void
    {
        $this->folderService->createDefaultStructure($case);
    }
}
