<?php

namespace App\Services\Workflow;

use App\Models\WorkflowStage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WorkflowStageService
{
    public function listByCaseType(int $caseTypeId): Collection
    {
        return WorkflowStage::with([
                'translations',
                'taskTemplates' => fn ($q) => $q->orderBy('sort_order'),
                'taskTemplates.translations',
            ])
            ->where('case_type_id', $caseTypeId)
            ->orderBy('sort_order')
            ->get();
    }

    public function create(array $data): WorkflowStage
    {
        return DB::transaction(function () use ($data) {
            $translations = $data['translations'] ?? [];
            unset($data['translations']);

            $data['sort_order'] = $data['sort_order']
                ?? ((WorkflowStage::where('case_type_id', $data['case_type_id'])->max('sort_order') ?? -1) + 1);

            $stage = WorkflowStage::create($data);

            foreach ($translations as $field => $byLocale) {
                $stage->setTranslations($field, $byLocale);
            }

            return $stage->load(['translations', 'taskTemplates.translations']);
        });
    }

    public function update(WorkflowStage $stage, array $data): WorkflowStage
    {
        return DB::transaction(function () use ($stage, $data) {
            $translations = $data['translations'] ?? [];
            unset($data['translations']);

            if (! empty($data)) {
                $stage->update($data);
            }

            foreach ($translations as $field => $byLocale) {
                $stage->setTranslations($field, $byLocale);
            }

            return $stage->fresh(['translations', 'taskTemplates.translations']);
        });
    }

    public function delete(WorkflowStage $stage): void
    {
        $stage->delete();
    }

    /**
     * @param array<int> $orderedIds
     */
    public function reorder(int $caseTypeId, array $orderedIds): void
    {
        DB::transaction(function () use ($caseTypeId, $orderedIds) {
            foreach ($orderedIds as $idx => $id) {
                WorkflowStage::where('id', $id)
                    ->where('case_type_id', $caseTypeId)
                    ->update(['sort_order' => $idx]);
            }
        });
    }
}
