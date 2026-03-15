<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScrumColumnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'order_index' => $this->order_index,
            'tasks_count' => $this->tasks_count ?? $this->tasks->count(),
            'tasks' => ScrumTaskListResource::collection($this->whenLoaded('tasks')),
        ];
    }
}
