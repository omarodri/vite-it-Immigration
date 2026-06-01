<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'case_number'    => $this->case_number,
            'case_code'      => $this->case_code,
            'status'         => $this->status,
            'priority'       => $this->priority,
            'priority_label' => $this->priority_label,
            'progress'       => $this->progress,
            'stage'          => $this->stage,
            'stage_label'    => $this->stage_label,
            'client'         => $this->whenLoaded('client', fn () => [
                'id'        => $this->client->id,
                'full_name' => $this->client->full_name,
            ]),
            'case_type'      => $this->whenLoaded('caseType', fn () => $this->caseType ? [
                'id'      => $this->caseType->id,
                'name'    => $this->caseType->name . ($this->caseType->trashed() ? ' (eliminado)' : ''),
                'code'    => $this->caseType->code,
                'deleted' => $this->caseType->trashed(),
            ] : null),
            'next_deadline'  => $this->whenLoaded('importantDates',
                fn () => $this->importantDates->first()?->due_date?->format('Y-m-d')
            ),
        ];
    }
}
