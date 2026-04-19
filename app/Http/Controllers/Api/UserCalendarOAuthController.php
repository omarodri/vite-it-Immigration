<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarSyncStatus;
use App\Models\OauthToken;
use App\Services\OAuthCredentialService;
use App\Services\OAuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserCalendarOAuthController extends Controller
{
    public function __construct(
        private readonly OAuthTokenService $tokenService,
        private readonly OAuthCredentialService $credentialService
    ) {}

    /**
     * Generate and return the OAuth authorization URL for calendar access.
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

        $state = Str::random(40);
        Cache::put("calendar_oauth_state:{$state}", $user->id, now()->addMinutes(10));

        $authUrl = match ($provider) {
            'microsoft' => $this->buildMicrosoftAuthUrl($credentials, $state),
            'google' => $this->buildGoogleAuthUrl($credentials, $state),
        };

        return response()->json([
            'url' => $authUrl,
        ]);
    }

    /**
     * Handle the OAuth callback from the provider for calendar access.
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $frontendUrl = rtrim(config('app.url'), '/');
        $profilePage = "{$frontendUrl}/users/profile";

        if ($request->has('error')) {
            Log::error("Calendar OAuth {$provider} callback error", [
                'error' => $request->input('error'),
                'description' => $request->input('error_description'),
            ]);

            return redirect("{$profilePage}?calendar_error=" . urlencode($request->input('error_description', 'Authorization failed')));
        }

        $code = $request->input('code');
        $state = $request->input('state');

        if (!$code) {
            return redirect("{$profilePage}?calendar_error=No+authorization+code+received");
        }

        $cacheKey = "calendar_oauth_state:{$state}";
        $userId = $state ? Cache::pull($cacheKey) : null;

        if (!$userId) {
            Log::error("Calendar OAuth {$provider} callback state mismatch", [
                'received_state' => $state,
            ]);
            return redirect("{$profilePage}?calendar_error=Invalid+state+parameter");
        }

        try {
            $user = \App\Models\User::findOrFail($userId);
            $tenant = $user->tenant;

            $credentials = match ($provider) {
                'microsoft' => $this->credentialService->getMicrosoftCredentials($tenant),
                'google' => $this->credentialService->getGoogleCredentials($tenant),
                default => null,
            };

            if (!$credentials) {
                return redirect("{$profilePage}?calendar_error=No+credentials+configured");
            }

            $tokenData = match ($provider) {
                'microsoft' => $this->exchangeMicrosoftCode($code, $credentials),
                'google' => $this->exchangeGoogleCode($code, $credentials),
            };

            if (!$tokenData) {
                return redirect("{$profilePage}?calendar_error=Token+exchange+failed");
            }

            $this->tokenService->storeUserCalendarToken($user, $provider, $tokenData);

            // Create or update CalendarSyncStatus
            CalendarSyncStatus::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'provider' => $provider,
                ],
                [
                    'tenant_id' => $user->tenant_id,
                    'status' => 'active',
                    'error_count' => 0,
                    'last_error' => null,
                ]
            );

            return redirect("{$profilePage}?calendar_connected={$provider}");
        } catch (\Exception $e) {
            Log::error("Calendar OAuth {$provider} callback exception", [
                'error' => $e->getMessage(),
            ]);

            return redirect("{$profilePage}?calendar_error=" . urlencode('An unexpected error occurred'));
        }
    }

    /**
     * Return the calendar OAuth connection status for the current user.
     */
    public function status(): JsonResponse
    {
        $user = Auth::user();
        $tenant = $user->tenant;

        $microsoftToken = OauthToken::where('user_id', $user->id)
            ->where('provider', 'microsoft')
            ->where('purpose', 'calendar')
            ->first();

        $googleToken = OauthToken::where('user_id', $user->id)
            ->where('provider', 'google')
            ->where('purpose', 'calendar')
            ->first();

        $microsoftSync = CalendarSyncStatus::where('user_id', $user->id)
            ->where('provider', 'microsoft')
            ->first();

        $googleSync = CalendarSyncStatus::where('user_id', $user->id)
            ->where('provider', 'google')
            ->first();

        return response()->json([
            'microsoft' => [
                'credentials_configured' => $this->credentialService->getMicrosoftCredentials($tenant) !== null,
                'connected' => $microsoftToken !== null,
                'expires_at' => $microsoftToken?->expires_at?->toIso8601String(),
                'is_expired' => $microsoftToken?->isExpired() ?? false,
                'sync_status' => $microsoftSync?->status,
                'last_pull_at' => $microsoftSync?->last_pull_at?->toIso8601String(),
                'last_push_at' => $microsoftSync?->last_push_at?->toIso8601String(),
                'last_error' => $microsoftSync?->last_error,
            ],
            'google' => [
                'credentials_configured' => $this->credentialService->getGoogleCredentials($tenant) !== null,
                'connected' => $googleToken !== null,
                'expires_at' => $googleToken?->expires_at?->toIso8601String(),
                'is_expired' => $googleToken?->isExpired() ?? false,
                'sync_status' => $googleSync?->status,
                'last_pull_at' => $googleSync?->last_pull_at?->toIso8601String(),
                'last_push_at' => $googleSync?->last_push_at?->toIso8601String(),
                'last_error' => $googleSync?->last_error,
            ],
        ]);
    }

    /**
     * Disconnect calendar OAuth for a provider.
     */
    public function disconnect(string $provider): JsonResponse
    {
        $user = Auth::user();

        $tokenRevoked = $this->tokenService->revokeUserCalendarToken($user, $provider);

        CalendarSyncStatus::where('user_id', $user->id)
            ->where('provider', $provider)
            ->delete();

        if (!$tokenRevoked) {
            return response()->json([
                'message' => "No {$provider} calendar connection found to disconnect.",
            ], 404);
        }

        return response()->json([
            'message' => ucfirst($provider) . ' calendar disconnected successfully.',
        ]);
    }

    /**
     * Build Microsoft OAuth authorization URL for calendar access.
     */
    private function buildMicrosoftAuthUrl(array $credentials, string $state): string
    {
        $tenant = config('services.microsoft.tenant', 'common');
        $redirectUri = $this->getRedirectUri('microsoft');

        $params = http_build_query([
            'client_id' => $credentials['client_id'],
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'scope' => 'Calendars.ReadWrite offline_access User.Read',
            'state' => $state,
            'response_mode' => 'query',
        ]);

        return "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize?{$params}";
    }

    /**
     * Build Google OAuth authorization URL for calendar access.
     */
    private function buildGoogleAuthUrl(array $credentials, string $state): string
    {
        $redirectUri = $this->getRedirectUri('google');

        $params = http_build_query([
            'client_id' => $credentials['client_id'],
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'scope' => 'https://www.googleapis.com/auth/calendar.events offline_access openid email',
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
                'scope' => 'Calendars.ReadWrite offline_access User.Read',
            ]
        );

        if (!$response->successful()) {
            Log::error('Microsoft calendar token exchange failed', [
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
            Log::error('Google calendar token exchange failed', [
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
     * Get the full redirect URI for a calendar OAuth provider.
     */
    private function getRedirectUri(string $provider): string
    {
        return rtrim(config('app.url'), '/') . "/api/calendar-oauth/{$provider}/callback";
    }
}
