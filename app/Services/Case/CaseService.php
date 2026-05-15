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
use App\Services\Workflow\WorkflowInstantiator;
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
        private WorkflowInstantiator $workflowInstantiator,
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
        return $case->load([
            'client',
            'primaryApplicantCompanion',
            'caseType',
            'currentStage.translations',
            'assignedTo.profile',
            'companions.promotedClient',
            'importantDates',
            'tasks.template.translations',
            'tasks.workflowStage.translations',
            'invoices',
        ]);
    }

    /**
     * Create a new case with auto-generated case number.
     */
    public function createCase(array $data): ImmigrationCase
    {
        // Extract folder selection before the transaction — used after case creation
        $foldersInput = array_key_exists('folders', $data) ? $data['folders'] : null;
        unset($data['folders']);

        $case = DB::transaction(function () use ($data) {
            // Extract companion_ids before creating the case
            $companionIds = $data['companion_ids'] ?? [];
            unset($data['companion_ids']);

            // Workflow: excluded template ids (optional templates the user opted out of)
            $excludedTemplateIds = $data['excluded_template_ids'] ?? [];
            unset($data['excluded_template_ids']);

            // Legacy: case_tasks input is ignored (workflow drives task creation now)
            unset($data['case_tasks']);

            // Extract important_dates before creating the case
            $importantDatesData = array_key_exists('important_dates', $data) && !empty($data['important_dates'])
                ? $data['important_dates']
                : $this->getDefaultImportantDates();
            unset($data['important_dates']);

            // Get case type and client (both required to build the new case number)
            $caseType = $this->caseTypeRepository->findById($data['case_type_id']);
            $client   = Client::findOrFail($data['client_id']);

            // Resolver apellido del aplicante principal para el código de expediente
            $primaryApplicantType        = $data['primary_applicant_type'] ?? 'client';
            $primaryApplicantCompanionId = $data['primary_applicant_companion_id'] ?? null;

            $primaryLastName = $client->last_name;
            if ($primaryApplicantType === 'companion' && $primaryApplicantCompanionId) {
                $primaryCompanion = \App\Models\Companion::find($primaryApplicantCompanionId);
                if ($primaryCompanion) {
                    $primaryLastName = $primaryCompanion->last_name;
                }
            }

            $data['case_number'] = $this->generateCaseNumber($caseType, $primaryLastName);

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

            // Materialize workflow stages + tasks from templates (no-op if no workflow defined for this case_type)
            $this->workflowInstantiator->instantiate($case, $excludedTemplateIds);

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

            return $case->load(['client', 'caseType', 'currentStage.translations',
                                'assignedTo.profile', 'companions', 'importantDates',
                                'tasks.template.translations']);
        });

        // Step 1: Create folder records in the DB (fast, no external calls).
        try {
            $this->folderService->createSelectedStructure($case, $foldersInput);
        } catch (\Throwable $e) {
            Log::error('CaseService: Failed to create default folder structure', [
                'case_id' => $case->id,
                'case_number' => $case->case_number,
                'error' => $e->getMessage(),
            ]);
        }

        // Step 2: Dispatch cloud sync to the queue — never block the HTTP request on
        // external API calls (Guzzle timeouts cause PHP fatal errors uncatchable by try-catch).
        // SyncCaseFolderStructure handles local vs cloud and retries on failure.
        \App\Jobs\SyncCaseFolderStructure::dispatch($case->id);

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
     * Example: 26-RT-RODR-0042
     *
     * NNNN is a global per-tenant counter (Spec 55): a single monotonic integer
     * that advances regardless of case type or client last name.
     * A retry loop with up to 5 attempts handles race conditions; after that an
     * exception is raised so the transaction rolls back cleanly.
     */
    private function generateCaseNumber(CaseType $caseType, string $primaryLastName): string
    {
        $year2        = date('y');
        $lastNameSlug = CaseCodeHelper::normalizeLastName($primaryLastName);
        $sequence     = $this->caseRepository->getNextSequence(Auth::user()->tenant_id);

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
