<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('activity-logs.view')) {
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

    public function show(Request $request, Activity $activity): JsonResponse
    {
        if (!$request->user()->can('activity-logs.view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $activity->load('causer', 'subject');

        return response()->json($activity);
    }
}
