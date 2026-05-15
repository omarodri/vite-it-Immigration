<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalendarSyncStatus;
use App\Models\OauthToken;
use App\Services\Calendar\CalendarSyncService;
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
        private readonly OAuthCredentialService $credentialService,
        private readonly CalendarSyncService $syncService,
    ) {}

    /**
     * Generate and return the OAuth authorization URL for calendar access.
     *
     * Microsoft: tries Application permissions first (Azure AD org accounts).
     * If an app token is obtainable, returns auto_connected=true (no redirect needed).
     * If not (personal @outlook.com / @hotmail.com accounts), returns the delegated
     * OAuth URL so the user can authorize manually — same flow as Google.
     */
    public function redirect(string $provider): JsonResponse
    {
        $user   = Auth::user();
        $tenant = $user->tenant;

        $credentials = match ($provider) {
            'microsoft' => $this->credentialService->getMicrosoftCredentials($tenant),
            'google'    => $this->credentialService->getGoogleCredentials($tenant),
            default     => null,
        };

        if (!$credentials) {
            return response()->json([
                'message' => "No {$provider} OAuth credentials configured for this tenant.",
            ], 422);
        }

        // Microsoft: try Application permissions first (Azure AD org accounts).
        // If an application token can be obtained, auto-connect without per-user OAuth.
        // For personal accounts (@outlook.com / @hotmail.com), fall through to delegated OAuth.
        if ($provider === 'microsoft') {
            $appToken = $this->tokenService->getMicrosoftApplicationToken($user->tenant_id);
            if ($appToken) {
                CalendarSyncStatus::updateOrCreate(
                    ['user_id' => $user->id, 'provider' => 'microsoft'],
                    ['tenant_id' => $user->tenant_id, 'status' => 'active', 'error_count' => 0, 'last_error' => null]
                );
                return response()->json(['auto_connected' => true]);
            }
            // No application token → fall through to delegated OAuth below.
        }

        $state = Str::random(40);
        Cache::put("calendar_oauth_state:{$state}", $user->id, now()->addMinutes(10));

        $url = match ($provider) {
            'microsoft' => $this->buildMicrosoftAuthUrl($credentials, $state),
            default     => $this->buildGoogleAuthUrl($credentials, $state),
        };

        return response()->json(['url' => $url]);
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

            // Backfill initial sync (non-fatal — connection persists even if pull fails)
            try {
                $this->syncService->pullEvents($user, $provider);
            } catch (\Throwable $e) {
                \Log::warning('Initial calendar backfill failed (non-fatal)', [
                    'user_id'  => $user->id,
                    'provider' => $provider,
                    'error'    => $e->getMessage(),
                ]);
            }

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
     *
     * Microsoft connection state is derived from CalendarSyncStatus (no user token needed).
     * Google connection state is derived from the per-user OAuth token.
     */
    public function status(): JsonResponse
    {
        $user   = Auth::user();
        $tenant = $user->tenant;

        $microsoftSync = CalendarSyncStatus::where('user_id', $user->id)
            ->where('provider', 'microsoft')
            ->first();

        $googleToken = OauthToken::where('user_id', $user->id)
            ->where('provider', 'google')
            ->where('purpose', 'calendar')
            ->first();

        $googleSync = CalendarSyncStatus::where('user_id', $user->id)
            ->where('provider', 'google')
            ->first();

        $msCredentials = $this->credentialService->getMicrosoftCredentials($tenant);

        return response()->json([
            'microsoft' => [
                'credentials_configured' => $msCredentials !== null,
                'connected'              => $microsoftSync?->status === 'active',
                'sync_status'            => $microsoftSync?->status,
                'last_pull_at'           => $microsoftSync?->last_pull_at?->toIso8601String(),
                'last_push_at'           => $microsoftSync?->last_push_at?->toIso8601String(),
                'last_error'             => $microsoftSync?->last_error,
            ],
            'google' => [
                'credentials_configured' => $this->credentialService->getGoogleCredentials($tenant) !== null,
                'connected'              => $googleToken !== null,
                'expires_at'             => $googleToken?->expires_at?->toIso8601String(),
                'is_expired'             => $googleToken?->isExpired() ?? false,
                'sync_status'            => $googleSync?->status,
                'last_pull_at'           => $googleSync?->last_pull_at?->toIso8601String(),
                'last_push_at'           => $googleSync?->last_push_at?->toIso8601String(),
                'last_error'             => $googleSync?->last_error,
            ],
        ]);
    }

    /**
     * Disconnect calendar for a provider.
     *
     * Microsoft: sets CalendarSyncStatus to 'paused' (no user token to revoke).
     *            The auto-provisioner in PullCalendarEventsJob won't recreate it
     *            because the record still exists.
     * Google:    revokes the per-user OAuth token and deletes the sync record.
     */
    public function disconnect(string $provider): JsonResponse
    {
        $user = Auth::user();

        if ($provider === 'microsoft') {
            $updated = CalendarSyncStatus::where('user_id', $user->id)
                ->where('provider', 'microsoft')
                ->update(['status' => 'paused']);

            if (!$updated) {
                return response()->json([
                    'message' => 'No Microsoft calendar connection found to disconnect.',
                ], 404);
            }
        } else {
            $tokenRevoked = $this->tokenService->revokeUserCalendarToken($user, $provider);

            CalendarSyncStatus::where('user_id', $user->id)
                ->where('provider', $provider)
                ->delete();

            if (!$tokenRevoked) {
                return response()->json([
                    'message' => "No {$provider} calendar connection found to disconnect.",
                ], 404);
            }
        }

        return response()->json([
            'message' => ucfirst($provider) . ' calendar disconnected successfully.',
        ]);
    }

    public function retry(Request $request, string $provider): JsonResponse
    {
        $user = Auth::user();

        $syncStatus = CalendarSyncStatus::where('user_id', $user->id)
            ->where('provider', $provider)
            ->first();

        if (!$syncStatus) {
            return response()->json(['error' => 'no_connection'], 404);
        }

        $syncStatus->update([
            'status'      => 'active',
            'error_count' => 0,
            'last_error'  => null,
        ]);

        try {
            $this->syncService->pullEvents($user, $provider);
            return response()->json([
                'success'   => true,
                'pulled_at' => now()->toIso8601String(),
                'provider'  => $provider,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Calendar retry pull failed', [
                'user_id'  => $user->id,
                'provider' => $provider,
                'error'    => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'error'   => 'sync_failed',
                'message' => $e->getMessage(),
            ], 502);
        }
    }

    /**
     * Trigger an on-demand calendar pull for the authenticated user.
     *
     * Runs synchronously (no queue). Applies a 15-minute cooldown per provider
     * so successive calls return immediately without hitting the external API.
     * Designed for use on shared hosting where no cron or queue worker is available.
     */
    public function pull(): JsonResponse
    {
        $user            = Auth::user();
        $cooldownMinutes = 15;

        // Auto-provision Microsoft sync if tenant has MS credentials but user has
        // no record yet (happens when no cron has run provisionMicrosoftUsers()).
        $msCredentials = $this->credentialService->getMicrosoftCredentials($user->tenant);
        if ($msCredentials) {
            CalendarSyncStatus::firstOrCreate(
                ['user_id' => $user->id, 'provider' => 'microsoft'],
                ['tenant_id' => $user->tenant_id, 'status' => 'active', 'error_count' => 0]
            );
        }

        $statuses = CalendarSyncStatus::where('user_id', $user->id)
            ->whereIn('status', ['active', 'paused'])
            ->get();

        if ($statuses->isEmpty()) {
            return response()->json(['pulled' => false, 'reason' => 'no_connection']);
        }

        $needsPull = $statuses->contains(
            fn ($s) => $s->last_pull_at === null || $s->last_pull_at->lt(now()->subMinutes($cooldownMinutes))
        );

        if (!$needsPull) {
            return response()->json(['pulled' => false, 'reason' => 'cooldown']);
        }

        $pulled = [];
        $errors = [];
        foreach ($statuses as $syncStatus) {
            if ($syncStatus->last_pull_at !== null && $syncStatus->last_pull_at->gte(now()->subMinutes($cooldownMinutes))) {
                continue;
            }

            try {
                $this->syncService->pullEvents($user);
                $pulled[] = $syncStatus->provider;
            } catch (\Exception $e) {
                $errors[] = $syncStatus->provider . ': ' . $e->getMessage();
                Log::warning('On-demand calendar pull failed', [
                    'user_id'  => $user->id,
                    'provider' => $syncStatus->provider,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'pulled'    => true,
            'providers' => $pulled,
            'errors'    => $errors,
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
