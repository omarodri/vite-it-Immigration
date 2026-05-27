<?php

namespace App\Services\Case\BlockResolvers;

use App\Services\Case\GenerationContext;

/**
 * Spec 65 — Resolves the YY block (two-digit year).
 */
final class YearBlockResolver implements BlockResolverInterface
{
    public function resolve(GenerationContext $ctx): string
    {
        return now()->format('y');
    }
}
