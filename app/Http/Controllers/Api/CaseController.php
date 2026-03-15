<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Case\AssignCaseRequest;
use App\Http\Requests\Case\StoreCaseRequest;
use App\Http\Requests\Case\UpdateCaseRequest;
use App\Http\Resources\CaseResource;
use App\Models\ImmigrationCase;
use App\Services\Case\CaseService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CaseController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private CaseService $caseService
    ) {}

    /**
     * List all cases with filters and pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ImmigrationCase::class);

        $filters = $request->only([
            'search',
            'status',
            'priority',
            'case_type_id',
            'assigned_to',
            'client_id',
            'hearing_from',
            'hearing_to',
            'stage',
            'ircc_status',
            'service_type',
            'sort_by',
            'sort_direction',
        ]);

        $perPage = $request->integer('per_page', 15);

        $cases = $this->caseService->listCases($filters, $perPage);

        return CaseResource::collection($cases);
    }

    /**
     * Store a new case.
     */
    public function store(StoreCaseRequest $request): JsonResponse
    {
        $this->authorize('create', ImmigrationCase::class);

        $case = $this->caseService->createCase($request->validated());

        return (new CaseResource($case))
            ->additional(['message' => 'Case created successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display a specific case.
     */
    public function show(ImmigrationCase $case): CaseResource
    {
        $this->authorize('view', $case);

        return new CaseResource($this->caseService->getCase($case));
    }

    /**
     * Update a case.
     */
    public function update(UpdateCaseRequest $request, ImmigrationCase $case): CaseResource
    {
        $this->authorize('update', $case);

        $case = $this->caseService->updateCase($case, $request->validated());

        return (new CaseResource($case))
            ->additional(['message' => 'Case updated successfully.']);
    }

    /**
     * Delete a case (soft delete).
     */
    public function destroy(ImmigrationCase $case): JsonResponse
    {
        $this->authorize('delete', $case);

        $this->caseService->deleteCase($case);

        return response()->json(['message' => 'Case deleted successfully.']);
    }

    /**
     * Assign a case to a user.
     */
    public function assign(AssignCaseRequest $request, ImmigrationCase $case): CaseResource
    {
        $this->authorize('assign', $case);

        $case = $this->caseService->assignCase($case, $request->validated()['assigned_to']);

        return (new CaseResource($case))
            ->additional(['message' => 'Case assigned successfully.']);
    }

    /**
     * Get the timeline (activity log) for a case.
     */
    public function timeline(ImmigrationCase $case): JsonResponse
    {
        $this->authorize('viewTimeline', $case);

        $timeline = $this->caseService->getTimeline($case);

        return response()->json([
                'data' => $timeline->map(fn ($activity) => [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'description' => $activity->description,
                'properties' => $activity->properties,
                'created_at' => $activity->created_at->toISOString(),
                'causer' => $activity->causer ? [
                    'id' => $activity->causer->id,
                    'name' => $activity->causer->name,
                ] : null,
            ]),
        ]);
    }

    /**
     * Get case statistics for dashboard.
     */
    public function statistics(): JsonResponse
    {
        $this->authorize('viewAny', ImmigrationCase::class);

        $statistics = $this->caseService->getStatistics();

        return response()->json(['data' => $statistics]);
    }
}
