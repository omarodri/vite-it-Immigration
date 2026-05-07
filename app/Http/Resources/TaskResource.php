<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'tenant_id'          => $this->tenant_id,
            'case_id'            => $this->case_id,
            'workflow_stage_id'  => $this->workflow_stage_id,
            'case_stage_id'      => $this->case_stage_id,
            'task_template_id'   => $this->task_template_id,
            'cloned_from_task_id' => $this->cloned_from_task_id,
            'requester_id'       => $this->requester_id,
            'assigned_to'        => $this->assigned_to,
            'subject'            => $this->subject,
            'description'        => $this->description,
            'type'               => $this->type,
            'priority'           => $this->priority,
            'status'             => $this->status,
            'sort_order'         => $this->sort_order,
            'due_date'           => $this->due_date?->toISOString(),
            'estimated_hours'    => $this->estimated_hours,
            'actual_hours'       => $this->actual_hours,
            'document_id'        => $this->document_id,
            'is_adhoc'           => $this->task_template_id === null,
            'template'           => $this->whenLoaded('template', fn () => new TaskTemplateResource($this->template)),
            'workflow_stage'     => $this->whenLoaded('workflowStage', fn () => new WorkflowStageResource($this->workflowStage)),
            'assignee'           => $this->whenLoaded('assignee', fn () => [
                'id'   => $this->assignee->id,
                'name' => $this->assignee->name,
            ]),
            'created_at'         => $this->created_at?->toISOString(),
            'updated_at'         => $this->updated_at?->toISOString(),
        ];
    }
}
