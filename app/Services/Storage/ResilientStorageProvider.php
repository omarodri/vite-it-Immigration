<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Contracts\DocumentStorageInterface;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ResilientStorageProvider implements DocumentStorageInterface
{
    private const MAX_RETRIES = 1;

    private const RETRY_DELAY_SECONDS = 2;

    public function __construct(
        private readonly DocumentStorageInterface $inner,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly string $providerName,
    ) {}

    public function upload(UploadedFile $file, string $destinationPath, array $metadata = []): array
    {
        return $this->executeWithResilience(
            fn () => $this->inner->upload($file, $destinationPath, $metadata),
            'upload'
        );
    }

    public function download(Document $document): StreamedResponse|string
    {
        return $this->executeWithResilience(
            fn () => $this->inner->download($document),
            'download'
        );
    }

    public function delete(Document $document): bool
    {
        return $this->executeWithResilience(
            fn () => $this->inner->delete($document),
            'delete'
        );
    }

    public function move(Document $document, string $newPath): bool
    {
        return $this->executeWithResilience(
            fn () => $this->inner->move($document, $newPath),
            'move'
        );
    }

    public function isAvailable(): bool
    {
        try {
            return $this->inner->isAvailable();
        } catch (Throwable) {
            return false;
        }
    }

    public function createFolder(string $folderName, ?string $parentExternalId = null): array
    {
        return $this->executeWithResilience(
            fn () => $this->inner->createFolder($folderName, $parentExternalId),
            'createFolder'
        );
    }

    public function deleteFolder(string $externalId): bool
    {
        return $this->executeWithResilience(
            fn () => $this->inner->deleteFolder($externalId),
            'deleteFolder'
        );
    }

    public function renameItem(string $externalId, string $newName): bool
    {
        return $this->executeWithResilience(
            fn () => $this->inner->renameItem($externalId, $newName),
            'renameItem'
        );
    }

    public function moveItem(string $externalId, string $targetParentExternalId): bool
    {
        return $this->executeWithResilience(
            fn () => $this->inner->moveItem($externalId, $targetParentExternalId),
            'moveItem'
        );
    }

    public function listFolder(string $externalId): array
    {
        return $this->executeWithResilience(
            fn () => $this->inner->listFolder($externalId),
            'listFolder'
        );
    }

    /**
     * Execute a storage operation with retry logic and circuit breaker recording.
     *
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    private function executeWithResilience(callable $operation, string $operationName): mixed
    {
        $lastException = null;

        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                if ($attempt > 0) {
                    Log::info("ResilientStorageProvider: retrying [{$operationName}] on [{$this->providerName}], attempt {$attempt}.");
                    sleep(self::RETRY_DELAY_SECONDS);
                }

                $result = $operation();

                $this->circuitBreaker->recordSuccess($this->providerName);

                return $result;
            } catch (Throwable $e) {
                $lastException = $e;

                Log::warning("ResilientStorageProvider: [{$operationName}] failed on [{$this->providerName}], attempt {$attempt}.", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->circuitBreaker->recordFailure($this->providerName);

        throw $lastException;
    }
}
