<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Trash\ParentInTrashException;
use App\Exceptions\Trash\RestoreConflictException;
use App\Http\Controllers\Controller;
use App\Services\Trash\TrashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrashController extends Controller
{
    public function __construct(private TrashService $trashService) {}

    public function summary(): JsonResponse
    {
        $this->authorize('trash.view');

        return response()->json($this->trashService->summary());
    }

    public function index(string $type, Request $request): JsonResponse
    {
        $this->authorize('trash.view');

        $validated = $request->validate([
            'search'   => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $paginator = $this->trashService->listByType(
            $type,
            $validated['per_page'] ?? 15,
            $validated['search'] ?? null,
        );

        return response()->json($paginator);
    }

    public function restore(string $type, int $id): JsonResponse
    {
        $this->authorize('trash.restore');

        try {
            $item = $this->trashService->restore($type, $id);

            return response()->json(['message' => 'Restored successfully', 'id' => $item->id]);
        } catch (ParentInTrashException $e) {
            return response()->json([
                'message'    => $e->getMessage(),
                'error'      => 'parent_in_trash',
                'parent'     => [
                    'type' => $e->parentType,
                    'id'   => $e->parentId,
                    'name' => $e->parentName,
                ],
                'suggestion' => 'restore_with_parents',
            ], 422);
        } catch (RestoreConflictException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error'   => 'restore_conflict',
                'field'   => $e->field,
            ], 422);
        }
    }

    public function restoreWithParents(string $type, int $id): JsonResponse
    {
        $this->authorize('trash.restore');

        try {
            $chain = $this->trashService->restoreWithParents($type, $id);

            return response()->json(['message' => 'Restored successfully', 'chain' => $chain]);
        } catch (RestoreConflictException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error'   => 'restore_conflict',
                'field'   => $e->field,
            ], 422);
        }
    }

    public function forceDelete(string $type, int $id): JsonResponse
    {
        $this->authorize('trash.force-delete');

        $this->trashService->forceDelete($type, $id);

        return response()->json(['message' => 'Permanently deleted']);
    }

    public function purge(Request $request): JsonResponse
    {
        $this->authorize('trash.purge');

        $validated = $request->validate([
            'older_than_days' => ['nullable', 'integer', 'min:0'],
        ]);

        $counts = $this->trashService->purge($validated['older_than_days'] ?? 30);

        return response()->json(['message' => 'Purge completed', 'counts' => $counts]);
    }
}
