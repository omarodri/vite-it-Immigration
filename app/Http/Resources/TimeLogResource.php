<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'case_id'            => $this->case_id,
            'todo_id'            => $this->todo_id,
            'user'               => $this->whenLoaded('user', fn () => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ], fn () => [
                'id' => $this->user_id,
            ]),
            'work_date'          => $this->work_date?->toDateString(),
            'duration_seconds'   => (int) $this->duration_seconds,
            'duration_formatted' => $this->duration_formatted,
            'description'        => $this->description,
            'started_at'         => $this->started_at?->toIso8601String(),
            'ended_at'           => $this->ended_at?->toIso8601String(),
            'elapsed_seconds'    => $this->elapsed_seconds,
            'is_running'         => $this->is_running,
            'source'             => $this->source,
            'todo'               => $this->whenLoaded('todo', fn () => $this->todo ? [
                'id'    => $this->todo->id,
                'title' => $this->todo->title,
            ] : null),
            'created_at'         => $this->created_at?->toIso8601String(),
        ];
    }
}
