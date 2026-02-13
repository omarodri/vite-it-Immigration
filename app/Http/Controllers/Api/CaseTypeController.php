<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CaseTypeResource;
use App\Models\CaseType;
use App\Repositories\Contracts\CaseTypeRepositoryInterface;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class CaseTypeController extends Controller
{
    public function __construct(
        private CaseTypeRepositoryInterface $caseTypeRepository
    ) {}

    /**
     * List all active case types for the current tenant.
     */
    public function index(): AnonymousResourceCollection
    {
        $tenantId = Auth::user()->tenant_id;
        $caseTypes = $this->caseTypeRepository->getActive($tenantId);

        return CaseTypeResource::collection($caseTypes);
    }

    /**
     * Display a specific case type.
     */
    public function show(CaseType $caseType): CaseTypeResource
    {
        $tenantId = Auth::user()->tenant_id;

        // Verify case type is global or belongs to tenant
        abort_if(
            $caseType->tenant_id !== null && $caseType->tenant_id !== $tenantId,
            404,
            'Case type not found.'
        );

        return new CaseTypeResource($caseType);
    }
}
