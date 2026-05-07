<?php

namespace App\Services\Case;

use App\Models\CaseStage;
use App\Models\CaseTask;
use App\Models\ImmigrationCase;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CaseTaskService
{
    /**
     * Replace all tasks for a case (delete-and-insert).
     * Also recalculates progress.
     */
    public function syncTasks(ImmigrationCase $case, array $tasks): void
    {
        $case->legacyChecklist()->delete();

        if (! empty($tasks)) {
            $normalized = array_values($tasks);
            foreach ($normalized as $idx => $task) {
                $case->legacyChecklist()->create([
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
     * Recalculate and persist progress on the case (legacy checklist).
     */
    public function recalculateProgress(ImmigrationCase $case): void
    {
        $total = $case->legacyChecklist()->count();
        $completed = $case->legacyChecklist()->where('is_completed', true)->count();
        $progress = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        $case->updateQuietly(['progress' => $progress]);
    }

    /**
     * Move a workflow task within or between stages, re-indexing source and target.
     * sourceStageId / targetStageId are case_stage_id values.
     */
    public function moveTask(ImmigrationCase $case, Task $task, int $sourceStageId, int $targetStageId, int $targetIndex): Task
    {
        return DB::transaction(function () use ($case, $task, $sourceStageId, $targetStageId, $targetIndex) {
            // Dual-write: resolve workflow_stage_id origin from the destination case_stage.
            $targetOriginId = CaseStage::where('id', $targetStageId)->value('workflow_stage_id');

            $task->case_stage_id     = $targetStageId;
            $task->workflow_stage_id = $targetOriginId;
            $task->save();

            // Re-index target stage (insert at target_index).
            $targetTasks = Task::where('case_id', $case->id)
                ->where('case_stage_id', $targetStageId)
                ->where('id', '!=', $task->id)
                ->orderBy('sort_order')->orderBy('id')
                ->get()
                ->values();

            $insertIndex = min($targetIndex, $targetTasks->count());
            $targetTasks->splice($insertIndex, 0, [$task]);

            foreach ($targetTasks as $i => $t) {
                Task::where('id', $t->id)->update(['sort_order' => $i]);
            }

            // Re-index source stage if different.
            if ($sourceStageId !== $targetStageId) {
                $sourceTasks = Task::where('case_id', $case->id)
                    ->where('case_stage_id', $sourceStageId)
                    ->orderBy('sort_order')->orderBy('id')
                    ->get();

                foreach ($sourceTasks as $i => $t) {
                    Task::where('id', $t->id)->update(['sort_order' => $i]);
                }
            }

            activity()
                ->causedBy(Auth::user())
                ->performedOn($case)
                ->withProperties([
                    'task_id'           => $task->id,
                    'from_case_stage_id'=> $sourceStageId,
                    'to_case_stage_id'  => $targetStageId,
                    'target_index'      => $insertIndex,
                ])
                ->log('Movio tarea entre etapas');

            return $task->fresh();
        });
    }

    /**
     * Create an ad-hoc task (task_template_id = NULL).
     */
    public function createAdHocTask(ImmigrationCase $case, array $data): Task
    {
        return DB::transaction(function () use ($case, $data) {
            $caseStageId = $data['case_stage_id'];

            // Dual-write: derive workflow_stage_id origin from the case_stage (NULL if ad-hoc stage).
            $originStageId = CaseStage::where('id', $caseStageId)->value('workflow_stage_id');

            $maxOrder = Task::where('case_id', $case->id)
                ->where('case_stage_id', $caseStageId)
                ->max('sort_order');

            $task = Task::create([
                'tenant_id'         => $case->tenant_id,
                'case_id'           => $case->id,
                'case_stage_id'     => $caseStageId,
                'workflow_stage_id' => $originStageId,
                'task_template_id'  => null,
                'requester_id'      => Auth::id(),
                'assigned_to'       => $data['assigned_to'] ?? $case->assigned_to,
                'subject'           => $data['subject'],
                'description'       => $data['description'] ?? null,
                'type'              => $data['type'],
                'priority'          => $data['priority'],
                'status'            => 'new',
                'due_date'          => $data['due_date'] ?? null,
                'sort_order'        => $maxOrder === null ? 0 : $maxOrder + 1,
            ]);

            $this->recalculateCaseProgress($case);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($case)
                ->withProperties([
                    'task_id'       => $task->id,
                    'subject'       => $task->subject,
                    'case_stage_id' => $task->case_stage_id,
                ])
                ->log('Creo tarea ad-hoc en expediente');

            return $task;
        });
    }

    /**
     * Soft-delete a workflow task and recalculate case progress.
     */
    public function softDeleteTask(ImmigrationCase $case, Task $task): void
    {
        DB::transaction(function () use ($case, $task) {
            $task->delete();
            $this->recalculateCaseProgress($case);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($case)
                ->withProperties([
                    'task_id' => $task->id,
                    'subject' => $task->subject,
                ])
                ->log('Envio tarea a papelera');
        });
    }

    /**
     * Restore a soft-deleted workflow task. If its stage was removed, set workflow_stage_id=NULL.
     */
    public function restoreTask(ImmigrationCase $case, int $taskId): Task
    {
        return DB::transaction(function () use ($case, $taskId) {
            /** @var Task $task */
            $task = Task::onlyTrashed()
                ->where('case_id', $case->id)
                ->findOrFail($taskId);

            $task->restore();

            // If the original case_stage no longer exists, null out the FK.
            if ($task->case_stage_id) {
                $stageExists = CaseStage::where('id', $task->case_stage_id)->exists();
                if (! $stageExists) {
                    $task->case_stage_id = null;
                }
            }

            // Place at the end of its stage (or "no stage" group).
            $maxOrder = Task::where('case_id', $case->id)
                ->where(function ($q) use ($task) {
                    if ($task->case_stage_id === null) {
                        $q->whereNull('case_stage_id');
                    } else {
                        $q->where('case_stage_id', $task->case_stage_id);
                    }
                })
                ->where('id', '!=', $task->id)
                ->max('sort_order');

            $task->sort_order = $maxOrder === null ? 0 : $maxOrder + 1;
            $task->save();

            $this->recalculateCaseProgress($case);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($case)
                ->withProperties([
                    'task_id' => $task->id,
                    'subject' => $task->subject,
                ])
                ->log('Restauro tarea desde papelera');

            return $task->fresh();
        });
    }

    /**
     * Recalculate case progress using ONLY template-instantiated tasks.
     * Ad-hoc tasks (task_template_id IS NULL) do not affect progress.
     */
    private function recalculateCaseProgress(ImmigrationCase $case): void
    {
        $total = Task::where('case_id', $case->id)
            ->whereNotNull('task_template_id')
            ->count();

        $done = Task::where('case_id', $case->id)
            ->whereNotNull('task_template_id')
            ->whereIn('status', ['resolved', 'closed'])
            ->count();

        $case->updateQuietly([
            'progress' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
        ]);
    }

    public function updateTaskCore(ImmigrationCase $case, Task $task, array $data): Task
    {
        return DB::transaction(function () use ($case, $task, $data) {
            $task->update([
                'subject'     => $data['subject'],
                'description' => $data['description'] ?? $task->description,
                'type'        => $data['type'] ?? $task->type,
                'priority'    => $data['priority'] ?? $task->priority,
                'assigned_to' => array_key_exists('assigned_to', $data) ? $data['assigned_to'] : $task->assigned_to,
                'due_date'    => array_key_exists('due_date', $data) ? $data['due_date'] : $task->due_date,
            ]);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($case)
                ->withProperties([
                    'task_id' => $task->id,
                    'subject' => $task->subject,
                    'is_core' => $task->task_template_id !== null,
                ])
                ->log('Edito campos core de tarea');

            $fresh = $task->fresh(['assignee']);

            if ($fresh->task_template_id) {
                app(\App\Services\Todo\TodoCoreService::class)->syncCoreFieldsFromTask($fresh);
            }

            return $fresh;
        });
    }

    public function cloneTask(ImmigrationCase $case, Task $original): Task
    {
        return DB::transaction(function () use ($case, $original) {
            Task::where('case_id', $case->id)
                ->where('case_stage_id', $original->case_stage_id)
                ->where('sort_order', '>', $original->sort_order)
                ->orderByDesc('sort_order')
                ->increment('sort_order');

            $clone = $original->replicate(['created_at', 'updated_at', 'deleted_at']);
            $clone->cloned_from_task_id = $original->id;
            $clone->sort_order          = $original->sort_order + 1;
            $clone->status              = 'new';
            $clone->save();

            $this->recalculateCaseProgress($case);

            if ($clone->task_template_id) {
                app(\App\Services\Todo\TodoCoreService::class)->createForTask($clone, 'workflow_add');
            }

            activity()
                ->causedBy(Auth::user())
                ->performedOn($case)
                ->withProperties([
                    'cloned_task_id'   => $clone->id,
                    'original_task_id' => $original->id,
                    'subject'          => $clone->subject,
                    'case_stage_id'    => $clone->case_stage_id,
                ])
                ->log('Clono tarea desde template');

            return $clone->fresh(['assignee']);
        });
    }
}
