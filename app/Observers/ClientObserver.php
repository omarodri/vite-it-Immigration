<?php

namespace App\Observers;

use App\Models\Client;

class ClientObserver
{
    public function deleting(Client $client): void
    {
        if ($client->isForceDeleting()) {
            return; // DB cascade handles hard delete
        }

        // Soft-delete companions (CompanionObserver will propagate to their LegalDocuments)
        $client->companions()->each(fn ($companion) => $companion->delete());

        // Soft-delete cases (ImmigrationCaseObserver will propagate to their Documents)
        $client->cases()->each(fn ($case) => $case->delete());

        // Soft-delete legal documents directly owned by the client
        $client->legalDocuments()->each(fn ($doc) => $doc->delete());
    }

    public function restoring(Client $client): void
    {
        // Only restore children deleted in the same cascade (deleted_at >= client.deleted_at)
        $client->companions()->onlyTrashed()
            ->where('deleted_at', '>=', $client->deleted_at)
            ->each(fn ($companion) => $companion->restore());

        $client->cases()->onlyTrashed()
            ->where('deleted_at', '>=', $client->deleted_at)
            ->each(fn ($case) => $case->restore());

        $client->legalDocuments()->onlyTrashed()
            ->where('deleted_at', '>=', $client->deleted_at)
            ->each(fn ($doc) => $doc->restore());
    }
}
