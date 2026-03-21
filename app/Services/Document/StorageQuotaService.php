<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Exceptions\StorageQuotaExceededException;
use App\Models\Document;
use App\Models\Tenant;

class StorageQuotaService
{
    /**
     * Check if the tenant has enough storage quota to upload a file.
     *
     * @param  int  $fileSize  File size in bytes.
     *
     * @throws StorageQuotaExceededException
     */
    public function checkQuota(Tenant $tenant, int $fileSize): void
    {
        $quotaBytes = (int) $tenant->storage_quota_mb * 1024 * 1024;
        $usedBytes = (int) $tenant->storage_used_bytes;
        $projectedUsage = $usedBytes + $fileSize;

        if ($projectedUsage > $quotaBytes) {
            throw new StorageQuotaExceededException(
                quotaMb: (float) $tenant->storage_quota_mb,
                usedMb: $this->bytesToMb($usedBytes),
                fileSizeMb: $this->bytesToMb($fileSize),
            );
        }
    }

    /**
     * Increment the tenant's storage usage.
     */
    public function addUsage(Tenant $tenant, int $bytes): void
    {
        $tenant->increment('storage_used_bytes', $bytes);
    }

    /**
     * Decrement the tenant's storage usage.
     */
    public function removeUsage(Tenant $tenant, int $bytes): void
    {
        $currentUsed = (int) $tenant->storage_used_bytes;
        $newUsed = max(0, $currentUsed - $bytes);

        $tenant->update(['storage_used_bytes' => $newUsed]);
    }

    /**
     * Recalculate the total storage used by a tenant from the documents table.
     *
     * @return int Total bytes used.
     */
    public function recalculateUsage(Tenant $tenant): int
    {
        $totalBytes = (int) Document::where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->sum('size');

        $tenant->update(['storage_used_bytes' => $totalBytes]);

        return $totalBytes;
    }

    private function bytesToMb(int $bytes): float
    {
        return round($bytes / (1024 * 1024), 2);
    }
}
