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

class OneDriveProvider implements DocumentStorageInterface
{
    private const BASE_URL = 'https://graph.microsoft.com/v1.0';

    private const SIMPLE_UPLOAD_MAX_SIZE = 4 * 1024 * 1024; // 4MB

    private ?int $tenantId;

    public function __construct(
        private readonly OAuthTokenService $tokenService,
        private readonly OAuthCredentialService $credentialService,
        ?int $tenantId = null
    ) {
        $this->tenantId = $tenantId;
    }

    /**
     * Upload a file to OneDrive.
     *
     * @return array{storage_path: string, size: int, external_id: string, external_url: string}
     */
    public function upload(UploadedFile $file, string $destinationPath, array $metadata = []): array
    {
        $accessToken = $this->getAccessToken();

        $filename = basename($destinationPath);
        $folderPath = dirname($destinationPath);

        // If a parent_external_id is provided, upload into that folder by ID
        if (!empty($metadata['parent_external_id'])) {
            $parentId = $metadata['parent_external_id'];

            if ($file->getSize() <= self::SIMPLE_UPLOAD_MAX_SIZE) {
                $encodedName = rawurlencode($filename);
                $response = Http::withToken($accessToken)
                    ->timeout(30)
                    ->withBody($file->getContent(), $file->getClientMimeType())
                    ->put(self::BASE_URL . "/me/drive/items/{$parentId}:/{$encodedName}:/content");

                if (!$response->successful()) {
                    Log::error('OneDrive upload to folder failed', [
                        'status' => $response->status(),
                        'error' => $response->json('error', 'Unknown error'),
                    ]);
                    throw new \RuntimeException('Failed to upload file to OneDrive folder: ' . $response->json('error.message', 'Unknown error'));
                }

                $result = $response->json();
            } else {
                $result = $this->resumableUpload($accessToken, $file, $folderPath, $filename);
            }
        } else {
            if ($file->getSize() <= self::SIMPLE_UPLOAD_MAX_SIZE) {
                $result = $this->simpleUpload($accessToken, $file, $folderPath, $filename);
            } else {
                $result = $this->resumableUpload($accessToken, $file, $folderPath, $filename);
            }
        }

        return [
            'storage_path' => $destinationPath,
            'size' => (int) $file->getSize(),
            'external_id' => $result['id'],
            'external_url' => $result['webUrl'] ?? '',
        ];
    }

    /**
     * Download a file from OneDrive.
     */
    public function download(Document $document): StreamedResponse|string
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->get(self::BASE_URL . "/me/drive/items/{$document->external_id}");

