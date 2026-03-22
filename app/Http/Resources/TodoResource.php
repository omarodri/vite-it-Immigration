<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TodoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'tag'         => $this->tag,
            'priority'    => $this->priority,
            'status'      => $this->status,
            'due_date'    => $this->due_date?->format('Y-m-d'),
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo ? [
                'id'   => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
                'avatar_url' => $this->assignedTo->profile?->avatar_url,
            ] : null),
            'case' => $this->whenLoaded('immigrationCase', fn () => $this->immigrationCase ? [
                'id'          => $this->immigrationCase->id,
                'case_number' => $this->immigrationCase->case_number ?? $this->immigrationCase->id,
                'client_name' => $this->immigrationCase->client?->full_name ?? null,
            ] : null),
        ];
    }
}
