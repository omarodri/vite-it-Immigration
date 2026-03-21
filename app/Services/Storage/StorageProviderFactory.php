<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Contracts\DocumentStorageInterface;
use App\Exceptions\CloudProviderUnavailableException;
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
        if ($type === 'local' || ! in_array($type, ['onedrive', 'google_drive'], true)) {
            return app(LocalStorageProvider::class);
        }

        // Check circuit breaker before attempting cloud provider
        if ($this->circuitBreaker->isOpen($type)) {
            throw new CloudProviderUnavailableException($type);
        }

        $innerProvider = match ($type) {
            'onedrive' => app(OneDriveProvider::class),
            'google_drive' => app(GoogleDriveProvider::class),
        };

        return new ResilientStorageProvider(
            inner: $innerProvider,
            circuitBreaker: $this->circuitBreaker,
            providerName: $type,
        );
    }
}
