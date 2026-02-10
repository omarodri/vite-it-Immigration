<?php

namespace App\Repositories\Eloquent;

use App\Models\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClientRepository implements ClientRepositoryInterface
{
    public function findById(int $id): ?Client
    {
        return Client::find($id);
    }

    public function findByEmail(string $email): ?Client
    {
        return Client::where('email', $email)->first();
    }

    public function findByPhone(string $phone): ?Client
    {
        return Client::where('phone', $phone)->first();
    }

    public function create(array $data): Client
    {
        return Client::create($data);
    }

    public function update(Client $client, array $data): Client
    {
        $client->update($data);

        return $client;
    }

    public function delete(Client $client): bool
    {
        return $client->delete();
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Client::query();

        // Search filter (name, email, phone, passport)
        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Status filter
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Nationality filter
        if (! empty($filters['nationality'])) {
            $query->where('nationality', $filters['nationality']);
        }

        // Canada status filter
        if (! empty($filters['canada_status'])) {
            $query->where('canada_status', $filters['canada_status']);
        }

        // Date range filter (created_at)
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Primary applicant filter
        if (isset($filters['is_primary_applicant'])) {
            $query->where('is_primary_applicant', $filters['is_primary_applicant']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        return $query->paginate($perPage);
    }

    public function bulkDelete(array $ids): int
    {
        return Client::whereIn('id', $ids)->delete();
    }

    public function countByStatus(string $status): int
    {
        return Client::where('status', $status)->count();
    }

    public function existsByEmailForTenant(string $email, ?int $excludeId = null): bool
    {
        $query = Client::where('email', $email);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function existsByPhoneForTenant(string $phone, ?int $excludeId = null): bool
    {
        $query = Client::where('phone', $phone);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
