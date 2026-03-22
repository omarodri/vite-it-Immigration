<?php

namespace App\Services\Document;

use App\Jobs\ScanDocumentForVirus;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\ImmigrationCase;
use App\Models\Tenant;
use App\Services\Storage\StorageProviderFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentService
{
    public function __construct(
        private readonly StorageProviderFactory $providerFactory,
        private readonly StorageQuotaService $storageQuotaService,
    ) {}

    /**
     * List documents for a case, optionally filtered by folder.
     */
    public function listDocuments(ImmigrationCase $case, ?int $folderId = null): Collection
    {
        $query = Document::byCase($case->id)
            ->with('uploader:id,name');

        if ($folderId !== null) {
            $query->byFolder($folderId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Upload a document to a case.
     */
    public function uploadDocument(ImmigrationCase $case, UploadedFile $file, array $data): Document
    {
        // Check storage quota before starting the transaction
        $tenant = $case->tenant;
        $this->storageQuotaService->checkQuota($tenant, (int) $file->getSize());

        return DB::transaction(function () use ($case, $file, $data, $tenant) {
            $provider = $this->providerFactory->make();

            $originalName = $file->getClientOriginalName();
            $storedName = $this->resolveUniqueFilename($case, $originalName);
            $destinationPath = "tenants/{$case->tenant_id}/cases/{$case->case_number}/{$storedName}";

            // For cloud storage, resolve the target folder's external_id so the file
            // is uploaded inside the case's OneDrive/GDrive folder, not at a raw path.
            $metadata = [];
            $parentExternalId = $this->resolveUploadParentExternalId($case, $data['folder_id'] ?? null);
            if ($parentExternalId) {
                $metadata['parent_external_id'] = $parentExternalId;
            }

            $storageResult = $provider->upload($file, $destinationPath, $metadata);

            // Determine storage type from tenant setting
            $storageType = Auth::user()?->tenant?->storage_type ?? 'local';
            $storageTypeConstant = match ($storageType) {
                'onedrive' => Document::STORAGE_ONEDRIVE,
                'google_drive' => Document::STORAGE_GOOGLE_DRIVE,
                default => Document::STORAGE_LOCAL,
            };

            $document = Document::create([
                'tenant_id' => $case->tenant_id,
                'case_id' => $case->id,
                'folder_id' => $data['folder_id'] ?? null,
                'uploaded_by' => Auth::id(),
                'name' => $storedName,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $storageResult['size'],
                'category' => $data['category'] ?? Document::CATEGORY_OTHER,
                'storage_type' => $storageTypeConstant,
                'storage_path' => $storageResult['storage_path'],
                'external_id' => $storageResult['external_id'] ?? null,
                'external_url' => $storageResult['external_url'] ?? null,
                'version' => 1,
                'checksum' => md5_file($file->getRealPath()),
            ]);

            // Track storage usage
            $this->storageQuotaService->addUsage($tenant, $storageResult['size']);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($document)
                ->withProperties([
                    'case_id' => $case->id,
                    'original_name' => $document->original_name,
                    'size' => $document->size,
                ])
                ->log('Uploaded document: ' . $document->original_name);

            ScanDocumentForVirus::dispatch($document->id);

            return $document->load('uploader:id,name');
        });
    }

    /**
     * Download a document.
     */
    public function downloadDocument(Document $document): StreamedResponse|string
    {
        $provider = $this->providerFactory->make($document->storage_type);

        return $provider->download($document);
    }

    /**
     * Preview a document inline (returns response with Content-Disposition: inline).
     * For cloud storage, proxies the file content through the server to avoid CORS issues.
     */
    public function previewDocument(Document $document): StreamedResponse|\Illuminate\Http\Response
    {
        $headers = [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="' . $document->original_name . '"',
            'Cache-Control' => 'private, max-age=3600',
        ];

        // For cloud-stored documents, proxy the content via download URL
        if ($document->storage_type !== Document::STORAGE_LOCAL && $document->external_id) {
            $provider = $this->providerFactory->make($document->storage_type);
            $downloadResult = $provider->download($document);

            // OneDrive/GoogleDrive download() returns a download URL string
            if (is_string($downloadResult)) {
                $content = \Illuminate\Support\Facades\Http::timeout(30)
                    ->withOptions(['stream' => false])
                    ->get($downloadResult);

                if ($content->successful()) {
                    return response($content->body(), 200, $headers);
                }

                throw new \RuntimeException('Failed to fetch file content from cloud provider.');
            }

            // If it's already a StreamedResponse, return as-is
            return $downloadResult;
        }

        // For local storage, stream the file inline
        $path = $document->storage_path;
        $disk = Storage::disk('local');

        return response()->stream(
            function () use ($disk, $path) {
                $stream = $disk->readStream($path);
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            200,
            $headers
        );
    }

    /**
     * Delete a document (soft delete + remove from storage).
     */
    public function deleteDocument(Document $document): bool
    {
        return DB::transaction(function () use ($document) {
            $provider = $this->providerFactory->make($document->storage_type);
            $provider->delete($document);

            // Release storage usage
            $tenant = $document->tenant ?? Tenant::find($document->tenant_id);
            if ($tenant) {
                $this->storageQuotaService->removeUsage($tenant, (int) $document->size);
            }

            activity()
                ->causedBy(Auth::user())
                ->performedOn($document)
                ->withProperties([
                    'case_id' => $document->case_id,
                    'original_name' => $document->original_name,
                ])
                ->log('Deleted document: ' . $document->original_name);

            return $document->delete();
        });
    }

    /**
     * Move a document to a different folder.
     */
    public function moveDocument(Document $document, int $targetFolderId): Document
    {
        $oldFolderId = $document->folder_id;

        $document->update(['folder_id' => $targetFolderId]);

        activity()
            ->causedBy(Auth::user())
            ->performedOn($document)
            ->withProperties([
                'old_folder_id' => $oldFolderId,
                'new_folder_id' => $targetFolderId,
            ])
            ->log('Moved document: ' . $document->original_name);

        return $document->fresh('uploader:id,name');
    }

    /**
     * Replace the file of an existing document (new version).
     */
    public function replaceDocument(Document $document, UploadedFile $file): Document
    {
        // Check quota for the delta (new file size minus old file size)
        $tenant = $document->tenant ?? Tenant::find($document->tenant_id);
        $oldSize = (int) $document->size;
        $newSize = (int) $file->getSize();
        if ($newSize > $oldSize && $tenant) {
            $this->storageQuotaService->checkQuota($tenant, $newSize - $oldSize);
        }

        return DB::transaction(function () use ($document, $file, $tenant, $oldSize) {
            $provider = $this->providerFactory->make($document->storage_type);

            // Delete old file from storage
            $provider->delete($document);

            // Upload new file using current tenant's storage provider
            $currentProvider = $this->providerFactory->make();
            $case = ImmigrationCase::findOrFail($document->case_id);
            $storedName = $this->resolveUniqueFilename($case, $file->getClientOriginalName());
            $destinationPath = "tenants/{$document->tenant_id}/cases/{$case->case_number}/{$storedName}";

            $metadata = [];
            $parentExternalId = $this->resolveUploadParentExternalId($case, $document->folder_id);
            if ($parentExternalId) {
                $metadata['parent_external_id'] = $parentExternalId;
            }

            $storageResult = $currentProvider->upload($file, $destinationPath, $metadata);

            // Determine current storage type
            $storageType = Auth::user()?->tenant?->storage_type ?? 'local';
            $storageTypeConstant = match ($storageType) {
                'onedrive' => Document::STORAGE_ONEDRIVE,
                'google_drive' => Document::STORAGE_GOOGLE_DRIVE,
                default => Document::STORAGE_LOCAL,
            };

            $document->update([
                'name' => $storedName,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $storageResult['size'],
                'storage_type' => $storageTypeConstant,
                'storage_path' => $storageResult['storage_path'],
                'external_id' => $storageResult['external_id'] ?? null,
                'external_url' => $storageResult['external_url'] ?? null,
                'version' => $document->version + 1,
                'checksum' => md5_file($file->getRealPath()),
            ]);

            // Update storage usage (delta between new and old file)
            if ($tenant) {
                $newFileSize = (int) $storageResult['size'];
                if ($newFileSize > $oldSize) {
                    $this->storageQuotaService->addUsage($tenant, $newFileSize - $oldSize);
                } elseif ($oldSize > $newFileSize) {
                    $this->storageQuotaService->removeUsage($tenant, $oldSize - $newFileSize);
                }
            }

            activity()
                ->causedBy(Auth::user())
                ->performedOn($document)
                ->withProperties([
                    'case_id' => $document->case_id,
                    'original_name' => $document->original_name,
                    'version' => $document->version,
                ])
                ->log('Replaced document: ' . $document->original_name . ' (v' . $document->version . ')');

            return $document->fresh('uploader:id,name');
        });
    }

    /**
     * Resolve the cloud folder external_id to use as upload parent.
     *
     * For cloud storage, files must be uploaded into the folder's external_id
     * (OneDrive item ID / Google Drive folder ID) rather than using a file-path.
     * Falls back to the case's root_external_folder_id if no folder is specified.
     * Returns null for local storage (path-based upload is correct).
     */
    private function resolveUploadParentExternalId(ImmigrationCase $case, ?int $folderId): ?string
    {
        $storageType = Auth::user()?->tenant?->storage_type ?? 'local';

        if (!in_array($storageType, ['onedrive', 'google_drive'], true)) {
            return null;
        }

        // If a specific folder is selected, use its external_id
        if ($folderId) {
            $folder = DocumentFolder::find($folderId);
            if ($folder && $folder->external_id) {
                return $folder->external_id;
            }
        }

        // Fall back to the case's root folder
        return $case->root_external_folder_id;
    }

    /**
     * Resolve a unique filename for a case to avoid collisions.
     *
     * If "report.pdf" already exists for the case, returns "report(1).pdf", etc.
     */
    private function resolveUniqueFilename(ImmigrationCase $case, string $originalName): string
    {
        $pathInfo = pathinfo($originalName);
        $baseName = $pathInfo['filename'];
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        $candidate = $originalName;
        $counter = 1;

        while (Document::where('case_id', $case->id)->where('name', $candidate)->exists()) {
            $candidate = "{$baseName}({$counter}){$extension}";
            $counter++;
        }

        return $candidate;
    }
}
