<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\StoreWorkflowStageRequest;
use App\Http\Requests\Workflow\UpdateWorkflowStageRequest;
use App\Http\Resources\WorkflowStageResource;
use App\Models\CaseType;
use App\Models\WorkflowStage;
use App\Services\Workflow\WorkflowStageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowStageController extends Controller
{
    public function __construct(private WorkflowStageService $service) {}

    public function index(CaseType $caseType): JsonResponse
    {
        $this->authorize('viewAny', WorkflowStage::class);
        $stages = $this->service->listByCaseType($caseType->id);
        return response()->json(['data' => WorkflowStageResource::collection($stages)]);
    }

    public function store(StoreWorkflowStageRequest $request, CaseType $caseType): JsonResponse
    {
        $this->authorize('create', WorkflowStage::class);
        $data = $request->validated();
        $data['case_type_id'] = $caseType->id;
        $stage = $this->service->create($data);
        return response()->json(['data' => new WorkflowStageResource($stage)], 201);
    }

    public function update(UpdateWorkflowStageRequest $request, WorkflowStage $stage): JsonResponse
    {
        $this->authorize('update', $stage);
        $stage = $this->service->update($stage, $request->validated());
        return response()->json(['data' => new WorkflowStageResource($stage)]);
    }

    public function destroy(WorkflowStage $stage): JsonResponse
    {
        $this->authorize('delete', $stage);
        $this->service->delete($stage);
        return response()->json(['message' => 'Stage deleted']);
    }

    public function reorder(Request $request, CaseType $caseType): JsonResponse
    {
        $this->authorize('create', WorkflowStage::class);
        $request->validate([
            'ordered_ids'   => ['required', 'array'],
            'ordered_ids.*' => ['integer', 'exists:workflow_stages,id'],
        ]);
        $this->service->reorder($caseType->id, $request->input('ordered_ids'));
        return response()->json(['message' => 'Stages reordered']);
    }
}
