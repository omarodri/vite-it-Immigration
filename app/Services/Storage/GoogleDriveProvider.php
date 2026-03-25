<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Contracts\DocumentStorageInterface;
use App\Models\Document;
use App\Services\OAuthCredentialService;
use App\Services\OAuthTokenService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GoogleDriveProvider implements DocumentStorageInterface
{
    private const API_BASE = 'https://www.googleapis.com';

    private const SIMPLE_UPLOAD_MAX_SIZE = 5 * 1024 * 1024; // 5MB

    private const FOLDER_MIME_TYPE = 'application/vnd.google-apps.folder';

    private ?int $tenantId;

    public function __construct(
        private readonly OAuthTokenService $tokenService,
        private readonly OAuthCredentialService $credentialService,
        ?int $tenantId = null
    ) {
        $this->tenantId = $tenantId;
    }

    /**
     * Upload a file to Google Drive.
     *
     * @return array{storage_path: string, size: int, external_id: string, external_url: string}
     */
    public function upload(UploadedFile $file, string $destinationPath, array $metadata = []): array
    {
        $accessToken = $this->getAccessToken();

        $filename = basename($destinationPath);
        $parentExternalId = $metadata['parent_external_id'] ?? null;

        if ($file->getSize() <= self::SIMPLE_UPLOAD_MAX_SIZE) {
            $result = $this->multipartUpload($accessToken, $file, $filename, $parentExternalId);
        } else {
            $result = $this->resumableUpload($accessToken, $file, $filename, $parentExternalId);
        }

        return [
            'storage_path' => $destinationPath,
            'size' => (int) $file->getSize(),
            'external_id' => $result['id'],
            'external_url' => $result['webViewLink'] ?? '',
        ];
    }

    /**
     * Download a file from Google Drive.
     */
    public function download(Document $document): StreamedResponse|string
    {
        $accessToken = $this->getAccessToken();

        return response()->stream(
            function () use ($accessToken, $document) {
                $response = Http::withToken($accessToken)
                    ->timeout(30)
                    ->withOptions(['stream' => true])
                    ->get(self::API_BASE . "/drive/v3/files/{$document->external_id}", [
                        'alt' => 'media',
                    ]);

                if (!$response->successful()) {
                    Log::error('Google Drive download failed', [
                        'document_id' => $document->id,
                        'status' => $response->status(),
                    ]);
                    throw new \RuntimeException('Failed to download file from Google Drive.');
                }

                echo $response->body();
            },
            200,
            [
                'Content-Type' => $document->mime_type,
                'Content-Disposition' => 'attachment; filename="' . $document->original_name . '"',
            ]
        );
    }

    /**
     * Delete a file from Google Drive.
     */
    public function delete(Document $document): bool
    {
        if (!$document->external_id) {
            return true;
        }

        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->delete(self::API_BASE . "/drive/v3/files/{$document->external_id}");

        if (!$response->successful() && $response->status() !== 404) {
            Log::error('Google Drive delete failed', [
                'document_id' => $document->id,
                'external_id' => $document->external_id,
                'status' => $response->status(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Move a file to a new parent folder in Google Drive.
     */
    public function move(Document $document, string $newPath): bool
    {
        if (!$document->external_id) {
            return false;
        }

        $accessToken = $this->getAccessToken();

        // Get current parents
        $metaResponse = Http::withToken($accessToken)
            ->timeout(30)
            ->get(self::API_BASE . "/drive/v3/files/{$document->external_id}", [
                'fields' => 'parents',
            ]);

        if (!$metaResponse->successful()) {
            Log::error('Google Drive move: failed to get file metadata', [
                'document_id' => $document->id,
                'status' => $metaResponse->status(),
            ]);
            return false;
        }

        $previousParents = implode(',', $metaResponse->json('parents', []));

        // Rename the file (Google Drive doesn't have path-based folders like OneDrive)
        $newFilename = basename($newPath);
        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->patch(self::API_BASE . "/drive/v3/files/{$document->external_id}", [
                'name' => $newFilename,
            ]);

        if (!$response->successful()) {
            Log::error('Google Drive move failed', [
                'document_id' => $document->id,
                'external_id' => $document->external_id,
                'status' => $response->status(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Check if Google Drive is available for the current user's tenant.
     */
    public function isAvailable(): bool
    {
        try {
            $tenantId = $this->resolveTenantId();
            if (!$tenantId) {
                return false;
            }

            return $this->tokenService->hasTenantToken($tenantId, 'google');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create a folder in Google Drive.
     *
     * @return array{external_id: string, external_url: string}
     */
    public function createFolder(string $folderName, ?string $parentExternalId = null): array
    {
        $accessToken = $this->getAccessToken();

        $metadata = [
            'name' => $folderName,
            'mimeType' => self::FOLDER_MIME_TYPE,
        ];

        if ($parentExternalId) {
            $metadata['parents'] = [$parentExternalId];
        }

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->post(self::API_BASE . '/drive/v3/files?fields=id,webViewLink', $metadata);

        if (!$response->successful()) {
            Log::error('Google Drive create folder failed', [
                'folder_name' => $folderName,
                'parent_id' => $parentExternalId,
                'status' => $response->status(),
                'error' => $response->json('error', 'Unknown error'),
            ]);
            throw new \RuntimeException('Failed to create folder in Google Drive: ' . ($response->json('error.message') ?? 'Unknown error'));
        }

        $data = $response->json();

        return [
            'external_id' => $data['id'],
            'external_url' => $data['webViewLink'] ?? '',
        ];
    }

    /**
     * Delete a folder from Google Drive.
     */
    public function deleteFolder(string $externalId): bool
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->delete(self::API_BASE . "/drive/v3/files/{$externalId}");

        if (!$response->successful() && $response->status() !== 404) {
            Log::error('Google Drive delete folder failed', [
                'external_id' => $externalId,
                'status' => $response->status(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Rename a file or folder in Google Drive.
     */
    public function renameItem(string $externalId, string $newName): bool
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->patch(self::API_BASE . "/drive/v3/files/{$externalId}", [
                'name' => $newName,
            ]);

        if (!$response->successful()) {
            Log::error('Google Drive rename item failed', [
                'external_id' => $externalId,
                'new_name' => $newName,
                'status' => $response->status(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Move a file or folder to a different parent in Google Drive.
     */
    public function moveItem(string $externalId, string $targetParentExternalId): bool
    {
        $accessToken = $this->getAccessToken();

        // Get current parents so we can remove them
        $metaResponse = Http::withToken($accessToken)
            ->timeout(30)
            ->get(self::API_BASE . "/drive/v3/files/{$externalId}", [
                'fields' => 'parents',
            ]);

        if (!$metaResponse->successful()) {
            Log::error('Google Drive move item: failed to get current parents', [
                'external_id' => $externalId,
                'status' => $metaResponse->status(),
            ]);
            return false;
        }

        $previousParents = implode(',', $metaResponse->json('parents', []));

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->patch(self::API_BASE . "/drive/v3/files/{$externalId}?addParents={$targetParentExternalId}&removeParents={$previousParents}");

        if (!$response->successful()) {
            Log::error('Google Drive move item failed', [
                'external_id' => $externalId,
                'target_parent_id' => $targetParentExternalId,
                'status' => $response->status(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * List contents of a folder in Google Drive.
     *
     * @return array<int, array{name: string, type: string, external_id: string}>
     */
    public function listFolder(string $externalId): array
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->get(self::API_BASE . '/drive/v3/files', [
                'q' => "'{$externalId}' in parents and trashed = false",
                'fields' => 'files(id,name,mimeType,size,webViewLink)',
                'pageSize' => 1000,
            ]);

        if (!$response->successful()) {
            Log::error('Google Drive list folder failed', [
                'external_id' => $externalId,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException('Failed to list Google Drive folder contents.');
        }

        $items = [];
        foreach ($response->json('files', []) as $file) {
            $isFolder = $file['mimeType'] === self::FOLDER_MIME_TYPE;
            $entry = [
                'name' => $file['name'],
                'type' => $isFolder ? 'folder' : 'file',
                'external_id' => $file['id'],
            ];

            if (!$isFolder) {
                $entry['size'] = (int) ($file['size'] ?? 0);
                $entry['mime_type'] = $file['mimeType'];
                $entry['web_url'] = $file['webViewLink'] ?? '';
            }

            $items[] = $entry;
        }

        return $items;
    }

    /**
     * Multipart upload for files <= 5MB.
     */
    private function multipartUpload(string $accessToken, UploadedFile $file, string $filename, ?string $parentExternalId = null): array
    {
        $metadataArray = [
            'name' => $filename,
        ];

        if ($parentExternalId) {
            $metadataArray['parents'] = [$parentExternalId];
        }

        $metadata = json_encode($metadataArray);

        $boundary = 'boundary_' . uniqid();
        $body = "--{$boundary}\r\n"
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
            . $metadata . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: {$file->getClientMimeType()}\r\n\r\n"
            . $file->getContent() . "\r\n"
            . "--{$boundary}--";

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->withHeaders([
                'Content-Type' => "multipart/related; boundary={$boundary}",
            ])
            ->withBody($body, "multipart/related; boundary={$boundary}")
            ->post(self::API_BASE . '/upload/drive/v3/files?uploadType=multipart&fields=id,webViewLink');

        if (!$response->successful()) {
            Log::error('Google Drive multipart upload failed', [
                'status' => $response->status(),
                'error' => $response->json('error', 'Unknown error'),
            ]);
            throw new \RuntimeException('Failed to upload file to Google Drive: ' . $response->json('error.message', 'Unknown error'));
        }

        return $response->json();
    }

    /**
     * Resumable upload for files > 5MB.
     */
    private function resumableUpload(string $accessToken, UploadedFile $file, string $filename, ?string $parentExternalId = null): array
    {
        $metadata = [
            'name' => $filename,
        ];

        if ($parentExternalId) {
            $metadata['parents'] = [$parentExternalId];
        }

        // Initiate resumable upload
        $initResponse = Http::withToken($accessToken)
            ->timeout(30)
            ->withHeaders([
                'X-Upload-Content-Type' => $file->getClientMimeType(),
                'X-Upload-Content-Length' => (string) $file->getSize(),
            ])
            ->post(self::API_BASE . '/upload/drive/v3/files?uploadType=resumable&fields=id,webViewLink', $metadata);

        if (!$initResponse->successful()) {
            Log::error('Google Drive resumable upload init failed', [
                'status' => $initResponse->status(),
                'error' => $initResponse->json('error', 'Unknown error'),
            ]);
            throw new \RuntimeException('Failed to initiate Google Drive resumable upload.');
        }

        $uploadUrl = $initResponse->header('Location');
        if (!$uploadUrl) {
            throw new \RuntimeException('Google Drive did not return an upload URL.');
        }

        $fileSize = $file->getSize();
        $chunkSize = 5 * 1024 * 1024; // 5MB chunks
        $content = $file->getContent();
        $offset = 0;
        $result = null;

        while ($offset < $fileSize) {
            $length = min($chunkSize, $fileSize - $offset);
            $chunk = substr($content, $offset, $length);
            $rangeEnd = $offset + $length - 1;

            $response = Http::timeout(60)
                ->withHeaders([
                    'Content-Length' => (string) $length,
                    'Content-Range' => "bytes {$offset}-{$rangeEnd}/{$fileSize}",
                ])
                ->withBody($chunk, 'application/octet-stream')
                ->put($uploadUrl);

            // 308 Resume Incomplete means more chunks needed; 200/201 means done
            if ($response->status() !== 308 && !$response->successful()) {
                Log::error('Google Drive chunk upload failed', [
                    'offset' => $offset,
                    'status' => $response->status(),
                ]);
                throw new \RuntimeException('Failed to upload file chunk to Google Drive.');
            }

            if ($response->successful()) {
                $result = $response->json();
            }

            $offset += $length;
        }

        return $result ?? [];
    }

    /**
     * Get a valid access token for the resolved tenant.
     */
    private function getAccessToken(): string
    {
        $tenantId = $this->resolveTenantId();

        if (!$tenantId) {
            throw new \RuntimeException('User must be authenticated with a tenant to use Google Drive storage.');
        }

        $token = $this->tokenService->getValidTenantToken($tenantId, 'google');

        if (!$token) {
            throw new \RuntimeException('Google Drive is not connected for this organization. An administrator must connect the Google Drive account in Admin > OAuth Settings.');
        }

        return $token;
    }

    /**
     * Resolve the tenant ID from the explicit property or the authenticated user.
     */
    private function resolveTenantId(): ?int
    {
        if ($this->tenantId !== null) {
            return $this->tenantId;
        }

        $user = Auth::user();

        return $user?->tenant_id;
    }
}
