<?php

namespace App\Repositories\Eloquent;

use App\Models\CaseType;
use App\Repositories\Contracts\CaseTypeRepositoryInterface;
use Illuminate\Support\Collection;

class CaseTypeRepository implements CaseTypeRepositoryInterface
{
    /**
     * Find a case type by ID.
     */
    public function findById(int $id): ?CaseType
    {
        return CaseType::find($id);
    }

    /**
     * Get all active case types for a tenant (includes global types).
     */
    public function getActive(int $tenantId): Collection
    {
        return CaseType::active()
            ->globalOrTenant($tenantId)
            ->orderBy('category')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get case types by category for a tenant.
     */
    public function getByCategory(string $category, int $tenantId): Collection
    {
        return CaseType::active()
            ->byCategory($category)
            ->globalOrTenant($tenantId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Find a case type by its code.
     */
    public function findByCode(string $code): ?CaseType
    {
        return CaseType::where('code', $code)->first();
    }
}
