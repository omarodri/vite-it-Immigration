<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use OpenApi\Attributes as OA;

class PasswordResetController extends Controller
{
    public function __construct(
        private PasswordResetService $passwordResetService
    ) {}

    #[OA\Post(
        path: '/api/forgot-password',
        summary: 'Send password reset link',
        tags: ['Password Reset'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password reset link sent'),
            new OA\Response(response: 422, description: 'Validation error or user not found'),
            new OA\Response(response: 429, description: 'Too many attempts'),
        ]
    )]
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = $this->passwordResetService->sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json(['message' => __($status)]);
        }

        return response()->json([
            'message' => __($status),
            'errors' => ['email' => [__($status)]],
        ], 422);
    }

    #[OA\Post(
        path: '/api/reset-password',
        summary: 'Reset password with token',
        tags: ['Password Reset'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'token', type: 'string', description: 'Reset token from email'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password reset successful'),
            new OA\Response(response: 422, description: 'Invalid token or validation error'),
        ]
    )]
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = $this->passwordResetService->resetPassword(
            $request->only('email', 'password', 'password_confirmation', 'token')
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)]);
        }

        return response()->json([
            'message' => __($status),
            'errors' => ['email' => [__($status)]],
        ], 422);
    }

    #[OA\Get(
        path: '/api/verify-token/{token}/{email}',
        summary: 'Verify password reset token',
        tags: ['Password Reset'],
        parameters: [
            new OA\Parameter(name: 'token', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'email', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'email')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Token is valid'),
            new OA\Response(response: 400, description: 'Invalid or expired token'),
        ]
    )]
    public function verifyToken(string $token, string $email): JsonResponse
    {
        $result = $this->passwordResetService->verifyToken($token, $email);

        return response()->json([
            'valid' => $result['valid'],
            'message' => $result['message'],
        ], $result['status']);
    }
}
