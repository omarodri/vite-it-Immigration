<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorConfirmRequest;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    protected TwoFactorService $twoFactorService;

    public function __construct(TwoFactorService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    /**
     * Enable two-factor authentication for the user.
     */
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

    /**
     * Confirm two-factor authentication with a valid code.
     */
    public function confirm(TwoFactorConfirmRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->two_factor_secret) {
            throw ValidationException::withMessages([
                'code' => ['Two-factor authentication is not enabled.'],
            ]);
        }

        if (!$this->twoFactorService->verifyCode($user->two_factor_secret, $request->code)) {
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

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
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

    /**
     * Get recovery codes for the user.
     */
    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'recovery_codes' => $user->two_factor_recovery_codes ?? [],
        ]);
    }

    /**
     * Regenerate recovery codes for the user.
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
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
