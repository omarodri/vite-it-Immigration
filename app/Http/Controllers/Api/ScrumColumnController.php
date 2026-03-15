<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scrum\ReorderScrumColumnsRequest;
use App\Http\Requests\Scrum\StoreScrumColumnRequest;
use App\Http\Requests\Scrum\UpdateScrumColumnRequest;
use App\Http\Resources\ScrumColumnResource;
use App\Models\ScrumColumn;
use Illuminate\Http\JsonResponse;

class ScrumColumnController extends Controller
{
    /**
     * POST /api/scrum/columns
     */
    public function store(StoreScrumColumnRequest $request): JsonResponse
    {
        $maxOrder = ScrumColumn::max('order_index') ?? 0;

        $column = ScrumColumn::create([
            'title' => $request->title,
            'order_index' => $maxOrder + 1000,
        ]);

        return (new ScrumColumnResource($column->load('tasks')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PATCH /api/scrum/columns/{scrumColumn}
     */
    public function update(UpdateScrumColumnRequest $request, ScrumColumn $scrumColumn): ScrumColumnResource
    {
        $scrumColumn->update(['title' => $request->title]);

        return new ScrumColumnResource($scrumColumn->load('tasks'));
    }

    /**
     * PATCH /api/scrum/columns/reorder
     */
    public function reorder(ReorderScrumColumnsRequest $request): JsonResponse
    {
        foreach ($request->columns as $item) {
            ScrumColumn::where('id', $item['id'])->update(['order_index' => $item['order_index']]);
        }

        return response()->json(['message' => 'Columns reordered successfully.']);
    }

    /**
     * DELETE /api/scrum/columns/{scrumColumn}
     */
    public function destroy(ScrumColumn $scrumColumn): JsonResponse|\Illuminate\Http\Response
    {
        if ($scrumColumn->tasks()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar una columna que contiene tareas. Mueva o elimine las tareas primero.',
            ], 422);
        }

        $scrumColumn->delete();

        return response()->noContent();
    }
}
