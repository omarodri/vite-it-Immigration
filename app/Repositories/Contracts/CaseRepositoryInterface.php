<?php

namespace App\Repositories\Contracts;

use App\Models\CaseType;
use App\Models\ImmigrationCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CaseRepositoryInterface
{
    /**
     * Find a case by ID with relations.
     */
    public function findById(int $id): ?ImmigrationCase;

    /**
     * Get paginated list of cases with filters.
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Create a new case.
     */
    public function create(array $data): ImmigrationCase;

    /**
     * Update an existing case.
     */
    public function update(ImmigrationCase $case, array $data): ImmigrationCase;

    /**
     * Delete a case (soft delete).
     */
    public function delete(ImmigrationCase $case): bool;

    /**
     * Get all cases for a specific client.
     */
    public function getByClient(int $clientId): Collection;

    /**
     * Count cases by status.
     */
    public function countByStatus(string $status): int;

    /**
     * Count cases by priority.
     */
    public function countByPriority(string $priority): int;

    /**
     * Get the next sequence number for a case type in a given year.
     */
    public function getNextSequence(CaseType $caseType, int $year): int;

    /**
     * Check if a case number already exists.
     */
    public function existsByCaseNumber(string $caseNumber): bool;

    /**
     * Get cases with upcoming hearings within N days.
     */
    public function getUpcomingHearings(int $days = 30): Collection;

    /**
     * Get case statistics for dashboard.
     */
    public function getStatistics(): array;
}
