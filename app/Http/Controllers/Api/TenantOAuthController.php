<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantResource;
use App\Services\OAuthCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantOAuthController extends Controller
{
    public function __construct(
        private OAuthCredentialService $oauthService
    ) {}

    /**
     * Get OAuth configuration status.
     */
    public function status(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        return response()->json([
            'microsoft' => [
                'configured' => $tenant->hasMicrosoftOAuth(),
                'client_id' => $tenant->ms_client_id ? $this->maskString($tenant->ms_client_id) : null,
            ],
            'google' => [
                'configured' => $tenant->hasGoogleOAuth(),
                'client_id' => $tenant->google_client_id ? $this->maskString($tenant->google_client_id) : null,
            ],
            'system_fallback' => [
                'microsoft_available' => $this->oauthService->hasSystemMicrosoftCredentials(),
                'google_available' => $this->oauthService->hasSystemGoogleCredentials(),
            ],
        ]);
    }

    /**
     * Update Microsoft OAuth credentials.
     */
    public function updateMicrosoft(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:500'],
        ]);

        $tenant = $request->user()->tenant;

        // Validate credentials with Microsoft
        $validation = $this->oauthService->validateMicrosoftCredentials(
            $request->client_id,
            $request->client_secret
        );

        if (!$validation['valid']) {
            return response()->json([
                'message' => 'Invalid Microsoft OAuth credentials.',
                'error' => $validation['error'] ?? 'Validation failed',
            ], 422);
        }

        // Save credentials
        $tenant->update([
            'ms_client_id' => $request->client_id,
            'ms_client_secret' => $request->client_secret,
        ]);

        return response()->json([
            'message' => 'Microsoft OAuth credentials saved successfully.',
            'configured' => true,
        ]);
    }

    /**
     * Update Google OAuth credentials.
     */
    public function updateGoogle(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:500'],
        ]);

        $tenant = $request->user()->tenant;

        // Validate credentials with Google
        $validation = $this->oauthService->validateGoogleCredentials(
            $request->client_id,
            $request->client_secret
        );

        if (!$validation['valid']) {
            return response()->json([
                'message' => 'Invalid Google OAuth credentials.',
                'error' => $validation['error'] ?? 'Validation failed',
            ], 422);
        }

        // Save credentials
        $tenant->update([
            'google_client_id' => $request->client_id,
            'google_client_secret' => $request->client_secret,
        ]);

        return response()->json([
            'message' => 'Google OAuth credentials saved successfully.',
            'configured' => true,
        ]);
    }

    /**
     * Remove Microsoft OAuth credentials.
     */
    public function removeMicrosoft(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $tenant->update([
            'ms_client_id' => null,
            'ms_client_secret' => null,
        ]);

        return response()->json([
            'message' => 'Microsoft OAuth credentials removed.',
            'configured' => false,
        ]);
    }

    /**
     * Remove Google OAuth credentials.
     */
    public function removeGoogle(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $tenant->update([
            'google_client_id' => null,
            'google_client_secret' => null,
        ]);

        return response()->json([
            'message' => 'Google OAuth credentials removed.',
            'configured' => false,
        ]);
    }

    /**
     * Mask a string for display (show first 4 and last 4 chars).
     */
    private function maskString(string $value): string
    {
        $length = strlen($value);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 4) . str_repeat('*', $length - 8) . substr($value, -4);
    }
}
