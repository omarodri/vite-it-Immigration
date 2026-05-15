<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIrccCredentialRequest;
use App\Http\Resources\IrccCredentialResource;
use App\Models\ImmigrationCase;
use App\Services\Ircc\IrccAuditService;
use App\Services\Ircc\IrccCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Spec 60 — IRCC Credentials Vault controller.
 *
 * Endpoints:
 *  GET  /api/cases/{case}/ircc-credentials/availability  (no audit, UX check)
 *  GET  /api/cases/{case}/ircc-credentials               (decrypts + audits read)
 *  PUT  /api/cases/{case}/ircc-credentials               (upsert + audits write)
 *
 * Authorization is enforced via dedicated Gate abilities defined in
 * AuthServiceProvider (`ircc_credentials.view`, `ircc_credentials.update`).
 * Gate::before bypass for super-admin / admin applies automatically.
 */
class IrccCredentialController extends Controller
{
    public function __construct(
        private readonly IrccCredentialService $service,
        private readonly IrccAuditService $auditService,
    ) {}

    /**
     * Lightweight permission probe used by the SPA to decide whether to
     * render the "IRCC Credentials" tab. Intentionally NOT audited — this
     * is a UX-only check, not a sensitive operation.
     */
    public function availability(ImmigrationCase $case): JsonResponse
    {
        $available = Gate::allows('ircc_credentials.view', $case);

        return response()->json(['available' => $available]);
    }

    /**
     * Return the decrypted credential bundle for this case.
     * If no record exists yet, returns an empty-shaped payload (exists=false).
     */
    public function show(ImmigrationCase $case): IrccCredentialResource
    {
        $this->authorize('ircc_credentials.view', $case);

        $credential = $this->service->getForCase($case);

        if ($credential) {
            $this->auditService->logRead($credential, Auth::user());
        }

        return new IrccCredentialResource($credential);
    }

    /**
     * Create or update the credential bundle for this case.
     *
     * Only the submitted (validated) field names are recorded in the audit
     * trail — we never log the values themselves.
     */
    public function update(StoreIrccCredentialRequest $request, ImmigrationCase $case): JsonResponse
    {
        $this->authorize('ircc_credentials.update', $case);

        $data = $request->validated();
        $changedFields = array_keys($data);

        $credential = $this->service->upsert($case, $data, Auth::user());
        $this->auditService->logWrite($credential, Auth::user(), $changedFields);

        return response()->json([
            'success'    => true,
            'updated_at' => $credential->updated_at?->toISOString(),
        ]);
    }
}
