<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class StorageQuotaExceededException extends RuntimeException
{
    public function __construct(float $quotaMb, float $usedMb, float $fileSizeMb)
    {
        parent::__construct(
            sprintf(
                'Storage quota exceeded. Quota: %.2f MB, Used: %.2f MB, File size: %.2f MB. Free up space or request a quota increase.',
                $quotaMb,
                $usedMb,
                $fileSizeMb
            )
        );
    }
}
