<?php

namespace App\Services\Case\BlockResolvers;

use App\Services\Case\GenerationContext;

/**
 * Spec 65 — Resolves the NNNN block (4-digit zero-padded sequence).
 *
 * The numeric value comes from `case_number_seq` (canonical column added
 * in 2026_06_01_000001). Falls back to 1 when no sequence has been set
 * yet so the resolver remains safe to call from preview / dummy contexts.
 */
final class SequenceBlockResolver implements BlockResolverInterface
{
    public function resolve(GenerationContext $ctx): string
    {
        return str_pad((string) ($ctx->sequence ?? 1), 4, '0', STR_PAD_LEFT);
    }
}