        if (!$response->successful()) {
            Log::error('OneDrive download metadata failed', [
                'document_id' => $document->id,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException('Failed to download file from OneDrive.');
        }

        $downloadUrl = $response->json('@microsoft.graph.downloadUrl');

        if ($downloadUrl) {
            // Return redirect URL for browser download
            return $downloadUrl;
        }

        // Fallback: stream content directly
        return response()->stream(
            function () use ($accessToken, $document) {
                $stream = Http::withToken($accessToken)
                    ->timeout(30)
                    ->withOptions(['stream' => true])
                    ->get(self::BASE_URL . "/me/drive/items/{$document->external_id}/content");

                echo $stream->body();
            },
            200,
            [
                'Content-Type' => $document->mime_type,
                'Content-Disposition' => 'attachment; filename="' . $document->original_name . '"',
            ]
        );
    }

    /**
     * Delete a file from OneDrive.
     */
    public function delete(Document $document): bool
    {
        if (!$document->external_id) {
            return true;
        }

        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->delete(self::BASE_URL . "/me/drive/items/{$document->external_id}");

        if (!$response->successful() && $response->status() !== 404) {
            Log::error('OneDrive delete failed', [
                'document_id' => $document->id,
                'external_id' => $document->external_id,
                'status' => $response->status(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Move a file to a new path in OneDrive.
     */
    public function move(Document $document, string $newPath): bool
    {
        if (!$document->external_id) {
            return false;
        }

        $accessToken = $this->getAccessToken();

        $newFilename = basename($newPath);
        $newFolder = dirname($newPath);

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->patch(self::BASE_URL . "/me/drive/items/{$document->external_id}", [
                'name' => $newFilename,
                'parentReference' => [
                    'path' => "/drive/root:/{$newFolder}",
                ],
            ]);

        if (!$response->successful()) {
            Log::error('OneDrive move failed', [
                'document_id' => $document->id,
                'external_id' => $document->external_id,
                'status' => $response->status(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Check if OneDrive is available for the current user's tenant.
     */
    public function isAvailable(): bool
    {
        try {
            $tenantId = $this->resolveTenantId();
            if (!$tenantId) {
                return false;
            }

            return $this->tokenService->hasTenantToken($tenantId, 'microsoft');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create a folder in OneDrive.
     *
     * @return array{external_id: string, external_url: string}
     */
    public function createFolder(string $folderName, ?string $parentExternalId = null): array
    {
        $accessToken = $this->getAccessToken();

        if ($parentExternalId) {
            $url = self::BASE_URL . "/me/drive/items/{$parentExternalId}/children";
        } else {
            $url = self::BASE_URL . '/me/drive/root/children';
        }

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->post($url, [
                'name' => $folderName,
                'folder' => new \stdClass(),
                '@microsoft.graph.conflictBehavior' => 'rename',
            ]);

        if (!$response->successful()) {
            Log::error('OneDrive create folder failed', [
                'folder_name' => $folderName,
                'parent_id' => $parentExternalId,
                'status' => $response->status(),
                'error' => $response->json('error', 'Unknown error'),
            ]);
            throw new \RuntimeException('Failed to create folder in OneDrive: ' . $response->json('error.message', 'Unknown error'));
        }

        $data = $response->json();

        return [
            'external_id' => $data['id'],
            'external_url' => $data['webUrl'] ?? '',
        ];
    }

    /**
     * Delete a folder from OneDrive.
     */
    public function deleteFolder(string $externalId): bool
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->delete(self::BASE_URL . "/me/drive/items/{$externalId}");

        if (!$response->successful() && $response->status() !== 404) {
            Log::error('OneDrive delete folder failed', [
                'external_id' => $externalId,
                'status' => $response->status(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Rename a file or folder in OneDrive.
     */
    public function renameItem(string $externalId, string $newName): bool
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->patch(self::BASE_URL . "/me/drive/items/{$externalId}", [
                'name' => $newName,
            ]);

        if (!$response->successful()) {
            Log::error('OneDrive rename item failed', [
                'external_id' => $externalId,
                'new_name' => $newName,
                'status' => $response->status(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Move a file or folder to a different parent in OneDrive.
     */
    public function moveItem(string $externalId, string $targetParentExternalId): bool
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->patch(self::BASE_URL . "/me/drive/items/{$externalId}", [
                'parentReference' => [
                    'id' => $targetParentExternalId,
                ],
            ]);

        if (!$response->successful()) {
            Log::error('OneDrive move item failed', [
                'external_id' => $externalId,
                'target_parent_id' => $targetParentExternalId,
                'status' => $response->status(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * List contents of a folder in OneDrive.
     *
     * @return array<int, array{name: string, type: string, external_id: string}>
     */
    public function listFolder(string $externalId): array
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->get(self::BASE_URL . "/me/drive/items/{$externalId}/children");

        if (!$response->successful()) {
            Log::error('OneDrive list folder failed', [
                'external_id' => $externalId,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException('Failed to list OneDrive folder contents.');
        }

        $items = [];
        foreach ($response->json('value', []) as $item) {
            $entry = [
                'name' => $item['name'],
                'type' => isset($item['folder']) ? 'folder' : 'file',
                'external_id' => $item['id'],
            ];

            if (!isset($item['folder'])) {
                $entry['size'] = $item['size'] ?? 0;
                $entry['mime_type'] = $item['file']['mimeType'] ?? 'application/octet-stream';
                $entry['web_url'] = $item['webUrl'] ?? '';
            }

            $items[] = $entry;
        }

        return $items;
    }

    /**
     * Simple upload for files <= 4MB.
     */
    private function simpleUpload(string $accessToken, UploadedFile $file, string $folderPath, string $filename): array
    {
        $encodedPath = rawurlencode($folderPath . '/' . $filename);

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->withBody($file->getContent(), $file->getClientMimeType())
            ->put(self::BASE_URL . "/me/drive/root:/{$encodedPath}:/content");

        if (!$response->successful()) {
            Log::error('OneDrive simple upload failed', [
                'status' => $response->status(),
                'error' => $response->json('error', 'Unknown error'),
            ]);
            throw new \RuntimeException('Failed to upload file to OneDrive: ' . $response->json('error.message', 'Unknown error'));
        }

        return $response->json();
    }

    /**
     * Resumable upload for files > 4MB using upload session.
     */
    private function resumableUpload(string $accessToken, UploadedFile $file, string $folderPath, string $filename): array
    {
        $encodedPath = rawurlencode($folderPath . '/' . $filename);

        // Create upload session
        $sessionResponse = Http::withToken($accessToken)
            ->timeout(30)
            ->post(self::BASE_URL . "/me/drive/root:/{$encodedPath}:/createUploadSession", [
                'item' => [
                    '@microsoft.graph.conflictBehavior' => 'rename',
                    'name' => $filename,
                ],
            ]);

        if (!$sessionResponse->successful()) {
            Log::error('OneDrive create upload session failed', [
                'status' => $sessionResponse->status(),
                'error' => $sessionResponse->json('error', 'Unknown error'),
            ]);
            throw new \RuntimeException('Failed to create OneDrive upload session.');
        }

        $uploadUrl = $sessionResponse->json('uploadUrl');
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

            if (!$response->successful()) {
                Log::error('OneDrive chunk upload failed', [
                    'offset' => $offset,
                    'status' => $response->status(),
                ]);
                throw new \RuntimeException('Failed to upload file chunk to OneDrive.');
            }

            $result = $response->json();
            $offset += $length;
        }

        return $result;
    }

    /**
     * Get a valid access token for the resolved tenant.
     */
    private function getAccessToken(): string
    {
        $tenantId = $this->resolveTenantId();

        if (!$tenantId) {
            throw new \RuntimeException('User must be authenticated with a tenant to use OneDrive storage.');
        }

        $token = $this->tokenService->getValidTenantToken($tenantId, 'microsoft');

        if (!$token) {
            throw new \RuntimeException('OneDrive is not connected for this organization. An administrator must connect the OneDrive account in Admin > OAuth Settings.');
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
