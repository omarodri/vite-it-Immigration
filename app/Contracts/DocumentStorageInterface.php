<?php

namespace App\Contracts;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface DocumentStorageInterface
{
    /**
     * Upload a file to storage.
     *
     * @return array{storage_path: string, size: int}
     */
    public function upload(UploadedFile $file, string $destinationPath): array;

    /**
     * Download a file from storage.
     */
    public function download(Document $document): StreamedResponse|string;

    /**
     * Delete a file from storage.
     */
    public function delete(Document $document): bool;

    /**
     * Move a file to a new path in storage.
     */
    public function move(Document $document, string $newPath): bool;

    /**
     * Check if the storage provider is available.
     */
    public function isAvailable(): bool;
}
