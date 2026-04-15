<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Search\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __construct(private GlobalSearchService $searchService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q'     => ['required', 'string', 'min:2', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $results = $this->searchService->search(
            $validated['q'],
            (int) ($validated['limit'] ?? 5),
        );

        return response()->json(array_merge(['query' => $validated['q']], $results));
    }
}
