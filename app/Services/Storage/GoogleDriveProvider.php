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

class GoogleDriveProvider implements DocumentStorageInterface
{
    private const API_BASE = 'https://www.googleapis.com';

    private const SIMPLE_UPLOAD_MAX_SIZE = 5 * 1024 * 1024; // 5MB

    public function __construct(
        private readonly OAuthTokenService $tokenService,
        private readonly OAuthCredentialService $credentialService
    ) {}

    /**
     * Upload a file to Google Drive.
     *
     * @return array{storage_path: string, size: int, external_id: string, external_url: string}
     */
    public function upload(UploadedFile $file, string $destinationPath): array
    {
        $user = $this->getAuthenticatedUser();
        $accessToken = $this->getAccessToken($user);

        $filename = basename($destinationPath);

        if ($file->getSize() <= self::SIMPLE_UPLOAD_MAX_SIZE) {
            $result = $this->multipartUpload($accessToken, $file, $filename);
        } else {
            $result = $this->resumableUpload($accessToken, $file, $filename);
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
        $user = $this->getAuthenticatedUser();
        $accessToken = $this->getAccessToken($user);

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

        $user = $this->getAuthenticatedUser();
        $accessToken = $this->getAccessToken($user);

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

        $user = $this->getAuthenticatedUser();
        $accessToken = $this->getAccessToken($user);

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
     * Check if Google Drive is available for the current user.
     */
    public function isAvailable(): bool
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return false;
            }

            $tenant = $user->tenant;
            $credentials = $this->credentialService->getGoogleCredentials($tenant);

            if (!$credentials) {
                return false;
            }

            return $this->tokenService->hasValidToken($user, 'google');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Multipart upload for files <= 5MB.
     */
    private function multipartUpload(string $accessToken, UploadedFile $file, string $filename): array
    {
        $metadata = json_encode([
            'name' => $filename,
        ]);

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
    private function resumableUpload(string $accessToken, UploadedFile $file, string $filename): array
    {
        $metadata = [
            'name' => $filename,
        ];

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
     * Get the authenticated user.
     */
    private function getAuthenticatedUser(): User
    {
        $user = Auth::user();
        if (!$user) {
            throw new \RuntimeException('User must be authenticated to use Google Drive storage.');
        }

        return $user;
    }

    /**
     * Get a valid access token for the authenticated user.
     */
    private function getAccessToken(User $user): string
    {
        $token = $this->tokenService->getValidToken($user, 'google');

        if (!$token) {
            throw new \RuntimeException('No valid Google OAuth token found. Please connect your Google Drive account.');
        }

        return $token;
    }
}
