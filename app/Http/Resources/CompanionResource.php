<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\LegalDocumentResource;

class CompanionResource extends JsonResource
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
            'client_id' => $this->client_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'relationship' => $this->relationship,
            'relationship_other' => $this->relationship_other,
            'relationship_label' => $this->relationship_label,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'age' => $this->age,
            'gender' => $this->gender,
            'marital_status' => $this->marital_status,
            'nationality' => $this->nationality,
            'notes' => $this->notes,
            'iuc' => $this->iuc,
            'email'                => $this->email,
            'phone'                => $this->phone,
            'phone_country_code'   => $this->phone_country_code,
            'canada_status'        => $this->canada_status,
            'canada_status_other'  => $this->canada_status_other,
            'canada_status_label'  => $this->canada_status_label,
            'arrival_date'         => $this->arrival_date?->format('Y-m-d'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'legal_documents' => LegalDocumentResource::collection($this->whenLoaded('legalDocuments')),
            'already_promoted'      => $this->promotedClient()->exists(),
            'promoted_to_client_id' => $this->promotedClient()->value('id'),
            'parent_companion_id' => $this->parent_companion_id,
            'is_employee'         => $this->resource->canHaveFamily(),
            'is_family_member'    => $this->resource->isFamilyMember(),
            'family_count'        => $this->when(
                array_key_exists('family_count', $this->resource->getAttributes()) || isset($this->family_count),
                fn () => $this->family_count ?? 0
            ),
            'family_members'      => CompanionResource::collection($this->whenLoaded('familyMembers')),
        ];
    }
}
