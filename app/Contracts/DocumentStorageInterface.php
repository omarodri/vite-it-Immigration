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
     * @param  array  $metadata  Optional metadata (e.g., parent_external_id for cloud folder placement).
     * @return array{storage_path: string, size: int}
     */
    public function upload(UploadedFile $file, string $destinationPath, array $metadata = []): array;

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

    /**
     * Create a folder in cloud storage.
     *
     * @return array{external_id: string, external_url: string}
     */
    public function createFolder(string $folderName, ?string $parentExternalId = null): array;

    /**
     * Delete a folder from cloud storage.
     */
    public function deleteFolder(string $externalId): bool;

    /**
     * List contents of a folder in cloud storage.
     *
     * @return array<int, array{name: string, type: string, external_id: string}>
     */
    public function listFolder(string $externalId): array;
}
