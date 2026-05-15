<?php

namespace App\Observers;

use App\Models\ImmigrationCase;

class ImmigrationCaseObserver
{
    public function deleting(ImmigrationCase $case): void
    {
        if ($case->isForceDeleting()) {
            return;
        }

        $case->documents()->each(fn ($doc) => $doc->delete());
    }

    public function restoring(ImmigrationCase $case): void
    {
        $case->documents()->onlyTrashed()
            ->where('deleted_at', '>=', $case->deleted_at)
            ->each(fn ($doc) => $doc->restore());
    }

    /**
     * Activate the client when their first case is created.
     */
    public function created(ImmigrationCase $case): void
    {
        $client = $case->client;
        if ($client && in_array($client->status, ['prospect', 'inactive'])) {
            $client->updateQuietly(['status' => 'active']);
        }
    }

    /**
     * Deactivate the client when all their cases are closed/archived.
     */
    public function updated(ImmigrationCase $case): void
    {
        if ($case->isDirty('status') && in_array($case->status, ['closed', 'archived'])) {
            $client = $case->client;
            if (! $client) {
                return;
            }

            $hasActiveCases = ImmigrationCase::where('client_id', $client->id)
                ->where('id', '!=', $case->id)
                ->whereIn('status', ['active', 'inactive'])
                ->exists();

            if (! $hasActiveCases && $client->status === 'active') {
                $client->updateQuietly(['status' => 'inactive']);
            }
        }
    }
}
