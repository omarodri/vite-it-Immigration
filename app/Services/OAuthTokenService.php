<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OauthToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OAuthTokenService
{
    public function __construct(
        private readonly OAuthCredentialService $credentialService
    ) {}

    /**
     * Get a valid access token for the user and provider.
     * Automatically refreshes if expired or expiring soon.
     */
    public function getValidToken(User $user, string $provider): ?string
    {
        $token = OauthToken::where('user_id', $user->id)
            ->where('provider', $provider)
            ->first();

        if (!$token) {
            return null;
        }

        if ($token->isExpiringSoon()) {
            $token = $this->refreshToken($token);
            if (!$token) {
                return null;
            }
        }

        return $token->access_token;
    }

    /**
     * Get a valid access token for the tenant and provider.
     * This is used for shared cloud storage (OneDrive/Google Drive).
     */
    public function getValidTenantToken(int $tenantId, string $provider): ?string
    {
        $token = OauthToken::where('tenant_id', $tenantId)
            ->where('provider', $provider)
            ->first();

        if (!$token) {
            return null;
        }

        if ($token->isExpiringSoon()) {
            $token = $this->refreshToken($token);
            if (!$token) {
                return null;
            }
        }

        return $token->access_token;
    }

    /**
     * Store or update OAuth tokens at tenant level.
     * The user_id records WHO connected it (for audit), but the token is shared.
     */
    public function storeTenantToken(int $tenantId, int $connectedByUserId, string $provider, array $tokenData): OauthToken
    {
        return OauthToken::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'provider' => $provider,
            ],
            [
                'user_id' => $connectedByUserId,
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'expires_at' => now()->addSeconds((int) ($tokenData['expires_in'] ?? 3600)),
                'scopes' => $tokenData['scopes'] ?? null,
            ]
        );
    }

    /**
     * Revoke tenant-level OAuth token for a provider.
     */
    public function revokeTenantToken(int $tenantId, string $provider): bool
    {
        $deleted = OauthToken::where('tenant_id', $tenantId)
            ->where('provider', $provider)
            ->delete();

        return $deleted > 0;
    }

    /**
     * Check if a tenant has a valid token for a provider.
     */
    public function hasTenantToken(int $tenantId, string $provider): bool
    {
        $token = OauthToken::where('tenant_id', $tenantId)
            ->where('provider', $provider)
            ->first();

        return $token !== null && !$token->isExpired();
    }

    /**
     * Refresh an OAuth token using the provider's token endpoint.
     * Uses a cache lock to prevent race conditions.
     */
    public function refreshToken(OauthToken $token): ?OauthToken
    {
        $lockKey = "oauth_refresh_{$token->tenant_id}_{$token->provider}";

        $lock = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            // Another process is refreshing; wait and re-fetch
            sleep(1);
            return $token->fresh();
        }

        try {
            if (!$token->refresh_token) {
                Log::error('OAuth token refresh failed: no refresh token', [
                    'user_id' => $token->user_id,
                    'provider' => $token->provider,
                ]);
                return null;
            }

            $tenant = Tenant::find($token->tenant_id);
            $credentials = $this->getCredentialsForProvider($token->provider, $tenant);

            if (!$credentials) {
                Log::error('OAuth token refresh failed: no credentials found', [
                    'user_id' => $token->user_id,
                    'provider' => $token->provider,
                ]);
                return null;
            }

            $response = match ($token->provider) {
                'microsoft' => $this->refreshMicrosoftToken($token, $credentials),
                'google' => $this->refreshGoogleToken($token, $credentials),
                default => null,
            };

            if (!$response) {
                return null;
            }

            $token->update([
                'access_token' => $response['access_token'],
                'refresh_token' => $response['refresh_token'] ?? $token->refresh_token,
                'expires_at' => now()->addSeconds((int) $response['expires_in']),
            ]);

            return $token->fresh();
        } catch (\Exception $e) {
            Log::error('OAuth token refresh exception', [
                'user_id' => $token->user_id,
                'provider' => $token->provider,
                'error' => $e->getMessage(),
            ]);
            return null;
        } finally {
            $lock->release();
        }
    }

    /**
     * Store or update OAuth tokens after a successful authorization flow.
     */
    public function storeToken(User $user, string $provider, array $tokenData): OauthToken
    {
        return OauthToken::updateOrCreate(
            [
                'user_id' => $user->id,
                'provider' => $provider,
            ],
            [
                'tenant_id' => $user->tenant_id,
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'expires_at' => now()->addSeconds((int) ($tokenData['expires_in'] ?? 3600)),
                'scopes' => $tokenData['scopes'] ?? null,
            ]
        );
    }

    /**
     * Revoke and delete a user's OAuth token for a provider.
     */
    public function revokeToken(User $user, string $provider): bool
    {
        $deleted = OauthToken::where('user_id', $user->id)
            ->where('provider', $provider)
            ->delete();

        return $deleted > 0;
    }

    /**
     * Check if a user has a valid token for a provider.
     */
    public function hasValidToken(User $user, string $provider): bool
    {
        $token = OauthToken::where('user_id', $user->id)
            ->where('provider', $provider)
            ->first();

        return $token !== null && !$token->isExpired();
    }

    /**
     * Store or update OAuth calendar tokens at user level (purpose='calendar').
     */
    public function storeUserCalendarToken(User $user, string $provider, array $tokenData): OauthToken
    {
        return OauthToken::updateOrCreate(
            [
                'user_id'  => $user->id,
                'provider' => $provider,
                'purpose'  => 'calendar',
            ],
            [
                'tenant_id'     => $user->tenant_id,
                'access_token'  => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'expires_at'    => now()->addSeconds((int) ($tokenData['expires_in'] ?? 3600)),
                'scopes'        => $tokenData['scopes'] ?? null,
            ]
        );
    }

    /**
     * Get a valid calendar access token for a user and provider.
     */
    public function getValidUserCalendarToken(User $user, string $provider): ?string
    {
        $token = OauthToken::where('user_id', $user->id)
            ->where('provider', $provider)
            ->where('purpose', 'calendar')
            ->first();

        if (!$token) {
            return null;
        }

        if ($token->isExpiringSoon()) {
            $token = $this->refreshToken($token);
            if (!$token) {
                return null;
            }
        }

        return $token->access_token;
    }

    /**
     * Revoke a user's calendar OAuth token for a provider.
     */
    public function revokeUserCalendarToken(User $user, string $provider): bool
    {
        return OauthToken::where('user_id', $user->id)
            ->where('provider', $provider)
            ->where('purpose', 'calendar')
            ->delete() > 0;
    }

    /**
     * Check if a user has a valid calendar token for a provider.
     */
    public function hasUserCalendarToken(User $user, string $provider): bool
    {
        $token = OauthToken::where('user_id', $user->id)
            ->where('provider', $provider)
            ->where('purpose', 'calendar')
            ->first();

        return $token !== null && !$token->isExpired();
    }

    /**
     * Get a short-lived application-level Microsoft access token via client credentials flow.
     * Requires Calendars.ReadWrite granted as an Application permission in Azure.
     * The result is cached until 60 seconds before expiry.
     */
    public function getMicrosoftApplicationToken(int $tenantId): ?string
    {
        $cacheKey = "ms_app_calendar_token:{$tenantId}";

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $tenant = Tenant::find($tenantId);
        $credentials = $this->credentialService->getMicrosoftCredentials($tenant);
        if (!$credentials) {
            return null;
        }

        $directoryId = $credentials['directory_id']
            ?? $this->extractMicrosoftDirectoryId($tenantId);

        if (!$directoryId) {
            Log::warning('getMicrosoftApplicationToken: Azure tenant directory ID not found', [
                'tenant_id' => $tenantId,
            ]);
            return null;
        }

        $response = Http::timeout(30)->asForm()->post(
            "https://login.microsoftonline.com/{$directoryId}/oauth2/v2.0/token",
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'scope'         => 'https://graph.microsoft.com/.default',
            ]
        );

        if (!$response->successful()) {
            Log::error('Microsoft application calendar token request failed', [
                'tenant_id' => $tenantId,
                'status'    => $response->status(),
                'error'     => $response->json('error_description', $response->json('error', 'Unknown')),
            ]);
            return null;
        }

        $token     = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 3600);

        Cache::put($cacheKey, $token, now()->addSeconds($expiresIn - 60));

        return $token;
    }

    /**
     * Decode the stored tenant Microsoft access-token JWT to extract the Azure
     * directory (tenant) ID from the `tid` claim. Works even on expired tokens.
     */
    private function extractMicrosoftDirectoryId(int $tenantId): ?string
    {
        $token = OauthToken::where('tenant_id', $tenantId)
            ->where('provider', 'microsoft')
            ->where('purpose', 'storage')
            ->first();

        // Legacy records without the purpose column
        if (!$token) {
            $token = OauthToken::where('tenant_id', $tenantId)
                ->where('provider', 'microsoft')
                ->whereNull('purpose')
                ->first();
        }

        if (!$token) {
            return null;
        }

        $parts = explode('.', $token->access_token);
        if (count($parts) !== 3) {
            return null;
        }

        $padded  = str_pad($parts[1], strlen($parts[1]) + (4 - strlen($parts[1]) % 4) % 4, '=');
        $payload = json_decode(base64_decode($padded), true);

        return $payload['tid'] ?? null;
    }

    /**
     * Get credentials for a provider using OAuthCredentialService.
     */
    private function getCredentialsForProvider(string $provider, $tenant): ?array
    {
        return match ($provider) {
            'microsoft' => $this->credentialService->getMicrosoftCredentials($tenant),
            'google' => $this->credentialService->getGoogleCredentials($tenant),
            default => null,
        };
    }

    /**
     * Refresh a Microsoft OAuth token.
     */
    private function refreshMicrosoftToken(OauthToken $token, array $credentials): ?array
    {
        $scope = match ($token->purpose ?? 'storage') {
            'calendar' => 'Calendars.ReadWrite offline_access User.Read',
            default    => 'Files.ReadWrite.All Sites.ReadWrite.All offline_access',
        };

        $response = Http::timeout(30)->asForm()->post(
            'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            [
                'grant_type'    => 'refresh_token',
                'client_id'     => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'refresh_token' => $token->refresh_token,
                'scope'         => $scope,
            ]
        );

        if (!$response->successful()) {
            Log::error('Microsoft token refresh failed', [
                'user_id' => $token->user_id,
                'status' => $response->status(),
                'error' => $response->json('error_description', $response->json('error', 'Unknown error')),
            ]);
            return null;
        }

        return $response->json();
    }

    /**
     * Refresh a Google OAuth token.
     */
    private function refreshGoogleToken(OauthToken $token, array $credentials): ?array
    {
        $response = Http::timeout(30)->asForm()->post(
            'https://oauth2.googleapis.com/token',
            [
                'grant_type' => 'refresh_token',
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'refresh_token' => $token->refresh_token,
            ]
        );

        if (!$response->successful()) {
            Log::error('Google token refresh failed', [
                'user_id' => $token->user_id,
                'status' => $response->status(),
                'error' => $response->json('error_description', $response->json('error', 'Unknown error')),
            ]);
            return null;
        }

        return $response->json();
    }
}
