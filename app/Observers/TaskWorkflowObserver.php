<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\Todo\TodoCoreService;
use App\Services\Workflow\WorkflowInstantiator;

class TaskWorkflowObserver
{
    /**
     * Re-entrancy guard. Set true when a Task update is being driven by a Todo
     * change so we don't bounce the update back to the Todo.
     */
    public static bool $syncingFromTodo = false;

    public function __construct(private WorkflowInstantiator $instantiator) {}

    public function updated(Task $task): void
    {
        if ($task->wasChanged('status') && $task->case_id && $task->task_template_id) {
            $case = $task->immigrationCase;
            if ($case) {
                $this->instantiator->recalculateProgress($case);
            }

            if (! self::$syncingFromTodo) {
                TodoObserver::$syncingFromTask = true;
                try {
                    app(TodoCoreService::class)->syncFromTaskStatus($task);
                } finally {
                    TodoObserver::$syncingFromTask = false;
                }
            }
        }
    }

    public function deleted(Task $task): void
    {
        if ($task->case_id && $task->task_template_id) {
            $case = $task->immigrationCase;
            if ($case) {
                $this->instantiator->recalculateProgress($case);
            }
        }
    }
}
