<?php

namespace App\Services\Case;

use App\Helpers\CaseCodeHelper;
use App\Models\CaseType;
use App\Models\Client;
use App\Models\ImmigrationCase;
use App\Models\User;
use App\Repositories\Contracts\CaseRepositoryInterface;
use App\Repositories\Contracts\CaseTypeRepositoryInterface;
use App\Services\Document\CaseFolderSyncService;
use App\Services\Document\FolderService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

class CaseService
{
    public function __construct(
        private CaseRepositoryInterface $caseRepository,
        private CaseTypeRepositoryInterface $caseTypeRepository,
        private CaseTaskService $caseTaskService,
        private FolderService $folderService,
        private CaseFolderSyncService $caseFolderSyncService,
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
        return $case->load(['client', 'caseType', 'assignedTo.profile', 'companions', 'importantDates', 'tasks', 'invoices']);
    }

    /**
     * Create a new case with auto-generated case number.
     */
    public function createCase(array $data): ImmigrationCase
    {
        $case = DB::transaction(function () use ($data) {
            // Extract companion_ids before creating the case
            $companionIds = $data['companion_ids'] ?? [];
            unset($data['companion_ids']);

            // Extract case_tasks before creating the case
            $caseTasks = $data['case_tasks'] ?? [];
            unset($data['case_tasks']);

            // Extract important_dates before creating the case
            $importantDatesData = array_key_exists('important_dates', $data) && !empty($data['important_dates'])
                ? $data['important_dates']
                : $this->getDefaultImportantDates();
            unset($data['important_dates']);

            // Get case type and client (both required to build the new case number)
            $caseType = $this->caseTypeRepository->findById($data['case_type_id']);
            $client   = Client::findOrFail($data['client_id']);

            // Generate case number with new format: YY-TYPE-LAST4-SEQUENCE
            $data['case_number'] = $this->generateCaseNumber($caseType, $client);

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

            // Create important dates
            $datesWithCaseId = array_map(function ($date) use ($case) {
                return array_merge($date, ['case_id' => $case->id]);
            }, $importantDatesData);

            $case->importantDates()->createMany($datesWithCaseId);

            // Sync case tasks if provided
            if (! empty($caseTasks)) {
                $this->caseTaskService->syncTasks($case, $caseTasks);
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

            return $case->load(['client', 'caseType', 'assignedTo.profile', 'companions', 'importantDates', 'tasks']);
        });

        // Create default folder structure AFTER transaction commits.
        // Runs outside the transaction so folder creation errors
        // do not prevent the case from being created.
        try {
            $this->folderService->createDefaultStructure($case);
        } catch (\Throwable $e) {
            Log::error('CaseService: Failed to create default folder structure', [
                'case_id' => $case->id,
                'case_number' => $case->case_number,
                'error' => $e->getMessage(),
            ]);
        }

        // Sync folders to cloud synchronously (no queue dependency).
        // For local storage, folders are already synced in createDefaultStructure().
        $tenant = $case->tenant ?? \App\Models\Tenant::find($case->tenant_id);
        $storageType = $tenant->storage_type ?? 'local';

        if (in_array($storageType, ['onedrive', 'google_drive', 'sharepoint'], true)) {
            try {
                $this->caseFolderSyncService->syncFolderStructure($case);
            } catch (\Throwable $e) {
                Log::error('CaseService: Failed to sync folders to cloud', [
                    'case_id' => $case->id,
                    'storage_type' => $storageType,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $case;
    }

    /**
     * Update an existing case.
     */
    public function updateCase(ImmigrationCase $case, array $data): ImmigrationCase
    {
        return DB::transaction(function () use ($case, $data) {
            $companionIds = array_key_exists('companion_ids', $data) ? $data['companion_ids'] : null;
            unset($data['companion_ids']);

            // Extract important_dates before updating
            $importantDates = array_key_exists('important_dates', $data) ? $data['important_dates'] : null;
            unset($data['important_dates']);

            // Extract case_tasks before updating
            $hasCaseTasks = array_key_exists('case_tasks', $data);
            $caseTasks = $hasCaseTasks ? $data['case_tasks'] : null;
            unset($data['case_tasks']);

            $oldCompanionIds = $companionIds !== null ? $case->companions()->pluck('companions.id')->toArray() : null;

            $oldValues = $case->only(array_keys($data));

            $updatedCase = $this->caseRepository->update($case, $data);

            if ($companionIds !== null) {
                $updatedCase->companions()->sync($companionIds);
            }

            // Handle important dates (replace strategy)
            if ($importantDates !== null) {
                $updatedCase->importantDates()->delete();
                if (!empty($importantDates)) {
                    $datesWithCaseId = array_map(fn ($d) => array_merge($d, ['case_id' => $updatedCase->id]), $importantDates);
                    $updatedCase->importantDates()->createMany($datesWithCaseId);
                }
            }

            // Handle case tasks (replace strategy)
            if ($hasCaseTasks) {
                $this->caseTaskService->syncTasks($updatedCase, $caseTasks ?? []);
            }

            activity()
                ->causedBy(Auth::user())
                ->performedOn($updatedCase)
                ->withProperties(array_filter([
                    'old' => $oldValues,
                    'new' => $data,
                    'old_companion_ids' => $oldCompanionIds,
                    'new_companion_ids' => $companionIds,
                ], fn ($v) => $v !== null))
                ->log('Updated case: ' . $updatedCase->case_number);

            return $updatedCase->load(['client', 'caseType', 'assignedTo.profile', 'companions', 'importantDates', 'tasks']);
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
     * Get cases with upcoming deadlines (important dates within N days).
     */
    public function getUpcomingDeadlines(int $days = 30): Collection
    {
        return $this->caseRepository->getUpcomingDeadlines($days);
    }

    /**
     * Get the default important dates for a new case.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getDefaultImportantDates(): array
    {
        return [
            ['label' => 'Fecha de inicio',     'due_date' => now()->format('Y-m-d'), 'sort_order' => 0],
            ['label' => 'Fecha limite legal',   'due_date' => null,                   'sort_order' => 1],
            ['label' => 'Fecha de envio IRCC',  'due_date' => null,                   'sort_order' => 2],
            ['label' => 'Fecha de decision',    'due_date' => null,                   'sort_order' => 3],
        ];
    }

    /**
     * Generate a unique case number.
     * Format: {YY}-{TYPE_CODE}-{LAST4}-{SEQUENCE4}
     * Example: 26-RT-RODR-0060
     *
     * The consecutive is scoped to (year2 + type_code + last_name_slug) so two
     * clients with the same 4-letter slug share the same counter, which is correct.
     * A retry loop with up to 5 attempts handles race conditions; after that an
     * exception is raised so the transaction rolls back cleanly.
     */
    private function generateCaseNumber(CaseType $caseType, Client $client): string
    {
        $year2        = date('y');
        $lastNameSlug = CaseCodeHelper::normalizeLastName($client->last_name);
        $sequence     = $this->caseRepository->getNextSequence($caseType, $year2, $lastNameSlug);

        $caseNumber = sprintf('%s-%s-%s-%04d', $year2, $caseType->code, $lastNameSlug, $sequence);

        // Safety net: retry up to 5 times on race-condition collisions
        $attempts = 0;
        while ($this->caseRepository->existsByCaseNumber($caseNumber) && $attempts < 5) {
            $sequence++;
            $attempts++;
            $caseNumber = sprintf('%s-%s-%s-%04d', $year2, $caseType->code, $lastNameSlug, $sequence);
        }

        if ($this->caseRepository->existsByCaseNumber($caseNumber)) {
            throw new \RuntimeException(
                "No se pudo generar un código único para el expediente después de {$attempts} intentos."
            );
        }

        return $caseNumber;
    }
}
