<?php

namespace App\Http\Resources;

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

            // Description
            'description' => $this->description,

            // Dates
            'hearing_date' => $this->hearing_date?->format('Y-m-d'),
            'fda_deadline' => $this->fda_deadline?->format('Y-m-d'),
            'brown_sheet_date' => $this->brown_sheet_date?->format('Y-m-d'),
            'evidence_deadline' => $this->evidence_deadline?->format('Y-m-d'),
            'days_until_hearing' => $this->days_until_hearing,

            // Archive
            'archive_box_number' => $this->archive_box_number,

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
        ];
    }
}
