<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportantDateAlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $daysDiff = (int) $this->days_diff;

        return [
            'id'              => $this->id,
            'case_id'         => $this->case_id,
            'case_number'     => $this->immigrationCase->case_number,
            'case_url'        => "/cases/{$this->case_id}",
            'client_id'       => $this->immigrationCase->client_id,
            'client_name'     => $this->immigrationCase->client?->full_name,
            'case_type'       => [
                'id'   => $this->immigrationCase->caseType?->id,
                'name' => $this->immigrationCase->caseType?->name,
            ],
            'consultant'      => $this->when($this->immigrationCase->assignedTo !== null, [
                'id'   => $this->immigrationCase->assignedTo?->id,
                'name' => $this->immigrationCase->assignedTo?->name,
            ]),
            'label'           => $this->label,
            'due_date'        => $this->due_date instanceof Carbon
                                    ? $this->due_date->format('Y-m-d')
                                    : $this->due_date,
            'days_diff'       => $daysDiff,
            'urgency_level'   => $this->computeUrgencyLevel($daysDiff),
            'urgency_bucket'  => $this->computeUrgencyBucket($daysDiff),
            'has_calendar_event' => ! is_null($this->calendar_event_id),
            'calendar_event_id'  => $this->calendar_event_id,
        ];
    }

    private function computeUrgencyLevel(int $daysDiff): int
    {
        return match (true) {
            $daysDiff < -7  => 1,
            $daysDiff < 0   => 2,
            $daysDiff === 0 => 3,
            $daysDiff <= 7  => 4,
            default         => 5,
        };
    }

    private function computeUrgencyBucket(int $daysDiff): string
    {
        return match (true) {
            $daysDiff < -7  => 'overdue_critical',
            $daysDiff < 0   => 'overdue_recent',
            $daysDiff === 0 => 'today',
            $daysDiff <= 7  => 'upcoming_week',
            default         => 'upcoming_month',
        };
    }
}
