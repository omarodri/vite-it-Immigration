<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\RescheduleEventRequest;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\ImmigrationCase;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    /**
     * GET /api/events?start=&end=
     * FullCalendar event source — returns events in the requested date range.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $events = Event::with(['assignedTo', 'immigrationCase'])
            ->inDateRange($request->start, $request->end)
            ->when($request->case_id, fn ($q, $id) => $q->where('case_id', $id))
            ->when($request->assigned_to_id, fn ($q, $id) => $q->where('assigned_to_id', $id))
            ->orderBy('start_date')
            ->get();

        return EventResource::collection($events);
    }

    /**
     * POST /api/events
     */
    public function store(StoreEventRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();

        // Auto-populate client snapshot when a case is linked
        if (! empty($data['case_id'])) {
            $case = ImmigrationCase::with('client')->find($data['case_id']);
            if ($case) {
                $data['client_id']            = $case->client_id;
                $data['client_name_snapshot'] = $case->client?->full_name ?? '';
            }
        }

        $event = Event::create($data);
        $event->load(['assignedTo', 'immigrationCase']);

        return (new EventResource($event))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/events/{event}
     */
    public function show(Event $event): EventResource
    {
        $event->load(['assignedTo', 'immigrationCase']);

        return new EventResource($event);
    }

    /**
     * PUT /api/events/{event}
     */
    public function update(UpdateEventRequest $request, Event $event): EventResource
    {
        $data = $request->validated();

        // Re-snapshot client name when case changes
        if (array_key_exists('case_id', $data) && $data['case_id'] !== $event->case_id) {
            if ($data['case_id']) {
                $case = ImmigrationCase::with('client')->find($data['case_id']);
                if ($case) {
                    $data['client_id']            = $case->client_id;
                    $data['client_name_snapshot'] = $case->client?->full_name ?? '';
                }
            } else {
                $data['client_id']            = null;
                $data['client_name_snapshot'] = null;
            }
        }

        $event->update($data);
        $event->load(['assignedTo', 'immigrationCase']);

        return new EventResource($event);
    }

    /**
     * DELETE /api/events/{event}
     */
    public function destroy(Event $event): \Illuminate\Http\Response
    {
        $event->delete();

        return response()->noContent();
    }

    /**
     * PATCH /api/events/{event}/reschedule
     * Persists drag & drop or resize from FullCalendar.
     * Only updates start_date and end_date — does not touch snapshot or case_id.
     */
    public function reschedule(RescheduleEventRequest $request, Event $event): EventResource
    {
        $event->update($request->validated());
        $event->load(['assignedTo', 'immigrationCase']);

        return new EventResource($event);
    }

    /**
     * POST /api/events/{event}/clone
     * Duplicates the event; the clone inherits case_id and client snapshot.
     */
    public function clone(Event $event): JsonResponse
    {
        $clone = $event->replicate();
        $clone->title        = $event->title . ' (copia)';
        $clone->created_by   = auth()->id();
        $clone->save();

        $clone->load(['assignedTo', 'immigrationCase']);

        return (new EventResource($clone))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/events/assignees
     * Returns active users with consultor or apoyo role for the current tenant.
     */
    public function assignees(): JsonResponse
    {
        $users = User::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', true)
            ->role(['consultor', 'apoyo'])
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $users]);
    }
}
