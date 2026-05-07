<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\CreateCaseStageRequest;
use App\Http\Requests\Workflow\DeleteCaseStageRequest;
use App\Http\Requests\Workflow\ReorderCaseStagesRequest;
use App\Http\Requests\Workflow\UpdateCaseStageRequest;
use App\Http\Resources\CaseStageResource;
use App\Models\CaseStage;
use App\Models\ImmigrationCase;
use App\Services\Workflow\CaseStageService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CaseStageController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CaseStageService $service) {}

    public function index(ImmigrationCase $case): AnonymousResourceCollection
    {
        $this->authorize('view', $case);

        return CaseStageResource::collection($this->service->listForCase($case));
    }

    public function store(CreateCaseStageRequest $request, ImmigrationCase $case): CaseStageResource
    {
        $this->authorize('update', $case);

        $stage = $this->service->createAdHocStage($case, $request->validated());

        return new CaseStageResource($stage);
    }

    public function update(UpdateCaseStageRequest $request, ImmigrationCase $case, CaseStage $stage): CaseStageResource
    {
        $this->authorize('update', $case);
        abort_unless($stage->case_id === $case->id, 404);

        return new CaseStageResource($this->service->updateStage($stage, $request->validated()));
    }

    public function reorder(ReorderCaseStagesRequest $request, ImmigrationCase $case): AnonymousResourceCollection
    {
        $this->authorize('update', $case);

        $ids = array_map('intval', $request->validated()['stage_ids']);

        return CaseStageResource::collection($this->service->reorderStages($case, $ids));
    }

    public function destroy(DeleteCaseStageRequest $request, ImmigrationCase $case, CaseStage $stage): Response|JsonResponse
    {
        $this->authorize('update', $case);
        abort_unless($stage->case_id === $case->id, 404);

        $data = $request->validated();
        $this->service->deleteStage(
            $case,
            $stage,
            $data['policy'],
            isset($data['move_to_stage_id']) ? (int) $data['move_to_stage_id'] : null
        );

        return response()->noContent();
    }
}
