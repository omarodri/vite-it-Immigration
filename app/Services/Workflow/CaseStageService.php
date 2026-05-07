<?php

namespace App\Services\Workflow;

use App\Models\CaseStage;
use App\Models\ImmigrationCase;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CaseStageService
{
    public function __construct(private WorkflowInstantiator $instantiator) {}

    public function listForCase(ImmigrationCase $case): Collection
    {
        return CaseStage::with('translations')
            ->where('case_id', $case->id)
            ->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    public function createAdHocStage(ImmigrationCase $case, array $data): CaseStage
    {
        return DB::transaction(function () use ($case, $data) {
            $maxOrder = CaseStage::where('case_id', $case->id)->max('sort_order');
            $nextOrder = $maxOrder === null ? 0 : $maxOrder + 1;

            $stage = CaseStage::create([
                'tenant_id'         => $case->tenant_id,
                'case_id'           => $case->id,
                'workflow_stage_id' => null,
                'code'              => 'adhoc_' . Str::random(10),
                'sort_order'        => $nextOrder,
                'is_active'         => true,
                'is_terminal'       => $data['is_terminal'] ?? false,
                'color'             => $data['color'],
            ]);

            $stage->setTranslations('name', $data['name']);
            if (! empty($data['description'])) {
                $stage->setTranslations('description', $data['description']);
            }

            activity()
                ->causedBy(Auth::user())
                ->performedOn($case)
                ->withProperties([
                    'case_stage_id' => $stage->id,
                    'name_es'       => $data['name']['es'] ?? null,
                ])
                ->log('Agrego etapa ad-hoc al expediente');

            return $stage->load('translations');
        });
    }

    public function updateStage(CaseStage $stage, array $data): CaseStage
    {
        return DB::transaction(function () use ($stage, $data) {
            $stage->fill(array_intersect_key($data, array_flip(['color', 'is_terminal'])));
            $stage->save();

            if (! empty($data['name'])) {
                $stage->setTranslations('name', $data['name']);
            }
            if (! empty($data['description'])) {
                $stage->setTranslations('description', $data['description']);
            }

            return $stage->fresh('translations');
        });
    }

    public function reorderStages(ImmigrationCase $case, array $orderedIds): Collection
    {
        return DB::transaction(function () use ($case, $orderedIds) {
            CaseStage::where('case_id', $case->id)->lockForUpdate()->get();

            foreach ($orderedIds as $i => $id) {
                CaseStage::where('id', $id)
                    ->where('case_id', $case->id)
                    ->update(['sort_order' => $i]);
            }

            activity()
                ->causedBy(Auth::user())
                ->performedOn($case)
                ->withProperties(['order' => $orderedIds])
                ->log('Reordeno etapas del expediente');

            return $this->listForCase($case);
        });
    }

    public function deleteStage(ImmigrationCase $case, CaseStage $stage, string $policy, ?int $moveToStageId): void
    {
        DB::transaction(function () use ($case, $stage, $policy, $moveToStageId) {
            // Validate: only terminal stage check
            if ($stage->is_terminal) {
                $otherTerminals = CaseStage::where('case_id', $case->id)
                    ->where('id', '!=', $stage->id)
                    ->where('is_terminal', true)
                    ->count();
                if ($otherTerminals === 0) {
                    abort(422, 'No puedes eliminar la unica etapa terminal del expediente.');
                }
            }

            // Reassign current_case_stage_id if pointing to this stage
            if ($case->current_case_stage_id === $stage->id) {
                $prev = CaseStage::where('case_id', $case->id)
                    ->where('id', '!=', $stage->id)
                    ->where('sort_order', '<', $stage->sort_order)
                    ->orderByDesc('sort_order')
                    ->first();
                $case->updateQuietly(['current_case_stage_id' => $prev?->id]);
            }

            $tasks = Task::where('case_stage_id', $stage->id)->lockForUpdate()->get();

            if ($policy === 'move') {
                $maxOrder = Task::where('case_id', $case->id)
                    ->where('case_stage_id', $moveToStageId)
                    ->max('sort_order');
                $base = $maxOrder === null ? 0 : $maxOrder + 1;

                // Resolve workflow_stage_id of destination for dual write
                $destOriginId = CaseStage::where('id', $moveToStageId)->value('workflow_stage_id');

                foreach ($tasks as $i => $t) {
                    $t->update([
                        'case_stage_id'     => $moveToStageId,
                        'workflow_stage_id' => $destOriginId,
                        'sort_order'        => $base + $i,
                    ]);
                }
            } elseif ($policy === 'trash') {
                foreach ($tasks as $t) {
                    $t->delete();
                }
            }

            $stage->delete();
            $this->instantiator->recalculateProgress($case);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($case)
                ->withProperties([
                    'case_stage_id'  => $stage->id,
                    'policy'         => $policy,
                    'move_to'        => $moveToStageId,
                    'tasks_affected' => $tasks->count(),
                ])
                ->log('Elimino etapa del expediente');
        });
    }
}
