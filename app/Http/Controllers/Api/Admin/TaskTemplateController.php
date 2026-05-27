<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\CloneTaskTemplateRequest;
use App\Http\Requests\Workflow\StoreTaskTemplateRequest;
use App\Http\Requests\Workflow\UpdateTaskTemplateRequest;
use App\Http\Resources\TaskTemplateResource;
use App\Models\TaskTemplate;
use App\Models\WorkflowStage;
use App\Services\Workflow\TaskTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskTemplateController extends Controller
{
    public function __construct(private TaskTemplateService $service) {}

    public function index(WorkflowStage $stage): JsonResponse
    {
        $this->authorize('viewAny', TaskTemplate::class);
        $templates = $stage->taskTemplates()->with('translations')->orderBy('sort_order')->get();
        return response()->json(['data' => TaskTemplateResource::collection($templates)]);
    }

    public function store(StoreTaskTemplateRequest $request, WorkflowStage $stage): JsonResponse
    {
        $this->authorize('create', TaskTemplate::class);
        $data = $request->validated();
        $data['workflow_stage_id'] = $stage->id;
        $template = $this->service->create($data);
        return response()->json(['data' => new TaskTemplateResource($template)], 201);
    }

    public function update(UpdateTaskTemplateRequest $request, TaskTemplate $template): JsonResponse
    {
        $this->authorize('update', $template);
        $template = $this->service->update($template, $request->validated());
        return response()->json(['data' => new TaskTemplateResource($template)]);
    }

    public function destroy(TaskTemplate $template): JsonResponse
    {
        $this->authorize('delete', $template);
        $this->service->delete($template);
        return response()->json(['message' => 'Template deleted']);
    }

    public function reorder(Request $request, WorkflowStage $stage): JsonResponse
    {
        $this->authorize('update', TaskTemplate::class);
        $request->validate([
            'ordered_ids'   => ['required', 'array'],
            'ordered_ids.*' => ['integer', 'exists:task_templates,id'],
        ]);
        $this->service->reorder($stage->id, $request->input('ordered_ids'));
        return response()->json(['message' => 'Templates reordered']);
    }

    public function clone(CloneTaskTemplateRequest $request, TaskTemplate $template): JsonResponse
    {
        $this->authorize('clone', $template);
        $cloned = $this->service->clone(
            $template,
            (int) $request->validated('target_stage_id')
        );
        return response()->json(['data' => new TaskTemplateResource($cloned)], 201);
    }
}
