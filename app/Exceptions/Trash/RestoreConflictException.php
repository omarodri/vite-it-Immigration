<?php

namespace App\Exceptions\Trash;

use Exception;

class RestoreConflictException extends Exception
{
    public function __construct(
        public readonly string $field,
        public readonly string $value,
        string $message = ''
    ) {
        parent::__construct($message ?: "Cannot restore: {$field} '{$value}' is already in use.");
    }
}
