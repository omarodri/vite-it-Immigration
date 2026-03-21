<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentFolderResource extends JsonResource
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
            'name' => $this->name,
            'parent_id' => $this->parent_id,
            'sort_order' => $this->sort_order,
            'is_default' => $this->is_default,
            'category' => $this->category,

            'children' => DocumentFolderResource::collection($this->whenLoaded('children')),
            'documents_count' => $this->whenCounted('documents'),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
