<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OauthToken;
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
     * Refresh an OAuth token using the provider's token endpoint.
     * Uses a cache lock to prevent race conditions.
     */
    public function refreshToken(OauthToken $token): ?OauthToken
    {
        $lockKey = "oauth_refresh_{$token->user_id}_{$token->provider}";

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

            $tenant = $token->user->tenant;
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
        $response = Http::timeout(30)->asForm()->post(
            'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            [
                'grant_type' => 'refresh_token',
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'refresh_token' => $token->refresh_token,
                'scope' => 'Files.ReadWrite.All offline_access',
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
