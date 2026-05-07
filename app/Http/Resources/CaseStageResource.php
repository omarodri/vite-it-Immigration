<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CaseStageResource extends JsonResource
{
    public function toArray($request): array
    {
        $byField = $this->translationsByField();

        return [
            'id'                => $this->id,
            'case_id'           => $this->case_id,
            'workflow_stage_id' => $this->workflow_stage_id,
            'is_ad_hoc'         => $this->workflow_stage_id === null,
            'code'              => $this->code,
            'sort_order'        => $this->sort_order,
            'is_active'         => $this->is_active,
            'is_terminal'       => $this->is_terminal,
            'color'             => $this->color,
            'name'              => $byField['name'] ?? [],
            'description'       => $byField['description'] ?? [],
            'name_resolved'     => $this->trans('name', app()->getLocale()),
            'created_at'        => $this->created_at?->toISOString(),
            'updated_at'        => $this->updated_at?->toISOString(),
        ];
    }
}
