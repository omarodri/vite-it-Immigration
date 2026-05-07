<?php

namespace App\Observers;

use App\Models\Task;
use App\Models\Todo;
use Illuminate\Support\Facades\DB;

class TodoObserver
{
    /**
     * Re-entrancy guard. Set true when a Todo update is being driven by a Task
     * change so we don't bounce the update back to the Task.
     */
    public static bool $syncingFromTask = false;

    public function updated(Todo $todo): void
    {
        if (self::$syncingFromTask) {
            return;
        }

        if (! $todo->is_core || ! $todo->task_id) {
            return;
        }

        if (! $todo->wasChanged('status')) {
            return;
        }

        $task = Task::find($todo->task_id);
        if (! $task) {
            return;
        }

        $targetTaskStatus = $todo->status === 'complete' ? 'resolved' : 'in_progress';

        if ($task->status === $targetTaskStatus) {
            return;
        }

        DB::transaction(function () use ($task, $targetTaskStatus) {
            TaskWorkflowObserver::$syncingFromTodo = true;
            try {
                $task->update(['status' => $targetTaskStatus]);
            } finally {
                TaskWorkflowObserver::$syncingFromTodo = false;
            }
        });
    }
}
