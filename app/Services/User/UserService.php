<?php

namespace App\Services\User;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function listUsers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->userRepository->paginate($filters, $perPage);
    }

    public function getUser(User $user): User
    {
        $user->load('roles.permissions');

        return $user;
    }

    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = $this->userRepository->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            if (! empty($data['roles'])) {
                $user->syncRoles($data['roles']);
            } else {
                $user->assignRole('user');
            }

            activity()
                ->causedBy(Auth::user())
                ->performedOn($user)
                ->withProperties(['roles' => $user->getRoleNames()])
                ->log('Created user: '.$user->name);

            $user->load('roles');

            return $user;
        });
    }

    public function updateUser(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $updateData = array_filter([
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
            ], fn ($value) => $value !== null);

            if (! empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            if (! empty($updateData)) {
                $this->userRepository->update($user, $updateData);
            }

            if (array_key_exists('roles', $data)) {
                $user->syncRoles($data['roles']);
            }

            activity()
                ->causedBy(Auth::user())
                ->performedOn($user)
                ->withProperties(['roles' => $user->getRoleNames()])
                ->log('Updated user: '.$user->name);

            $user->load('roles');

            return $user;
        });
    }

    public function deleteUser(User $currentUser, User $user): void
    {
        if ($currentUser->id === $user->id) {
            throw new \InvalidArgumentException('You cannot delete your own account');
        }

        if ($user->hasRole('admin')) {
            $adminCount = $this->userRepository->countByRole('admin');
            if ($adminCount <= 1) {
                throw new \InvalidArgumentException('Cannot delete the last administrator');
            }
        }

        activity()
            ->causedBy($currentUser)
            ->performedOn($user)
            ->withProperties(['deleted_user' => $user->email])
            ->log('Deleted user: '.$user->name);

        $this->userRepository->delete($user);
    }

    public function bulkDeleteUsers(User $currentUser, array $ids): int
    {
        $ids = array_diff($ids, [$currentUser->id]);

        $adminCount = $this->userRepository->countByRole('admin');
        $adminIdsToDelete = $this->userRepository->getAdminIdsFromList($ids);

        if (count($adminIdsToDelete) >= $adminCount) {
            throw new \InvalidArgumentException('Cannot delete all administrators');
        }

        activity()
            ->causedBy($currentUser)
            ->withProperties(['deleted_ids' => $ids])
            ->log('Bulk deleted '.count($ids).' users');

        return $this->userRepository->bulkDelete($ids);
    }
}
