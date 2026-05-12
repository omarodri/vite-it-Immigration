<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ImportantDateAlertResource;
use App\Models\CaseImportantDate;
use App\Models\Event;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportantDateAlertController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CaseImportantDate::class);

        $tenantId = (int) auth()->user()->tenant_id;
        $today    = now();

        $validated = $request->validate([
            'consultant_id'      => ['nullable', 'integer'],
            'case_type_id'       => ['nullable', 'integer'],
            'urgency'            => ['nullable', 'string', 'in:overdue,today,this_week,upcoming,all'],
            'search'             => ['nullable', 'string', 'max:100'],
            'sort_by'            => ['nullable', 'string', 'in:due_date,urgency,case_number'],
            'sort_dir'           => ['nullable', 'string', 'in:asc,desc'],
            'per_page'           => ['nullable', 'integer', 'min:1', 'max:100'],
            'page'               => ['nullable', 'integer', 'min:1'],
            'window_past_days'   => ['nullable', 'integer', 'min:1', 'max:90'],
            'window_future_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $pastDays   = (int) ($validated['window_past_days'] ?? 30);
        $futureDays = (int) ($validated['window_future_days'] ?? 30);
        $from       = $today->copy()->subDays($pastDays)->toDateString();
        $to         = $today->copy()->addDays($futureDays)->toDateString();
        $sortBy     = $validated['sort_by'] ?? 'urgency';
        $sortDir    = $validated['sort_dir'] ?? 'asc';

        $query = CaseImportantDate::query()
            ->select('case_important_dates.*')
            ->selectRaw('DATEDIFF(case_important_dates.due_date, CURDATE()) as days_diff')
            ->join('cases', 'cases.id', '=', 'case_important_dates.case_id')
            ->where('cases.tenant_id', $tenantId)
            ->whereNull('cases.deleted_at')
            ->inDateWindow($from, $to)
            ->with([
                'immigrationCase:id,tenant_id,case_number,client_id,case_type_id,assigned_to',
                'immigrationCase.client:id,tenant_id,first_name,last_name',
                'immigrationCase.caseType:id,name',
                'immigrationCase.assignedTo:id,name',
            ]);

        if (! empty($validated['consultant_id'])) {
            $query->where('cases.assigned_to', $validated['consultant_id']);
        }

        if (! empty($validated['case_type_id'])) {
            $query->where('cases.case_type_id', $validated['case_type_id']);
        }

        if (! empty($validated['urgency']) && $validated['urgency'] !== 'all') {
            $todayStr = $today->toDateString();

            if ($validated['urgency'] === 'overdue') {
                $query->where('case_important_dates.due_date', '<', $todayStr);
            } elseif ($validated['urgency'] === 'today') {
                $query->whereDate('case_important_dates.due_date', $todayStr);
            } elseif ($validated['urgency'] === 'this_week') {
                $query->whereBetween('case_important_dates.due_date', [
                    $todayStr,
                    $today->copy()->addDays(7)->toDateString(),
                ]);
            } elseif ($validated['urgency'] === 'upcoming') {
                $query->where('case_important_dates.due_date', '>=', $todayStr);
            }
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('case_important_dates.label', 'like', "%{$search}%")
                  ->orWhere('cases.case_number', 'like', "%{$search}%");
            });
        }

        if ($sortBy === 'due_date') {
            $query->orderBy('case_important_dates.due_date', $sortDir);
        } elseif ($sortBy === 'case_number') {
            $query->orderBy('cases.case_number', $sortDir);
        } else {
            $query->orderByRaw('ABS(DATEDIFF(case_important_dates.due_date, CURDATE())) ASC')
                  ->orderBy('case_important_dates.due_date', 'asc');
        }

        $paginated  = $query->paginate((int) ($validated['per_page'] ?? 25));
        $collection = ImportantDateAlertResource::collection($paginated);
        $response   = $collection->response($request)->getData(true);

        $response['meta']['window'] = [
            'from'  => $from,
            'to'    => $to,
            'today' => $today->toDateString(),
        ];
        $response['meta']['counts_by_urgency'] = $this->countsByUrgency($tenantId, $from, $to, $validated);

        return response()->json($response);
    }

    public function linkEvent(Request $request, int $id): JsonResponse
    {
        $date = CaseImportantDate::with('immigrationCase')->findOrFail($id);
        $this->authorize('update', $date);

        $validated = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
        ]);

        $tenantId = (int) auth()->user()->tenant_id;

        $event = Event::where('id', $validated['event_id'])
                       ->where('tenant_id', $tenantId)
                       ->firstOrFail();

        $date->update(['calendar_event_id' => $event->id]);

        $date->load([
            'immigrationCase:id,tenant_id,case_number,client_id,case_type_id,assigned_to',
            'immigrationCase.client:id,tenant_id,first_name,last_name',
            'immigrationCase.caseType:id,name',
            'immigrationCase.assignedTo:id,name',
        ]);

        $date->days_diff = (int) now()->startOfDay()->diffInDays(
            $date->due_date instanceof \Carbon\Carbon
                ? $date->due_date->copy()->startOfDay()
                : \Carbon\Carbon::parse($date->due_date)->startOfDay(),
            false
        );

        return response()->json(['data' => new ImportantDateAlertResource($date)]);
    }

    private function countsByUrgency(int $tenantId, string $from, string $to, array $filters): array
    {
        $query = CaseImportantDate::query()
            ->join('cases', 'cases.id', '=', 'case_important_dates.case_id')
            ->where('cases.tenant_id', $tenantId)
            ->whereNull('cases.deleted_at')
            ->whereBetween('case_important_dates.due_date', [$from, $to]);

        if (! empty($filters['consultant_id'])) {
            $query->where('cases.assigned_to', $filters['consultant_id']);
        }

        if (! empty($filters['case_type_id'])) {
            $query->where('cases.case_type_id', $filters['case_type_id']);
        }

        $row = $query->selectRaw(
            'SUM(CASE WHEN DATEDIFF(case_important_dates.due_date, CURDATE()) < -7  THEN 1 ELSE 0 END) as overdue_critical,'
            . 'SUM(CASE WHEN DATEDIFF(case_important_dates.due_date, CURDATE()) BETWEEN -7 AND -1 THEN 1 ELSE 0 END) as overdue_recent,'
            . 'SUM(CASE WHEN DATEDIFF(case_important_dates.due_date, CURDATE()) = 0 THEN 1 ELSE 0 END) as today,'
            . 'SUM(CASE WHEN DATEDIFF(case_important_dates.due_date, CURDATE()) BETWEEN 1 AND 7 THEN 1 ELSE 0 END) as upcoming_week,'
            . 'SUM(CASE WHEN DATEDIFF(case_important_dates.due_date, CURDATE()) > 7 THEN 1 ELSE 0 END) as upcoming_month'
        )->first();

        return [
            'overdue_critical' => (int) ($row->overdue_critical ?? 0),
            'overdue_recent'   => (int) ($row->overdue_recent ?? 0),
            'today'            => (int) ($row->today ?? 0),
            'upcoming_week'    => (int) ($row->upcoming_week ?? 0),
            'upcoming_month'   => (int) ($row->upcoming_month ?? 0),
        ];
    }
}
