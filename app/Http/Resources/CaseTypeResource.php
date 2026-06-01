<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'tenant_id'             => $this->tenant_id,
            'name'                  => $this->name,
            'code'                  => $this->code,
            'category'              => $this->category,
            'category_label'        => $this->category_label,
            'description'           => $this->description,
            'is_active'             => $this->is_active,
            'is_deleted'            => $this->trashed(),
            'is_global'             => $this->isGlobal(),
            'workflow_stages_count' => $this->workflow_stages_count ?? 0,
            'tasks_count'           => $this->tasks_count ?? 0,
            // Informativo para el aviso en UI — no bloquea el borrado (D7).
            'active_cases_count'    => $this->when(
                isset($this->active_cases_count),
                fn () => $this->active_cases_count ?? 0
            ),
            'created_at'            => $this->created_at?->toISOString(),
            'updated_at'            => $this->updated_at?->toISOString(),
        ];
    }
}
