<?php

namespace App\Repositories\Contracts;

use App\Models\Client;
use App\Models\Companion;
use Illuminate\Database\Eloquent\Collection;

interface CompanionRepositoryInterface
{
    public function findById(int $id): ?Companion;

    public function getByClient(Client $client): Collection;

    public function create(array $data): Companion;

    public function update(Companion $companion, array $data): Companion;

    public function delete(Companion $companion): bool;

    public function countByClient(Client $client): int;
}
