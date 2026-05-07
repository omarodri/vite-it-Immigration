<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TaskTemplateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                      => $this->id,
            'tenant_id'               => $this->tenant_id,
            'workflow_stage_id'       => $this->workflow_stage_id,
            'code'                    => $this->code,
            'sort_order'              => $this->sort_order,
            'is_required'             => $this->is_required,
            'blocks_stage_completion' => $this->blocks_stage_completion,
            'default_type'            => $this->default_type,
            'default_priority'        => $this->default_priority,
            'due_offset_days'         => $this->due_offset_days,
            'is_active'               => $this->is_active,
            'translations'            => $this->translationsByField(),
            'created_at'              => $this->created_at,
            'updated_at'              => $this->updated_at,
        ];
    }
}
