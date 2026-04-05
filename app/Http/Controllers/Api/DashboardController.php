<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardCaseResource;
use App\Http\Resources\DashboardEventResource;
use App\Http\Resources\DashboardTodoResource;
use App\Models\Client;
use App\Models\Event;
use App\Models\ImmigrationCase;
use App\Models\LegalDocument;
use App\Models\Todo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Consolidated dashboard endpoint.
     *
     * Returns metrics, assigned tasks, upcoming events, and recent cases
     * for the authenticated user in a single API call.
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $today = now()->toDateString();

        // -- Metrics ----------------------------------------------------------
        $activeCasesCount = $user->can('cases.view')
            ? ImmigrationCase::active()->byAssignee($user->id)->count()
            : 0;

        $todayEventsCount = Event::where(fn ($q) =>
                $q->where('assigned_to_id', $user->id)
                  ->orWhere('created_by', $user->id)
            )->whereDate('start_date', $today)->count();

        $pendingTodosCount = Todo::where('assigned_to_id', $user->id)
            ->whereIn('status', ['pending', 'important'])
            ->count();

        // -- Assigned Tasks (max 50) ------------------------------------------
        $assignedTasks = $user->can('tasks.view')
            ? Todo::with(['immigrationCase:id,case_number'])
                  ->where('assigned_to_id', $user->id)
                  ->whereIn('status', ['pending', 'important'])
                  ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
                  ->orderBy('due_date')
                  ->limit(50)
                  ->get()
            : collect();

        // -- Upcoming Events (next 10) ----------------------------------------
        $upcomingEvents = Event::where(fn ($q) =>
                $q->where('assigned_to_id', $user->id)
                  ->orWhere('created_by', $user->id)
            )->where('start_date', '>=', now())
             ->orderBy('start_date')
             ->limit(10)
             ->get();

        // -- Recent Cases (last 8 viewed) -------------------------------------
        $recentCases = collect();
        if ($user->can('cases.view')) {
            $recentCaseIds = DB::table('user_case_history')
                ->where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->orderByDesc('viewed_at')
                ->limit(8)
                ->pluck('case_id');

            if ($recentCaseIds->isNotEmpty()) {
                $recentCases = ImmigrationCase::with([
                        'client:id,first_name,last_name',
                        'caseType:id,name,code',
                        'importantDates' => fn ($q) => $q
                            ->whereNotNull('due_date')
                            ->whereDate('due_date', '>=', $today)
                            ->orderBy('due_date')
                            ->limit(1),
                    ])
                    ->whereIn('id', $recentCaseIds)
                    ->get()
                    ->sortBy(fn ($c) => $recentCaseIds->search($c->id))
                    ->values();
            }
        }

        // -- Expiring Documents (top 5) ------------------------------------
        $expiringDocuments = LegalDocument::query()
            ->where('tenant_id', $user->tenant_id)
            ->expiringSoon()
            ->with('documentable')
            ->orderBy('expiry_date')
            ->limit(5)
            ->get()
            ->map(fn ($d) => [
                'id'              => $d->id,
                'document_type'   => $d->document_type,
                'display_name'    => $d->display_name,
                'document_number' => $d->document_number,
                'expiry_date'     => $d->expiry_date?->format('Y-m-d'),
                'days_remaining'  => $d->days_remaining,
                'alert_status'    => $d->alert_status,
                'entity_name'     => $d->documentable?->full_name,
                'entity_type'     => $d->documentable instanceof Client ? 'client' : 'companion',
                'entity_id'       => $d->documentable_id,
                'client_id'       => $d->documentable instanceof Client
                                     ? $d->documentable_id
                                     : $d->documentable?->client_id,
            ]);

        return response()->json([
            'data' => [
                'metrics'         => [
                    'active_cases_assigned_to_me' => $activeCasesCount,
                    'today_events'                => $todayEventsCount,
                    'pending_todos'               => $pendingTodosCount,
                ],
                'assigned_tasks'      => DashboardTodoResource::collection($assignedTasks),
                'upcoming_events'     => DashboardEventResource::collection($upcomingEvents),
                'recent_cases'        => DashboardCaseResource::collection($recentCases),
                'expiring_documents'  => $expiringDocuments,
            ],
        ]);
    }
}
