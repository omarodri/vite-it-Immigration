<?php

namespace App\Repositories\Contracts;

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
     * Get the next global sequence number for a tenant.
     * Derives the MAX from the last numeric segment of all existing case_numbers.
     */
    public function getNextSequence(int $tenantId): int;

    /**
     * Check if a case number already exists.
     */
    public function existsByCaseNumber(string $caseNumber): bool;

    /**
     * Get cases with upcoming deadlines within N days.
     */
    public function getUpcomingDeadlines(int $days = 30): Collection;

    /**
     * Get case statistics for dashboard.
     */
    public function getStatistics(): array;
}
