<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Case\BulkUpdateCaseInvoicesRequest;
use App\Http\Resources\CaseResource;
use App\Models\ImmigrationCase;
use App\Services\Case\CaseInvoiceService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class CaseInvoiceController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CaseInvoiceService $caseInvoiceService
    ) {}

    /**
     * PUT /api/cases/{case}/invoices
     * Replace all invoices for a case (delete-and-insert).
     */
    public function bulkUpdate(BulkUpdateCaseInvoicesRequest $request, ImmigrationCase $case): JsonResponse
    {
        $this->caseInvoiceService->syncInvoices($case, $request->validated()['invoices']);

        $case->load(['client', 'caseType', 'assignedTo', 'companions', 'importantDates', 'tasks', 'invoices']);

        return response()->json([
            'data' => new CaseResource($case),
            'message' => 'Invoices updated successfully.',
        ]);
    }
}
