<?php

namespace App\Services\Document;

use App\Jobs\ScanDocumentForVirus;
use App\Models\Document;
use App\Models\ImmigrationCase;
use App\Models\Tenant;
use App\Services\Storage\StorageProviderFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

            $extension = $file->getClientOriginalExtension();
            $uniqueName = Str::uuid() . '.' . $extension;
            $destinationPath = "tenants/{$case->tenant_id}/cases/{$case->id}/{$uniqueName}";

            $storageResult = $provider->upload($file, $destinationPath);

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
                'name' => $uniqueName,
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
     * For cloud storage, redirects to the external URL if available.
     */
    public function previewDocument(Document $document): StreamedResponse|\Illuminate\Http\RedirectResponse
    {
        // For cloud-stored documents with an external URL, redirect to it
        if ($document->storage_type !== Document::STORAGE_LOCAL && $document->external_url) {
            return redirect($document->external_url);
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
            [
                'Content-Type' => $document->mime_type,
                'Content-Disposition' => 'inline; filename="' . $document->original_name . '"',
                'Cache-Control' => 'private, max-age=3600',
            ]
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
        return DB::transaction(function () use ($document, $file) {
            $provider = $this->providerFactory->make($document->storage_type);

            // Delete old file from storage
            $provider->delete($document);

            // Upload new file using current tenant's storage provider
            $currentProvider = $this->providerFactory->make();
            $extension = $file->getClientOriginalExtension();
            $uniqueName = Str::uuid() . '.' . $extension;
            $destinationPath = "tenants/{$document->tenant_id}/cases/{$document->case_id}/{$uniqueName}";

            $storageResult = $currentProvider->upload($file, $destinationPath);

            // Determine current storage type
            $storageType = Auth::user()?->tenant?->storage_type ?? 'local';
            $storageTypeConstant = match ($storageType) {
                'onedrive' => Document::STORAGE_ONEDRIVE,
                'google_drive' => Document::STORAGE_GOOGLE_DRIVE,
                default => Document::STORAGE_LOCAL,
            };

            $document->update([
                'name' => $uniqueName,
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
}
