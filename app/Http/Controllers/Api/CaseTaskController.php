<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Case\BulkUpdateCaseTasksRequest;
use App\Http\Requests\Workflow\CreateAdHocTaskRequest;
use App\Http\Requests\Workflow\MoveTaskRequest;
use App\Http\Requests\Workflow\UpdateWorkflowTaskCoreRequest;
use App\Http\Resources\CaseResource;
use App\Http\Resources\CaseTaskResource;
use App\Http\Resources\TaskResource;
use App\Models\CaseTask;
use App\Models\ImmigrationCase;
use App\Models\Task;
use App\Services\Case\CaseTaskService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseTaskController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CaseTaskService $caseTaskService
    ) {}

    /**
     * PUT /api/cases/{case}/tasks
     * Replace all tasks for a case (delete-and-insert).
     */
    public function bulkUpdate(BulkUpdateCaseTasksRequest $request, ImmigrationCase $case): JsonResponse
    {
        $this->caseTaskService->syncTasks($case, $request->validated()['tasks']);

        $case->load(['client', 'caseType', 'assignedTo', 'companions', 'importantDates', 'tasks']);

        return response()->json([
            'data' => new CaseResource($case),
            'message' => 'Tasks updated successfully.',
        ]);
    }

    /**
     * PATCH /api/cases/{case}/tasks/{task}/toggle
     * Toggle is_completed for a single task.
     */
    public function toggle(ImmigrationCase $case, CaseTask $task): JsonResponse
    {
        // Verify task belongs to case
        if ($task->case_id !== $case->id) {
            return response()->json(['message' => 'Task not found for this case.'], 404);
        }

        $this->authorize('update', $case);

        $updatedTask = $this->caseTaskService->toggleTask($case, $task);

        // Refresh progress from DB
        $case->refresh();

        return response()->json([
            'data' => new CaseTaskResource($updatedTask),
            'meta' => ['case_progress' => $case->progress],
            'message' => 'Task toggled successfully.',
        ]);
    }

    /**
     * PATCH /api/cases/{case}/workflow-tasks/{workflowTask}/toggle
     * Toggle status for a workflow task (new tasks table): new/in_progress ↔ resolved.
     */
    public function toggleWorkflowTask(ImmigrationCase $case, Task $workflowTask): JsonResponse
    {
        if ($workflowTask->case_id !== $case->id) {
            return response()->json(['message' => 'Task not found for this case.'], 404);
        }

        $this->authorize('update', $case);

        $isCompleted = in_array($workflowTask->status, ['resolved', 'closed'], true);
        $workflowTask->status = $isCompleted ? 'new' : 'resolved';
        $workflowTask->save();

        // Recalculate and persist case progress
        $total = $case->tasks()->count();
        $done  = $case->tasks()->whereIn('status', ['resolved', 'closed'])->count();
        $progress = $total > 0 ? (int) round(($done / $total) * 100) : 0;
        $case->update(['progress' => $progress]);

        return response()->json([
            'data' => new TaskResource($workflowTask),
            'meta' => ['case_progress' => $progress],
            'message' => 'Workflow task toggled successfully.',
        ]);
    }

    /**
     * PATCH /api/cases/{case}/workflow-tasks/reorder
     * Persist new sort_order for workflow tasks.
     * Body: { tasks: [{id: 1, sort_order: 0}, ...] }
     */
    public function reorderWorkflowTasks(Request $request, ImmigrationCase $case): JsonResponse
    {
        $this->authorize('update', $case);

        $validated = $request->validate([
            'tasks'             => ['required', 'array'],
            'tasks.*.id'        => ['required', 'integer'],
            'tasks.*.sort_order'=> ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['tasks'] as $item) {
            $case->tasks()
                ->where('id', $item['id'])
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['message' => 'Tasks reordered successfully.']);
    }

    /**
     * PATCH /api/cases/{case}/workflow-tasks/{workflowTask}/move
     * Move a workflow task within or between stages.
     */
    public function moveWorkflowTask(MoveTaskRequest $request, ImmigrationCase $case, Task $workflowTask): JsonResponse
    {
        if ($workflowTask->case_id !== $case->id) {
            return response()->json(['message' => 'Task not found for this case.'], 404);
        }

        $this->authorize('update', $case);

        $data = $request->validated();

        $task = $this->caseTaskService->moveTask(
            $case,
            $workflowTask,
            (int) $data['source_stage_id'],
            (int) $data['target_stage_id'],
            (int) $data['target_index'],
        );

        return response()->json([
            'data'    => new TaskResource($task),
            'message' => 'Task moved successfully.',
        ]);
    }

    /**
     * POST /api/cases/{case}/workflow-tasks
     * Create an ad-hoc workflow task (task_template_id NULL).
     */
    public function createWorkflowTask(CreateAdHocTaskRequest $request, ImmigrationCase $case): JsonResponse
    {
        $this->authorize('update', $case);

        $task = $this->caseTaskService->createAdHocTask($case, $request->validated());

        if ($request->boolean('create_todo_core', true)) {
            app(\App\Services\Todo\TodoCoreService::class)->createForTask($task, 'workflow_add');
        }

        return response()->json([
            'data'    => new TaskResource($task),
            'message' => 'Task created successfully.',
        ], 201);
    }

    /**
     * DELETE /api/cases/{case}/workflow-tasks/{workflowTask}
     * Soft-delete a workflow task (sends to trash).
     */
    public function deleteWorkflowTask(Request $request, ImmigrationCase $case, Task $workflowTask): JsonResponse
    {
        if ($workflowTask->case_id !== $case->id) {
            return response()->json(['message' => 'Task not found for this case.'], 404);
        }

        $this->authorize('update', $case);

        $strategy = $request->input('cascade_todo', 'soft_delete');
        app(\App\Services\Todo\TodoCoreService::class)->removeForTask($workflowTask, $strategy);

        $this->caseTaskService->softDeleteTask($case, $workflowTask);

        return response()->json([
            'message' => 'Task moved to trash.',
        ]);
    }

    /**
     * POST /api/cases/{case}/workflow-tasks/{workflowTaskId}/restore
     * Restore a soft-deleted workflow task.
     */
    public function restoreWorkflowTask(ImmigrationCase $case, int $workflowTaskId): JsonResponse
    {
        $this->authorize('update', $case);

        // Verify the trashed task belongs to this case (also enforces tenant scope).
        $exists = Task::onlyTrashed()
            ->where('case_id', $case->id)
            ->where('id', $workflowTaskId)
            ->exists();

        if (! $exists) {
            return response()->json(['message' => 'Task not found for this case.'], 404);
        }

        $task = $this->caseTaskService->restoreTask($case, $workflowTaskId);

        return response()->json([
            'data'    => new TaskResource($task),
            'message' => 'Task restored successfully.',
        ]);
    }

    /**
     * GET /api/cases/{case}/workflow-tasks/trashed
     * List soft-deleted workflow tasks for a case.
     */
    public function listTrashedWorkflowTasks(ImmigrationCase $case): JsonResponse
    {
        $this->authorize('update', $case);

        $tasks = Task::onlyTrashed()
            ->where('case_id', $case->id)
            ->orderByDesc('deleted_at')
            ->get();

        return response()->json([
            'data' => TaskResource::collection($tasks),
        ]);
    }

    /**
     * PATCH /api/cases/{case}/workflow-tasks/{workflowTask}/update-core
     * Update core editable fields (subject, description, priority, assigned_to).
     */
    public function updateWorkflowTaskCore(
        UpdateWorkflowTaskCoreRequest $request,
        ImmigrationCase $case,
        Task $workflowTask
    ): JsonResponse {
        if ($workflowTask->case_id !== $case->id) {
            return response()->json(['message' => 'Task not found for this case.'], 404);
        }

        $this->authorize('update', $case);

        $task = $this->caseTaskService->updateTaskCore($case, $workflowTask, $request->validated());

        return response()->json([
            'data'    => new TaskResource($task),
            'message' => 'Task updated successfully.',
        ]);
    }

    /**
     * POST /api/cases/{case}/workflow-tasks/{workflowTask}/clone
     * Clone a workflow task within the same stage.
     */
    public function cloneWorkflowTask(
        Request $request,
        ImmigrationCase $case,
        Task $workflowTask
    ): JsonResponse {
        if ($workflowTask->case_id !== $case->id) {
            return response()->json(['message' => 'Task not found for this case.'], 404);
        }

        $this->authorize('update', $case);

        $clone = $this->caseTaskService->cloneTask($case, $workflowTask);

        return response()->json([
            'data' => new TaskResource($clone),
            'meta' => ['cloned_from_task_id' => $workflowTask->id],
            'message' => 'Task cloned successfully.',
        ], 201);
    }
}
