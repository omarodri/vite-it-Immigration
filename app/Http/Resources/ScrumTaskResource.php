<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScrumTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scrum_column_id' => $this->scrum_column_id,
            'title' => $this->title,
            'description' => $this->description,
            'tags' => $this->tags ?? [],
            'category' => $this->category,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'order_index'  => $this->order_index,
            'is_completed' => (bool) $this->is_completed,
            'created_at'   => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo ? [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
                'email' => $this->assignedTo->email,
            ] : null),
            'case' => $this->whenLoaded('immigrationCase', fn () => $this->immigrationCase ? [
                'id' => $this->immigrationCase->id,
                'case_number' => $this->immigrationCase->case_number ?? $this->immigrationCase->id,
                'client_name' => $this->immigrationCase->client?->full_name ?? null,
            ] : null),
        ];
    }
}
