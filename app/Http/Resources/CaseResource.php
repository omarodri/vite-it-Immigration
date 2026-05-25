<?php

namespace App\Http\Resources;

use App\Http\Resources\CaseInvoiceResource;
use App\Http\Resources\TaskResource;
use App\Http\Resources\WorkflowStageResource;
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
            'primary_applicant_type'            => $this->primary_applicant_type,
            'primary_applicant_companion_id'    => $this->primary_applicant_companion_id,
            'client_is_participant'             => (bool) $this->client_is_participant,
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
            'current_stage_id' => $this->current_stage_id,
            'current_case_stage_id' => $this->current_case_stage_id,
            'workflow_snapshot' => $this->workflow_snapshot,
            'current_stage' => $this->whenLoaded('currentStage', fn () => new WorkflowStageResource($this->currentStage)),
            'current_case_stage' => $this->whenLoaded('currentCaseStage', fn () => [
                'id'            => $this->currentCaseStage->id,
                'name_resolved' => $this->currentCaseStage->trans('name', app()->getLocale()),
                'color'         => $this->currentCaseStage->color,
            ]),
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

            // Timesheet aggregates
            'total_time_spent_seconds' => (int) ($this->total_time_spent_seconds ?? 0),
            'total_time_spent_formatted' => $this->total_time_spent_formatted ?? '0m',

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Conditional Relations
            'client' => $this->whenLoaded('client', fn () => [
                'id'         => $this->client->id,
                'type'       => $this->client->type,
                'first_name' => $this->client->first_name,
                'last_name'  => $this->client->last_name,
                'full_name'  => $this->client->full_name,
                'initials'   => $this->client->initials,
                'email'      => $this->client->email,
                'phone'      => $this->client->phone,
            ]),

            'primary_applicant' => $this->when(
                $this->relationLoaded('client') || $this->relationLoaded('primaryApplicantCompanion'),
                function () {
                    if ($this->primary_applicant_type === 'companion' && $this->relationLoaded('primaryApplicantCompanion') && $this->primaryApplicantCompanion) {
                        return new CompanionResource($this->primaryApplicantCompanion);
                    }
                    if ($this->relationLoaded('client') && $this->client) {
                        return [
                            'id'         => $this->client->id,
                            'first_name' => $this->client->first_name,
                            'last_name'  => $this->client->last_name,
                            'full_name'  => $this->client->full_name,
                            'initials'   => $this->client->initials,
                            'email'      => $this->client->email,
                            'phone'      => $this->client->phone,
                            'type'       => 'client',
                        ];
                    }
                    return null;
                }
            ),

            'case_type' => $this->whenLoaded('caseType', fn () => new CaseTypeResource($this->caseType)),

            'assigned_user' => $this->whenLoaded('assignedTo', fn () => [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
                'email' => $this->assignedTo->email,
                'avatar_url' => $this->assignedTo->profile?->avatar_url,
            ]),

            'companions' => $this->whenLoaded('companions', fn () => CompanionResource::collection($this->companions)),

            'important_dates' => CaseImportantDateResource::collection($this->whenLoaded('importantDates')),

            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),

            'invoices' => CaseInvoiceResource::collection($this->whenLoaded('invoices')),

            'financial_summary' => $this->when(
                $request->user()?->can('cases.view-fees') && $this->relationLoaded('invoices'),
                function () {
                    $totalInvoiced = $this->invoices->sum(fn ($i) => (float) $i->amount);
                    $totalCollected = $this->invoices->where('is_collected', true)->sum(fn ($i) => (float) $i->amount);
                    $fees = $this->fees !== null ? (float) $this->fees : null;

                    return [
                        'fees' => $fees,
                        'total_invoiced' => round($totalInvoiced, 2),
                        'total_collected' => round($totalCollected, 2),
                        'balance' => $fees !== null ? round($fees - $totalInvoiced, 2) : null,
                    ];
                }
            ),
        ];
    }
}
