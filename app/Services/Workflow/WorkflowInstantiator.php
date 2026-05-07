<?php

namespace App\Services\Workflow;

use App\Exceptions\StageBlockedException;
use App\Models\CaseStage;
use App\Models\ImmigrationCase;
use App\Models\Task;
use App\Models\WorkflowStage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WorkflowInstantiator
{
    public function __construct(private TranslationResolver $i18n) {}

    /**
     * @param array<int> $excludedTemplateIds
     */
    public function instantiate(ImmigrationCase $case, array $excludedTemplateIds = []): void
    {
        $stages = WorkflowStage::with([
                'taskTemplates' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                'taskTemplates.translations',
                'translations',
            ])
            ->where('case_type_id', $case->case_type_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($stages->isEmpty()) {
            return;
        }

        $locale = $case->language ?? 'es';

        DB::transaction(function () use ($case, $stages, $excludedTemplateIds, $locale) {
            // Create CaseStage instances and copy translations
            $stageIdMap = []; // workflow_stage_id => case_stage_id
            foreach ($stages as $stage) {
                $caseStage = CaseStage::create([
                    'tenant_id'         => $case->tenant_id,
                    'case_id'           => $case->id,
                    'workflow_stage_id' => $stage->id,
                    'code'              => $stage->code,
                    'sort_order'        => $stage->sort_order,
                    'is_active'         => $stage->is_active,
                    'is_terminal'       => $stage->is_terminal,
                    'color'             => $stage->color,
                ]);

                foreach ($stage->translations as $t) {
                    $caseStage->translations()->create([
                        'tenant_id' => $t->tenant_id,
                        'locale'    => $t->locale,
                        'field'     => $t->field,
                        'value'     => $t->value,
                    ]);
                }

                $stageIdMap[$stage->id] = $caseStage->id;
            }

            $case->workflow_snapshot      = $this->buildSnapshot($stages, $locale);
            $case->current_stage_id       = $stages->first()->id;
            $case->current_case_stage_id  = $stageIdMap[$stages->first()->id];
            $case->saveQuietly();

            $rows = [];
            $now  = now();
            foreach ($stages as $stage) {
                foreach ($stage->taskTemplates as $tmpl) {
                    if (in_array($tmpl->id, $excludedTemplateIds, true)) {
                        continue;
                    }

                    $requesterId = Auth::id()
                        ?? $case->assigned_to
                        ?? \App\Models\User::withoutGlobalScopes()
                            ->where('tenant_id', $case->tenant_id)->value('id');

                    if (! $requesterId) {
                        continue;
                    }

                    $rows[] = [
                        'tenant_id'         => $case->tenant_id,
                        'case_id'           => $case->id,
                        'workflow_stage_id' => $stage->id,
                        'case_stage_id'     => $stageIdMap[$stage->id],
                        'task_template_id'  => $tmpl->id,
                        'requester_id'      => $requesterId,
                        'assigned_to'       => $case->assigned_to,
                        'subject'           => $this->i18n->resolve($tmpl, 'name', $locale)
                                                  ?? "Template #{$tmpl->id}",
                        'description'       => $this->i18n->resolve($tmpl, 'description', $locale),
                        'type'              => $tmpl->default_type,
                        'priority'          => $tmpl->default_priority,
                        'status'            => 'new',
                        'due_date'          => $tmpl->due_offset_days
                                                  ? $now->copy()->addDays($tmpl->due_offset_days)
                                                  : null,
                        'sort_order'        => $tmpl->sort_order,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ];
                }
            }

            if ($rows) {
                Task::insert($rows);
            }

            // Refrescar relacion tras insert masivo
            $case->load('tasks.template');
            app(\App\Services\Todo\TodoCoreService::class)->createForCase($case, 'wizard');

            $this->recalculateProgress($case);
        });
    }

    public function recalculateProgress(ImmigrationCase $case): void
    {
        $base  = Task::where('case_id', $case->id)->whereNotNull('task_template_id');
        $total = (clone $base)->count();
        $done  = (clone $base)->whereIn('status', ['resolved', 'closed'])->count();

        $case->updateQuietly([
            'progress' => $total ? (int) round($done / $total * 100) : 0,
        ]);
    }

    public function advanceStage(ImmigrationCase $case): ?CaseStage
    {
        return DB::transaction(function () use ($case) {
            $case = ImmigrationCase::lockForUpdate()->findOrFail($case->id);
            if (! $case->current_case_stage_id) {
                return null;
            }

            $blocking = Task::where('case_id', $case->id)
                ->where('case_stage_id', $case->current_case_stage_id)
                ->whereIn('status', ['new', 'assigned', 'in_progress'])
                ->whereHas('template', fn ($q) => $q->where('blocks_stage_completion', true))
                ->exists();

            if ($blocking) {
                throw new StageBlockedException(
                    'No se puede avanzar: quedan tareas obligatorias pendientes en la etapa actual.'
                );
            }

            $current = CaseStage::find($case->current_case_stage_id);
            $next = CaseStage::where('case_id', $case->id)
                ->where('is_active', true)
                ->where('sort_order', '>', $current->sort_order)
                ->orderBy('sort_order')
                ->first();

            $updates = [
                'current_case_stage_id' => $next?->id,
                // Keep legacy current_stage_id in sync via dual-write
                'current_stage_id'      => $next?->workflow_stage_id,
            ];
            if ($next?->is_terminal) {
                $updates['status']    = ImmigrationCase::STATUS_CLOSED;
                $updates['closed_at'] = now();
            }
            $case->update($updates);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($case)
                ->withProperties([
                    'from_case_stage_id' => $current->id,
                    'to_case_stage_id'   => $next?->id,
                    'from_code'          => $current->code,
                    'to_code'            => $next?->code,
                ])
                ->log('Avanzo etapa del expediente ' . $case->case_number);

            return $next;
        });
    }

    private function buildSnapshot(Collection $stages, string $locale): array
    {
        return [
            'locale'      => $locale,
            'captured_at' => now()->toIso8601String(),
            'stages'      => $stages->map(fn ($s) => [
                'id'          => $s->id,
                'code'        => $s->code,
                'sort_order'  => $s->sort_order,
                'is_terminal' => $s->is_terminal,
                'color'       => $s->color,
                'name'        => $this->i18n->resolve($s, 'name', $locale),
                'description' => $this->i18n->resolve($s, 'description', $locale),
            ])->values()->all(),
        ];
    }
}
