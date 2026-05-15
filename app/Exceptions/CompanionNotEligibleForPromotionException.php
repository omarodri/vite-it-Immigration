<?php

namespace App\Exceptions;

use RuntimeException;

class CompanionNotEligibleForPromotionException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly array $missing = []
    ) {
        parent::__construct($errorCode);
    }
}
