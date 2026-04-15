<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class SearchResultDTO
{
    public function __construct(
        public int    $id,
        public string $type,
        public string $label,
        public string $description,
        public string $route,
    ) {}

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'type'        => $this->type,
            'label'       => $this->label,
            'description' => $this->description,
            'route'       => $this->route,
        ];
    }
}
