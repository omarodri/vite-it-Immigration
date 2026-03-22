<?php

namespace App\Observers;

use App\Models\ImmigrationCase;

class CaseObserver
{
    /**
     * Note: Default folder creation is handled explicitly in CaseService::createCase()
     * to avoid duplication. Do NOT add createDefaultStructure here.
     */
}
