<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScrumColumnResource;
use App\Models\ScrumColumn;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScrumBoardController extends Controller
{
    /**
     * GET /api/scrum/board
     * Returns full board (columns + tasks) for the current tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $columns = ScrumColumn::with(['tasks' => function ($q) {
            $q->with(['assignedTo', 'immigrationCase.client'])
                ->orderBy('order_index');
        }])->orderBy('order_index')->get();

        return response()->json([
            'data' => ScrumColumnResource::collection($columns),
        ]);
    }

    /**
     * GET /api/scrum/assignees
     * Returns users with roles consultor, apoyo, or admin that belong to the current tenant.
     */
    public function assignees(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $users = User::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['consultor', 'apoyo']))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json(['data' => $users]);
    }
}
