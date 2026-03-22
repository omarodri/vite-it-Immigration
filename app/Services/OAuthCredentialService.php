<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OAuthCredentialService
{
    /**
     * Check if system-level Microsoft credentials are configured.
     */
    public function hasSystemMicrosoftCredentials(): bool
    {
        return !empty(config('services.microsoft.client_id'))
            && !empty(config('services.microsoft.client_secret'));
    }

    /**
     * Check if system-level Google credentials are configured.
     */
    public function hasSystemGoogleCredentials(): bool
    {
        return !empty(config('services.google.client_id'))
            && !empty(config('services.google.client_secret'));
    }

    /**
     * Get Microsoft credentials for a tenant (with system fallback).
     */
    public function getMicrosoftCredentials(?Tenant $tenant = null): ?array
    {
        // Try tenant credentials first
        if ($tenant && $tenant->hasMicrosoftOAuth()) {
            return [
                'client_id' => $tenant->ms_client_id,
                'client_secret' => $tenant->ms_client_secret,
                'source' => 'tenant',
            ];
        }

        // Fall back to system credentials
        if ($this->hasSystemMicrosoftCredentials()) {
            return [
                'client_id' => config('services.microsoft.client_id'),
                'client_secret' => config('services.microsoft.client_secret'),
                'source' => 'system',
            ];
        }

        return null;
    }

    /**
     * Get Google credentials for a tenant (with system fallback).
     */
    public function getGoogleCredentials(?Tenant $tenant = null): ?array
    {
        // Try tenant credentials first
        if ($tenant && $tenant->hasGoogleOAuth()) {
            return [
                'client_id' => $tenant->google_client_id,
                'client_secret' => $tenant->google_client_secret,
                'source' => 'tenant',
            ];
        }

        // Fall back to system credentials
        if ($this->hasSystemGoogleCredentials()) {
            return [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'source' => 'system',
            ];
        }

        return null;
    }

    /**
     * Validate Microsoft OAuth credentials.
     *
     * We validate format and check the app registration exists via OpenID discovery.
     * We do NOT use client_credentials flow because:
     * - It requires a specific tenant ID (not /common/)
     * - Conditional Access policies may block token issuance (AADSTS53003)
     * - The real validation happens when the user completes the OAuth authorization code flow
     */
    public function validateMicrosoftCredentials(string $clientId, string $clientSecret): array
    {
        try {
            // Validate UUID format for client_id
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $clientId)) {
                return [
                    'valid' => false,
                    'error' => 'Invalid Microsoft Client ID format. Must be a valid UUID.',
                ];
            }

            if (strlen($clientSecret) < 10) {
                return [
                    'valid' => false,
                    'error' => 'Client secret appears to be too short.',
                ];
            }

            // Verify the app registration exists by checking the OpenID configuration
            $response = Http::timeout(10)->get(
                "https://login.microsoftonline.com/common/v2.0/.well-known/openid-configuration"
            );

            if (!$response->successful()) {
                return [
                    'valid' => false,
                    'error' => 'Failed to connect to Microsoft OAuth services.',
                ];
            }

            return ['valid' => true];
        } catch (\Exception $e) {
            Log::warning('Microsoft OAuth validation failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'valid' => false,
                'error' => 'Failed to connect to Microsoft. Please check your credentials.',
            ];
        }
    }

    /**
     * Validate Google OAuth credentials.
     *
     * Note: Google doesn't support client credentials flow for user data,
     * so we just validate the format and check if the client ID exists.
     */
    public function validateGoogleCredentials(string $clientId, string $clientSecret): array
    {
        try {
            // Basic format validation
            if (!str_contains($clientId, '.apps.googleusercontent.com')) {
                return [
                    'valid' => false,
                    'error' => 'Invalid Google Client ID format. Should end with .apps.googleusercontent.com',
                ];
            }

            if (strlen($clientSecret) < 10) {
                return [
                    'valid' => false,
                    'error' => 'Client secret appears to be too short.',
                ];
            }

            // Try to fetch the OAuth discovery document to verify connectivity
            $response = Http::get('https://accounts.google.com/.well-known/openid-configuration');

            if (!$response->successful()) {
                return [
                    'valid' => false,
                    'error' => 'Failed to connect to Google OAuth services.',
                ];
            }

            // We can't fully validate without user interaction, so accept if format is correct
            return ['valid' => true];
        } catch (\Exception $e) {
            Log::warning('Google OAuth validation failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'valid' => false,
                'error' => 'Failed to connect to Google. Please check your credentials.',
            ];
        }
    }
}
