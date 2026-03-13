<?php

namespace App\Http\Resources;

use App\Http\Resources\CaseTaskResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'case_number' => $this->case_number,
            'tenant_id' => $this->tenant_id,
            'client_id' => $this->client_id,
            'case_type_id' => $this->case_type_id,
            'assigned_to' => $this->assigned_to,

            // Status & Priority
            'status' => $this->status,
            'status_label' => $this->status_label,
            'priority' => $this->priority,
            'priority_label' => $this->priority_label,
            'progress' => $this->progress,
            'progress_percentage' => $this->progress_percentage,
            'language' => $this->language,

            // Operational tracking
            'stage' => $this->stage,
            'stage_label' => $this->stage_label,
            'ircc_status' => $this->ircc_status,
            'ircc_status_label' => $this->ircc_status_label,
            'final_result' => $this->final_result,
            'final_result_label' => $this->final_result_label,
            'ircc_code' => $this->ircc_code,

            // Description
            'description' => $this->description,

            // Archive
            'archive_box_number' => $this->archive_box_number,

            // Financial/Admin
            'contract_number' => $this->contract_number,
            'service_type' => $this->service_type,
            'service_type_label' => $this->service_type_label,
            'fees' => $this->when(
                $request->user()?->can('cases.view-fees'),
                fn () => $this->fees
            ),

            // Closure
            'closed_at' => $this->closed_at?->format('Y-m-d'),
            'closure_notes' => $this->closure_notes,

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Conditional Relations
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'first_name' => $this->client->first_name,
                'last_name' => $this->client->last_name,
                'full_name' => $this->client->full_name,
                'email' => $this->client->email,
                'phone' => $this->client->phone,
            ]),

            'case_type' => $this->whenLoaded('caseType', fn () => new CaseTypeResource($this->caseType)),

            'assigned_user' => $this->whenLoaded('assignedTo', fn () => [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
                'email' => $this->assignedTo->email,
            ]),

            'companions' => $this->whenLoaded('companions', fn () => CompanionResource::collection($this->companions)),

            'important_dates' => CaseImportantDateResource::collection($this->whenLoaded('importantDates')),

            'tasks' => CaseTaskResource::collection($this->whenLoaded('tasks')),
        ];
    }
}
