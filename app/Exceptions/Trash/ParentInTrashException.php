<?php

namespace App\Exceptions\Trash;

use Exception;

class ParentInTrashException extends Exception
{
    public function __construct(
        public readonly string $parentType,
        public readonly int $parentId,
        public readonly string $parentName,
        string $message = ''
    ) {
        parent::__construct($message ?: "Cannot restore: parent {$parentType} #{$parentId} is in trash.");
    }
}
