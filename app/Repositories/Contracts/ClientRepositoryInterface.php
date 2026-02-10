<?php

namespace App\Repositories\Contracts;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ClientRepositoryInterface
{
    public function findById(int $id): ?Client;

    public function findByEmail(string $email): ?Client;

    public function findByPhone(string $phone): ?Client;

    public function create(array $data): Client;

    public function update(Client $client, array $data): Client;

    public function delete(Client $client): bool;

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function bulkDelete(array $ids): int;

    public function countByStatus(string $status): int;

    public function existsByEmailForTenant(string $email, ?int $excludeId = null): bool;

    public function existsByPhoneForTenant(string $phone, ?int $excludeId = null): bool;
}
