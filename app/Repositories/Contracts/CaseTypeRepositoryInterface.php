<?php

namespace App\Repositories\Contracts;

use App\Models\CaseType;
use Illuminate\Support\Collection;

interface CaseTypeRepositoryInterface
{
    /**
     * Find a case type by ID.
     */
    public function findById(int $id): ?CaseType;

    /**
     * Get all active case types for a tenant (includes global types).
     */
    public function getActive(int $tenantId): Collection;

    /**
     * Get case types by category for a tenant.
     */
    public function getByCategory(string $category, int $tenantId): Collection;

    /**
     * Find a case type by its code.
     */
    public function findByCode(string $code): ?CaseType;
}
