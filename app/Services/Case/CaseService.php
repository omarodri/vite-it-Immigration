<?php

namespace App\Services\Case;

use App\Models\CaseType;
use App\Models\ImmigrationCase;
use App\Models\User;
use App\Repositories\Contracts\CaseRepositoryInterface;
use App\Repositories\Contracts\CaseTypeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class CaseService
{
    public function __construct(
        private CaseRepositoryInterface $caseRepository,
        private CaseTypeRepositoryInterface $caseTypeRepository
    ) {}

    /**
     * Get paginated list of cases with filters.
     */
    public function listCases(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->caseRepository->paginate($filters, $perPage);
    }

    /**
     * Get a single case with relations.
     */
    public function getCase(ImmigrationCase $case): ImmigrationCase
    {
        return $case->load(['client', 'caseType', 'assignedTo', 'companions']);
    }

    /**
     * Create a new case with auto-generated case number.
     */
    public function createCase(array $data): ImmigrationCase
    {
        return DB::transaction(function () use ($data) {
            // Extract companion_ids before creating the case
            $companionIds = $data['companion_ids'] ?? [];
            unset($data['companion_ids']);

            // Get case type
            $caseType = $this->caseTypeRepository->findById($data['case_type_id']);

            // Generate case number
            $data['case_number'] = $this->generateCaseNumber($caseType);

            // Set default values if not provided
            $data['status'] = $data['status'] ?? ImmigrationCase::STATUS_ACTIVE;
            $data['priority'] = $data['priority'] ?? ImmigrationCase::PRIORITY_MEDIUM;
            $data['progress'] = $data['progress'] ?? 0;
            $data['language'] = $data['language'] ?? 'es';

            $case = $this->caseRepository->create($data);

            // Attach companions if provided
            if (! empty($companionIds)) {
                $case->companions()->sync($companionIds);
            }

            activity()
                ->causedBy(Auth::user())
                ->performedOn($case)
                ->withProperties([
                    'case_number' => $case->case_number,
                    'client_id' => $case->client_id,
                    'case_type' => $caseType->name,
                    'companions_count' => count($companionIds),
                ])
                ->log('Created case: ' . $case->case_number);

            return $case->load(['client', 'caseType', 'assignedTo', 'companions']);
        });
    }

    /**
     * Update an existing case.
     */
    public function updateCase(ImmigrationCase $case, array $data): ImmigrationCase
    {
        return DB::transaction(function () use ($case, $data) {
            $oldValues = $case->only(array_keys($data));

            $updatedCase = $this->caseRepository->update($case, $data);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($updatedCase)
                ->withProperties([
                    'old' => $oldValues,
                    'new' => $data,
                ])
                ->log('Updated case: ' . $updatedCase->case_number);

            return $updatedCase;
        });
    }

    /**
     * Delete a case (soft delete).
     */
    public function deleteCase(ImmigrationCase $case): void
    {
        $caseNumber = $case->case_number;

        activity()
            ->causedBy(Auth::user())
            ->performedOn($case)
            ->withProperties(['case_number' => $caseNumber])
            ->log('Deleted case: ' . $caseNumber);

        $this->caseRepository->delete($case);
    }

    /**
     * Assign a case to a user.
     */
    public function assignCase(ImmigrationCase $case, int $userId): ImmigrationCase
    {
        return DB::transaction(function () use ($case, $userId) {
            $previousAssignee = $case->assignedTo;
            $newAssignee = User::findOrFail($userId);

            $case = $this->caseRepository->update($case, [
                'assigned_to' => $userId,
            ]);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($case)
                ->withProperties([
                    'previous_assignee' => $previousAssignee?->name,
                    'new_assignee' => $newAssignee->name,
                ])
                ->log('Assigned case ' . $case->case_number . ' to ' . $newAssignee->name);

            return $case;
        });
    }

    /**
     * Update the status of a case.
     */
    public function updateStatus(ImmigrationCase $case, string $status): ImmigrationCase
    {
        return DB::transaction(function () use ($case, $status) {
            $oldStatus = $case->status;

            $data = ['status' => $status];

            // If closing, set closed_at
            if ($status === ImmigrationCase::STATUS_CLOSED && $oldStatus !== ImmigrationCase::STATUS_CLOSED) {
                $data['closed_at'] = now();
            }

            // If reopening, clear closed_at
            if ($status !== ImmigrationCase::STATUS_CLOSED && $oldStatus === ImmigrationCase::STATUS_CLOSED) {
                $data['closed_at'] = null;
            }

            $case = $this->caseRepository->update($case, $data);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($case)
                ->withProperties([
                    'old_status' => $oldStatus,
                    'new_status' => $status,
                ])
                ->log('Changed status of case ' . $case->case_number . ' from ' . $oldStatus . ' to ' . $status);

            return $case;
        });
    }

    /**
     * Close a case with notes.
     */
    public function closeCase(ImmigrationCase $case, ?string $notes = null): ImmigrationCase
    {
        return DB::transaction(function () use ($case, $notes) {
            $case = $this->caseRepository->update($case, [
                'status' => ImmigrationCase::STATUS_CLOSED,
                'closed_at' => now(),
                'closure_notes' => $notes,
            ]);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($case)
                ->withProperties(['closure_notes' => $notes])
                ->log('Closed case: ' . $case->case_number);

            return $case;
        });
    }

    /**
     * Get the timeline (activity log) for a case.
     */
    public function getTimeline(ImmigrationCase $case): Collection
    {
        return Activity::forSubject($case)
            ->with('causer:id,name,email')
            ->latest()
            ->get();
    }

    /**
     * Get case statistics for dashboard.
     */
    public function getStatistics(): array
    {
        return $this->caseRepository->getStatistics();
    }

    /**
     * Get cases for a specific client.
     */
    public function getCasesByClient(int $clientId): Collection
    {
        return $this->caseRepository->getByClient($clientId);
    }

    /**
     * Get cases with upcoming hearings.
     */
    public function getUpcomingHearings(int $days = 30): Collection
    {
        return $this->caseRepository->getUpcomingHearings($days);
    }

    /**
     * Generate a unique case number.
     * Format: {YEAR}-{TYPE_CODE}-{SEQUENCE}
     * Example: 2026-ASYLUM-00042
     */
    private function generateCaseNumber(CaseType $caseType): string
    {
        $year = date('Y');
        $sequence = $this->caseRepository->getNextSequence($caseType, $year);

        $caseNumber = sprintf('%s-%s-%05d', $year, $caseType->code, $sequence);

        // Ensure uniqueness (in case of race condition)
        while ($this->caseRepository->existsByCaseNumber($caseNumber)) {
            $sequence++;
            $caseNumber = sprintf('%s-%s-%05d', $year, $caseType->code, $sequence);
        }

        return $caseNumber;
    }
}
