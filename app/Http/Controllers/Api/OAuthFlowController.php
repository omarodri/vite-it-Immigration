<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OauthToken;
use App\Services\OAuthCredentialService;
use App\Services\OAuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OAuthFlowController extends Controller
{
    public function __construct(
        private readonly OAuthTokenService $tokenService,
        private readonly OAuthCredentialService $credentialService
    ) {}

    /**
     * Generate and return the OAuth authorization URL for a provider.
     */
    public function redirect(string $provider): JsonResponse
    {
        $user = Auth::user();
        $tenant = $user->tenant;

        $credentials = match ($provider) {
            'microsoft' => $this->credentialService->getMicrosoftCredentials($tenant),
            'google' => $this->credentialService->getGoogleCredentials($tenant),
            default => null,
        };

        if (!$credentials) {
            return response()->json([
                'message' => "No {$provider} OAuth credentials configured for this tenant.",
            ], 422);
        }

        // Generate state parameter for CSRF protection
        $state = Str::random(40);
        session(['oauth_state' => $state, 'oauth_user_id' => $user->id]);

        $authUrl = match ($provider) {
            'microsoft' => $this->buildMicrosoftAuthUrl($credentials, $state),
            'google' => $this->buildGoogleAuthUrl($credentials, $state),
        };

        return response()->json([
            'url' => $authUrl,
        ]);
    }

    /**
     * Handle the OAuth callback from the provider.
     * Exchange authorization code for tokens and store them.
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $frontendUrl = config('app.frontend_url', config('app.url'));

        if ($request->has('error')) {
            Log::error("OAuth {$provider} callback error", [
                'error' => $request->input('error'),
                'description' => $request->input('error_description'),
            ]);

            return redirect("{$frontendUrl}/settings/integrations?oauth_error=" . urlencode($request->input('error_description', 'Authorization failed')));
        }

        $code = $request->input('code');
        $state = $request->input('state');

        if (!$code) {
            return redirect("{$frontendUrl}/settings/integrations?oauth_error=No+authorization+code+received");
        }

        // Validate state
        $expectedState = session('oauth_state');
        $userId = session('oauth_user_id');

        if (!$expectedState || $state !== $expectedState || !$userId) {
            Log::error("OAuth {$provider} callback state mismatch", [
                'expected' => $expectedState,
                'received' => $state,
            ]);
            return redirect("{$frontendUrl}/settings/integrations?oauth_error=Invalid+state+parameter");
        }

        session()->forget(['oauth_state', 'oauth_user_id']);

        try {
            $user = \App\Models\User::findOrFail($userId);
            $tenant = $user->tenant;

            $credentials = match ($provider) {
                'microsoft' => $this->credentialService->getMicrosoftCredentials($tenant),
                'google' => $this->credentialService->getGoogleCredentials($tenant),
                default => null,
            };

            if (!$credentials) {
                return redirect("{$frontendUrl}/settings/integrations?oauth_error=No+credentials+configured");
            }

            $tokenData = match ($provider) {
                'microsoft' => $this->exchangeMicrosoftCode($code, $credentials),
                'google' => $this->exchangeGoogleCode($code, $credentials),
            };

            if (!$tokenData) {
                return redirect("{$frontendUrl}/settings/integrations?oauth_error=Token+exchange+failed");
            }

            $this->tokenService->storeToken($user, $provider, $tokenData);

            return redirect("{$frontendUrl}/settings/integrations?oauth_success={$provider}");
        } catch (\Exception $e) {
            Log::error("OAuth {$provider} callback exception", [
                'error' => $e->getMessage(),
            ]);

            return redirect("{$frontendUrl}/settings/integrations?oauth_error=" . urlencode('An unexpected error occurred'));
        }
    }

    /**
     * Return the OAuth connection status for the current user.
     */
    public function status(): JsonResponse
    {
        $user = Auth::user();
        $tenant = $user->tenant;

        $microsoftToken = OauthToken::where('user_id', $user->id)
            ->where('provider', 'microsoft')
            ->first();

        $googleToken = OauthToken::where('user_id', $user->id)
            ->where('provider', 'google')
            ->first();

        return response()->json([
            'storage_type' => $tenant->storage_type ?? 'local',
            'microsoft' => [
                'credentials_configured' => $this->credentialService->getMicrosoftCredentials($tenant) !== null,
                'connected' => $microsoftToken !== null,
                'expires_at' => $microsoftToken?->expires_at?->toIso8601String(),
                'is_expired' => $microsoftToken?->isExpired() ?? false,
            ],
            'google' => [
                'credentials_configured' => $this->credentialService->getGoogleCredentials($tenant) !== null,
                'connected' => $googleToken !== null,
                'expires_at' => $googleToken?->expires_at?->toIso8601String(),
                'is_expired' => $googleToken?->isExpired() ?? false,
            ],
        ]);
    }

    /**
     * Disconnect (revoke) OAuth tokens for a provider.
     */
    public function disconnect(string $provider): JsonResponse
    {
        $user = Auth::user();

        $revoked = $this->tokenService->revokeToken($user, $provider);

        if (!$revoked) {
            return response()->json([
                'message' => "No {$provider} connection found to disconnect.",
            ], 404);
        }

        return response()->json([
            'message' => ucfirst($provider) . ' account disconnected successfully.',
        ]);
    }

    /**
     * Build Microsoft OAuth authorization URL.
     */
    private function buildMicrosoftAuthUrl(array $credentials, string $state): string
    {
        $tenant = config('services.microsoft.tenant', 'common');
        $redirectUri = $this->getRedirectUri('microsoft');

        $params = http_build_query([
            'client_id' => $credentials['client_id'],
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'scope' => 'Files.ReadWrite.All offline_access',
            'state' => $state,
            'response_mode' => 'query',
        ]);

        return "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize?{$params}";
    }

    /**
     * Build Google OAuth authorization URL.
     */
    private function buildGoogleAuthUrl(array $credentials, string $state): string
    {
        $redirectUri = $this->getRedirectUri('google');

        $params = http_build_query([
            'client_id' => $credentials['client_id'],
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'scope' => 'https://www.googleapis.com/auth/drive.file',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        return "https://accounts.google.com/o/oauth2/v2/auth?{$params}";
    }

    /**
     * Exchange Microsoft authorization code for tokens.
     */
    private function exchangeMicrosoftCode(string $code, array $credentials): ?array
    {
        $tenant = config('services.microsoft.tenant', 'common');
        $redirectUri = $this->getRedirectUri('microsoft');

        $response = Http::timeout(30)->asForm()->post(
            "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",
            [
                'grant_type' => 'authorization_code',
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'scope' => 'Files.ReadWrite.All offline_access',
            ]
        );

        if (!$response->successful()) {
            Log::error('Microsoft token exchange failed', [
                'status' => $response->status(),
                'error' => $response->json('error_description', $response->json('error', 'Unknown error')),
            ]);
            return null;
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in' => $data['expires_in'] ?? 3600,
            'scopes' => explode(' ', $data['scope'] ?? ''),
        ];
    }

    /**
     * Exchange Google authorization code for tokens.
     */
    private function exchangeGoogleCode(string $code, array $credentials): ?array
    {
        $redirectUri = $this->getRedirectUri('google');

        $response = Http::timeout(30)->asForm()->post(
            'https://oauth2.googleapis.com/token',
            [
                'grant_type' => 'authorization_code',
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]
        );

        if (!$response->successful()) {
            Log::error('Google token exchange failed', [
                'status' => $response->status(),
                'error' => $response->json('error_description', $response->json('error', 'Unknown error')),
            ]);
            return null;
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in' => $data['expires_in'] ?? 3600,
            'scopes' => isset($data['scope']) ? explode(' ', $data['scope']) : [],
        ];
    }

    /**
     * Get the full redirect URI for a provider.
     */
    private function getRedirectUri(string $provider): string
    {
        $configUri = config("services.{$provider}.redirect_uri");

        // If it's a relative path, prepend the app URL
        if ($configUri && !str_starts_with($configUri, 'http')) {
            return rtrim(config('app.url'), '/') . '/' . ltrim($configUri, '/');
        }

        return $configUri ?? rtrim(config('app.url'), '/') . "/api/oauth/{$provider}/callback";
    }
}
