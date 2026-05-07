<?php

namespace App\Services\Workflow;

use App\Models\TaskTemplate;
use Illuminate\Support\Facades\DB;

class TaskTemplateService
{
    public function create(array $data): TaskTemplate
    {
        return DB::transaction(function () use ($data) {
            $translations = $data['translations'] ?? [];
            unset($data['translations']);

            $data['sort_order'] = $data['sort_order']
                ?? ((TaskTemplate::where('workflow_stage_id', $data['workflow_stage_id'])->max('sort_order') ?? -1) + 1);

            $template = TaskTemplate::create($data);

            foreach ($translations as $field => $byLocale) {
                $template->setTranslations($field, $byLocale);
            }

            return $template->load('translations');
        });
    }

    public function update(TaskTemplate $template, array $data): TaskTemplate
    {
        return DB::transaction(function () use ($template, $data) {
            $translations = $data['translations'] ?? [];
            unset($data['translations']);

            if (! empty($data)) {
                $template->update($data);
            }

            foreach ($translations as $field => $byLocale) {
                $template->setTranslations($field, $byLocale);
            }

            return $template->fresh('translations');
        });
    }

    public function delete(TaskTemplate $template): void
    {
        $template->delete();
    }

    /**
     * @param array<int> $orderedIds
     */
    public function reorder(int $stageId, array $orderedIds): void
    {
        DB::transaction(function () use ($stageId, $orderedIds) {
            foreach ($orderedIds as $idx => $id) {
                TaskTemplate::where('id', $id)
                    ->where('workflow_stage_id', $stageId)
                    ->update(['sort_order' => $idx]);
            }
        });
    }
}
