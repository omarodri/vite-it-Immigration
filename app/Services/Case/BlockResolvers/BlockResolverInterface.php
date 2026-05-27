<?php

namespace App\Services\Case\BlockResolvers;

use App\Services\Case\GenerationContext;

/**
 * Spec 65 — Contract every case-code block resolver must implement.
 *
 * Each resolver is responsible for translating one block key (YY, TT, AAAA,
 * NNNN, NOMBRE) into its final string representation given a generation
 * context.
 */
interface BlockResolverInterface
{
    public function resolve(GenerationContext $ctx): string;
}
