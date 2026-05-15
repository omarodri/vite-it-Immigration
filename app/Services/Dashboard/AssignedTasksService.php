<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AssignedTasksService
{
    public const DEFAULT_PER_PAGE = 10;
    public const MAX_PER_PAGE     = 50;

    public function paginate(User $user, int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));

        return Todo::query()
            ->with(['immigrationCase:id,case_number'])
            ->where('assigned_to_id', $user->id)
            ->whereIn('status', ['pending', 'important'])
            ->orderByRaw('due_date IS NULL ASC')
            ->orderBy('due_date', 'asc')
            ->orderBy('id', 'asc')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
