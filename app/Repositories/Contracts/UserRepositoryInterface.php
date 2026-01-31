<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function create(array $data): User;

    public function update(User $user, array $data): User;

    public function delete(User $user): bool;

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function bulkDelete(array $ids): int;

    public function countByRole(string $role): int;

    public function getAdminIdsFromList(array $ids): array;
}
