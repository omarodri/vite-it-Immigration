<?php

namespace App\Services\Case;

use App\Models\CaseTask;
use App\Models\ImmigrationCase;

class CaseTaskService
{
    /**
     * Replace all tasks for a case (delete-and-insert).
     * Also recalculates progress.
     */
    public function syncTasks(ImmigrationCase $case, array $tasks): void
    {
        $case->tasks()->delete();

        if (! empty($tasks)) {
            $normalized = array_values($tasks);
            foreach ($normalized as $idx => $task) {
                $case->tasks()->create([
                    'label' => $task['label'],
                    'is_completed' => $task['is_completed'] ?? false,
                    'is_custom' => $task['is_custom'] ?? false,
                    'sort_order' => $idx,
                    'completed_at' => (! empty($task['is_completed']) && empty($task['completed_at']))
                        ? now()
                        : ($task['completed_at'] ?? null),
                ]);
            }
        }

        $this->recalculateProgress($case);
    }

    /**
     * Toggle is_completed for a single task.
     * Updates completed_at accordingly.
     * Recalculates case progress.
     */
    public function toggleTask(ImmigrationCase $case, CaseTask $task): CaseTask
    {
        $newCompleted = ! $task->is_completed;

        $task->update([
            'is_completed' => $newCompleted,
            'completed_at' => $newCompleted ? now() : null,
        ]);

        $this->recalculateProgress($case);

        return $task->fresh();
    }

    /**
     * Recalculate and persist progress on the case.
     */
    public function recalculateProgress(ImmigrationCase $case): void
    {
        $total = $case->tasks()->count();
        $completed = $case->tasks()->where('is_completed', true)->count();
        $progress = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        $case->updateQuietly(['progress' => $progress]);
    }
}
