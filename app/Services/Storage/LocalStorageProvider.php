<?php

namespace App\Services\Storage;

use App\Contracts\DocumentStorageInterface;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LocalStorageProvider implements DocumentStorageInterface
{
    /**
     * Upload a file to local storage.
     *
     * @return array{storage_path: string, size: int}
     */
    public function upload(UploadedFile $file, string $destinationPath): array
    {
        $this->validatePath($destinationPath);

        $storagePath = Storage::disk('local')->putFileAs(
            dirname($destinationPath),
            $file,
            basename($destinationPath)
        );

        return [
            'storage_path' => $storagePath,
            'size' => $file->getSize(),
        ];
    }

    /**
     * Download a file from local storage.
     */
    public function download(Document $document): StreamedResponse|string
    {
        $this->validatePath($document->storage_path);

        return Storage::disk('local')->download(
            $document->storage_path,
            $document->original_name
        );
    }

    /**
     * Delete a file from local storage.
     */
    public function delete(Document $document): bool
    {
        if ($document->storage_path) {
            $this->validatePath($document->storage_path);

            if (Storage::disk('local')->exists($document->storage_path)) {
                return Storage::disk('local')->delete($document->storage_path);
            }
        }

        return true;
    }

    /**
     * Move a file to a new path in local storage.
     */
    public function move(Document $document, string $newPath): bool
    {
        if ($document->storage_path) {
            $this->validatePath($document->storage_path);
            $this->validatePath($newPath);

            if (Storage::disk('local')->exists($document->storage_path)) {
                return Storage::disk('local')->move($document->storage_path, $newPath);
            }
        }

        return false;
    }

    /**
     * Check if local storage is available.
     */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Validate a storage path to prevent directory traversal attacks.
     *
     * @throws \RuntimeException If the path contains traversal patterns or is outside allowed prefix.
     */
    private function validatePath(string $path): void
    {
        // Check for null bytes
        if (str_contains($path, "\0")) {
            throw new \RuntimeException('Invalid storage path: null bytes detected.');
        }

        // Check for directory traversal patterns
        if (preg_match('#(^|[\\/])\.\.($|[\\/])#', $path) || str_starts_with($path, './')) {
            throw new \RuntimeException('Invalid storage path: directory traversal detected.');
        }

        // Validate required tenant prefix
        if (! str_starts_with($path, 'tenants/')) {
            throw new \RuntimeException('Invalid storage path: must start with tenants/ prefix.');
        }
    }
}
