<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorConfirmRequest;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class TwoFactorController extends Controller
{
    protected TwoFactorService $twoFactorService;

    public function __construct(TwoFactorService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    #[OA\Post(
        path: '/api/two-factor/enable',
        summary: 'Enable two-factor authentication',
        description: 'Generate QR code, secret, and recovery codes for 2FA setup',
        tags: ['Two-Factor'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: '2FA setup initiated with QR code and recovery codes'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        $secret = $this->twoFactorService->generateSecret();
        $qrCode = $this->twoFactorService->generateQrCodeSvg($user, $secret);
        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();

        // Store secret and recovery codes but don't confirm yet
        $user->two_factor_secret = $secret;
        $user->two_factor_recovery_codes = $recoveryCodes;
        $user->save();

        return response()->json([
            'qr_code' => $qrCode,
            'secret' => $secret,
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    #[OA\Post(
        path: '/api/two-factor/confirm',
        summary: 'Confirm two-factor authentication',
        description: 'Verify TOTP code to complete 2FA setup',
        tags: ['Two-Factor'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', example: '123456', description: 'TOTP code (6 digits)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '2FA confirmed successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Invalid code or 2FA not enabled'),
        ]
    )]
    public function confirm(TwoFactorConfirmRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->two_factor_secret) {
            throw ValidationException::withMessages([
                'code' => ['Two-factor authentication is not enabled.'],
            ]);
        }

        if (! $this->twoFactorService->verifyCode($user->two_factor_secret, $request->code)) {
            throw ValidationException::withMessages([
                'code' => ['The provided code is invalid.'],
            ]);
        }

        $user->two_factor_confirmed_at = now();
        $user->save();

        return response()->json([
            'message' => 'Two-factor authentication confirmed successfully.',
        ]);
    }

    #[OA\Delete(
        path: '/api/two-factor/disable',
        summary: 'Disable two-factor authentication',
        tags: ['Two-Factor'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '2FA disabled successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Incorrect password'),
        ]
    )]
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password is incorrect.'],
            ]);
        }

        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return response()->json([
            'message' => 'Two-factor authentication disabled successfully.',
        ]);
    }

    #[OA\Get(
        path: '/api/two-factor/recovery-codes',
        summary: 'Get recovery codes',
        tags: ['Two-Factor'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Recovery codes retrieved'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'recovery_codes' => $user->two_factor_recovery_codes ?? [],
        ]);
    }

    #[OA\Post(
        path: '/api/two-factor/recovery-codes',
        summary: 'Regenerate recovery codes',
        tags: ['Two-Factor'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Recovery codes regenerated'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Incorrect password'),
        ]
    )]
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password is incorrect.'],
            ]);
        }

        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();
        $user->two_factor_recovery_codes = $recoveryCodes;
        $user->save();

        return response()->json([
            'recovery_codes' => $recoveryCodes,
            'message' => 'Recovery codes regenerated successfully.',
        ]);
    }
}
