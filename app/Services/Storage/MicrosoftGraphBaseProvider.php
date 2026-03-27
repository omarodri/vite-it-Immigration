<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Contracts\DocumentStorageInterface;
use App\Models\Document;
use App\Services\OAuthTokenService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class MicrosoftGraphBaseProvider implements DocumentStorageInterface
{
    protected const BASE_URL = 'https://graph.microsoft.com/v1.0';

    private const SIMPLE_UPLOAD_MAX_SIZE = 4 * 1024 * 1024; // 4MB

    protected ?int $tenantId;

    public function __construct(
        protected readonly OAuthTokenService $tokenService,
        ?int $tenantId = null
    ) {
        $this->tenantId = $tenantId;
    }

    /**
     * Return the drive base path segment for the Microsoft Graph API.
     *
     * Examples:
     *  - OneDrive personal: "/me/drive"
     *  - SharePoint drive:  "/drives/{driveId}"
     */
    abstract protected function getDriveBasePath(): string;

    /**
     * Upload a file to Microsoft Graph drive.
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
                    ->put(self::BASE_URL . "{$this->getDriveBasePath()}/items/{$parentId}:/{$encodedName}:/content");

                if (!$response->successful()) {
                    Log::error('Microsoft Graph upload to folder failed', [
                        'status' => $response->status(),
                        'error' => $response->json('error', 'Unknown error'),
                    ]);
                    throw new \RuntimeException('Failed to upload file to cloud folder: ' . $response->json('error.message', 'Unknown error'));
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
     * Download a file from Microsoft Graph drive.
     */
    public function download(Document $document): StreamedResponse|string
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->get(self::BASE_URL . "{$this->getDriveBasePath()}/items/{$document->external_id}");

        if (!$response->successful()) {
            Log::error('Microsoft Graph download metadata failed', [
                'document_id' => $document->id,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException('Failed to download file from cloud storage.');
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
                    ->get(self::BASE_URL . "{$this->getDriveBasePath()}/items/{$document->external_id}/content");

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
     * Delete a file from Microsoft Graph drive.
     */
    public function delete(Document $document): bool
    {
        if (!$document->external_id) {
            return true;
        }

        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->delete(self::BASE_URL . "{$this->getDriveBasePath()}/items/{$document->external_id}");

        if (!$response->successful() && $response->status() !== 404) {
            Log::error('Microsoft Graph delete failed', [
                'document_id' => $document->id,
                'external_id' => $document->external_id,
                'status' => $response->status(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Move a file to a new path in Microsoft Graph drive.
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
            ->patch(self::BASE_URL . "{$this->getDriveBasePath()}/items/{$document->external_id}", [
                'name' => $newFilename,
                'parentReference' => [
                    'path' => "/drive/root:/{$newFolder}",
                ],
            ]);

        if (!$response->successful()) {
            Log::error('Microsoft Graph move failed', [
                'document_id' => $document->id,
                'external_id' => $document->external_id,
                'status' => $response->status(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Check if Microsoft Graph drive is available for the current tenant.
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
     * Create a folder in Microsoft Graph drive.
     *
     * @return array{external_id: string, external_url: string}
     */
    public function createFolder(string $folderName, ?string $parentExternalId = null): array
    {
        $accessToken = $this->getAccessToken();

        if ($parentExternalId) {
            $url = self::BASE_URL . "{$this->getDriveBasePath()}/items/{$parentExternalId}/children";
        } else {
            $url = self::BASE_URL . "{$this->getDriveBasePath()}/root/children";
        }

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->post($url, [
                'name' => $folderName,
                'folder' => new \stdClass(),
                '@microsoft.graph.conflictBehavior' => 'rename',
            ]);

        if (!$response->successful()) {
            Log::error('Microsoft Graph create folder failed', [
                'folder_name' => $folderName,
                'parent_id' => $parentExternalId,
                'status' => $response->status(),
                'error' => $response->json('error', 'Unknown error'),
            ]);
            throw new \RuntimeException('Failed to create folder in cloud storage: ' . $response->json('error.message', 'Unknown error'));
        }

        $data = $response->json();

        return [
            'external_id' => $data['id'],
            'external_url' => $data['webUrl'] ?? '',
        ];
    }

    /**
     * Delete a folder from Microsoft Graph drive.
     */
    public function deleteFolder(string $externalId): bool
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->delete(self::BASE_URL . "{$this->getDriveBasePath()}/items/{$externalId}");

        if (!$response->successful() && $response->status() !== 404) {
            Log::error('Microsoft Graph delete folder failed', [
                'external_id' => $externalId,
                'status' => $response->status(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Rename a file or folder in Microsoft Graph drive.
     */
    public function renameItem(string $externalId, string $newName): bool
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->patch(self::BASE_URL . "{$this->getDriveBasePath()}/items/{$externalId}", [
                'name' => $newName,
            ]);

        if (!$response->successful()) {
            Log::error('Microsoft Graph rename item failed', [
                'external_id' => $externalId,
                'new_name' => $newName,
                'status' => $response->status(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * Move a file or folder to a different parent in Microsoft Graph drive.
     */
    public function moveItem(string $externalId, string $targetParentExternalId): bool
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->patch(self::BASE_URL . "{$this->getDriveBasePath()}/items/{$externalId}", [
                'parentReference' => [
                    'id' => $targetParentExternalId,
                ],
            ]);

        if (!$response->successful()) {
            Log::error('Microsoft Graph move item failed', [
                'external_id' => $externalId,
                'target_parent_id' => $targetParentExternalId,
                'status' => $response->status(),
            ]);
            return false;
        }

        return true;
    }

    /**
     * List contents of a folder in Microsoft Graph drive.
     *
     * @return array<int, array{name: string, type: string, external_id: string}>
     */
    public function listFolder(string $externalId): array
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->get(self::BASE_URL . "{$this->getDriveBasePath()}/items/{$externalId}/children");

        if (!$response->successful()) {
            Log::error('Microsoft Graph list folder failed', [
                'external_id' => $externalId,
                'status' => $response->status(),
            ]);
            throw new \RuntimeException('Failed to list cloud folder contents.');
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
    protected function simpleUpload(string $accessToken, UploadedFile $file, string $folderPath, string $filename): array
    {
        $encodedPath = rawurlencode($folderPath . '/' . $filename);

        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->withBody($file->getContent(), $file->getClientMimeType())
            ->put(self::BASE_URL . "{$this->getDriveBasePath()}/root:/{$encodedPath}:/content");

        if (!$response->successful()) {
            Log::error('Microsoft Graph simple upload failed', [
                'status' => $response->status(),
                'error' => $response->json('error', 'Unknown error'),
            ]);
            throw new \RuntimeException('Failed to upload file to cloud storage: ' . $response->json('error.message', 'Unknown error'));
        }

        return $response->json();
    }

    /**
     * Resumable upload for files > 4MB using upload session.
     */
    protected function resumableUpload(string $accessToken, UploadedFile $file, string $folderPath, string $filename): array
    {
        $encodedPath = rawurlencode($folderPath . '/' . $filename);

        // Create upload session
        $sessionResponse = Http::withToken($accessToken)
            ->timeout(30)
            ->post(self::BASE_URL . "{$this->getDriveBasePath()}/root:/{$encodedPath}:/createUploadSession", [
                'item' => [
                    '@microsoft.graph.conflictBehavior' => 'rename',
                    'name' => $filename,
                ],
            ]);

        if (!$sessionResponse->successful()) {
            Log::error('Microsoft Graph create upload session failed', [
                'status' => $sessionResponse->status(),
                'error' => $sessionResponse->json('error', 'Unknown error'),
            ]);
            throw new \RuntimeException('Failed to create cloud upload session.');
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
                Log::error('Microsoft Graph chunk upload failed', [
                    'offset' => $offset,
                    'status' => $response->status(),
                ]);
                throw new \RuntimeException('Failed to upload file chunk to cloud storage.');
            }

            $result = $response->json();
            $offset += $length;
        }

        return $result;
    }

    /**
     * Get a valid access token for the resolved tenant.
     */
    protected function getAccessToken(): string
    {
        $tenantId = $this->resolveTenantId();

        if (!$tenantId) {
            throw new \RuntimeException('User must be authenticated with a tenant to use Microsoft Graph storage.');
        }

        $token = $this->tokenService->getValidTenantToken($tenantId, 'microsoft');

        if (!$token) {
            throw new \RuntimeException('Microsoft cloud storage is not connected for this organization. An administrator must connect the account in Admin > OAuth Settings.');
        }

        return $token;
    }

    /**
     * Get the resolved tenant ID.
     */
    public function getTenantId(): ?int
    {
        return $this->resolveTenantId();
    }

    /**
     * Resolve the tenant ID from the explicit property or the authenticated user.
     */
    protected function resolveTenantId(): ?int
    {
        if ($this->tenantId !== null) {
            return $this->tenantId;
        }

        $user = Auth::user();

        return $user?->tenant_id;
    }
}
