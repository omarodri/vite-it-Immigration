<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Client;
use App\Models\Event;
use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class LegalDocumentService
{
    /**
     * Get all legal documents for a given entity (Client or Companion).
     */
    public function getDocumentsForEntity(Model $entity): Collection
    {
        return $entity->legalDocuments()->get();
    }

    /**
     * Create a new legal document for a given entity.
     */
    public function createDocument(Model $entity, array $data): LegalDocument
    {
        $data['tenant_id'] = $entity->tenant_id;

        return $entity->legalDocuments()->create($data);
    }

    /**
     * Update an existing legal document.
     */
    public function updateDocument(LegalDocument $doc, array $data): LegalDocument
    {
        $doc->update($data);

        return $doc->fresh();
    }

    /**
     * Delete a legal document.
     */
    public function deleteDocument(LegalDocument $doc): void
    {
        $doc->delete();
    }

    /**
     * Create a calendar event from a legal document's expiry date.
     */
    public function syncToCalendar(LegalDocument $doc, User $user): Event
    {
        $entityName = $doc->documentable?->full_name ?? '';

        // Determine client_id: if documentable is Client, use its id;
        // if Companion, use the companion's client_id.
        $clientId = $doc->documentable instanceof Client
            ? $doc->documentable_id
            : $doc->documentable?->client_id;

        $expiryDate = $doc->expiry_date->copy()->startOfDay();

        return Event::create([
            'title'                => "{$doc->display_name} – {$entityName}",
            'start_date'           => $expiryDate,
            'end_date'             => $expiryDate,
            'all_day'              => true,
            'category'             => 'importante',
            'client_id'            => $clientId,
            'tenant_id'            => $doc->tenant_id,
            'created_by'           => $user->id,
            'assigned_to_id'       => $user->id,
            'client_name_snapshot' => $entityName,
        ]);
    }
}
