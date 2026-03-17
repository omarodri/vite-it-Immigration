<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTodoResource extends JsonResource
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
            'case'        => $this->whenLoaded('immigrationCase', fn () => $this->immigrationCase
                ? ['id' => $this->immigrationCase->id, 'case_number' => $this->immigrationCase->case_number]
                : null
            ),
        ];
    }
}
