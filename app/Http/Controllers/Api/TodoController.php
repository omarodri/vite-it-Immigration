<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Todo\BulkDeleteTodoRequest;
use App\Http\Requests\Todo\StoreTodoRequest;
use App\Http\Requests\Todo\UpdateTodoRequest;
use App\Http\Requests\Todo\UpdateTodoStatusRequest;
use App\Http\Resources\TodoResource;
use App\Models\Todo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TodoController extends Controller
{
    /**
     * GET /api/todos
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Todo::with(['assignedTo', 'immigrationCase.client'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when(! $request->filled('status'), fn ($q) => $q->where('status', '!=', 'trash'))
            ->when($request->filled('tag'), fn ($q) => $q->where('tag', $request->tag))
            ->when($request->assigned_to_id, fn ($q, $id) => $q->where('assigned_to_id', $id))
            ->when($request->case_id, fn ($q, $id) => $q->where('case_id', $id))
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->latest();

        return TodoResource::collection(
            $query->paginate($request->per_page ?? 10)
        );
    }

    /**
     * POST /api/todos
     */
    public function store(StoreTodoRequest $request): JsonResponse
    {
        $todo = Todo::create(array_merge($request->validated(), [
            'tenant_id' => auth()->user()->tenant_id,
        ]));

        $todo->load(['assignedTo', 'immigrationCase.client']);

        return (new TodoResource($todo))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/todos/{todo}
     */
    public function show(Todo $todo): TodoResource
    {
        $todo->load(['assignedTo', 'immigrationCase.client']);

        return new TodoResource($todo);
    }

    /**
     * PUT /api/todos/{todo}
     */
    public function update(UpdateTodoRequest $request, Todo $todo): TodoResource
    {
        $todo->update($request->validated());
        $todo->load(['assignedTo', 'immigrationCase.client']);

        return new TodoResource($todo);
    }

    /**
     * DELETE /api/todos/{todo}
     */
    public function destroy(Todo $todo): \Illuminate\Http\Response
    {
        $todo->delete();

        return response()->noContent();
    }

    /**
     * PATCH /api/todos/{todo}/status
     */
    public function updateStatus(UpdateTodoStatusRequest $request, Todo $todo): TodoResource
    {
        $todo->update(['status' => $request->status]);
        $todo->load(['assignedTo', 'immigrationCase.client']);

        return new TodoResource($todo);
    }

    /**
     * DELETE /api/todos/bulk
     */
    public function bulkDestroy(BulkDeleteTodoRequest $request): \Illuminate\Http\Response
    {
        Todo::whereIn('id', $request->ids)->delete();

        return response()->noContent();
    }
}
