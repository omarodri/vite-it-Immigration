<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaseTaskResource extends JsonResource
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
            'label' => $this->label,
            'is_completed' => $this->is_completed,
            'is_custom' => $this->is_custom,
            'sort_order' => $this->sort_order,
            'completed_at' => $this->completed_at?->toISOString(),
        ];
    }
}
