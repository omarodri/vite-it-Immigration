<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Case\BulkUpdateCaseTasksRequest;
use App\Http\Resources\CaseResource;
use App\Http\Resources\CaseTaskResource;
use App\Models\CaseTask;
use App\Models\ImmigrationCase;
use App\Services\Case\CaseTaskService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

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
}
