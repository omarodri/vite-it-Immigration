<?php

namespace App\Services\Case\BlockResolvers;

use App\Helpers\CaseCodeHelper;
use App\Services\Case\GenerationContext;

/**
 * Spec 65 — Resolves the AAAA block (4-char uppercase last-name slug).
 *
 * Delegates to the existing {@see CaseCodeHelper::normalizeLastName()} so
 * the transliteration / padding rules already used across the application
 * remain a single source of truth.
 */
final class LastNameBlockResolver implements BlockResolverInterface
{
    public function resolve(GenerationContext $ctx): string
    {
        return CaseCodeHelper::normalizeLastName($ctx->primaryLastName);
    }
}
