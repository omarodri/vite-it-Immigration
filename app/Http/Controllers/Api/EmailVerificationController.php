<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class EmailVerificationController extends Controller
{
    #[OA\Post(
        path: '/api/email/verification-notification',
        summary: 'Send email verification notification',
        tags: ['Email Verification'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Verification link sent'),
            new OA\Response(response: 400, description: 'Email already verified'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function send(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified',
            ], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification link sent',
        ]);
    }

    #[OA\Get(
        path: '/api/email/verify/{id}/{hash}',
        summary: 'Verify email address',
        tags: ['Email Verification'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'User ID'),
            new OA\Parameter(name: 'hash', in: 'path', required: true, schema: new OA\Schema(type: 'string'), description: 'Verification hash'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Email verified successfully'),
            new OA\Response(response: 403, description: 'Invalid or expired verification link'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        // Check if the hash matches
        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'message' => 'Invalid verification link',
            ], 403);
        }

        // Check if the signature is valid (the route is already signed)
        if (! $request->hasValidSignature()) {
            return response()->json([
                'message' => 'Invalid or expired verification link',
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified',
                'verified' => true,
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'message' => 'Email verified successfully',
            'verified' => true,
        ]);
    }

    #[OA\Get(
        path: '/api/email/verification-status',
        summary: 'Check email verification status',
        tags: ['Email Verification'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Verification status'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'verified' => $request->user()->hasVerifiedEmail(),
            'email' => $request->user()->email,
        ]);
    }
}
