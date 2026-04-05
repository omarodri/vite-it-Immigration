<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LegalDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'document_type'    => $this->document_type,
            'document_name'    => $this->document_name,
            'display_name'     => $this->display_name,
            'document_number'  => $this->document_number,
            'issuing_country'  => $this->issuing_country,
            'issue_date'       => $this->issue_date?->format('Y-m-d'),
            'expiry_date'      => $this->expiry_date?->format('Y-m-d'),
            'alert_days_before' => $this->alert_days_before,
            'alert_active'     => $this->alert_active,
            'notes'            => $this->notes,
            'sort_order'       => $this->sort_order,
            'days_remaining'   => $this->days_remaining,
            'alert_status'     => $this->alert_status,
            // Include documentable entity info when loaded
            'entity_name'      => $this->whenLoaded('documentable', fn () => $this->documentable?->full_name),
            'entity_type'      => $this->when(
                $this->relationLoaded('documentable'),
                fn () => $this->documentable instanceof Client ? 'client' : 'companion'
            ),
            'entity_id'        => $this->documentable_id,
            'client_id'        => $this->whenLoaded('documentable', fn () =>
                $this->documentable instanceof Client
                    ? $this->documentable_id
                    : $this->documentable?->client_id
            ),
        ];
    }
}
