<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Contracts\DocumentStorageInterface;
use App\Exceptions\CloudProviderUnavailableException;
use App\Models\Tenant;
use App\Services\OAuthCredentialService;
use App\Services\OAuthTokenService;
use Illuminate\Support\Facades\Auth;

class StorageProviderFactory
{
    public function __construct(
        private readonly CircuitBreaker $circuitBreaker,
    ) {}

    /**
     * Resolve the correct storage provider based on tenant's storage_type.
     *
     * Cloud providers are wrapped with ResilientStorageProvider for retry
     * logic and circuit breaker integration. If the circuit is open for a
     * cloud provider, a CloudProviderUnavailableException is thrown.
     */
    public function make(?string $storageType = null): DocumentStorageInterface
    {
        $type = $storageType ?? Auth::user()?->tenant?->storage_type ?? 'local';

        // Local storage does not need circuit breaker protection
        if ($type === 'local' || ! in_array($type, ['onedrive', 'google_drive', 'sharepoint'], true)) {
            return app(LocalStorageProvider::class);
        }

        // Check circuit breaker before attempting cloud provider
        if ($this->circuitBreaker->isOpen($type)) {
            throw new CloudProviderUnavailableException($type);
        }

        $innerProvider = match ($type) {
            'onedrive' => app(OneDriveProvider::class),
            'google_drive' => app(GoogleDriveProvider::class),
            'sharepoint' => app(SharePointProvider::class),
        };

        return new ResilientStorageProvider(
            inner: $innerProvider,
            circuitBreaker: $this->circuitBreaker,
            providerName: $type,
        );
    }

    /**
     * Resolve the correct storage provider for a specific tenant.
     *
     * This method creates providers with an explicit tenantId so they work
     * without an authenticated user (e.g., from queued jobs).
     */
    public function makeForTenant(Tenant $tenant): DocumentStorageInterface
    {
        $type = $tenant->storage_type ?? 'local';

        // Local storage does not need circuit breaker protection
        if ($type === 'local' || ! in_array($type, ['onedrive', 'google_drive', 'sharepoint'], true)) {
            return app(LocalStorageProvider::class);
        }

        // Check circuit breaker before attempting cloud provider
        if ($this->circuitBreaker->isOpen($type)) {
            throw new CloudProviderUnavailableException($type);
        }

        $tokenService = app(OAuthTokenService::class);
        $credentialService = app(OAuthCredentialService::class);

        $innerProvider = match ($type) {
            'onedrive' => new OneDriveProvider($tokenService, $credentialService, $tenant->id),
            'google_drive' => new GoogleDriveProvider($tokenService, $credentialService, $tenant->id),
            'sharepoint' => new SharePointProvider($tokenService, $credentialService, $tenant->id),
        };

        return new ResilientStorageProvider(
            inner: $innerProvider,
            circuitBreaker: $this->circuitBreaker,
            providerName: $type,
        );
    }
}
