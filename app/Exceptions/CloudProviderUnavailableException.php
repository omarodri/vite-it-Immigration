<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class CloudProviderUnavailableException extends RuntimeException
{
    public function __construct(string $provider)
    {
        parent::__construct(
            "Cloud storage provider [{$provider}] is temporarily unavailable. Please try again later or contact your administrator."
        );
    }
}
