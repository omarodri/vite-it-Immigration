<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CaseType\StoreCaseTypeRequest;
use App\Http\Requests\CaseType\UpdateCaseTypeRequest;
use App\Http\Resources\CaseTypeResource;
use App\Models\CaseType;
use App\Services\Case\CaseTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CaseTypeController extends Controller
{
    public function __construct(private readonly CaseTypeService $caseTypeService) {}

    /**
     * GET /api/case-types — ruta pública (wizard de expedientes).
     * Devuelve solo tipos activos del tenant; no requiere permiso específico.
     */
    public function publicIndex(): AnonymousResourceCollection
    {
        return CaseTypeResource::collection(
            CaseType::where('is_active', true)->orderBy('name')->get()
        );
    }

    /**
     * GET /api/admin/case-types — ruta admin con conteos de etapas y tareas.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CaseType::class);

        return CaseTypeResource::collection(
            $this->caseTypeService->getWithWorkflowCount()
        );
    }

    public function store(StoreCaseTypeRequest $request): CaseTypeResource
    {
        $this->authorize('create', CaseType::class);

        $caseType = new CaseType($request->validated());
        $caseType->tenant_id = app('tenant')->id;
        $caseType->save();

        return new CaseTypeResource($caseType);
    }

    public function show(CaseType $caseType): CaseTypeResource
    {
        $this->authorize('view', $caseType);

        return new CaseTypeResource($caseType->load('workflowStages'));
    }

    public function update(UpdateCaseTypeRequest $request, CaseType $caseType): CaseTypeResource
    {
        $this->authorize('update', $caseType);

        $caseType->update($request->validated());

        return new CaseTypeResource($caseType);
    }

    public function destroy(CaseType $caseType): JsonResponse
    {
        $this->authorize('delete', $caseType);

        // Soft-delete siempre libre (D7). El contador informativo se expone
        // en activeCasesCount() para que el frontend muestre el aviso antes
        // de confirmar. La única protección dura es el ->restrictOnDelete()
        // de BD para hard-delete, que nunca se alcanza desde este endpoint.
        $caseType->delete();    // soft-delete; el observer propaga a stages y tasks

        return response()->json(['message' => __('case_types.deleted')]);
    }

    /**
     * GET /api/admin/case-types/{caseType}/active-cases-count
     * Usado por el frontend para mostrar el aviso informativo antes del borrado.
     */
    public function activeCasesCount(CaseType $caseType): JsonResponse
    {
        $this->authorize('delete', $caseType);

        return response()->json([
            'count' => $this->caseTypeService->getActiveCasesCount($caseType),
        ]);
    }

    /**
     * POST /api/admin/case-types/{caseType}/clone
     * Clonación en cascada síncrona: CaseType → Stages → Tasks → Translations.
     */
    public function clone(CaseType $caseType): CaseTypeResource
    {
        $this->authorize('clone', $caseType);

        $clone = $this->caseTypeService->clone($caseType);

        return new CaseTypeResource($clone);
    }
}
