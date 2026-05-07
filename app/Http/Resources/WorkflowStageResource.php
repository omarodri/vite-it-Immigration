<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowStageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'tenant_id'            => $this->tenant_id,
            'case_type_id'         => $this->case_type_id,
            'code'                 => $this->code,
            'sort_order'           => $this->sort_order,
            'is_active'            => $this->is_active,
            'is_terminal'          => $this->is_terminal,
            'color'                => $this->color,
            'translations'         => $this->translationsByField(),
            'task_templates_count' => $this->whenCounted('taskTemplates'),
            'task_templates'       => TaskTemplateResource::collection($this->whenLoaded('taskTemplates')),
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
