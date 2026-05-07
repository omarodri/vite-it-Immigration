<?php

namespace App\Services\Todo;

use App\Models\ImmigrationCase;
use App\Models\Task;
use App\Models\Todo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class TodoCoreService
{
    /**
     * Create (or fetch idempotently) the Core Todo associated with a workflow Task.
     */
    public function createForTask(Task $task, string $source = 'wizard', ?Carbon $referenceDate = null): ?Todo
    {
        if (! $task->task_template_id) {
            return null;
        }

        $template = $task->relationLoaded('template') ? $task->template : $task->template()->first();

        $assignedTo = $task->assigned_to ?? $task->requester_id ?? Auth::id();

        $dueDate = null;
        if ($template && $template->due_offset_days !== null) {
            $base = $referenceDate ? $referenceDate->copy() : now();
            $dueDate = $base->addDays((int) $template->due_offset_days);
        }

        $priority = $this->mapPriority((string) ($task->priority ?? 'medium'));

        return Todo::firstOrCreate(
            [
                'task_id' => $task->id,
                'is_core' => true,
            ],
            [
                'tenant_id'        => $task->tenant_id,
                'title'            => $task->subject,
                'description'      => $task->description,
                'assigned_to_id'   => $assignedTo,
                'case_id'          => $task->case_id,
                'task_template_id' => $task->task_template_id,
                'source'           => $source,
                'priority'         => $priority,
                'status'           => 'pending',
                'due_date'         => $dueDate,
            ]
        );
    }

    /**
     * Create Core Todos for every workflow-template-backed Task in a case.
     */
    public function createForCase(ImmigrationCase $case, string $source = 'wizard'): void
    {
        $referenceDate = $case->created_at ? Carbon::parse($case->created_at) : now();

        $tasks = $case->tasks()
            ->whereNotNull('task_template_id')
            ->whereNull('deleted_at')
            ->with('template')
            ->get();

        foreach ($tasks as $task) {
            $this->createForTask($task, $source, $referenceDate);
        }
    }

    /**
     * Sync editable core fields (title, description, priority, assigned_to, due_date)
     * from a Task to its associated Core Todo.
     * Uses updateQuietly to avoid triggering TodoObserver's status-sync loop.
     */
    public function syncCoreFieldsFromTask(Task $task): void
    {
        $todo = Todo::where('task_id', $task->id)
            ->where('is_core', true)
            ->first();

        if (! $todo) {
            return;
        }

        $todo->updateQuietly([
            'title'          => $task->subject,
            'description'    => $task->description,
            'priority'       => $this->mapPriority((string) ($task->priority ?? 'medium')),
            'assigned_to_id' => $task->assigned_to,
            'due_date'       => $task->due_date,
        ]);
    }

    /**
     * Sync the Core Todo status from a Task status change.
     * Tasks resolved/closed -> Todo complete; otherwise Todo pending.
     */
    public function syncFromTaskStatus(Task $task): void
    {
        $todo = Todo::where('task_id', $task->id)
            ->where('is_core', true)
            ->first();

        if (! $todo) {
            return;
        }

        $newStatus = in_array($task->status, ['resolved', 'closed'], true) ? 'complete' : 'pending';

        if ($todo->status === $newStatus) {
            return;
        }

        $todo->updateQuietly(['status' => $newStatus]);
    }

    /**
     * Remove (or detach) the Core Todo when its Task is deleted.
     *
     * @param string $strategy soft_delete|hard_delete|keep_orphan
     */
    public function removeForTask(Task $task, string $strategy = 'soft_delete'): void
    {
        $todo = Todo::where('task_id', $task->id)
            ->where('is_core', true)
            ->first();

        if (! $todo) {
            return;
        }

        match ($strategy) {
            'soft_delete' => $this->softDeleteByStatus($todo),
            'hard_delete' => $todo->forceDelete(),
            'keep_orphan' => $todo->updateQuietly(['task_id' => null]),
            default       => $this->softDeleteByStatus($todo),
        };
    }

    /**
     * Reassign all pending Core Todos for a case to a new user.
     */
    public function reassignPendingForCase(ImmigrationCase $case, int $newUserId): void
    {
        Todo::where('case_id', $case->id)
            ->where('is_core', true)
            ->where('status', 'pending')
            ->update(['assigned_to_id' => $newUserId]);
    }

    /**
     * Soft-delete only if the todo is in a non-final state.
     * Preserve complete and important records to keep the audit trail.
     */
    private function softDeleteByStatus(Todo $todo): void
    {
        if (in_array($todo->status, ['pending', 'trash'], true)) {
            $todo->delete();
        }
    }

    /**
     * Map a Task priority to a Todo priority.
     */
    private function mapPriority(string $taskPriority): string
    {
        return match ($taskPriority) {
            'urgent', 'high' => 'high',
            'medium'         => 'medium',
            default          => 'low',
        };
    }
}
