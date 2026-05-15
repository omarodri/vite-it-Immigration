<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ClientPromotionConflictException;
use App\Exceptions\CompanionNotEligibleForPromotionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromoteCompanionToClientRequest;
use App\Models\Companion;
use App\Services\Client\ClientPromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ClientPromotionController extends Controller
{
    public function __construct(private ClientPromotionService $promotionService) {}

    public function store(PromoteCompanionToClientRequest $request, Companion $companion): JsonResponse
    {
        $this->authorize('promoteToClient', $companion);

        try {
            $client = $this->promotionService->promote($companion, Auth::user());
        } catch (CompanionNotEligibleForPromotionException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code'    => $e->errorCode,
                'missing' => $e->missing,
            ], 422);
        } catch (ClientPromotionConflictException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code'    => $e->errorCode,
            ], 409);
        }

        return response()->json([
            'data'    => $client,
            'message' => 'Companion promoted to client successfully.',
        ], 201);
    }

    public function eligibility(Companion $companion): JsonResponse
    {
        $this->authorize('promoteToClient', $companion);

        return response()->json([
            'data' => $this->promotionService->checkEligibility($companion),
        ]);
    }
}
