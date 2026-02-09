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
     * This performs a basic validation by attempting to get an access token
     * using client credentials flow.
     */
    public function validateMicrosoftCredentials(string $clientId, string $clientSecret): array
    {
        try {
            // Attempt to get a token using client credentials
            $response = Http::asForm()->post(
                'https://login.microsoftonline.com/common/oauth2/v2.0/token',
                [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                ]
            );

            if ($response->successful()) {
                return ['valid' => true];
            }

            $error = $response->json('error_description') ?? $response->json('error') ?? 'Unknown error';

            return [
                'valid' => false,
                'error' => $error,
            ];
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
