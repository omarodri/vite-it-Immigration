<?php

namespace App\Services\Companion;

use App\Exceptions\CompanionHasActiveCasesException;
use App\Models\Client;
use App\Models\Companion;
use App\Models\ImmigrationCase;
use App\Repositories\Contracts\CompanionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompanionService
{
    public function __construct(
        private CompanionRepositoryInterface $companionRepository
    ) {}

    public function listCompanions(Client $client): Collection
    {
        return $this->companionRepository->getByClient($client);
    }

    public function getCompanion(Companion $companion): Companion
    {
        return $companion->load(['client']);
    }

    public function createCompanion(Client $client, array $data): Companion
    {
        return DB::transaction(function () use ($client, $data) {
            $data['client_id'] = $client->id;

            $companion = $this->companionRepository->create($data);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($companion)
                ->withProperties(['client' => $client->full_name])
                ->log('Added companion: '.$companion->full_name.' to client '.$client->full_name);

            return $companion;
        });
    }

    public function updateCompanion(Companion $companion, array $data): Companion
    {
        return DB::transaction(function () use ($companion, $data) {
            $this->companionRepository->update($companion, $data);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($companion)
                ->withProperties(['updated_fields' => array_keys($data)])
                ->log('Updated companion: '.$companion->full_name);

            return $companion->fresh();
        });
    }

    public function deleteCompanion(Companion $companion): void
    {
        $activeCasesCount = $companion->cases()
            ->withoutGlobalScopes()
            ->whereNotIn('cases.status', [
                ImmigrationCase::STATUS_CLOSED,
                ImmigrationCase::STATUS_ARCHIVED,
            ])
            ->count();

        if ($activeCasesCount > 0) {
            throw CompanionHasActiveCasesException::forCompanion($companion, $activeCasesCount);
        }

        $fullName = $companion->full_name;
        $clientName = $companion->client->full_name;

        activity()
            ->causedBy(Auth::user())
            ->performedOn($companion)
            ->withProperties(['deleted_companion' => $fullName])
            ->log('Deleted companion: '.$fullName.' from client '.$clientName);

        $this->companionRepository->delete($companion);
    }

    public function getCompanionCount(Client $client): int
    {
        return $this->companionRepository->countByClient($client);
    }
}
