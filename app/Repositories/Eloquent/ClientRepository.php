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

        // Type filter — Spec 64 (Clientes Empresa). Accepts 'person' | 'company' | 'all'.
        if (! empty($filters['type']) && $filters['type'] !== 'all') {
            $query->ofType($filters['type']);
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

        // Sorting — only allow real DB columns
        $allowedSortFields = ['first_name', 'last_name', 'display_name', 'email', 'status', 'created_at', 'arrival_date'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSortFields) ? $filters['sort_by'] : 'created_at';
        $sortDirection = ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
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

    public function existsByEmailForTenantWithTrashed(string $email): bool
    {
        // withTrashed() only bypasses the SoftDeletingScope — TenantScope still applies.
        return Client::withTrashed()->where('email', $email)->exists();
    }

    public function existsByPhoneForTenantWithTrashed(string $phone): bool
    {
        // withTrashed() only bypasses the SoftDeletingScope — TenantScope still applies.
        return Client::withTrashed()->where('phone', $phone)->exists();
    }
}
