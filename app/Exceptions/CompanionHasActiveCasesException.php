<?php

namespace App\Exceptions;

use App\Models\Companion;

class CompanionHasActiveCasesException extends \RuntimeException
{
    private int $activeCasesCount;

    public function __construct(string $companionName, int $activeCasesCount)
    {
        $this->activeCasesCount = $activeCasesCount;

        parent::__construct(
            "No se puede eliminar al acompañante '{$companionName}' porque está vinculado a {$activeCasesCount} expediente(s) activo(s)."
        );
    }

    public function getActiveCasesCount(): int
    {
        return $this->activeCasesCount;
    }

    public static function forCompanion(Companion $companion, int $activeCasesCount): self
    {
        return new self($companion->full_name, $activeCasesCount);
    }
}
