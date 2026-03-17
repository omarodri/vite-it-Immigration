<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'start_date'  => $this->all_day
                                 ? $this->start_date->toDateString()
                                 : $this->start_date->toIso8601String(),
            'end_date'    => $this->all_day
                                 ? $this->end_date->toDateString()
                                 : $this->end_date->toIso8601String(),
            'all_day'     => $this->all_day,
            'category'    => $this->category,
            'hex_color'   => $this->getCategoryHex(),
            'location'    => $this->location,
            'client_name' => $this->client_name_snapshot,
        ];
    }
}
