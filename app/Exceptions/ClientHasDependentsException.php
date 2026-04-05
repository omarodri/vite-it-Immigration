<?php

namespace App\Exceptions;

use RuntimeException;

class ClientHasDependentsException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly int $count,
        string $message = ''
    ) {
        parent::__construct($message ?: "Client has active {$reason}: {$count}");
    }
}
