<?php

namespace App\Services\Case\BlockResolvers;

use App\Services\Case\GenerationContext;

/**
 * Spec 65 — Resolves the TT block (two-letter case-type code).
 *
 * Uses {@see CaseType::$code} (canonical field in this codebase — there is
 * no `abbreviation` column). Truncates to 2 chars, uppercased, with a safe
 * fallback to `XX` if the code is empty for any reason.
 */
final class TypeBlockResolver implements BlockResolverInterface
{
    public function resolve(GenerationContext $ctx): string
    {
        $code = (string) ($ctx->caseType->code ?? '');
        if ($code === '') {
            return 'XX';
        }

        return strtoupper(mb_substr($code, 0, 2));
    }
}
