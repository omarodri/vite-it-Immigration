<?php

namespace App\Repositories\Eloquent;

use App\Models\ImmigrationCase;
use App\Repositories\Contracts\CaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CaseRepository implements CaseRepositoryInterface
{
    /**
     * Find a case by ID with relations.
     */
    public function findById(int $id): ?ImmigrationCase
    {
        return ImmigrationCase::with(['client', 'caseType', 'assignedTo', 'importantDates', 'tasks'])
            ->find($id);
    }

    /**
     * Get paginated list of cases with filters.
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ImmigrationCase::with([
            'client:id,tenant_id,first_name,last_name,email,phone',
            'caseType:id,name,code,category',
            'assignedTo:id,name,email',
            'importantDates',
            'currentCaseStage.translations',
        ]);

        // Apply filters
        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->byPriority($filters['priority']);
        }

        if (! empty($filters['case_type_id'])) {
            $query->byCaseType($filters['case_type_id']);
        }

        if (! empty($filters['assigned_to'])) {
            $query->byAssignee($filters['assigned_to']);
        }

        if (! empty($filters['client_id'])) {
            $query->byClient($filters['client_id']);
        }

        if (! empty($filters['stage'])) {
            $query->byStage($filters['stage']);
        }

        if (! empty($filters['current_case_stage_id'])) {
            $query->where('current_case_stage_id', $filters['current_case_stage_id']);
        }

        if (! empty($filters['ircc_status'])) {
            $query->byIrccStatus($filters['ircc_status']);
        }

        if (! empty($filters['service_type'])) {
            $query->byServiceType($filters['service_type']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereHas('importantDates', function ($q) use ($filters) {
                $q->whereDate('due_date', '>=', $filters['date_from']);
            });
        }

        if (! empty($filters['date_to'])) {
            $query->whereHas('importantDates', function ($q) use ($filters) {
                $q->whereDate('due_date', '<=', $filters['date_to']);
            });
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        // Validate sort column to prevent SQL injection
        $allowedSortColumns = [
            'case_number',
            'status',
            'priority',
            'progress',
            'stage',
            'service_type',
            'fees',
            'created_at',
            'updated_at',
        ];

        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new case.
     */
    public function create(array $data): ImmigrationCase
    {
        // Ensure tenant_id is set from authenticated user
        if (! isset($data['tenant_id']) && Auth::check()) {
            $data['tenant_id'] = Auth::user()->tenant_id;
        }

        return ImmigrationCase::create($data);
    }

    /**
     * Update an existing case.
     */
    public function update(ImmigrationCase $case, array $data): ImmigrationCase
    {
        $case->update($data);

        return $case->fresh(['client', 'caseType', 'assignedTo']);
    }

    /**
     * Delete a case (soft delete).
     */
    public function delete(ImmigrationCase $case): bool
    {
        return $case->delete();
    }

    /**
     * Get all cases for a specific client.
     */
    public function getByClient(int $clientId): Collection
    {
        return ImmigrationCase::with(['caseType', 'assignedTo'])
            ->byClient($clientId)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Count cases by status.
     */
    public function countByStatus(string $status): int
    {
        return ImmigrationCase::byStatus($status)->count();
    }

    /**
     * Count cases by priority.
     */
    public function countByPriority(string $priority): int
    {
        return ImmigrationCase::byPriority($priority)->count();
    }

    /**
     * Get the next global sequence number for a tenant (Spec 55).
     *
     * Scope: all cases for the tenant, including soft-deleted ones.
     * Extracts the numeric last segment of each case_number to find the MAX,
     * so legacy case_numbers with the old scoped format are handled transparently.
     * withTrashed() prevents a deleted case from freeing its number for reuse.
     */
    public function getNextSequence(int $tenantId): int
    {
        $max = ImmigrationCase::withTrashed()
            ->where('tenant_id', $tenantId)
            ->get()
            ->map(fn ($case) => (int) last(explode('-', (string) $case->case_number)))
            ->max();

        return (int) ($max ?? 0) + 1;
    }

    /**
     * Check if a case number already exists.
     */
    public function existsByCaseNumber(string $caseNumber): bool
    {
        return ImmigrationCase::withTrashed()
            ->where('case_number', $caseNumber)
            ->exists();
    }

    /**
     * Get cases with upcoming deadlines within N days.
     */
    public function getUpcomingDeadlines(int $days = 30): Collection
    {
        return ImmigrationCase::with(['client:id,tenant_id,first_name,last_name', 'caseType:id,name,code', 'importantDates'])
            ->upcoming($days)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Get case statistics for dashboard.
     */
    public function getStatistics(): array
    {
        return [
            'total' => ImmigrationCase::count(),
            'by_status' => [
                'active' => $this->countByStatus(ImmigrationCase::STATUS_ACTIVE),
                'inactive' => $this->countByStatus(ImmigrationCase::STATUS_INACTIVE),
                'archived' => $this->countByStatus(ImmigrationCase::STATUS_ARCHIVED),
                'closed' => $this->countByStatus(ImmigrationCase::STATUS_CLOSED),
            ],
            'by_priority' => [
                'urgent' => $this->countByPriority(ImmigrationCase::PRIORITY_URGENT),
                'high' => $this->countByPriority(ImmigrationCase::PRIORITY_HIGH),
                'medium' => $this->countByPriority(ImmigrationCase::PRIORITY_MEDIUM),
                'low' => $this->countByPriority(ImmigrationCase::PRIORITY_LOW),
            ],
            'by_stage' => ImmigrationCase::whereNotNull('stage')
                ->selectRaw('stage, count(*) as count')
                ->groupBy('stage')
                ->pluck('count', 'stage')
                ->toArray(),
            'upcoming_deadlines' => ImmigrationCase::upcoming(30)->count(),
            'unassigned' => ImmigrationCase::unassigned()->count(),
        ];
    }
}
