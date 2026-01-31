<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    #[OA\Get(
        path: '/api/activity-logs',
        summary: 'List activity logs (paginated)',
        tags: ['Activity Logs'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Search in description, log_name, causer name or email'),
            new OA\Parameter(name: 'log_name', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Filter by log name'),
            new OA\Parameter(name: 'event', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Filter by event type (created, updated, deleted)'),
            new OA\Parameter(name: 'causer_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), description: 'Filter by causer user ID'),
            new OA\Parameter(name: 'subject_type', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Filter by subject type (model class)'),
            new OA\Parameter(name: 'subject_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), description: 'Filter by subject ID'),
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date-time'), description: 'Filter from date'),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date-time'), description: 'Filter to date'),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated activity logs'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('activity-logs.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = Activity::with('causer', 'subject')->latest();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('log_name', 'like', "%{$search}%")
                    ->orWhereHas('causer', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($logName = $request->get('log_name')) {
            $query->where('log_name', $logName);
        }

        if ($event = $request->get('event')) {
            $query->where('event', $event);
        }

        if ($causerId = $request->get('causer_id')) {
            $query->where('causer_id', $causerId);
        }

        if ($subjectType = $request->get('subject_type')) {
            $query->where('subject_type', $subjectType);
        }

        if ($subjectId = $request->get('subject_id')) {
            $query->where('subject_id', $subjectId);
        }

        if ($from = $request->get('from')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->where('created_at', '<=', $to);
        }

        $perPage = (int) $request->get('per_page', 20);
        $activities = $query->paginate($perPage);

        return response()->json($activities);
    }

    #[OA\Get(
        path: '/api/activity-logs/{activity}',
        summary: 'Get activity log details',
        tags: ['Activity Logs'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'activity', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Activity log details with causer and subject'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Request $request, Activity $activity): JsonResponse
    {
        if (! $request->user()->can('activity-logs.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $activity->load('causer', 'subject');

        return response()->json($activity);
    }
}
