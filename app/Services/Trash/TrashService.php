<?php

namespace App\Services\Trash;

use App\Exceptions\Trash\ParentInTrashException;
use App\Exceptions\Trash\RestoreConflictException;
use App\Models\Client;
use App\Models\Companion;
use App\Models\Document;
use App\Models\ImmigrationCase;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TrashService
{
    private array $allowedTypes = ['clients', 'cases', 'companions', 'documents'];

    public function summary(): array
    {
        $tenantId = Auth::user()->tenant_id;

        return [
            'counts' => [
                'clients'    => Client::onlyTrashed()->where('tenant_id', $tenantId)->count(),
                'cases'      => ImmigrationCase::onlyTrashed()->where('tenant_id', $tenantId)->count(),
                'companions' => Companion::onlyTrashed()->where('tenant_id', $tenantId)->count(),
                'documents'  => Document::onlyTrashed()->where('tenant_id', $tenantId)->count(),
            ],
            'auto_purge_days' => 30,
        ];
    }

    public function listByType(string $type, int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        $this->validateType($type);

        $tenantId    = Auth::user()->tenant_id;
        $modelClass  = $this->resolveModelClass($type);

        $query = $modelClass::onlyTrashed()->where('tenant_id', $tenantId);

        // Eager-load parent for parent_in_trash detection
        match ($type) {
            'cases'      => $query->with(['client' => fn ($q) => $q->withTrashed()]),
            'companions' => $query->with(['client' => fn ($q) => $q->withTrashed()]),
            'documents'  => $query->with(['case' => fn ($q) => $q->withTrashed()->with(['client' => fn ($q2) => $q2->withTrashed()])]),
            default      => null,
        };

        if ($search) {
            if ($type === 'clients' || $type === 'companions') {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            } elseif ($type === 'cases') {
                $query->where(function ($q) use ($search) {
                    $q->where('case_number', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            } elseif ($type === 'documents') {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('original_name', 'like', "%{$search}%");
                });
            }
        }

        $paginator = $query->orderByDesc('deleted_at')->paginate($perPage);

        return $paginator->through(fn ($model) => $this->transformItem($type, $model));
    }

    private function transformItem(string $type, Model $model): array
    {
        $purgeDays  = (int) config('trash.auto_purge_days', 30);
        $daysUntil  = max(0, $purgeDays - (int) now()->diffInDays($model->deleted_at));

        return match ($type) {
            'clients' => [
                'id'               => $model->id,
                'type'             => 'clients',
                'display_name'     => trim("{$model->first_name} {$model->last_name}"),
                'subtitle'         => $model->email,
                'deleted_at'       => $model->deleted_at->toIso8601String(),
                'days_until_purge' => $daysUntil,
                'parent_in_trash'  => false,
                'parent'           => null,
                'can_restore'      => true,
            ],
            'cases' => [
                'id'               => $model->id,
                'type'             => 'cases',
                'display_name'     => $model->case_number,
                'subtitle'         => $model->description,
                'deleted_at'       => $model->deleted_at->toIso8601String(),
                'days_until_purge' => $daysUntil,
                'parent_in_trash'  => $model->client?->trashed() ?? false,
                'parent'           => $model->client ? [
                    'type' => 'clients',
                    'id'   => $model->client->id,
                    'name' => trim("{$model->client->first_name} {$model->client->last_name}"),
                ] : null,
                'can_restore'      => ! ($model->client?->trashed() ?? false),
            ],
            'companions' => [
                'id'               => $model->id,
                'type'             => 'companions',
                'display_name'     => trim("{$model->first_name} {$model->last_name}"),
                'subtitle'         => $model->relationship,
                'deleted_at'       => $model->deleted_at->toIso8601String(),
                'days_until_purge' => $daysUntil,
                'parent_in_trash'  => $model->client?->trashed() ?? false,
                'parent'           => $model->client ? [
                    'type' => 'clients',
                    'id'   => $model->client->id,
                    'name' => trim("{$model->client->first_name} {$model->client->last_name}"),
                ] : null,
                'can_restore'      => ! ($model->client?->trashed() ?? false),
            ],
            'documents' => [
                'id'               => $model->id,
                'type'             => 'documents',
                'display_name'     => $model->original_name ?? $model->name,
                'subtitle'         => $model->case?->case_number,
                'deleted_at'       => $model->deleted_at->toIso8601String(),
                'days_until_purge' => $daysUntil,
                'parent_in_trash'  => $model->case?->trashed() ?? false,
                'parent'           => $model->case ? [
                    'type' => 'cases',
                    'id'   => $model->case->id,
                    'name' => $model->case->case_number,
                ] : null,
                'can_restore'      => ! ($model->case?->trashed() ?? false),
            ],
            default => [],
        };
    }

    public function restore(string $type, int $id): Model
    {
        $this->validateType($type);

        $model = $this->findTrashed($type, $id);

        // Verify parent is not in trash
        $this->assertParentNotInTrash($type, $model);

        // Verify uniqueness conflicts for clients
        if ($type === 'clients') {
            $this->assertNoUniqueConflict($model);
        }

        $model->restore();

        return $model->fresh();
    }

    public function restoreWithParents(string $type, int $id): array
    {
        $this->validateType($type);

        $model = $this->findTrashed($type, $id);
        $chain = $this->buildRestorationChain($type, $model);

        DB::transaction(function () use ($chain) {
            // Restore from parent down
            foreach (array_reverse($chain) as [$chainType, $chainModel]) {
                if ($chainType === 'clients') {
                    $this->assertNoUniqueConflict($chainModel);
                }
                $chainModel->restore();
            }
        });

        return array_map(fn ($item) => ['type' => $item[0], 'id' => $item[1]->id], $chain);
    }

    public function forceDelete(string $type, int $id): void
    {
        $this->validateType($type);

        $model = $this->findTrashed($type, $id);
        $model->forceDelete();
    }

    public function purge(int $olderThanDays = 30): array
    {
        $tenantId = Auth::user()->tenant_id;
        $cutoff   = now()->subDays($olderThanDays);

        $counts = [];

        // Order: children first to avoid FK issues on force delete
        $types = [
            'documents'  => Document::class,
            'companions' => Companion::class,
            'cases'      => ImmigrationCase::class,
            'clients'    => Client::class,
        ];

        foreach ($types as $key => $modelClass) {
            $query = $modelClass::onlyTrashed()->where('tenant_id', $tenantId);
            if ($olderThanDays > 0) {
                $query->where('deleted_at', '<=', $cutoff);
            }
            $counts[$key] = $query->count();
            $query->get()->each->forceDelete();
        }

        return $counts;
    }

    // --- Private helpers ---

    private function validateType(string $type): void
    {
        if (! in_array($type, $this->allowedTypes)) {
            throw new \InvalidArgumentException("Unknown trash type: {$type}");
        }
    }

    private function resolveModelClass(string $type): string
    {
        return match ($type) {
            'clients'    => Client::class,
            'cases'      => ImmigrationCase::class,
            'companions' => Companion::class,
            'documents'  => Document::class,
            default      => throw new \InvalidArgumentException("Unknown trash type: {$type}"),
        };
    }

    private function findTrashed(string $type, int $id): Model
    {
        $modelClass = $this->resolveModelClass($type);
        $tenantId   = Auth::user()->tenant_id;

        return $modelClass::onlyTrashed()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }

    private function assertParentNotInTrash(string $type, Model $model): void
    {
        $parent = match ($type) {
            'cases'      => $model->client()->withTrashed()->first(),
            'companions' => $model->client()->withTrashed()->first(),
            'documents'  => $model->case()->withTrashed()->first(),
            default      => null,
        };

        if ($parent && $parent->trashed()) {
            $parentType = match ($type) {
                'cases', 'companions' => 'client',
                'documents'           => 'case',
                default               => 'unknown',
            };

            $parentName = match ($parentType) {
                'client' => "{$parent->first_name} {$parent->last_name}",
                'case'   => $parent->case_number,
                default  => "#{$parent->id}",
            };

            throw new ParentInTrashException(
                parentType: $parentType,
                parentId: $parent->id,
                parentName: $parentName,
            );
        }
    }

    private function assertNoUniqueConflict(Client $client): void
    {
        $tenantId = $client->tenant_id;

        if ($client->email && Client::where('tenant_id', $tenantId)->where('email', $client->email)->exists()) {
            throw new RestoreConflictException('email', $client->email);
        }

        if ($client->phone && Client::where('tenant_id', $tenantId)->where('phone', $client->phone)->exists()) {
            throw new RestoreConflictException('phone', $client->phone);
        }
    }

    private function buildRestorationChain(string $type, Model $model): array
    {
        $chain = [[$type, $model]];

        if (in_array($type, ['cases', 'companions'])) {
            $client = $model->client()->withTrashed()->first();
            if ($client && $client->trashed()) {
                array_unshift($chain, ['clients', $client]);
            }
        }

        if ($type === 'documents') {
            $case = $model->case()->withTrashed()->first();
            if ($case && $case->trashed()) {
                array_unshift($chain, ['cases', $case]);

                $client = $case->client()->withTrashed()->first();
                if ($client && $client->trashed()) {
                    array_unshift($chain, ['clients', $client]);
                }
            }
        }

        return $chain;
    }
}
