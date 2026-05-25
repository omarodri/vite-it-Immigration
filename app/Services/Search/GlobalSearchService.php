<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\DTOs\SearchResultDTO;
use App\Models\Client;
use App\Models\Companion;
use App\Models\Event;
use App\Models\ImmigrationCase;
use App\Models\Todo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class GlobalSearchService
{
    /**
     * @return array{results: array<string, array<int, array<string, mixed>>>, totals: array<string, int>}
     */
    public function search(string $query, int $limit = 5): array
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;
        $cachePrefix = "search.{$tenantId}." . md5($query);

        $results = [];
        $totals = [];

        // Clients
        if ($user->can('clients.view')) {
            $clients = Cache::remember("{$cachePrefix}.clients", 30, function () use ($query, $tenantId, $limit) {
                return Client::where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->where(function ($q) use ($query) {
                        $q->where('display_name', 'LIKE', "%{$query}%")
                          ->orWhere('email', 'LIKE', "%{$query}%")
                          ->orWhere('tax_id', 'LIKE', "%{$query}%");
                    })
                    ->limit($limit)
                    ->get(['id', 'tenant_id', 'first_name', 'last_name', 'email', 'status', 'display_name', 'type', 'company_name', 'trade_name']);
            });

            $results['clients'] = $clients->map(fn ($c) => new SearchResultDTO(
                id: $c->id,
                type: 'client',
                label: (string) ($c->display_name ?? $c->full_name),
                description: "{$c->email} · {$c->status}",
                route: "/clients/{$c->id}",
            ))->map(fn (SearchResultDTO $dto) => $dto->toArray())->values()->all();

            $totals['clients'] = count($results['clients']);
        }

        // Cases
        if ($user->can('cases.view')) {
            $cases = Cache::remember("{$cachePrefix}.cases", 30, function () use ($query, $tenantId, $limit) {
                return ImmigrationCase::where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->where(function ($q) use ($query) {
                        $q->where('case_number', 'LIKE', "%{$query}%")
                          ->orWhere('description', 'LIKE', "%{$query}%");
                    })
                    ->limit($limit)
                    ->get(['id', 'case_number', 'description', 'status']);
            });

            $results['cases'] = $cases->map(fn ($c) => new SearchResultDTO(
                id: $c->id,
                type: 'case',
                label: $c->case_number,
                description: mb_substr((string) $c->description, 0, 60) . " · {$c->status}",
                route: "/cases/{$c->id}",
            ))->map(fn (SearchResultDTO $dto) => $dto->toArray())->values()->all();

            $totals['cases'] = count($results['cases']);
        }

        // Companions
        if ($user->can('companions.view')) {
            $companions = Cache::remember("{$cachePrefix}.companions", 30, function () use ($query, $tenantId, $limit) {
                return Companion::where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->where(function ($q) use ($query) {
                        $q->where('first_name', 'LIKE', "%{$query}%")
                          ->orWhere('last_name', 'LIKE', "%{$query}%");
                    })
                    ->limit($limit)
                    ->get(['id', 'tenant_id', 'first_name', 'last_name', 'relationship', 'client_id']);
            });

            $results['companions'] = $companions->map(fn ($c) => new SearchResultDTO(
                id: $c->id,
                type: 'companion',
                label: $c->full_name,
                description: (string) $c->relationship,
                route: "/clients/{$c->client_id}",
            ))->map(fn (SearchResultDTO $dto) => $dto->toArray())->values()->all();

            $totals['companions'] = count($results['companions']);
        }

        // Events (always accessible - EventPolicy::viewAny returns true)
        $events = Cache::remember("{$cachePrefix}.events", 30, function () use ($query, $tenantId, $limit) {
            return Event::where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where(function ($q) use ($query) {
                    $q->where('title', 'LIKE', "%{$query}%")
                      ->orWhere('description', 'LIKE', "%{$query}%");
                })
                ->limit($limit)
                ->get(['id', 'title', 'start_date', 'category']);
        });

        $results['events'] = $events->map(fn ($e) => new SearchResultDTO(
            id: $e->id,
            type: 'event',
            label: $e->title,
            description: "{$e->start_date} · {$e->category}",
            route: '/apps/calendar',
        ))->map(fn (SearchResultDTO $dto) => $dto->toArray())->values()->all();

        $totals['events'] = count($results['events']);

        // Todos (always accessible - TodoPolicy::viewAny returns true, filtered by assigned_to_id)
        $todos = Cache::remember("{$cachePrefix}.todos.{$user->id}", 30, function () use ($query, $tenantId, $limit, $user) {
            return Todo::where('tenant_id', $tenantId)
                ->where('assigned_to_id', $user->id)
                ->where(function ($q) use ($query) {
                    $q->where('title', 'LIKE', "%{$query}%")
                      ->orWhere('description', 'LIKE', "%{$query}%");
                })
                ->limit($limit)
                ->get(['id', 'title', 'priority', 'status']);
        });

        $results['todos'] = $todos->map(fn ($t) => new SearchResultDTO(
            id: $t->id,
            type: 'todo',
            label: $t->title,
            description: "{$t->priority} · {$t->status}",
            route: '/apps/todolist',
        ))->map(fn (SearchResultDTO $dto) => $dto->toArray())->values()->all();

        $totals['todos'] = count($results['todos']);

        // Trash
        if ($user->can('trash.view')) {
            $trashResults = collect();

            $trashedClients = Cache::remember("{$cachePrefix}.trash.clients", 30, function () use ($query, $tenantId, $limit) {
                return Client::onlyTrashed()
                    ->where('tenant_id', $tenantId)
                    ->where(function ($q) use ($query) {
                        $q->where('display_name', 'LIKE', "%{$query}%")
                          ->orWhere('email', 'LIKE', "%{$query}%")
                          ->orWhere('tax_id', 'LIKE', "%{$query}%");
                    })
                    ->limit($limit)
                    ->get(['id', 'tenant_id', 'first_name', 'last_name', 'display_name', 'type', 'company_name', 'trade_name']);
            });

            foreach ($trashedClients as $c) {
                $trashResults->push(new SearchResultDTO(
                    id: $c->id,
                    type: 'trash',
                    label: (string) ($c->display_name ?? $c->full_name),
                    description: 'En papelera',
                    route: '/trash',
                ));
            }

            $trashedCases = Cache::remember("{$cachePrefix}.trash.cases", 30, function () use ($query, $tenantId, $limit) {
                return ImmigrationCase::onlyTrashed()
                    ->where('tenant_id', $tenantId)
                    ->where(function ($q) use ($query) {
                        $q->where('case_number', 'LIKE', "%{$query}%")
                          ->orWhere('description', 'LIKE', "%{$query}%");
                    })
                    ->limit($limit)
                    ->get(['id', 'case_number']);
            });

            foreach ($trashedCases as $c) {
                $trashResults->push(new SearchResultDTO(
                    id: $c->id,
                    type: 'trash',
                    label: $c->case_number,
                    description: 'En papelera',
                    route: '/trash',
                ));
            }

            $trashedCompanions = Cache::remember("{$cachePrefix}.trash.companions", 30, function () use ($query, $tenantId, $limit) {
                return Companion::onlyTrashed()
                    ->where('tenant_id', $tenantId)
                    ->where(function ($q) use ($query) {
                        $q->where('first_name', 'LIKE', "%{$query}%")
                          ->orWhere('last_name', 'LIKE', "%{$query}%");
                    })
                    ->limit($limit)
                    ->get(['id', 'tenant_id', 'first_name', 'last_name']);
            });

            foreach ($trashedCompanions as $c) {
                $trashResults->push(new SearchResultDTO(
                    id: $c->id,
                    type: 'trash',
                    label: $c->full_name,
                    description: 'En papelera',
                    route: '/trash',
                ));
            }

            $results['trash'] = $trashResults->map(fn (SearchResultDTO $dto) => $dto->toArray())->values()->all();
            $totals['trash'] = count($results['trash']);
        }

        return [
            'results' => $results,
            'totals'  => $totals,
        ];
    }
}
