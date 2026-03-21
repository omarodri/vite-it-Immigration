<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Contracts\DocumentStorageInterface;
use App\Models\Document;
use App\Models\User;
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

    public function __construct(
        private readonly OAuthTokenService $tokenService,
        private readonly OAuthCredentialService $credentialService
    ) {}

    /**
     * Upload a file to OneDrive.
     *
     * @return array{storage_path: string, size: int, external_id: string, external_url: string}
     */
    public function upload(UploadedFile $file, string $destinationPath): array
    {
        $user = $this->getAuthenticatedUser();
        $accessToken = $this->getAccessToken($user);

        $filename = basename($destinationPath);
        $folderPath = dirname($destinationPath);

        if ($file->getSize() <= self::SIMPLE_UPLOAD_MAX_SIZE) {
            $result = $this->simpleUpload($accessToken, $file, $folderPath, $filename);
        } else {
            $result = $this->resumableUpload($accessToken, $file, $folderPath, $filename);
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
        $user = $this->getAuthenticatedUser();
        $accessToken = $this->getAccessToken($user);

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

        $user = $this->getAuthenticatedUser();
        $accessToken = $this->getAccessToken($user);

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

        $user = $this->getAuthenticatedUser();
        $accessToken = $this->getAccessToken($user);

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
     * Check if OneDrive is available for the current user.
     */
    public function isAvailable(): bool
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return false;
            }

            $tenant = $user->tenant;
            $credentials = $this->credentialService->getMicrosoftCredentials($tenant);

            if (!$credentials) {
                return false;
            }

            return $this->tokenService->hasValidToken($user, 'microsoft');
        } catch (\Exception $e) {
            return false;
        }
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
     * Get the authenticated user.
     */
    private function getAuthenticatedUser(): User
    {
        $user = Auth::user();
        if (!$user) {
            throw new \RuntimeException('User must be authenticated to use OneDrive storage.');
        }

        return $user;
    }

    /**
     * Get a valid access token for the authenticated user.
     */
    private function getAccessToken(User $user): string
    {
        $token = $this->tokenService->getValidToken($user, 'microsoft');

        if (!$token) {
            throw new \RuntimeException('No valid Microsoft OAuth token found. Please connect your OneDrive account.');
        }

        return $token;
    }
}
