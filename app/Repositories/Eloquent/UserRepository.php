<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class UserRepository implements UserRepositoryInterface
{
    /**
     * Get the tenant_id of the current authenticated user.
     * Super admins can see all users (returns null).
     */
    private function getCurrentTenantId(): ?int
    {
        $user = Auth::user();

        if (!$user) {
            return null;
        }

        // Super admins can see all users across all tenants
        if ($user->hasRole('super-admin')) {
            return null;
        }

        return $user->tenant_id;
    }
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user;
    }

    public function delete(User $user): bool
    {
        return $user->delete();
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::with('roles');

        // Apply tenant scope (except for super-admin)
        $tenantId = $this->getCurrentTenantId();
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['role'])) {
            $query->role($filters['role']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        return $query->paginate($perPage);
    }

    public function bulkDelete(array $ids): int
    {
        $query = User::whereIn('id', $ids);

        // Apply tenant scope (except for super-admin)
        $tenantId = $this->getCurrentTenantId();
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->delete();
    }

    public function countByRole(string $role): int
    {
        $query = User::role($role);

        // Apply tenant scope (except for super-admin)
        $tenantId = $this->getCurrentTenantId();
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->count();
    }

    public function getAdminIdsFromList(array $ids): array
    {
        $query = User::role('admin')->whereIn('id', $ids);

        // Apply tenant scope (except for super-admin)
        $tenantId = $this->getCurrentTenantId();
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->pluck('id')->toArray();
    }
}
