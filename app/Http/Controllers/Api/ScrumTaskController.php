<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scrum\MoveScrumTaskRequest;
use App\Http\Requests\Scrum\StoreScrumTaskRequest;
use App\Http\Requests\Scrum\UpdateScrumTaskRequest;
use App\Http\Resources\ScrumTaskResource;
use App\Models\ScrumTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ScrumTaskController extends Controller
{
    /**
     * POST /api/scrum/tasks
     */
    public function store(StoreScrumTaskRequest $request): JsonResponse
    {
        $maxOrder = ScrumTask::where('scrum_column_id', $request->scrum_column_id)
            ->max('order_index') ?? 0;

        $task = ScrumTask::create(array_merge($request->validated(), [
            'order_index' => $maxOrder + 1000,
        ]));

        $task->load(['assignedTo', 'immigrationCase.client']);

        return (new ScrumTaskResource($task))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/scrum/tasks/{scrumTask}
     */
    public function show(ScrumTask $scrumTask): ScrumTaskResource
    {
        $scrumTask->load(['assignedTo', 'immigrationCase.client']);

        return new ScrumTaskResource($scrumTask);
    }

    /**
     * PUT /api/scrum/tasks/{scrumTask}
     */
    public function update(UpdateScrumTaskRequest $request, ScrumTask $scrumTask): ScrumTaskResource
    {
        $scrumTask->update($request->validated());
        $scrumTask->load(['assignedTo', 'immigrationCase.client']);

        return new ScrumTaskResource($scrumTask);
    }

    /**
     * DELETE /api/scrum/tasks/{scrumTask}
     */
    public function destroy(ScrumTask $scrumTask): \Illuminate\Http\Response
    {
        $scrumTask->delete();

        return response()->noContent();
    }

    /**
     * PATCH /api/scrum/tasks/{scrumTask}/toggle
     * Toggle is_completed status.
     */
    public function toggle(ScrumTask $scrumTask): JsonResponse
    {
        $scrumTask->update(['is_completed' => ! $scrumTask->is_completed]);

        return response()->json([
            'data' => [
                'id'           => $scrumTask->id,
                'is_completed' => $scrumTask->is_completed,
            ],
        ]);
    }

    /**
     * PATCH /api/scrum/tasks/{scrumTask}/move
     * Move task to a column at a given logical position (0-based).
     */
    public function move(MoveScrumTaskRequest $request, ScrumTask $scrumTask): JsonResponse
    {
        $targetColumnId = $request->scrum_column_id;
        $position = $request->position;

        DB::transaction(function () use ($scrumTask, $targetColumnId, $position) {
            $scrumTask->scrum_column_id = $targetColumnId;

            // Get tasks in target column excluding the moving task, ordered
            $tasksInTarget = ScrumTask::where('scrum_column_id', $targetColumnId)
                ->where('id', '!=', $scrumTask->id)
                ->orderBy('order_index')
                ->pluck('order_index')
                ->values();

            $newIndex = $this->calculateOrderIndex($tasksInTarget, $position, $targetColumnId, $scrumTask->id);

            $scrumTask->order_index = $newIndex;
            $scrumTask->save();
        });

        return response()->json([
            'data' => [
                'id' => $scrumTask->id,
                'scrum_column_id' => $scrumTask->scrum_column_id,
                'order_index' => $scrumTask->order_index,
            ],
        ]);
    }

    /**
     * Calculate order_index using gap strategy.
     * Returns a value that fits between existing positions.
     */
    private function calculateOrderIndex($tasksInTarget, int $position, int $columnId, int $taskId): int
    {
        $count = $tasksInTarget->count();

        if ($count === 0) {
            return 1000;
        }

        if ($position <= 0) {
            $first = $tasksInTarget->first();
            if ($first > 1) {
                return (int) floor($first / 2);
            }
            // No space, reindex then insert at start
            $this->reindexColumn($columnId, $taskId);

            return 500;
        }

        if ($position >= $count) {
            return $tasksInTarget->last() + 1000;
        }

        $prev = $tasksInTarget[$position - 1];
        $next = $tasksInTarget[$position];
        $gap = $next - $prev;

        if ($gap > 1) {
            return $prev + (int) floor($gap / 2);
        }

        // Gap exhausted: reindex and recalculate
        $this->reindexColumn($columnId, $taskId);

        // After reindex, position P has index (P * 1000)
        return $position * 1000 + 500;
    }

    /**
     * Reindex all tasks in a column using multiples of 1000.
     */
    private function reindexColumn(int $columnId, int $excludeTaskId): void
    {
        $tasks = ScrumTask::where('scrum_column_id', $columnId)
            ->where('id', '!=', $excludeTaskId)
            ->orderBy('order_index')
            ->get();

        foreach ($tasks as $i => $t) {
            $t->update(['order_index' => ($i + 1) * 1000]);
        }
    }
}
