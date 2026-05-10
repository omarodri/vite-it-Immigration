<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTimeLogRequest;
use App\Http\Requests\UpdateTimeLogRequest;
use App\Http\Resources\TimeLogResource;
use App\Models\ImmigrationCase;
use App\Models\TimeLog;
use App\Services\Timesheet\TimeLogService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TimeLogController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly TimeLogService $service) {}

    /**
     * GET /api/cases/{case}/time-logs
     */
    public function index(Request $request, ImmigrationCase $case): AnonymousResourceCollection
    {
        $this->authorize('time_logs.view');

        $logs = $this->service->getLogsForCase($case->id, $request->only([
            'user_id', 'date_from', 'date_to', 'per_page',
        ]));

        return TimeLogResource::collection($logs);
    }

    /**
     * POST /api/cases/{case}/time-logs
     */
    public function store(StoreTimeLogRequest $request, ImmigrationCase $case): TimeLogResource
    {
        $this->authorize('time_logs.create');

        $data = array_merge($request->validated(), ['case_id' => $case->id]);
        $log  = $this->service->logManual($request->user(), $data);

        return new TimeLogResource($log->load(['user', 'todo']));
    }

    /**
     * POST /api/cases/{case}/time-logs/start
     */
    public function start(Request $request, ImmigrationCase $case): TimeLogResource
    {
        $this->authorize('time_logs.create');

        $log = $this->service->startTimer(
            $request->user(),
            $case->id,
            $request->input('todo_id'),
        );

        return new TimeLogResource($log->load(['user', 'todo']));
    }

    /**
     * PATCH /api/cases/{case}/time-logs/{log}/stop
     */
    public function stop(Request $request, ImmigrationCase $case, TimeLog $log): TimeLogResource
    {
        $this->authorize('time_logs.edit');

        if ($log->case_id !== $case->id) {
            abort(404, 'Time log not found for this case.');
        }

        $stopped = $this->service->stopTimer($log->id, $request->user());

        return new TimeLogResource($stopped->load(['user', 'todo']));
    }

    /**
     * PUT /api/cases/{case}/time-logs/{log}
     */
    public function update(UpdateTimeLogRequest $request, ImmigrationCase $case, TimeLog $log): TimeLogResource
    {
        $this->authorize('time_logs.edit');

        if ($log->case_id !== $case->id) {
            abort(404, 'Time log not found for this case.');
        }

        $updated = $this->service->updateLog($log->id, $request->user(), $request->validated());

        return new TimeLogResource($updated->load(['user', 'todo']));
    }

    /**
     * DELETE /api/cases/{case}/time-logs/{log}
     */
    public function destroy(Request $request, ImmigrationCase $case, TimeLog $log): Response
    {
        $this->authorize('time_logs.delete');

        if ($log->case_id !== $case->id) {
            abort(404, 'Time log not found for this case.');
        }

        $this->service->deleteLog($log->id, $request->user());

        return response()->noContent();
    }

    /**
     * GET /api/me/active-timer
     */
    public function activeTimer(Request $request): TimeLogResource|JsonResponse
    {
        $log = $this->service->getActiveTimerForUser($request->user());

        if (! $log) {
            return response()->json(['data' => null]);
        }

        return new TimeLogResource($log);
    }
}
