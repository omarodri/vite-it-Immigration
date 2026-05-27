<?php

namespace App\Repositories\Eloquent;

use App\Models\Client;
use App\Models\Companion;
use App\Repositories\Contracts\CompanionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class CompanionRepository implements CompanionRepositoryInterface
{
    public function findById(int $id): ?Companion
    {
        return Companion::find($id);
    }

    public function getByClient(Client $client): Collection
    {
        return $client->companions()
            ->orderBy('relationship')
            ->orderBy('first_name')
            ->get();
    }

    public function getByClientFiltered(Client $client, array $params = []): Collection
    {
        $query = $client->companions()
            ->orderBy('relationship')
            ->orderBy('first_name');

        $tier = $params['tier'] ?? 'all';
        if ($tier === 'employees') {
            $query->whereNull('parent_companion_id')
                  ->where('relationship', Companion::RELATIONSHIP_BENEFICIARY);
        } elseif (isset($params['parent_id'])) {
            $parentId = $params['parent_id'];
            if ($parentId === 'null' || $parentId === null) {
                $query->whereNull('parent_companion_id');
            } else {
                $query->where('parent_companion_id', (int) $parentId);
            }
        }

        if (! empty($params['with_family_count'])) {
            $query->withCount('familyMembers as family_count');
        }

        $result = $query->get();

        if (! empty($params['with_family']) && $result->isNotEmpty()) {
            $result->load('familyMembers');
        }

        return $result;
    }

    public function create(array $data): Companion
    {
        $data['tenant_id'] = Auth::user()->tenant_id;

        return Companion::create($data);
    }

    public function update(Companion $companion, array $data): Companion
    {
        $companion->update($data);

        return $companion->fresh();
    }

    public function delete(Companion $companion): bool
    {
        return $companion->delete();
    }

    public function countByClient(Client $client): int
    {
        return $client->companions()->count();
    }
}
