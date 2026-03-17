<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // Native FullCalendar fields
            'id'        => $this->id,
            'title'     => $this->title,
            // All-day events use date-only strings so FullCalendar treats them as
            // "floating" dates with no timezone conversion (avoids day-shift bugs).
            'start'     => $this->all_day
                               ? $this->start_date->toDateString()
                               : $this->start_date->toIso8601String(),
            'end'       => $this->all_day
                               ? $this->end_date->toDateString()
                               : $this->end_date->toIso8601String(),
            'allDay'           => $this->all_day,
            'className'        => $this->getCategoryColor(),
            'backgroundColor'  => $this->getCategoryHex(),
            'borderColor'      => $this->getCategoryHex(),

            // Extended props (accessible as event.extendedProps in FullCalendar)
            'extendedProps' => [
                'description'  => $this->description,
                'category'     => $this->category,
                'location'     => $this->location,
                'assigned_to'  => $this->whenLoaded('assignedTo', fn () => $this->assignedTo ? [
                    'id'   => $this->assignedTo->id,
                    'name' => $this->assignedTo->name,
                ] : null),
                'case_id'      => $this->case_id,
                'client_id'    => $this->client_id,
                'case_number'  => $this->whenLoaded('immigrationCase',
                    fn () => $this->immigrationCase?->case_number
                ),
                'client_name'  => $this->client_name_snapshot,
            ],
        ];
    }
}
